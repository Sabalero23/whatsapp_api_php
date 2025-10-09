<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('No autorizado');
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

while (ob_get_level()) {
    ob_end_clean();
}

$logDir = '/root/.pm2/logs/';

// Obtener log actual y los 2 últimos rotados
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$logFiles = [
    'whatsapp-out.log' => 150,                          // Actual
    "whatsapp-out__{$today}_00-00-00.log" => 100,      // Rotado hoy
    "whatsapp-out__{$yesterday}_00-00-00.log" => 50,   // Rotado ayer
    'whatsapp-error.log' => 50                          // Errores
];

$logsFound = false;

foreach ($logFiles as $filename => $lines) {
    $logPath = $logDir . $filename;
    
    if (file_exists($logPath) && is_readable($logPath)) {
        $filesize = filesize($logPath);
        
        if ($filesize > 0) {
            echo str_repeat("=", 80) . "\n";
            echo "📄 " . strtoupper($filename) . "\n";
            echo "    Tamaño: " . number_format($filesize) . " bytes | Últimas $lines líneas\n";
            echo str_repeat("=", 80) . "\n\n";
            
            $content = shell_exec("tail -$lines " . escapeshellarg($logPath) . " 2>&1");
            echo $content ?: "(sin contenido)\n";
            echo "\n\n";
            
            $logsFound = true;
        }
    }
}

if (!$logsFound) {
    echo "❌ No se encontraron logs de PM2\n\n";
    echo "Archivos disponibles en $logDir:\n";
    $files = scandir($logDir);
    foreach ($files as $file) {
        if (strpos($file, 'whatsapp') !== false) {
            $size = filesize($logDir . $file);
            echo "  - $file (" . number_format($size) . " bytes)\n";
        }
    }
}

exit;