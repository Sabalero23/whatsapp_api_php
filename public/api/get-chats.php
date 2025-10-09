<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsAppClient.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Cargar configuración
    $envFile = __DIR__ . '/../../.env';
    $env = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    $whatsapp = new WhatsAppClient(
        'http://127.0.0.1:3000',
        $env['API_KEY'],
        [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => $env['REDIS_PASSWORD']
        ]
    );
    
    $chatsData = $whatsapp->getChats(50);
    
    echo json_encode([
        'success' => true,
        'chats' => $chatsData['chats'] ?? [],
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}