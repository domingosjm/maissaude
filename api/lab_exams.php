<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get lab exams
    $status = $_GET['status'] ?? '';
    $patientId = $_GET['patient_id'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($status) {
        $where[] = "le.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($patientId) {
        $where[] = "le.patient_id = ?";
        $params[] = $patientId;
        $types .= 'i';
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM lab_exams le $whereClause";
    if ($params) {
        $stmt = $mysqli->prepare($countQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $total = $mysqli->query($countQuery)->fetch_assoc()['total'];
    }
    
    // Get exams
    $query = "SELECT le.*, p.name as patient_name, p.cpf as patient_cpf,
              et.name as exam_type_name, u.name as created_by_name
              FROM lab_exams le
              LEFT JOIN patients p ON le.patient_id = p.id
              LEFT JOIN exam_types et ON le.exam_type_id = et.id
              LEFT JOIN users u ON le.created_by = u.id
              $whereClause
              ORDER BY le.created_at DESC LIMIT ? OFFSET ?";
    
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
    $exams = $result->fetch_all(MYSQLI_ASSOC);
    
    sendResponse([
        'exams' => $exams,
        'total' => $total,
        'page' => $page,
        'totalPages' => ceil($total / $limit)
    ]);
    
} elseif ($method === 'POST') {
    // Create new exam request
    $input = getJsonInput();
    
    if (empty($input['patient_id']) || empty($input['exam_type_id'])) {
        sendResponse(['error' => 'Paciente e tipo de exame são obrigatórios'], 400);
    }
    
    $stmt = $mysqli->prepare(
        'INSERT INTO lab_exams (patient_id, exam_type_id, status, requested_by, created_at) 
         VALUES (?, ?, ?, ?, NOW())'
    );
    
    $status = 'pending';
    $stmt->bind_param('iisi', $input['patient_id'], $input['exam_type_id'], $status, $user['user_id']);
    
    if ($stmt->execute()) {
        $examId = $mysqli->insert_id;
        sendResponse([
            'success' => true,
            'exam_id' => $examId,
            'message' => 'Exame solicitado com sucesso'
        ], 201);
    } else {
        sendResponse(['error' => 'Erro ao solicitar exame'], 500);
    }
    
} elseif ($method === 'PUT') {
    // Update exam results
    $input = getJsonInput();
    
    if (empty($input['exam_id'])) {
        sendResponse(['error' => 'ID do exame é obrigatório'], 400);
    }
    
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['results'])) {
        $updates[] = 'results = ?';
        $params[] = $input['results'];
        $types .= 's';
    }
    
    if (isset($input['status'])) {
        $updates[] = 'status = ?';
        $params[] = $input['status'];
        $types .= 's';
        
        if ($input['status'] === 'completed') {
            $updates[] = 'completed_at = NOW()';
            $updates[] = 'completed_by = ?';
            $params[] = $user['user_id'];
            $types .= 'i';
        }
    }
    
    if (empty($updates)) {
        sendResponse(['error' => 'Nenhum campo para atualizar'], 400);
    }
    
    $params[] = $input['exam_id'];
    $types .= 'i';
    
    $query = 'UPDATE lab_exams SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        sendResponse(['success' => true, 'message' => 'Exame atualizado com sucesso']);
    } else {
        sendResponse(['error' => 'Erro ao atualizar exame'], 500);
    }
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
