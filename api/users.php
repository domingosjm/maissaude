<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get users
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($search) {
        $where[] = "(name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    if ($role) {
        $where[] = "role = ?";
        $params[] = $role;
        $types .= 's';
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT id, name, email, role, active, created_at FROM users $whereClause ORDER BY name ASC";
    
    if ($params) {
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $mysqli->query($query);
    }
    
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    sendResponse(['users' => $users]);
    
} elseif ($method === 'POST') {
    // Create user
    $input = getJsonInput();
    
    $required = ['name', 'email', 'password', 'role'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Campo '$field' é obrigatório"], 400);
        }
    }
    
    // Check if email exists
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $input['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(['error' => 'E-mail já cadastrado'], 400);
    }
    
    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $mysqli->prepare(
        'INSERT INTO users (name, email, password, role, active) VALUES (?, ?, ?, ?, 1)'
    );
    
    $stmt->bind_param('ssss', $input['name'], $input['email'], $hashedPassword, $input['role']);
    
    if ($stmt->execute()) {
        $userId = $mysqli->insert_id;
        sendResponse([
            'success' => true,
            'user_id' => $userId,
            'message' => 'Usuário criado com sucesso'
        ], 201);
    } else {
        sendResponse(['error' => 'Erro ao criar usuário'], 500);
    }
    
} elseif ($method === 'PUT') {
    // Update user
    $input = getJsonInput();
    
    if (empty($input['user_id'])) {
        sendResponse(['error' => 'ID do usuário é obrigatório'], 400);
    }
    
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['name'])) {
        $updates[] = 'name = ?';
        $params[] = $input['name'];
        $types .= 's';
    }
    
    if (isset($input['email'])) {
        $updates[] = 'email = ?';
        $params[] = $input['email'];
        $types .= 's';
    }
    
    if (isset($input['role'])) {
        $updates[] = 'role = ?';
        $params[] = $input['role'];
        $types .= 's';
    }
    
    if (isset($input['active'])) {
        $updates[] = 'active = ?';
        $params[] = $input['active'] ? 1 : 0;
        $types .= 'i';
    }
    
    if (isset($input['password']) && !empty($input['password'])) {
        $updates[] = 'password = ?';
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        $types .= 's';
    }
    
    if (empty($updates)) {
        sendResponse(['error' => 'Nenhum campo para atualizar'], 400);
    }
    
    $params[] = $input['user_id'];
    $types .= 'i';
    
    $query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        sendResponse(['success' => true, 'message' => 'Usuário atualizado com sucesso']);
    } else {
        sendResponse(['error' => 'Erro ao atualizar usuário'], 500);
    }
    
} elseif ($method === 'DELETE') {
    // Deactivate user (soft delete)
    $userId = $_GET['id'] ?? '';
    
    if (empty($userId)) {
        sendResponse(['error' => 'ID do usuário é obrigatório'], 400);
    }
    
    $stmt = $mysqli->prepare('UPDATE users SET active = 0 WHERE id = ?');
    $stmt->bind_param('i', $userId);
    
    if ($stmt->execute()) {
        sendResponse(['success' => true, 'message' => 'Usuário desativado com sucesso']);
    } else {
        sendResponse(['error' => 'Erro ao desativar usuário'], 500);
    }
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
