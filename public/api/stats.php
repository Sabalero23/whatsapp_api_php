<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'messages_chart':
            // Últimos 7 días
            $data = $db->fetchAll(
                "SELECT 
                    fecha,
                    COALESCE(mensajes_enviados, 0) as enviados,
                    COALESCE(mensajes_recibidos, 0) as recibidos
                FROM estadisticas_diarias 
                WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ORDER BY fecha ASC"
            );
            
            $labels = [];
            $sent = [];
            $received = [];
            
            foreach ($data as $row) {
                $labels[] = date('d/m', strtotime($row['fecha']));
                $sent[] = (int) $row['enviados'];
                $received[] = (int) $row['recibidos'];
            }
            
            echo json_encode([
                'labels' => $labels,
                'sent' => $sent,
                'received' => $received
            ]);
            break;
            
        case 'summary':
            $summary = [
                'hoy' => $db->fetch(
                    "SELECT 
                        COALESCE(mensajes_enviados, 0) as enviados,
                        COALESCE(mensajes_recibidos, 0) as recibidos
                    FROM estadisticas_diarias 
                    WHERE fecha = CURDATE()"
                ),
                'mes' => $db->fetch(
                    "SELECT 
                        SUM(COALESCE(mensajes_enviados, 0)) as enviados,
                        SUM(COALESCE(mensajes_recibidos, 0)) as recibidos
                    FROM estadisticas_diarias 
                    WHERE MONTH(fecha) = MONTH(CURDATE()) 
                    AND YEAR(fecha) = YEAR(CURDATE())"
                ),
                'total_contactos' => $db->fetch(
                    "SELECT COUNT(*) as total FROM contactos"
                )['total']
            ];
            
            echo json_encode($summary);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}