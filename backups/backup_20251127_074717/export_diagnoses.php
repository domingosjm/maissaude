<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

// Biblioteca DomPDF para gerar PDF
require_once(__DIR__ . '/vendor/autoload.php');

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = $mysqli;
$user_name = $_SESSION['user_name'] ?? 'Usuário';
$user_id = $_SESSION['user_id'] ?? 0;

// Verificar se o usuário é da recepção
$allowed_roles = ['recepcao', 'admin'];
$user_role = $_SESSION['user_role'] ?? '';

if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

$patient_id = $_GET['patient_id'] ?? 0;

if ($patient_id <= 0) {
    die('ID do paciente inválido!');
}

// Buscar dados do paciente
$patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

if (!$patient) {
    die('Paciente não encontrado!');
}

// Buscar diagnósticos psiquiátricos e psicológicos
$diagnoses = [];
$query = "SELECT pd.*, a.consultation_date, u.name as doctor_name, s.name as specialty_name
          FROM psychiatric_diagnoses pd
          JOIN appointments a ON pd.appointment_id = a.id
          JOIN professionals pr ON pd.professional_id = pr.id
          JOIN users u ON pr.user_id = u.id
          JOIN specialties s ON pr.specialty_id = s.id
          WHERE pd.patient_id = $patient_id
          ORDER BY a.consultation_date DESC";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $diagnoses[] = $row;
}

if (empty($diagnoses)) {
    die('Não há diagnósticos psiquiátricos ou psicológicos registrados para este paciente.');
}

