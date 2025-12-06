<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

$user_name = $_SESSION['user_name'] ?? 'Usuário';

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = $_GET['data_fim'] ?? date('Y-m-d'); // Hoje
$tipo_relatorio = $_GET['tipo'] ?? 'financeiro';

// Relatório Financeiro
$relatorio_financeiro = null;
if ($tipo_relatorio === 'financeiro') {
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

    $relatorio_financeiro = [
        'receitas_categoria' => $receitas_categoria,
        'resumo_faturas' => $resumo_faturas,
        'pagamentos_metodo' => $pagamentos_metodo,
        'despesas_categoria' => $despesas_categoria,
        'total_despesas' => $total_despesas,
        'saldo_periodo' => $saldo_periodo
    ];
}

// Relatório de Consultas
$relatorio_consultas = null;
if ($tipo_relatorio === 'consultas') {
    // Atendimentos por especialidade
    $query_especialidades = "
        SELECT 
            s.name AS especialidade,
            COUNT(a.id) AS total_atendimentos,
            COUNT(DISTINCT a.patient_id) AS pacientes_unicos,
            p.name AS profissional
        FROM attendances a
        INNER JOIN appointments app ON a.appointment_id = app.id
        INNER JOIN services s ON app.service_id = s.id
        INNER JOIN professionals p ON app.professional_id = p.id
        WHERE DATE(a.created_at) BETWEEN ? AND ?
        GROUP BY s.name, p.name
        ORDER BY total_atendimentos DESC
    ";
    $stmt = $mysqli->prepare($query_especialidades);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $atendimentos_especialidade = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Atendimentos por profissional
    $query_profissionais = "
        SELECT 
            p.name AS profissional,
            p.specialty AS especialidade,
            COUNT(a.id) AS total_atendimentos,
            COUNT(DISTINCT a.patient_id) AS pacientes_unicos
        FROM attendances a
        INNER JOIN appointments app ON a.appointment_id = app.id
        INNER JOIN professionals p ON app.professional_id = p.id
        WHERE DATE(a.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name, p.specialty
        ORDER BY total_atendimentos DESC
    ";
    $stmt = $mysqli->prepare($query_profissionais);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $atendimentos_profissional = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Status de consultas
    $query_status = "
        SELECT 
            app.status,
            COUNT(*) AS quantidade
        FROM appointments app
        WHERE DATE(app.appointment_date) BETWEEN ? AND ?
        GROUP BY app.status
    ";
    $stmt = $mysqli->prepare($query_status);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $consultas_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $relatorio_consultas = [
        'atendimentos_especialidade' => $atendimentos_especialidade,
        'atendimentos_profissional' => $atendimentos_profissional,
        'consultas_status' => $consultas_status
    ];
}

// Relatório de Farmácia
$relatorio_farmacia = null;
if ($tipo_relatorio === 'farmacia') {
    // Medicamentos mais dispensados
    $query_medicamentos = "
        SELECT 
            m.name AS medicamento,
            COUNT(pi.id) AS vezes_prescrito,
            SUM(pi.quantity) AS quantidade_total,
            ps.quantity AS stock_atual
        FROM prescription_items pi
        INNER JOIN medications m ON pi.medication_id = m.id
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        LEFT JOIN pharmacy_stock ps ON m.id = ps.medication_id
        WHERE p.status = 'Entregue'
        AND DATE(p.updated_at) BETWEEN ? AND ?
        GROUP BY m.id, m.name, ps.quantity
        ORDER BY quantidade_total DESC
        LIMIT 20
    ";
    $stmt = $mysqli->prepare($query_medicamentos);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $medicamentos_dispensados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Stock crítico (menos de 10 unidades)
    $query_stock_baixo = "
        SELECT 
            m.name AS medicamento,
            ps.quantity AS quantidade,
            m.category AS categoria
        FROM pharmacy_stock ps
        INNER JOIN medications m ON ps.medication_id = m.id
        WHERE ps.quantity < 10
        ORDER BY ps.quantity ASC
    ";
    $stock_critico = $mysqli->query($query_stock_baixo)->fetch_all(MYSQLI_ASSOC);

    // Valor de medicamentos faturados
    $query_valor_meds = "
        SELECT 
            SUM(ii.subtotal) AS valor_total_medicamentos
        FROM invoice_items ii
        INNER JOIN services s ON ii.service_id = s.id
        INNER JOIN invoices i ON ii.invoice_id = i.id
        WHERE s.category = 'medicamento'
        AND DATE(i.created_at) BETWEEN ? AND ?
    ";
    $stmt = $mysqli->prepare($query_valor_meds);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $valor_medicamentos = $stmt->get_result()->fetch_assoc();

    $relatorio_farmacia = [
        'medicamentos_dispensados' => $medicamentos_dispensados,
        'stock_critico' => $stock_critico,
        'valor_medicamentos' => $valor_medicamentos['valor_total_medicamentos'] ?? 0
    ];
}

// Relatório de Laboratório
$relatorio_laboratorio = null;
if ($tipo_relatorio === 'laboratorio') {
    // Exames mais solicitados
    $query_exames = "
        SELECT 
            e.name AS exame,
            COUNT(er.id) AS quantidade_solicitada,
            SUM(CASE WHEN er.status = 'Liberado' THEN 1 ELSE 0 END) AS quantidade_liberada,
            SUM(CASE WHEN er.status = 'Pendente' THEN 1 ELSE 0 END) AS quantidade_pendente
        FROM exam_requests er
        INNER JOIN exams e ON er.exam_id = e.id
        WHERE DATE(er.created_at) BETWEEN ? AND ?
        GROUP BY e.id, e.name
        ORDER BY quantidade_solicitada DESC
    ";
    $stmt = $mysqli->prepare($query_exames);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $exames_solicitados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Tempo médio de liberação
    $query_tempo = "
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, er.created_at, er.released_at)) AS horas_media
        FROM exam_requests er
        WHERE er.status = 'Liberado'
        AND DATE(er.created_at) BETWEEN ? AND ?
    ";
    $stmt = $mysqli->prepare($query_tempo);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $tempo_medio = $stmt->get_result()->fetch_assoc();

    $relatorio_laboratorio = [
        'exames_solicitados' => $exames_solicitados,
        'tempo_medio_horas' => round($tempo_medio['horas_media'] ?? 0, 1)
    ];
}

