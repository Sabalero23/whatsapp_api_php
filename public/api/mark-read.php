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
$input = json_decode(file_get_contents('php://input'), true);

try {
    if (isset($input['all']) && $input['all']) {
        // Marcar todos como leídos
        $db->execute(
            "UPDATE mensajes_entrantes 
            SET procesado = 1 
            WHERE procesado = 0 
            AND numero_remitente != 'status@broadcast'"
        );
    } elseif (isset($input['id'])) {
        // Marcar uno específico
        $db->execute(
            "UPDATE mensajes_entrantes 
            SET procesado = 1 
            WHERE id = ?",
            [$input['id']]
        );
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}