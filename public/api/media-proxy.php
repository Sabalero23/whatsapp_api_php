<?php
ini_set('session.cookie_path', '/');
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('No autorizado');
}

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die('Archivo no especificado');
}

// Limpiar la ruta
$file = ltrim($file, '/');

// media-proxy.php está en /public/api/
// Los archivos están en /media/ (un nivel arriba de /public/)
$basePath = dirname(dirname(__DIR__)); // Sube 2 niveles: /www/wwwroot/whatsapp.cellcomweb.com.ar

$mediaPath = $basePath . '/' . $file;
$realPath = realpath($mediaPath);

// Directorios permitidos
$allowedDirIncoming = realpath($basePath . '/media/incoming');
$allowedDirUploads = realpath($basePath . '/media/uploads');

// Debug (comentar después)
error_log("File param: $file");
error_log("BasePath: $basePath");
error_log("MediaPath: $mediaPath");
error_log("RealPath: " . ($realPath ?: 'NULL'));
error_log("AllowedDir Incoming: " . ($allowedDirIncoming ?: 'NULL'));
error_log("AllowedDir Uploads: " . ($allowedDirUploads ?: 'NULL'));

// Validar que el archivo está en uno de los directorios permitidos
$isInIncoming = $realPath && $allowedDirIncoming && strpos($realPath, $allowedDirIncoming) === 0;
$isInUploads = $realPath && $allowedDirUploads && strpos($realPath, $allowedDirUploads) === 0;

if (!$isInIncoming && !$isInUploads) {
    http_response_code(404);
    error_log("Seguridad: archivo fuera de directorios permitidos");
    die('Archivo no encontrado');
}

// Verificar que el archivo existe
if (!file_exists($realPath) || !is_file($realPath)) {
    http_response_code(404);
    error_log("Archivo no existe: $realPath");
    die('Archivo no encontrado');
}

// Determinar tipo MIME
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'mp3' => 'audio/mpeg',
    'ogg' => 'audio/ogg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Enviar headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=2592000');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

// Enviar archivo
readfile($realPath);
exit;