<?php
require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Login
    $input = getJsonInput();
    
    if (empty($input['email']) || empty($input['password'])) {
        sendResponse(['error' => 'E-mail e senha são obrigatórios'], 400);
    }
    
    $email = trim($input['email']);
    $password = $input['password'];
    
    $stmt = $mysqli->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['email'];
        
        // Load permissions if exists
        if (file_exists(__DIR__ . '/../permissions_helper_simple.php')) {
            require_once __DIR__ . '/../permissions_helper_simple.php';
            loadUserPermissions($user['id']);
        }
        
        sendResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ]);
    } else {
        sendResponse(['error' => 'E-mail ou senha inválidos'], 401);
    }
    
} elseif ($method === 'GET') {
    // Check if authenticated
    if (isset($_SESSION['user_id'])) {
        sendResponse([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? '',
                'email' => $_SESSION['username'] ?? ''
            ]
        ]);
    } else {
        sendResponse(['authenticated' => false], 401);
    }
    
} elseif ($method === 'DELETE') {
    // Logout
    session_destroy();
    sendResponse(['success' => true, 'message' => 'Logout realizado com sucesso']);
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
