<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

// Usar a variável $mysqli do config.php
$conn = $mysqli;

$user_name = $_SESSION['user_name'] ?? 'Usuário';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_general') {
        $clinic_name = $_POST['clinic_name'] ?? '';
        $clinic_nuit = $_POST['clinic_nuit'] ?? '';
        $clinic_address = $_POST['clinic_address'] ?? '';
        $clinic_phone = $_POST['clinic_phone'] ?? '';
        $clinic_email = $_POST['clinic_email'] ?? '';
        $clinic_website = $_POST['clinic_website'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                VALUES (?, ?, 'general', NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        
        $settings = [
            'clinic_name' => $clinic_name,
            'clinic_nuit' => $clinic_nuit,
            'clinic_address' => $clinic_address,
            'clinic_phone' => $clinic_phone,
            'clinic_email' => $clinic_email,
            'clinic_website' => $clinic_website
        ];
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Configurações gerais atualizadas com sucesso!']);
        exit;
    }
    
    if ($action === 'save_financial') {
        $currency = $_POST['currency'] ?? 'MZN';
        $tax_rate = $_POST['tax_rate'] ?? '16';
        $payment_methods = $_POST['payment_methods'] ?? '';
        $invoice_prefix = $_POST['invoice_prefix'] ?? 'INV';
        $invoice_next_number = $_POST['invoice_next_number'] ?? '1';
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                VALUES (?, ?, 'financial', NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        
        $settings = [
            'currency' => $currency,
            'tax_rate' => $tax_rate,
            'payment_methods' => $payment_methods,
            'invoice_prefix' => $invoice_prefix,
            'invoice_next_number' => $invoice_next_number
        ];
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Configurações financeiras atualizadas com sucesso!']);
        exit;
    }
    
    if ($action === 'save_appointment') {
        $consultation_duration = $_POST['consultation_duration'] ?? '30';
        $working_hours_start = $_POST['working_hours_start'] ?? '08:00';
        $working_hours_end = $_POST['working_hours_end'] ?? '18:00';
        $working_days = $_POST['working_days'] ?? '';
        $max_appointments_per_day = $_POST['max_appointments_per_day'] ?? '20';
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                VALUES (?, ?, 'appointment', NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        
        $settings = [
            'consultation_duration' => $consultation_duration,
            'working_hours_start' => $working_hours_start,
            'working_hours_end' => $working_hours_end,
            'working_days' => $working_days,
            'max_appointments_per_day' => $max_appointments_per_day
        ];
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Configurações de agendamento atualizadas com sucesso!']);
        exit;
    }
    
    if ($action === 'reset_data') {
        $module = $_POST['module'] ?? '';
        $confirm_text = $_POST['confirm_text'] ?? '';
        
        if ($confirm_text !== 'RESETAR') {
            echo json_encode(['success' => false, 'message' => 'Texto de confirmação incorreto. Digite RESETAR para confirmar.']);
            exit;
        }
        
        $success = false;
        $message = '';
        
        try {
            // Desabilitar verificação de chaves estrangeiras temporariamente
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            switch ($module) {
                case 'appointments':
                    $conn->query("TRUNCATE TABLE consultation_history");
                    $conn->query("TRUNCATE TABLE exam_notifications");
                    $conn->query("TRUNCATE TABLE exam_results");
                    $conn->query("TRUNCATE TABLE exam_requests");
                    $conn->query("TRUNCATE TABLE attendances");
                    $conn->query("TRUNCATE TABLE appointments");
                    $message = 'Todos os agendamentos e consultas foram removidos.';
                    $success = true;
                    break;
                    
                case 'prescriptions':
                    $conn->query("TRUNCATE TABLE prescription_items");
                    $conn->query("TRUNCATE TABLE prescriptions");
                    $message = 'Todas as prescrições foram removidas.';
                    $success = true;
                    break;
                    
                case 'invoices':
                    $conn->query("TRUNCATE TABLE invoice_items");
                    $conn->query("TRUNCATE TABLE payments");
                    $conn->query("TRUNCATE TABLE invoices");
                    $message = 'Todas as faturas e pagamentos foram removidos.';
                    $success = true;
                    break;
                    
                case 'financial':
                    $conn->query("TRUNCATE TABLE expenses");
                    $conn->query("TRUNCATE TABLE invoice_items");
                    $conn->query("TRUNCATE TABLE payments");
                    $conn->query("TRUNCATE TABLE invoices");
                    $message = 'Todos os dados financeiros foram removidos.';
                    $success = true;
                    break;
                    
                case 'pharmacy':
                    $conn->query("TRUNCATE TABLE prescription_items");
                    $conn->query("TRUNCATE TABLE prescriptions");
                    $conn->query("UPDATE medications SET stock_quantity = 0");
                    $message = 'Dados da farmácia resetados (estoque zerado e prescrições removidas).';
                    $success = true;
                    break;
                    
                case 'laboratory':
                    $conn->query("TRUNCATE TABLE exam_notifications");
                    $conn->query("TRUNCATE TABLE exam_results");
                    $conn->query("TRUNCATE TABLE exam_requests");
                    $message = 'Todos os exames laboratoriais foram removidos.';
                    $success = true;
                    break;
                    
                case 'psychology':
                    $conn->query("DELETE FROM psychiatric_diagnoses WHERE diagnosis_type = 'psicologia'");
                    $conn->query("DELETE FROM consultation_history WHERE action_type IN ('diagnostico', 'observacao')");
                    $message = 'Dados de psicologia removidos.';
                    $success = true;
                    break;
                    
                case 'psychiatry':
                    $conn->query("DELETE FROM psychiatric_diagnoses WHERE diagnosis_type = 'psiquiatria'");
                    $conn->query("TRUNCATE TABLE prescription_items");
                    $conn->query("TRUNCATE TABLE prescriptions");
                    $message = 'Dados de psiquiatria e prescrições removidos.';
                    $success = true;
                    break;
                    
                case 'all_clinical':
                    $conn->query("TRUNCATE TABLE consultation_history");
                    $conn->query("TRUNCATE TABLE exam_notifications");
                    $conn->query("TRUNCATE TABLE exam_results");
                    $conn->query("TRUNCATE TABLE exam_requests");
                    $conn->query("TRUNCATE TABLE prescription_items");
                    $conn->query("TRUNCATE TABLE prescriptions");
                    $conn->query("TRUNCATE TABLE psychiatric_diagnoses");
                    $conn->query("TRUNCATE TABLE attendances");
                    $conn->query("TRUNCATE TABLE appointments");
                    $message = 'TODOS os dados clínicos foram removidos (consultas, exames, prescrições, diagnósticos).';
                    $success = true;
                    break;
                    
                default:
                    $message = 'Módulo inválido selecionado.';
                    break;
            }
            
            // Reabilitar verificação de chaves estrangeiras
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            
        } catch (Exception $e) {
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $message = 'Erro ao resetar dados: ' . $e->getMessage();
            $success = false;
        }
        
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    
    if ($action === 'save_notification') {
        $email_notifications = $_POST['email_notifications'] ?? '0';
        $sms_notifications = $_POST['sms_notifications'] ?? '0';
        $whatsapp_notifications = $_POST['whatsapp_notifications'] ?? '0';
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = $_POST['smtp_port'] ?? '587';
        $smtp_user = $_POST['smtp_user'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                VALUES (?, ?, 'notification', NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        
        $settings = [
            'email_notifications' => $email_notifications,
            'sms_notifications' => $sms_notifications,
            'whatsapp_notifications' => $whatsapp_notifications,
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_user' => $smtp_user,
            'smtp_password' => $smtp_password
        ];
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Configurações de notificações atualizadas com sucesso!']);
        exit;
    }
    
    if ($action === 'save_security') {
        $session_timeout = $_POST['session_timeout'] ?? '30';
        $password_min_length = $_POST['password_min_length'] ?? '8';
        $password_require_special = $_POST['password_require_special'] ?? '1';
        $max_login_attempts = $_POST['max_login_attempts'] ?? '5';
        $enable_2fa = $_POST['enable_2fa'] ?? '0';
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                VALUES (?, ?, 'security', NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        
        $settings = [
            'session_timeout' => $session_timeout,
            'password_min_length' => $password_min_length,
            'password_require_special' => $password_require_special,
            'max_login_attempts' => $max_login_attempts,
            'enable_2fa' => $enable_2fa
        ];
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Configurações de segurança atualizadas com sucesso!']);
        exit;
    }

    // Upload do logo da clínica
    if ($action === 'upload_logo') {
        if (empty($_FILES['company_logo']) || $_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['erro_msg'] = 'Nenhum arquivo enviado ou erro no envio.';
            header('Location: configuracoes.php');
            exit;
        }

        $file = $_FILES['company_logo'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];

        if (!isset($allowed[$mime])) {
            $_SESSION['erro_msg'] = 'Formato inválido. Envie PNG, JPG ou SVG.';
            header('Location: configuracoes.php');
            exit;
        }

        $ext = $allowed[$mime];
        $destDir = __DIR__ . '/assets';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'company_logo.' . $ext;
        $dest = $destDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $_SESSION['erro_msg'] = 'Erro ao salvar o arquivo no servidor.';
            header('Location: configuracoes.php');
            exit;
        }

        // Salvar caminho relativo nas configurações (system_settings)
        $relpath = 'assets/' . $filename;
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) VALUES (?, ?, 'financial', NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $key = 'company_logo';
        $value = $relpath;
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();

        $_SESSION['sucesso_msg'] = 'Logo atualizado com sucesso!';
        header('Location: configuracoes.php');
        exit;
    }
}

// Buscar configurações atuais
$result = $conn->query("SELECT setting_key, setting_value, category FROM system_settings");
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['category']][$row['setting_key']] = $row['setting_value'];
}

// Valores padrão
$general = $settings['general'] ?? [];
$financial = $settings['financial'] ?? [];
$appointment = $settings['appointment'] ?? [];
$notification = $settings['notification'] ?? [];
$security = $settings['security'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Sistema | Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.9); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
    <!-- Navbar -->
    <nav class="glass-effect shadow-xl border-b border-indigo-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3">
                        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 p-2 rounded-xl shadow-lg">
                            <i class="bi bi-gear-fill text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                                Configurações do Sistema
                            </h1>
                            <p class="text-xs text-gray-500">Parâmetros e preferências</p>
                        </div>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Usuário</p>
                        <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                    </div>
                    <a href="dashboard.php" class="px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg hover:from-indigo-600 hover:to-purple-700 transition-all shadow-lg">
                        <i class="bi bi-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar de Tabs -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-2xl shadow-xl border-2 border-indigo-200 p-4 sticky top-24">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <i class="bi bi-list-ul mr-2 text-indigo-600"></i>
                        Categorias
                    </h3>
                    <div class="space-y-2">
                        <button onclick="switchTab('general')" class="tab-btn w-full text-left px-4 py-3 rounded-xl hover:bg-indigo-100 transition-all flex items-center space-x-3 active" data-tab="general">
                            <i class="bi bi-info-circle text-indigo-600 text-xl"></i>
                            <span class="font-semibold">Geral</span>
                        </button>
                        <button onclick="switchTab('financial')" class="tab-btn w-full text-left px-4 py-3 rounded-xl hover:bg-emerald-100 transition-all flex items-center space-x-3" data-tab="financial">
                            <i class="bi bi-cash-coin text-emerald-600 text-xl"></i>
                            <span class="font-semibold">Financeiro</span>
                        </button>
                        <button onclick="switchTab('appointment')" class="tab-btn w-full text-left px-4 py-3 rounded-xl hover:bg-blue-100 transition-all flex items-center space-x-3" data-tab="appointment">
                            <i class="bi bi-calendar-check text-blue-600 text-xl"></i>
                            <span class="font-semibold">Agendamento</span>
                        </button>
                        <button onclick="switchTab('notification')" class="tab-btn w-full text-left px-4 py-3 rounded-xl hover:bg-yellow-100 transition-all flex items-center space-x-3" data-tab="notification">
                            <i class="bi bi-bell text-yellow-600 text-xl"></i>
                            <span class="font-semibold">Notificações</span>
                        </button>
                        <button onclick="switchTab('security')" class="tab-btn w-full text-left px-4 py-3 rounded-xl hover:bg-red-100 transition-all flex items-center space-x-3" data-tab="security">
                            <i class="bi bi-shield-lock text-red-600 text-xl"></i>
                            <span class="font-semibold">Segurança</span>
                        </button>
                        <button onclick="switchTab('data-management')" class="tab-btn w-full text-left px-4 py-3 rounded-xl hover:bg-purple-100 transition-all flex items-center space-x-3" data-tab="data-management">
                            <i class="bi bi-database text-purple-600 text-xl"></i>
                            <span class="font-semibold">Gerenciamento de Dados</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Conteúdo das Tabs -->
            <div class="lg:col-span-3">
                <!-- Tab: Geral -->
                <div id="general" class="tab-content active">
                    <div class="glass-effect rounded-2xl shadow-xl border-2 border-indigo-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="bi bi-info-circle mr-3 text-indigo-600"></i>
                                Configurações Gerais
                            </h2>
                        </div>
                        
                        <form id="formGeneral" class="space-y-6">
                            <input type="hidden" name="action" value="save_general">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-building mr-2 text-indigo-600"></i>Nome da Clínica
                                    </label>
                                    <input type="text" name="clinic_name" value="<?= htmlspecialchars($general['clinic_name'] ?? 'Mais Saúde Integrada') ?>" 
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" required>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-file-text mr-2 text-indigo-600"></i>NUIT
                                    </label>
                                    <input type="text" name="clinic_nuit" value="<?= htmlspecialchars($general['clinic_nuit'] ?? '') ?>" 
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-geo-alt mr-2 text-indigo-600"></i>Endereço
                                </label>
                                <textarea name="clinic_address" rows="3" 
                                          class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"><?= htmlspecialchars($general['clinic_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-telephone mr-2 text-indigo-600"></i>Telefone
                                    </label>
                                    <input type="text" name="clinic_phone" value="<?= htmlspecialchars($general['clinic_phone'] ?? '') ?>" 
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-envelope mr-2 text-indigo-600"></i>Email
                                    </label>
                                    <input type="email" name="clinic_email" value="<?= htmlspecialchars($general['clinic_email'] ?? '') ?>" 
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-globe mr-2 text-indigo-600"></i>Website
                                    </label>
                                    <input type="url" name="clinic_website" value="<?= htmlspecialchars($general['clinic_website'] ?? '') ?>" 
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl hover:from-indigo-600 hover:to-purple-700 transition-all shadow-lg font-semibold">
                                    <i class="bi bi-save mr-2"></i>Salvar Alterações
                                </button>
                            </div>
                        </form>
                        <!-- Upload do Logo da Clínica -->
                        <div class="mt-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-3">Logo da Clínica</h3>
                            <div class="flex items-center space-x-6">
                                <div>
                                    <?php if (!empty($financial['company_logo'])): ?>
                                        <img src="<?= htmlspecialchars($financial['company_logo']) ?>" alt="Logo" style="height:80px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.08);">
                                    <?php else: ?>
                                        <div class="w-40 h-20 bg-gray-100 rounded-lg flex items-center justify-center text-sm text-gray-400">Sem logo</div>
                                    <?php endif; ?>
                                </div>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_logo">
                                    <div class="flex items-center space-x-3">
                                        <input type="file" name="company_logo" accept="image/png,image/jpeg,image/svg+xml" required class="block" />
                                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl hover:from-emerald-600 hover:to-teal-700 transition-all">Enviar Logo</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Financeiro -->
                <div id="financial" class="tab-content">
                    <div class="glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="bi bi-cash-coin mr-3 text-emerald-600"></i>
                                Configurações Financeiras
                            </h2>
                        </div>
                        
                        <form id="formFinancial" class="space-y-6">
                            <input type="hidden" name="action" value="save_financial">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-currency-exchange mr-2 text-emerald-600"></i>Moeda Padrão
                                    </label>
                                    <select name="currency" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all">
                                        <option value="MZN" <?= ($financial['currency'] ?? 'MZN') === 'MZN' ? 'selected' : '' ?>>MZN - Metical</option>
                                        <option value="USD" <?= ($financial['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD - Dólar</option>
                                        <option value="EUR" <?= ($financial['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-percent mr-2 text-emerald-600"></i>Taxa de IVA (%)
                                    </label>
                                    <input type="number" name="tax_rate" value="<?= htmlspecialchars($financial['tax_rate'] ?? '16') ?>" step="0.01" min="0" max="100"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-credit-card mr-2 text-emerald-600"></i>Métodos de Pagamento (separados por vírgula)
                                </label>
                                <input type="text" name="payment_methods" value="<?= htmlspecialchars($financial['payment_methods'] ?? 'Dinheiro,Cartão,Transferência,M-Pesa') ?>" 
                                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" 
                                       placeholder="Ex: Dinheiro,Cartão,Transferência">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-file-earmark-text mr-2 text-emerald-600"></i>Prefixo de Fatura
                                    </label>
                                    <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($financial['invoice_prefix'] ?? 'INV') ?>" 
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-hash mr-2 text-emerald-600"></i>Próximo Número de Fatura
                                    </label>
                                    <input type="number" name="invoice_next_number" value="<?= htmlspecialchars($financial['invoice_next_number'] ?? '1') ?>" min="1"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all">
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl hover:from-emerald-600 hover:to-teal-700 transition-all shadow-lg font-semibold">
                                    <i class="bi bi-save mr-2"></i>Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Agendamento -->
                <div id="appointment" class="tab-content">
                    <div class="glass-effect rounded-2xl shadow-xl border-2 border-blue-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="bi bi-calendar-check mr-3 text-blue-600"></i>
                                Configurações de Agendamento
                            </h2>
                        </div>
                        
                        <form id="formAppointment" class="space-y-6">
                            <input type="hidden" name="action" value="save_appointment">
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-clock mr-2 text-blue-600"></i>Duração da Consulta (min)
                                    </label>
                                    <input type="number" name="consultation_duration" value="<?= htmlspecialchars($appointment['consultation_duration'] ?? '30') ?>" min="15" step="15"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-sunrise mr-2 text-blue-600"></i>Horário de Início
                                    </label>
                                    <input type="time" name="working_hours_start" value="<?= htmlspecialchars($appointment['working_hours_start'] ?? '08:00') ?>"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-sunset mr-2 text-blue-600"></i>Horário de Término
                                    </label>
                                    <input type="time" name="working_hours_end" value="<?= htmlspecialchars($appointment['working_hours_end'] ?? '18:00') ?>"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-calendar-week mr-2 text-blue-600"></i>Dias de Funcionamento (separados por vírgula)
                                </label>
                                <input type="text" name="working_days" value="<?= htmlspecialchars($appointment['working_days'] ?? 'Segunda,Terça,Quarta,Quinta,Sexta') ?>" 
                                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                       placeholder="Ex: Segunda,Terça,Quarta,Quinta,Sexta">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-people mr-2 text-blue-600"></i>Máximo de Agendamentos por Dia
                                </label>
                                <input type="number" name="max_appointments_per_day" value="<?= htmlspecialchars($appointment['max_appointments_per_day'] ?? '20') ?>" min="1"
                                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 transition-all shadow-lg font-semibold">
                                    <i class="bi bi-save mr-2"></i>Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Notificações -->
                <div id="notification" class="tab-content">
                    <div class="glass-effect rounded-2xl shadow-xl border-2 border-yellow-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="bi bi-bell mr-3 text-yellow-600"></i>
                                Configurações de Notificações
                            </h2>
                        </div>
                        
                        <form id="formNotification" class="space-y-6">
                            <input type="hidden" name="action" value="save_notification">
                            
                            <div class="bg-yellow-50 border-2 border-yellow-300 rounded-xl p-4 mb-6">
                                <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <i class="bi bi-toggles mr-2 text-yellow-600"></i>
                                    Canais de Notificação
                                </h3>
                                <div class="space-y-3">
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="checkbox" name="email_notifications" value="1" <?= ($notification['email_notifications'] ?? '0') === '1' ? 'checked' : '' ?>
                                               class="w-5 h-5 rounded border-2 border-yellow-300 text-yellow-600 focus:ring-2 focus:ring-yellow-200">
                                        <span class="font-semibold text-gray-700">Notificações por Email</span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="checkbox" name="sms_notifications" value="1" <?= ($notification['sms_notifications'] ?? '0') === '1' ? 'checked' : '' ?>
                                               class="w-5 h-5 rounded border-2 border-yellow-300 text-yellow-600 focus:ring-2 focus:ring-yellow-200">
                                        <span class="font-semibold text-gray-700">Notificações por SMS</span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="checkbox" name="whatsapp_notifications" value="1" <?= ($notification['whatsapp_notifications'] ?? '0') === '1' ? 'checked' : '' ?>
                                               class="w-5 h-5 rounded border-2 border-yellow-300 text-yellow-600 focus:ring-2 focus:ring-yellow-200">
                                        <span class="font-semibold text-gray-700">Notificações por WhatsApp</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-4">
                                <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <i class="bi bi-envelope-at mr-2 text-blue-600"></i>
                                    Configurações SMTP (Email)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Host SMTP</label>
                                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($notification['smtp_host'] ?? '') ?>" 
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Porta SMTP</label>
                                        <input type="number" name="smtp_port" value="<?= htmlspecialchars($notification['smtp_port'] ?? '587') ?>" 
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Usuário SMTP</label>
                                        <input type="email" name="smtp_user" value="<?= htmlspecialchars($notification['smtp_user'] ?? '') ?>" 
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                               placeholder="email@example.com">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Senha SMTP</label>
                                        <input type="password" name="smtp_password" value="<?= htmlspecialchars($notification['smtp_password'] ?? '') ?>" 
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                               placeholder="••••••••">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-orange-600 text-white rounded-xl hover:from-yellow-600 hover:to-orange-700 transition-all shadow-lg font-semibold">
                                    <i class="bi bi-save mr-2"></i>Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Segurança -->
                <div id="security" class="tab-content">
                    <div class="glass-effect rounded-2xl shadow-xl border-2 border-red-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="bi bi-shield-lock mr-3 text-red-600"></i>
                                Configurações de Segurança
                            </h2>
                        </div>
                        
                        <form id="formSecurity" class="space-y-6">
                            <input type="hidden" name="action" value="save_security">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-hourglass-split mr-2 text-red-600"></i>Timeout de Sessão (minutos)
                                    </label>
                                    <input type="number" name="session_timeout" value="<?= htmlspecialchars($security['session_timeout'] ?? '30') ?>" min="5"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-key mr-2 text-red-600"></i>Tamanho Mínimo de Senha
                                    </label>
                                    <input type="number" name="password_min_length" value="<?= htmlspecialchars($security['password_min_length'] ?? '8') ?>" min="6"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="bi bi-exclamation-triangle mr-2 text-red-600"></i>Máximo de Tentativas de Login
                                    </label>
                                    <input type="number" name="max_login_attempts" value="<?= htmlspecialchars($security['max_login_attempts'] ?? '5') ?>" min="3"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all">
                                </div>
                                
                                <div>
                                    <label class="flex items-center space-x-3 cursor-pointer pt-8">
                                        <input type="checkbox" name="password_require_special" value="1" <?= ($security['password_require_special'] ?? '1') === '1' ? 'checked' : '' ?>
                                               class="w-5 h-5 rounded border-2 border-red-300 text-red-600 focus:ring-2 focus:ring-red-200">
                                        <span class="font-semibold text-gray-700">Exigir Caracteres Especiais</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="bg-red-50 border-2 border-red-300 rounded-xl p-4">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" name="enable_2fa" value="1" <?= ($security['enable_2fa'] ?? '0') === '1' ? 'checked' : '' ?>
                                           class="w-5 h-5 rounded border-2 border-red-300 text-red-600 focus:ring-2 focus:ring-red-200">
                                    <div>
                                        <span class="font-bold text-gray-800 block">Autenticação de Dois Fatores (2FA)</span>
                                        <span class="text-sm text-gray-600">Adiciona uma camada extra de segurança ao login</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl hover:from-red-600 hover:to-pink-700 transition-all shadow-lg font-semibold">
                                    <i class="bi bi-save mr-2"></i>Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Gerenciamento de Dados -->
                <div id="data-management" class="tab-content">
                    <div class="glass-effect rounded-2xl shadow-xl border-2 border-purple-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="bi bi-database mr-3 text-purple-600"></i>
                                Gerenciamento de Dados
                            </h2>
                        </div>
                        
                        <!-- Aviso de Perigo -->
                        <div class="bg-red-100 border-l-4 border-red-600 p-6 mb-6 rounded-lg">
                            <div class="flex items-start">
                                <i class="bi bi-exclamation-triangle-fill text-red-600 text-3xl mr-4"></i>
                                <div>
                                    <h3 class="text-xl font-bold text-red-800 mb-2">⚠️ ATENÇÃO - OPERAÇÃO IRREVERSÍVEL</h3>
                                    <p class="text-red-700 mb-2">
                                        As operações de reset abaixo irão <strong>APAGAR PERMANENTEMENTE</strong> os dados selecionados do sistema.
                                    </p>
                                    <ul class="list-disc list-inside text-red-700 text-sm space-y-1">
                                        <li>Esta ação NÃO PODE ser desfeita</li>
                                        <li>Faça backup do banco de dados antes de prosseguir</li>
                                        <li>Todos os dados relacionados serão removidos em cascata</li>
                                        <li>Use apenas em ambiente de testes ou após aprovação da direção</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Reset: Agendamentos e Consultas -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-calendar-x text-blue-600 text-2xl mr-3"></i>
                                            Agendamentos e Consultas
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove todos os agendamentos, consultas, atendimentos e histórico clínico.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> appointments, attendances, consultation_history, exam_requests, exam_results, exam_notifications
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('appointments')" 
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Prescrições -->
                            <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-prescription2 text-purple-600 text-2xl mr-3"></i>
                                            Prescrições Médicas
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove todas as prescrições e medicamentos prescritos.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> prescriptions, prescription_items
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('prescriptions')" 
                                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Faturas -->
                            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-receipt text-green-600 text-2xl mr-3"></i>
                                            Faturas e Pagamentos
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove todas as faturas, itens de fatura e pagamentos registrados.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> invoices, invoice_items, payments
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('invoices')" 
                                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Financeiro Completo -->
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-cash-stack text-yellow-600 text-2xl mr-3"></i>
                                            Financeiro Completo
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove TODOS os dados financeiros: faturas, pagamentos, despesas.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> invoices, invoice_items, payments, expenses
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('financial')" 
                                            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Farmácia -->
                            <div class="bg-gradient-to-r from-teal-50 to-cyan-50 border-2 border-teal-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-capsule text-teal-600 text-2xl mr-3"></i>
                                            Farmácia
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Zera estoque de medicamentos e remove prescrições.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> medications (stock zerado), prescriptions, prescription_items
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('pharmacy')" 
                                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Laboratório -->
                            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border-2 border-indigo-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-clipboard-pulse text-indigo-600 text-2xl mr-3"></i>
                                            Laboratório
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove todas as solicitações e resultados de exames laboratoriais.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> exam_requests, exam_results, exam_notifications
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('laboratory')" 
                                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Psicologia -->
                            <div class="bg-gradient-to-r from-pink-50 to-rose-50 border-2 border-pink-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-心 text-pink-600 text-2xl mr-3"></i>
                                            Psicologia
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove diagnósticos e histórico de atendimentos psicológicos.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> psychiatric_diagnoses (tipo psicologia), consultation_history relacionado
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('psychology')" 
                                            class="px-4 py-2 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: Psiquiatria -->
                            <div class="bg-gradient-to-r from-violet-50 to-purple-50 border-2 border-violet-300 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                            <i class="bi bi-person-lines-fill text-violet-600 text-2xl mr-3"></i>
                                            Psiquiatria
                                        </h3>
                                        <p class="text-gray-700 text-sm mb-2">Remove diagnósticos psiquiátricos e prescrições relacionadas.</p>
                                        <p class="text-xs text-gray-600">
                                            <strong>Afeta:</strong> psychiatric_diagnoses (tipo psiquiatria), prescriptions, prescription_items
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('psychiatry')" 
                                            class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg font-semibold transition-all whitespace-nowrap ml-4">
                                        <i class="bi bi-trash mr-2"></i>Resetar
                                    </button>
                                </div>
                            </div>

                            <!-- Reset: TODOS OS DADOS CLÍNICOS -->
                            <div class="bg-gradient-to-r from-red-100 to-pink-100 border-4 border-red-600 rounded-xl p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-red-800 mb-2 flex items-center">
                                            <i class="bi bi-exclamation-octagon-fill text-red-600 text-3xl mr-3"></i>
                                            RESET TOTAL - Todos os Dados Clínicos
                                        </h3>
                                        <p class="text-red-700 font-semibold text-sm mb-2">
                                            ⚠️ EXTREMO CUIDADO: Remove TUDO relacionado a atendimentos clínicos!
                                        </p>
                                        <p class="text-xs text-red-600">
                                            <strong>Afeta:</strong> appointments, attendances, prescriptions, exam_requests, exam_results, 
                                            psychiatric_diagnoses, consultation_history - TODOS OS MÓDULOS CLÍNICOS
                                        </p>
                                    </div>
                                    <button onclick="showResetModal('all_clinical')" 
                                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold transition-all whitespace-nowrap ml-4 shadow-lg">
                                        <i class="bi bi-trash3 mr-2"></i>RESETAR TUDO
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Reset -->
    <div id="resetModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="closeResetModal(event)">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6" onclick="event.stopPropagation()">
            <div class="text-center mb-6">
                <div class="bg-red-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-exclamation-triangle-fill text-red-600 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirmar Reset de Dados</h3>
                <p class="text-gray-600 text-sm" id="modalDescription">Esta ação não pode ser desfeita!</p>
            </div>
            
            <form id="resetForm" class="space-y-4">
                <input type="hidden" name="action" value="reset_data">
                <input type="hidden" name="module" id="resetModule">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Digite <span class="text-red-600 font-bold">RESETAR</span> para confirmar:
                    </label>
                    <input type="text" name="confirm_text" id="confirmText" required
                           placeholder="RESETAR"
                           class="w-full px-4 py-3 rounded-lg border-2 border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 text-center font-bold text-lg uppercase"
                           autocomplete="off">
                </div>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="closeResetModal()" 
                            class="flex-1 px-4 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-semibold transition-all">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-all">
                        <i class="bi bi-trash mr-2"></i>Confirmar Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remover classe active de todos os botões
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-100', 'bg-emerald-100', 'bg-blue-100', 'bg-yellow-100', 'bg-red-100');
            });
            
            // Mostrar o conteúdo selecionado
            document.getElementById(tabId).classList.add('active');
            
            // Adicionar classe active ao botão clicado
            const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
            activeBtn.classList.add('active');
            
            // Adicionar cor específica
            const colors = {
                'general': 'bg-indigo-100',
                'financial': 'bg-emerald-100',
                'appointment': 'bg-blue-100',
                'notification': 'bg-yellow-100',
                'security': 'bg-red-100',
                'data-management': 'bg-purple-100'
            };
            activeBtn.classList.add(colors[tabId]);
        }
        
        // Modal de Reset
        function showResetModal(module) {
            const moduleNames = {
                'appointments': 'Agendamentos e Consultas',
                'prescriptions': 'Prescrições Médicas',
                'invoices': 'Faturas e Pagamentos',
                'financial': 'Dados Financeiros Completos',
                'pharmacy': 'Dados da Farmácia',
                'laboratory': 'Dados do Laboratório',
                'psychology': 'Dados de Psicologia',
                'psychiatry': 'Dados de Psiquiatria',
                'all_clinical': 'TODOS OS DADOS CLÍNICOS'
            };
            
            document.getElementById('resetModule').value = module;
            document.getElementById('modalDescription').textContent = 
                `Você está prestes a resetar: ${moduleNames[module]}. Esta ação é IRREVERSÍVEL!`;
            document.getElementById('confirmText').value = '';
            document.getElementById('resetModal').classList.remove('hidden');
        }
        
        function closeResetModal(event) {
            if (!event || event.target.id === 'resetModal') {
                document.getElementById('resetModal').classList.add('hidden');
                document.getElementById('confirmText').value = '';
            }
        }
        
        // Form de Reset
        document.getElementById('resetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const confirmText = document.getElementById('confirmText').value;
            if (confirmText !== 'RESETAR') {
                alert('❌ Você deve digitar exatamente "RESETAR" para confirmar.');
                return;
            }
            
            if (!confirm('⚠️ ÚLTIMA CONFIRMAÇÃO: Tem certeza absoluta que deseja prosseguir com o reset?')) {
                return;
            }
            
            try {
                const formData = new FormData(e.target);
                const response = await fetch('configuracoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeResetModal();
                    window.location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro ao resetar dados: ' + error.message);
            }
        });
        
        // Handlers de formulários
        document.getElementById('formGeneral').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings(e.target);
        });
        
        document.getElementById('formFinancial').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings(e.target);
        });
        
        document.getElementById('formAppointment').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings(e.target);
        });
        
        document.getElementById('formNotification').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings(e.target);
        });
        
        document.getElementById('formSecurity').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings(e.target);
        });
        
        async function saveSettings(form) {
            try {
                const formData = new FormData(form);
                const response = await fetch('configuracoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                } else {
                    alert('❌ Erro ao salvar: ' + (result.message || 'Erro desconhecido'));
                }
            } catch (error) {
                alert('❌ Erro ao salvar configurações: ' + error.message);
            }
        }
    </script>
</body>
</html>
