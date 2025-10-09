<?php
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'b8c1a3c243e89f8c12e401e00d14b9d8021d7e264c5885d20138045f7c569a0d') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

$config = $db->fetch(
    "SELECT valor FROM configuracion WHERE clave = 'bot_activo'"
);

echo json_encode([
    'activo' => ($config && $config['valor'] == '1')
]);