// Relatório de Psicologia/Psiquiatria
$relatorio_mental = null;
if ($tipo_relatorio === 'mental') {
    // Sessões de psicologia
    $query_psicologia = "
        SELECT 
            COUNT(*) AS total_sessoes,
            COUNT(DISTINCT patient_id) AS pacientes_unicos,
            AVG(CASE WHEN next_session_date IS NOT NULL THEN 1 ELSE 0 END) * 100 AS taxa_retorno
        FROM psychology_sessions
        WHERE DATE(session_date) BETWEEN ? AND ?
    ";
    $stmt = $mysqli->prepare($query_psicologia);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $resumo_psicologia = $stmt->get_result()->fetch_assoc();

    // Consultas de psiquiatria
    $query_psiquiatria = "
        SELECT 
            COUNT(*) AS total_consultas,
            COUNT(DISTINCT patient_id) AS pacientes_unicos,
            SUM(CASE WHEN prescriptions IS NOT NULL THEN 1 ELSE 0 END) AS consultas_com_prescricao
        FROM psychiatry_sessions
        WHERE DATE(session_date) BETWEEN ? AND ?
    ";
    $stmt = $mysqli->prepare($query_psiquiatria);
    $stmt->bind_param('ss', $data_inicio, $data_fim);
    $stmt->execute();
    $resumo_psiquiatria = $stmt->get_result()->fetch_assoc();

    $relatorio_mental = [
        'psicologia' => $resumo_psicologia,
        'psiquiatria' => $resumo_psiquiatria
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios | Sistema Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.95); }
        .dark .glass-effect { background: rgba(17, 24, 39, 0.95); }
        @media print {
            .no-print { display: none !important; }
            .glass-effect { background: white; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-gray-50 to-stone-50 dark:from-gray-900 dark:via-gray-800 dark:to-slate-900 min-h-screen">
    <!-- Navbar -->
    <nav class="glass-effect shadow-lg border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-br from-slate-500 to-gray-700 p-2 rounded-xl">
                        <i class="bi bi-file-earmark-bar-graph text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">Relatórios</h1>
                        <p class="text-xs text-gray-500">Análises e Indicadores</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        <i class="bi bi-person-circle mr-1"></i><?= htmlspecialchars($user_name) ?>
                    </span>
                    <a href="dashboard.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg text-sm font-medium transition-all">
                        <i class="bi bi-house-door mr-1"></i>Dashboard
                    </a>
                    <a href="logout.php" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-all">
                        <i class="bi bi-box-arrow-right mr-1"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Filtros -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 no-print">
        <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        <i class="bi bi-file-text mr-1"></i>Tipo de Relatório
                    </label>
                    <select name="tipo" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-slate-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="financeiro" <?= $tipo_relatorio === 'financeiro' ? 'selected' : '' ?>>Financeiro</option>
                        <option value="consultas" <?= $tipo_relatorio === 'consultas' ? 'selected' : '' ?>>Consultas</option>
                        <option value="farmacia" <?= $tipo_relatorio === 'farmacia' ? 'selected' : '' ?>>Farmácia</option>
                        <option value="laboratorio" <?= $tipo_relatorio === 'laboratorio' ? 'selected' : '' ?>>Laboratório</option>
                        <option value="mental" <?= $tipo_relatorio === 'mental' ? 'selected' : '' ?>>Psicologia/Psiquiatria</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        <i class="bi bi-calendar-event mr-1"></i>Data Início
                    </label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-slate-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        <i class="bi bi-calendar-check mr-1"></i>Data Fim
                    </label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-slate-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-slate-500 to-gray-700 hover:from-slate-600 hover:to-gray-800 text-white rounded-lg font-semibold shadow-md transition-all">
                        <i class="bi bi-funnel mr-1"></i>Filtrar
                    </button>
                    <?php if ($tipo_relatorio === 'financeiro'): ?>
                    <a href="exportar_relatorio_financeiro.php?data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" 
                       target="_blank"
                       class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold shadow-md transition-all inline-flex items-center">
                        <i class="bi bi-file-pdf mr-1"></i>PDF
                    </a>
                    <?php endif; ?>
                    <button type="button" onclick="window.print()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold shadow-md transition-all">
                        <i class="bi bi-printer"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Conteúdo dos Relatórios -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        
        <?php if ($tipo_relatorio === 'financeiro' && $relatorio_financeiro): ?>
            <!-- RELATÓRIO FINANCEIRO -->
            <div class="space-y-6">
                <!-- Resumo Geral -->
                <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="bi bi-cash-coin mr-3 text-emerald-600"></i>
                        Resumo Financeiro
                        <span class="ml-auto text-sm font-normal text-gray-500">
                            <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?>
                        </span>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border-l-4 border-blue-500">
                            <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase mb-1">Total Faturado</p>
                            <p class="text-2xl font-bold text-blue-800 dark:text-blue-200">
                                <?= number_format($relatorio_financeiro['resumo_faturas']['valor_faturado'] ?? 0, 2) ?> MT
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                <?= $relatorio_financeiro['resumo_faturas']['total_faturas'] ?? 0 ?> faturas
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-lg p-4 border-l-4 border-emerald-500">
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold uppercase mb-1">Total Recebido</p>
                            <p class="text-2xl font-bold text-emerald-800 dark:text-emerald-200">
                                <?= number_format($relatorio_financeiro['resumo_faturas']['valor_pago'] ?? 0, 2) ?> MT
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                <?= number_format(($relatorio_financeiro['resumo_faturas']['valor_pago'] / max($relatorio_financeiro['resumo_faturas']['valor_faturado'], 1)) * 100, 1) ?>% do faturado
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-lg p-4 border-l-4 border-orange-500">
                            <p class="text-xs text-orange-600 dark:text-orange-400 font-semibold uppercase mb-1">Total Despesas</p>
                            <p class="text-2xl font-bold text-orange-800 dark:text-orange-200">
                                <?= number_format($relatorio_financeiro['total_despesas'], 2) ?> MT
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                <?= count($relatorio_financeiro['despesas_categoria']) ?> categorias
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-50 to-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-100 dark:from-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-900/20 dark:to-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-800/20 rounded-lg p-4 border-l-4 border-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-500">
                            <p class="text-xs text-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-600 dark:text-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-400 font-semibold uppercase mb-1">Saldo do Período</p>
                            <p class="text-2xl font-bold text-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-800 dark:text-<?= $relatorio_financeiro['saldo_periodo'] >= 0 ? 'green' : 'red' ?>-200">
                                <?= number_format($relatorio_financeiro['saldo_periodo'], 2) ?> MT
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Receitas - Despesas</p>
                        </div>
                    </div>
                </div>

                <!-- Receitas por Categoria -->
                <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                        <i class="bi bi-pie-chart mr-2"></i>Receitas por Categoria
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-gray-100 to-slate-100 dark:from-gray-800 dark:to-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Nº Faturas</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Qtd. Serviços</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Total Receita</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">% Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php 
                                $total_receita = array_sum(array_column($relatorio_financeiro['receitas_categoria'], 'total_receita'));
                                foreach ($relatorio_financeiro['receitas_categoria'] as $cat): 
                                    $percentual = ($cat['total_receita'] / max($total_receita, 1)) * 100;
                                ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars(ucfirst($cat['categoria'])) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">
                                            <?= $cat['num_faturas'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">
                                            <?= $cat['quantidade_servicos'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800 dark:text-gray-200">
                                            <?= number_format($cat['total_receita'], 2) ?> MT
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center">
                                                <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                                    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 h-2 rounded-full" style="width: <?= $percentual ?>%"></div>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">
                                                    <?= number_format($percentual, 1) ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-800 dark:text-gray-200 text-right">TOTAL</td>
                                    <td class="px-4 py-3 text-sm font-bold text-right text-emerald-600 dark:text-emerald-400">
                                        <?= number_format($total_receita, 2) ?> MT
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-center text-gray-600 dark:text-gray-400">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Pagamentos por Método -->
                    <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                            <i class="bi bi-credit-card mr-2"></i>Pagamentos por Método
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($relatorio_financeiro['pagamentos_metodo'] as $pag): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars(ucfirst($pag['metodo'])) ?>
                                        </p>
                                        <p class="text-xs text-gray-500"><?= $pag['num_pagamentos'] ?> transações</p>
                                    </div>
                                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                        <?= number_format($pag['total_recebido'], 2) ?> MT
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Despesas por Categoria -->
                    <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                            <i class="bi bi-wallet2 mr-2"></i>Despesas por Categoria
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($relatorio_financeiro['despesas_categoria'] as $desp): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars(ucfirst($desp['categoria'])) ?>
                                        </p>
                                        <p class="text-xs text-gray-500"><?= $desp['num_despesas'] ?> despesas</p>
                                    </div>
                                    <p class="text-lg font-bold text-orange-600 dark:text-orange-400">
                                        <?= number_format($desp['total_gasto'], 2) ?> MT
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($tipo_relatorio === 'consultas' && $relatorio_consultas): ?>
            <!-- RELATÓRIO DE CONSULTAS -->
            <div class="space-y-6">
                <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="bi bi-clipboard2-pulse mr-3 text-blue-600"></i>
                        Relatório de Consultas
                        <span class="ml-auto text-sm font-normal text-gray-500">
                            <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?>
                        </span>
                    </h2>

                    <!-- Status de Consultas -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <?php foreach ($relatorio_consultas['consultas_status'] as $status): ?>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                                <p class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?= $status['quantidade'] ?></p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 uppercase"><?= htmlspecialchars($status['status']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Atendimentos por Profissional -->
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-3">
                        <i class="bi bi-person-badge mr-2"></i>Atendimentos por Profissional
                    </h3>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-blue-100 to-cyan-100 dark:from-blue-900 dark:to-cyan-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Profissional</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Especialidade</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Atendimentos</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Pacientes Únicos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($relatorio_consultas['atendimentos_profissional'] as $prof): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars($prof['profissional']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            <?= htmlspecialchars($prof['especialidade']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-semibold text-blue-600 dark:text-blue-400">
                                            <?= $prof['total_atendimentos'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">
                                            <?= $prof['pacientes_unicos'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Atendimentos por Especialidade -->
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-3">
                        <i class="bi bi-hospital mr-2"></i>Atendimentos por Especialidade
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-teal-100 to-emerald-100 dark:from-teal-900 dark:to-emerald-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Especialidade</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Profissional</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Atendimentos</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Pacientes Únicos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($relatorio_consultas['atendimentos_especialidade'] as $esp): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars($esp['especialidade']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            <?= htmlspecialchars($esp['profissional']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-semibold text-teal-600 dark:text-teal-400">
                                            <?= $esp['total_atendimentos'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">
                                            <?= $esp['pacientes_unicos'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($tipo_relatorio === 'farmacia' && $relatorio_farmacia): ?>
            <!-- RELATÓRIO DE FARMÁCIA -->
            <div class="space-y-6">
                <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="bi bi-capsule-pill mr-3 text-green-600"></i>
                        Relatório de Farmácia
                        <span class="ml-auto text-sm font-normal text-gray-500">
                            <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?>
                        </span>
                    </h2>

                    <!-- Valor Total Faturado -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg p-6 mb-6 border-l-4 border-green-500">
                        <p class="text-sm text-green-600 dark:text-green-400 font-semibold uppercase mb-2">Valor Total de Medicamentos Faturados</p>
                        <p class="text-3xl font-bold text-green-800 dark:text-green-200">
                            <?= number_format($relatorio_farmacia['valor_medicamentos'], 2) ?> MT
                        </p>
                    </div>

                    <!-- Medicamentos Mais Dispensados -->
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-3">
                        <i class="bi bi-graph-up-arrow mr-2"></i>Top 20 Medicamentos Dispensados
                    </h3>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Medicamento</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Vezes Prescrito</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Qtd. Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Stock Atual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($relatorio_farmacia['medicamentos_dispensados'] as $idx => $med): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 <?= $med['stock_atual'] < 10 ? 'bg-red-50 dark:bg-red-900/10' : '' ?>">
                                        <td class="px-4 py-3 text-sm font-bold text-gray-600 dark:text-gray-400"><?= $idx + 1 ?></td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars($med['medicamento']) ?>
                                            <?php if ($med['stock_atual'] < 10): ?>
                                                <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded-full">Stock Baixo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">
                                            <?= $med['vezes_prescrito'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-semibold text-green-600 dark:text-green-400">
                                            <?= $med['quantidade_total'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-semibold <?= $med['stock_atual'] < 10 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' ?>">
                                            <?= $med['stock_atual'] ?? 0 ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Stock Crítico -->
                    <?php if (count($relatorio_farmacia['stock_critico']) > 0): ?>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border-l-4 border-red-500">
                            <h3 class="text-lg font-bold text-red-800 dark:text-red-300 mb-3">
                                <i class="bi bi-exclamation-triangle-fill mr-2"></i>
                                Alerta: Medicamentos com Stock Crítico (&lt; 10 unidades)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($relatorio_farmacia['stock_critico'] as $crit): ?>
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-red-200 dark:border-red-800">
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars($crit['medicamento']) ?>
                                        </p>
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-xs text-gray-500"><?= htmlspecialchars($crit['categoria']) ?></span>
                                            <span class="text-lg font-bold text-red-600 dark:text-red-400">
                                                <?= $crit['quantidade'] ?> un.
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tipo_relatorio === 'laboratorio' && $relatorio_laboratorio): ?>
            <!-- RELATÓRIO DE LABORATÓRIO -->
            <div class="space-y-6">
                <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="bi bi-droplet mr-3 text-purple-600"></i>
                        Relatório de Laboratório
                        <span class="ml-auto text-sm font-normal text-gray-500">
                            <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?>
                        </span>
                    </h2>

                    <!-- Tempo Médio -->
                    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg p-6 mb-6 border-l-4 border-purple-500">
                        <p class="text-sm text-purple-600 dark:text-purple-400 font-semibold uppercase mb-2">Tempo Médio de Liberação</p>
                        <p class="text-3xl font-bold text-purple-800 dark:text-purple-200">
                            <?= $relatorio_laboratorio['tempo_medio_horas'] ?> horas
                        </p>
                    </div>

                    <!-- Exames Mais Solicitados -->
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-3">
                        <i class="bi bi-clipboard-data mr-2"></i>Exames Mais Solicitados
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-purple-100 to-indigo-100 dark:from-purple-900 dark:to-indigo-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Exame</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Solicitados</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Liberados</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Pendentes</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">% Liberado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($relatorio_laboratorio['exames_solicitados'] as $exame): 
                                    $perc_lib = ($exame['quantidade_liberada'] / max($exame['quantidade_solicitada'], 1)) * 100;
                                ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars($exame['exame']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-semibold text-purple-600 dark:text-purple-400">
                                            <?= $exame['quantidade_solicitada'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-green-600 dark:text-green-400">
                                            <?= $exame['quantidade_liberada'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-orange-600 dark:text-orange-400">
                                            <?= $exame['quantidade_pendente'] ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center">
                                                <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 h-2 rounded-full" style="width: <?= $perc_lib ?>%"></div>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">
                                                    <?= number_format($perc_lib, 1) ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($tipo_relatorio === 'mental' && $relatorio_mental): ?>
            <!-- RELATÓRIO DE PSICOLOGIA/PSIQUIATRIA -->
            <div class="space-y-6">
                <div class="glass-effect rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="bi bi-brain mr-3 text-pink-600"></i>
                        Relatório de Saúde Mental
                        <span class="ml-auto text-sm font-normal text-gray-500">
                            <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?>
                        </span>
                    </h2>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Psicologia -->
                        <div class="bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 rounded-lg p-6 border-l-4 border-yellow-500">
                            <h3 class="text-xl font-bold text-yellow-800 dark:text-yellow-300 mb-4">
                                <i class="bi bi-person-heart mr-2"></i>Psicologia
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 uppercase mb-1">Total de Sessões</p>
                                    <p class="text-3xl font-bold text-yellow-800 dark:text-yellow-200">
                                        <?= $relatorio_mental['psicologia']['total_sessoes'] ?? 0 ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 uppercase mb-1">Pacientes Únicos</p>
                                    <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">
                                        <?= $relatorio_mental['psicologia']['pacientes_unicos'] ?? 0 ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 uppercase mb-1">Taxa de Retorno</p>
                                    <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">
                                        <?= number_format($relatorio_mental['psicologia']['taxa_retorno'] ?? 0, 1) ?>%
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Psiquiatria -->
                        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-6 border-l-4 border-indigo-500">
                            <h3 class="text-xl font-bold text-indigo-800 dark:text-indigo-300 mb-4">
                                <i class="bi bi-person-check mr-2"></i>Psiquiatria
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 uppercase mb-1">Total de Consultas</p>
                                    <p class="text-3xl font-bold text-indigo-800 dark:text-indigo-200">
                                        <?= $relatorio_mental['psiquiatria']['total_consultas'] ?? 0 ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 uppercase mb-1">Pacientes Únicos</p>
                                    <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">
                                        <?= $relatorio_mental['psiquiatria']['pacientes_unicos'] ?? 0 ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 uppercase mb-1">Consultas com Prescrição</p>
                                    <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">
                                        <?= $relatorio_mental['psiquiatria']['consultas_com_prescricao'] ?? 0 ?>
                                        <span class="text-sm font-normal">
                                            (<?= number_format(($relatorio_mental['psiquiatria']['consultas_com_prescricao'] / max($relatorio_mental['psiquiatria']['total_consultas'], 1)) * 100, 1) ?>%)
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Dark mode toggle
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>
