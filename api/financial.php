<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get financial transactions
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    $type = $_GET['type'] ?? '';
    
    $where = ["DATE(created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    $types = 'ss';
    
    if ($type) {
        $where[] = "type = ?";
        $params[] = $type;
        $types .= 's';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // Get transactions
    $query = "SELECT ft.*, p.name as patient_name, u.name as created_by_name
              FROM financial_transactions ft
              LEFT JOIN patients p ON ft.patient_id = p.id
              LEFT JOIN users u ON ft.created_by = u.id
              $whereClause
              ORDER BY ft.created_at DESC";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate totals
    $income = 0;
    $expense = 0;
    foreach ($transactions as $transaction) {
        if ($transaction['type'] === 'income') {
            $income += $transaction['amount'];
        } else {
            $expense += $transaction['amount'];
        }
    }
    
    sendResponse([
        'transactions' => $transactions,
        'summary' => [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense
        ]
    ]);
    
} elseif ($method === 'POST') {
    // Create transaction
    $input = getJsonInput();
    
    $required = ['type', 'amount', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Campo '$field' é obrigatório"], 400);
        }
    }
    
    if (!in_array($input['type'], ['income', 'expense'])) {
        sendResponse(['error' => 'Tipo inválido. Use "income" ou "expense"'], 400);
    }
    
    $stmt = $mysqli->prepare(
        'INSERT INTO financial_transactions (type, amount, description, patient_id, category, payment_method, created_by) 
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    
    $patientId = $input['patient_id'] ?? null;
    $category = $input['category'] ?? null;
    $paymentMethod = $input['payment_method'] ?? null;
    
    $stmt->bind_param(
        'sdssssi',
        $input['type'],
        $input['amount'],
        $input['description'],
        $patientId,
        $category,
        $paymentMethod,
        $user['user_id']
    );
    
    if ($stmt->execute()) {
        $transactionId = $mysqli->insert_id;
        sendResponse([
            'success' => true,
            'transaction_id' => $transactionId,
            'message' => 'Transação registrada com sucesso'
        ], 201);
    } else {
        sendResponse(['error' => 'Erro ao registrar transação'], 500);
    }
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
