<?php
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'b8c1a3c243e89f8c12e401e00d14b9d8021d7e264c5885d20138045f7c569a0d') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();

try {
    $db->insert('mensajes_salientes', [
        'numero_destino' => $input['numero'],
        'mensaje' => $input['mensaje'],
        'tipo' => 'fuera_horario',
        'estado' => 'enviado',
        'fecha_creacion' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}