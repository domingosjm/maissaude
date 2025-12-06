<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$invoice_id) {
    die('ID da fatura não fornecido');
}

// Buscar dados da fatura
$invoice_query = $mysqli->query("
    SELECT i.*, p.name as patient_name, p.codigo, p.cpf, p.phone, p.address, p.email
    FROM invoices i
    JOIN patients p ON i.patient_id = p.id
    WHERE i.id = $invoice_id
");

if (!$invoice_query || $invoice_query->num_rows === 0) {
    die('Fatura não encontrada');
}

$invoice = $invoice_query->fetch_assoc();

// Buscar itens da fatura
$items_query = $mysqli->query("
    SELECT ii.*, s.name as service_name
    FROM invoice_items ii
    LEFT JOIN services s ON ii.service_id = s.id
    WHERE ii.invoice_id = $invoice_id
    ORDER BY ii.id
");

$items = [];
while ($item = $items_query->fetch_assoc()) {
    $items[] = $item;
}

// Buscar configurações da empresa
$company_settings = [];
$settings_query = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE category = 'financial' AND setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_nuit', 'tax_rate', 'company_logo')");
while ($setting = $settings_query->fetch_assoc()) {
    $company_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Logo desabilitado

// Preparar logo se existir
$company_logo_data = '';
if (!empty($company_settings['company_logo'])) {
    // Suporta caminho relativo (ex: 'uploads/logo.png') ou base64
    $logo = $company_settings['company_logo'];
    if (preg_match('/^data:image\//', $logo)) {
        // Já está em base64
        $company_logo_data = $logo;
    } elseif (file_exists($logo)) {
        // Caminho relativo no servidor
        $type = pathinfo($logo, PATHINFO_EXTENSION);
        $data = file_get_contents($logo);
        $company_logo_data = 'data:image/' . $type . ';base64,' . base64_encode($data);
    } elseif (file_exists(__DIR__ . '/' . $logo)) {
        // Caminho relativo a partir do diretório do script
        $type = pathinfo($logo, PATHINFO_EXTENSION);
        $data = file_get_contents(__DIR__ . '/' . $logo);
        $company_logo_data = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

// Buscar pagamentos
$payments_query = $mysqli->query("
    SELECT * FROM payments 
    WHERE invoice_id = $invoice_id 
    ORDER BY payment_date
");

$payments = [];
while ($payment = $payments_query->fetch_assoc()) {
    $payments[] = $payment;
}

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

// Status colors

$status_colors = [
    'pendente' => '#04a46c',
    'paga' => '#04a46c',
    'parcial' => '#04a46c',
    'cancelada' => '#EF4444',
    'vencida' => '#DC2626'
];

$status_bg_colors = [
    'pendente' => '#e6f7f0',
    'paga' => '#e6f7f0',
    'parcial' => '#e6f7f0',
    'cancelada' => '#FEE2E2',
    'vencida' => '#FEE2E2'
];

$status_color = $status_colors[$invoice['status']] ?? '#6B7280';
$status_bg = $status_bg_colors[$invoice['status']] ?? '#F3F4F6';

// HTML da fatura
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Montserrat não pode ser importada via @import em Dompdf. Usar Arial/Montserrat local se disponível. */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Montserrat, Arial, sans-serif;
            font-weight: bold;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #04a46c 0%, #04a46c 100%);
            color: white;
            padding: 30px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-family: Montserrat, Arial, sans-serif;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .company-info {
            font-size: 10px;
            opacity: 0.9;
        }
        .invoice-title {
            text-align: left;
            margin-top: -90px;
            margin-left: 32px;
        }
        .invoice-number {
            font-size: 12px;
            font-family: Montserrat, Arial, sans-serif;
            font-weight: 700;
        }
        .container {
            padding: 0 30px;
        }
        .info-section {
            margin-bottom: 30px;
            overflow: hidden;
        }
        .info-box {
            float: left;
            width: 48%;
            background: #F9FAFB;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #04a46c;
        }
        .info-box.right {
            margin-left: 32px;
        }
        .info-title {
            font-family: Montserrat, Arial, sans-serif;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 10px;
            color: #04a46c;
        }
        .info-line {
            margin-bottom: 5px;
            font-size: 11px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 11px;
            background: ' . $status_bg . ';
            color: ' . $status_color . ';
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #04a46c;
            color: white;
            padding: 12px;
            text-align: left;
            font-family: Montserrat, Arial, sans-serif;
            font-weight: 700;
            font-size: 11px;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #E5E7EB;
        }
        tr:nth-child(even) {
            background: #F9FAFB;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .total-line {
            padding: 25px 0;
            border-bottom: 1px solid #E5E7EB;
            overflow: hidden;
        }
        .total-line.final {
            border-bottom: none;
            border-top: 2px solid #04a46c;
            font-size: 16px;
            font-family: Montserrat, Arial, sans-serif;
            font-weight: 700;
            color: #04a46c;
            padding-top: 15px;
            margin-top: 10px;
        }
        .total-label {
            float: left;
            font-weight: bold;
        }
        .total-value {
            float: right;
        }
        .payments-section {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #E5E7EB;
        }
        .payment-item {
            background: #ECFDF5;
            padding: 10px;
            margin-bottom: 5px;
            border-left: 3px solid #10B981;
            font-size: 11px;
        }
        .notes-section {
            margin-top: 30px;
            padding: 15px;
            background: #e6f7f0;
            border-left: 4px solid #04a46c;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 10px;
            color: #6B7280;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>

     <div class="header" style="text-align: center;">
        ' . ($company_logo_data ? '<div style="width:100%;text-align:center;"><img src="' . $company_logo_data . '" alt="Logo" style="height: 90px; margin: 0 auto 10px auto; display: block; max-width:160px; object-fit:contain;"></div>' : '') . '
        <div class="company-name">' . htmlspecialchars($company_settings['company_name'] ?? 'Integrada Mais Saúde') . '</div>
        <div class="company-info">
            ' . nl2br(htmlspecialchars($company_settings['company_address'] ?? '')) . '<br>
            Tel: ' . htmlspecialchars($company_settings['company_phone'] ?? '') . ' | 
            Email: ' . htmlspecialchars($company_settings['company_email'] ?? '') . '<br>
            NUIT: ' . htmlspecialchars($company_settings['company_nuit'] ?? '') . '
        </div>
            </div>
        </div>
        <div class="invoice-title">
            <div class="invoice-number">FATURA</div>
            <div>' . htmlspecialchars($invoice['invoice_number']) . '</div>
        </div>
    </div>

    <div class="container">
        <div class="info-section clearfix">
            <div class="info-box">
                <div class="info-title">CLIENTE</div>
                <div class="info-line"><span class="info-label">Nome:</span> ' . htmlspecialchars($invoice['patient_name']) . '</div>
                <div class="info-line"><span class="info-label">Código:</span> ' . htmlspecialchars($invoice['codigo']) . '</div>
                ' . ($invoice['cpf'] ? '<div class="info-line"><span class="info-label">BI/NUIT:</span> ' . htmlspecialchars($invoice['cpf']) . '</div>' : '') . '
                ' . ($invoice['phone'] ? '<div class="info-line"><span class="info-label">Telefone:</span> ' . htmlspecialchars($invoice['phone']) . '</div>' : '') . '
                ' . ($invoice['email'] ? '<div class="info-line"><span class="info-label">Email:</span> ' . htmlspecialchars($invoice['email']) . '</div>' : '') . '
                ' . ($invoice['address'] ? '<div class="info-line"><span class="info-label">Endereço:</span> ' . htmlspecialchars($invoice['address']) . '</div>' : '') . '
            </div>

            <div class="info-box right">
                <div class="info-title">DETALHES DA FATURA</div>
                <div class="info-line"><span class="info-label">Emissão:</span> ' . date('d/m/Y', strtotime($invoice['issue_date'])) . '</div>
                <div class="info-line"><span class="info-label">Vencimento:</span> ' . date('d/m/Y', strtotime($invoice['due_date'])) . '</div>
                <div class="info-line"><span class="info-label">Status:</span> <span class="status-badge">' . strtoupper($invoice['status']) . '</span></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">DESCRIÇÃO</th>
                    <th class="text-center" style="width: 10%;">QTD</th>
                    <th class="text-right" style="width: 15%;">PREÇO UNIT.</th>
                    <th class="text-right" style="width: 10%;">DESCONTO</th>
                    <th class="text-right" style="width: 15%;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>';

foreach ($items as $item) {
    $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td class="text-center">' . $item['quantity'] . '</td>
                    <td class="text-right">' . number_format($item['unit_price'], 2, ',', '.') . ' MT</td>
                    <td class="text-right">' . number_format($item['discount'], 2, ',', '.') . ' MT</td>
                    <td class="text-right"><strong>' . number_format($item['subtotal'], 2, ',', '.') . ' MT</strong></td>
                </tr>';
}

$html .= '
            </tbody>
        </table>

        <div class="totals-section">
            <div class="total-line">
                <span class="total-label">Subtotal:</span>
                <span class="total-value">' . number_format($invoice['subtotal'], 2, ',', '.') . ' MT</span>
            </div>';

if ($invoice['discount'] > 0) {
    $html .= '
            <div class="total-line">
                <span class="total-label">Desconto (' . $invoice['discount_percent'] . '%):</span>
                <span class="total-value">- ' . number_format($invoice['discount'], 2, ',', '.') . ' MT</span>
            </div>';
}

if ($invoice['tax'] > 0) {
    $tax_rate = $company_settings['tax_rate'] ?? 16;
    $html .= '
            <div class="total-line">
                <span class="total-label">IVA (' . $tax_rate . '%):</span>
                <span class="total-value">' . number_format($invoice['tax'], 2, ',', '.') . ' MT</span>
            </div>';
}

$html .= '
            <div class="total-line final">
                <span class="total-label">TOTAL:</span>
                <span class="total-value">' . number_format($invoice['total'], 2, ',', '.') . ' MT</span>
            </div>
        </div>

        <div style="clear: both;"></div>';

if (!empty($payments)) {
    $html .= '
        <div class="payments-section">
            <div class="info-title">PAGAMENTOS RECEBIDOS</div>';
    
    foreach ($payments as $payment) {
        $html .= '
            <div class="payment-item">
                <strong>' . number_format($payment['amount'], 2, ',', '.') . ' MT</strong> - 
                ' . date('d/m/Y H:i', strtotime($payment['payment_date'])) . ' - 
                ' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . 
                ($payment['reference'] ? ' (Ref: ' . htmlspecialchars($payment['reference']) . ')' : '') . '
            </div>';
    }
    
    $html .= '
            <div class="total-line" style="margin-top: 15px;">
                <span class="total-label">Total Pago:</span>
                <span class="total-value" style="color: #04a46c; font-weight: bold;">' . number_format($invoice['amount_paid'], 2, ',', '.') . ' MT</span>
            </div>
            <div class="total-line">
                <span class="total-label">Saldo Pendente:</span>
                <span class="total-value" style="color: #EF4444; font-weight: bold;">' . number_format($invoice['amount_due'], 2, ',', '.') . ' MT</span>
            </div>
        </div>';
}

$html .= '
        <div class="footer">
            <p><strong>Obrigado pela preferência!</strong></p>
            <p>Este documento foi gerado eletronicamente e é válido sem assinatura.</p>
            <p>Emitido em: ' . date('d/m/Y H:i:s') . '</p>
        </div>
    </div>
</body>
</html>';

// Gerar PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$filename = 'Fatura_' . $invoice['invoice_number'] . '_' . date('Ymd') . '.pdf';

// Enviar para o navegador
$dompdf->stream($filename, ['Attachment' => false]);
