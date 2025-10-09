<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsAppClient.php';

// ✅ CRÍTICO: Establecer zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

set_time_limit(15);
ini_set('max_execution_time', '15');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$chatId = $_GET['chatId'] ?? '';
$after = (int)($_GET['after'] ?? 0);

if (!$chatId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Chat ID requerido']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $numero = str_replace(['@c.us', '@g.us'], '', $chatId);
    
    $cacheKey = "last_sync_{$numero}";
    $lastSync = $_SESSION[$cacheKey] ?? 0;
    $now = time();
    
    $shouldSync = ($now - $lastSync) > 10;
    
    if ($shouldSync) {
        try {
            $envFile = __DIR__ . '/../../.env';
            $env = [];
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (!strpos($line, '=')) continue;
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
            
            $whatsapp = new WhatsAppClient(
                'http://127.0.0.1:3000',
                $env['API_KEY'] ?? '',
                [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password' => $env['REDIS_PASSWORD'] ?? null
                ]
            );
            
            $messagesData = $whatsapp->getChatMessages($chatId, 20);
            
            if (isset($messagesData['messages'])) {
                foreach ($messagesData['messages'] as $msg) {
                    $exists = $db->fetch(
                        "SELECT id FROM mensajes_entrantes WHERE mensaje_id = ?",
                        [$msg['id']]
                    );
                    
                    if (!$exists && !$msg['fromMe']) {
                        $tipoMedia = 'chat';
                        $mediaUrl = null;
                        
                        if ($msg['hasMedia']) {
                            if (isset($msg['type'])) {
                                switch ($msg['type']) {
                                    case 'image':
                                        $tipoMedia = 'image';
                                        break;
                                    case 'video':
                                        $tipoMedia = 'video';
                                        break;
                                    case 'audio':
                                    case 'ptt':
                                        $tipoMedia = 'audio';
                                        break;
                                    case 'document':
                                        $tipoMedia = 'document';
                                        break;
                                    default:
                                        $tipoMedia = 'media';
                                }
                            }
                            
                            if (isset($msg['mediaUrl'])) {
                                $mediaUrl = $msg['mediaUrl'];
                            }
                        }
                        
                        $db->insert('mensajes_entrantes', [
                            'mensaje_id' => $msg['id'],
                            'numero_remitente' => $numero,
                            'mensaje' => $msg['body'] ?? '',
                            'timestamp' => $msg['timestamp'],
                            'tipo' => $tipoMedia,
                            'tiene_media' => $msg['hasMedia'] ? 1 : 0,
                            'media_url' => $mediaUrl,
                            'procesado' => 0,
                            'fecha_recepcion' => date('Y-m-d H:i:s', $msg['timestamp'])
                        ]);
                    }
                }
            }
            
            $_SESSION[$cacheKey] = $now;
            
        } catch (Exception $e) {
            error_log('Error sincronizando con WhatsApp: ' . $e->getMessage());
        }
    }
    
    // ✅ CORRECCIÓN: Usar timestamp directo para mensajes entrantes
    $query = "SELECT 
                mensaje_id as id,
                mensaje as body,
                tipo as type,
                timestamp,
                0 as fromMe,
                tiene_media as hasMedia,
                media_url as mediaUrl,
                media_url as mediaPath
              FROM mensajes_entrantes 
              WHERE numero_remitente = ? 
              AND timestamp > ?
              ORDER BY timestamp ASC 
              LIMIT 50";
    
    $mensajesEntrantes = $db->fetchAll($query, [$numero, $after]);
    
    // ✅ CORRECCIÓN: Usar timestamp directo para mensajes salientes
    $querySalientes = "SELECT 
                        mensaje_id as id,
                        mensaje as body,
                        tipo as type,
                        timestamp,
                        1 as fromMe,
                        IF(media_path IS NOT NULL, 1, 0) as hasMedia,
                        media_path as mediaUrl,
                        media_path as mediaPath
                       FROM mensajes_salientes 
                       WHERE numero_destinatario = ? 
                       AND timestamp > ?
                       ORDER BY timestamp ASC 
                       LIMIT 50";
    
    $mensajesSalientes = $db->fetchAll($querySalientes, [$numero, $after]);
    
    $messages = array_merge($mensajesEntrantes, $mensajesSalientes);
    
    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    $messages = array_map(function($msg) {
        return [
            'id' => $msg['id'] ?? uniqid('msg_'),
            'body' => $msg['body'] ?? '',
            'type' => $msg['type'] ?? 'chat',
            'timestamp' => (int)$msg['timestamp'],
            'fromMe' => (bool)$msg['fromMe'],
            'hasMedia' => (bool)($msg['hasMedia'] ?? false),
            'mediaUrl' => $msg['mediaUrl'] ?? null,
            'mediaPath' => $msg['mediaPath'] ?? null
        ];
    }, $messages);
    
    $lastTimestamp = 0;
    if (!empty($messages)) {
        $lastTimestamp = max(array_column($messages, 'timestamp'));
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages),
        'lastTimestamp' => $lastTimestamp,
        'synced' => $shouldSync
    ]);
    
} catch (Exception $e) {
    error_log('Error en get-chat-messages.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}