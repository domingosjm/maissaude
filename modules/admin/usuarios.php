<?php
require __DIR__ . '/../../config.php';
require __DIR__ . '/../../functions.php';
require_login();

$conn = $mysqli;
$user_name = $_SESSION['user_name'] ?? 'Usuário';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role_id = $_POST['role_id'] ?? '';
        $department = $_POST['department'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'ativo';
        
        // Validações
        if (empty($name) || empty($email) || empty($password) || empty($role_id)) {
            echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos!']);
            exit;
        }
        
        // Verificar se email já existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email já cadastrado no sistema!']);
            exit;
        }
        
        // Hash da senha
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Inserir em users
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('sss', $name, $email, $password_hash);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Inserir em system_users
            $username = strtolower(str_replace(' ', '.', $name));
            $password_md5 = md5($password);
            
            $stmt2 = $conn->prepare("INSERT INTO system_users (username, password, role_id, full_name, email, department, phone, status, created_at) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt2->bind_param('ssisssss', $username, $password_md5, $role_id, $name, $email, $department, $phone, $status);
            $stmt2->execute();
            
            $system_user_id = $conn->insert_id;
            
            // Atualizar users com system_user_id e role_id
            $stmt3 = $conn->prepare("UPDATE users SET system_user_id = ?, role_id = ? WHERE id = ?");
            $stmt3->bind_param('iii', $system_user_id, $role_id, $user_id);
            $stmt3->execute();
            
            echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar usuário: ' . $conn->error]);
        }
        exit;
    }
    
    if ($action === 'update_user') {
        $user_id = $_POST['user_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role_id = $_POST['role_id'] ?? '';
        $department = $_POST['department'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($user_id) || empty($name) || empty($email) || empty($role_id)) {
            echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos!']);
            exit;
        }
        
        // Atualizar users
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ? WHERE id = ?");
        $stmt->bind_param('ssii', $name, $email, $role_id, $user_id);
        
        if ($stmt->execute()) {
            // Atualizar system_users
            $stmt2 = $conn->prepare("UPDATE system_users SET full_name = ?, email = ?, role_id = ?, department = ?, phone = ?, status = ? 
                                     WHERE email = ? OR id IN (SELECT system_user_id FROM users WHERE id = ?)");
            $stmt2->bind_param('ssissssi', $name, $email, $role_id, $department, $phone, $status, $email, $user_id);
            $stmt2->execute();
            
            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar usuário: ' . $conn->error]);
        }
        exit;
    }
    
    if ($action === 'reset_password') {
        $user_id = $_POST['user_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($user_id) || empty($new_password)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos!']);
            exit;
        }
        
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $password_md5 = md5($new_password);
        
        // Atualizar senha em users
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Atualizar senha em system_users
            $stmt2 = $conn->prepare("UPDATE system_users SET password = ? WHERE id IN (SELECT system_user_id FROM users WHERE id = ?)");
            $stmt2->bind_param('si', $password_md5, $user_id);
            $stmt2->execute();
            
            echo json_encode(['success' => true, 'message' => 'Senha resetada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao resetar senha: ' . $conn->error]);
        }
        exit;
    }
    
    if ($action === 'toggle_status') {
        $user_id = $_POST['user_id'] ?? '';
        $status = $_POST['status'] ?? 'inativo';
        
        if (empty($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido!']);
            exit;
        }
        
        // Atualizar status em system_users
        $stmt = $conn->prepare("UPDATE system_users SET status = ? WHERE id IN (SELECT system_user_id FROM users WHERE id = ?)");
        $stmt->bind_param('si', $status, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status: ' . $conn->error]);
        }
        exit;
    }
    
    if ($action === 'delete_user') {
        $user_id = $_POST['user_id'] ?? '';
        
        if (empty($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido!']);
            exit;
        }
        
        // Verificar se não é o próprio usuário logado
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Você não pode deletar sua própria conta!']);
            exit;
        }
        
        // Deletar de system_users primeiro
        $stmt = $conn->prepare("DELETE FROM system_users WHERE id IN (SELECT system_user_id FROM users WHERE id = ?)");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Deletar de users
        $stmt2 = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt2->bind_param('i', $user_id);
        
        if ($stmt2->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuário deletado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar usuário: ' . $conn->error]);
        }
        exit;
    }
}

// Buscar usuários
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT u.id, u.name, u.email, u.created_at, 
                 r.name as role_name, r.id as role_id,
                 su.department, su.phone, su.status, su.last_login
          FROM users u
          LEFT JOIN user_roles r ON u.role_id = r.id
          LEFT JOIN system_users su ON u.system_user_id = su.id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR su.department LIKE '%$search%')";
}

