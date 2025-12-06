<?php
// Evitar redeclarações se o arquivo for incluído múltiplas vezes
if (!function_exists('csrf_token')) {

// Funções auxiliares para autenticação e CSRF
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Obter configuração do sistema
 */
function get_system_config($key, $default = null) {
    global $mysqli;
    
    if (!isset($mysqli) || !$mysqli->ping()) {
        return $default;
    }
    
    $key = $mysqli->real_escape_string($key);
    $result = $mysqli->query("SELECT config_value FROM system_configs WHERE config_key = '$key' LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['config_value'];
    }
    
    return $default;
}

/**
 * Definir configuração do sistema
 */
function set_system_config($key, $value) {
    global $mysqli;
    
    if (!isset($mysqli) || !$mysqli->ping()) {
        return false;
    }
    
    $key = $mysqli->real_escape_string($key);
    $value = $mysqli->real_escape_string($value);
    
    $query = "INSERT INTO system_configs (config_key, config_value) 
              VALUES ('$key', '$value') 
              ON DUPLICATE KEY UPDATE config_value = '$value'";
    
    return $mysqli->query($query);
}

/**
 * Verificar modo de manutenção
 */
function is_maintenance_mode() {
    $maintenance = get_system_config('maintenance_mode', '0');
    return $maintenance == '1' || $maintenance === '1';
}

/**
 * Verificar timeout de sessão
 */
function check_session_timeout() {
    $timeout = intval(get_system_config('session_timeout', '30')) * 60; // converter minutos para segundos
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > $timeout) {
            session_unset();
            session_destroy();
            header('Location: index.php?timeout=1');
            exit;
        }
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Verificar estoque baixo
 */
function check_low_stock() {
    global $mysqli;
    
    $alert_threshold = intval(get_system_config('low_stock_alert', '10'));
    
    $result = $mysqli->query("
        SELECT m.name, ps.quantity 
        FROM pharmacy_stock ps 
        JOIN medications m ON ps.medication_id = m.id 
        WHERE ps.quantity <= $alert_threshold
    ");
    
    $low_stock_items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $low_stock_items[] = $row;
        }
    }
    
    return $low_stock_items;
}

/**
 * Verificar medicamentos perto do vencimento
 */
function check_expiring_medications() {
    global $mysqli;
    
    $alert_days = intval(get_system_config('expiry_alert_days', '30'));
    
    // Verificar se a coluna expiry_date existe na tabela pharmacy_stock
    $columns_check = $mysqli->query("SHOW COLUMNS FROM pharmacy_stock LIKE 'expiry_date'");
    
    if ($columns_check && $columns_check->num_rows > 0) {
        // Coluna existe, usar query normal
        $result = $mysqli->query("
            SELECT m.name, ps.expiry_date, ps.quantity 
            FROM pharmacy_stock ps 
            JOIN medications m ON ps.medication_id = m.id 
            WHERE ps.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $alert_days DAY)
            AND ps.expiry_date >= CURDATE()
        ");
        
        $expiring_items = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $expiring_items[] = $row;
            }
        }
        
        return $expiring_items;
    }
    
    // Coluna não existe, retornar array vazio
    return [];
}

/**
 * Registrar log de auditoria
 */
