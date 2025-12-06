<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get patients list
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $where = '';
    $params = [];
    $types = '';
    
    if ($search) {
        $where = "WHERE name LIKE ? OR cpf LIKE ? OR phone LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types = 'sss';
    }
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM patients $where";
    if ($params) {
        $stmt = $mysqli->prepare($countQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $countResult = $stmt->get_result();
        $total = $countResult->fetch_assoc()['total'];
    } else {
        $total = $mysqli->query($countQuery)->fetch_assoc()['total'];
    }
    
    // Get patients
    $query = "SELECT id, name, cpf, birth_date, phone, email, address, created_at 
              FROM patients $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    if ($params) {
        $stmt = $mysqli->prepare($query);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    
    sendResponse([
        'patients' => $patients,
        'total' => $total,
        'page' => $page,
        'totalPages' => ceil($total / $limit)
    ]);
    
} elseif ($method === 'POST') {
    // Create new patient
    $input = getJsonInput();
    
    $required = ['name', 'cpf', 'birth_date', 'phone'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Campo '$field' é obrigatório"], 400);
        }
    }
    
    // Check if BI already exists
    $stmt = $mysqli->prepare('SELECT id FROM patients WHERE cpf = ?');
    $stmt->bind_param('s', $input['cpf']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(['error' => 'BI já cadastrado'], 400);
    }
    
    $stmt = $mysqli->prepare(
        'INSERT INTO patients (name, cpf, birth_date, phone, email, address, created_by) 
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    
    $email = $input['email'] ?? null;
    $address = $input['address'] ?? null;
    
    $stmt->bind_param(
        'ssssssi',
        $input['name'],
        $input['cpf'],
        $input['birth_date'],
        $input['phone'],
        $email,
        $address,
        $user['user_id']
    );
    
    if ($stmt->execute()) {
        $patientId = $mysqli->insert_id;
        sendResponse([
            'success' => true,
            'patient_id' => $patientId,
            'message' => 'Paciente cadastrado com sucesso'
        ], 201);
    } else {
        sendResponse(['error' => 'Erro ao cadastrar paciente'], 500);
    }
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
