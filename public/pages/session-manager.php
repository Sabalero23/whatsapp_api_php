<?php
// pages/session-manager.php - Gestión avanzada de sesión

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$messageType = '';
$logs = '';

// Token de API (debe coincidir con el del servidor Node.js)
// Cargar .env manualmente - buscar en múltiples ubicaciones
$apiToken = null;

// Posibles ubicaciones del .env
$possiblePaths = [
    __DIR__ . '/../../.env',
    __DIR__ . '/../.env',
    __DIR__ . '/.env',
    '/www/wwwroot/whatsapp.cellcomweb.com.ar/.env'
];

foreach ($possiblePaths as $envFile) {
    if (file_exists($envFile)) {
        error_log("DEBUG - Intentando leer .env desde: $envFile");
        $envContent = file_get_contents($envFile);
        $envLines = explode("\n", $envContent);
        
        foreach ($envLines as $line) {
            $line = trim($line);
            // Ignorar líneas vacías y comentarios
            if (empty($line) || strpos($line, '#') === 0) continue;
            
            // Buscar API_KEY
            if (strpos($line, 'API_KEY=') === 0) {
                $apiToken = trim(str_replace('API_KEY=', '', $line));
                // Remover comillas si existen
                $apiToken = trim($apiToken, '"\'');
                error_log("DEBUG - ✅ Token encontrado en $envFile: " . substr($apiToken, 0, 10) . "...");
                break 2; // Salir de ambos loops
            }
        }
    } else {
        error_log("DEBUG - Archivo no existe: $envFile");
    }
}

// Si no se encontró, intentar otras fuentes
if (empty($apiToken)) {
    error_log("DEBUG - .env no encontrado o sin API_KEY, intentando getenv()");
    $apiToken = getenv('API_KEY');
}

if (empty($apiToken)) {
    error_log("DEBUG - getenv() falló, intentando \$_ENV");
    $apiToken = $_ENV['API_KEY'] ?? null;
}

// Último recurso (no debería llegar aquí)
if (empty($apiToken)) {
    error_log("ERROR - ❌ No se pudo cargar API_KEY desde ninguna fuente");
    die('Error: API_KEY no configurado. Verifica tu archivo .env en /www/wwwroot/whatsapp.cellcomweb.com.ar/.env');
}

$apiBaseUrl = 'http://127.0.0.1:3000/api/session';

// Debug final
error_log("DEBUG - Token final a usar: " . substr($apiToken, 0, 10) . "...");

// Función helper para llamar a la API
function callSessionAPI($endpoint, $token, $baseUrl) {
    // Debug
    error_log("DEBUG - Llamando a: " . $baseUrl . $endpoint);
    error_log("DEBUG - Token enviado: " . substr($token, 0, 10) . "...");
    
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Debug
    error_log("DEBUG - HTTP Code: " . $httpCode);
    error_log("DEBUG - Response: " . substr($response, 0, 200));
    
    if ($response === false) {
        throw new Exception('Error de conexión con la API: ' . $curlError);
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode !== 200 || !$data['success']) {
        throw new Exception($data['error'] ?? 'Error desconocido');
    }
    
    return $data;
}

