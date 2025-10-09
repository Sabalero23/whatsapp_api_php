<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Obtener TODOS los contactos con nombre
    $contactos = $db->fetchAll(
        "SELECT numero, nombre FROM contactos WHERE nombre IS NOT NULL AND nombre != '' ORDER BY nombre ASC"
    );
    
    echo json_encode([
        'success' => true,
        'contactos' => $contactos,
        'total' => count($contactos)
    ]);
    
} catch (Exception $e) {
    error_log('Error en get-all-contact-names.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}