<?php
/**
 * API de Notificações
 * Gerencia notificações push e alertas do sistema
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Criar tabela de notificações se não existir
$create_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('success', 'error', 'warning', 'info') DEFAULT 'info',
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    url VARCHAR(500),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
)";

$mysqli->query($create_table);

switch ($action) {
    case 'check_new':
        // Buscar notificações não lidas dos últimos 5 minutos
        $stmt = $mysqli->prepare("
            SELECT id, title, message, type, priority, url
            FROM notifications
            WHERE user_id = ? 
            AND is_read = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY created_at DESC
        ");
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        // Contar total não lidas
        $count_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $count_stmt->bind_param('i', $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $unread_count = $count_result->fetch_assoc()['count'];
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'unread_count':
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        break;
        
    case 'get_all':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $stmt = $mysqli->prepare("
            SELECT id, title, message, type, priority, url, is_read, created_at, read_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
        break;
        
    case 'mark_read':
        $input = json_decode(file_get_contents('php://input'), true);
        $notification_id = $input['notification_id'] ?? 0;
        
        $stmt = $mysqli->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->bind_param('ii', $notification_id, $user_id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'mark_all_read':
        $stmt = $mysqli->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ? AND is_read = 0
        ");
        
        $stmt->bind_param('i', $user_id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'create':
        // Criar nova notificação (apenas para admins ou sistema)
        if ($_SESSION['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Permissão negada']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $target_user_id = $input['user_id'] ?? null;
        $title = $input['title'] ?? '';
        $message = $input['message'] ?? '';
        $type = $input['type'] ?? 'info';
        $priority = $input['priority'] ?? 'normal';
        $url = $input['url'] ?? null;
        
        if ($target_user_id && $title && $message) {
            $stmt = $mysqli->prepare("
                INSERT INTO notifications (user_id, title, message, type, priority, url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param('isssss', $target_user_id, $title, $message, $type, $priority, $url);
            $success = $stmt->execute();
            
            echo json_encode([
                'success' => $success,
                'notification_id' => $mysqli->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}
