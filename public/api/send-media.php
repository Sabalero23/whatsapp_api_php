<?php
ob_start();
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '512M'); // Aumentar de 256M a 512M
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', '600'); // Aumentar de 300 a 600 segundos

header('Content-Type: application/json');

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsAppClient.php';

$logFile = __DIR__ . '/../../logs/send-media.log';
function logToFile($message) {
    global $logFile;
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

logToFile("=== INICIO send-media.php ===");

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

if (empty($_FILES['file'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Archivo no enviado']);
    exit;
}

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    logToFile("ERROR DB: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
    exit;
}

$envFile = __DIR__ . '/../../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (!strpos($line, '=')) continue;
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

try {
    $whatsapp = new WhatsAppClient(
        'http://127.0.0.1:3000',
        $env['API_KEY'] ?? 'default_key',
        [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => $env['REDIS_PASSWORD'] ?? null
        ]
    );
} catch (Exception $e) {
    logToFile("ERROR WhatsApp: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error WhatsApp']);
    exit;
}

$to = $_POST['to'] ?? '';
$caption = $_POST['caption'] ?? '';
$file = $_FILES['file'];

if (!$to) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Destinatario requerido']);
    exit;
}

$maxSize = 50 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Archivo muy grande (max 50MB)']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    logToFile("Upload error: " . $file['error']);
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al subir archivo']);
    exit;
}

$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/mpeg',
    'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/x-m4a',
    'application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

if (!in_array($file['type'], $allowedTypes)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo no permitido']);
    exit;
}

try {
    // Construir rutas absolutas desde __DIR__ (que está en /public/api/)
    $uploadDir = __DIR__ . '/../../media/temp';
    $permanentDir = __DIR__ . '/../../media/uploads';
    
    // Asegurar que terminan con /
    $uploadDir = rtrim($uploadDir, '/') . '/';
    $permanentDir = rtrim($permanentDir, '/') . '/';
    
    logToFile("Ruta uploadDir: " . $uploadDir);
    logToFile("Ruta permanentDir: " . $permanentDir);
    
    // Crear directorios si no existen
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0775, true)) {
            throw new Exception('No se pudo crear directorio temporal: ' . $uploadDir);
        }
        logToFile("Directorio temporal creado: $uploadDir");
    }
    
    if (!is_dir($permanentDir)) {
        if (!@mkdir($permanentDir, 0775, true)) {
            throw new Exception('No se pudo crear directorio permanente: ' . $permanentDir);
        }
        logToFile("Directorio permanente creado: $permanentDir");
    }
    
    // Verificar permisos intentando crear un archivo de prueba
    $testFile = $uploadDir . '.test_' . uniqid();
    if (@file_put_contents($testFile, 'test') === false) {
        logToFile("ADVERTENCIA: is_writable devuelve false, pero intentaremos continuar");
        logToFile("Permisos uploadDir: " . substr(sprintf('%o', fileperms($uploadDir)), -4));
    } else {
        @unlink($testFile);
        logToFile("Verificación de escritura OK en uploadDir");
    }
    
    $testFile = $permanentDir . '.test_' . uniqid();
    if (@file_put_contents($testFile, 'test') === false) {
        logToFile("ADVERTENCIA: No se puede escribir en permanentDir");
        logToFile("Permisos permanentDir: " . substr(sprintf('%o', fileperms($permanentDir)), -4));
    } else {
        @unlink($testFile);
        logToFile("Verificación de escritura OK en permanentDir");
    }
    
    // Generar nombre único para el archivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('media_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    $permanentPath = $permanentDir . $filename;
    
    logToFile("Moviendo archivo subido a: $filepath");
    logToFile("Archivo temporal PHP: " . $file['tmp_name']);
    logToFile("Existe tmp_name: " . (file_exists($file['tmp_name']) ? 'si' : 'no'));
    logToFile("Permisos directorio destino: " . substr(sprintf('%o', fileperms($uploadDir)), -4));
    logToFile("open_basedir: " . (ini_get('open_basedir') ?: 'no configurado'));
    
    // Verificar que el archivo temporal es legible
    if (!is_readable($file['tmp_name'])) {
        throw new Exception('El archivo temporal no es legible: ' . $file['tmp_name']);
    }
    
    // Intentar mover archivo subido
    $moved = @move_uploaded_file($file['tmp_name'], $filepath);
    $moveError = error_get_last();
    
    if (!$moved) {
        logToFile("move_uploaded_file falló. Error: " . ($moveError['message'] ?? 'desconocido'));
        logToFile("Intentando método alternativo con file_get_contents/file_put_contents...");
        
        // Leer contenido del archivo temporal
        $content = @file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new Exception('No se pudo leer el archivo temporal');
        }
        
        // Escribir en destino
        $written = @file_put_contents($filepath, $content);
        if ($written === false) {
            $error = error_get_last();
            throw new Exception('Error al escribir archivo: ' . ($error['message'] ?? 'desconocido'));
        }
        
        // Eliminar el archivo temporal original
        @unlink($file['tmp_name']);
        logToFile("Archivo guardado usando file_get_contents/file_put_contents");
    } else {
        logToFile("Archivo movido correctamente con move_uploaded_file()");
    }
    
    if (!file_exists($filepath)) {
        throw new Exception('El archivo no existe después de moverlo: ' . $filepath);
    }
    
    logToFile("Archivo temporal guardado correctamente en: $filepath");
    logToFile("Tamaño archivo temporal: " . filesize($filepath) . " bytes");
    
    // Determinar tipo de media
    $mediaType = 'document';
    if (strpos($file['type'], 'image/') === 0) {
        $mediaType = 'image';
    } elseif (strpos($file['type'], 'video/') === 0) {
        $mediaType = 'video';
    } elseif (strpos($file['type'], 'audio/') === 0) {
        $mediaType = 'audio';
    }
    
    logToFile("Tipo de media detectado: $mediaType");
    
    // IMPORTANTE: Copiar a directorio permanente ANTES de enviar
    logToFile("Copiando archivo a ubicación permanente: $permanentPath");
    
    if (!@copy($filepath, $permanentPath)) {
        $error = error_get_last();
        throw new Exception('Error al copiar archivo a ubicación permanente: ' . ($error['message'] ?? 'desconocido'));
    }
    
    // Verificar que el archivo se copió correctamente
    if (!file_exists($permanentPath)) {
        throw new Exception('El archivo no se guardó en la ubicación permanente');
    }
    
    $fileSize = filesize($permanentPath);
    logToFile("Archivo copiado correctamente. Tamaño: $fileSize bytes");
    
    // Generar URL del media (relativa al público)
    $mediaUrl = 'media/uploads/' . $filename;
    logToFile("URL del media: $mediaUrl");
    
    // Enviar por WhatsApp
    logToFile("Enviando $mediaType por WhatsApp a $to");
    
    $result = null;
    switch ($mediaType) {
        case 'image':
            $result = $whatsapp->sendImage($to, $filepath, $caption);
            break;
        case 'video':
            $result = $whatsapp->sendVideo($to, $filepath, $caption);
            break;
        case 'audio':
            $result = $whatsapp->sendAudio($to, $filepath);
            break;
        case 'document':
            $result = $whatsapp->sendDocument($to, $filepath, $file['name']);
            break;
    }
    
    logToFile("Resultado del envío: " . json_encode($result));
    
    // Guardar en base de datos
    $insertId = $db->insert('mensajes_salientes', [
        'numero_destinatario' => str_replace(['@c.us', '@g.us'], '', $to),
        'mensaje' => $caption ?: "[$mediaType enviado]",
        'tipo' => $mediaType,
        'media_path' => $mediaUrl,
        'estado' => 'enviado',
        'fecha_envio' => date('Y-m-d H:i:s')
    ]);
    
    logToFile("Mensaje guardado en BD con ID: $insertId");
    
    // Registrar en logs
    $db->insert('logs', [
        'usuario_id' => $_SESSION['user_id'],
        'accion' => 'Enviar ' . $mediaType,
        'descripcion' => "Media a $to",
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
    
    // Eliminar archivo temporal (pero mantener el permanente)
    if (file_exists($filepath)) {
        @unlink($filepath);
        logToFile("Archivo temporal eliminado");
    }
    
    logToFile("=== ÉXITO: $mediaType enviado correctamente ===");
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Multimedia enviado',
        'type' => $mediaType,
        'mediaUrl' => $mediaUrl,
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    // Limpiar archivos en caso de error
    if (isset($filepath) && file_exists($filepath)) {
        @unlink($filepath);
    }
    if (isset($permanentPath) && file_exists($permanentPath)) {
        @unlink($permanentPath);
    }
    
    logToFile('=== ERROR: ' . $e->getMessage() . ' ===');
    logToFile('Stack trace: ' . $e->getTraceAsString());
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();