// Construir HTML para o PDF
$html = '
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #667eea;
            font-size: 24pt;
            margin: 0;
        }
        .header p {
            color: #666;
            font-size: 10pt;
            margin: 5px 0 0 0;
        }
        .patient-info {
            background: #667eea;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .patient-info h2 {
            margin: 0 0 10px 0;
            font-size: 14pt;
        }
        .patient-info p {
            margin: 3px 0;
            font-size: 10pt;
        }
        .generated-info {
            text-align: right;
            font-size: 9pt;
            color: #666;
            font-style: italic;
            margin-bottom: 20px;
        }
        .diagnosis-block {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .diagnosis-header {
            background: #f0f0f0;
            padding: 12px;
            font-weight: bold;
            font-size: 12pt;
            border-left: 4px solid #667eea;
            margin-bottom: 15px;
        }
        .consultation-info {
            background: #f9f9f9;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            color: #667eea;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 5px;
        }
        .section-content {
            padding-left: 10px;
            border-left: 2px solid #e0e0e0;
        }
        .divider {
            border-top: 1px solid #ddd;
            margin: 25px 0;
        }
        .confidentiality {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 12px;
            font-size: 8pt;
            font-style: italic;
            color: #888;
            text-align: justify;
            margin-top: 30px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MSLDA - Clínica Mais Saúde</h1>
        <p>Relatório de Diagnósticos Psiquiátricos e Psicológicos</p>
    </div>
    
    <div class="patient-info">
        <h2>DADOS DO PACIENTE</h2>
        <p><strong>Nome:</strong> ' . htmlspecialchars($patient['name']) . '</p>
        <p><strong>Data de Nascimento:</strong> ' . date('d/m/Y', strtotime($patient['date_of_birth'])) . '</p>
        <p><strong>Idade:</strong> ' . (date('Y') - date('Y', strtotime($patient['date_of_birth']))) . ' anos</p>
        <p><strong>Gênero:</strong> ' . ($patient['gender'] === 'M' ? 'Masculino' : 'Feminino') . '</p>
        <p><strong>Contato:</strong> ' . htmlspecialchars($patient['contact_number']) . '</p>
        <p><strong>Endereço:</strong> ' . htmlspecialchars($patient['address']) . '</p>
    </div>
    
    <div class="generated-info">
        Relatório gerado em: ' . date('d/m/Y H:i') . ' por ' . htmlspecialchars($user_name) . '
    </div>
';

// Adicionar cada diagnóstico
foreach ($diagnoses as $index => $diagnosis) {
    $html .= '
    <div class="diagnosis-block">
        <div class="diagnosis-header">
            DIAGNÓSTICO #' . ($index + 1) . ' - ' . strtoupper($diagnosis['diagnosis_type']) . '
        </div>
        
        <div class="consultation-info">
            <p><strong>Data da Consulta:</strong> ' . date('d/m/Y', strtotime($diagnosis['consultation_date'])) . '</p>
            <p><strong>Especialidade:</strong> ' . htmlspecialchars($diagnosis['specialty_name']) . '</p>
            <p><strong>Profissional:</strong> ' . htmlspecialchars($diagnosis['doctor_name']) . '</p>
        </div>
        
        <div class="section">
            <div class="section-title">DIAGNÓSTICO PRINCIPAL:</div>
            <div class="section-content">' . nl2br(htmlspecialchars($diagnosis['primary_diagnosis'])) . '</div>
        </div>
    ';
    
    if (!empty($diagnosis['secondary_diagnosis'])) {
        $html .= '
        <div class="section">
            <div class="section-title">DIAGNÓSTICO SECUNDÁRIO:</div>
            <div class="section-content">' . nl2br(htmlspecialchars($diagnosis['secondary_diagnosis'])) . '</div>
        </div>
        ';
    }
    
    if (!empty($diagnosis['symptoms'])) {
        $html .= '
        <div class="section">
            <div class="section-title">SINTOMAS OBSERVADOS:</div>
            <div class="section-content">' . nl2br(htmlspecialchars($diagnosis['symptoms'])) . '</div>
        </div>
        ';
    }
    
    if (!empty($diagnosis['observations'])) {
        $html .= '
        <div class="section">
            <div class="section-title">OBSERVAÇÕES CLÍNICAS:</div>
            <div class="section-content">' . nl2br(htmlspecialchars($diagnosis['observations'])) . '</div>
        </div>
        ';
    }
    
    if (!empty($diagnosis['treatment_plan'])) {
        $html .= '
        <div class="section">
            <div class="section-title">PLANO DE TRATAMENTO:</div>
            <div class="section-content">' . nl2br(htmlspecialchars($diagnosis['treatment_plan'])) . '</div>
        </div>
        ';
    }
    
    if (!empty($diagnosis['follow_up_recommendations'])) {
        $html .= '
        <div class="section">
            <div class="section-title">RECOMENDAÇÕES DE ACOMPANHAMENTO:</div>
            <div class="section-content">' . nl2br(htmlspecialchars($diagnosis['follow_up_recommendations'])) . '</div>
        </div>
        ';
    }
    
    $html .= '</div>';
    
    // Adicionar divisória se não for o último diagnóstico
    if ($index < count($diagnoses) - 1) {
        $html .= '<div class="divider"></div>';
    }
}

// Adicionar nota de confidencialidade
$html .= '
    <div class="confidentiality">
        <strong>NOTA DE CONFIDENCIALIDADE:</strong> Este documento contém informações médicas confidenciais protegidas por lei. 
        A divulgação não autorizada deste relatório é proibida e sujeita a sanções legais. Este relatório deve ser utilizado 
        exclusivamente para fins médicos e terapêuticos relacionados ao tratamento do paciente.
    </div>
</body>
</html>
';

// Configurar DomPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'Arial');

// Criar instância do DomPDF
$dompdf = new Dompdf($options);

// Carregar HTML
$dompdf->loadHtml($html);

// Configurar tamanho e orientação do papel
$dompdf->setPaper('A4', 'portrait');

// Renderizar PDF
$dompdf->render();

// Adicionar numeração de páginas
$canvas = $dompdf->getCanvas();
$canvas->page_text(520, 820, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, array(0.5, 0.5, 0.5));

// Gerar nome do arquivo
$filename = 'Diagnosticos_' . preg_replace('/[^a-zA-Z0-9]/', '_', $patient['name']) . '_' . date('Ymd') . '.pdf';

// Enviar PDF para o navegador
$dompdf->stream($filename, array('Attachment' => 0));
?>
