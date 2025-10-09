<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Obtener datos de los últimos 7 días
    $labels = [];
    $sent = [];
    $received = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d M', strtotime($date));
        
        // Mensajes enviados
        $result = $db->fetch("
            SELECT COUNT(*) as total 
            FROM mensajes_salientes 
            WHERE DATE(fecha_creacion) = ?
        ", [$date]);
        $sent[] = (int)($result['total'] ?? 0);
        
        // Mensajes recibidos
        $result = $db->fetch("
            SELECT COUNT(*) as total 
            FROM mensajes_entrantes 
            WHERE DATE(fecha_recepcion) = ?
        ", [$date]);
        $received[] = (int)($result['total'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'sent' => $sent,
        'received' => $received
    ]);
    
} catch (Exception $e) {
    error_log('Error en dashboard-stats.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor'
    ]);
}