// Función para hacer request usando fsockopen (para obtener QR y status)
function apiRequestSocket($path, $apiKey) {
    $fp = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 5);
    if (!$fp) {
        error_log("ERROR - No se pudo conectar a Node.js: $errstr ($errno)");
        return null;
    }
    
    $request = "GET $path HTTP/1.1\r\n";
    $request .= "Host: 127.0.0.1:3000\r\n";
    $request .= "Authorization: Bearer $apiKey\r\n";
    $request .= "Connection: Close\r\n\r\n";
    
    fwrite($fp, $request);
    
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 1024);
    }
    fclose($fp);
    
    $parts = explode("\r\n\r\n", $response, 2);
    if (count($parts) < 2) {
        error_log("ERROR - Respuesta inválida de la API");
        return null;
    }
    
    $body = trim($parts[1]);
    $data = json_decode($body, true);
    
    error_log("DEBUG - API $path: " . substr($body, 0, 200));
    
    return $data;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'logout':
            try {
                $result = callSessionAPI('/logout', $apiToken, $apiBaseUrl);
                $message = $result['message'];
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error al cerrar sesión: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'clean_all':
            try {
                $result = callSessionAPI('/clean', $apiToken, $apiBaseUrl);
                $message = $result['message'];
                $messageType = 'success';
                $logs = $result['logs'] ?? '';
            } catch (Exception $e) {
                $message = 'Error en limpieza: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'restart':
            try {
                $result = callSessionAPI('/restart', $apiToken, $apiBaseUrl);
                $message = $result['message'];
                $messageType = 'success';
                $logs = $result['logs'] ?? '';
            } catch (Exception $e) {
                $message = 'Error al reiniciar: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
    }
}

// Obtener estado y QR usando fsockopen (más confiable)
$status = apiRequestSocket('/api/status', $apiToken);
$qrData = null;

$isReady = false;
if ($status) {
    $isReady = (
        (isset($status['isReady']) && $status['isReady'] === true) ||
        (isset($status['status']) && $status['status'] === 'ready')
    );
}

// Si no está conectado, obtener QR
if ($status && !$isReady) {
    $qrData = apiRequestSocket('/api/qr', $apiToken);
    if ($qrData) {
        error_log("DEBUG - QR obtenido: " . (isset($qrData['qr']) ? 'SÍ' : 'NO'));
    }
}

// Variable para compatibilidad con el resto del código
$qrCode = ($qrData && isset($qrData['qr'])) ? $qrData['qr'] : null;
$hasQR = !empty($qrCode);

$sessionExists = file_exists(__DIR__ . '/../../whatsapp-session');
?>

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
.session-manager {
    max-width: 900px;
    margin: 0 auto;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.card h3 {
    margin: 0 0 20px 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.btn-warning {
    background: #ffc107;
    color: #000;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.status-box {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #dee2e6;
}

.status-item:last-child {
    border-bottom: none;
}

.logs-container {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 20px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    max-height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.action-card {
    padding: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.action-card:hover {
    border-color: #007bff;
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.action-card i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.action-card h4 {
    margin: 10px 0 5px 0;
    color: #333;
}

.action-card p {
    font-size: 13px;
    color: #666;
    margin: 0;
}

/* Personalización de SweetAlert2 */
.swal2-popup {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.swal2-title {
    font-size: 1.5em;
    font-weight: 600;
}

.swal2-html-container {
    font-size: 1em;
    line-height: 1.6;
}
</style>

<div class="session-manager">
    <!-- QR Code si no está conectado -->
    <?php if (!$isReady && $qrCode): ?>
    <div class="card">
        <h3>
            <i class="fas fa-qrcode"></i>
            Escanea el código QR
        </h3>
        <div style="text-align: center; padding: 20px;">
            <img src="<?= htmlspecialchars($qrCode) ?>" alt="QR Code" style="max-width: 300px; border: 2px solid #e0e0e0; border-radius: 8px;">
            <p style="margin-top: 15px; color: #666;">
                Escanea este código con WhatsApp para conectar
            </p>
            <p style="font-size: 12px; color: #999;">
                El código se actualiza automáticamente cada 60 segundos
            </p>
        </div>
    </div>
    <?php elseif (!$isReady && !$qrCode): ?>
    <div class="card">
        <h3>
            <i class="fas fa-spinner fa-spin"></i>
            Generando código QR...
        </h3>
        <div style="text-align: center; padding: 20px;">
            <p>Por favor espera mientras se genera el código QR</p>
            <p style="font-size: 12px; color: #999;">
                La página se actualizará automáticamente
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Estado actual -->
    <div class="card">
        <h3>
            <i class="fas fa-info-circle"></i>
            Estado del Sistema
        </h3>
        <div class="status-box">
            <div class="status-item">
                <strong>WhatsApp:</strong>
                <span style="color: <?= $isReady ? '#28a745' : '#dc3545' ?>">
                    <i class="fas fa-circle"></i>
                    <?= $isReady ? 'Conectado' : 'Desconectado' ?>
                </span>
            </div>
            <div class="status-item">
                <strong>Servidor Node.js:</strong>
                <span id="nodeStatus">
                    <i class="fas fa-spinner fa-spin"></i> Verificando...
                </span>
            </div>
            <div class="status-item">
                <strong>Sesión guardada:</strong>
                <span><?= $sessionExists ? 'Sí' : 'No' ?></span>
            </div>
        </div>
    </div>
    
    <!-- Acciones -->
    <div class="card">
        <h3>
            <i class="fas fa-tools"></i>
            Gestión de Sesión
        </h3>
        
        <div class="action-grid">
            <!-- Cerrar sesión simple -->
            <div class="action-card" onclick="sessionAction('logout')">
                <i class="fas fa-sign-out-alt" style="color: #ffc107"></i>
                <h4>Cerrar Sesión</h4>
                <p>Desvincula WhatsApp pero mantiene datos</p>
            </div>
            
            <!-- Limpieza completa -->
            <div class="action-card" onclick="sessionAction('clean_all')">
                <i class="fas fa-trash-alt" style="color: #dc3545"></i>
                <h4>Limpieza Completa</h4>
                <p>Elimina todo para nuevo número</p>
            </div>
            
            <!-- Reiniciar servicio -->
            <div class="action-card" onclick="sessionAction('restart')">
                <i class="fas fa-sync-alt" style="color: #007bff"></i>
                <h4>Reiniciar Servicio</h4>
                <p>Reinicia Node.js sin borrar sesión</p>
            </div>
        </div>
    </div>
    
    <!-- Logs de ejecución de scripts -->
    <?php if ($logs): ?>
    <div class="card">
        <h3>
            <i class="fas fa-terminal"></i>
            Salida del Script
        </h3>
        <div class="logs-container"><?= htmlspecialchars($logs) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Esperar a que SweetAlert2 esté disponible
window.addEventListener('load', function() {
    
    // Configuración de SweetAlert2
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    // Mostrar mensaje de respuesta si existe
    <?php if ($message): ?>
    Swal.fire({
        icon: '<?= $messageType ?>',
        title: '<?= $messageType === 'success' ? '¡Éxito!' : 'Error' ?>',
        text: <?= json_encode($message, JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '<?= $messageType === 'success' ? '#28a745' : '#dc3545' ?>',
        <?php if ($messageType === 'success'): ?>
        timer: 3000,
        timerProgressBar: true
        <?php endif; ?>
    });
    <?php endif; ?>

    const sessionActions = {
        logout: {
            title: '¿Cerrar sesión de WhatsApp?',
            text: 'Se desconectará WhatsApp pero se mantendrán los datos. Podrás reconectar escaneando el QR nuevamente.',
            icon: 'warning',
            confirmButtonText: 'Sí, cerrar sesión',
            confirmButtonColor: '#ffc107'
        },
        clean_all: {
            title: '¿Eliminar TODO y cambiar de número?',
            html: '<strong>⚠️ ADVERTENCIA:</strong><br>Esto eliminará la sesión actual, caché y datos de Redis.<br><br>Deberás escanear un nuevo QR.',
            icon: 'error',
            confirmButtonText: 'Sí, eliminar todo',
            confirmButtonColor: '#dc3545',
            showDenyButton: true,
            denyButtonText: 'Cancelar',
            denyButtonColor: '#6c757d'
        },
        restart: {
            title: '¿Reiniciar el servicio?',
            text: 'El servicio Node.js se reiniciará. La sesión actual se mantendrá.',
            icon: 'question',
            confirmButtonText: 'Sí, reiniciar',
            confirmButtonColor: '#007bff'
        }
    };

    // Función global para las acciones de sesión
    window.sessionAction = function(action) {
        const config = sessionActions[action];
        
        Swal.fire({
            title: config.title,
            text: config.text,
            html: config.html,
            icon: config.icon,
            showCancelButton: !config.showDenyButton,
            showDenyButton: config.showDenyButton || false,
            confirmButtonText: config.confirmButtonText,
            confirmButtonColor: config.confirmButtonColor,
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#6c757d',
            denyButtonText: config.denyButtonText,
            denyButtonColor: config.denyButtonColor,
            reverseButtons: true,
            focusCancel: action === 'clean_all'
        }).then((result) => {
            if (result.isConfirmed) {
                executeSessionAction(action);
            }
        });
    };

    function executeSessionAction(action) {
        // Mostrar loading
        Swal.fire({
            title: 'Procesando...',
            html: 'Por favor espera mientras se ejecuta la acción',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="${action}">`;
        document.body.appendChild(form);
        form.submit();
    }

    // Verificar estado del servidor Node.js
    async function checkNodeServer() {
        const nodeStatusEl = document.getElementById('nodeStatus');
        if (!nodeStatusEl) return;
        
        const urls = [
            window.location.origin + '/api/health',
            'http://127.0.0.1:3000/api/health',
            'http://localhost:3000/api/health'
        ];
        
        for (const url of urls) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000);
                
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (response.ok) {
                    nodeStatusEl.innerHTML = '<span style="color: #28a745"><i class="fas fa-circle"></i> Online</span>';
                    console.log('✅ Node.js conectado en:', url);
                    return;
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.debug('Intento fallido:', url);
                }
            }
        }
        
        nodeStatusEl.innerHTML = `
            <span style="color: #dc3545">
                <i class="fas fa-circle"></i> Offline
            </span>
            <br>
            <small style="color: #666; font-size: 0.85em;">
                Ejecuta: <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">pm2 start whatsapp</code>
            </small>
        `;
    }

    // Ejecutar verificación
    checkNodeServer();

    // Auto-refresh cada 10 segundos
    setInterval(checkNodeServer, 10000);
    
    // Si no está conectado, recargar la página cada 60 segundos para obtener nuevo QR
    <?php if (!$isReady): ?>
    console.log('WhatsApp no conectado, auto-refresh activado');
    setTimeout(() => {
        console.log('Recargando página para actualizar QR...');
        window.location.reload();
    }, 60000); // 60 segundos
    <?php endif; ?>
    
});
</script>