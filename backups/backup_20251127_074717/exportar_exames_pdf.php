<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar se foi passado exam_request_id
if (!isset($_GET['exam_request_id'])) {
    die('ID do exame não fornecido.');
}

$exam_request_id = (int)$_GET['exam_request_id'];

// Buscar dados do exame
$query = "
    SELECT 
        eresult.id as exam_result_id,
        eresult.result,
        eresult.validation_notes,
        eresult.validated_at,
        er.requested_at,
        e.name as exam_name,
        e.description as exam_description,
        p.name as patient_name,
        p.codigo as patient_code,
        p.birth_date,
        p.cpf,
        p.phone,
        u.name as requesting_professional_name,
        validator.name as validated_by_name
    FROM exam_results eresult
    INNER JOIN exam_requests er ON eresult.exam_request_id = er.id
    INNER JOIN exams e ON er.exam_id = e.id
    INNER JOIN attendances att ON er.attendance_id = att.id
    INNER JOIN appointments a ON att.appointment_id = a.id
    INNER JOIN patients p ON a.patient_id = p.id
    LEFT JOIN professionals prof ON a.professional_id = prof.id
    LEFT JOIN users u ON prof.user_id = u.id
    LEFT JOIN users validator ON eresult.validated_by = validator.id
    WHERE er.id = ?
    AND eresult.status = 'liberado'
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $exam_request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Exame não encontrado ou não liberado.');
}

$exam = $result->fetch_assoc();


// Calcular idade
$birth_date = new DateTime($exam['birth_date']);
$today = new DateTime();
$age = $today->diff($birth_date)->y;

