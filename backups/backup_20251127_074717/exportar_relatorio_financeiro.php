<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

// Incluir Dompdf
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// Receitas por categoria
$query_receitas = "
    SELECT 
        s.category AS categoria,
        COUNT(DISTINCT i.id) AS num_faturas,
        SUM(ii.quantity) AS quantidade_servicos,
        SUM(ii.subtotal) AS total_receita
    FROM invoices i
    INNER JOIN invoice_items ii ON i.id = ii.invoice_id
    INNER JOIN services s ON ii.service_id = s.id
    WHERE i.status IN ('Paga', 'Parcialmente Paga')
    AND DATE(i.created_at) BETWEEN ? AND ?
    GROUP BY s.category
    ORDER BY total_receita DESC
";
$stmt = $mysqli->prepare($query_receitas);
$stmt->bind_param('ss', $data_inicio, $data_fim);
$stmt->execute();
$receitas_categoria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total de faturas
$query_faturas = "
    SELECT 
        COUNT(*) AS total_faturas,
        SUM(total) AS valor_faturado,
        SUM(amount_paid) AS valor_pago,
        SUM(total - amount_paid) AS valor_pendente
    FROM invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
";
$stmt = $mysqli->prepare($query_faturas);
$stmt->bind_param('ss', $data_inicio, $data_fim);
$stmt->execute();
$resumo_faturas = $stmt->get_result()->fetch_assoc();

