<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

$med_id = isset($_GET['med_id']) ? (int)$_GET['med_id'] : 0;
if (!$med_id) die('Medicamento não especificado.');

// Buscar medicamento
$med = $mysqli->query("SELECT id, name, estoque FROM medications WHERE id = $med_id")->fetch_assoc();
if (!$med) die('Medicamento não encontrado.');

$erro = '';
$sucesso = '';
// Ajuste manual (entrada/saida manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'ajuste') {
    $tipo = $_POST['tipo'] ?? '';
    $qtd = (int)($_POST['qtd'] ?? 0);
    $obs = $mysqli->real_escape_string(trim($_POST['obs'] ?? ''));
    if ($qtd <= 0) {
        $erro = 'Quantidade inválida.';
    } else {
        if ($tipo === 'entrada') {
            $mysqli->query("UPDATE medications SET estoque = estoque + $qtd WHERE id = $med_id");
            // log entrada se tabela existir
            $db = $mysqli->real_escape_string($mysqli->query("SELECT DATABASE()")->fetch_row()[0]);
            $tbl_check = $mysqli->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '".$db."' AND table_name = 'entrada_medicamentos'");
            if ($tbl_check && $tbl_check->num_rows) {
                $mysqli->query("INSERT INTO entrada_medicamentos (medication_id, quantidade, entrada_em, observacao) VALUES ($med_id, $qtd, NOW(), '$obs')");
            }
            $sucesso = 'Entrada registrada.';
        } elseif ($tipo === 'saida') {
            // somente se houver stock suficiente
            $cur = (int)$mysqli->query("SELECT estoque FROM medications WHERE id = $med_id")->fetch_assoc()['estoque'];
            if ($cur < $qtd) {
                $erro = 'Estoque insuficiente (atual: ' . $cur . ').';
            } else {
                $mysqli->query("UPDATE medications SET estoque = estoque - $qtd WHERE id = $med_id");
                $db = $mysqli->real_escape_string($mysqli->query("SELECT DATABASE()")->fetch_row()[0]);
                $tbl_check = $mysqli->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '".$db."' AND table_name = 'saida_medicamentos'");
                if ($tbl_check && $tbl_check->num_rows) {
                    $mysqli->query("INSERT INTO saida_medicamentos (medication_id, quantidade, saida_em, observacao) VALUES ($med_id, $qtd, NOW(), '$obs')");
                }
                $sucesso = 'Saída registrada.';
            }
        }
        // refresh med
        $med = $mysqli->query("SELECT id, name, estoque FROM medications WHERE id = $med_id")->fetch_assoc();
    }
}

