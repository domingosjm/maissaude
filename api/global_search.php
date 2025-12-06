<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$query = isset($data['query']) ? trim($data['query']) : '';
$filter = isset($data['filter']) ? $data['filter'] : 'all';

if (strlen($query) < 2) {
    echo json_encode(['error' => 'Query muito curta']);
    exit;
}

$searchTerm = '%' . $mysqli->real_escape_string($query) . '%';
$results = [
    'patients' => [],
    'exams' => [],
    'medications' => [],
    'invoices' => []
];

// Buscar Pacientes
if ($filter === 'all' || $filter === 'patients') {
    $stmt = $mysqli->prepare("
        SELECT id, name, codigo, cpf, phone, email 
        FROM patients 
        WHERE name LIKE ? 
           OR codigo LIKE ? 
           OR cpf LIKE ? 
           OR phone LIKE ?
        ORDER BY name ASC
        LIMIT 10
    ");
    $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results['patients'][] = $row;
    }
    $stmt->close();
}

// Buscar Exames (Solicitações de Exames)
if ($filter === 'all' || $filter === 'exams') {
    $stmt = $mysqli->prepare("
        SELECT DISTINCT
            e.id,
            e.name as exam_name,
            p.name as patient_name,
            p.codigo as patient_codigo,
            ar.scheduled_at
        FROM exam_requests er
        JOIN exams e ON er.exam_id = e.id
        JOIN attendances a ON er.attendance_id = a.id
        JOIN appointments ar ON a.appointment_id = ar.id
        JOIN patients p ON ar.patient_id = p.id
        WHERE e.name LIKE ?
           OR p.name LIKE ?
           OR p.codigo LIKE ?
        ORDER BY er.requested_at DESC
        LIMIT 10
    ");
    $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results['exams'][] = $row;
    }
    $stmt->close();
}

// Buscar Medicamentos
if ($filter === 'all' || $filter === 'medications') {
    $stmt = $mysqli->prepare("
        SELECT 
            m.id,
            m.name,
            m.description,
            COALESCE(ms.quantity, 0) as stock_quantity
        FROM medications m
        LEFT JOIN medication_stock ms ON m.id = ms.medication_id
        WHERE m.name LIKE ?
           OR m.description LIKE ?
        ORDER BY m.name ASC
        LIMIT 10
    ");
    $stmt->bind_param('ss', $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results['medications'][] = $row;
    }
    $stmt->close();
}

// Buscar Faturas
if ($filter === 'all' || $filter === 'invoices') {
    $stmt = $mysqli->prepare("
        SELECT 
            i.id,
            i.invoice_number,
            i.status,
            i.total,
            i.issue_date,
            i.due_date,
            p.name as patient_name,
            p.codigo as patient_codigo
        FROM invoices i
        JOIN patients p ON i.patient_id = p.id
        WHERE i.invoice_number LIKE ?
           OR p.name LIKE ?
           OR p.codigo LIKE ?
        ORDER BY i.issue_date DESC
        LIMIT 10
    ");
    $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results['invoices'][] = $row;
    }
    $stmt->close();
}

echo json_encode($results);
