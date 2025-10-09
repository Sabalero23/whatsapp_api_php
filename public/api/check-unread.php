<?php
/**
 * API: Verificar mensajes no leídos
 * Sistema de notificaciones global
 */

// Configuración de errores
ini_set('display_errors', 0);
error_reporting(0);

// Limpiar cualquier salida previa
if (ob_get_level()) ob_end_clean();
ob_start();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

session_start();

// Función para enviar JSON limpio
function sendJSON($data) {
    if (ob_get_level()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    sendJSON([
        'success' => false,
        'error' => 'No autenticado',
        'totalUnread' => 0,
        'chats' => []
    ]);
}

try {
    // Cargar dependencias
    require_once __DIR__ . '/../../src/Database.php';
    require_once __DIR__ . '/../../src/WhatsAppClient.php';
    
    // Cargar configuración
    $envFile = __DIR__ . '/../../.env';
    $env = [];
    
    if (file_exists($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                if (empty($line) || $line[0] === '#') continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $env[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
    }
    
    // Inicializar WhatsApp Client
    $whatsapp = new WhatsAppClient(
        'http://127.0.0.1:3000',
        $env['API_KEY'] ?? '',
        [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => $env['REDIS_PASSWORD'] ?? null
        ]
    );
    
    // Verificar conexión
    if (!$whatsapp->isReady()) {
        sendJSON([
            'success' => true,
            'totalUnread' => 0,
            'chats' => [],
            'whatsappReady' => false
        ]);
    }
    
    // Obtener chats
    $chatsData = $whatsapp->getChats(100);
    
    if (!isset($chatsData['chats']) || !is_array($chatsData['chats'])) {
        sendJSON([
            'success' => true,
            'totalUnread' => 0,
            'chats' => [],
            'whatsappReady' => true
        ]);
    }
    
    // Procesar mensajes no leídos
    $unreadChats = [];
    $totalUnread = 0;
    
    foreach ($chatsData['chats'] as $chat) {
        $chatId = $chat['id'] ?? '';
        $chatName = $chat['name'] ?? 'Desconocido';
        $unreadCount = isset($chat['unreadCount']) ? (int)$chat['unreadCount'] : 0;
        
        // ✅ FILTRAR CHATS INVÁLIDOS
        if (
            // Ignorar estados/broadcast
            strpos($chatId, 'status@broadcast') !== false ||
            strpos($chatId, '@broadcast') !== false ||
            stripos($chatName, 'mi estado') !== false ||
            stripos($chatName, 'my status') !== false ||
            // Ignorar chats sin ID válido
            $chatId === '0@c.us' ||
            empty($chatId)
        ) {
            continue;
        }
        
        // Solo chats con mensajes no leídos
        if ($unreadCount > 0) {
            $unreadChats[] = [
                'id' => $chatId,
                'name' => $chatName,
                'unreadCount' => $unreadCount,
                'timestamp' => isset($chat['timestamp']) ? (int)$chat['timestamp'] : 0,
                'isGroup' => !empty($chat['isGroup'])
            ];
            
            $totalUnread += $unreadCount;
        }
    }
    
    // Ordenar por timestamp (más reciente primero)
    usort($unreadChats, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Limitar a los 10 chats más recientes con mensajes no leídos
    $unreadChats = array_slice($unreadChats, 0, 10);
    
    // Respuesta exitosa
    sendJSON([
        'success' => true,
        'totalUnread' => $totalUnread,
        'chats' => $unreadChats,
        'whatsappReady' => true,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    // Log del error
    @error_log("check-unread.php error: " . $e->getMessage());
    
    sendJSON([
        'success' => false,
        'error' => 'Error interno del servidor',
        'totalUnread' => 0,
        'chats' => [],
        'debug' => $e->getMessage() // Solo para desarrollo
    ]);
}