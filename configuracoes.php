<?php
/**
 * CONFIGURAÇÕES DO SISTEMA - INTEGRADA MAIS SAÚDE
 * Painel completo de administração e parametrização
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

// Verificar autenticação e permissões de admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Verificar se é admin
$user_id = $_SESSION['user_id'];
$user_query = $mysqli->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = $user_id");
$user = $user_query->fetch_assoc();

if (!$user || !in_array($user['role_name'], ['admin', 'Administrador'])) {
    header('Location: dashboard.php');
    exit;
}

// Processar salvamento de configurações
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    switch ($_POST['action']) {
        case 'create_backup':
            require_once 'functions.php';
            $backup_file = create_database_backup();
            
            if ($backup_file) {
                $success_message = "Backup criado com sucesso! Arquivo: " . basename($backup_file);
            } else {
                $error_message = "Erro ao criar backup. Verifique as configurações.";
            }
            break;
        case 'save_general':
            $clinic_name = $mysqli->real_escape_string($_POST['clinic_name']);
            $clinic_address = $mysqli->real_escape_string($_POST['clinic_address']);
            $clinic_phone = $mysqli->real_escape_string($_POST['clinic_phone']);
            $clinic_email = $mysqli->real_escape_string($_POST['clinic_email']);
            $clinic_nuit = $mysqli->real_escape_string($_POST['clinic_nuit']);
            
            $configs = [
                'clinic_name' => $clinic_name,
                'clinic_address' => $clinic_address,
                'clinic_phone' => $clinic_phone,
                'clinic_email' => $clinic_email,
                'clinic_nuit' => $clinic_nuit
            ];
            
            foreach ($configs as $key => $value) {
                $mysqli->query("INSERT INTO system_configs (config_key, config_value) VALUES ('$key', '$value') 
                               ON DUPLICATE KEY UPDATE config_value = '$value'");
            }
            $success_message = "Configurações gerais salvas com sucesso!";
            break;
            
        case 'save_financial':
            $default_payment_method = $mysqli->real_escape_string($_POST['default_payment_method']);
            $invoice_prefix = $mysqli->real_escape_string($_POST['invoice_prefix']);
            $enable_discounts = isset($_POST['enable_discounts']) ? 1 : 0;
            $max_discount_percent = floatval($_POST['max_discount_percent']);
            $late_payment_fee = floatval($_POST['late_payment_fee']);
            
            $configs = [
                'default_payment_method' => $default_payment_method,
                'invoice_prefix' => $invoice_prefix,
                'enable_discounts' => $enable_discounts,
                'max_discount_percent' => $max_discount_percent,
                'late_payment_fee' => $late_payment_fee
            ];
            
            foreach ($configs as $key => $value) {
                $mysqli->query("INSERT INTO system_configs (config_key, config_value) VALUES ('$key', '$value') 
                               ON DUPLICATE KEY UPDATE config_value = '$value'");
            }
            $success_message = "Configurações financeiras salvas com sucesso!";
            break;
            
        case 'save_appointments':
            $appointment_duration = intval($_POST['appointment_duration']);
            $working_hours_start = $mysqli->real_escape_string($_POST['working_hours_start']);
            $working_hours_end = $mysqli->real_escape_string($_POST['working_hours_end']);
            $max_appointments_per_day = intval($_POST['max_appointments_per_day']);
            $allow_same_day_booking = isset($_POST['allow_same_day_booking']) ? 1 : 0;
            $send_sms_reminder = isset($_POST['send_sms_reminder']) ? 1 : 0;
            $reminder_hours_before = intval($_POST['reminder_hours_before']);
            
            $configs = [
                'appointment_duration' => $appointment_duration,
                'working_hours_start' => $working_hours_start,
                'working_hours_end' => $working_hours_end,
                'max_appointments_per_day' => $max_appointments_per_day,
                'allow_same_day_booking' => $allow_same_day_booking,
                'send_sms_reminder' => $send_sms_reminder,
                'reminder_hours_before' => $reminder_hours_before
            ];
            
            foreach ($configs as $key => $value) {
                $mysqli->query("INSERT INTO system_configs (config_key, config_value) VALUES ('$key', '$value') 
                               ON DUPLICATE KEY UPDATE config_value = '$value'");
            }
            $success_message = "Configurações de consultas salvas com sucesso!";
            break;
            
        case 'save_laboratory':
            $lab_result_delay_hours = intval($_POST['lab_result_delay_hours']);
            $require_lab_validation = isset($_POST['require_lab_validation']) ? 1 : 0;
            $auto_print_results = isset($_POST['auto_print_results']) ? 1 : 0;
            $show_reference_values = isset($_POST['show_reference_values']) ? 1 : 0;
            
            $configs = [
                'lab_result_delay_hours' => $lab_result_delay_hours,
                'require_lab_validation' => $require_lab_validation,
                'auto_print_results' => $auto_print_results,
                'show_reference_values' => $show_reference_values
            ];
            
            foreach ($configs as $key => $value) {
                $mysqli->query("INSERT INTO system_configs (config_key, config_value) VALUES ('$key', '$value') 
                               ON DUPLICATE KEY UPDATE config_value = '$value'");
            }
            $success_message = "Configurações do laboratório salvas com sucesso!";
            break;
            
        case 'save_pharmacy':
            $low_stock_alert = intval($_POST['low_stock_alert']);
            $allow_negative_stock = isset($_POST['allow_negative_stock']) ? 1 : 0;
            $require_prescription = isset($_POST['require_prescription']) ? 1 : 0;
            $expiry_alert_days = intval($_POST['expiry_alert_days']);
            
            $configs = [
                'low_stock_alert' => $low_stock_alert,
                'allow_negative_stock' => $allow_negative_stock,
                'require_prescription' => $require_prescription,
                'expiry_alert_days' => $expiry_alert_days
            ];
            
            foreach ($configs as $key => $value) {
                $mysqli->query("INSERT INTO system_configs (config_key, config_value) VALUES ('$key', '$value') 
                               ON DUPLICATE KEY UPDATE config_value = '$value'");
            }
            $success_message = "Configurações da farmácia salvas com sucesso!";
            break;
            
        case 'save_system':
            $session_timeout = intval($_POST['session_timeout']);
            $enable_audit_log = isset($_POST['enable_audit_log']) ? 1 : 0;
            $enable_backup = isset($_POST['enable_backup']) ? 1 : 0;
            $backup_frequency = $mysqli->real_escape_string($_POST['backup_frequency']);
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            $records_per_page = intval($_POST['records_per_page']);
            
            $configs = [
                'session_timeout' => $session_timeout,
                'enable_audit_log' => $enable_audit_log,
                'enable_backup' => $enable_backup,
                'backup_frequency' => $backup_frequency,
                'maintenance_mode' => $maintenance_mode,
                'records_per_page' => $records_per_page
            ];
            
            foreach ($configs as $key => $value) {
                $mysqli->query("INSERT INTO system_configs (config_key, config_value) VALUES ('$key', '$value') 
                               ON DUPLICATE KEY UPDATE config_value = '$value'");
            }
            $success_message = "Configurações do sistema salvas com sucesso!";
            break;
    }
}

// Criar tabela de configurações se não existir
$mysqli->query("CREATE TABLE IF NOT EXISTS system_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Buscar configurações atuais
function getConfig($key, $default = '') {
    global $mysqli;
    $result = $mysqli->query("SELECT config_value FROM system_configs WHERE config_key = '$key'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['config_value'];
    }
    return $default;
}

$page_title = 'Configurações';
$module_name = 'Administração';
include 'includes/header.php';
?>

<style>
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .tab-button.active {
        border-color: #10b981 !important;
        color: #10b981 !important;
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="bi bi-gear-fill text-emerald-600 mr-3"></i>
                Configurações do Sistema
            </h1>
            <p class="text-gray-600 mt-2">Gerencie todos os parâmetros do sistema Integrada Mais Saúde</p>
        </div>
        <a href="dashboard.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center">
            <i class="bi bi-arrow-left mr-2"></i> Voltar
        </a>
    </div>

    <!-- Mensagens -->
    <?php if ($success_message): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg animate-fade-in" id="successAlert">
            <div class="flex items-center">
                <i class="bi bi-check-circle text-green-500 text-xl mr-3"></i>
                <p class="text-green-800 flex-1"><?= $success_message ?></p>
                <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="flex border-b border-gray-200 overflow-x-auto">
            <button onclick="switchTab('general')" id="tab-general" class="tab-button active px-6 py-4 text-sm font-medium border-b-2 transition whitespace-nowrap">
                <i class="bi bi-building mr-2"></i>Geral
            </button>
            <button onclick="switchTab('financial')" id="tab-financial" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 transition whitespace-nowrap">
                <i class="bi bi-cash-coin mr-2"></i>Financeiro
            </button>
            <button onclick="switchTab('appointments')" id="tab-appointments" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 transition whitespace-nowrap">
                <i class="bi bi-calendar-check mr-2"></i>Consultas
            </button>
            <button onclick="switchTab('laboratory')" id="tab-laboratory" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 transition whitespace-nowrap">
                <i class="bi bi-heart-pulse mr-2"></i>Laboratório
            </button>
            <button onclick="switchTab('pharmacy')" id="tab-pharmacy" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 transition whitespace-nowrap">
                <i class="bi bi-capsule mr-2"></i>Farmácia
            </button>
            <button onclick="switchTab('system')" id="tab-system" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 transition whitespace-nowrap">
                <i class="bi bi-server mr-2"></i>Sistema
            </button>
        </div>
    </div>

    <!-- GERAL -->
    <div class="tab-panel active" id="panel-general">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-building text-emerald-600 mr-2"></i>
                Configurações Gerais da Clínica
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_general">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Clínica *</label>
                        <input type="text" name="clinic_name" value="<?= htmlspecialchars(getConfig('clinic_name', 'Integrada Mais Saúde')) ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">NUIT</label>
                        <input type="text" name="clinic_nuit" value="<?= htmlspecialchars(getConfig('clinic_nuit', '')) ?>" 
                               placeholder="123456789" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Endereço Completo</label>
                    <textarea name="clinic_address" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"><?= htmlspecialchars(getConfig('clinic_address', 'Maputo, Moçambique')) ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Telefone Principal</label>
                        <input type="tel" name="clinic_phone" value="<?= htmlspecialchars(getConfig('clinic_phone', '+258 84 000 0000')) ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">E-mail de Contato</label>
                        <input type="email" name="clinic_email" value="<?= htmlspecialchars(getConfig('clinic_email', 'contato@integradamaissaude.co.mz')) ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex items-center">
                        <i class="bi bi-save mr-2"></i> Salvar Configurações Gerais
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FINANCEIRO -->
    <div class="tab-panel" id="panel-financial">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-cash-coin text-emerald-600 mr-2"></i>
                Configurações Financeiras
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_financial">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Método de Pagamento Padrão</label>
                        <select name="default_payment_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="dinheiro" <?= getConfig('default_payment_method') == 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                            <option value="mpesa" <?= getConfig('default_payment_method') == 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                            <option value="mkesh" <?= getConfig('default_payment_method') == 'mkesh' ? 'selected' : '' ?>>Mkesh</option>
                            <option value="cartao" <?= getConfig('default_payment_method') == 'cartao' ? 'selected' : '' ?>>Cartão</option>
                            <option value="transferencia" <?= getConfig('default_payment_method') == 'transferencia' ? 'selected' : '' ?>>Transferência</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prefixo de Faturas</label>
                        <input type="text" name="invoice_prefix" value="<?= htmlspecialchars(getConfig('invoice_prefix', 'FAT')) ?>" maxlength="10"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Ex: FAT-2024-0001</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Desconto Máximo Permitido (%)</label>
                        <input type="number" name="max_discount_percent" value="<?= getConfig('max_discount_percent', '10') ?>" min="0" max="100" step="0.1"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Taxa de Atraso (%)</label>
                        <input type="number" name="late_payment_fee" value="<?= getConfig('late_payment_fee', '2') ?>" min="0" max="100" step="0.1"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Taxa aplicada em pagamentos atrasados</p>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="enable_discounts" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('enable_discounts', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Permitir descontos em faturas</span>
                    </label>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex items-center">
                        <i class="bi bi-save mr-2"></i> Salvar Configurações Financeiras
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CONSULTAS -->
    <div class="tab-panel" id="panel-appointments">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-calendar-check text-emerald-600 mr-2"></i>
                Configurações de Consultas
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_appointments">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Duração da Consulta (min)</label>
                        <input type="number" name="appointment_duration" value="<?= getConfig('appointment_duration', '30') ?>" min="15" max="240" step="15"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Horário de Início</label>
                        <input type="time" name="working_hours_start" value="<?= getConfig('working_hours_start', '08:00') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Horário de Término</label>
                        <input type="time" name="working_hours_end" value="<?= getConfig('working_hours_end', '18:00') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Máximo de Consultas por Dia</label>
                        <input type="number" name="max_appointments_per_day" value="<?= getConfig('max_appointments_per_day', '50') ?>" min="1" max="200"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lembrete com Antecedência (horas)</label>
                        <input type="number" name="reminder_hours_before" value="<?= getConfig('reminder_hours_before', '24') ?>" min="1" max="168"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="space-y-3 mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_same_day_booking" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('allow_same_day_booking', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Permitir marcação no mesmo dia</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="send_sms_reminder" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('send_sms_reminder', '0') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Enviar lembrete por SMS</span>
                    </label>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex items-center">
                        <i class="bi bi-save mr-2"></i> Salvar Configurações de Consultas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LABORATÓRIO -->
    <div class="tab-panel" id="panel-laboratory">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-heart-pulse text-emerald-600 mr-2"></i>
                Configurações do Laboratório
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_laboratory">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prazo Padrão para Resultados (horas)</label>
                    <input type="number" name="lab_result_delay_hours" value="<?= getConfig('lab_result_delay_hours', '24') ?>" min="1" max="168"
                           class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    <p class="text-xs text-gray-500 mt-1">Tempo estimado para entrega de resultados</p>
                </div>

                <div class="space-y-3 mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="require_lab_validation" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('require_lab_validation', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Exigir validação técnica dos resultados</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="auto_print_results" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('auto_print_results', '0') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Imprimir resultados automaticamente após validação</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="show_reference_values" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('show_reference_values', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Exibir valores de referência nos resultados</span>
                    </label>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex items-center">
                        <i class="bi bi-save mr-2"></i> Salvar Configurações do Laboratório
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FARMÁCIA -->
    <div class="tab-panel" id="panel-pharmacy">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-capsule text-emerald-600 mr-2"></i>
                Configurações da Farmácia
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_pharmacy">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alerta de Estoque Baixo (unidades)</label>
                        <input type="number" name="low_stock_alert" value="<?= getConfig('low_stock_alert', '10') ?>" min="1" max="1000"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Quantidade mínima antes do alerta</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alerta de Validade (dias)</label>
                        <input type="number" name="expiry_alert_days" value="<?= getConfig('expiry_alert_days', '30') ?>" min="1" max="365"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Dias antes do vencimento para alertar</p>
                    </div>
                </div>

                <div class="space-y-3 mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_negative_stock" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('allow_negative_stock', '0') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Permitir estoque negativo</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="require_prescription" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('require_prescription', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Exigir prescrição médica para dispensar medicamentos</span>
                    </label>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex items-center">
                        <i class="bi bi-save mr-2"></i> Salvar Configurações da Farmácia
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SISTEMA -->
    <div class="tab-panel" id="panel-system">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-server text-emerald-600 mr-2"></i>
                Configurações do Sistema
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_system">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Timeout de Sessão (minutos)</label>
                        <input type="number" name="session_timeout" value="<?= getConfig('session_timeout', '30') ?>" min="5" max="480"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-gray-500 mt-1">Tempo de inatividade antes do logout automático</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Registros por Página</label>
                        <input type="number" name="records_per_page" value="<?= getConfig('records_per_page', '25') ?>" min="10" max="100"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Frequência de Backup</label>
                    <select name="backup_frequency" class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="diario" <?= getConfig('backup_frequency') == 'diario' ? 'selected' : '' ?>>Diário</option>
                        <option value="semanal" <?= getConfig('backup_frequency') == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="mensal" <?= getConfig('backup_frequency') == 'mensal' ? 'selected' : '' ?>>Mensal</option>
                    </select>
                </div>

                <div class="space-y-3 mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="enable_audit_log" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('enable_audit_log', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Ativar log de auditoria (rastrear todas as ações)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="enable_backup" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" <?= getConfig('enable_backup', '1') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Ativar backup automático</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="maintenance_mode" class="w-4 h-4 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500" <?= getConfig('maintenance_mode', '0') == '1' ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-yellow-700 font-medium">Modo de Manutenção (bloqueia acesso de não-admins)</span>
                    </label>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex items-center">
                        <i class="bi bi-save mr-2"></i> Salvar Configurações do Sistema
                    </button>
                </div>
            </form>
        </div>

        <!-- Informações do Sistema -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-info-circle text-blue-600 mr-2"></i>
                Informações do Sistema
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Versão do PHP:</span>
                    <span class="text-gray-600"><?= phpversion() ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Versão do MySQL:</span>
                    <span class="text-gray-600"><?= $mysqli->server_info ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Servidor Web:</span>
                    <span class="text-gray-600"><?= $_SERVER['SERVER_SOFTWARE'] ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Sistema Operacional:</span>
                    <span class="text-gray-600"><?= PHP_OS ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Espaço em Disco:</span>
                    <span class="text-gray-600"><?= round(disk_free_space("C:") / 1024 / 1024 / 1024, 2) ?> GB livres</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Memória PHP:</span>
                    <span class="text-gray-600"><?= ini_get('memory_limit') ?></span>
                </div>
            </div>
        </div>

        <!-- Ações do Sistema -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                <i class="bi bi-tools text-purple-600 mr-2"></i>
                Ações e Manutenção
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Criar Backup -->
                <form method="POST" action="" onsubmit="return confirm('Criar backup do banco de dados agora?')">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="w-full px-6 py-4 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex flex-col items-center">
                        <i class="bi bi-database-fill-down text-3xl mb-2"></i>
                        <span class="font-semibold">Criar Backup</span>
                        <span class="text-xs opacity-80">Backup manual do BD</span>
                    </button>
                </form>

                <!-- Ver Alertas -->
                <a href="system_alerts.php" class="w-full px-6 py-4 bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex flex-col items-center">
                    <i class="bi bi-bell-fill text-3xl mb-2"></i>
                    <span class="font-semibold">Ver Alertas</span>
                    <span class="text-xs opacity-80">Estoque e validade</span>
                </a>

                <!-- Ver Logs -->
                <a href="audit_logs.php" class="w-full px-6 py-4 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg shadow-md hover:shadow-lg transition flex flex-col items-center">
                    <i class="bi bi-file-earmark-text-fill text-3xl mb-2"></i>
                    <span class="font-semibold">Log de Auditoria</span>
                    <span class="text-xs opacity-80">Histórico de ações</span>
                </a>
            </div>
        </div>
    </div>

</div>

<script>
function switchTab(tabName) {
    // Remove active from all tabs
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('border-transparent', 'text-gray-600');
        btn.classList.remove('border-emerald-500', 'text-emerald-600');
    });
    
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    // Activate selected tab
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.add('active');
    activeTab.classList.remove('border-transparent', 'text-gray-600');
    activeTab.classList.add('border-emerald-500', 'text-emerald-600');
    
    // Show selected panel
    document.getElementById('panel-' + tabName).classList.add('active');
    
    // Save to localStorage
    localStorage.setItem('activeConfigTab', tabName);
}

// Restore active tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const activeTab = localStorage.getItem('activeConfigTab');
    if (activeTab) {
        switchTab(activeTab);
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('[id$="Alert"]').forEach(alert => alert.remove());
    }, 5000);
});
</script>

<?php include 'includes/footer.php'; ?>