// Pagamentos por método
$query_pagamentos = "
    SELECT 
        payment_method AS metodo,
        COUNT(*) AS num_pagamentos,
        SUM(amount) AS total_recebido
    FROM payments
    WHERE DATE(payment_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total_recebido DESC
";
$stmt = $mysqli->prepare($query_pagamentos);
$stmt->bind_param('ss', $data_inicio, $data_fim);
$stmt->execute();
$pagamentos_metodo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Despesas
$query_despesas = "
    SELECT 
        category AS categoria,
        COUNT(*) AS num_despesas,
        SUM(amount) AS total_gasto
    FROM expenses
    WHERE DATE(expense_date) BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_gasto DESC
";
$stmt = $mysqli->prepare($query_despesas);
$stmt->bind_param('ss', $data_inicio, $data_fim);
$stmt->execute();
$despesas_categoria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_despesas = array_sum(array_column($despesas_categoria, 'total_gasto'));
$saldo_periodo = $resumo_faturas['valor_pago'] - $total_despesas;
$total_receita = array_sum(array_column($receitas_categoria, 'total_receita'));

// Gerar HTML do relatório
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #10b981;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .summary-boxes {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-box {
            display: table-cell;
            width: 25%;
            padding: 15px;
            text-align: center;
            border: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        .summary-box h3 {
            margin: 0 0 5px 0;
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-box .value {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .summary-box.positive .value {
            color: #10b981;
        }
        .summary-box.negative .value {
            color: #ef4444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #10b981;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .section-title {
            color: #10b981;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #10b981;
        }
        .two-columns {
            display: table;
            width: 100%;
        }
        .column {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }
        .column:first-child {
            padding-right: 2%;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            color: #666;
            font-size: 9px;
        }
        .item-box {
            background: #f9fafb;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid #10b981;
        }
        .item-box .name {
            font-weight: bold;
            color: #1f2937;
        }
        .item-box .details {
            color: #666;
            font-size: 10px;
        }
        .item-box .amount {
            float: right;
            font-weight: bold;
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO FINANCEIRO</h1>
        <p>Sistema Integrado Mais Saúde - MSLDA</p>
        <p>Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . '</p>
        <p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>
    </div>

    <div class="summary-boxes">
        <div class="summary-box">
            <h3>Total Faturado</h3>
            <div class="value">' . number_format($resumo_faturas['valor_faturado'] ?? 0, 2) . ' MT</div>
            <div class="details">' . ($resumo_faturas['total_faturas'] ?? 0) . ' faturas</div>
        </div>
        <div class="summary-box positive">
            <h3>Total Recebido</h3>
            <div class="value">' . number_format($resumo_faturas['valor_pago'] ?? 0, 2) . ' MT</div>
            <div class="details">' . number_format(($resumo_faturas['valor_pago'] / max($resumo_faturas['valor_faturado'], 1)) * 100, 1) . '% do faturado</div>
        </div>
        <div class="summary-box negative">
            <h3>Total Despesas</h3>
            <div class="value">' . number_format($total_despesas, 2) . ' MT</div>
            <div class="details">' . count($despesas_categoria) . ' categorias</div>
        </div>
        <div class="summary-box ' . ($saldo_periodo >= 0 ? 'positive' : 'negative') . '">
            <h3>Saldo do Período</h3>
            <div class="value">' . number_format($saldo_periodo, 2) . ' MT</div>
            <div class="details">Receitas - Despesas</div>
        </div>
    </div>

    <h2 class="section-title">Receitas por Categoria</h2>
    <table>
        <thead>
            <tr>
                <th>Categoria</th>
                <th style="text-align: center;">Nº Faturas</th>
                <th style="text-align: center;">Qtd. Serviços</th>
                <th style="text-align: right;">Total Receita</th>
                <th style="text-align: center;">% Total</th>
            </tr>
        </thead>
        <tbody>';

foreach ($receitas_categoria as $cat) {
    $percentual = ($cat['total_receita'] / max($total_receita, 1)) * 100;
    $html .= '
            <tr>
                <td>' . htmlspecialchars(ucfirst($cat['categoria'])) . '</td>
                <td style="text-align: center;">' . $cat['num_faturas'] . '</td>
                <td style="text-align: center;">' . $cat['quantidade_servicos'] . '</td>
                <td style="text-align: right;"><strong>' . number_format($cat['total_receita'], 2) . ' MT</strong></td>
                <td style="text-align: center;">' . number_format($percentual, 1) . '%</td>
            </tr>';
}

$html .= '
            <tr style="background: #e5e7eb; font-weight: bold;">
                <td colspan="3" style="text-align: right;">TOTAL</td>
                <td style="text-align: right; color: #10b981;">' . number_format($total_receita, 2) . ' MT</td>
                <td style="text-align: center;">100%</td>
            </tr>
        </tbody>
    </table>

    <div class="two-columns">
        <div class="column">
            <h2 class="section-title">Pagamentos por Método</h2>';

foreach ($pagamentos_metodo as $pag) {
    $html .= '
            <div class="item-box">
                <span class="amount">' . number_format($pag['total_recebido'], 2) . ' MT</span>
                <div class="name">' . htmlspecialchars(ucfirst($pag['metodo'])) . '</div>
                <div class="details">' . $pag['num_pagamentos'] . ' transações</div>
            </div>';
}

$html .= '
        </div>
        <div class="column">
            <h2 class="section-title">Despesas por Categoria</h2>';

foreach ($despesas_categoria as $desp) {
    $html .= '
            <div class="item-box" style="border-left-color: #ef4444;">
                <span class="amount" style="color: #ef4444;">' . number_format($desp['total_gasto'], 2) . ' MT</span>
                <div class="name">' . htmlspecialchars(ucfirst($desp['categoria'])) . '</div>
                <div class="details">' . $desp['num_despesas'] . ' despesas</div>
            </div>';
}

$html .= '
        </div>
    </div>

    <div class="footer">
        <p><strong>Sistema Integrado Mais Saúde - MSLDA</strong></p>
        <p>Este relatório é confidencial e destinado apenas para uso interno da organização.</p>
    </div>
</body>
</html>
';

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Enviar o PDF ao navegador
$filename = 'Relatorio_Financeiro_' . date('Ymd', strtotime($data_inicio)) . '_' . date('Ymd', strtotime($data_fim)) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
