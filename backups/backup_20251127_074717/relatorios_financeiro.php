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

$tipo = $_GET['tipo'] ?? 'receitas';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$formato = $_GET['formato'] ?? 'html'; // html ou pdf

// Configurações da empresa
$company_settings = [];
$settings_query = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE category = 'financial'");
while ($setting = $settings_query->fetch_assoc()) {
    $company_settings[$setting['setting_key']] = $setting['setting_value'];
}

$company_name = $company_settings['company_name'] ?? 'Integrada Mais Saúde';

// Função para formatar moeda
function formatCurrency($value) {
    return number_format($value, 2, ',', '.') . ' MT';
}

// Preparar dados conforme o tipo de relatório
$titulo = '';
$dados = [];

switch ($tipo) {
    case 'receitas':
        $titulo = 'Relatório de Receitas';
        
        // Total de receitas por categoria
        $query_categorias = $mysqli->query("
            SELECT category, SUM(amount) as total, COUNT(*) as quantidade
            FROM cash_flow
            WHERE type = 'entrada' 
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY category
            ORDER BY total DESC
        ");
        
        $dados['categorias'] = [];
        $dados['total_geral'] = 0;
        while ($row = $query_categorias->fetch_assoc()) {
            $dados['categorias'][] = $row;
            $dados['total_geral'] += $row['total'];
        }
        
        // Receitas por dia
        $query_diario = $mysqli->query("
            SELECT DATE(transaction_date) as data, SUM(amount) as total
            FROM cash_flow
            WHERE type = 'entrada'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY DATE(transaction_date)
            ORDER BY data
        ");
        
        $dados['diario'] = [];
        while ($row = $query_diario->fetch_assoc()) {
            $dados['diario'][] = $row;
        }
        
        // Top 10 transações
        $query_top = $mysqli->query("
            SELECT description, amount, category, transaction_date
            FROM cash_flow
            WHERE type = 'entrada'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            ORDER BY amount DESC
            LIMIT 10
        ");
        
        $dados['top_transacoes'] = [];
        while ($row = $query_top->fetch_assoc()) {
            $dados['top_transacoes'][] = $row;
        }
        break;
        
    case 'despesas':
        $titulo = 'Relatório de Despesas';
        
        // Total de despesas por categoria
        $query_categorias = $mysqli->query("
            SELECT category, SUM(amount) as total, COUNT(*) as quantidade
            FROM cash_flow
            WHERE type = 'saida'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY category
            ORDER BY total DESC
        ");
        
        $dados['categorias'] = [];
        $dados['total_geral'] = 0;
        while ($row = $query_categorias->fetch_assoc()) {
            $dados['categorias'][] = $row;
            $dados['total_geral'] += $row['total'];
        }
        
        // Despesas por dia
        $query_diario = $mysqli->query("
            SELECT DATE(transaction_date) as data, SUM(amount) as total
            FROM cash_flow
            WHERE type = 'saida'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY DATE(transaction_date)
            ORDER BY data
        ");
        
        $dados['diario'] = [];
        while ($row = $query_diario->fetch_assoc()) {
            $dados['diario'][] = $row;
        }
        
        // Top 10 despesas
        $query_top = $mysqli->query("
            SELECT description, amount, category, transaction_date
            FROM cash_flow
            WHERE type = 'saida'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            ORDER BY amount DESC
            LIMIT 10
        ");
        
        $dados['top_transacoes'] = [];
        while ($row = $query_top->fetch_assoc()) {
            $dados['top_transacoes'][] = $row;
        }
        break;
        
    case 'fluxo':
        $titulo = 'Relatório de Fluxo de Caixa';
        
        // Totais
        $entradas = $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_flow WHERE type = 'entrada' AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'")->fetch_assoc()['total'];
        $saidas = $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_flow WHERE type = 'saida' AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'")->fetch_assoc()['total'];
        
        $dados['entradas'] = $entradas;
        $dados['saidas'] = $saidas;
        $dados['saldo'] = $entradas - $saidas;
        
        // Fluxo diário
        $query_fluxo = $mysqli->query("
            SELECT 
                DATE(transaction_date) as data,
                SUM(CASE WHEN type = 'entrada' THEN amount ELSE 0 END) as entradas,
                SUM(CASE WHEN type = 'saida' THEN amount ELSE 0 END) as saidas
            FROM cash_flow
            WHERE DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY DATE(transaction_date)
            ORDER BY data
        ");
        
        $dados['fluxo_diario'] = [];
        while ($row = $query_fluxo->fetch_assoc()) {
            $row['saldo'] = $row['entradas'] - $row['saidas'];
            $dados['fluxo_diario'][] = $row;
        }
        break;
        
    case 'receber':
        $titulo = 'Contas a Receber';
        
        // Faturas pendentes e parciais
        $query_receber = $mysqli->query("
            SELECT 
                i.invoice_number,
                i.issue_date,
                i.due_date,
                i.total,
                i.amount_paid,
                i.amount_due,
                i.status,
                p.name as patient_name,
                p.codigo,
                DATEDIFF(CURDATE(), i.due_date) as dias_atraso
            FROM invoices i
            JOIN patients p ON i.patient_id = p.id
            WHERE i.status IN ('pendente', 'parcial', 'vencida')
            AND DATE(i.issue_date) <= '$data_fim'
            ORDER BY i.due_date ASC
        ");
        
        $dados['faturas'] = [];
        $dados['total_receber'] = 0;
        $dados['total_vencido'] = 0;
        $dados['total_a_vencer'] = 0;
        
        while ($row = $query_receber->fetch_assoc()) {
            $dados['faturas'][] = $row;
            $dados['total_receber'] += $row['amount_due'];
            
            if ($row['dias_atraso'] > 0) {
                $dados['total_vencido'] += $row['amount_due'];
            } else {
                $dados['total_a_vencer'] += $row['amount_due'];
            }
        }
        break;
        
    case 'demonstrativo':
        $titulo = 'Demonstrativo Financeiro';
        
        // Receitas
        $receitas_query = $mysqli->query("
            SELECT category, SUM(amount) as total
            FROM cash_flow
            WHERE type = 'entrada'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY category
        ");
        
        $dados['receitas'] = [];
        $dados['total_receitas'] = 0;
        while ($row = $receitas_query->fetch_assoc()) {
            $dados['receitas'][] = $row;
            $dados['total_receitas'] += $row['total'];
        }
        
        // Despesas
        $despesas_query = $mysqli->query("
            SELECT category, SUM(amount) as total
            FROM cash_flow
            WHERE type = 'saida'
            AND DATE(transaction_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY category
        ");
        
        $dados['despesas'] = [];
        $dados['total_despesas'] = 0;
        while ($row = $despesas_query->fetch_assoc()) {
            $dados['despesas'][] = $row;
            $dados['total_despesas'] += $row['total'];
        }
        
        $dados['resultado'] = $dados['total_receitas'] - $dados['total_despesas'];
        break;
        
    case 'servicos':
        $titulo = 'Serviços Mais Vendidos';
        
        $query_servicos = $mysqli->query("
            SELECT 
                s.name,
                s.category,
                s.price,
                COUNT(ii.id) as quantidade_vendida,
                SUM(ii.subtotal) as faturamento_total
            FROM invoice_items ii
            JOIN services s ON ii.service_id = s.id
            JOIN invoices i ON ii.invoice_id = i.id
            WHERE DATE(i.issue_date) BETWEEN '$data_inicio' AND '$data_fim'
            GROUP BY s.id
            ORDER BY faturamento_total DESC
            LIMIT 20
        ");
        
        $dados['servicos'] = [];
        $dados['total_faturamento'] = 0;
        while ($row = $query_servicos->fetch_assoc()) {
            $dados['servicos'][] = $row;
            $dados['total_faturamento'] += $row['faturamento_total'];
        }
        break;
}

// Gerar HTML do relatório
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #04a46c 0%, #04a46c 100%);
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .periodo {
            background: #f0f0f0;
            padding: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #04a46c;
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
            font-weight: bold;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-box {
            background: #e6f7f0;
            border: 2px solid #04a46c;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .total-box h3 {
            color: #04a46c;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .total-value {
            font-size: 24px;
            font-weight: bold;
            color: #04a46c;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        .summary-card {
            background: #f9f9f9;
            border-left: 4px solid #04a46c;
            padding: 15px;
        }
        .summary-card h4 {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .summary-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #04a46c;
        }
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($company_name) ?></h1>
        <h2><?= $titulo ?></h2>
    </div>
    
    <div class="periodo">
        <strong>Período:</strong> <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?>
        <br>
        <strong>Gerado em:</strong> <?= date('d/m/Y H:i:s') ?>
    </div>

    <?php if ($tipo === 'receitas'): ?>
        <div class="total-box">
            <h3>Total de Receitas</h3>
            <div class="total-value"><?= formatCurrency($dados['total_geral']) ?></div>
        </div>
        
        <h3 style="margin-top: 30px; color: #04a46c;">Receitas por Categoria</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="text-center">Quantidade</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['categorias'] as $cat): ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['category']) ?></td>
                        <td class="text-center"><?= $cat['quantidade'] ?></td>
                        <td class="text-right"><?= formatCurrency($cat['total']) ?></td>
                        <td class="text-right"><?= number_format(($cat['total'] / $dados['total_geral']) * 100, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (!empty($dados['top_transacoes'])): ?>
            <h3 style="margin-top: 30px; color: #04a46c;">Top 10 Maiores Receitas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados['top_transacoes'] as $trans): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($trans['transaction_date'])) ?></td>
                            <td><?= htmlspecialchars($trans['description']) ?></td>
                            <td><?= htmlspecialchars($trans['category']) ?></td>
                            <td class="text-right"><?= formatCurrency($trans['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($tipo === 'despesas'): ?>
        <div class="total-box">
            <h3>Total de Despesas</h3>
            <div class="total-value"><?= formatCurrency($dados['total_geral']) ?></div>
        </div>
        
        <h3 style="margin-top: 30px; color: #04a46c;">Despesas por Categoria</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="text-center">Quantidade</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['categorias'] as $cat): ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['category']) ?></td>
                        <td class="text-center"><?= $cat['quantidade'] ?></td>
                        <td class="text-right"><?= formatCurrency($cat['total']) ?></td>
                        <td class="text-right"><?= number_format(($cat['total'] / $dados['total_geral']) * 100, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (!empty($dados['top_transacoes'])): ?>
            <h3 style="margin-top: 30px; color: #04a46c;">Top 10 Maiores Despesas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados['top_transacoes'] as $trans): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($trans['transaction_date'])) ?></td>
                            <td><?= htmlspecialchars($trans['description']) ?></td>
                            <td><?= htmlspecialchars($trans['category']) ?></td>
                            <td class="text-right"><?= formatCurrency($trans['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($tipo === 'fluxo'): ?>
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total de Entradas</h4>
                <div class="value" style="color: #10B981;"><?= formatCurrency($dados['entradas']) ?></div>
            </div>
            <div class="summary-card">
                <h4>Total de Saídas</h4>
                <div class="value" style="color: #EF4444;"><?= formatCurrency($dados['saidas']) ?></div>
            </div>
            <div class="summary-card">
                <h4>Saldo do Período</h4>
                <div class="value" style="color: <?= $dados['saldo'] >= 0 ? '#10B981' : '#EF4444' ?>;"><?= formatCurrency($dados['saldo']) ?></div>
            </div>
        </div>
        
        <h3 style="margin-top: 30px; color: #04a46c;">Fluxo Diário</h3>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th class="text-right">Entradas</th>
                    <th class="text-right">Saídas</th>
                    <th class="text-right">Saldo do Dia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['fluxo_diario'] as $fluxo): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($fluxo['data'])) ?></td>
                        <td class="text-right" style="color: #10B981;"><?= formatCurrency($fluxo['entradas']) ?></td>
                        <td class="text-right" style="color: #EF4444;"><?= formatCurrency($fluxo['saidas']) ?></td>
                        <td class="text-right" style="color: <?= $fluxo['saldo'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                            <strong><?= formatCurrency($fluxo['saldo']) ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($tipo === 'receber'): ?>
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total a Receber</h4>
                <div class="value"><?= formatCurrency($dados['total_receber']) ?></div>
            </div>
            <div class="summary-card">
                <h4>Vencido</h4>
                <div class="value" style="color: #EF4444;"><?= formatCurrency($dados['total_vencido']) ?></div>
            </div>
            <div class="summary-card">
                <h4>A Vencer</h4>
                <div class="value" style="color: #10B981;"><?= formatCurrency($dados['total_a_vencer']) ?></div>
            </div>
        </div>
        
        <h3 style="margin-top: 30px; color: #04a46c;">Detalhamento das Faturas</h3>
        <table>
            <thead>
                <tr>
                    <th>Fatura</th>
                    <th>Paciente</th>
                    <th class="text-center">Emissão</th>
                    <th class="text-center">Vencimento</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Pago</th>
                    <th class="text-right">A Receber</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['faturas'] as $fatura): ?>
                    <tr>
                        <td><?= htmlspecialchars($fatura['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($fatura['patient_name']) ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($fatura['issue_date'])) ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($fatura['due_date'])) ?></td>
                        <td class="text-right"><?= formatCurrency($fatura['total']) ?></td>
                        <td class="text-right"><?= formatCurrency($fatura['amount_paid']) ?></td>
                        <td class="text-right"><strong><?= formatCurrency($fatura['amount_due']) ?></strong></td>
                        <td class="text-center">
                            <?php if ($fatura['dias_atraso'] > 0): ?>
                                <span style="color: #EF4444;">Vencido (<?= $fatura['dias_atraso'] ?>d)</span>
                            <?php else: ?>
                                <span style="color: #F59E0B;"><?= ucfirst($fatura['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($tipo === 'demonstrativo'): ?>
        <h3 style="margin-top: 20px; color: #04a46c;">RECEITAS</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['receitas'] as $rec): ?>
                    <tr>
                        <td><?= htmlspecialchars($rec['category']) ?></td>
                        <td class="text-right"><?= formatCurrency($rec['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: #e6f7f0; font-weight: bold;">
                    <td>TOTAL DE RECEITAS</td>
                    <td class="text-right"><?= formatCurrency($dados['total_receitas']) ?></td>
                </tr>
            </tbody>
        </table>
        
        <h3 style="margin-top: 30px; color: #04a46c;">DESPESAS</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['despesas'] as $desp): ?>
                    <tr>
                        <td><?= htmlspecialchars($desp['category']) ?></td>
                        <td class="text-right"><?= formatCurrency($desp['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: #fee2e2; font-weight: bold;">
                    <td>TOTAL DE DESPESAS</td>
                    <td class="text-right"><?= formatCurrency($dados['total_despesas']) ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="total-box" style="<?= $dados['resultado'] >= 0 ? 'background: #e6f7f0; border-color: #10B981;' : 'background: #fee2e2; border-color: #EF4444;' ?>">
            <h3 style="color: <?= $dados['resultado'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                RESULTADO DO PERÍODO
            </h3>
            <div class="total-value" style="color: <?= $dados['resultado'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                <?= formatCurrency(abs($dados['resultado'])) ?>
                <?= $dados['resultado'] >= 0 ? '(Lucro)' : '(Prejuízo)' ?>
            </div>
        </div>

    <?php elseif ($tipo === 'servicos'): ?>
        <div class="total-box">
            <h3>Faturamento Total dos Serviços</h3>
            <div class="total-value"><?= formatCurrency($dados['total_faturamento']) ?></div>
        </div>
        
        <h3 style="margin-top: 30px; color: #04a46c;">Ranking de Serviços</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Serviço</th>
                    <th>Categoria</th>
                    <th class="text-center">Qtd Vendida</th>
                    <th class="text-right">Preço Unit.</th>
                    <th class="text-right">Faturamento</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                <?php $pos = 1; foreach ($dados['servicos'] as $serv): ?>
                    <tr>
                        <td class="text-center"><strong><?= $pos++ ?></strong></td>
                        <td><?= htmlspecialchars($serv['name']) ?></td>
                        <td><?= htmlspecialchars($serv['category']) ?></td>
                        <td class="text-center"><?= $serv['quantidade_vendida'] ?></td>
                        <td class="text-right"><?= formatCurrency($serv['price']) ?></td>
                        <td class="text-right"><strong><?= formatCurrency($serv['faturamento_total']) ?></strong></td>
                        <td class="text-right"><?= number_format(($serv['faturamento_total'] / $dados['total_faturamento']) * 100, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print()" style="background: #04a46c; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            Imprimir Relatório
        </button>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
?>
