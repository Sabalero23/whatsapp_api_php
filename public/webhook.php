<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;

define('LOG_FILE', __DIR__ . '/../logs/webhook.log');

// Leer .env
$envFile = __DIR__ . '/../.env';
$config = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }
}

$WEBHOOK_SECRET = $config['WEBHOOK_SECRET'] ?? '71c4e26c7f99cb7973575f882ef585f210c665bc4f3192ddccbdd70e9ce9165e';

function logMessage($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message";
    if ($data) {
        $logLine .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents(LOG_FILE, $logLine . "\n", FILE_APPEND);
}

logMessage("=== WEBHOOK LLAMADO ===");

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logMessage("ERROR: Método no permitido");
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Validar secret
$headers = getallheaders();
$webhookSecret = $headers['X-Webhook-Secret'] ?? '';

if ($webhookSecret !== $WEBHOOK_SECRET) {
    logMessage('Intento de acceso no autorizado');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Leer payload
$rawInput = file_get_contents('php://input');
$messageData = json_decode($rawInput, true);

if (!$messageData) {
    logMessage('Datos JSON inválidos');
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

logMessage("Mensaje recibido", $messageData);

// Conectar a BD
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=whatsapp_db;charset=utf8mb4',
        'whatsapp_db',
        'b2Byp8e3WwaipXJ4'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logMessage("Conexión a BD exitosa");
} catch (PDOException $e) {
    logMessage('Error de BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
    exit;
}

// Ignorar grupos y estados
$from = $messageData['from'];
if (strpos($from, '@g.us') !== false || $from === 'status@broadcast') {
    logMessage("Mensaje ignorado (grupo o estado)");
    http_response_code(200);
    echo json_encode(['success' => true, 'processed' => false, 'reason' => 'ignored']);
    exit;
}

// Guardar mensaje entrante
try {
    $stmt = $pdo->prepare("
        INSERT INTO mensajes_entrantes 
        (numero_remitente, mensaje, timestamp, tipo, tiene_media, procesado, fecha_recepcion)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([
        $messageData['from'],
        $messageData['body'] ?? '',
        $messageData['timestamp'],
        $messageData['type'] ?? 'chat',
        $messageData['hasMedia'] ? 1 : 0
    ]);
    
    $messageId = $pdo->lastInsertId();
    logMessage("Mensaje guardado con ID: $messageId");
    
} catch (PDOException $e) {
    logMessage('Error al guardar mensaje: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar']);
    exit;
}

// Procesar respuesta automática
$body = trim($messageData['body'] ?? '');

if (empty($body)) {
    logMessage("Mensaje vacío, no se procesa");
    http_response_code(200);
    echo json_encode(['success' => true, 'processed' => false]);
    exit;
}

// Verificar si el bot está activo
$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'bot_activo'");
$stmt->execute();
$botConfig = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$botConfig || $botConfig['valor'] != '1') {
    logMessage("Bot inactivo");
    http_response_code(200);
    echo json_encode(['success' => true, 'processed' => false, 'reason' => 'bot_inactive']);
    exit;
}

logMessage("Bot activo, buscando respuestas...");

// Buscar respuestas automáticas
$stmt = $pdo->prepare("
    SELECT * FROM respuestas_automaticas 
    WHERE activa = 1 
    ORDER BY prioridad DESC, id ASC
");
$stmt->execute();
$respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

logMessage("Respuestas encontradas: " . count($respuestas));

$respuestaEncontrada = null;
$bodyLower = strtolower($body);

foreach ($respuestas as $respuesta) {
    $palabraClaveLower = strtolower($respuesta['palabra_clave']);
    
    logMessage("Evaluando palabra clave", [
        'palabra_clave' => $respuesta['palabra_clave'],
        'exacta' => $respuesta['exacta'],
        'body' => $bodyLower,
        'palabra_lower' => $palabraClaveLower
    ]);
    
    $match = false;
    
    if ($respuesta['exacta'] == 1) {
        $match = ($bodyLower === $palabraClaveLower);
        logMessage("Comparación exacta", ['match' => $match]);
    } else {
        $match = (strpos($bodyLower, $palabraClaveLower) !== false);
logMessage("Comparación contiene", ['match' => $match, 'posicion' => strpos($bodyLower, $palabraClaveLower)]);
    }
    
    if ($match) {
        $respuestaEncontrada = $respuesta;
        logMessage("✅ MATCH encontrado", ['palabra_clave' => $respuesta['palabra_clave']]);
        break;
    }
}

if (!$respuestaEncontrada) {
    logMessage("No se encontró respuesta automática");
    http_response_code(200);
    echo json_encode(['success' => true, 'processed' => false, 'reason' => 'no_match']);
    exit;
}

// Preparar respuesta
$respuestaTexto = $respuestaEncontrada['respuesta'];
$numeroLimpio = str_replace(['@c.us', '@s.whatsapp.net'], '', $from);
$nombre = $numeroLimpio;

// Buscar nombre en contactos
$stmt = $pdo->prepare("SELECT nombre FROM contactos WHERE numero = ?");
$stmt->execute([$numeroLimpio]);
$contacto = $stmt->fetch(PDO::FETCH_ASSOC);

if ($contacto && !empty($contacto['nombre'])) {
    $nombre = $contacto['nombre'];
}

// Reemplazar variables
$variables = [
    '{nombre}' => $nombre,
    '{numero}' => $numeroLimpio,
    '{fecha}' => date('d/m/Y'),
    '{hora}' => date('H:i')
];

$respuestaFinal = str_replace(
    array_keys($variables),
    array_values($variables),
    $respuestaTexto
);

logMessage("Respuesta preparada", ['respuesta' => $respuestaFinal]);

// Conectar a Redis y encolar
try {
    $redisConfig = [
        'scheme' => 'tcp',
        'host'   => $config['REDIS_HOST'] ?? '127.0.0.1',
        'port'   => (int)($config['REDIS_PORT'] ?? 6379),
    ];
    
    if (!empty($config['REDIS_PASSWORD'])) {
        $redisConfig['password'] = $config['REDIS_PASSWORD'];
    }
    
    logMessage("Conectando a Redis", $redisConfig);
    
    $redis = new PredisClient($redisConfig);
    
    // Probar conexión
    $redis->ping();
    logMessage("Redis conectado correctamente");
    
    $outgoingMessageId = 'auto_' . time() . '_' . bin2hex(random_bytes(4));
    
    $outgoingMessage = [
        'to' => $from,
        'message' => $respuestaFinal,
        'messageId' => $outgoingMessageId
    ];
    
    $redis->lpush('whatsapp:outgoing_queue', json_encode($outgoingMessage));
    
    logMessage("✅ Mensaje encolado en Redis", ['messageId' => $outgoingMessageId]);
    
    $redis->disconnect();
    
    // Actualizar contador de usos
    $stmt = $pdo->prepare("
        UPDATE respuestas_automaticas 
        SET contador_usos = contador_usos + 1,
            ultima_vez_usada = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$respuestaEncontrada['id']]);
    
    // Marcar mensaje como procesado
    $stmt = $pdo->prepare("UPDATE mensajes_entrantes SET procesado = 1 WHERE id = ?");
    $stmt->execute([$messageId]);
    
    // Guardar mensaje saliente
    $stmt = $pdo->prepare("
        INSERT INTO mensajes_salientes 
        (numero_destinatario, mensaje, mensaje_id, estado, tipo, fecha_creacion)
        VALUES (?, ?, ?, 'pendiente', 'auto_reply', NOW())
    ");
    $stmt->execute([$from, $respuestaFinal, $outgoingMessageId]);
    
    logMessage("✅ Respuesta automática procesada exitosamente");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'processed' => true,
        'keyword' => $respuestaEncontrada['palabra_clave'],
        'response' => $respuestaFinal,
        'messageId' => $outgoingMessageId
    ]);
    
} catch (Exception $e) {
    logMessage('❌ Error al encolar en Redis: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al encolar mensaje',
        'details' => $e->getMessage()
    ]);
}

logMessage("=== FIN WEBHOOK ===");