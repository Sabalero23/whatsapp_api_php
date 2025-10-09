<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $id = $db->insert('plantillas', [
                'nombre' => $input['nombre'],
                'contenido' => $input['contenido'],
                'categoria' => $input['categoria'] ?? 'general'
            ]);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
            
        case 'delete':
            $db->delete('plantillas', 'id = ?', [$input['id']]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}