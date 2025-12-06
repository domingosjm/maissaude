<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || !isset($_GET['attendance_id'])) {
    die('Acesso negado');
}

$attendance_id = (int)$_GET['attendance_id'];

// Buscar dados da sessão psiquiátrica
$query = "
    SELECT 
        ps.*, 
        p.name as patient_name, 
        p.codigo as patient_code,
        p.birth_date,
        p.cpf,
        u.name as psychiatrist_name,
        prof.crm_crp,
        a.scheduled_at
    FROM psychiatry_sessions ps
    JOIN attendances att ON ps.attendance_id = att.id
    JOIN appointments a ON att.appointment_id = a.id
    JOIN patients p ON a.patient_id = p.id
    JOIN professionals prof ON a.professional_id = prof.id
    JOIN users u ON prof.user_id = u.id
    WHERE ps.attendance_id = $attendance_id
";

$result = $mysqli->query($query);
if (!$result || $result->num_rows === 0) {
    die('Sessão não encontrada');
}

$session = $result->fetch_assoc();

// Buscar prescrições associadas
$prescriptions = [];
$presc_query = $mysqli->query("
    SELECT 
        m.name as medication,
        pi.dosage,
        pi.instructions
    FROM prescriptions pr
    JOIN prescription_items pi ON pr.id = pi.prescription_id
    LEFT JOIN medications m ON pi.medication_id = m.id
    WHERE pr.attendance_id = $attendance_id
    ORDER BY pi.id
");

if ($presc_query) {
    while ($row = $presc_query->fetch_assoc()) {
        $prescriptions[] = $row;
    }
}

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Gerar HTML do relatório
$prescricao_html = '';
if (!empty($prescriptions)) {
    $prescricao_html = '<div class="section">
        <div class="section-title">PRESCRIÇÃO MÉDICA</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Medicamento</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Dosagem</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Instruções</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($prescriptions as $presc) {
        $prescricao_html .= '<tr>
            <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($presc['medication']) . '</td>
            <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($presc['dosage']) . '</td>
            <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($presc['instructions']) . '</td>
        </tr>';
    }
    
    $prescricao_html .= '</tbody></table></div>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12pt; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #8b5cf6; padding-bottom: 20px; }
        .header h1 { color: #8b5cf6; margin: 0; font-size: 24pt; }
        .header p { margin: 5px 0; font-size: 11pt; color: #666; }
        .section { margin-bottom: 25px; }
        .section-title { background: #8b5cf6; color: white; padding: 8px 15px; margin-bottom: 10px; font-size: 14pt; font-weight: bold; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; width: 150px; padding: 5px; color: #555; }
        .info-value { display: table-cell; padding: 5px; }
        .content-box { border: 1px solid #ddd; padding: 15px; background: #f9f9f9; min-height: 100px; }
        .footer { margin-top: 50px; text-align: center; font-size: 10pt; color: #888; border-top: 1px solid #ddd; padding-top: 15px; }
        .signature { margin-top: 60px; text-align: center; }
        .signature-line { border-top: 2px solid #333; width: 300px; margin: 0 auto; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE CONSULTA PSIQUIÁTRICA</h1>
        <p><strong>Sistema Integrado Mais Saúde</strong></p>
        <p>Av. Principal, 1000 - Maputo, Moçambique | Tel: +258 84 000 0000</p>
    </div>
    
    <div class="section">
        <div class="section-title">DADOS DO PACIENTE</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value">' . htmlspecialchars($session['patient_name']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Código:</div>
                <div class="info-value">' . htmlspecialchars($session['patient_code']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">BI:</div>
                <div class="info-value">' . htmlspecialchars($session['cpf'] ?? 'Não informado') . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data de Nascimento:</div>
                <div class="info-value">' . date('d/m/Y', strtotime($session['birth_date'])) . '</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">DADOS DA CONSULTA</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Data da Consulta:</div>
                <div class="info-value">' . date('d/m/Y H:i', strtotime($session['session_date'])) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Psiquiatra:</div>
                <div class="info-value">' . htmlspecialchars($session['psychiatrist_name']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">CRM:</div>
                <div class="info-value">' . htmlspecialchars($session['crm_crp'] ?? 'Não informado') . '</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">OBSERVAÇÕES CLÍNICAS</div>
        <div class="content-box">' . nl2br(htmlspecialchars($session['observations'])) . '</div>
    </div>
    
    <div class="section">
        <div class="section-title">EXAME DO ESTADO MENTAL</div>
        <div class="content-box">' . nl2br(htmlspecialchars($session['mental_status_exam'] ?? 'Não informado')) . '</div>
    </div>
    
    <div class="section">
        <div class="section-title">DIAGNÓSTICO</div>
        <div class="content-box">' . nl2br(htmlspecialchars($session['diagnosis'] ?? 'Não informado')) . '</div>
    </div>
    
    <div class="section">
        <div class="section-title">PLANO DE TRATAMENTO</div>
        <div class="content-box">' . nl2br(htmlspecialchars($session['treatment_plan'] ?? 'Não informado')) . '</div>
    </div>
    
    ' . $prescricao_html . '
    
    <div class="signature">
        <div class="signature-line">
            <strong>' . htmlspecialchars($session['psychiatrist_name']) . '</strong><br>
            CRM: ' . htmlspecialchars($session['crm_crp'] ?? 'Não informado') . '
        </div>
    </div>
    
    <div class="footer">
        Relatório gerado em ' . date('d/m/Y H:i:s') . ' | Sistema Integrado Mais Saúde
    </div>
</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$filename = 'Relatorio_Psiquiatria_' . $session['patient_code'] . '_' . date('Ymd_His') . '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
