<?php
/**
 * QR Connect - Gestión Unificada de WhatsApp
 * Ubicación: /www/wwwroot/whatsapp.cellcomweb.com.ar/public/pages/qr-connect.php
 */

// Variables de control
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$messageType = '';
$logs = '';

// =====================================================
// CARGAR API_KEY DESDE .ENV
// =====================================================
$API_KEY = null;
$possiblePaths = [
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
    '/www/wwwroot/whatsapp.cellcomweb.com.ar/.env'
];

foreach ($possiblePaths as $envFile) {
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $envLines = explode("\n", $envContent);
        
        foreach ($envLines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            
            if (strpos($line, 'API_KEY=') === 0) {
                $API_KEY = trim(str_replace('API_KEY=', '', $line));
                $API_KEY = trim($API_KEY, '"\'');
                error_log("✅ API_KEY encontrado: " . substr($API_KEY, 0, 10) . "...");
                break 2;
            }
        }
    }
}

if (empty($API_KEY)) {
    $API_KEY = getenv('API_KEY') ?: ($_ENV['API_KEY'] ?? null);
}

if (empty($API_KEY)) {
    error_log("❌ ERROR CRÍTICO: API_KEY no encontrado");
    die('<div style="padding: 20px; background: #ffebee; border: 2px solid #f44336; border-radius: 8px; margin: 20px;">
        <h3 style="color: #c62828; margin: 0 0 10px 0;">⚠️ Error de Configuración</h3>
        <p><strong>API_KEY no configurado</strong></p>
        <p>Por favor, verifica que el archivo <code>/www/wwwroot/whatsapp.cellcomweb.com.ar/.env</code> exista y contenga la línea:</p>
        <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">API_KEY=tu_clave_secreta_aqui</pre>
        </div>');
}

// =====================================================
// FUNCIONES DE API
// =====================================================

/**
 * Request HTTP usando fsockopen
 */
function apiRequest($path, $method = 'GET', $data = null) {
    global $API_KEY;
    
    error_log("🔵 API Request: $method $path");
    error_log("🔑 Usando API_KEY: " . substr($API_KEY, 0, 10) . "...");
    
    $fp = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 5);
    if (!$fp) {
        error_log("❌ fsockopen falló: [$errno] $errstr");
        return ['error' => "No se pudo conectar al servidor Node.js: $errstr"];
    }
    
    $body = '';
    if ($data && $method === 'POST') {
        $body = json_encode($data);
    }
    
    // Construir request HTTP/1.1 completo
    $request = "$method $path HTTP/1.1\r\n";
    $request .= "Host: 127.0.0.1:3000\r\n";
    $request .= "x-api-key: $API_KEY\r\n";  // Usar minúsculas para compatibilidad
    $request .= "Content-Type: application/json\r\n";
    $request .= "User-Agent: PHP-WhatsApp-Client/1.0\r\n";
    
    if ($body) {
        $request .= "Content-Length: " . strlen($body) . "\r\n";
    } else {
        $request .= "Content-Length: 0\r\n";
    }
    
    $request .= "Connection: Close\r\n";
    $request .= "\r\n";
    
    if ($body) {
        $request .= $body;
    }
    
    // Log del request completo (para debug)
    error_log("📤 Request HTTP completo:\n" . str_replace($API_KEY, substr($API_KEY, 0, 10) . '...', $request));
    
    fwrite($fp, $request);
    
    stream_set_timeout($fp, 5);
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 1024);
    }
    fclose($fp);
    
    error_log("📥 Response length: " . strlen($response));
    
    // Separar headers y body
    $parts = explode("\r\n\r\n", $response, 2);
    if (count($parts) < 2) {
        error_log("❌ Respuesta HTTP inválida");
        error_log("📄 Respuesta recibida: " . substr($response, 0, 500));
        return ['error' => 'Respuesta inválida del servidor'];
    }
    
    $headers = $parts[0];
    $body = trim($parts[1]);
    
    // Log de headers de respuesta
    error_log("📋 Response headers:\n" . substr($headers, 0, 200));
    error_log("📄 Response body: " . substr($body, 0, 200));
    
    // Verificar código de estado HTTP
    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers, $matches)) {
        $statusCode = (int)$matches[1];
        error_log("📊 HTTP Status Code: $statusCode");
        
        if ($statusCode === 401) {
            error_log("❌ Error de autenticación (401)");
        } elseif ($statusCode === 403) {
            error_log("❌ Acceso prohibido (403)");
        }
    }
    
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ JSON decode error: " . json_last_error_msg());
        error_log("📄 Body que falló: " . $body);
        return ['error' => 'Error decodificando respuesta JSON: ' . json_last_error_msg()];
    }
    
    return $decoded;
}

