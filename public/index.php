<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/WhatsAppClient.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$auth = Auth::getInstance();

// Obtener usuario con rol
$user = $db->fetch("
    SELECT u.*, r.nombre as rol_nombre, r.id as rol_id
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
    WHERE u.id = ?
", [$_SESSION['user_id']]);

if (!$user || !$user['activo']) {
    session_destroy();
    header('Location: login.php?error=inactive');
    exit;
}

// Actualizar último acceso
$db->update('usuarios', ['ultimo_acceso' => date('Y-m-d H:i:s')], 'id = ?', [$_SESSION['user_id']]);

// Cargar configuración
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

// WhatsApp Client
$whatsapp = new WhatsAppClient(
    'http://127.0.0.1:3000',
    $env['API_KEY'],
    [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => $env['REDIS_PASSWORD']
    ]
);

// Obtener estadísticas
$isReady = $whatsapp->isReady();
$stats = [
    'mensajes_hoy' => 0,
    'mensajes_recibidos_hoy' => 0,
    'chats_no_leidos' => 0,
    'total_contactos' => 0,
    'difusiones_activas' => 0,
    'mensajes_pendientes' => 0
];

try {
    $result = $db->fetch("SELECT COUNT(*) as total FROM mensajes_salientes WHERE DATE(fecha_creacion) = CURDATE()");
    $stats['mensajes_hoy'] = $result['total'] ?? 0;
    
    $result = $db->fetch("SELECT COUNT(*) as total FROM mensajes_entrantes WHERE DATE(fecha_recepcion) = CURDATE()");
    $stats['mensajes_recibidos_hoy'] = $result['total'] ?? 0;
    
    $result = $db->fetch("SELECT COUNT(*) as total FROM contactos");
    $stats['total_contactos'] = $result['total'] ?? 0;
    
    $result = $db->fetch("SELECT COUNT(*) as total FROM difusiones WHERE estado IN ('programada', 'enviando')");
    $stats['difusiones_activas'] = $result['total'] ?? 0;
    
    $result = $db->fetch("SELECT COUNT(*) as total FROM mensajes_salientes WHERE estado = 'pendiente'");
    $stats['mensajes_pendientes'] = $result['total'] ?? 0;
} catch (Exception $e) {
    error_log("Error en estadísticas: " . $e->getMessage());
}

// Obtener página solicitada
$page = $_GET['page'] ?? 'dashboard';

// SISTEMA DE PERMISOS: Verificar si el usuario puede ver la página
if (!$auth->canViewPage($page)) {
    // Si no tiene permiso, redirigir a dashboard con mensaje
    if ($page !== 'dashboard') {
        $_SESSION['error_message'] = 'No tienes permiso para acceder a esa página';
        header('Location: index.php?page=dashboard');
        exit;
    }
}

// Obtener páginas permitidas para el sidebar
$allowedPages = $auth->getAllowedPages();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestión WhatsApp Business - Cellcom Technology">
    <meta name="theme-color" content="#25D366">
    <title>WhatsApp Dashboard - Cellcom Technology</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/dashboard-compact.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <style>
/* Estilos del sistema (mantener los del original) */
.sidebar-footer {
    padding: 10px;
    border-top: 1px solid var(--border);
    position: relative;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-info:hover {
    background: rgba(37, 211, 102, 0.1);
}

/* Mensaje de error de permisos */
.permission-error {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.permission-error i {
    font-size: 24px;
}
/* ============================================
   ESTILOS DEL MENÚ DE USUARIO Y MODAL
   ============================================ */

/* Menú desplegable de usuario */
.user-info i.fa-user-circle {
    font-size: 1.3em;
    color: var(--primary);
    filter: drop-shadow(0 2px 4px rgba(37, 211, 102, 0.3));
}

.user-info span {
    font-size: 0.9em;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
}

.user-info small {
    font-size: 0.75em !important;
    color: rgba(255, 255, 255, 0.6) !important;
}

.user-info #userMenuIcon {
    font-size: 0.8em;
    color: rgba(255, 255, 255, 0.6);
    transition: transform 0.3s ease;
}

.user-info.active #userMenuIcon {
    transform: rotate(180deg);
}

/* Dropdown de usuario mejorado */
.user-dropdown {
    position: absolute;
    bottom: 100%;
    left: 12px;
    right: 12px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    margin-bottom: 8px;
    z-index: 1000;
}

.user-dropdown.active {
    max-height: 200px;
    opacity: 1;
}

.user-dropdown a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.9em;
    font-weight: 500;
}