// Buscar configurações da empresa para logo
$company_settings = [];
$settings_query = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE category = 'financial' AND setting_key IN ('company_logo')");
while ($setting = $settings_query->fetch_assoc()) {
    $company_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Preparar logo se existir
$company_logo_data = '';
if (!empty($company_settings['company_logo'])) {
    $logo = $company_settings['company_logo'];
    if (preg_match('/^data:image\//', $logo)) {
        $company_logo_data = $logo;
    } elseif (file_exists($logo)) {
        $type = pathinfo($logo, PATHINFO_EXTENSION);
        $data = file_get_contents($logo);
        $company_logo_data = 'data:image/' . $type . ';base64,' . base64_encode($data);
    } elseif (file_exists(__DIR__ . '/' . $logo)) {
        $type = pathinfo($logo, PATHINFO_EXTENSION);
        $data = file_get_contents(__DIR__ . '/' . $logo);
        $company_logo_data = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

// HTML do PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Montserrat não pode ser importada via @import em Dompdf. Usar Arial/Montserrat local se disponível. */
        @page {
            margin: 20mm;
        }
        body {
            font-family: Montserrat, DejaVu Sans, sans-serif;
            font-weight: 700;
            font-size: 11pt;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
            padding: 0 0 18px 0;
            border-bottom: 2px solid #04a46c;
            background: #fff;
        }
        .header h1 {
            margin: 0 0 2px 0;
            color: #04a46c;
            font-size: 22pt;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .header p {
            margin: 2px 0;
            color: #333;
            font-size: 10pt;
        }
        .section-title {
            color: #04a46c;
            background: none;
            border-left: 4px solid #ffffffff;
            padding: 0 0 0 12px;
            margin: 24px 0 12px 0;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 13pt;
        }
        .info-box {
            background: #f8fdfa;
            box-shadow: 0 2px 8px rgba(4,164,108,0.07);
            border-left: 4px solid #04a46c;
            padding: 16px 18px 10px 18px;
            margin-bottom: 18px;
            border-radius: 10px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 150px;
        }
        .info-value {
            color: #1e293b;
        }
        .result-box {
            background: #fff;
            box-shadow: 0 2px 8px rgba(4,164,108,0.07);
            border-left: 4px solid #04a46c;
            padding: 18px 18px 12px 18px;
            margin: 20px 0 16px 0;
            border-radius: 10px;
        }
        .result-title {
            color: #04a46c;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #04a46c;
        }
        .result-content {
            white-space: pre-line;
            color: #1e293b;
            font-size: 11pt;
            line-height: 1.8;
        }
        .observations-box {
            background: #f8fdfa;
            box-shadow: 0 2px 8px rgba(4,164,108,0.07);
            border-left: 4px solid #04a46c;
            padding: 12px 16px 10px 16px;
            margin: 14px 0;
            border-radius: 10px;
        }
        .footer {
            margin-top: 36px;
            padding-top: 14px;
            border-top: 1.5px solid #e6f7f0;
            text-align: center;
            font-size: 9pt;
            color: #64748b;
        }
        .validation-stamp {
            background: #e6f7f0;
            border: 2px solid #04a46c;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border-radius: 8px;
        }
        .validation-stamp .title {
            color: #04a46c;
            font-family: Montserrat, DejaVu Sans, sans-serif;
            font-weight: 700;
            font-size: 12pt;
            margin-bottom: 8px;
        }
        .validation-stamp .info {
            color: #04a46c;
            font-size: 10pt;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100pt;
            color: rgba(4, 164, 108, 0.05);
            font-weight: bold;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="watermark">MSLDA</div>
    

    <!-- Cabeçalho -->
    <div class="header">
        ' . ($company_logo_data ? '<img src="' . $company_logo_data . '" alt="Logo" style="height: 80px; margin-bottom: 10px; max-width:180px; object-fit:contain; display:block; margin-left:auto; margin-right:auto;">' : '') . '
        
        <p>Laboratório de Análises Clínicas</p>
        <p>Avenida Eduardo Mondlane, Rua Vasco Fernandes, Beira, Sofala.</p>
        <p style="margin-top: 10px; font-weight: bold; color: #2c8a72ff;">RESULTADO DE EXAME</p>
    </div>
    
    <!-- Informações do Paciente -->
    <div class="section-title">DADOS DO PACIENTE</div>
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Nome:</span>
            <span class="info-value">' . htmlspecialchars($exam['patient_name']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Código:</span>
            <span class="info-value">' . htmlspecialchars($exam['patient_code']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Data de Nascimento:</span>
            <span class="info-value">' . date('d/m/Y', strtotime($exam['birth_date'])) . ' (' . $age . ' anos)</span>
        </div>';

if ($exam['cpf']) {
    $html .= '
        <div class="info-row">
            <span class="info-label"> BI</span>
            <span class="info-value">' . htmlspecialchars($exam['cpf']) . '</span>
        </div>';
}

if ($exam['phone']) {
    $html .= '
        <div class="info-row">
            <span class="info-label">Telefone:</span>
            <span class="info-value">' . htmlspecialchars($exam['phone']) . '</span>
        </div>';
}

$html .= '
    </div>
    
    <!-- Informações do Exame -->
    <div class="section-title">INFORMAÇÕES DO EXAME</div>
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Exame:</span>
            <span class="info-value" style="font-weight: bold; color: #000000ff;">' . htmlspecialchars($exam['exam_name']) . '</span>
        </div>';

if ($exam['exam_description']) {
    $html .= '
        <div class="info-row">
            <span class="info-label">Descrição:</span>
            <span class="info-value">' . htmlspecialchars($exam['exam_description']) . '</span>
        </div>';
}

$html .= '
        <div class="info-row">
            <span class="info-label">Solicitado em:</span>
            <span class="info-value">' . date('d/m/Y H:i', strtotime($exam['requested_at'])) . '</span>
        </div>';

if ($exam['requesting_professional_name']) {
    $html .= '
        <div class="info-row">
            <span class="info-label">Solicitante:</span>
            <span class="info-value">' . htmlspecialchars($exam['requesting_professional_name']) . '</span>
        </div>';
}

$html .= '
    </div>
    
    <!-- Resultado -->
    <div class="section-title">RESULTADO</div>
    <div class="result-box">
        <div class="result-title">Resultado do Exame</div>
        <div class="result-content">' . nl2br(htmlspecialchars($exam['result'])) . '</div>
    </div>';

// Observações (se houver)
if ($exam['validation_notes']) {
    $html .= '
    <div class="observations-box">
        <div style="font-weight: bold; color: #f59e0b; margin-bottom: 8px;">
            <strong>Observações:</strong>
        </div>
        <div style="color: #92400e;">' . nl2br(htmlspecialchars($exam['validation_notes'])) . '</div>
    </div>';
}

// Validação
$html .= '
    <div class="validation-stamp">
        <div class="title">✓ RESULTADO VALIDADO E LIBERADO</div>
        <div class="info">
            Data: ' . date('d/m/Y H:i', strtotime($exam['validated_at'])) . '<br>';

if ($exam['validated_by_name']) {
    $html .= 'Validado por: ' . htmlspecialchars($exam['validated_by_name']) . '<br>';
}

$html .= '
        </div>
    </div>
    
    <!-- Rodapé -->
    <div class="footer">
        <p><strong>CLÍNICA INTEGRADA MAIS SAÚDE,
LDA</strong></p>
        <p>Laboratório de Análises Clínicas</p>
        <p>Este documento é válido apenas com assinatura e carimbo do responsável técnico</p>
        <p style="margin-top: 10px; font-size: 8pt; color: #94a3b8;">
            Documento gerado eletronicamente em ' . date('d/m/Y H:i:s') . '
        </p>
    </div>
</body>
</html>
';

// Gerar PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$filename = 'Exame_' . $exam['patient_code'] . '_' . date('Ymd') . '.pdf';

// Stream do PDF
$dompdf->stream($filename, ['Attachment' => false]);
