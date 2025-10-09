<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

// Solo admins pueden ejecutar tareas de mantenimiento
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'clear_logs':
                // Eliminar logs de más de 30 días
                $result = $db->query(
                    "DELETE FROM logs WHERE fecha < DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                
                $deleted = $result->rowCount();
                
                // Log de la acción
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Limpiar logs antiguos',
                    'descripcion' => "$deleted registros eliminados",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode([
                    'success' => true, 
                    'deleted' => $deleted,
                    'message' => "Se eliminaron $deleted registros antiguos"
                ]);
                break;
                
            case 'clear_old_messages':
                // Eliminar mensajes antiguos (más de 90 días)
                $days = $input['days'] ?? 90;
                
                $deletedEntrantes = $db->query(
                    "DELETE FROM mensajes_entrantes WHERE fecha_recepcion < DATE_SUB(NOW(), INTERVAL ? DAY)",
                    [$days]
                )->rowCount();
                
                $deletedSalientes = $db->query(
                    "DELETE FROM mensajes_salientes WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL ? DAY)",
                    [$days]
                )->rowCount();
                
                $total = $deletedEntrantes + $deletedSalientes;
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Limpiar mensajes antiguos',
                    'descripcion' => "$total mensajes eliminados ($days días)",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'deleted' => $total,
                    'entrantes' => $deletedEntrantes,
                    'salientes' => $deletedSalientes,
                    'message' => "Se eliminaron $total mensajes antiguos"
                ]);
                break;
                
            case 'optimize_database':
                // Optimizar tablas
                $tables = [
                    'logs',
                    'mensajes_entrantes',
                    'mensajes_salientes',
                    'contactos',
                    'chats',
                    'difusiones',
                    'difusion_destinatarios'
                ];
                
                $optimized = 0;
                foreach ($tables as $table) {
                    try {
                        $db->query("OPTIMIZE TABLE $table");
                        $optimized++;
                    } catch (Exception $e) {
                        error_log("Error optimizando tabla $table: " . $e->getMessage());
                    }
                }
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Optimizar base de datos',
                    'descripcion' => "$optimized tablas optimizadas",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'optimized' => $optimized,
                    'message' => "Se optimizaron $optimized tablas"
                ]);
                break;
                
            case 'get_database_stats':
                // Obtener estadísticas de la base de datos
                $stats = [];
                
                $tables = [
                    'logs' => 'Logs del sistema',
                    'mensajes_entrantes' => 'Mensajes recibidos',
                    'mensajes_salientes' => 'Mensajes enviados',
                    'contactos' => 'Contactos',
                    'chats' => 'Chats',
                    'difusiones' => 'Difusiones',
                    'usuarios' => 'Usuarios'
                ];
                
                foreach ($tables as $table => $nombre) {
                    $result = $db->fetch("SELECT COUNT(*) as total FROM $table");
                    $stats[$table] = [
                        'nombre' => $nombre,
                        'total' => $result['total']
                    ];
                }
                
                // Tamaño de la base de datos
                $size = $db->fetch(
                    "SELECT 
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE()"
                );
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats,
                    'database_size_mb' => $size['size_mb']
                ]);
                break;
                
            case 'backup_database':
                // Crear backup de la base de datos
                // Esta funcionalidad requiere acceso al sistema de archivos
                $backupDir = __DIR__ . '/../../backups';
                
                if (!file_exists($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backupDir . '/' . $filename;
                
                // Aquí necesitarías implementar mysqldump o una solución similar
                // Por seguridad, esto debería ejecutarse en el servidor
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Backup creado (funcionalidad en desarrollo)',
                    'filename' => $filename
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log('Error en maintenance.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Obtener estadísticas de mantenimiento
        $stats = [];
        
        // Logs antiguos
        $oldLogs = $db->fetch(
            "SELECT COUNT(*) as total FROM logs WHERE fecha < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stats['old_logs'] = $oldLogs['total'];
        
        // Mensajes antiguos
        $oldMessages = $db->fetch(
            "SELECT 
                (SELECT COUNT(*) FROM mensajes_entrantes WHERE fecha_recepcion < DATE_SUB(NOW(), INTERVAL 90 DAY)) +
                (SELECT COUNT(*) FROM mensajes_salientes WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 90 DAY)) as total"
        );
        $stats['old_messages'] = $oldMessages['total'];
        
        // Tamaño de la base de datos
        $size = $db->fetch(
            "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()"
        );
        $stats['database_size_mb'] = $size['size_mb'];
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        error_log('Error en maintenance.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor']);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}