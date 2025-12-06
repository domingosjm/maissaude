<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3001');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Helper function to send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Helper function to get JSON input
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Helper function to validate authentication
function validateAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['error' => 'Não autenticado'], 401);
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'user_name' => $_SESSION['user_name'] ?? '',
        'username' => $_SESSION['username'] ?? ''
    ];
}
