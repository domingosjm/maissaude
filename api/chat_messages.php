<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$user_id = (int)$_GET['user_id'];

if ($user_id <= 0) {
    echo json_encode(['messages' => []]);
    exit;
}

// Buscar mensagens entre os dois usuários
$query = $mysqli->prepare("
    SELECT cm.*, 
           u_sender.name as sender_name,
           u_receiver.name as receiver_name
    FROM chat_messages cm
    JOIN users u_sender ON cm.sender_id = u_sender.id
    JOIN users u_receiver ON cm.receiver_id = u_receiver.id
    WHERE (cm.sender_id = ? AND cm.receiver_id = ?)
       OR (cm.sender_id = ? AND cm.receiver_id = ?)
    ORDER BY cm.created_at ASC
");

$query->bind_param('iiii', $current_user_id, $user_id, $user_id, $current_user_id);
$query->execute();
$result = $query->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'message' => $row['message'],
        'is_read' => (bool)$row['is_read'],
        'created_at' => $row['created_at'],
        'sender_name' => $row['sender_name'],
        'receiver_name' => $row['receiver_name']
    ];
}

$query->close();

// Marcar mensagens como lidas
$mysqli->query("UPDATE chat_messages SET is_read = TRUE WHERE sender_id = $user_id AND receiver_id = $current_user_id AND is_read = FALSE");

echo json_encode(['messages' => $messages]);
?>
