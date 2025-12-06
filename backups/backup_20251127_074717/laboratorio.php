<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    // Faturar exames de um atendimento
    if ($_POST['acao'] === 'faturar_exames' && isset($_POST['attendance_id'])) {
        $attendance_id = (int)$_POST['attendance_id'];
        
        // Buscar dados do atendimento e paciente
        $attendance_data = $mysqli->query("
            SELECT ap.patient_id, pat.codigo
            FROM attendances a
            JOIN appointments ap ON a.appointment_id = ap.id
            JOIN patients pat ON ap.patient_id = pat.id
            WHERE a.id = $attendance_id
        ")->fetch_assoc();
        
        if ($attendance_data) {
            $patient_id = $attendance_data['patient_id'];
            
            // Buscar exames do atendimento com preços dos serviços
            $exams_query = $mysqli->query("
                SELECT DISTINCT
                    e.id as exam_id,
                    e.name,
                    s.id as service_id,
                    s.price,
                    1 as quantity
                FROM exam_requests er
                JOIN exams e ON er.exam_id = e.id
                LEFT JOIN (
                    SELECT id, name, price 
                    FROM services 
                    WHERE category = 'exame'
                    GROUP BY name
                ) s ON s.name COLLATE utf8mb4_unicode_ci = e.name COLLATE utf8mb4_unicode_ci
                WHERE er.attendance_id = $attendance_id
            ");
            
            $servicos_fatura = [];
            $exames_processados = [];
            
            while ($exam = $exams_query->fetch_assoc()) {
                // Evitar processar o mesmo exame múltiplas vezes
                if (in_array($exam['exam_id'], $exames_processados)) {
                    continue;
                }
                $exames_processados[] = $exam['exam_id'];
                
                if ($exam['service_id']) {
                    $servicos_fatura[] = [
                        'service_id' => $exam['service_id'],
                        'description' => $exam['name'],
                        'quantity' => 1,
                        'price' => $exam['price']
                    ];
                }
            }
            
            // Criar fatura se houver exames com preço
            if (!empty($servicos_fatura)) {
                // Gerar número de fatura
                $prefix_result = $mysqli->query("SELECT setting_value FROM system_settings WHERE setting_key = 'invoice_prefix' AND category = 'financial'");
                $prefix = $prefix_result && $prefix_result->num_rows > 0 ? $prefix_result->fetch_assoc()['setting_value'] : 'FT';
                $year = date('Y');
                $last_invoice = $mysqli->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE '$prefix$year%' ORDER BY id DESC LIMIT 1");
                if ($last_invoice && $last_invoice->num_rows > 0) {
                    $last_num = $last_invoice->fetch_assoc()['invoice_number'];
                    $num = (int)substr($last_num, -6) + 1;
                } else {
                    $num = 1;
                }
                $invoice_number = $prefix . $year . str_pad($num, 6, '0', STR_PAD_LEFT);
                
                $issue_date = date('Y-m-d');
                $due_date = date('Y-m-d', strtotime('+30 days'));
                
                // Calcular totais
                $subtotal = 0;
                foreach ($servicos_fatura as $serv) {
                    $subtotal += $serv['price'] * $serv['quantity'];
                }
                
                $tax_rate_result = $mysqli->query("SELECT setting_value FROM system_settings WHERE setting_key = 'tax_rate' AND category = 'financial'");
                $tax_rate = $tax_rate_result && $tax_rate_result->num_rows > 0 ? (float)$tax_rate_result->fetch_assoc()['setting_value'] : 0;
                
                $discount = 0;
                $discount_percent = 0;
                $tax = ($subtotal * $tax_rate) / 100;
                $total = $subtotal + $tax;
                
                // Criar fatura
                $stmt = $mysqli->prepare("INSERT INTO invoices (invoice_number, patient_id, issue_date, due_date, status, subtotal, discount, discount_percent, tax, total, amount_paid, amount_due, notes, created_by) VALUES (?, ?, ?, ?, 'pendente', ?, ?, ?, ?, ?, 0, ?, ?, ?)");
                $notes = "Exames laboratoriais realizados";
                $username = $_SESSION['user_name'] ?? 'Laboratório';
                $stmt->bind_param('sissddddddss', $invoice_number, $patient_id, $issue_date, $due_date, $subtotal, $discount, $discount_percent, $tax, $total, $total, $notes, $username);
                
                if ($stmt->execute()) {
                    $invoice_id = $stmt->insert_id;
                    
                    // Inserir itens da fatura
                    $stmt_item = $mysqli->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount, subtotal) VALUES (?, ?, ?, ?, ?, 0, ?)");
                    foreach ($servicos_fatura as $serv) {
                        $item_subtotal = $serv['price'] * $serv['quantity'];
                        $stmt_item->bind_param('iisidd', $invoice_id, $serv['service_id'], $serv['description'], $serv['quantity'], $serv['price'], $item_subtotal);
                        $stmt_item->execute();
                    }
                    $stmt_item->close();
                    
                    // Registrar no fluxo de caixa
                    $mysqli->query("INSERT INTO cash_flow (type, category, description, amount, transaction_date, reference_type, reference_id, created_by) VALUES ('entrada', 'Faturamento', 'Fatura $invoice_number - Exames Laboratoriais', $total, NOW(), 'invoice', $invoice_id, '$username')");
                    
                    $sucesso = 'Fatura gerada com sucesso! Nº ' . $invoice_number;
                } else {
                    $erro = 'Erro ao gerar fatura: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $erro = 'Nenhum exame com preço cadastrado encontrado para faturar.';
            }
        } else {
            $erro = 'Atendimento não encontrado.';
        }
    }
    
    // Inserir resultado de exame
    if ($_POST['acao'] === 'inserir_resultado') {
        $exam_request_id = (int)$_POST['exam_request_id'];
        $resultado = trim($_POST['resultado']);
        
        if ($resultado !== '') {
            $stmt = $mysqli->prepare("INSERT INTO exam_results (exam_request_id, result, released_at) VALUES (?, ?, NOW())");
            $stmt->bind_param('is', $exam_request_id, $resultado);
            
            if ($stmt->execute()) {
                $sucesso = 'Resultado salvo com sucesso!';
            } else {
                $erro = 'Erro ao salvar resultado: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $erro = 'Resultado não pode estar vazio.';
        }
    }
}

// Buscar paciente
$codigo_paciente = $_POST['codigo_paciente'] ?? $_GET['codigo_paciente'] ?? '';
$f_data = $_POST['f_data'] ?? $_GET['f_data'] ?? '';
$paciente_id = null;
$paciente_info = null;

if ($codigo_paciente) {
    $result = $mysqli->query("SELECT id, name, codigo, cpf, birth_date, email, phone FROM patients WHERE codigo = '" . $mysqli->real_escape_string($codigo_paciente) . "'");
    if ($result && $result->num_rows > 0) {
        $paciente_info = $result->fetch_assoc();
        $paciente_id = $paciente_info['id'];
    } else {
        $erro = 'Paciente não encontrado.';
    }
}

// Agrupar exames por paciente
$exames_agrupados = [];
if ($paciente_id) {
    $where = ["p.id = $paciente_id"];
    if ($f_data !== '') {
        $where[] = "DATE(ar.scheduled_at) = '" . $mysqli->real_escape_string($f_data) . "'";
    }
    $sql = "SELECT er.id AS exam_request_id, p.name AS patient_name, p.codigo, p.cpf, p.birth_date,
                   e.name AS exam_name, e.description AS exam_description, 
                   ar.scheduled_at, er2.result, er2.released_at, a.id as attendance_id
            FROM exam_requests er
            JOIN attendances a ON er.attendance_id = a.id
            JOIN appointments ar ON a.appointment_id = ar.id
            JOIN patients p ON ar.patient_id = p.id
            JOIN exams e ON er.exam_id = e.id
            LEFT JOIN exam_results er2 ON er.id = er2.exam_request_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.id DESC, er.id ASC";
    
    $result = $mysqli->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $attendance_id = $row['attendance_id'];
            if (!isset($exames_agrupados[$attendance_id])) {
                $exames_agrupados[$attendance_id] = [
                    'patient_name' => $row['patient_name'],
                    'codigo' => $row['codigo'],
                    'cpf' => $row['cpf'],
                    'birth_date' => $row['birth_date'],
                    'scheduled_at' => $row['scheduled_at'],
                    'exames' => []
                ];
            }
            $exames_agrupados[$attendance_id]['exames'][] = $row;
        }
    }
}

