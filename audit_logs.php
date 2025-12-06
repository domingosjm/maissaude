<?php
/**
 * LOG DE AUDITORIA
 * Visualização do histórico de ações no sistema
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

// Verificar autenticação e permissões de admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_query = $mysqli->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = $user_id");
$user = $user_query->fetch_assoc();

if (!$user || !in_array($user['role_name'], ['admin', 'Administrador'])) {
    header('Location: dashboard.php');
    exit;
}

// Criar tabela de auditoria se não existir
$mysqli->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    table_name VARCHAR(100),
    record_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(created_at),
    INDEX(table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Verificar se auditoria está habilitada
$audit_enabled = get_system_config('enable_audit_log', '1');

// Paginação
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = intval(get_system_config('records_per_page', '25'));
$offset = ($page - 1) * $records_per_page;

// Filtros
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filter_action = isset($_GET['action']) ? $mysqli->real_escape_string($_GET['action']) : '';
$filter_date = isset($_GET['date']) ? $mysqli->real_escape_string($_GET['date']) : '';

// Construir query
$where_clauses = [];
if ($filter_user > 0) {
    $where_clauses[] = "al.user_id = $filter_user";
}
if ($filter_action) {
    $where_clauses[] = "al.action = '$filter_action'";
}
if ($filter_date) {
    $where_clauses[] = "DATE(al.created_at) = '$filter_date'";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Buscar logs
$logs_query = $mysqli->query("
    SELECT al.*, u.name as user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_sql
    ORDER BY al.created_at DESC
    LIMIT $offset, $records_per_page
");

// Contar total
$total_query = $mysqli->query("SELECT COUNT(*) as total FROM audit_logs al $where_sql");
$total_result = $total_query ? $total_query->fetch_assoc() : ['total' => 0];
$total_records = $total_result['total'];
$total_pages = ceil($total_records / $records_per_page);

// Buscar usuários para filtro
$users = $mysqli->query("SELECT id, name FROM users ORDER BY name");

// Buscar ações únicas
$actions = $mysqli->query("SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL AND action != '' ORDER BY action");

$page_title = 'Log de Auditoria';
$module_name = 'Administração';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="bi bi-file-earmark-text-fill text-purple-600 mr-3"></i>
                Log de Auditoria
            </h1>
            <p class="text-gray-600 mt-2">Histórico completo de ações no sistema</p>
        </div>
        <a href="configuracoes.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center">
            <i class="bi bi-arrow-left mr-2"></i> Voltar
        </a>
    </div>

    <?php if ($audit_enabled != '1'): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-lg">
        <div class="flex items-center">
            <i class="bi bi-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
            <p class="text-yellow-800">Log de auditoria está desabilitado. Ative em <a href="configuracoes.php" class="underline font-semibold">Configurações → Sistema</a>.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                <select name="user_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="0">Todos</option>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ação</label>
                <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">Todas</option>
                    <?php while($a = $actions->fetch_assoc()): ?>
                        <option value="<?= $a['action'] ?>" <?= $filter_action == $a['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['action']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg shadow-md transition">
                    <i class="bi bi-funnel mr-2"></i>Filtrar
                </button>
                <a href="audit_logs.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela de Logs -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data/Hora</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($logs_query && $logs_query->num_rows > 0): ?>
                        <?php while($log = $logs_query->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    <?php 
                                    if (strpos($log['action'], 'CREATE') !== false) echo 'bg-green-100 text-green-800';
                                    elseif (strpos($log['action'], 'UPDATE') !== false) echo 'bg-blue-100 text-blue-800';
                                    elseif (strpos($log['action'], 'DELETE') !== false) echo 'bg-red-100 text-red-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 max-w-md truncate">
                                <?= htmlspecialchars($log['description']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="bi bi-inbox text-4xl mb-2"></i>
                                <p>Nenhum registro encontrado</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
            <div class="text-sm text-gray-700">
                Mostrando <?= $offset + 1 ?> a <?= min($offset + $records_per_page, $total_records) ?> de <?= $total_records ?> registros
            </div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $filter_user ? "&user_id=$filter_user" : '' ?><?= $filter_action ? "&action=$filter_action" : '' ?><?= $filter_date ? "&date=$filter_date" : '' ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_user ? "&user_id=$filter_user" : '' ?><?= $filter_action ? "&action=$filter_action" : '' ?><?= $filter_date ? "&date=$filter_date" : '' ?>" 
                       class="px-4 py-2 rounded-lg transition <?= $i == $page ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $filter_user ? "&user_id=$filter_user" : '' ?><?= $filter_action ? "&action=$filter_action" : '' ?><?= $filter_date ? "&date=$filter_date" : '' ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
