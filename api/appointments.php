<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get appointments
    $date = $_GET['date'] ?? date('Y-m-d');
    $patientId = $_GET['patient_id'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $where = ["DATE(appointment_date) = ?"];
    $params = [$date];
    $types = 's';
    
    if ($patientId) {
        $where[] = "patient_id = ?";
        $params[] = $patientId;
        $types .= 'i';
    }
    
    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $query = "SELECT a.*, p.name as patient_name, p.cpf as patient_cpf, p.phone as patient_phone,
              d.name as doctor_name, s.name as service_name
              FROM appointments a
              LEFT JOIN patients p ON a.patient_id = p.id
              LEFT JOIN users d ON a.doctor_id = d.id
              LEFT JOIN services s ON a.service_id = s.id
              $whereClause
              ORDER BY a.appointment_date ASC";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    
    sendResponse(['appointments' => $appointments]);
    
} elseif ($method === 'POST') {
    // Create appointment
    $input = getJsonInput();
    
    $required = ['patient_id', 'appointment_date', 'appointment_time'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Campo '$field' é obrigatório"], 400);
        }
    }
    
    // Combine date and time
    $appointmentDateTime = $input['appointment_date'] . ' ' . $input['appointment_time'];
    
    $stmt = $mysqli->prepare(
        'INSERT INTO appointments (patient_id, doctor_id, service_id, appointment_date, status, notes, created_by) 
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    
    $doctorId = $input['doctor_id'] ?? null;
    $serviceId = $input['service_id'] ?? null;
    $status = 'scheduled';
    $notes = $input['notes'] ?? null;
    
    $stmt->bind_param(
        'iiisssi',
        $input['patient_id'],
        $doctorId,
        $serviceId,
        $appointmentDateTime,
        $status,
        $notes,
        $user['user_id']
    );
    
    if ($stmt->execute()) {
        $appointmentId = $mysqli->insert_id;
        sendResponse([
            'success' => true,
            'appointment_id' => $appointmentId,
            'message' => 'Agendamento criado com sucesso'
        ], 201);
    } else {
        sendResponse(['error' => 'Erro ao criar agendamento'], 500);
    }
    
} elseif ($method === 'PUT') {
    // Update appointment status
    $input = getJsonInput();
    
    if (empty($input['appointment_id']) || empty($input['status'])) {
        sendResponse(['error' => 'ID do agendamento e status são obrigatórios'], 400);
    }
    
    $validStatuses = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($input['status'], $validStatuses)) {
        sendResponse(['error' => 'Status inválido'], 400);
    }
    
    $stmt = $mysqli->prepare('UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $input['status'], $input['appointment_id']);
    
    if ($stmt->execute()) {
        sendResponse(['success' => true, 'message' => 'Agendamento atualizado com sucesso']);
    } else {
        sendResponse(['error' => 'Erro ao atualizar agendamento'], 500);
    }
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
