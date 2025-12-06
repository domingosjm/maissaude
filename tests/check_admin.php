<?php
require 'config.php';

echo "=== USUÁRIOS ADMINISTRADORES DO SISTEMA ===\n\n";

// Buscar todos os usuários admin
$query = "SELECT u.id, u.name, u.email, u.system_user_id, r.name as role_name 
          FROM users u 
          LEFT JOIN roles r ON u.role_id = r.id 
          WHERE r.name LIKE '%admin%' OR r.name LIKE '%Admin%'
          ORDER BY u.id";

$result = $mysqli->query($query);

if ($result && $result->num_rows > 0) {
    echo "Total de administradores: " . $result->num_rows . "\n\n";
    
    while ($user = $result->fetch_assoc()) {
        echo "-----------------------------------\n";
        echo "ID: {$user['id']}\n";
        echo "Nome: {$user['name']}\n";
        echo "Email: {$user['email']}\n";
        echo "Role: {$user['role_name']}\n";
        echo "System User ID: {$user['system_user_id']}\n";
        echo "-----------------------------------\n\n";
    }
} else {
    echo "Nenhum administrador encontrado!\n";
}

// Verificar todas as roles disponíveis
echo "\n=== ROLES DISPONÍVEIS NO SISTEMA ===\n\n";
$roles_query = "SELECT id, name FROM roles ORDER BY id";
$roles_result = $mysqli->query($roles_query);

if ($roles_result && $roles_result->num_rows > 0) {
    while ($role = $roles_result->fetch_assoc()) {
        echo "ID {$role['id']}: {$role['name']}\n";
    }
}

// Listar alguns usuários para referência
echo "\n\n=== PRIMEIROS 5 USUÁRIOS DO SISTEMA ===\n\n";
$users_query = "SELECT u.id, u.name, u.email, r.name as role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LIMIT 5";
$users_result = $mysqli->query($users_query);

if ($users_result && $users_result->num_rows > 0) {
    while ($user = $users_result->fetch_assoc()) {
        echo "• {$user['name']} ({$user['email']}) - Role: {$user['role_name']}\n";
    }
}
