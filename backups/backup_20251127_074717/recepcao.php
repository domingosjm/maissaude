<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';
$novo_paciente_id = null;
$departamento_selecionado = '';
$username = $_SESSION['username'] ?? 'Recepção';

// Função para gerar número de fatura
function gerarNumeroFatura($mysqli) {
    $prefix_result = $mysqli->query("SELECT setting_value FROM financial_settings WHERE setting_key = 'invoice_prefix'");
    $prefix = $prefix_result && $prefix_result->num_rows > 0 ? $prefix_result->fetch_assoc()['setting_value'] : 'FT';
    $year = date('Y');
    $last_invoice = $mysqli->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE '$prefix$year%' ORDER BY id DESC LIMIT 1");
    if ($last_invoice && $last_invoice->num_rows > 0) {
        $last_num = $last_invoice->fetch_assoc()['invoice_number'];
        $num = (int)substr($last_num, -6) + 1;
    } else {
        $num = 1;
    }
    return $prefix . $year . str_pad($num, 6, '0', STR_PAD_LEFT);
}

// Função para criar fatura automaticamente
function criarFaturaAutomatica($mysqli, $patient_id, $servicos, $username) {
    if (empty($servicos)) return null;
    
    $invoice_number = gerarNumeroFatura($mysqli);
    $issue_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+30 days'));
    
    // Calcular totais
    $subtotal = 0;
    foreach ($servicos as $serv) {
        $subtotal += $serv['price'] * $serv['quantity'];
    }
    
    // Buscar taxa de imposto
    $tax_rate_result = $mysqli->query("SELECT setting_value FROM financial_settings WHERE setting_key = 'tax_rate'");
    $tax_rate = $tax_rate_result && $tax_rate_result->num_rows > 0 ? (float)$tax_rate_result->fetch_assoc()['setting_value'] : 0;
    
    $discount = 0;
    $discount_percent = 0;
    $tax = ($subtotal * $tax_rate) / 100;
    $total = $subtotal + $tax;
    
    // Criar fatura
    $stmt = $mysqli->prepare("INSERT INTO invoices (invoice_number, patient_id, issue_date, due_date, status, subtotal, discount, discount_percent, tax, total, amount_paid, amount_due, notes, created_by) VALUES (?, ?, ?, ?, 'pendente', ?, ?, ?, ?, ?, 0, ?, ?, ?)");
    $notes = "Fatura gerada automaticamente pela recepção";
    $stmt->bind_param('sissddddddss', $invoice_number, $patient_id, $issue_date, $due_date, $subtotal, $discount, $discount_percent, $tax, $total, $total, $notes, $username);
    
    if ($stmt->execute()) {
        $invoice_id = $stmt->insert_id;
        
        // Inserir itens da fatura
        $stmt_item = $mysqli->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount, subtotal) VALUES (?, ?, ?, ?, ?, 0, ?)");
        foreach ($servicos as $serv) {
            $item_subtotal = $serv['price'] * $serv['quantity'];
            $stmt_item->bind_param('iisidd', $invoice_id, $serv['service_id'], $serv['description'], $serv['quantity'], $serv['price'], $item_subtotal);
            $stmt_item->execute();
        }
        $stmt_item->close();
        
        // Registrar no fluxo de caixa
        $mysqli->query("INSERT INTO cash_flow (type, category, description, amount, transaction_date, reference_type, reference_id, created_by) VALUES ('entrada', 'Faturamento', 'Fatura $invoice_number criada', $total, NOW(), 'invoice', $invoice_id, '$username')");
        
        return $invoice_id;
    }
    
    return null;
}

// CADASTRAR NOVO PACIENTE COM DEPARTAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_paciente'])) {
    $nome = trim($_POST['nome']);
    $cpf = trim($_POST['cpf']);
    $nasc = $_POST['nasc'];
    $email = trim($_POST['email']);
    $fone = trim($_POST['fone']);
    $endereco = trim($_POST['endereco']);
    $departamento = $_POST['departamento'] ?? '';
    $exames_selecionados = $_POST['exames'] ?? [];
    
    if ($nome && $nasc && $departamento) {
        // Gerar código único MS-XXXX
        $last = $mysqli->query("SELECT MAX(CAST(SUBSTRING(codigo, 4) AS UNSIGNED)) as max_num FROM patients WHERE codigo LIKE 'MS-%'");
        $max_num = $last->fetch_assoc()['max_num'] ?? 0;
        $codigo = 'MS-' . str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);
        
        $stmt = $mysqli->prepare("INSERT INTO patients (name, cpf, birth_date, email, phone, address, codigo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', $nome, $cpf, $nasc, $email, $fone, $endereco, $codigo);
        
        if ($stmt->execute()) {
            $novo_paciente_id = $stmt->insert_id;
            $departamento_selecionado = $departamento;
            
            // Se for laboratório E tiver exames selecionados, criar solicitação direta
            if ($departamento === 'laboratorio' && !empty($exames_selecionados)) {
                // Buscar um profissional do laboratório ou usar um ID padrão (recepção)
                $prof_query = $mysqli->query("SELECT id FROM professionals LIMIT 1");
                $prof_id = $prof_query ? $prof_query->fetch_assoc()['id'] : 1;
                
                // Criar appointment automático
                $datetime = date('Y-m-d H:i:s');
                $status = 'agendado';
                $stmt_apt = $mysqli->prepare("INSERT INTO appointments (patient_id, professional_id, scheduled_at, status) VALUES (?, ?, ?, ?)");
                $stmt_apt->bind_param('iiss', $novo_paciente_id, $prof_id, $datetime, $status);
                
                if ($stmt_apt->execute()) {
                    $appointment_id = $stmt_apt->insert_id;
                    
                    // Criar attendance
                    $stmt_att = $mysqli->prepare("INSERT INTO attendances (appointment_id, notes) VALUES (?, 'Exames solicitados pela recepção')");
                    $stmt_att->bind_param('i', $appointment_id);
                    
                    if ($stmt_att->execute()) {
                        $attendance_id = $stmt_att->insert_id;
                        
                        // Buscar dados dos exames para criar fatura
                        $servicos_fatura = [];
                        $stmt_exam = $mysqli->prepare("INSERT INTO exam_requests (attendance_id, exam_id, requested_at) VALUES (?, ?, ?)");
                        foreach ($exames_selecionados as $exam_id) {
                            $stmt_exam->bind_param('iis', $attendance_id, $exam_id, $datetime);
                            $stmt_exam->execute();
                            
                            // Buscar preço do exame no catálogo de serviços
                            $exam_data = $mysqli->query("SELECT e.name, s.id as service_id, s.price FROM exams e LEFT JOIN services s ON s.name COLLATE utf8mb4_unicode_ci = e.name COLLATE utf8mb4_unicode_ci AND s.category = 'exame' WHERE e.id = $exam_id")->fetch_assoc();
                            
                            if ($exam_data && $exam_data['service_id']) {
                                $servicos_fatura[] = [
                                    'service_id' => $exam_data['service_id'],
                                    'description' => $exam_data['name'],
                                    'quantity' => 1,
                                    'price' => $exam_data['price']
                                ];
                            }
                        }
                        $stmt_exam->close();
                        
                        // Criar fatura automaticamente
                        $invoice_id = criarFaturaAutomatica($mysqli, $novo_paciente_id, $servicos_fatura, $username);
                        
                        if ($invoice_id) {
                            // Buscar número da fatura
                            $invoice_number = $mysqli->query("SELECT invoice_number FROM invoices WHERE id = $invoice_id")->fetch_assoc()['invoice_number'];
                            $sucesso = "Paciente cadastrado (Código: $codigo), exames solicitados e fatura $invoice_number gerada com sucesso!";
                            // Redirecionar para impressão do PDF
                            header("Location: imprimir_fatura.php?id=$invoice_id");
                            exit;
                        } else {
                            $sucesso = "Paciente cadastrado (Código: $codigo) e exames solicitados com sucesso!";
                        }
                    }
                    $stmt_att->close();
                }
                $stmt_apt->close();
            } else {
                // Para outros departamentos, apenas mostra sucesso
                $sucesso = "Paciente cadastrado com sucesso! Código: $codigo";
            }
        } else {
            $erro = 'Erro ao cadastrar paciente: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $erro = 'Nome, data de nascimento e departamento são obrigatórios.';
    }
}