// Buscar histórico
$db = $mysqli->real_escape_string($mysqli->query("SELECT DATABASE()")->fetch_row()[0]);
$has_entrada = $mysqli->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'entrada_medicamentos'")->num_rows;
$has_saida = $mysqli->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'saida_medicamentos'")->num_rows;
$entradas = [];
$saidas = [];
if ($has_entrada) {
    $res = $mysqli->query("SELECT quantidade, entrada_em, COALESCE(observacao, '') AS obs FROM entrada_medicamentos WHERE medication_id = $med_id ORDER BY entrada_em DESC LIMIT 100");
    while ($r = $res->fetch_assoc()) $entradas[] = $r;
}
if ($has_saida) {
    $res = $mysqli->query("SELECT quantidade, saida_em, COALESCE(observacao, '') AS obs FROM saida_medicamentos WHERE medication_id = $med_id ORDER BY saida_em DESC LIMIT 100");
    while ($r = $res->fetch_assoc()) $saidas[] = $r;
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Estoque — <?= htmlspecialchars($med['name']) ?> | Integrada Mais Saúde</title>
    
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
        
        .animate-fadeIn { animation: fadeIn 0.6s ease-out; }
        .animate-slideUp { animation: slideUp 0.8s ease-out; }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .dark .glass-effect {
            background: rgba(31, 41, 55, 0.9);
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
<body class="bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass-effect border-b border-emerald-200 dark:border-gray-700 sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="bi bi-heart-pulse-fill text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800 dark:text-white">Integrada Mais Saúde</h1>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Gestão de Estoque</p>
                        </div>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-300">
                        <i class="bi bi-moon-stars-fill dark:bi-sun-fill text-gray-700 dark:text-yellow-400 text-xl"></i>
                    </button>
                    <a href="farmacia.php" class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="bi bi-arrow-left mr-2"></i>Voltar
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
        
        <!-- Header do Medicamento -->
        <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 p-6 mb-6 animate-fadeIn">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-2">
                        <i class="bi bi-capsule-pill text-emerald-600 mr-3"></i>
                        <?= htmlspecialchars($med['name']) ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">Controle de estoque e movimentações</p>
                </div>
                <div class="text-right">
                    <span class="text-sm text-gray-500 dark:text-gray-400 block mb-1">ID do Medicamento</span>
                    <span class="text-2xl font-mono font-bold text-emerald-600">#<?= $med['id'] ?></span>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Card de Estoque Atual -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 p-8 text-center hover-lift animate-slideUp">
                    <i class="bi bi-box-seam text-6xl text-emerald-600 mb-4"></i>
                    <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wider">Estoque Atual</h3>
                    <div class="text-5xl font-bold text-gray-800 dark:text-white mb-2">
                        <?= (int)$med['estoque'] ?>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">unidades disponíveis</p>
                    
                    <?php 
                    $estoque = (int)$med['estoque'];
                    $status_color = $estoque > 50 ? 'emerald' : ($estoque > 20 ? 'amber' : 'red');
                    $status_text = $estoque > 50 ? 'Estoque OK' : ($estoque > 20 ? 'Estoque Baixo' : 'Crítico');
                    ?>
                    
                    <div class="mt-4">
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-bold bg-<?= $status_color ?>-100 dark:bg-<?= $status_color ?>-900 text-<?= $status_color ?>-800 dark:text-<?= $status_color ?>-200">
                            <i class="bi bi-circle-fill mr-2 text-<?= $status_color ?>-600"></i>
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Formulário de Ajuste -->
            <div class="lg:col-span-2">
                <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 p-6 animate-slideUp">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="bi bi-arrow-left-right text-emerald-600 mr-3"></i>
                        Registrar Movimentação
                    </h3>
                    
                    <!-- Alertas -->
                    <?php if ($erro): ?>
                        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl mr-3"></i>
                                <p class="text-red-800 dark:text-red-200 font-medium"><?= htmlspecialchars($erro) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($sucesso): ?>
                        <div class="mb-4 bg-emerald-50 dark:bg-emerald-900/30 border-l-4 border-emerald-500 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="bi bi-check-circle-fill text-emerald-500 text-xl mr-3"></i>
                                <p class="text-emerald-800 dark:text-emerald-200 font-medium"><?= htmlspecialchars($sucesso) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="acao" value="ajuste">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="bi bi-arrow-repeat mr-1"></i>
                                    Tipo de Movimentação
                                </label>
                                <select name="tipo" required 
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all duration-300">
                                    <option value="entrada">📥 Entrada (Compra/Recebimento)</option>
                                    <option value="saida">📤 Saída (Venda/Consumo)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="bi bi-123 mr-1"></i>
                                    Quantidade
                                </label>
                                <input type="number" name="qtd" min="1" value="1" required 
                                       class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all duration-300">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="bi bi-chat-left-text mr-1"></i>
                                    Observação
                                </label>
                                <input type="text" name="obs" placeholder="Opcional" 
                                       class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all duration-300">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-8 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                <i class="bi bi-check-circle mr-2"></i>
                                Registrar Movimentação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Histórico de Movimentações -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            
            <!-- Últimas Entradas -->
            <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-4">
                    <h3 class="text-lg font-bold text-white flex items-center">
                        <i class="bi bi-arrow-down-circle mr-3 text-xl"></i>
                        Últimas Entradas
                    </h3>
                </div>
                <div class="p-6">
                    <?php if ($has_entrada && count($entradas) > 0): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($entradas as $e): ?>
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4 border-l-4 border-emerald-500">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-2xl font-bold text-emerald-600">+<?= (int)$e['quantidade'] ?></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">
                                        <i class="bi bi-calendar3 mr-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($e['entrada_em'])) ?>
                                    </span>
                                </div>
                                <?php if ($e['obs']): ?>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <i class="bi bi-chat-left-text mr-1"></i>
                                    <?= htmlspecialchars($e['obs']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="bi bi-inbox text-5xl text-gray-400 mb-3"></i>
                            <p class="text-gray-500 dark:text-gray-400">Nenhuma entrada registrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Últimas Saídas -->
            <div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 py-4">
                    <h3 class="text-lg font-bold text-white flex items-center">
                        <i class="bi bi-arrow-up-circle mr-3 text-xl"></i>
                        Últimas Saídas
                    </h3>
                </div>
                <div class="p-6">
                    <?php if ($has_saida && count($saidas) > 0): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($saidas as $s): ?>
                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border-l-4 border-red-500">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-2xl font-bold text-red-600">-<?= (int)$s['quantidade'] ?></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">
                                        <i class="bi bi-calendar3 mr-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($s['saida_em'])) ?>
                                    </span>
                                </div>
                                <?php if ($s['obs']): ?>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <i class="bi bi-chat-left-text mr-1"></i>
                                    <?= htmlspecialchars($s['obs']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="bi bi-inbox text-5xl text-gray-400 mb-3"></i>
                            <p class="text-gray-500 dark:text-gray-400">Nenhuma saída registrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