/**
 * Request a los endpoints de sesión
 */
function sessionRequest($endpoint) {
    return apiRequest("/api/session$endpoint", 'POST');
}

// =====================================================
// PROCESAR ACCIONES
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    try {
        error_log("🎬 Procesando acción: $action");
        
        switch ($action) {
            case 'logout':
                $result = sessionRequest('/logout');
                if ($result && !isset($result['error']) && isset($result['success']) && $result['success']) {
                    $message = 'Sesión cerrada correctamente';
                    $messageType = 'success';
                } else {
                    throw new Exception($result['error'] ?? 'Error desconocido al cerrar sesión');
                }
                break;
                
            case 'clean_all':
                $result = sessionRequest('/clean');
                if ($result && !isset($result['error']) && isset($result['success']) && $result['success']) {
                    $message = 'Limpieza completa iniciada. Recarga en 10 segundos.';
                    $messageType = 'success';
                    $logs = $result['logs'] ?? 'Procesando...';
                } else {
                    throw new Exception($result['error'] ?? 'Error desconocido en limpieza');
                }
                break;
                
            case 'restart':
                $result = sessionRequest('/restart');
                if ($result && !isset($result['error']) && isset($result['success']) && $result['success']) {
                    $message = 'Servicio reiniciándose. Recarga en 5 segundos.';
                    $messageType = 'success';
                    $logs = $result['logs'] ?? 'Reiniciando...';
                } else {
                    throw new Exception($result['error'] ?? 'Error desconocido al reiniciar');
                }
                break;
                
            default:
                throw new Exception('Acción no válida: ' . $action);
        }
    } catch (Exception $e) {
        error_log("❌ Error procesando acción: " . $e->getMessage());
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// =====================================================
// OBTENER ESTADO Y QR
// =====================================================

$status = apiRequest('/api/status');
$qrData = null;
$hasError = isset($status['error']);

error_log("📊 Status obtenido: " . json_encode($status));

// Determinar estado de conexión
$isConnected = false;
if ($status && !$hasError) {
    $isConnected = (
        (isset($status['isReady']) && $status['isReady'] === true) ||
        (isset($status['status']) && $status['status'] === 'ready')
    );
}

error_log("🔍 isConnected: " . ($isConnected ? 'SI' : 'NO'));

// Obtener QR si no está conectado
if (!$isConnected && !$hasError) {
    error_log("📱 Solicitando QR...");
    $qrData = apiRequest('/api/qr');
    error_log("📱 QR Response: " . json_encode($qrData));
}

// Verificar archivos de sesión
$sessionExists = file_exists('/www/wwwroot/whatsapp.cellcomweb.com.ar/whatsapp-session');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp - Gestión de Conexión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            transition: transform 0.3s, box-shadow 0.3s;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
        }

        .card h2 {
            margin: 0 0 24px 0;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 28px;
            font-weight: 700;
        }

        .card h3 {
            margin: 0 0 20px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 600;
        }

        /* QR Code Styles */
        .qr-section {
            text-align: center;
            padding: 20px;
        }

        .qr-image {
            max-width: 320px;
            width: 100%;
            margin: 30px auto;
            display: block;
            border: 5px solid #25D366;
            border-radius: 20px;
            padding: 25px;
            background: white;
            box-shadow: 0 8px 24px rgba(37, 211, 102, 0.3);
        }

        .qr-instructions {
            text-align: left;
            margin: 28px 0;
            padding: 28px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            border-left: 5px solid #25D366;
        }

        .qr-instructions ol {
            margin: 18px 0;
            padding-left: 30px;
        }

        .qr-instructions li {
            margin: 14px 0;
            line-height: 1.7;
            color: #444;
            font-size: 15px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 17px;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .status-badge.connected {
            background: linear-gradient(135deg, #dcf8c6 0%, #b8e994 100%);
            color: #075e54;
            border: 3px solid #25D366;
        }

        .status-badge.disconnected {
            background: linear-gradient(135deg, #ffe0e0 0%, #ffb3b3 100%);
            color: #c62828;
            border: 3px solid #f44336;
        }

        .status-badge.waiting {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
            border: 3px solid #ffc107;
        }

        /* Status Grid */
        .status-grid {
            display: grid;
            gap: 18px;
            margin-top: 24px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border-left: 5px solid #007bff;
            transition: all 0.3s;
        }

        .status-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .status-item strong {
            color: #333;
            font-weight: 700;
            font-size: 15px;
        }

        .status-value {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 15px;
        }

        /* Action Grid */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-top: 28px;
        }

        .action-card {
            padding: 28px;
            border: 3px solid #e0e0e0;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .action-card:hover {
            border-color: #007bff;
            background: linear-gradient(135deg, #f8f9ff 0%, #e7f0ff 100%);
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(0,123,255,0.2);
        }

        .action-card i {
            font-size: 56px;
            margin-bottom: 18px;
            display: block;
        }

        .action-card h4 {
            margin: 14px 0 10px 0;
            color: #1a1a1a;
            font-size: 20px;
            font-weight: 700;
        }

        .action-card p {
            font-size: 14px;
            color: #666;
            margin: 0;
            line-height: 1.6;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #000;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Alerts */
        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .alert i {
            font-size: 24px;
            margin-top: 2px;
        }

        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 5px solid #2196F3;
            color: #1565c0;
        }

        .alert-error {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-left: 5px solid #f44336;
            color: #c62828;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border-left: 5px solid #ffc107;
            color: #856404;
        }

        /* Spinner */
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #25D366;
            border-radius: 50%;
            width: 70px;
            height: 70px;
            animation: spin 1s linear infinite;
            margin: 30px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Logs Container */
        .logs-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 28px;
            border-radius: 15px;
            font-family: 'Courier New', Consolas, monospace;
            font-size: 14px;
            max-height: 450px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.7;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .card {
                padding: 24px;
                margin-bottom: 16px;
                border-radius: 16px;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                justify-content: center;
                margin: 10px 0;
            }

            .qr-image {
                max-width: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if ($hasError): ?>
            <!-- ERROR DE CONEXIÓN -->
            <div class="card">
                <h2>
                    <i class="fas fa-exclamation-triangle" style="color: #f44336;"></i>
                    Error de Conexión
                </h2>
                <div class="status-badge disconnected">
                    <i class="fas fa-times-circle"></i>
                    No Conectado
                </div>
                
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error:</strong><br>
                        <?= htmlspecialchars($status['error'] ?? 'Error desconocido') ?>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Solución:</strong>
                        <ul style="margin: 8px 0 0 20px; line-height: 1.8;">
                            <li>Verifica que Node.js esté corriendo: <code>pm2 status</code></li>
                            <li>Comprueba el puerto 3000: <code>netstat -tulpn | grep 3000</code></li>
                            <li>Revisa la API_KEY en: <code>/www/wwwroot/whatsapp.cellcomweb.com.ar/.env</code></li>
                            <li>Consulta los logs: <code>pm2 logs whatsapp --lines 50</code></li>
                        </ul>
                    </div>
                </div>
                
                <button onclick="location.reload()" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Reintentar Conexión
                </button>
            </div>
            
        <?php elseif ($isConnected): ?>
            <!-- CONECTADO -->
            <div class="card">
                <h2>
                    <i class="fas fa-check-circle" style="color: #4caf50;"></i>
                    WhatsApp Conectado
                </h2>
                
                <div class="qr-section">
                    <div class="status-badge connected">
                        <i class="fas fa-check-circle"></i>
                        Activo y Listo
                    </div>
                    
                    <p style="font-size: 19px; color: #666; margin: 20px 0; font-weight: 500;">
                        Tu WhatsApp está vinculado y funcionando correctamente
                    </p>
                    
                    <div style="margin: 28px 0;">
                        <a href="?page=dashboard" class="btn btn-success">
                            <i class="fas fa-home"></i> Ir al Dashboard
                        </a>
                        <button onclick="location.reload()" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Actualizar Estado
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- INFORMACIÓN DEL SISTEMA -->
            <div class="card">
                <h3>
                    <i class="fas fa-info-circle"></i>
                    Estado del Sistema
                </h3>
                
                <div class="status-grid">
                    <div class="status-item">
                        <strong>WhatsApp</strong>
                        <span class="status-value" style="color: #28a745;">
                            <i class="fas fa-circle"></i>
                            Conectado
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <strong>Sistema</strong>
                        <span class="status-value">
                            <?= htmlspecialchars($status['status'] ?? 'ready') ?>
                        </span>
                    </div>
                    
                    <?php if (isset($status['phoneNumber'])): ?>
                    <div class="status-item">
                        <strong>Número</strong>
                        <span class="status-value">
                            <?= htmlspecialchars($status['phoneNumber']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ACCIONES DISPONIBLES -->
            <div class="card">
                <h3>
                    <i class="fas fa-tools"></i>
                    Acciones Disponibles
                </h3>
                
                <div class="action-grid">
                    <div class="action-card" onclick="sessionAction('logout')">
                        <i class="fas fa-sign-out-alt" style="color: #ffc107;"></i>
                        <h4>Cerrar Sesión</h4>
                        <p>Desconectar WhatsApp pero mantener datos locales</p>
                    </div>
                    
                    <div class="action-card" onclick="sessionAction('restart')">
                        <i class="fas fa-sync-alt" style="color: #007bff;"></i>
                        <h4>Reiniciar Servicio</h4>
                        <p>Reiniciar Node.js manteniendo la sesión</p>
                    </div>
                    
                    <div class="action-card" onclick="sessionAction('clean_all')">
                        <i class="fas fa-trash-alt" style="color: #f44336;"></i>
                        <h4>Limpieza Completa</h4>
                        <p>Eliminar todo y empezar desde cero</p>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- ESPERANDO QR -->
            <div class="card">
                <h2>
                    <i class="fas fa-qrcode" style="color: #25D366;"></i>
                    Conectar WhatsApp
                </h2>
                
                <div class="qr-section">
                    <div class="status-badge waiting">
                        <i class="fas fa-hourglass-half"></i>
                        Esperando Conexión
                    </div>
                    
                    <?php if ($qrData && isset($qrData['qr']) && !isset($qrData['error'])): ?>
                        <img src="<?= htmlspecialchars($qrData['qr']) ?>" alt="Código QR" class="qr-image" id="qr-image">
                        
                        <div class="qr-instructions">
                            <h4 style="margin-bottom: 16px; color: #075e54; font-size: 18px;">
                                <i class="fas fa-mobile-alt"></i> Cómo conectar:
                            </h4>
                            <ol>
                                <li>Abre <strong>WhatsApp</strong> en tu teléfono</li>
                                <li>Toca <strong>Menú</strong> (<i class="fas fa-ellipsis-v"></i>) o <strong>Configuración</strong> (<i class="fas fa-cog"></i>)</li>
                                <li>Selecciona <strong>Dispositivos vinculados</strong></li>
                                <li>Toca <strong>Vincular un dispositivo</strong></li>
                                <li>Apunta tu teléfono a esta pantalla para escanear el código</li>
                            </ol>
                        </div>
                        
                        <div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-radius: 10px;">
                            <p style="margin: 0; color: #1565c0;">
                                <i class="fas fa-info-circle"></i> 
                                <strong>El código QR se actualiza automáticamente cada 10 segundos</strong>
                            </p>
                        </div>
                        
                        <button onclick="actualizarQR()" class="btn btn-primary" id="btn-actualizar">
                            <i class="fas fa-sync-alt"></i> Actualizar Ahora
                        </button>
                        
                    <?php elseif ($qrData && isset($qrData['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <strong>Error al obtener QR:</strong><br>
                                <?= htmlspecialchars($qrData['error']) ?>
                            </div>
                        </div>
                        <button onclick="location.reload()" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Reintentar
                        </button>
                        
                    <?php else: ?>
                        <div class="spinner"></div>
                        <p style="margin-top: 20px; color: #666;">Generando código QR...</p>
                        <script>
                            console.log('⏳ QR no disponible, recargando en 3 segundos...');
                            setTimeout(() => location.reload(), 3000);
                        </script>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ACCIONES DE RECUPERACIÓN -->
            <?php if ($sessionExists): ?>
            <div class="card">
                <h3>
                    <i class="fas fa-wrench"></i>
                    Opciones de Recuperación
                </h3>
                
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        Se detectaron archivos de sesión. Si tienes problemas conectando, prueba estas opciones:
                    </div>
                </div>
                
                <div class="action-grid">
                    <div class="action-card" onclick="sessionAction('restart')">
                        <i class="fas fa-sync-alt" style="color: #007bff;"></i>
                        <h4>Reiniciar Servicio</h4>
                        <p>Reinicia el servicio manteniendo la sesión</p>
                    </div>
                    
                    <div class="action-card" onclick="sessionAction('clean_all')">
                        <i class="fas fa-trash-alt" style="color: #f44336;"></i>
                        <h4>Limpieza Total</h4>
                        <p>Elimina todo y genera un nuevo QR</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <!-- LOGS (si existen) -->
        <?php if ($logs): ?>
        <div class="card">
            <h3>
                <i class="fas fa-terminal"></i>
                Salida del Proceso
            </h3>
            <div class="logs-container"><?= htmlspecialchars($logs) ?></div>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        console.log('🚀 QR Connect cargado');
        
        // Configuración de alertas
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        // Mostrar mensaje si existe
        <?php if ($message): ?>
        Swal.fire({
            icon: '<?= $messageType === 'success' ? 'success' : 'error' ?>',
            title: '<?= $messageType === 'success' ? '¡Éxito!' : 'Error' ?>',
            text: <?= json_encode($message) ?>,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '<?= $messageType === 'success' ? '#28a745' : '#f44336' ?>'
        });
        <?php endif; ?>

        // =====================================================
        // AUTO-ACTUALIZACIÓN DEL QR CODE
        // =====================================================
        
        let qrUpdateInterval = null;
        let updateInProgress = false;
        
        async function actualizarQR() {
            if (updateInProgress) {
                console.log('⏳ Actualización ya en progreso, saltando...');
                return;
            }
            
            updateInProgress = true;
            const btn = document.getElementById('btn-actualizar');
            const qrImg = document.getElementById('qr-image');
            
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            }
            
            try {
                console.log('🔄 Solicitando nuevo QR...');
                
                // Hacer request directo a la API
                const response = await fetch('/api/session/qr', {
                    method: 'GET',
                    headers: {
                        'X-API-Key': '<?= $API_KEY ?>'
                    }
                });
                
                const data = await response.json();
                console.log('📥 Respuesta recibida:', data);
                
                if (data.success && data.qr) {
                    console.log('✅ Nuevo QR recibido');
                    
                    // Actualizar imagen del QR
                    if (qrImg) {
                        qrImg.src = data.qr;
                        console.log('🖼️ Imagen QR actualizada');
                    }
                    
                    Toast.fire({
                        icon: 'success',
                        title: 'QR actualizado'
                    });
                    
                } else if (data.connected) {
                    console.log('✅ Ya conectado, redirigiendo...');
                    Toast.fire({
                        icon: 'success',
                        title: '¡Conectado!'
                    });
                    setTimeout(() => location.reload(), 1500);
                    
                } else {
                    console.log('⚠️ QR no disponible:', data.error);
                    
                    if (data.initializing) {
                        console.log('⏳ Sistema inicializándose...');
                    } else {
                        Toast.fire({
                            icon: 'warning',
                            title: 'QR no disponible aún'
                        });
                    }
                }
                
            } catch (error) {
                console.error('❌ Error actualizando QR:', error);
                Toast.fire({
                    icon: 'error',
                    title: 'Error al actualizar'
                });
            } finally {
                updateInProgress = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualizar Ahora';
                }
            }
        }
        
        // Verificar si estamos en la página de QR
        const hasQRImage = document.getElementById('qr-image') !== null;
        
        if (hasQRImage) {
            console.log('📱 Página de QR detectada, iniciando auto-actualización cada 10 segundos');
            
            // Actualizar cada 10 segundos
            qrUpdateInterval = setInterval(actualizarQR, 10000);
            
            // También verificar estado general cada 5 segundos
            setInterval(async () => {
                try {
                    const response = await fetch('/api/status', {
                        headers: { 'X-API-Key': '<?= $API_KEY ?>' }
                    });
                    const data = await response.json();
                    
                    if (data.isReady || data.status === 'ready') {
                        console.log('✅ Cliente conectado, recargando página...');
                        clearInterval(qrUpdateInterval);
                        location.reload();
                    }
                } catch (error) {
                    console.error('Error verificando estado:', error);
                }
            }, 5000);
        }

        // =====================================================
        // DEFINIR ACCIONES
        // =====================================================

        const actions = {
            logout: {
                title: '¿Cerrar sesión de WhatsApp?',
                text: 'Se desconectará WhatsApp pero se mantendrán los datos locales. Podrás reconectar escaneando el QR nuevamente.',
                icon: 'warning',
                confirmText: 'Sí, cerrar sesión',
                color: '#ffc107'
            },
            clean_all: {
                title: '⚠️ ¿Eliminar TODO?',
                html: '<div style="text-align: left;"><strong>ADVERTENCIA CRÍTICA:</strong><br><br>Esto eliminará:<br>• Sesión actual de WhatsApp<br>• Caché del sistema<br>• Todos los datos de Redis<br>• Configuración de vinculación<br><br><strong style="color: #f44336;">Deberás escanear un nuevo QR desde cero.</strong></div>',
                icon: 'error',
                confirmText: 'Sí, eliminar todo',
                color: '#f44336'
            },
            restart: {
                title: '¿Reiniciar el servicio Node.js?',
                text: 'El servicio se reiniciará completamente. La sesión actual de WhatsApp se mantendrá intacta.',
                icon: 'question',
                confirmText: 'Sí, reiniciar',
                color: '#007bff'
            }
        };

        // Función para ejecutar acciones
        function sessionAction(action) {
            const config = actions[action];
            
            console.log('🎬 Iniciando acción:', action);
            
            Swal.fire({
                title: config.title,
                text: config.text,
                html: config.html,
                icon: config.icon,
                showCancelButton: true,
                confirmButtonText: config.confirmText,
                confirmButtonColor: config.color,
                cancelButtonText: 'Cancelar',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusCancel: action === 'clean_all',
                customClass: {
                    popup: 'animated-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    executeAction(action);
                } else {
                    console.log('❌ Acción cancelada por el usuario');
                }
            });
        }

        // Ejecutar acción
        function executeAction(action) {
            console.log('⚙️ Ejecutando acción:', action);
            
            Swal.fire({
                title: 'Procesando...',
                html: 'Por favor espera mientras se ejecuta la operación',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `<input type="hidden" name="action" value="${action}">`;
            document.body.appendChild(form);
            
            console.log('📤 Enviando formulario...');
            form.submit();
        }

        // Prevenir doble envío de formularios
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('button[type="submit"]');
                if (btn && btn.disabled) {
                    console.warn('⚠️ Formulario ya enviado, previniendo doble submit');
                    e.preventDefault();
                    return false;
                }
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                }
            });
        });

        // Log de estado actual
        console.log('📊 Estado actual:', {
            hasError: <?= $hasError ? 'true' : 'false' ?>,
            isConnected: <?= $isConnected ? 'true' : 'false' ?>,
            hasQR: <?= ($qrData && isset($qrData['qr']) && !isset($qrData['error'])) ? 'true' : 'false' ?>,
            sessionExists: <?= $sessionExists ? 'true' : 'false' ?>
        });
    </script>
    
    <style>
        .animated-popup {
            animation: zoomIn 0.3s ease-out;
        }
        
        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</body>
</html>