if (!empty($role_filter)) {
    $query .= " AND u.role_id = " . intval($role_filter);
}

if (!empty($status_filter)) {
    $query .= " AND su.status = '$status_filter'";
}

$query .= " ORDER BY u.created_at DESC";

$result = $conn->query($query);
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Buscar roles disponíveis
$roles_result = $conn->query("SELECT id, name as role_name, description FROM user_roles ORDER BY name");
$roles = [];
while ($row = $roles_result->fetch_assoc()) {
    $roles[] = $row;
}

// Estatísticas
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'active' => $conn->query("SELECT COUNT(*) as count FROM system_users WHERE status = 'ativo'")->fetch_assoc()['count'],
    'inactive' => $conn->query("SELECT COUNT(*) as count FROM system_users WHERE status = 'inativo'")->fetch_assoc()['count'],
    'online' => $conn->query("SELECT COUNT(*) as count FROM system_users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetch_assoc()['count']
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários | Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.9); }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gradient-to-br from-violet-50 via-purple-50 to-fuchsia-50 min-h-screen">
    <!-- Navbar -->
    <nav class="glass-effect shadow-xl border-b border-purple-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3">
                        <div class="bg-gradient-to-br from-violet-500 to-fuchsia-600 p-2 rounded-xl shadow-lg">
                            <i class="bi bi-person-badge-fill text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold bg-gradient-to-r from-violet-600 to-fuchsia-600 bg-clip-text text-transparent">
                                Gestão de Usuários
                            </h1>
                            <p class="text-xs text-gray-500">Controle de acessos e permissões</p>
                        </div>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Usuário</p>
                        <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                    </div>
                    <a href="dashboard.php" class="px-4 py-2 bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white rounded-lg hover:from-violet-600 hover:to-fuchsia-700 transition-all shadow-lg">
                        <i class="bi bi-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-blue-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Total de Usuários</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-people-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-green-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Usuários Ativos</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?= $stats['active'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-check-circle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-red-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Usuários Inativos</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?= $stats['inactive'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-x-circle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Online Agora</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-2"><?= $stats['online'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-wifi text-white text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros e Ações -->
        <div class="glass-effect rounded-2xl shadow-xl border-2 border-purple-200 p-6 mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex flex-wrap gap-4 flex-1">
                    <div class="flex-1 min-w-[250px]">
                        <input type="text" id="searchInput" placeholder="🔍 Pesquisar por nome, email ou departamento..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                    </div>
                    
                    <select id="roleFilter" class="px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                        <option value="">Todos os Cargos</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $role_filter == $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="statusFilter" class="px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                        <option value="">Todos os Status</option>
                        <option value="ativo" <?= $status_filter === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inativo" <?= $status_filter === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                    
                    <button onclick="applyFilters()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all shadow-lg font-semibold">
                        <i class="bi bi-funnel mr-2"></i>Filtrar
                    </button>
                </div>
                
                <button onclick="openCreateModal()" class="px-6 py-3 bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white rounded-xl hover:from-violet-600 hover:to-fuchsia-700 transition-all shadow-lg font-semibold">
                    <i class="bi bi-plus-circle mr-2"></i>Novo Usuário
                </button>
            </div>
        </div>

        <!-- Tabela de Usuários -->
        <div class="glass-effect rounded-2xl shadow-xl border-2 border-purple-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white">
                        <tr>
                            <th class="px-6 py-4 text-left font-bold">Usuário</th>
                            <th class="px-6 py-4 text-left font-bold">Cargo</th>
                            <th class="px-6 py-4 text-left font-bold">Departamento</th>
                            <th class="px-6 py-4 text-left font-bold">Contato</th>
                            <th class="px-6 py-4 text-center font-bold">Status</th>
                            <th class="px-6 py-4 text-center font-bold">Último Acesso</th>
                            <th class="px-6 py-4 text-center font-bold">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-purple-200">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-fuchsia-600 rounded-full flex items-center justify-center shadow-lg">
                                        <span class="text-white font-bold text-sm">
                                            <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-lg text-sm font-semibold">
                                    <?= htmlspecialchars($user['role_name'] ?? 'Sem cargo') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-700"><?= htmlspecialchars($user['department'] ?? '-') ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-700"><?= htmlspecialchars($user['phone'] ?? '-') ?></p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($user['status'] === 'ativo'): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold">
                                        <i class="bi bi-check-circle-fill mr-1"></i>Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">
                                        <i class="bi bi-x-circle-fill mr-1"></i>Inativo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <p class="text-sm text-gray-600">
                                    <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca' ?>
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick='editUser(<?= json_encode($user) ?>)' 
                                            class="p-2 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-lg transition-all" title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button onclick="resetPassword(<?= $user['id'] ?>)" 
                                            class="p-2 bg-yellow-100 hover:bg-yellow-200 text-yellow-600 rounded-lg transition-all" title="Resetar Senha">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
                                    <button onclick="toggleStatus(<?= $user['id'] ?>, '<?= $user['status'] === 'ativo' ? 'inativo' : 'ativo' ?>')" 
                                            class="p-2 bg-orange-100 hover:bg-orange-200 text-orange-600 rounded-lg transition-all" title="Alterar Status">
                                        <i class="bi bi-toggle-<?= $user['status'] === 'ativo' ? 'on' : 'off' ?>"></i>
                                    </button>
                                    <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" 
                                            class="p-2 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg transition-all" title="Deletar">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="bi bi-inbox text-6xl text-gray-300 mb-4 block"></i>
                                <p class="text-lg font-semibold">Nenhum usuário encontrado</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Criar/Editar Usuário -->
    <div id="userModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="glass-effect rounded-2xl shadow-2xl border-2 border-purple-200 p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-800">Novo Usuário</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <form id="userForm" class="space-y-4">
                <input type="hidden" id="userId" name="user_id">
                <input type="hidden" id="formAction" name="action" value="create_user">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-person mr-2 text-purple-600"></i>Nome Completo *
                        </label>
                        <input type="text" id="userName" name="name" required
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-envelope mr-2 text-purple-600"></i>Email *
                        </label>
                        <input type="email" id="userEmail" name="email" required
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                    </div>
                </div>
                
                <div id="passwordField">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="bi bi-key mr-2 text-purple-600"></i>Senha *
                    </label>
                    <input type="password" id="userPassword" name="password"
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-briefcase mr-2 text-purple-600"></i>Cargo *
                        </label>
                        <select id="userRole" name="role_id" required
                                class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                            <option value="">Selecione...</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-building mr-2 text-purple-600"></i>Departamento
                        </label>
                        <input type="text" id="userDepartment" name="department"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-telephone mr-2 text-purple-600"></i>Telefone
                        </label>
                        <input type="text" id="userPhone" name="phone"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-toggle-on mr-2 text-purple-600"></i>Status
                        </label>
                        <select id="userStatus" name="status"
                                class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl transition-all font-semibold">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white rounded-xl hover:from-violet-600 hover:to-fuchsia-700 transition-all shadow-lg font-semibold">
                        <i class="bi bi-save mr-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (role) params.append('role', role);
            if (status) params.append('status', status);
            
            window.location.href = 'usuarios.php?' + params.toString();
        }
        
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Novo Usuário';
            document.getElementById('formAction').value = 'create_user';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('userPassword').required = true;
            document.getElementById('userModal').classList.add('active');
        }
        
        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'Editar Usuário';
            document.getElementById('formAction').value = 'update_user';
            document.getElementById('userId').value = user.id;
            document.getElementById('userName').value = user.name;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role_id;
            document.getElementById('userDepartment').value = user.department || '';
            document.getElementById('userPhone').value = user.phone || '';
            document.getElementById('userStatus').value = user.status || 'ativo';
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('userPassword').required = false;
            document.getElementById('userModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.remove('active');
        }
        
        async function resetPassword(userId) {
            const newPassword = prompt('Digite a nova senha:');
            if (!newPassword) return;
            
            if (newPassword.length < 6) {
                alert('❌ A senha deve ter no mínimo 6 caracteres!');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'reset_password');
                formData.append('user_id', userId);
                formData.append('new_password', newPassword);
                
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro ao resetar senha: ' + error.message);
            }
        }
        
        async function toggleStatus(userId, newStatus) {
            if (!confirm(`Deseja realmente ${newStatus === 'ativo' ? 'ativar' : 'desativar'} este usuário?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('user_id', userId);
                formData.append('status', newStatus);
                
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro ao alterar status: ' + error.message);
            }
        }
        
        async function deleteUser(userId, userName) {
            if (!confirm(`⚠️ ATENÇÃO!\n\nDeseja realmente deletar o usuário "${userName}"?\n\nEsta ação não pode ser desfeita!`)) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);
                
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro ao deletar usuário: ' + error.message);
            }
        }
        
        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            try {
                const formData = new FormData(e.target);
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro ao salvar: ' + error.message);
            }
        });
        
        // Fechar modal ao clicar fora
        document.getElementById('userModal').addEventListener('click', (e) => {
            if (e.target.id === 'userModal') {
                closeModal();
            }
        });
        
        // Aplicar filtros ao pressionar Enter
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    </script>
</body>
</html>