// AGENDAR CONSULTA (mantém funcionalidade original)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar_consulta'])) {
    $paciente_id = (int)$_POST['paciente_id'];
    $data = $_POST['data'];
    $hora = $_POST['hora'];
    $profissional_id = (int)$_POST['profissional_id'];
    $tipo_servico = $_POST['tipo_servico'] ?? '';
    $gerar_fatura = isset($_POST['gerar_fatura']) ? true : false;
    
    if ($paciente_id && $data && $hora && $profissional_id && $tipo_servico) {
        $datetime = $data . ' ' . $hora . ':00';
        $status = 'agendado';
        
        $stmt = $mysqli->prepare("INSERT INTO appointments (patient_id, professional_id, scheduled_at, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $paciente_id, $profissional_id, $datetime, $status);
        
        if ($stmt->execute()) {
            $appointment_id = $stmt->insert_id;
            
            // Se solicitado, gerar fatura para a consulta
            if ($gerar_fatura) {
                // Buscar serviço específico baseado no tipo selecionado
                $service_name_map = [
                    'clinica_geral' => 'Consulta Médica Geral',
                    'psicologia_psiquiatria' => 'Consulta Psicologia / Psquiatria',
                    'laboratorio' => 'Exames Laboratoriais'
                ];
                
                $service_name = $service_name_map[$tipo_servico] ?? 'Consulta Médica Geral';
                $consulta_service = $mysqli->query("SELECT id, name, price FROM services WHERE name LIKE '%$service_name%' AND active = 1 LIMIT 1")->fetch_assoc();
                
                // Se não encontrar o serviço específico, busca qualquer serviço de consulta
                if (!$consulta_service) {
                    $consulta_service = $mysqli->query("SELECT id, name, price FROM services WHERE category = 'consulta' AND active = 1 LIMIT 1")->fetch_assoc();
                }
                
                if ($consulta_service) {
                    $servicos_fatura = [[
                        'service_id' => $consulta_service['id'],
                        'description' => $consulta_service['name'],
                        'quantity' => 1,
                        'price' => $consulta_service['price']
                    ]];
                    
                    $invoice_id = criarFaturaAutomatica($mysqli, $paciente_id, $servicos_fatura, $username);
                    
                    if ($invoice_id) {
                        // Buscar número da fatura
                        $invoice_number = $mysqli->query("SELECT invoice_number FROM invoices WHERE id = $invoice_id")->fetch_assoc()['invoice_number'];
                        $_SESSION['fatura_gerada'] = $invoice_id;
                        $_SESSION['sucesso_msg'] = "Consulta agendada e fatura $invoice_number gerada com sucesso!";
                        // Redirecionar para impressão do PDF
                        header("Location: imprimir_fatura.php?id=$invoice_id");
                        exit;
                    } else {
                        $sucesso = 'Consulta agendada com sucesso!';
                    }
                } else {
                    $sucesso = 'Consulta agendada com sucesso! (Nenhum serviço de consulta encontrado no catálogo)';
                }
            } else {
                $sucesso = 'Consulta agendada com sucesso!';
            }
            
            $novo_paciente_id = null;
            header('Location: recepcao.php');
            exit;
        } else {
            $erro = 'Erro ao agendar consulta: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $erro = 'Todos os campos são obrigatórios, incluindo o tipo de serviço.';
    }
}

// Ver detalhes do paciente
$paciente_detalhes = null;
$historico_consultas = [];
$prescricoes_paciente = [];
$exames_paciente = [];
$sessoes_psicologia = [];
$sessoes_psiquiatria = [];

if (isset($_GET['ver_paciente'])) {
    $paciente_id = (int)$_GET['ver_paciente'];
    
    // Buscar dados do paciente
    $pac_query = $mysqli->query("SELECT * FROM patients WHERE id = $paciente_id");
    if ($pac_query && $pac_query->num_rows > 0) {
        $paciente_detalhes = $pac_query->fetch_assoc();
        
        // Buscar histórico de consultas
        $hist_query = $mysqli->query("
            SELECT a.id, a.scheduled_at, a.status, u.name as professional_name, att.notes
            FROM appointments a
            JOIN professionals prof ON a.professional_id = prof.id
            JOIN users u ON prof.user_id = u.id
            LEFT JOIN attendances att ON att.appointment_id = a.id
            WHERE a.patient_id = $paciente_id
            ORDER BY a.scheduled_at DESC
        ");
        if ($hist_query) {
            while ($row = $hist_query->fetch_assoc()) {
                $historico_consultas[] = $row;
            }
        }
        
        // Buscar prescrições médicas
        $presc_query = $mysqli->query("
            SELECT p.id as prescription_id, p.created_at, u.name as professional_name,
                   GROUP_CONCAT(CONCAT(m.name, ' - ', pi.dosage, ' - ', pi.instructions) SEPARATOR '||') as medicamentos
            FROM prescriptions p
            JOIN attendances att ON p.attendance_id = att.id
            JOIN appointments a ON att.appointment_id = a.id
            JOIN professionals prof ON a.professional_id = prof.id
            JOIN users u ON prof.user_id = u.id
            LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
            LEFT JOIN medications m ON pi.medication_id = m.id
            WHERE a.patient_id = $paciente_id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ");
        if ($presc_query) {
            while ($row = $presc_query->fetch_assoc()) {
                $prescricoes_paciente[] = $row;
            }
        }
        
        // Buscar exames e resultados
        $exam_query = $mysqli->query("
            SELECT er.id, e.name as exam_name, er.requested_at, res.result, res.released_at,
                   u.name as professional_name
            FROM exam_requests er
            JOIN exams e ON er.exam_id = e.id
            JOIN attendances att ON er.attendance_id = att.id
            JOIN appointments a ON att.appointment_id = a.id
            JOIN professionals prof ON a.professional_id = prof.id
            JOIN users u ON prof.user_id = u.id
            LEFT JOIN exam_results res ON er.id = res.exam_request_id
            WHERE a.patient_id = $paciente_id
            ORDER BY er.requested_at DESC
        ");
        if ($exam_query) {
            while ($row = $exam_query->fetch_assoc()) {
                $exames_paciente[] = $row;
            }
        }
        
        // Buscar sessões de psicologia
        $psico_query = $mysqli->query("
            SELECT ps.*, a.scheduled_at, u.name as professional_name
            FROM psychology_sessions ps
            JOIN attendances att ON ps.attendance_id = att.id
            JOIN appointments a ON att.appointment_id = a.id
            JOIN professionals prof ON a.professional_id = prof.id
            JOIN users u ON prof.user_id = u.id
            WHERE a.patient_id = $paciente_id
            ORDER BY ps.session_date DESC
        ");
        if ($psico_query) {
            while ($row = $psico_query->fetch_assoc()) {
                $sessoes_psicologia[] = $row;
            }
        }
        
        // Buscar sessões de psiquiatria
        $psiq_query = $mysqli->query("
            SELECT ps.*, a.scheduled_at, u.name as professional_name
            FROM psychiatry_sessions ps
            JOIN attendances att ON ps.attendance_id = att.id
            JOIN appointments a ON att.appointment_id = a.id
            JOIN professionals prof ON a.professional_id = prof.id
            JOIN users u ON prof.user_id = u.id
            WHERE a.patient_id = $paciente_id
            ORDER BY ps.session_date DESC
        ");
        if ($psiq_query) {
            while ($row = $psiq_query->fetch_assoc()) {
                $sessoes_psiquiatria[] = $row;
            }
        }
        
        // Buscar faturas do paciente
        $faturas_paciente = [];
        $faturas_query = $mysqli->query("
            SELECT i.*, 
                   (SELECT COUNT(*) FROM invoice_items WHERE invoice_id = i.id) as total_items
            FROM invoices i
            WHERE i.patient_id = $paciente_id
            ORDER BY i.created_at DESC
        ");
        if ($faturas_query) {
            while ($row = $faturas_query->fetch_assoc()) {
                $faturas_paciente[] = $row;
            }
        }
        
        // Buscar pagamentos do paciente
        $pagamentos_paciente = [];
        $pagamentos_query = $mysqli->query("
            SELECT p.*, i.invoice_number
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            WHERE i.patient_id = $paciente_id
            ORDER BY p.payment_date DESC
        ");
        if ($pagamentos_query) {
            while ($row = $pagamentos_query->fetch_assoc()) {
                $pagamentos_paciente[] = $row;
            }
        }
    }
}

// Buscar pacientes
$pacientes = $mysqli->query("SELECT id, name, codigo, cpf FROM patients ORDER BY name");

// Buscar exames disponíveis
$exames_disponiveis = $mysqli->query("SELECT id, name, description FROM exams ORDER BY name");

// Buscar últimos agendamentos com informações de exames
$agendamentos = $mysqli->query("SELECT a.id as appointment_id, a.scheduled_at, p.id as patient_id, p.name as patient_name, 
                                pr.name as professional_name, s.name as specialty_name, a.status,
                                (SELECT COUNT(*) FROM attendances att 
                                 JOIN exam_requests er ON att.id = er.attendance_id 
                                 WHERE att.appointment_id = a.id) as total_exames
                                FROM appointments a 
                                JOIN patients p ON a.patient_id = p.id 
                                JOIN professionals prof ON a.professional_id = prof.id
                                JOIN users pr ON prof.user_id = pr.id
                                JOIN specialties s ON prof.specialty_id = s.id
                                ORDER BY a.scheduled_at DESC 
                                LIMIT 20");
?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepção | Integrada Mais Saúde</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .animate-fadeIn { animation: fadeIn 0.6s ease-out; }
        .animate-slideUp { animation: slideUp 0.8s ease-out; }
        .animate-pulse-soft { animation: pulse-soft 2s ease-in-out infinite; }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .dark .glass-effect {
            background: rgba(31, 41, 55, 0.9);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 50%, #be185d 100%);
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(236, 72, 153, 0.3);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-pink-50 via-rose-50 to-fuchsia-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass-effect border-b border-pink-200 dark:border-gray-700 sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                        <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="bi bi-heart-pulse-fill text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800 dark:text-white">Integrada Mais Saúde</h1>
                            <p class="text-xs text-pink-600 dark:text-pink-400 font-medium">Módulo Recepção</p>
                        </div>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-300">
                        <i class="bi bi-moon-stars-fill dark:bi-sun-fill text-gray-700 dark:text-yellow-400 text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3 px-4 py-2 rounded-xl bg-pink-50 dark:bg-pink-900/30">
                        <i class="bi bi-person-circle text-pink-600 dark:text-pink-400 text-2xl"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuário'); ?></span>
                    </div>
                    <a href="logout.php" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="bi bi-box-arrow-right mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <!-- Container Principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Sidebar Esquerda: Formulários -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Cadastrar Novo Paciente -->
                <div class="glass-effect rounded-2xl shadow-xl border border-pink-200 dark:border-gray-700 overflow-hidden hover-lift">
                    <div class="bg-gradient-to-r from-pink-500 to-rose-600 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="bi bi-person-plus-fill mr-3 text-xl"></i>
                            Novo Paciente
                        </h3>
                    </div>
                    <form method="post" id="formCadastro" class="p-6 space-y-4">
                        <input type="hidden" name="cadastrar_paciente" value="1">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-person mr-1"></i>
                                Nome Completo *
                            </label>
                            <input type="text" name="nome" required 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-card-text mr-1"></i>
                                Bilhete de Identificação
                            </label>
                            <input type="text" name="cpf" 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-calendar-event mr-1"></i>
                                Data de Nascimento *
                            </label>
                            <input type="date" name="nasc" required 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-envelope mr-1"></i>
                                E-mail
                            </label>
                            <input type="email" name="email" 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-telephone mr-1"></i>
                                Telefone
                            </label>
                            <input type="text" name="fone" 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-geo-alt mr-1"></i>
                                Endereço
                            </label>
                            <input type="text" name="endereco" 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-hospital mr-1"></i>
                                Departamento *
                            </label>
                            <select name="departamento" id="departamento" required onchange="toggleExamesSection()"
                                    class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all duration-300">
                                <option value="">Selecione o departamento...</option>
                                <option value="medico">👨‍⚕️ Médico</option>
                                <option value="laboratorio">🔬 Laboratório</option>
                                <option value="farmacia">💊 Farmácia</option>
                                <option value="psicologia">🧠 Psicologia</option>
                                <option value="psiquiatria">🩺 Psiquiatria</option>
                            </select>
                        </div>
                        
                        <!-- Seção de Exames (aparece apenas para Laboratório) -->
                        <div id="examesSection" style="display: none;" class="border-t-2 border-purple-200 dark:border-purple-800 pt-4">
                            <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg border border-orange-200 dark:border-orange-800 mb-3">
                                <p class="text-sm text-orange-800 dark:text-orange-200 flex items-start">
                                    <i class="bi bi-info-circle-fill mr-2 mt-0.5 flex-shrink-0"></i>
                                    <span><strong>Fatura Automática:</strong> Uma fatura será gerada automaticamente com os exames selecionados</span>
                                </p>
                            </div>
                            <label class="block text-sm font-bold text-purple-700 dark:text-purple-300 mb-3">
                                <i class="bi bi-clipboard2-pulse mr-1"></i>
                                Selecione os Exames
                            </label>
                            <div class="space-y-2 max-h-64 overflow-y-auto p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                <?php 
                                if ($exames_disponiveis && $exames_disponiveis->num_rows > 0):
                                    while($exam = $exames_disponiveis->fetch_assoc()): ?>
                                        <label class="flex items-start space-x-3 p-3 bg-white dark:bg-gray-800 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/30 cursor-pointer transition-all duration-200 border border-gray-200 dark:border-gray-700">
                                            <input type="checkbox" name="exames[]" value="<?= $exam['id'] ?>" 
                                                   class="mt-1 w-5 h-5 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                                            <div class="flex-1">
                                                <div class="font-semibold text-gray-800 dark:text-white">
                                                    <?= htmlspecialchars($exam['name']) ?>
                                                </div>
                                                <?php if($exam['description']): ?>
                                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                        <?= htmlspecialchars($exam['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endwhile;
                                else: ?>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                        Nenhum exame disponível no momento
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <button type="submit" 
                                class="w-full px-6 py-3 bg-gradient-to-r from-pink-500 to-rose-600 hover:from-pink-600 hover:to-rose-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                            <i class="bi bi-check-circle mr-2"></i>
                            Cadastrar Paciente
                        </button>
                    </form>
                    
                    <script>
                        function toggleExamesSection() {
                            const dept = document.getElementById('departamento').value;
                            const examesSection = document.getElementById('examesSection');
                            
                            if (dept === 'laboratorio') {
                                examesSection.style.display = 'block';
                                examesSection.classList.add('animate-fadeIn');
                            } else {
                                examesSection.style.display = 'none';
                            }
                        }
                    </script>
                </div>
                
                <!-- Agendar Consulta para Novo Paciente -->
                <?php if ($novo_paciente_id): ?>
                <div class="glass-effect rounded-2xl shadow-xl border border-pink-200 dark:border-gray-700 overflow-hidden hover-lift animate-fadeIn">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="bi bi-calendar-plus-fill mr-3 text-xl"></i>
                            Agendar Consulta
                        </h3>
                    </div>
                    <form method="post" class="p-6 space-y-4">
                        <input type="hidden" name="agendar_consulta" value="1">
                        <input type="hidden" name="paciente_id" value="<?= $novo_paciente_id ?>">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-calendar-date mr-1"></i>
                                Data
                            </label>
                            <input type="date" name="data" required 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-clock mr-1"></i>
                                Hora
                            </label>
                            <input type="time" name="hora" required 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-clipboard2-pulse mr-1"></i>
                                Tipo de Serviço *
                            </label>
                            <select name="tipo_servico" required 
                                    class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300">
                                <option value="">Selecione o tipo de serviço...</option>
                                <option value="clinica_geral">Consulta Médica Geral</option>
                                <option value="psicologia_psiquiatria">Psicologia / Psiquiatria</option>
                                <option value="laboratorio">Laboratório</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <i class="bi bi-person-badge mr-1"></i>
                                Profissional
                            </label>
                            <select name="profissional_id" required 
                                    class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300">
                                <option value="">Selecione o profissional...</option>
                                <?php 
                                $profs = $mysqli->query("SELECT p.id, u.name, s.id as specialty_id, s.name as specialty 
                                                         FROM professionals p 
                                                         JOIN users u ON p.user_id = u.id 
                                                         JOIN specialties s ON p.specialty_id = s.id 
                                                         ORDER BY s.name, u.name");
                                $current_specialty = '';
                                while($pr = $profs->fetch_assoc()): 
                                    if ($current_specialty !== $pr['specialty']) {
                                        if ($current_specialty !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($pr['specialty']) . '">';
                                        $current_specialty = $pr['specialty'];
                                    }
                                ?>
                                    <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
                                <?php endwhile; 
                                if ($current_specialty !== '') echo '</optgroup>';
                                ?>
                            </select>
                        </div>
                        
                        <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border-2 border-orange-200 dark:border-orange-800">
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="checkbox" name="gerar_fatura" value="1" checked
                                       class="w-5 h-5 text-orange-600 rounded focus:ring-2 focus:ring-orange-500">
                                <div>
                                    <span class="font-semibold text-gray-800 dark:text-white">
                                        <i class="bi bi-file-earmark-text mr-1 text-orange-600"></i>
                                        Gerar Fatura Automaticamente
                                    </span>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        Uma fatura será criada automaticamente para esta consulta
                                    </p>
                                </div>
                            </label>
                        </div>
                        
                        <button type="submit" 
                                class="w-full px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                            <i class="bi bi-calendar-check mr-2"></i>
                            Agendar Consulta
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Lista de Pacientes -->
                <div class="glass-effect rounded-2xl shadow-xl border border-pink-200 dark:border-gray-700 overflow-hidden hover-lift">
                    <div class="bg-gradient-to-r from-rose-500 to-pink-600 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="bi bi-people-fill mr-3 text-xl"></i>
                            Pacientes Cadastrados
                        </h3>
                    </div>
                    <div class="p-4 max-h-[400px] overflow-y-auto space-y-2">
                        <?php 
                        $pacientes->data_seek(0);
                        if ($pacientes && $pacientes->num_rows > 0):
                            while($p = $pacientes->fetch_assoc()): ?>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-pink-500 shadow hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-800 dark:text-white">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="bi bi-upc-scan mr-1"></i>
                                                <?= htmlspecialchars($p['codigo'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 bg-pink-100 dark:bg-pink-900 text-pink-800 dark:text-pink-200 rounded-full text-xs font-bold">
                                            ID <?= $p['id'] ?>
                                        </span>
                                    </div>
                                    <a href="?ver_paciente=<?= $p['id'] ?>" 
                                       class="block w-full px-3 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white text-center rounded-lg font-medium text-sm transition-all duration-300 transform hover:scale-105">
                                        <i class="bi bi-file-medical mr-1"></i>Ver Histórico Completo
                                    </a>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <div class="text-center py-8">
                                <i class="bi bi-inbox text-5xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Nenhum paciente cadastrado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Área Principal: Alertas e Agendamentos -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Alertas -->
                <?php if ($erro): ?>
                    <div class="bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 rounded-lg p-4 animate-fadeIn">
                        <div class="flex items-center">
                            <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl mr-3"></i>
                            <p class="text-red-800 dark:text-red-200 font-medium"><?= htmlspecialchars($erro) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 border-l-4 border-emerald-500 rounded-lg p-4 animate-fadeIn shadow-lg">
                        <div class="flex items-center">
                            <i class="bi bi-check-circle-fill text-emerald-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-emerald-800 dark:text-emerald-200 font-medium"><?= htmlspecialchars($sucesso) ?></p>
                                <?php if ($departamento_selecionado === 'laboratorio'): ?>
                                    <p class="text-sm text-emerald-700 dark:text-emerald-300 mt-1">
                                        <i class="bi bi-arrow-right mr-1"></i>
                                        Os exames já estão disponíveis no módulo de <a href="laboratorio.php" class="underline font-semibold hover:text-emerald-900">Laboratório</a>
                                    </p>
                                <?php elseif ($departamento_selecionado): ?>
                                    <p class="text-sm text-emerald-700 dark:text-emerald-300 mt-1">
                                        <i class="bi bi-arrow-right mr-1"></i>
                                        Departamento selecionado: <span class="font-semibold"><?= ucfirst($departamento_selecionado) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Últimos Agendamentos -->
                <div class="glass-effect rounded-2xl shadow-xl border border-pink-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-pink-500 to-rose-600 px-6 py-5">
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="bi bi-calendar-week mr-3 text-3xl"></i>
                            Últimos Agendamentos
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="w-full">
                                <thead class="bg-gradient-to-r from-pink-500 to-rose-600 text-white">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-bold">Data/Hora</th>
                                        <th class="px-6 py-4 text-left text-sm font-bold">Paciente</th>
                                        <th class="px-6 py-4 text-left text-sm font-bold">Profissional</th>
                                        <th class="px-6 py-4 text-center text-sm font-bold">Exames</th>
                                        <th class="px-6 py-4 text-center text-sm font-bold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php 
                                    if ($agendamentos && $agendamentos->num_rows > 0):
                                        while($a = $agendamentos->fetch_assoc()): 
                                            $status_colors = [
                                                'agendado' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                                                'em_atendimento' => 'bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200',
                                                'concluido' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200'
                                            ];
                                            $status_class = $status_colors[$a['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <tr class="hover:bg-pink-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-gray-800 dark:text-white">
                                                <i class="bi bi-calendar-event mr-2 text-pink-600"></i>
                                                <?= date('d/m/Y', strtotime($a['scheduled_at'])) ?>
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <i class="bi bi-clock mr-1"></i>
                                                <?= date('H:i', strtotime($a['scheduled_at'])) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-between">
                                                <div class="font-semibold text-gray-800 dark:text-white">
                                                    <?= htmlspecialchars($a['patient_name']) ?>
                                                </div>
                                                <a href="?ver_paciente=<?= $a['patient_id'] ?>" 
                                                   class="ml-2 px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-800 text-xs transition-all"
                                                   title="Ver histórico completo">
                                                    <i class="bi bi-file-medical"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                                <i class="bi bi-person-badge mr-1 text-pink-600"></i>
                                                <?= htmlspecialchars($a['professional_name']) ?>
                                                <span class="ml-2 px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-full text-xs font-semibold">
                                                    <?= htmlspecialchars($a['specialty_name']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($a['total_exames'] > 0): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                                    <i class="bi bi-clipboard2-pulse mr-1"></i>
                                                    <?= $a['total_exames'] ?> exame<?= $a['total_exames'] > 1 ? 's' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?= $status_class ?>">
                                                <?php
                                                $status_icons = [
                                                    'agendado' => 'bi-calendar-check',
                                                    'em_atendimento' => 'bi-hourglass-split',
                                                    'concluido' => 'bi-check-circle-fill'
                                                ];
                                                $icon = $status_icons[$a['status']] ?? 'bi-info-circle';
                                                ?>
                                                <i class="bi <?= $icon ?> mr-1"></i>
                                                <?= ucfirst(str_replace('_', ' ', $a['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center">
                                                <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                                                <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Nenhum agendamento</h3>
                                                <p class="text-gray-600 dark:text-gray-400">Não há consultas agendadas no momento.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico Completo do Paciente -->
    <?php if ($paciente_detalhes): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto" id="modalHistorico">
        <div class="min-h-screen px-4 py-8">
            <div class="max-w-6xl mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-2xl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-pink-500 to-rose-600 px-6 py-5 flex justify-between items-center sticky top-0 rounded-t-2xl">
                    <div>
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="bi bi-person-badge-fill mr-3"></i>
                            <?= htmlspecialchars($paciente_detalhes['name']) ?>
                        </h2>
                        <p class="text-pink-100 text-sm mt-1">
                            Código: <?= htmlspecialchars($paciente_detalhes['codigo']) ?> | 
                            BI: <?= htmlspecialchars($paciente_detalhes['cpf'] ?? 'N/A') ?>
                        </p>
                    </div>
                    <a href="recepcao.php" class="text-white hover:text-pink-200 transition-colors">
                        <i class="bi bi-x-lg text-3xl"></i>
                    </a>
                </div>

                <!-- Dados Pessoais -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="bi bi-person-circle mr-2 text-pink-600"></i>
                        Dados Pessoais
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Data Nascimento:</span>
                            <p class="font-semibold text-gray-800 dark:text-white">
                                <?= $paciente_detalhes['birth_date'] ? date('d/m/Y', strtotime($paciente_detalhes['birth_date'])) : 'N/A' ?>
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Email:</span>
                            <p class="font-semibold text-gray-800 dark:text-white">
                                <?= htmlspecialchars($paciente_detalhes['email'] ?? 'N/A') ?>
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Telefone:</span>
                            <p class="font-semibold text-gray-800 dark:text-white">
                                <?= htmlspecialchars($paciente_detalhes['phone'] ?? 'N/A') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-1 p-4" role="tablist">
                        <button onclick="switchTab('consultas')" id="tab-consultas" 
                                class="tab-button px-4 py-2 font-semibold rounded-lg transition-all active">
                            <i class="bi bi-calendar-check mr-1"></i>Consultas
                        </button>
                        <button onclick="switchTab('prescricoes')" id="tab-prescricoes" 
                                class="tab-button px-4 py-2 font-semibold rounded-lg transition-all">
                            <i class="bi bi-prescription2 mr-1"></i>Prescrições
                        </button>
                        <button onclick="switchTab('exames')" id="tab-exames" 
                                class="tab-button px-4 py-2 font-semibold rounded-lg transition-all">
                            <i class="bi bi-flask mr-1"></i>Exames
                        </button>
                        <button onclick="switchTab('psicologia')" id="tab-psicologia" 
                                class="tab-button px-4 py-2 font-semibold rounded-lg transition-all">
                            <i class="bi bi-emoji-smile mr-1"></i>Psicologia
                        </button>
                        <button onclick="switchTab('psiquiatria')" id="tab-psiquiatria" 
                                class="tab-button px-4 py-2 font-semibold rounded-lg transition-all">
                            <i class="bi bi-brain mr-1"></i>Psiquiatria
                        </button>
                        <button onclick="switchTab('financeiro')" id="tab-financeiro" 
                                class="tab-button px-4 py-2 font-semibold rounded-lg transition-all">
                            <i class="bi bi-currency-exchange mr-1"></i>Financeiro
                        </button>
                    </nav>
                </div>

                <!-- Conteúdo das Tabs -->
                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <!-- Tab: Consultas -->
                    <div id="content-consultas" class="tab-content">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Histórico de Consultas</h3>
                        <?php if (!empty($historico_consultas)): ?>
                            <div class="space-y-3">
                                <?php foreach ($historico_consultas as $consulta): ?>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border-l-4 border-blue-500">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <p class="font-semibold text-gray-800 dark:text-white">
                                                    <?= htmlspecialchars($consulta['professional_name']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i class="bi bi-calendar mr-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($consulta['scheduled_at'])) ?>
                                                </p>
                                            </div>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold 
                                                <?= $consulta['status'] === 'concluido' ? 'bg-green-100 text-green-800' : 
                                                   ($consulta['status'] === 'em_atendimento' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $consulta['status'])) ?>
                                            </span>
                                        </div>
                                        <?php if ($consulta['notes']): ?>
                                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                                <strong>Observações:</strong> <?= nl2br(htmlspecialchars($consulta['notes'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-8">Nenhuma consulta registrada</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Prescrições -->
                    <div id="content-prescricoes" class="tab-content hidden">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Prescrições Médicas</h3>
                        <?php if (!empty($prescricoes_paciente)): ?>
                            <div class="space-y-4">
                                <?php foreach ($prescricoes_paciente as $presc): ?>
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border-l-4 border-blue-600">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <p class="font-semibold text-gray-800 dark:text-white">
                                                    <i class="bi bi-person-badge mr-1"></i>
                                                    <?= htmlspecialchars($presc['professional_name']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i class="bi bi-calendar mr-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($presc['created_at'])) ?>
                                                </p>
                                            </div>
                                            <span class="px-3 py-1 bg-blue-600 text-white rounded-full text-xs font-bold">
                                                ID #<?= $presc['prescription_id'] ?>
                                            </span>
                                        </div>
                                        <?php if ($presc['medicamentos']): ?>
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 mt-2">
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                    <i class="bi bi-capsule mr-1"></i>Medicamentos:
                                                </p>
                                                <ul class="space-y-2">
                                                    <?php 
                                                    $meds = explode('||', $presc['medicamentos']);
                                                    foreach ($meds as $med):
                                                        if (trim($med)):
                                                    ?>
                                                        <li class="text-sm text-gray-800 dark:text-gray-200 flex items-start">
                                                            <i class="bi bi-dot mr-1 text-blue-600"></i>
                                                            <span><?= htmlspecialchars($med) ?></span>
                                                        </li>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-8">Nenhuma prescrição registrada</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Exames -->
                    <div id="content-exames" class="tab-content hidden">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Exames e Resultados</h3>
                        <?php if (!empty($exames_paciente)): ?>
                            <div class="space-y-4">
                                <?php foreach ($exames_paciente as $exame): ?>
                                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border-l-4 border-purple-600">
                                        <div class="flex justify-between items-start mb-2">
                                            <div class="flex-1">
                                                <p class="font-bold text-gray-800 dark:text-white text-lg">
                                                    <i class="bi bi-flask mr-1 text-purple-600"></i>
                                                    <?= htmlspecialchars($exame['exam_name']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                    Solicitado por: <?= htmlspecialchars($exame['professional_name']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i class="bi bi-calendar mr-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($exame['requested_at'])) ?>
                                                </p>
                                            </div>
                                            <?php if ($exame['released_at']): ?>
                                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold">
                                                    <i class="bi bi-check-circle mr-1"></i>Concluído
                                                </span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-bold">
                                                    <i class="bi bi-clock mr-1"></i>Pendente
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($exame['result']): ?>
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 mt-3 border-l-4 border-green-500">
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                    <i class="bi bi-file-text mr-1"></i>Resultado:
                                                </p>
                                                <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
                                                    <?= nl2br(htmlspecialchars($exame['result'])) ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-2">
                                                    Liberado em: <?= date('d/m/Y H:i', strtotime($exame['released_at'])) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-8">Nenhum exame registrado</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Psicologia -->
                    <div id="content-psicologia" class="tab-content hidden">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Sessões de Psicologia</h3>
                        <?php if (!empty($sessoes_psicologia)): ?>
                            <div class="space-y-4">
                                <?php foreach ($sessoes_psicologia as $sessao): ?>
                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border-l-4 border-yellow-600">
                                        <div class="mb-3">
                                            <p class="font-semibold text-gray-800 dark:text-white">
                                                <i class="bi bi-person-badge mr-1"></i>
                                                <?= htmlspecialchars($sessao['professional_name']) ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="bi bi-calendar mr-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($sessao['session_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <?php if ($sessao['observations']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Observações:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['observations'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($sessao['diagnosis']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Diagnóstico:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['diagnosis'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($sessao['treatment_plan']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Plano de Tratamento:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['treatment_plan'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-8">Nenhuma sessão de psicologia registrada</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Psiquiatria -->
                    <div id="content-psiquiatria" class="tab-content hidden">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Sessões de Psiquiatria</h3>
                        <?php if (!empty($sessoes_psiquiatria)): ?>
                            <div class="space-y-4">
                                <?php foreach ($sessoes_psiquiatria as $sessao): ?>
                                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border-l-4 border-indigo-600">
                                        <div class="mb-3">
                                            <p class="font-semibold text-gray-800 dark:text-white">
                                                <i class="bi bi-person-badge mr-1"></i>
                                                <?= htmlspecialchars($sessao['professional_name']) ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <i class="bi bi-calendar mr-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($sessao['session_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <?php if ($sessao['observations']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Observações:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['observations'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($sessao['diagnosis']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Diagnóstico/CID:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['diagnosis'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($sessao['mental_status_exam']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Exame do Estado Mental:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['mental_status_exam'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($sessao['treatment_plan']): ?>
                                                <div class="bg-white dark:bg-gray-800 rounded p-3">
                                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">Plano de Tratamento:</p>
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">
                                                        <?= nl2br(htmlspecialchars($sessao['treatment_plan'])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-8">Nenhuma sessão de psiquiatria registrada</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Financeiro -->
                    <div id="content-financeiro" class="tab-content hidden">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Informações Financeiras</h3>
                            <a href="financeiro.php?criar_fatura&patient_id=<?= $paciente_detalhes['id'] ?>" 
                               class="px-4 py-2 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-lg font-semibold transition-all">
                                <i class="bi bi-plus-circle mr-2"></i>Nova Fatura
                            </a>
                        </div>

                        <!-- Resumo Financeiro -->
                        <?php
                        $total_faturado = 0;
                        $total_pago = 0;
                        $total_pendente = 0;
                        foreach ($faturas_paciente as $f) {
                            $total_faturado += $f['total'];
                            $total_pago += $f['amount_paid'];
                            $total_pendente += $f['amount_due'];
                        }
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-90">Total Faturado</p>
                                        <p class="text-2xl font-bold"><?= number_format($total_faturado, 2, ',', '.') ?> MT</p>
                                    </div>
                                    <i class="bi bi-file-earmark-text text-4xl opacity-50"></i>
                                </div>
                            </div>
                            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-90">Total Pago</p>
                                        <p class="text-2xl font-bold"><?= number_format($total_pago, 2, ',', '.') ?> MT</p>
                                    </div>
                                    <i class="bi bi-check-circle text-4xl opacity-50"></i>
                                </div>
                            </div>
                            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-4 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-90">Total Pendente</p>
                                        <p class="text-2xl font-bold"><?= number_format($total_pendente, 2, ',', '.') ?> MT</p>
                                    </div>
                                    <i class="bi bi-exclamation-circle text-4xl opacity-50"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Faturas -->
                        <h4 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center">
                            <i class="bi bi-receipt mr-2 text-orange-600"></i>
                            Faturas
                        </h4>
                        <?php if (!empty($faturas_paciente)): ?>
                            <div class="space-y-3 mb-6">
                                <?php foreach ($faturas_paciente as $fatura): 
                                    $status_colors = [
                                        'pendente' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'paga' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'parcial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'cancelada' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                        'vencida' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ];
                                ?>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border-l-4 border-orange-500">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <p class="font-mono font-bold text-gray-800 dark:text-white">
                                                    <?= htmlspecialchars($fatura['invoice_number']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i class="bi bi-calendar mr-1"></i>
                                                    Emissão: <?= date('d/m/Y', strtotime($fatura['issue_date'])) ?> | 
                                                    Vencimento: <?= date('d/m/Y', strtotime($fatura['due_date'])) ?>
                                                </p>
                                            </div>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold <?= $status_colors[$fatura['status']] ?>">
                                                <?= ucfirst($fatura['status']) ?>
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-4 mt-3 text-sm">
                                            <div>
                                                <span class="text-gray-600 dark:text-gray-400">Total:</span>
                                                <p class="font-bold text-gray-800 dark:text-white">
                                                    <?= number_format($fatura['total'], 2, ',', '.') ?> MT
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-gray-600 dark:text-gray-400">Pago:</span>
                                                <p class="font-bold text-green-600">
                                                    <?= number_format($fatura['amount_paid'], 2, ',', '.') ?> MT
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-gray-600 dark:text-gray-400">Pendente:</span>
                                                <p class="font-bold text-red-600">
                                                    <?= number_format($fatura['amount_due'], 2, ',', '.') ?> MT
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2 mt-3">
                                            <a href="financeiro.php?ver_fatura=<?= $fatura['id'] ?>" 
                                               class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition-all">
                                                <i class="bi bi-eye mr-1"></i>Ver Detalhes
                                            </a>
                                            <?php if ($fatura['status'] !== 'paga' && $fatura['status'] !== 'cancelada'): ?>
                                                <a href="financeiro.php?pagamento&invoice_id=<?= $fatura['id'] ?>" 
                                                   class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs transition-all">
                                                    <i class="bi bi-cash-stack mr-1"></i>Registrar Pagamento
                                                </a>
                                            <?php endif; ?>
                                            <a href="financeiro.php?imprimir_fatura=<?= $fatura['id'] ?>" target="_blank"
                                               class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs transition-all">
                                                <i class="bi bi-printer mr-1"></i>Imprimir
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4 mb-6">Nenhuma fatura registrada</p>
                        <?php endif; ?>

                        <!-- Lista de Pagamentos -->
                        <h4 class="font-bold text-gray-800 dark:text-white mb-3 flex items-center">
                            <i class="bi bi-cash-stack mr-2 text-green-600"></i>
                            Histórico de Pagamentos
                        </h4>
                        <?php if (!empty($pagamentos_paciente)): ?>
                            <div class="space-y-2">
                                <?php foreach ($pagamentos_paciente as $pag): 
                                    $metodo_icons = [
                                        'dinheiro' => 'bi-cash',
                                        'mpesa' => 'bi-phone',
                                        'emola' => 'bi-phone',
                                        'mkesh' => 'bi-phone',
                                        'ponto24' => 'bi-credit-card',
                                        'multicaixa' => 'bi-credit-card',
                                        'visa' => 'bi-credit-card',
                                        'mastercard' => 'bi-credit-card-2-front',
                                        'transferencia_bancaria' => 'bi-bank',
                                        'cheque' => 'bi-receipt',
                                        'outros' => 'bi-three-dots'
                                    ];
                                    $icon = $metodo_icons[$pag['payment_method']] ?? 'bi-cash';
                                ?>
                                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 flex justify-between items-center">
                                        <div class="flex-1">
                                            <p class="font-mono text-sm font-bold text-gray-800 dark:text-white">
                                                Fatura: <?= htmlspecialchars($pag['invoice_number']) ?>
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                <i class="bi bi-calendar mr-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($pag['payment_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-green-600 text-lg">
                                                <?= number_format($pag['amount'], 2, ',', '.') ?> MT
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                <i class="bi <?= $icon ?> mr-1"></i>
                                                <?= ucfirst(str_replace('_', ' ', $pag['payment_method'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">Nenhum pagamento registrado</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4">
                    <a href="recepcao.php" 
                       class="block w-full px-6 py-3 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white text-center rounded-lg font-semibold transition-all">
                        <i class="bi bi-x-circle mr-2"></i>Fechar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tab-button {
            color: #6b7280;
        }
        .tab-button.active {
            background: linear-gradient(to right, #ec4899, #db2777);
            color: white;
        }
        .tab-button:hover:not(.active) {
            background: #f3f4f6;
        }
        .dark .tab-button:hover:not(.active) {
            background: #374151;
        }
    </style>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Add active class to selected button
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
    <?php endif; ?>

</body>
</html>
