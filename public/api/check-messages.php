<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

try {
    // Obtener timestamp de última verificación (de la sesión o parámetro)
    $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));
    
    // Obtener mensajes nuevos (excluyendo status@broadcast)
    $newMessages = $db->fetchAll(
        "SELECT 
            me.*,
            c.nombre
        FROM mensajes_entrantes me
        LEFT JOIN contactos c ON c.numero = me.numero_remitente
        WHERE me.fecha_recepcion > ?
        AND me.numero_remitente != 'status@broadcast'
        ORDER BY me.fecha_recepcion DESC
        LIMIT 20",
        [$lastCheck]
    );
    
    // Contar mensajes no leídos totales
    $unreadCount = $db->fetch(
        "SELECT COUNT(*) as total 
        FROM mensajes_entrantes 
        WHERE procesado = 0 
        AND numero_remitente != 'status@broadcast'"
    )['total'];
    
    echo json_encode([
        'success' => true,
        'messages' => $newMessages,
        'count' => count($newMessages),
        'unread_total' => $unreadCount,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}