// Gerar PDF dos Protocolos do Dia
if (isset($_GET['exportar_protocolos']) && $_GET['exportar_protocolos'] === 'pdf') {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new Dompdf($options);
    
    $hoje = date('Y-m-d');
    $protocolos_pdf = $mysqli->query("
        SELECT p.id AS patient_id, p.name AS patient_name, p.codigo, p.cpf, p.birth_date,
               GROUP_CONCAT(DISTINCT e.name ORDER BY e.name SEPARATOR '|') AS exames,
               ar.scheduled_at,
               COUNT(DISTINCT er.id) AS total_exames
        FROM exam_requests er 
        JOIN attendances a ON er.attendance_id = a.id 
        JOIN appointments ar ON a.appointment_id = ar.id 
        JOIN patients p ON ar.patient_id = p.id 
        JOIN exams e ON er.exam_id = e.id 
        WHERE DATE(ar.scheduled_at) = '$hoje' 
        GROUP BY p.id, p.name, p.codigo, p.cpf, p.birth_date, ar.scheduled_at
        ORDER BY ar.scheduled_at ASC
    ");
    
    $html = '<html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #10b981; padding-bottom: 15px; }
        .header h1 { color: #10b981; margin: 0; font-size: 24pt; }
        .info-box { background: #f0fdf4; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .protocol-item { border: 2px solid #10b981; padding: 15px; margin-bottom: 20px; border-radius: 8px; page-break-inside: avoid; }
        .protocol-header { background: #10b981; color: white; padding: 10px; margin: -15px -15px 15px -15px; border-radius: 6px 6px 0 0; }
        .patient-name { font-size: 14pt; font-weight: bold; margin: 0; }
        .patient-code { background: #059669; color: white; padding: 5px 10px; border-radius: 5px; font-size: 9pt; display: inline-block; margin-top: 5px; }
        .patient-info { background: #f9fafb; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
        .patient-info table { width: 100%; font-size: 9pt; }
        .patient-info td { padding: 3px; }
        .exam-badge { background: #d1fae5; color: #065f46; padding: 5px 10px; border-radius: 5px; font-weight: bold; font-size: 9pt; display: inline-block; margin-bottom: 10px; }
        .exam-list { margin: 10px 0; }
        .exam-item { padding: 8px; margin: 5px 0; background: #f0fdf4; border-left: 4px solid #10b981; }
        .datetime { color: #6b7280; font-size: 9pt; margin-top: 10px; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #d1d5db; font-size: 9pt; color: #6b7280; }
        .summary { background: #fef3c7; border: 2px solid #f59e0b; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .summary-title { color: #92400e; font-weight: bold; font-size: 12pt; margin: 0 0 10px 0; }
    </style></head><body>';
    
    $html .= '<div class="header">
        <h1>🔬 Integrada Mais Saúde</h1>
        <p style="margin: 5px 0 0 0; color: #6b7280;">Protocolos Laboratoriais do Dia</p>
    </div>';
    
    $html .= '<div class="info-box">
        <p style="margin: 0; font-weight: bold; color: #10b981; font-size: 12pt;">📅 ' . date('d/m/Y', strtotime($hoje)) . '</p>
        <p style="margin: 5px 0 0 0; color: #6b7280;">Relatório gerado em: ' . date('d/m/Y H:i:s') . '</p>
    </div>';
    
    if ($protocolos_pdf && $protocolos_pdf->num_rows > 0) {
        $total_pacientes = $protocolos_pdf->num_rows;
        $total_exames_dia = 0;
        
        // Calcular totais
        $protocolos_array = [];
        while ($row = $protocolos_pdf->fetch_assoc()) {
            $protocolos_array[] = $row;
            $total_exames_dia += $row['total_exames'];
        }
        
        $html .= '<div class="summary">
            <p class="summary-title">📊 Resumo do Dia</p>
            <table style="width: 100%; font-size: 10pt;">
                <tr>
                    <td><strong>Total de Pacientes:</strong></td>
                    <td>' . $total_pacientes . '</td>
                    <td><strong>Total de Exames:</strong></td>
                    <td>' . $total_exames_dia . '</td>
                </tr>
            </table>
        </div>';
        
        foreach ($protocolos_array as $pr) {
            $exames_array = explode('|', $pr['exames']);
            
            $html .= '<div class="protocol-item">
                <div class="protocol-header">
                    <p class="patient-name">👤 ' . htmlspecialchars($pr['patient_name']) . '</p>
                    <span class="patient-code">📋 Código: ' . htmlspecialchars($pr['codigo']) . '</span>
                </div>
                
                <div class="patient-info">
                    <table>
                        <tr>
                            <td><strong>BI/CPF:</strong></td>
                            <td>' . htmlspecialchars($pr['cpf'] ?? 'N/A') . '</td>
                            <td><strong>Data Nascimento:</strong></td>
                            <td>' . ($pr['birth_date'] ? date('d/m/Y', strtotime($pr['birth_date'])) : 'N/A') . '</td>
                        </tr>
                    </table>
                </div>
                
                <span class="exam-badge">🔬 ' . $pr['total_exames'] . ' ' . ($pr['total_exames'] == 1 ? 'Exame Solicitado' : 'Exames Solicitados') . '</span>
                
                <div class="exam-list">';
            
            foreach ($exames_array as $exame) {
                $html .= '<div class="exam-item">✓ ' . htmlspecialchars($exame) . '</div>';
            }
            
            $html .= '</div>
                
                <div class="datetime">
                    🕐 Agendado para: ' . date('d/m/Y H:i', strtotime($pr['scheduled_at'])) . '
                </div>
            </div>';
        }
    } else {
        $html .= '<div style="text-align: center; padding: 40px; color: #6b7280;">
            <p style="font-size: 14pt;">Nenhum protocolo encontrado para hoje.</p>
        </div>';
    }
    
    $html .= '<div class="footer">
        <p><strong>Integrada Mais Saúde</strong> - Sistema de Gestão Clínica</p>
        <p>Laboratório de Análises Clínicas</p>
    </div>';
    
    $html .= '</body></html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('Protocolos_Laboratorio_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
    exit;
}

// Gerar PDF
if (isset($_GET['exportar']) && $_GET['exportar'] === 'pdf' && $paciente_info && !empty($exames_agrupados)) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new Dompdf($options);
    
    $html = '<html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #10b981; padding-bottom: 15px; }
        .header h1 { color: #10b981; margin: 0; font-size: 24pt; }
        .patient-info { background: #f0fdf4; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .patient-info table { width: 100%; }
        .patient-info td { padding: 5px; }
        .exam-group { margin-bottom: 30px; page-break-inside: avoid; }
        .exam-group h3 { background: #10b981; color: white; padding: 10px; margin: 0 0 15px 0; }
        .exam-item { border: 1px solid #d1d5db; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .exam-item h4 { color: #10b981; margin: 0 0 10px 0; }
        .exam-result { background: #f9fafb; padding: 10px; border-left: 4px solid #10b981; margin-top: 10px; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #d1d5db; font-size: 9pt; color: #6b7280; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 5px; font-size: 9pt; font-weight: bold; }
        .status-completo { background: #d1fae5; color: #065f46; }
        .status-pendente { background: #fef3c7; color: #92400e; }
    </style></head><body>';
    
    $html .= '<div class="header">
        <h1>Mais Saúde</h1>
        <p style="margin: 5px 0 0 0; color: #6b7280;">Resultados de Exames Laboratoriais</p>
    </div>';
    
    $html .= '<div class="patient-info">
        <h3 style="margin: 0 0 10px 0; color: #10b981;">📋 Dados do Paciente</h3>
        <table>
            <tr>
                <td><strong>Nome:</strong></td>
                <td>' . htmlspecialchars($paciente_info['name']) . '</td>
                <td><strong>Código:</strong></td>
                <td>' . htmlspecialchars($paciente_info['codigo']) . '</td>
            </tr>
            <tr>
                <td><strong>BI/CPF:</strong></td>
                <td>' . htmlspecialchars($paciente_info['cpf'] ?? 'N/A') . '</td>
                <td><strong>Data Nascimento:</strong></td>
                <td>' . ($paciente_info['birth_date'] ? date('d/m/Y', strtotime($paciente_info['birth_date'])) : 'N/A') . '</td>
            </tr>
        </table>
    </div>';
    
    foreach ($exames_agrupados as $attendance_id => $grupo) {
        $data_solicitacao = date('d/m/Y H:i', strtotime($grupo['scheduled_at']));
        
        $html .= '<div class="exam-group">
            <h3>🩺 Protocolo de Atendimento #' . $attendance_id . ' - ' . $data_solicitacao . '</h3>';
        
        foreach ($grupo['exames'] as $exame) {
            $status_html = $exame['released_at'] 
                ? '<span class="status status-completo">✓ Concluído</span>' 
                : '<span class="status status-pendente">⏳ Pendente</span>';
            
            $html .= '<div class="exam-item">
                <h4>' . htmlspecialchars($exame['exam_name']) . ' ' . $status_html . '</h4>';
            
            if ($exame['exam_description']) {
                $html .= '<p style="color: #6b7280; font-size: 9pt; margin: 5px 0;">' . htmlspecialchars($exame['exam_description']) . '</p>';
            }
            
            if ($exame['result']) {
                $html .= '<div class="exam-result">
                    <strong>Resultado:</strong><br>' . nl2br(htmlspecialchars($exame['result'])) . '
                </div>';
                
                if ($exame['released_at']) {
                    $html .= '<p style="margin: 10px 0 0 0; font-size: 9pt; color: #6b7280;">
                        <strong>Liberado em:</strong> ' . date('d/m/Y H:i', strtotime($exame['released_at'])) . '
                    </p>';
                }
            } else {
                $html .= '<p style="color: #9ca3af; font-style: italic; margin-top: 10px;">Resultado aguardando processamento...</p>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '<div class="footer">
        <p><strong>Integrada Mais Saúde</strong> - Sistema de Gestão Clínica</p>
        <p>Documento gerado em ' . date('d/m/Y H:i:s') . '</p>
    </div>';
    
    $html .= '</body></html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('Exames_' . $paciente_info['codigo'] . '_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratório — Exames | Integrada Mais Saúde</title>
    
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
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 via-green-50 to-teal-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass-effect border-b border-emerald-200 dark:border-gray-700 sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="bi bi-heart-pulse-fill text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800 dark:text-white">Integrada Mais Saúde</h1>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Módulo Laboratório</p>
                        </div>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="sysmex_integration.php" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 flex items-center" title="Integração Sysmex XN">
                        <i class="bi bi-hdd-network mr-2"></i>Sysmex XN
                    </a>
                    <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-300">
                        <i class="bi bi-moon-stars-fill dark:bi-sun-fill text-gray-700 dark:text-yellow-400 text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3 px-4 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                        <i class="bi bi-person-circle text-emerald-600 dark:text-emerald-400 text-2xl"></i>
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
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <!-- Sidebar: Protocolos do Dia -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden hover-lift">
                    <div class="bg-gradient-to-r from-emerald-500 to-green-600 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="bi bi-calendar-check mr-3 text-xl"></i>
                            Protocolos do Dia
                        </h3>
                        <a href="?exportar_protocolos=pdf" target="_blank" class="bg-white hover:bg-emerald-50 text-emerald-600 px-3 py-2 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 text-sm font-semibold" title="Exportar Protocolos em PDF">
                            <i class="bi bi-file-earmark-pdf-fill mr-1"></i>PDF
                        </a>
                    </div>
                    <div class="p-4 max-h-[600px] overflow-y-auto space-y-3">
                        <?php
                        $hoje = date('Y-m-d');
                        // Agrupar exames por paciente
                        $protocolos = $mysqli->query("
                            SELECT p.id AS patient_id, p.name AS patient_name, p.codigo, 
                                   GROUP_CONCAT(e.name SEPARATOR '|') AS exames,
                                   ar.scheduled_at,
                                   COUNT(er.id) AS total_exames
                            FROM exam_requests er 
                            JOIN attendances a ON er.attendance_id = a.id 
                            JOIN appointments ar ON a.appointment_id = ar.id 
                            JOIN patients p ON ar.patient_id = p.id 
                            JOIN exams e ON er.exam_id = e.id 
                            WHERE DATE(ar.scheduled_at) = '$hoje' 
                            GROUP BY p.id, ar.scheduled_at
                            ORDER BY ar.scheduled_at DESC
                        ");
                        
                        if ($protocolos && $protocolos->num_rows > 0):
                            while($pr = $protocolos->fetch_assoc()): 
                                $exames_array = explode('|', $pr['exames']);
                                ?>
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-emerald-500 shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="font-semibold text-gray-800 dark:text-white">
                                            <?= htmlspecialchars($pr['patient_name']) ?>
                                        </div>
                                        <span class="bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 text-xs font-bold px-2 py-1 rounded-full">
                                            <?= $pr['total_exames'] ?> <?= $pr['total_exames'] == 1 ? 'exame' : 'exames' ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300 font-mono mb-2 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded inline-block">
                                        <i class="bi bi-person-badge mr-1"></i>Código: <?= htmlspecialchars($pr['codigo']) ?>
                                    </div>
                                    <div class="space-y-1 mb-2">
                                        <?php foreach($exames_array as $exame): ?>
                                            <div class="text-sm text-emerald-600 dark:text-emerald-400 flex items-center">
                                                <i class="bi bi-dot text-lg"></i>
                                                <?= htmlspecialchars($exame) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <i class="bi bi-clock mr-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($pr['scheduled_at'])) ?>
                                    </div>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <div class="text-center py-8">
                                <i class="bi bi-inbox text-5xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Nenhum protocolo hoje</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-emerald-500 to-green-600 px-6 py-5">
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="bi bi-clipboard2-pulse mr-3 text-3xl"></i>
                            Lançamento de Resultados
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <!-- Alertas -->
                        <?php if ($erro): ?>
                            <div class="mb-6 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 rounded-lg p-4 animate-fadeIn">
                                <div class="flex items-center">
                                    <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl mr-3"></i>
                                    <p class="text-red-800 dark:text-red-200 font-medium"><?= htmlspecialchars($erro) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($sucesso): ?>
                            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border-l-4 border-emerald-500 rounded-lg p-4 animate-fadeIn">
                                <div class="flex items-center">
                                    <i class="bi bi-check-circle-fill text-emerald-500 text-xl mr-3"></i>
                                    <p class="text-emerald-800 dark:text-emerald-200 font-medium"><?= htmlspecialchars($sucesso) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Formulário de Busca -->
                        <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label for="codigo_paciente" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="bi bi-person-badge mr-1"></i>
                                    Código do Paciente
                                </label>
                                <input type="text" name="codigo_paciente" id="codigo_paciente" 
                                       value="<?= htmlspecialchars($codigo_paciente) ?>" 
                                       placeholder="MS-0000" 
                                       class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all duration-300">
                            </div>
                            
                            <div>
                                <label for="f_data" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="bi bi-calendar-event mr-1"></i>
                                    Data do Pedido
                                </label>
                                <input type="date" name="f_data" id="f_data" 
                                       value="<?= htmlspecialchars($f_data ?? '') ?>" 
                                       class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all duration-300">
                            </div>
                            
                            <div class="md:col-span-2 flex items-end">
                                <button type="submit" name="buscar_codigo" 
                                        class="w-full px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                    <i class="bi bi-search mr-2"></i>
                                    Buscar Exames
                                </button>
                            </div>
                        </form>
                        <?php if ($paciente_info && !empty($exames_agrupados)): ?>
                        
                        <!-- Informações do Paciente -->
                        <div class="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-xl p-6 mb-6 border-2 border-emerald-200 dark:border-emerald-700">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">
                                        <i class="bi bi-person-badge-fill text-emerald-600 mr-2"></i>
                                        <?= htmlspecialchars($paciente_info['name']) ?>
                                    </h3>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Código:</span>
                                            <span class="font-bold text-emerald-700 dark:text-emerald-400 ml-2"><?= htmlspecialchars($paciente_info['codigo']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">BI/CPF:</span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200 ml-2"><?= htmlspecialchars($paciente_info['cpf'] ?? 'N/A') ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Nascimento:</span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200 ml-2"><?= $paciente_info['birth_date'] ? date('d/m/Y', strtotime($paciente_info['birth_date'])) : 'N/A' ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Total de Exames:</span>
                                            <span class="font-bold text-emerald-700 dark:text-emerald-400 ml-2"><?= array_sum(array_map(function($g) { return count($g['exames']); }, $exames_agrupados)) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <a href="?exportar=pdf&codigo_paciente=<?= urlencode($codigo_paciente) ?>&f_data=<?= urlencode($f_data) ?>" 
                                   target="_blank"
                                   class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105 whitespace-nowrap">
                                    <i class="bi bi-file-earmark-pdf mr-2"></i>
                                    Exportar PDF Completo
                                </a>
                            </div>
                        </div>
                        
                        <!-- Exames Agrupados por Atendimento -->
                        <div class="space-y-6">
                            <?php foreach ($exames_agrupados as $attendance_id => $grupo): ?>
                                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-emerald-200 dark:border-emerald-700 overflow-hidden animate-fadeIn">
                                    <!-- Header do Grupo -->
                                    <div class="bg-gradient-to-r from-emerald-500 to-green-600 px-6 py-4">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h4 class="text-lg font-bold text-white flex items-center">
                                                    <i class="bi bi-clipboard2-check mr-2"></i>
                                                    Protocolo de Atendimento #<?= $attendance_id ?>
                                                </h4>
                                                <p class="text-emerald-100 text-sm mt-1">
                                                    <i class="bi bi-calendar-event mr-1"></i>
                                                    Solicitado em <?= date('d/m/Y H:i', strtotime($grupo['scheduled_at'])) ?>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="bg-white text-emerald-700 px-4 py-2 rounded-full font-bold text-sm">
                                                    <?= count($grupo['exames']) ?> exame<?= count($grupo['exames']) > 1 ? 's' : '' ?>
                                                </span>
                                                <?php 
                                                // Verificar se já foi faturado através das invoices vinculadas ao paciente e data
                                                $patient_id = $paciente_info['id'];
                                                $check_fatura = $mysqli->query("
                                                    SELECT i.id 
                                                    FROM invoices i
                                                    WHERE i.patient_id = $patient_id 
                                                    AND i.notes LIKE '%Exames laboratoriais%'
                                                    AND DATE(i.issue_date) = CURDATE()
                                                    ORDER BY i.id DESC
                                                    LIMIT 1
                                                ");
                                                $fatura_existente = $check_fatura && $check_fatura->num_rows > 0 ? $check_fatura->fetch_assoc()['id'] : null;
                                                
                                                if ($fatura_existente): 
                                                ?>
                                                    <a href="ver_fatura.php?id=<?= $fatura_existente ?>" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-300 shadow-md hover:shadow-lg">
                                                        <i class="bi bi-receipt mr-1"></i>Ver Fatura
                                                    </a>
                                                <?php else: ?>
                                                    <form method="post" class="inline" onsubmit="return confirm('Confirma a geração da fatura para estes exames?')">
                                                        <input type="hidden" name="acao" value="faturar_exames">
                                                        <input type="hidden" name="attendance_id" value="<?= $attendance_id ?>">
                                                        <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-300 shadow-md hover:shadow-lg">
                                                            <i class="bi bi-cash-coin mr-1"></i>Gerar Fatura
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Lista de Exames -->
                                    <div class="p-6 space-y-4">
                                        <?php foreach ($grupo['exames'] as $exame): ?>
                                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-5 hover:border-emerald-300 dark:hover:border-emerald-600 transition-all duration-300 <?= $exame['released_at'] ? 'bg-emerald-50 dark:bg-emerald-900/10' : 'bg-gray-50 dark:bg-gray-700/30' ?>">
                                                <div class="flex justify-between items-start mb-3">
                                                    <div class="flex-1">
                                                        <h5 class="text-lg font-bold text-gray-800 dark:text-white flex items-center">
                                                            <i class="bi bi-flask text-emerald-600 mr-2"></i>
                                                            <?= htmlspecialchars($exame['exam_name']) ?>
                                                            <span class="ml-3 text-xs font-mono text-gray-500">#<?= $exame['exam_request_id'] ?></span>
                                                        </h5>
                                                        <?php if ($exame['exam_description']): ?>
                                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                                <?= htmlspecialchars($exame['exam_description']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($exame['released_at']): ?>
                                                            <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200 shadow-md">
                                                                <i class="bi bi-check-circle-fill mr-2"></i>
                                                                Concluído
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 shadow-md animate-pulse-soft">
                                                                <i class="bi bi-clock-fill mr-2"></i>
                                                                Pendente
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($exame['result']): ?>
                                                    <!-- Resultado -->
                                                    <div class="mt-4 bg-white dark:bg-gray-800 border-l-4 border-emerald-500 rounded-lg p-4">
                                                        <div class="flex items-start">
                                                            <i class="bi bi-file-text text-emerald-600 text-xl mr-3 mt-1"></i>
                                                            <div class="flex-1">
                                                                <h6 class="font-bold text-gray-700 dark:text-gray-300 mb-2">Resultado:</h6>
                                                                <div class="text-gray-800 dark:text-gray-200 whitespace-pre-line">
                                                                    <?= nl2br(htmlspecialchars($exame['result'])) ?>
                                                                </div>
                                                                <?php if ($exame['released_at']): ?>
                                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                                                        <i class="bi bi-clock-history mr-1"></i>
                                                                        Liberado em <?= date('d/m/Y H:i', strtotime($exame['released_at'])) ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Formulário para Inserir Resultado -->
                                                    <form method="post" class="mt-4">
                                                        <input type="hidden" name="exam_request_id" value="<?= $exame['exam_request_id'] ?>">
                                                        <input type="hidden" name="acao" value="inserir_resultado">
                                                        <input type="hidden" name="codigo_paciente" value="<?= htmlspecialchars($codigo_paciente) ?>">
                                                        <input type="hidden" name="f_data" value="<?= htmlspecialchars($f_data) ?>">
                                                        
                                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                            <i class="bi bi-pencil-square mr-1"></i>
                                                            Inserir Resultado do Exame:
                                                        </label>
                                                        <textarea name="resultado" 
                                                                  placeholder="Digite o resultado detalhado do exame..." 
                                                                  required 
                                                                  rows="4"
                                                                  class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-200 transition-all duration-300"></textarea>
                                                        
                                                        <button type="submit" 
                                                                class="mt-3 px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                                            <i class="bi bi-save mr-2"></i>
                                                            Salvar e Liberar Resultado
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php elseif ($paciente_id): ?>
                            <div class="text-center py-12">
                                <i class="bi bi-search text-6xl text-gray-400 mb-4"></i>
                                <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Nenhum exame encontrado</h3>
                                <p class="text-gray-600 dark:text-gray-400">Não há exames registrados para este paciente.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
