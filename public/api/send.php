<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsAppClient.php';

// ✅ Establecer zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

error_log('=== SEND.PHP CALLED ===');

if (!isset($_SESSION['user_id'])) {
    error_log('ERROR: No autenticado');
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();

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

$input = json_decode(file_get_contents('php://input'), true);
error_log('INPUT RECIBIDO: ' . json_encode($input));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$input) {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$to = $input['to'] ?? '';
$message = $input['message'] ?? '';
$media = $input['media'] ?? null;

if (!$to || !$message) {
    error_log('ERROR: Faltan parámetros');
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros requeridos']);
    exit;
}

try {
    error_log("Enviando mensaje a: $to");
    error_log("Mensaje: $message");
    
    $isGroup = strpos($to, '@g.us') !== false;
    
    // ✅ CRÍTICO: Obtener timestamp actual
    $currentTimestamp = time();
    
    if ($isGroup) {
        error_log('ENVIANDO MENSAJE DIRECTO A GRUPO');
        
        $result = $whatsapp->sendMessage($to, $message);
        
        error_log('RESULTADO ENVÍO DIRECTO: ' . json_encode($result));
        
        // ✅ Guardar con timestamp
        $db->insert('mensajes_salientes', [
            'numero_destinatario' => str_replace(['@c.us', '@g.us'], '', $to),
            'mensaje' => $message,
            'timestamp' => $currentTimestamp,
            'estado' => 'enviado',
            'fecha_envio' => date('Y-m-d H:i:s', $currentTimestamp)
        ]);
        
    } else {
        error_log('ENCOLANDO MENSAJE PARA CHAT INDIVIDUAL');
        
        $messageId = $whatsapp->queueMessage($to, $message, $media);
        
        // ✅ Guardar con timestamp
        $db->insert('mensajes_salientes', [
            'mensaje_id' => $messageId,
            'numero_destinatario' => str_replace(['@c.us', '@g.us'], '', $to),
            'mensaje' => $message,
            'timestamp' => $currentTimestamp,
            'estado' => 'pendiente',
            'fecha_envio' => date('Y-m-d H:i:s', $currentTimestamp)
        ]);
        
        $result = ['success' => true, 'messageId' => $messageId];
    }
    
    // Registrar log
    $db->insert('logs', [
        'usuario_id' => $_SESSION['user_id'],
        'accion' => 'Enviar mensaje' . ($isGroup ? ' a grupo' : ''),
        'descripcion' => "Mensaje a $to",
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Actualizar estadísticas
    $today = date('Y-m-d');
    $stat = $db->fetch("SELECT id FROM estadisticas_diarias WHERE fecha = ?", [$today]);
    
    if ($stat) {
        $db->query(
            "UPDATE estadisticas_diarias SET mensajes_enviados = mensajes_enviados + 1 WHERE fecha = ?",
            [$today]
        );
    } else {
        $db->insert('estadisticas_diarias', [
            'fecha' => $today,
            'mensajes_enviados' => 1
        ]);
    }
    
    error_log('=== ÉXITO: Mensaje procesado correctamente ===');
    
    echo json_encode([
        'success' => true,
        'messageId' => $result['messageId'] ?? null,
        'timestamp' => $currentTimestamp,
        'message' => $isGroup ? 'Mensaje enviado a grupo' : 'Mensaje encolado correctamente'
    ]);
    
} catch (Exception $e) {
    error_log('EXCEPCIÓN: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}