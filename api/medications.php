<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get medications
    $search = $_GET['search'] ?? '';
    $lowStock = isset($_GET['low_stock']) && $_GET['low_stock'] === 'true';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($search) {
        $where[] = "(m.name LIKE ? OR m.barcode LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    if ($lowStock) {
        $where[] = "ms.quantity < ms.minimum_stock";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total 
                   FROM medications m 
                   LEFT JOIN medication_stock ms ON m.id = ms.medication_id 
                   $whereClause";
    
    if ($params) {
        $stmt = $mysqli->prepare($countQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $total = $mysqli->query($countQuery)->fetch_assoc()['total'];
    }
    
    // Get medications
    $query = "SELECT m.*, ms.quantity, ms.minimum_stock, ms.unit, ms.expiry_date
              FROM medications m
              LEFT JOIN medication_stock ms ON m.id = ms.medication_id
              $whereClause
              ORDER BY m.name ASC LIMIT ? OFFSET ?";
    
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
    $medications = $result->fetch_all(MYSQLI_ASSOC);
    
    sendResponse([
        'medications' => $medications,
        'total' => $total,
        'page' => $page,
        'totalPages' => ceil($total / $limit)
    ]);
    
} elseif ($method === 'POST') {
    // Add new medication
    $input = getJsonInput();
    
    if (empty($input['name'])) {
        sendResponse(['error' => 'Nome do medicamento é obrigatório'], 400);
    }
    
    // Check if medication exists
    $stmt = $mysqli->prepare('SELECT id FROM medications WHERE name = ?');
    $stmt->bind_param('s', $input['name']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(['error' => 'Medicamento já cadastrado'], 400);
    }
    
    $stmt = $mysqli->prepare(
        'INSERT INTO medications (name, description, barcode, manufacturer, created_by) 
         VALUES (?, ?, ?, ?, ?)'
    );
    
    $description = $input['description'] ?? null;
    $barcode = $input['barcode'] ?? null;
    $manufacturer = $input['manufacturer'] ?? null;
    
    $stmt->bind_param('ssssi', $input['name'], $description, $barcode, $manufacturer, $user['user_id']);
    
    if ($stmt->execute()) {
        $medicationId = $mysqli->insert_id;
        
        // Add initial stock if provided
        if (isset($input['quantity'])) {
            $stmtStock = $mysqli->prepare(
                'INSERT INTO medication_stock (medication_id, quantity, minimum_stock, unit, expiry_date) 
                 VALUES (?, ?, ?, ?, ?)'
            );
            
            $minimumStock = $input['minimum_stock'] ?? 10;
            $unit = $input['unit'] ?? 'unidade';
            $expiryDate = $input['expiry_date'] ?? null;
            
            $stmtStock->bind_param('iiiss', $medicationId, $input['quantity'], $minimumStock, $unit, $expiryDate);
            $stmtStock->execute();
        }
        
        sendResponse([
            'success' => true,
            'medication_id' => $medicationId,
            'message' => 'Medicamento cadastrado com sucesso'
        ], 201);
    } else {
        sendResponse(['error' => 'Erro ao cadastrar medicamento'], 500);
    }
    
} elseif ($method === 'PUT') {
    // Update stock
    $input = getJsonInput();
    
    if (empty($input['medication_id']) || !isset($input['quantity'])) {
        sendResponse(['error' => 'ID do medicamento e quantidade são obrigatórios'], 400);
    }
    
    $stmt = $mysqli->prepare(
        'UPDATE medication_stock SET quantity = ?, updated_at = NOW() WHERE medication_id = ?'
    );
    
    $stmt->bind_param('ii', $input['quantity'], $input['medication_id']);
    
    if ($stmt->execute()) {
        // Register stock movement
        $stmtMovement = $mysqli->prepare(
            'INSERT INTO stock_movements (medication_id, type, quantity, user_id, created_at) 
             VALUES (?, ?, ?, ?, NOW())'
        );
        
        $type = $input['type'] ?? 'adjustment';
        $stmtMovement->bind_param('isii', $input['medication_id'], $type, $input['quantity'], $user['user_id']);
        $stmtMovement->execute();
        
        sendResponse(['success' => true, 'message' => 'Stock atualizado com sucesso']);
    } else {
        sendResponse(['error' => 'Erro ao atualizar stock'], 500);
    }
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