.user-dropdown a:hover {
    background: linear-gradient(90deg, rgba(37, 211, 102, 0.1) 0%, transparent 100%);
    padding-left: 20px;
}

.user-dropdown a i {
    width: 20px;
    color: var(--primary);
    text-align: center;
}

.user-dropdown a:last-child {
    border-top: 1px solid var(--border);
    color: var(--danger);
}

.user-dropdown a:last-child i {
    color: var(--danger);
}

.user-dropdown a:last-child:hover {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1) 0%, transparent 100%);
}

/* Modal de perfil */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.modal-overlay.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-profile {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes slideUp {
    from {
        transform: translateY(50px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

.modal-profile .modal-header {
    padding: 24px 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
}

.modal-profile .modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.3em;
    font-weight: 700;
}

.modal-profile .modal-header h3 i {
    font-size: 1.2em;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.modal-profile .btn-close {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    font-size: 1.5em;
    color: white;
    cursor: pointer;
    padding: 0;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-profile .btn-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

.modal-profile .modal-body {
    padding: 28px;
    overflow-y: auto;
    flex: 1;
}

/* Scrollbar personalizado */
.modal-profile .modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-profile .modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.modal-profile .modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

.profile-section {
    margin-bottom: 32px;
    padding-bottom: 28px;
    border-bottom: 2px solid #f3f4f6;
}

.profile-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
}

.profile-section h4 {
    margin: 0 0 20px 0;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.15em;
    font-weight: 700;
}

.profile-section h4 i {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(37, 211, 102, 0.1);
    border-radius: 8px;
}

.modal-profile .modal-footer {
    padding: 18px 28px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #f9fafb;
}

/* Mejorar inputs del formulario */
.profile-section .form-control {
    transition: all 0.3s ease;
}

.profile-section .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
}

.profile-section .form-control[readonly] {
    background: #f9fafb;
    color: var(--gray);
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-profile {
        width: 95%;
        max-height: 95vh;
        border-radius: 12px;
    }
    
    .modal-profile .modal-header {
        padding: 20px;
    }
    
    .modal-profile .modal-header h3 {
        font-size: 1.1em;
    }
    
    .modal-profile .modal-body {
        padding: 20px 16px;
    }
    
    .modal-profile .modal-footer {
        padding: 16px;
        flex-direction: column;
    }
    
    .modal-profile .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
    
    .profile-section {
        margin-bottom: 24px;
        padding-bottom: 20px;
    }
}

/* Animación para campos inválidos */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.form-control.error {
    border-color: var(--danger);
    animation: shake 0.3s;
}

/* Loading state para botones */
.btn.loading {
    position: relative;
    color: transparent;
    pointer-events: none;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
</head>
<body>
    <div class="dashboard-container">
        
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fab fa-whatsapp"></i>
                <h2>WhatsApp Admin</h2>
            </div>
            
            <nav class="sidebar-nav">
                <?php
                // Definir elementos del menú con sus permisos
                $menuItems = [
                    'dashboard' => ['icon' => 'fa-home', 'label' => 'Dashboard'],
                    'chats' => ['icon' => 'fa-comments', 'label' => 'Chats', 'badge' => $stats['chats_no_leidos']],
                    'status' => ['icon' => 'fa-circle-notch', 'label' => 'Estados'],
                    'contacts' => ['icon' => 'fa-address-book', 'label' => 'Contactos'],
                    'groups' => ['icon' => 'fa-users', 'label' => 'Grupos'],
                    'broadcast' => ['icon' => 'fa-bullhorn', 'label' => 'Difusión', 'badge' => $stats['difusiones_activas'], 'badge_class' => 'badge-warning'],
                    'templates' => ['icon' => 'fa-file-alt', 'label' => 'Plantillas'],
                    'auto-reply' => ['icon' => 'fa-robot', 'label' => 'Respuestas Auto'],
                    'stats' => ['icon' => 'fa-chart-line', 'label' => 'Estadísticas'],
                    'settings' => ['icon' => 'fa-cog', 'label' => 'Configuración'],
                    'users' => ['icon' => 'fa-users-cog', 'label' => 'Usuarios'],
                    'qr-connect' => ['icon' => 'fa-qrcode', 'label' => 'Conectar API']
                ];
                
                // Mostrar solo páginas permitidas
                foreach ($menuItems as $pageKey => $item):
                    if (in_array($pageKey, $allowedPages)):
                ?>
                    <a href="?page=<?= $pageKey ?>" class="<?= $page === $pageKey ? 'active' : '' ?>">
                        <i class="fas <?= $item['icon'] ?>"></i> 
                        <span><?= $item['label'] ?></span>
                        <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                            <span class="badge <?= $item['badge_class'] ?? '' ?>"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php
                    endif;
                endforeach;
                ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info" id="userMenuTrigger">
                    <i class="fas fa-user-circle"></i>
                    <div style="flex: 1;">
                        <span><?= htmlspecialchars($user['nombre']) ?></span>
                        <small style="display: block; font-size: 0.8em; color: var(--gray);">
                            <?= htmlspecialchars($user['rol_nombre'] ?? 'Sin rol') ?>
                        </small>
                    </div>
                    <i class="fas fa-chevron-up" id="userMenuIcon"></i>
                </div>
                
                <div class="user-dropdown" id="userDropdown">
                    <a href="#" onclick="openProfileModal(); return false;">
                        <i class="fas fa-user-edit"></i> Mi Perfil
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <h1>
                    <?php
                    $titles = [
                        'dashboard' => 'Dashboard',
                        'chats' => 'Chats',
                        'status' => 'Estados',
                        'contacts' => 'Contactos',
                        'groups' => 'Grupos',
                        'broadcast' => 'Difusión',
                        'templates' => 'Plantillas',
                        'auto-reply' => 'Respuestas Automáticas',
                        'stats' => 'Estadísticas',
                        'settings' => 'Configuración',
                        'users' => 'Usuarios',
                        'qr-connect' => 'Conectar API'
                    ];
                    echo $titles[$page] ?? 'Dashboard';
                    ?>
                </h1>
                
                <div class="header-actions">
                    <div class="status-indicator <?= $isReady ? 'online' : 'offline' ?>">
                        <span class="dot"></span>
                        <?= $isReady ? 'Conectado' : 'Desconectado' ?>
                    </div>
                    
                    <?php if (!$isReady && in_array('qr-connect', $allowedPages)): ?>
                        <a href="index.php?page=qr-connect" class="btn btn-primary">
                            <i class="fas fa-qrcode"></i> Conectar WhatsApp
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-icon" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content-body">
                <?php if (isset($_SESSION['error_message'])): ?>
    <div class="permission-error">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
                <?php
                // Cargar página correspondiente
                switch ($page) {
                    case 'chats':
                        include 'pages/chats.php';
                        break;
                    case 'status':
                        include 'pages/status.php';
                        break;
                    case 'contacts':
                        include 'pages/contacts.php';
                        break;
                    case 'groups':
                        include 'pages/groups.php';
                        break;
                    case 'broadcast':
                        include 'pages/broadcast.php';
                        break;
                    case 'templates':
                        include 'pages/templates.php';
                        break;
                    case 'auto-reply':
                        include 'pages/auto-reply.php';
                        break;
                    case 'stats':
                        include 'pages/stats.php';
                        break;
                    case 'settings':
                        include 'pages/settings.php';
                        break;
                    case 'users':
                        include 'pages/users.php';
                        break;
                    case 'qr-connect':
                        include 'pages/qr-connect.php';
                        break;
                    default:
                        // Dashboard principal mejorado
                        
                        // Obtener chats no leídos desde WhatsApp API
                        $unreadChatsCount = 0;
                        try {
                            $chatsData = $whatsapp->getChats(100);
                            if (isset($chatsData['chats']) && is_array($chatsData['chats'])) {
                                foreach ($chatsData['chats'] as $chat) {
                                    if (isset($chat['unreadCount']) && $chat['unreadCount'] > 0) {
                                        $unreadChatsCount += $chat['unreadCount'];
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error obteniendo chats: " . $e->getMessage());
                        }
                        $stats['chats_no_leidos'] = $unreadChatsCount;
                        ?>
                        
                        <style>
                        .stats-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                            gap: 20px;
                            margin-bottom: 30px;
                        }

                        .stat-card {
                            background: white;
                            padding: 24px;
                            border-radius: 16px;
                            display: flex;
                            gap: 20px;
                            align-items: center;
                            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
                            transition: all 0.3s ease;
                            position: relative;
                            overflow: hidden;
                        }

                        .stat-card::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 4px;
                            background: linear-gradient(90deg, var(--card-color, #25D366), transparent);
                            opacity: 0;
                            transition: opacity 0.3s;
                        }

                        .stat-card:hover {
                            transform: translateY(-8px);
                            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
                        }

                        .stat-card:hover::before {
                            opacity: 1;
                        }

                        .stat-icon {
                            width: 64px;
                            height: 64px;
                            border-radius: 16px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 28px;
                            color: white;
                            position: relative;
                            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
                        }

                        .stat-icon::after {
                            content: '';
                            position: absolute;
                            inset: -2px;
                            border-radius: 16px;
                            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3));
                            opacity: 0;
                            transition: opacity 0.3s;
                        }

                        .stat-card:hover .stat-icon::after {
                            opacity: 1;
                        }

                        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                        .stat-icon.green { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); }
                        .stat-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
                        .stat-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

                        .stat-content h3 {
                            font-size: 32px;
                            font-weight: 800;
                            color: #1f2937;
                            margin-bottom: 4px;
                            line-height: 1;
                            letter-spacing: -1px;
                        }

                        .stat-content p {
                            color: #6b7280;
                            font-size: 14px;
                            font-weight: 500;
                            margin: 0;
                        }

                        .stat-trend {
                            display: flex;
                            align-items: center;
                            gap: 4px;
                            font-size: 12px;
                            margin-top: 8px;
                            font-weight: 600;
                        }

                        .stat-trend.up { color: #10b981; }
                        .stat-trend.down { color: #ef4444; }

                        .section-row {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
                            gap: 24px;
                            margin-bottom: 30px;
                        }

                        .card {
                            background: white;
                            border-radius: 16px;
                            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
                            overflow: hidden;
                            transition: all 0.3s ease;
                        }

                        .card:hover {
                            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
                        }

                        .card-header {
                            padding: 20px 24px;
                            border-bottom: 1px solid #f3f4f6;
                            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
                        }

                        .card-header h3 {
                            font-size: 18px;
                            font-weight: 700;
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            color: #1f2937;
                        }

                        .card-header h3 i {
                            color: #25D366;
                        }

                        .message-item, .activity-item {
                            padding: 16px;
                            border-radius: 12px;
                            transition: all 0.2s;
                        }

                        .message-item:hover {
                            background: #f9fafb;
                            transform: translateX(4px);
                        }

                        .activity-item {
                            border-left: 3px solid #25D366;
                            background: #f9fafb;
                        }

                        .activity-item:hover {
                            background: #f3f4f6;
                            transform: translateX(4px);
                        }

                        @media (max-width: 768px) {
                            .section-row {
                                grid-template-columns: 1fr;
                            }
                        }
                        </style>

                        <!-- Stats Cards -->
                        <div class="stats-grid">
                            <div class="stat-card" style="--card-color: #667eea;">
                                <div class="stat-icon blue">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= number_format($stats['mensajes_hoy']) ?></h3>
                                    <p>Mensajes Enviados Hoy</p>
                                    <?php 
                                    $yesterdaySent = $db->fetch("SELECT COUNT(*) as total FROM mensajes_salientes WHERE DATE(fecha_creacion) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['total'] ?? 0;
                                    $trendSent = $stats['mensajes_hoy'] - $yesterdaySent;
                                    if ($trendSent != 0): ?>
                                        <div class="stat-trend <?= $trendSent > 0 ? 'up' : 'down' ?>">
                                            <i class="fas fa-arrow-<?= $trendSent > 0 ? 'up' : 'down' ?>"></i>
                                            <?= abs($trendSent) ?> vs ayer
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="stat-card" style="--card-color: #25D366;">
                                <div class="stat-icon green">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= number_format($stats['mensajes_recibidos_hoy']) ?></h3>
                                    <p>Mensajes Recibidos Hoy</p>
                                    <?php 
                                    $yesterdayReceived = $db->fetch("SELECT COUNT(*) as total FROM mensajes_entrantes WHERE DATE(fecha_recepcion) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['total'] ?? 0;
                                    $trendReceived = $stats['mensajes_recibidos_hoy'] - $yesterdayReceived;
                                    if ($trendReceived != 0): ?>
                                        <div class="stat-trend <?= $trendReceived > 0 ? 'up' : 'down' ?>">
                                            <i class="fas fa-arrow-<?= $trendReceived > 0 ? 'up' : 'down' ?>"></i>
                                            <?= abs($trendReceived) ?> vs ayer
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="stat-card" style="--card-color: #f5576c;">
                                <div class="stat-icon orange">
                                    <i class="fas fa-comment-dots"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= number_format($stats['chats_no_leidos']) ?></h3>
                                    <p>Mensajes Sin Leer</p>
                                    <?php if ($stats['chats_no_leidos'] > 0): ?>
                                        <div class="stat-trend up">
                                            <i class="fas fa-bell"></i>
                                            Requiere atención
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="stat-card" style="--card-color: #4facfe;">
                                <div class="stat-icon purple">
                                    <i class="fas fa-address-book"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= number_format($stats['total_contactos']) ?></h3>
                                    <p>Total Contactos</p>
                                    <div class="stat-trend">
                                        <i class="fas fa-database"></i>
                                        En base de datos
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mensajes y Actividad -->
                        <div class="section-row">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-inbox"></i> Mensajes Recientes</h3>
                                    <a href="?page=chats" class="btn btn-sm btn-primary">Ver todos</a>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $mensajes = $db->fetchAll("SELECT * FROM mensajes_entrantes ORDER BY fecha_recepcion DESC LIMIT 8");
                                    if (empty($mensajes)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 12px;"></i>
                                            <p style="color: #6b7280;">No hay mensajes recientes</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="messages-list">
                                            <?php foreach ($mensajes as $msg): ?>
                                                <div class="message-item">
                                                    <div class="message-avatar"><i class="fas fa-user"></i></div>
                                                    <div class="message-content">
                                                        <div class="message-header">
                                                            <strong><?= htmlspecialchars($msg['numero_remitente']) ?></strong>
                                                            <span class="time"><?= date('H:i', strtotime($msg['fecha_recepcion'])) ?></span>
                                                        </div>
                                                        <p><?= htmlspecialchars(substr($msg['mensaje'], 0, 80)) ?><?= strlen($msg['mensaje']) > 80 ? '...' : '' ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-clock"></i> Actividad Reciente</h3>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $actividad = $db->fetchAll("SELECT * FROM logs ORDER BY fecha DESC LIMIT 8");
                                    if (empty($actividad)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-history" style="font-size: 48px; color: #d1d5db; margin-bottom: 12px;"></i>
                                            <p style="color: #6b7280;">No hay actividad reciente</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="activity-list">
                                            <?php foreach ($actividad as $log): ?>
                                                <div class="activity-item">
                                                    <i class="fas fa-circle"></i>
                                                    <div>
                                                        <strong><?= htmlspecialchars($log['accion']) ?></strong>
                                                        <p><?= htmlspecialchars($log['descripcion']) ?></p>
                                                        <span class="time">
                                                            <i class="far fa-clock"></i>
                                                            <?= date('d/m/Y H:i', strtotime($log['fecha'])) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Estadísticas de Mensajes - Últimos 7 Días</h3>
                                <button class="btn btn-sm btn-icon" onclick="refreshChart()" title="Actualizar">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <canvas id="messagesChart" style="min-height: 300px;"></canvas>
                            </div>
                        </div>

                        <script>
document.addEventListener('DOMContentLoaded', function() {
    loadMessagesChart();
});

async function loadMessagesChart() {
    try {
        const response = await fetch('api/dashboard-stats.php');
        const data = await response.json();
        
        if (!data.success) {
            console.error('Error cargando datos:', data.error);
            createEmptyChart();
            return;
        }
        
        const ctx = document.getElementById('messagesChart');
        if (!ctx) {
            console.error('Canvas no encontrado');
            return;
        }
        
        // Destruir gráfico anterior si existe
        if (window.messagesChartInstance) {
            window.messagesChartInstance.destroy();
            window.messagesChartInstance = null;
        }
        
        window.messagesChartInstance = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Enviados',
                    data: data.sent,
                    borderColor: '#25D366',
                    backgroundColor: 'rgba(37, 211, 102, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#25D366',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Recibidos',
                    data: data.received,
                    borderColor: '#128C7E',
                    backgroundColor: 'rgba(18, 140, 126, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#128C7E',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 13, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' mensajes';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 12 } },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        ticks: { font: { size: 12 } },
                        grid: { display: false }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error al cargar grafico:', error);
        createEmptyChart();
    }
}

function createEmptyChart() {
    const ctx = document.getElementById('messagesChart');
    if (!ctx) return;
    
    if (window.messagesChartInstance) {
        window.messagesChartInstance.destroy();
        window.messagesChartInstance = null;
    }
    
    const labels = [];
    const today = new Date();
    
    for (let i = 6; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' }));
    }
    
    window.messagesChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Enviados',
                data: [0, 0, 0, 0, 0, 0, 0],
                borderColor: '#25D366',
                backgroundColor: 'rgba(37, 211, 102, 0.1)',
                borderWidth: 3,
                tension: 0.4
            }, {
                label: 'Recibidos',
                data: [0, 0, 0, 0, 0, 0, 0],
                borderColor: '#128C7E',
                backgroundColor: 'rgba(18, 140, 126, 0.1)',
                borderWidth: 3,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function refreshChart() {
    loadMessagesChart();
    showNotification('Grafico actualizado', 'success');
}
</script>
                        
                        <?php
                        break;
                }
                ?>
            </div>
        </main>
    </div>
    
    <!-- Modal de Perfil - Agregar antes de cerrar </body> -->
<div id="modalProfile" class="modal-overlay">
    <div class="modal-profile">
        <div class="modal-header">
            <h3><i class="fas fa-user-circle"></i> Mi Perfil</h3>
            <button onclick="closeProfileModal()" class="btn-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <form id="formProfile" onsubmit="return saveProfile(event)">
                <div class="profile-section">
                    <h4><i class="fas fa-user"></i> Información Personal</h4>
                    
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="username" class="form-control" 
                               value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        <small style="color: var(--gray);">El usuario no se puede modificar</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre Completo *</label>
                        <input type="text" name="nombre" class="form-control" 
                               value="<?= htmlspecialchars($user['nombre']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Rol</label>
                        <input type="text" class="form-control" 
                               value="<?= htmlspecialchars($user['rol']) ?>" readonly>
                        <small style="color: var(--gray);">Solo un administrador puede cambiar roles</small>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h4><i class="fas fa-lock"></i> Cambiar Contraseña</h4>
                    <small style="color: var(--gray); display: block; margin-bottom: 15px;">
                        Deja estos campos en blanco si no deseas cambiar tu contraseña
                    </small>
                    
                    <div class="form-group">
                        <label>Contraseña Actual</label>
                        <input type="password" name="current_password" class="form-control" 
                               autocomplete="current-password">
                    </div>
                    
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="new_password" class="form-control" 
                               id="newPassword" autocomplete="new-password">
                        <small style="color: var(--gray);">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar Nueva Contraseña</label>
                        <input type="password" name="confirm_password" class="form-control" 
                               id="confirmPassword" autocomplete="new-password">
                    </div>
                </div>
                
                <div class="profile-section">
                    <h4><i class="fas fa-info-circle"></i> Información de la Cuenta</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; color: var(--gray); font-size: 0.9em;">
                        <div>
                            <strong>Último acceso:</strong><br>
                            <?= $user['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($user['ultimo_acceso'])) : 'Nunca' ?>
                        </div>
                        <div>
                            <strong>Cuenta creada:</strong><br>
                            <?= date('d/m/Y', strtotime($user['fecha_creacion'])) ?>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeProfileModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    
    
    

<script>
// Configuración global
const GLOBAL_CONFIG = {
    CHECK_INTERVAL: 5000, // Cada 5 segundos
    SOUND_ENABLED: true,
    DESKTOP_NOTIFICATIONS: true
};

let globalState = {
    lastUnreadCounts: new Map(),
    isChecking: false,
    notificationsEnabled: false,
    totalUnread: 0
};

// ============ INICIALIZAR NOTIFICACIONES ============

function initGlobalNotifications() {
    // Solicitar permisos de notificación
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            globalState.notificationsEnabled = (permission === 'granted');
            console.log('📢 Notificaciones:', globalState.notificationsEnabled ? 'Habilitadas' : 'Deshabilitadas');
        });
    } else if (Notification.permission === 'granted') {
        globalState.notificationsEnabled = true;
    }
    
    // Cargar contadores iniciales
    initializeGlobalUnreadCache();
    
    // Iniciar verificación periódica
    setInterval(checkForNewMessages, GLOBAL_CONFIG.CHECK_INTERVAL);
    
    // Verificar inmediatamente
    setTimeout(checkForNewMessages, 2000);
    
    console.log('✅ Sistema de notificaciones global iniciado');
}

// ============ CACHE DE CONTADORES ============

function initializeGlobalUnreadCache() {
    // Leer del badge del sidebar si existe
    const badge = document.querySelector('.sidebar-nav a[href*="chats"] .badge');
    if (badge) {
        globalState.totalUnread = parseInt(badge.textContent) || 0;
    }
    
    // Si estamos en página de chats, cargar contadores individuales
    document.querySelectorAll('.chat-item').forEach(item => {
        const chatId = item.dataset.chatId;
        const unread = parseInt(item.dataset.unread) || 0;
        globalState.lastUnreadCounts.set(chatId, unread);
    });
}

// ============ VERIFICAR MENSAJES NUEVOS ============

async function checkForNewMessages() {
    if (globalState.isChecking) return;
    
    globalState.isChecking = true;
    
    try {
        const response = await fetch('api/check-unread.php?t=' + Date.now());
        
        if (!response.ok) {
            console.error('❌ Error HTTP:', response.status);
            return;
        }
        
        // Verificar que la respuesta sea JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('❌ Respuesta no es JSON:', contentType);
            const text = await response.text();
            console.error('Contenido:', text.substring(0, 200));
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            processUnreadChanges(data);
        } else {
            console.warn('⚠️ API devolvió success=false:', data.error);
        }
        
    } catch (error) {
        console.error('❌ Error en verificación global:', error.message);
        
        // Si es error de parsing JSON, mostrar más detalles
        if (error instanceof SyntaxError) {
            console.error('💥 El servidor devolvió HTML en lugar de JSON');
            console.error('Verifica que el archivo api/check-unread.php no tenga errores de PHP');
        }
    } finally {
        globalState.isChecking = false;
    }
}

// ============ PROCESAR CAMBIOS ============

function processUnreadChanges(data) {
    const newTotalUnread = data.totalUnread || 0;
    const chatsWithUnread = data.chats || [];
    
    // Verificar si hay mensajes nuevos
    if (newTotalUnread > globalState.totalUnread) {
        const newMessages = newTotalUnread - globalState.totalUnread;
        
        // Encontrar qué chat tiene mensajes nuevos
        let newChatName = '';
        chatsWithUnread.forEach(chat => {
            const previousCount = globalState.lastUnreadCounts.get(chat.id) || 0;
            if (chat.unreadCount > previousCount) {
                newChatName = chat.name;
                globalState.lastUnreadCounts.set(chat.id, chat.unreadCount);
            }
        });
        
        // Mostrar notificación
        if (newChatName) {
            showGlobalNotification(newChatName, newMessages);
            
            if (GLOBAL_CONFIG.SOUND_ENABLED) {
                playNotificationSound();
            }
            
            if (GLOBAL_CONFIG.DESKTOP_NOTIFICATIONS && globalState.notificationsEnabled && !document.hasFocus()) {
                showDesktopNotification(newChatName, `${newMessages} mensaje${newMessages > 1 ? 's' : ''} nuevo${newMessages > 1 ? 's' : ''}`);
            }
            
            // Vibración en móviles
            if ('vibrate' in navigator) {
                navigator.vibrate([200, 100, 200]);
            }
        }
    }
    
    // Actualizar contador global
    globalState.totalUnread = newTotalUnread;
    updateSidebarBadge(newTotalUnread);
    updatePageTitle(newTotalUnread);
}

// ============ NOTIFICACIONES VISUALES ============

function showGlobalNotification(chatName, count) {
    let indicator = document.getElementById('globalMessageIndicator');
    
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'globalMessageIndicator';
        indicator.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
            font-size: 15px;
            font-weight: 500;
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 320px;
            cursor: pointer;
        `;
        document.body.appendChild(indicator);
        
        indicator.addEventListener('click', () => {
            window.location.href = '?page=chats';
        });
    }
    
    const messageText = count > 1 
        ? `${count} mensajes nuevos de ${chatName}` 
        : `Nuevo mensaje de ${chatName}`;
    
    indicator.innerHTML = `
        <i class="fab fa-whatsapp" style="font-size: 24px;"></i>
        <div style="flex: 1;">
            <div style="font-weight: 600;">${escapeHtml(messageText)}</div>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">
                ${new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })}
            </div>
            <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;">
                Click para abrir
            </div>
        </div>
    `;
    
    indicator.style.transform = 'translateX(400px)';
    setTimeout(() => {
        indicator.style.opacity = '1';
        indicator.style.transform = 'translateX(0)';
    }, 10);
    
    setTimeout(() => {
        indicator.style.opacity = '0';
        indicator.style.transform = 'translateX(400px)';
    }, 6000);
}

function showDesktopNotification(chatName, messagePreview) {
    if (!globalState.notificationsEnabled || document.hasFocus()) return;
    
    try {
        const notification = new Notification(`WhatsApp - ${chatName}`, {
            body: messagePreview,
            icon: 'assets/img/whatsapp-icon.png',
            badge: 'assets/img/badge-icon.png',
            tag: 'whatsapp-message-' + Date.now(),
            requireInteraction: false,
            silent: false
        });
        
        notification.onclick = function() {
            window.focus();
            window.location.href = '?page=chats';
            notification.close();
        };
        
        setTimeout(() => notification.close(), 8000);
    } catch (e) {
        console.error('Error en notificación de escritorio:', e);
    }
}

// ============ ACTUALIZAR UI ============

function updateSidebarBadge(count) {
    const chatsLink = document.querySelector('.sidebar-nav a[href*="chats"]');
    if (!chatsLink) return;
    
    let badge = chatsLink.querySelector('.badge');
    
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
        } else {
            badge = document.createElement('span');
            badge.className = 'badge';
            badge.textContent = count;
            chatsLink.appendChild(badge);
            
            // Animar entrada
            badge.style.animation = 'badgeAppear 0.4s ease';
        }
        
        // Animar cambio
        badge.style.animation = 'badgePulse 0.6s ease';
    } else if (badge) {
        badge.style.animation = 'badgeDisappear 0.3s ease';
        setTimeout(() => badge.remove(), 300);
    }
}

function updatePageTitle(count) {
    if (count > 0) {
        document.title = `(${count}) WhatsApp Dashboard`;
    } else {
        document.title = 'WhatsApp Dashboard - Cellcom Technology';
    }
}

// ============ SONIDO ============

function playNotificationSound() {
    try {
        const audio = new Audio('assets/sounds/new-message.mp3');
        audio.volume = 0.4;
        
        // Intentar reproducir
        const playPromise = audio.play();
        
        if (playPromise !== undefined) {
            playPromise.catch(error => {
                // Si falla por autoplay bloqueado, intentar después de interacción
                console.log('Sonido bloqueado por navegador. Requiere interacción del usuario primero.');
                
                // Habilitar sonido después del primer click
                document.addEventListener('click', function enableSound() {
                    audio.play().catch(() => {});
                    document.removeEventListener('click', enableSound);
                }, { once: true });
            });
        }
    } catch (e) {
        console.error('Error reproduciendo sonido:', e);
    }
}

// ============ UTILIDADES ============

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============ ANIMACIONES CSS ============

const globalNotificationStyles = document.createElement('style');
globalNotificationStyles.textContent = `
@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

@keyframes badgeAppear {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes badgeDisappear {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(0); opacity: 0; }
}

.badge {
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
    margin-left: 8px;
}
`;
document.head.appendChild(globalNotificationStyles);

// ============ INICIALIZACIÓN ============

document.addEventListener('DOMContentLoaded', () => {
    initGlobalNotifications();
    console.log('🚀 Sistema de notificaciones global cargado');
});

// Pausar cuando la pestaña no está activa (opcional)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        console.log('👁️ Pestaña visible, verificando mensajes...');
        setTimeout(checkForNewMessages, 1000);
    }
});
</script>

<script>
// Toggle menú de usuario
document.getElementById('userMenuTrigger')?.addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    const icon = document.getElementById('userMenuIcon');
    
    dropdown.classList.toggle('active');
    this.classList.toggle('active');
});

// Cerrar menú al hacer click fuera
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('userDropdown');
    const trigger = document.getElementById('userMenuTrigger');
    
    if (dropdown && !trigger.contains(e.target)) {
        dropdown.classList.remove('active');
        trigger.classList.remove('active');
    }
});

// Abrir modal de perfil
function openProfileModal() {
    document.getElementById('modalProfile').classList.add('active');
    document.getElementById('userDropdown').classList.remove('active');
    document.getElementById('userMenuTrigger').classList.remove('active');
}

// Cerrar modal de perfil
function closeProfileModal() {
    document.getElementById('modalProfile').classList.remove('active');
}

// Cerrar modal al hacer click fuera
document.getElementById('modalProfile')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeProfileModal();
    }
});

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProfileModal();
    }
});

// Guardar perfil
async function saveProfile(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Validar contraseñas si se están cambiando
    if (data.new_password || data.confirm_password) {
        if (!data.current_password) {
            showNotification('Debes ingresar tu contraseña actual', 'error');
            return false;
        }
        
        if (data.new_password !== data.confirm_password) {
            showNotification('Las contraseñas no coinciden', 'error');
            return false;
        }
        
        if (data.new_password.length < 6) {
            showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
            return false;
        }
    }
    
    // Deshabilitar botón
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    try {
        const response = await fetch('api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                nombre: data.nombre,
                email: data.email,
                current_password: data.current_password || null,
                new_password: data.new_password || null
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Perfil actualizado correctamente', 'success');
            
            // Actualizar nombre en sidebar
            const userName = document.querySelector('.user-info span');
            if (userName) userName.textContent = data.nombre;
            
            // Limpiar campos de contraseña
            form.querySelectorAll('input[type="password"]').forEach(input => {
                input.value = '';
            });
            
            setTimeout(() => {
                closeProfileModal();
            }, 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error(error);
        showNotification('Error de conexión', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
    }
    
    return false;
}
</script>
<script>
// Menú móvil
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            const icon = this.querySelector('i');
            icon.className = sidebar.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            menuToggle.querySelector('i').className = 'fas fa-bars';
        });

        document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    menuToggle.querySelector('i').className = 'fas fa-bars';
                }
            });
        });
    }
});

</script>
</body>
</html>