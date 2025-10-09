<?php
session_start();
require_once __DIR__ . '/../../src/WhatsAppClient.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

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

try {
    // Obtener todos los chats
    $chatsData = $whatsapp->getChats(100);
    $chats = $chatsData['chats'] ?? [];
    
    $processed = 0;
    $errors = 0;
    
    // Marcar cada chat con mensajes no leídos
    foreach ($chats as $chat) {
        if ($chat['unreadCount'] > 0) {
            try {
                $whatsapp->markAsRead($chat['id']);
                $processed++;
                usleep(200000); // 200ms entre cada petición para no saturar
            } catch (Exception $e) {
                $errors++;
                error_log("Error marcando chat {$chat['id']}: " . $e->getMessage());
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'errors' => $errors,
        'message' => "$processed chat(s) marcado(s) como leído(s)"
    ]);
    
} catch (Exception $e) {
    error_log('Error en mark-all-read: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}