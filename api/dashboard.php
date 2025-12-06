<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get dashboard statistics
    $stats = [];
    
    // Patients today
    $query = "SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()";
    $stats['patients_today'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Total patients
    $query = "SELECT COUNT(*) as count FROM patients";
    $stats['total_patients'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Appointments today
    $query = "SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()";
    $stats['appointments_today'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Exams pending
    $query = "SELECT COUNT(*) as count FROM lab_exams WHERE status = 'pending'";
    $stats['exams_pending'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Exams completed today
    $query = "SELECT COUNT(*) as count FROM lab_exams WHERE status = 'completed' AND DATE(completed_at) = CURDATE()";
    $stats['exams_completed_today'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Medications count
    $query = "SELECT COUNT(*) as count FROM medications";
    $stats['medications_count'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Low stock medications
    $query = "SELECT COUNT(*) as count FROM medication_stock WHERE quantity < minimum_stock";
    $stats['low_stock_count'] = (int)$mysqli->query($query)->fetch_assoc()['count'];
    
    // Revenue today (if financial table exists)
    $query = "SELECT SUM(amount) as total FROM financial_transactions WHERE DATE(created_at) = CURDATE() AND type = 'income'";
    $result = $mysqli->query($query);
    if ($result) {
        $stats['revenue_today'] = (float)($result->fetch_assoc()['total'] ?? 0);
    } else {
        $stats['revenue_today'] = 0;
    }
    
    sendResponse(['statistics' => $stats]);
    
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
