<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsAppClient.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();

// Cargar WhatsApp Client
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'mark_read':
                $chatId = $input['chatId'];
                $result = $whatsapp->markAsRead($chatId);
                echo json_encode(['success' => true]);
                break;
                
            case 'archive':
                $chatId = $input['chatId'];
                $result = $whatsapp->archiveChat($chatId);
                echo json_encode(['success' => true]);
                break;
                
            case 'pin':
                $chatId = $input['chatId'];
                $result = $whatsapp->pinChat($chatId);
                echo json_encode(['success' => true]);
                break;
                
            case 'mute':
                $chatId = $input['chatId'];
                $duration = $input['duration'] ?? null;
                $result = $whatsapp->muteChat($chatId, $duration);
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'check_new':
                // Verificar mensajes nuevos
                $lastCheck = $_SESSION['last_check'] ?? time() - 60;
                $newMessages = $db->fetch(
                    "SELECT COUNT(*) as total FROM mensajes_entrantes 
                    WHERE UNIX_TIMESTAMP(fecha_recepcion) > ?",
                    [$lastCheck]
                )['total'];
                
                $_SESSION['last_check'] = time();
                echo json_encode(['hasNew' => $newMessages > 0, 'count' => $newMessages]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}