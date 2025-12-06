<?php
/**
 * Sistema de Permissões Simplificado - MSLDA
 */

// Carregar permissões do usuário
function loadUserPermissions($user_id) {
    global $mysqli;
    
    // Inicializar com valores padrão
    $_SESSION['user_data'] = [
        'id' => $user_id,
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['user_name'] ?? 'Usuário',
        'email' => $_SESSION['username'] ?? '',
        'department' => '',
        'role_name' => 'Usuário',
        'role_id' => 0
    ];
    
    $_SESSION['permissions'] = [
        'dashboard' => ['name' => 'Dashboard', 'view' => true, 'create' => false, 'edit' => false, 'delete' => false],
        'recepcao' => ['name' => 'Recepção', 'view' => true, 'create' => true, 'edit' => true, 'delete' => false],
        'financeiro' => ['name' => 'Financeiro', 'view' => true, 'create' => true, 'edit' => true, 'delete' => false],
        'laboratorio' => ['name' => 'Laboratório', 'view' => true, 'create' => true, 'edit' => false, 'delete' => false]
    ];
    
    try {
        // Buscar role_id do usuário
        $stmt = $mysqli->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && $user['role_id']) {
            $role_id = $user['role_id'];
            $_SESSION['user_data']['role_id'] = $role_id;
            
            // Buscar nome do role
            $role_stmt = $mysqli->prepare("SELECT name FROM user_roles WHERE id = ?");
            $role_stmt->bind_param('i', $role_id);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            if ($role_data = $role_result->fetch_assoc()) {
                $_SESSION['user_data']['role_name'] = $role_data['name'];
            }
            
            // Buscar permissões específicas
            $perm_query = "SELECT sm.code, sm.name, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
                          FROM role_permissions rp
                          JOIN system_modules sm ON rp.module_id = sm.id
                          WHERE rp.role_id = ?";
            $perm_stmt = $mysqli->prepare($perm_query);
            $perm_stmt->bind_param('i', $role_id);
            $perm_stmt->execute();
            $perm_result = $perm_stmt->get_result();
            
            $custom_permissions = [];
            while ($perm = $perm_result->fetch_assoc()) {
                $custom_permissions[$perm['code']] = [
                    'name' => $perm['name'],
                    'view' => (bool)$perm['can_view'],
                    'create' => (bool)$perm['can_create'],
                    'edit' => (bool)$perm['can_edit'],
                    'delete' => (bool)$perm['can_delete']
                ];
            }
            
            // Se encontrou permissões, usar elas
            if (!empty($custom_permissions)) {
                $_SESSION['permissions'] = $custom_permissions;
            }
        }
        
        // Buscar dados complementares do system_users
        $su_stmt = $mysqli->prepare("SELECT su.full_name, su.department FROM users u 
                                      JOIN system_users su ON u.system_user_id = su.id 
                                      WHERE u.id = ?");
        $su_stmt->bind_param('i', $user_id);
        $su_stmt->execute();
        $su_result = $su_stmt->get_result();
        if ($su_data = $su_result->fetch_assoc()) {
            $_SESSION['user_data']['full_name'] = $su_data['full_name'];
            $_SESSION['user_data']['department'] = $su_data['department'];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao carregar permissões: " . $e->getMessage());
        // Mantém os valores padrão já definidos
    }
    
    return $_SESSION['user_data'];
}

// Verificar permissão
function hasPermission($module_code, $action = 'view') {
    if (!isset($_SESSION['permissions'])) {
        return true; // Acesso liberado por padrão
    }
    
    if (!isset($_SESSION['permissions'][$module_code])) {
        return false;
    }
    
    return $_SESSION['permissions'][$module_code][$action] ?? false;
}

// Atalhos
function canView($module_code) {
    return hasPermission($module_code, 'view');
}

function canCreate($module_code) {
    return hasPermission($module_code, 'create');
}

function canEdit($module_code) {
    return hasPermission($module_code, 'edit');
}

function canDelete($module_code) {
    return hasPermission($module_code, 'delete');
}

// Obter nome completo
function getFullName() {
    return $_SESSION['user_data']['full_name'] ?? $_SESSION['user_name'] ?? 'Usuário';
}

// Obter departamento
function getUserDepartment() {
    return $_SESSION['user_data']['department'] ?? '';
}

// Obter cargo
function getUserRole() {
    return $_SESSION['user_data']['role_name'] ?? '';
}

// Verificar se é admin
function isAdmin() {
    return isset($_SESSION['user_data']) && $_SESSION['user_data']['role_id'] == 1;
}
?>
