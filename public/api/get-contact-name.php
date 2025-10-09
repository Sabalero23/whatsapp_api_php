<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$numero = $_GET['numero'] ?? '';

// ✅ Limpiar y validar número
$numero = preg_replace('/[^0-9]/', '', $numero);

if (empty($numero) || strlen($numero) < 10) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Número inválido o vacío',
        'numero_recibido' => $_GET['numero'] ?? 'null'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Buscar el contacto en la base de datos
    $contacto = $db->fetch("SELECT nombre FROM contactos WHERE numero = ?", [$numero]);
    
    if ($contacto && !empty($contacto['nombre'])) {
        echo json_encode([
            'success' => true,
            'nombre' => $contacto['nombre'],
            'numero' => $numero
        ]);
    } else {
        // No hay contacto guardado, devolver sin nombre
        echo json_encode([
            'success' => false,
            'nombre' => null,
            'numero' => $numero
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error en get-contact-name.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}