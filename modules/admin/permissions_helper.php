<?php
/**
 * Sistema de Permissões - MSLDA
 * Helper para verificar permissões de usuários
 */

// Carregar permissões do usuário na sessão (por user_id da tabela users)
function loadUserPermissions($user_id) {
    global $mysqli;
    
    // Primeiro, buscar dados do usuário
    $user_query = "SELECT u.id, u.name, u.email, u.role_id,
                          su.username, su.full_name, su.department
                   FROM users u
                   LEFT JOIN system_users su ON u.system_user_id = su.id
                   WHERE u.id = ?";
    
    $stmt = $mysqli->prepare($user_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_row = $user_result->fetch_assoc();
    
    if (!$user_row) {
        return null;
    }
    
    // Buscar nome do role
    $role_id = $user_row['role_id'];
    $role_name = 'Usuário';
    
    if ($role_id) {
        $role_query = "SELECT r.name FROM user_roles r WHERE r.id = ?";
        $role_stmt = $mysqli->prepare($role_query);
        $role_stmt->bind_param('i', $role_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        if ($role_data = $role_result->fetch_assoc()) {
            $role_name = $role_data['name'];
        }
    }
    
    // Buscar permissões
    $perm_query = "SELECT sm.code as module_code, sm.name as module_name,
                          rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
                   FROM role_permissions rp
                   JOIN system_modules sm ON rp.module_id = sm.id
                   WHERE rp.role_id = ?";
    
    $perm_stmt = $mysqli->prepare($perm_query);
    $perm_stmt->bind_param('i', $role_id);
    $perm_stmt->execute();
    $result = $perm_stmt->get_result();
    
    // Montar dados do usuário
    $user_data = [
        'id' => $user_row['id'],
        'username' => $user_row['username'] ?? $user_row['email'],
        'full_name' => $user_row['full_name'] ?? $user_row['name'],
        'email' => $user_row['email'],
        'department' => $user_row['department'] ?? '',
        'role_name' => $role_name,
        'role_id' => $role_id ?? 0
    ];
    
    // Montar permissões
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[$row['module_code']] = [
            'name' => $row['module_name'],
            'view' => (bool)$row['can_view'],
            'create' => (bool)$row['can_create'],
            'edit' => (bool)$row['can_edit'],
            'delete' => (bool)$row['can_delete']
        ];
    }
    
    // Se não tem permissões definidas, dar acesso básico
    if (empty($permissions)) {
        $permissions = [
            'dashboard' => ['name' => 'Dashboard', 'view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            'recepcao' => ['name' => 'Recepção', 'view' => true, 'create' => true, 'edit' => true, 'delete' => false]
        ];
    }
    
    $_SESSION['user_data'] = $user_data;
    $_SESSION['permissions'] = $permissions;
    
    // Atualizar último login
    if (isset($user_data['id'])) {
        $mysqli->query("UPDATE users SET updated_at = NOW() WHERE id = " . $user_data['id']);
    }
    
    return $user_data;
}

// Verificar se usuário tem permissão para um módulo
function hasPermission($module_code, $action = 'view') {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    
    $permissions = $_SESSION['permissions'];
    
    if (!isset($permissions[$module_code])) {
        return false;
    }
    
    return $permissions[$module_code][$action] ?? false;
}

// Verificar se usuário pode ver o módulo
function canView($module_code) {
    return hasPermission($module_code, 'view');
}

// Verificar se usuário pode criar no módulo
function canCreate($module_code) {
    return hasPermission($module_code, 'create');
}

// Verificar se usuário pode editar no módulo
function canEdit($module_code) {
    return hasPermission($module_code, 'edit');
}

// Verificar se usuário pode excluir no módulo
function canDelete($module_code) {
    return hasPermission($module_code, 'delete');
}

// Redirecionar se não tiver permissão
function requirePermission($module_code, $action = 'view') {
    if (!hasPermission($module_code, $action)) {
        header('Location: dashboard.php?error=sem_permissao');
        exit;
    }
}

// Obter módulos que o usuário tem acesso
function getAccessibleModules() {
    if (!isset($_SESSION['permissions'])) {
        return [];
    }
    
    $modules = [];
    foreach ($_SESSION['permissions'] as $code => $perms) {
        if ($perms['view']) {
            $modules[] = [
                'code' => $code,
                'name' => $perms['name'],
                'permissions' => $perms
            ];
        }
    }
    
    return $modules;
}

// Verificar se é admin (Diretor Geral)
function isAdmin() {
    return isset($_SESSION['user_data']) && $_SESSION['user_data']['role_id'] == 1;
}

// Obter nome completo do usuário logado
function getFullName() {
    return $_SESSION['user_data']['full_name'] ?? $_SESSION['username'] ?? 'Usuário';
}

// Obter departamento do usuário
function getUserDepartment() {
    return $_SESSION['user_data']['department'] ?? '';
}

// Obter cargo/role do usuário
function getUserRole() {
    return $_SESSION['user_data']['role_name'] ?? '';
}

// Renderizar badge de permissão
function permissionBadge($has_permission, $label) {
    $color = $has_permission ? 'green' : 'red';
    $icon = $has_permission ? 'check-circle' : 'x-circle';
    $text = $has_permission ? $label : "Sem $label";
    
    return "<span class='badge bg-$color'><i class='bi bi-$icon'></i> $text</span>";
}

// Gerar menu com base nas permissões
function generateMenu() {
    global $mysqli;
    
    $modules_query = "SELECT * FROM system_modules WHERE active = 1 ORDER BY display_order";
    $modules = $mysqli->query($modules_query);
    
    $menu_html = '';
    
    while ($module = $modules->fetch_assoc()) {
        if (canView($module['code'])) {
            $active = (basename($_SERVER['PHP_SELF']) == $module['url']) ? 'active' : '';
            
            $menu_html .= "
                <a href='{$module['url']}' class='nav-link $active'>
                    <i class='bi {$module['icon']}'></i>
                    <span>{$module['name']}</span>
                </a>
            ";
        }
    }
    
    return $menu_html;
}

// Log de auditoria
function logAction($module_code, $action, $description, $affected_id = null) {
    global $mysqli;
    
    $user_id = $_SESSION['user_data']['id'] ?? null;
    $username = $_SESSION['user_data']['username'] ?? 'system';
    
    $stmt = $mysqli->prepare("INSERT INTO audit_logs (user_id, username, module_code, action, description, affected_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt->bind_param('isssssss', $user_id, $username, $module_code, $action, $description, $affected_id, $ip, $user_agent);
    $stmt->execute();
}

// Criar tabela de logs (executar uma vez)
function createAuditLogTable() {
    global $mysqli;
    
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            username VARCHAR(100),
            module_code VARCHAR(50),
            action VARCHAR(50),
            description TEXT,
            affected_id INT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_module (module_code),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
?>