function audit_log($user_id, $action, $description, $table_name = null, $record_id = null) {
    global $mysqli;
    
    // Verificar se auditoria está habilitada
    if (get_system_config('enable_audit_log', '1') != '1') {
        return true;
    }
    
    // Criar tabela de auditoria se não existir
    $mysqli->query("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        table_name VARCHAR(100),
        record_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(created_at),
        INDEX(table_name, record_id)
    )");
    
    $user_id = intval($user_id);
    $action = $mysqli->real_escape_string($action);
    $description = $mysqli->real_escape_string($description);
    $table_name = $table_name ? $mysqli->real_escape_string($table_name) : 'NULL';
    $record_id = $record_id ? intval($record_id) : 'NULL';
    $ip = $mysqli->real_escape_string($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $user_agent = $mysqli->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    
    $table_name_val = $table_name === 'NULL' ? 'NULL' : "'$table_name'";
    
    $query = "INSERT INTO audit_logs (user_id, action, description, table_name, record_id, ip_address, user_agent) 
              VALUES ($user_id, '$action', '$description', $table_name_val, $record_id, '$ip', '$user_agent')";
    
    return $mysqli->query($query);
}

/**
 * Formatar moeda MZN
 */
function format_currency($amount) {
    return number_format($amount, 2, ',', '.') . ' MT';
}

/**
 * Calcular idade a partir da data de nascimento
 */
function calculate_age($birth_date) {
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

/**
 * Gerar código único de paciente
 */
function generate_patient_code() {
    global $mysqli;
    
    // Buscar o último código
    $result = $mysqli->query("SELECT codigo FROM patients ORDER BY id DESC LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_code = $row['codigo'];
        
        // Extrair número (assumindo formato MS-0001)
        if (preg_match('/MS-(\d+)/', $last_code, $matches)) {
            $next_number = intval($matches[1]) + 1;
            return 'MS-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        }
    }
    
    // Se não há pacientes ou formato inválido, começar do 1
    return 'MS-0001';
}

/**
 * Gerar número de fatura
 */
function generate_invoice_number() {
    global $mysqli;
    
    $prefix = get_system_config('invoice_prefix', 'FAT');
    $year = date('Y');
    
    // Buscar última fatura do ano
    $result = $mysqli->query("
        SELECT invoice_number 
        FROM invoices 
        WHERE invoice_number LIKE '$prefix-$year-%' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_number = $row['invoice_number'];
        
        // Extrair número sequencial
        if (preg_match('/-(\d+)$/', $last_number, $matches)) {
            $next_number = intval($matches[1]) + 1;
            return $prefix . '-' . $year . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        }
    }
    
    // Primeira fatura do ano
    return $prefix . '-' . $year . '-0001';
}

/**
 * Enviar notificação SMS (integração futura)
 */
function send_sms_notification($phone, $message) {
    // Verificar se SMS está habilitado
    if (get_system_config('send_sms_reminder', '0') != '1') {
        return false;
    }
    
    // TODO: Integrar com provedor de SMS (ex: Vodacom, Movitel)
    // Por enquanto, apenas registrar no log
    audit_log(
        $_SESSION['user_id'] ?? 0,
        'SMS_SENT',
        "SMS enviado para $phone: $message",
        'notifications',
        null
    );
    
    return true;
}

/**
 * Criar backup do banco de dados
 */
function create_database_backup() {
    global $mysqli;
    
    // Verificar se backup está habilitado
    if (get_system_config('enable_backup', '1') != '1') {
        return false;
    }
    
    $backup_dir = __DIR__ . '/backups';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $filename = 'backup_' . date('Ymd_His') . '.sql';
    $filepath = $backup_dir . '/' . $filename;
    
    // Obter credenciais do config.php
    $db_host = DB_HOST;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
    $db_name = DB_NAME;
    
    // Comando mysqldump (assumindo XAMPP no Windows)
    $mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    
    if (file_exists($mysqldump_path)) {
        $command = "\"$mysqldump_path\" --host=$db_host --user=$db_user --password=$db_pass $db_name > \"$filepath\"";
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($filepath)) {
            audit_log(
                $_SESSION['user_id'] ?? 0,
                'BACKUP_CREATED',
                "Backup criado: $filename",
                'system',
                null
            );
            return $filepath;
        }
    }
    
    return false;
}

/**
 * Validar BI (Bilhete de Identidade) de Moçambique
 */
function validate_bi($bi) {
    // BI tem formato: 123456789A (9 dígitos + 1 letra)
    return preg_match('/^\d{9}[A-Z]$/', strtoupper($bi));
}

/**
 * Sanitizar entrada de usuário
 */
function sanitize_input($input) {
    global $mysqli;
    
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Criar notificação para usuário
 */
function create_notification($user_id, $title, $message, $type = 'info', $priority = 'normal', $url = null) {
    global $mysqli;
    
    // Criar tabela se não existir
    $mysqli->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('success', 'error', 'warning', 'info') DEFAULT 'info',
        priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
        url VARCHAR(500),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    )");
    
    $stmt = $mysqli->prepare("
        INSERT INTO notifications (user_id, title, message, type, priority, url)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('isssss', $user_id, $title, $message, $type, $priority, $url);
    return $stmt->execute();
}

/**
 * Criar notificação para múltiplos usuários
 */
function create_notification_for_users($user_ids, $title, $message, $type = 'info', $priority = 'normal', $url = null) {
    $success = true;
    foreach ($user_ids as $user_id) {
        if (!create_notification($user_id, $title, $message, $type, $priority, $url)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Criar notificação para todos os usuários de uma role
 */
function create_notification_for_role($role, $title, $message, $type = 'info', $priority = 'normal', $url = null) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE role = ?");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $user_ids = [];
    while ($row = $result->fetch_assoc()) {
        $user_ids[] = $row['id'];
    }
    
    return create_notification_for_users($user_ids, $title, $message, $type, $priority, $url);
}

/**
 * Criar notificação para todos os usuários
 */
function create_notification_for_all($title, $message, $type = 'info', $priority = 'normal', $url = null) {
    global $mysqli;
    
    $result = $mysqli->query("SELECT id FROM users WHERE active = 1");
    
    $user_ids = [];
    while ($row = $result->fetch_assoc()) {
        $user_ids[] = $row['id'];
    }
    
    return create_notification_for_users($user_ids, $title, $message, $type, $priority, $url);
}

/**
 * Notificar sobre novo agendamento
 */
function notify_new_appointment($patient_id, $doctor_id, $appointment_data) {
    $patient_name = $appointment_data['patient_name'] ?? 'Paciente';
    $date = $appointment_data['appointment_date'] ?? '';
    $time = $appointment_data['appointment_time'] ?? '';
    
    create_notification(
        $doctor_id,
        'Nova Consulta Agendada',
        "Consulta agendada para {$patient_name} em {$date} às {$time}",
        'info',
        'normal',
        '/MSLDA/consultorios/agenda.php'
    );
    
    create_notification(
        $patient_id,
        'Consulta Confirmada',
        "Sua consulta foi agendada para {$date} às {$time}",
        'success',
        'normal',
        '/MSLDA/portal_paciente.php'
    );
}

/**
 * Notificar sobre exame pronto
 */
function notify_exam_ready($patient_id, $exam_name) {
    create_notification(
        $patient_id,
        'Resultado de Exame Disponível',
        "O resultado do exame {$exam_name} está disponível",
        'success',
        'high',
        '/MSLDA/portal_paciente.php?tab=exames'
    );
}

/**
 * Notificar sobre medicamento com estoque baixo
 */
function notify_low_stock($medication_name, $current_stock) {
    create_notification_for_role(
        'farmacia',
        'Estoque Baixo',
        "O medicamento {$medication_name} está com estoque baixo: {$current_stock} unidades",
        'warning',
        'high',
        '/MSLDA/modules/farmacia/stocks.php'
    );
}

/**
 * Notificar sobre medicamento expirando
 */
function notify_expiring_medication($medication_name, $expiry_date) {
    create_notification_for_role(
        'farmacia',
        'Medicamento Próximo ao Vencimento',
        "O medicamento {$medication_name} vence em {$expiry_date}",
        'warning',
        'normal',
        '/MSLDA/modules/farmacia/stocks.php'
    );
}

/**
 * Notificar sobre fatura pendente
 */
function notify_pending_invoice($patient_id, $invoice_number, $amount) {
    $formatted_amount = format_currency($amount);
    
    create_notification(
        $patient_id,
        'Fatura Pendente',
        "Você possui uma fatura pendente #{$invoice_number} no valor de {$formatted_amount}",
        'warning',
        'normal',
        '/MSLDA/ver_fatura.php?id=' . $invoice_number
    );
}

/**
 * Notificar sobre pagamento recebido
 */
function notify_payment_received($patient_id, $invoice_number, $amount) {
    $formatted_amount = format_currency($amount);
    
    create_notification(
        $patient_id,
        'Pagamento Confirmado',
        "Pagamento de {$formatted_amount} confirmado para fatura #{$invoice_number}",
        'success',
        'normal',
        '/MSLDA/ver_fatura.php?id=' . $invoice_number
    );
}

} // Fim da verificação function_exists

