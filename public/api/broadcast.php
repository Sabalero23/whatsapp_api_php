<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsAppClient.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();

// Cargar WhatsApp Client
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'draft':
            case 'send':
                // Validaciones
                if (empty($input['nombre'])) {
                    echo json_encode(['success' => false, 'error' => 'Nombre requerido']);
                    break;
                }
                
                if (empty($input['mensaje'])) {
                    echo json_encode(['success' => false, 'error' => 'Mensaje requerido']);
                    break;
                }
                
                if (empty($input['destinatarios']) || !is_array($input['destinatarios'])) {
                    echo json_encode(['success' => false, 'error' => 'Destinatarios requeridos']);
                    break;
                }
                
                $destinatarios = array_filter($input['destinatarios'], function($n) {
                    return !empty(trim($n));
                });
                
                if (count($destinatarios) === 0) {
                    echo json_encode(['success' => false, 'error' => 'Debe agregar al menos un destinatario']);
                    break;
                }
                
                // Crear difusión
                $estado = $action === 'send' ? 'enviando' : 'borrador';
                
                $difusionId = $db->insert('difusiones', [
                    'nombre' => $input['nombre'],
                    'mensaje' => $input['mensaje'],
                    'tipo' => 'text',
                    'estado' => $estado,
                    'total_destinatarios' => count($destinatarios),
                    'programada_para' => $input['programada_para'] ?? null,
                    'creado_por' => $_SESSION['username'] ?? 'sistema'
                ]);
                
                // Insertar destinatarios
                foreach ($destinatarios as $numero) {
                    $numero = trim($numero);
                    // Asegurar formato correcto
                    if (!str_contains($numero, '@')) {
                        $numero = $numero . '@c.us';
                    }
                    
                    $db->insert('difusion_destinatarios', [
                        'difusion_id' => $difusionId,
                        'numero' => $numero,
                        'estado' => 'pendiente'
                    ]);
                }
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => $action === 'send' ? 'Iniciar difusión' : 'Crear borrador difusión',
                    'descripcion' => "Difusión: {$input['nombre']} - " . count($destinatarios) . " destinatarios",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                // Si es envío inmediato, procesar en background
                if ($action === 'send') {
                    processBroadcast($difusionId, $input['delay'] ?? 2);
                }
                
                echo json_encode([
                    'success' => true, 
                    'id' => $difusionId,
                    'message' => $action === 'send' ? 'Difusión iniciada' : 'Borrador guardado'
                ]);
                break;
                
            case 'resend':
                // Reenviar difusión basada en una anterior
                if (!isset($input['difusion_base_id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID de difusión base requerido']);
                    break;
                }
                
                // Obtener difusión original
                $difusionBase = $db->fetch(
                    "SELECT * FROM difusiones WHERE id = ?",
                    [$input['difusion_base_id']]
                );
                
                if (!$difusionBase) {
                    echo json_encode(['success' => false, 'error' => 'Difusión base no encontrada']);
                    break;
                }
                
                // Obtener destinatarios originales si no se proporcionan nuevos
                $destinatarios = $input['destinatarios'] ?? [];
                
                if (empty($destinatarios)) {
                    $destOriginal = $db->fetchAll(
                        "SELECT numero FROM difusion_destinatarios WHERE difusion_id = ?",
                        [$input['difusion_base_id']]
                    );
                    $destinatarios = array_column($destOriginal, 'numero');
                }
                
                // Limpiar formato @c.us para almacenar solo números
                $destinatarios = array_map(function($n) {
                    return str_replace(['@c.us', '@g.us'], '', trim($n));
                }, $destinatarios);
                
                // Crear nueva difusión
                $nombre = $input['nombre'] ?? $difusionBase['nombre'] . ' (Reenvío)';
                $mensaje = $input['mensaje'] ?? $difusionBase['mensaje'];
                
                $nuevaDifusionId = $db->insert('difusiones', [
                    'nombre' => $nombre,
                    'mensaje' => $mensaje,
                    'tipo' => 'text',
                    'estado' => 'enviando',
                    'total_destinatarios' => count($destinatarios),
                    'creado_por' => $_SESSION['username'] ?? 'sistema'
                ]);
                
                // Insertar destinatarios
                foreach ($destinatarios as $numero) {
                    if (!str_contains($numero, '@')) {
                        $numero = $numero . '@c.us';
                    }
                    
                    $db->insert('difusion_destinatarios', [
                        'difusion_id' => $nuevaDifusionId,
                        'numero' => $numero,
                        'estado' => 'pendiente'
                    ]);
                }
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Reenviar difusión',
                    'descripcion' => "Nueva difusión: $nombre - " . count($destinatarios) . " destinatarios",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                // Procesar envío
                processBroadcast($nuevaDifusionId, $input['delay'] ?? 2);
                
                echo json_encode([
                    'success' => true, 
                    'id' => $nuevaDifusionId,
                    'message' => 'Difusión reenviada'
                ]);
                break;
                
            case 'search_contacts':
                // Buscar contactos por nombre o número
                $query = $input['query'] ?? '';
                
                if (strlen($query) < 2) {
                    echo json_encode(['success' => true, 'contacts' => []]);
                    break;
                }
                
                $contacts = $db->fetchAll(
                    "SELECT id, numero, nombre, empresa, etiquetas 
                    FROM contactos 
                    WHERE (nombre LIKE ? OR numero LIKE ? OR empresa LIKE ?) 
                    AND bloqueado = 0
                    ORDER BY nombre ASC 
                    LIMIT 20",
                    ["%$query%", "%$query%", "%$query%"]
                );
                
                echo json_encode(['success' => true, 'contacts' => $contacts]);
                break;
                
            case 'delete':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                // Verificar que no esté enviando
                $difusion = $db->fetch(
                    "SELECT estado FROM difusiones WHERE id = ?",
                    [$input['id']]
                );
                
                if ($difusion['estado'] === 'enviando') {
                    echo json_encode(['success' => false, 'error' => 'No se puede eliminar una difusión en proceso']);
                    break;
                }
                
                $db->delete('difusiones', 'id = ?', [$input['id']]);
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Eliminar difusión',
                    'descripcion' => "ID: {$input['id']}",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'get':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $difusion = $db->fetch(
                    "SELECT * FROM difusiones WHERE id = ?",
                    [$input['id']]
                );
                
                if (!$difusion) {
                    echo json_encode(['success' => false, 'error' => 'Difusión no encontrada']);
                    break;
                }
                
                $destinatarios = $db->fetchAll(
                    "SELECT * FROM difusion_destinatarios WHERE difusion_id = ?",
                    [$input['id']]
                );
                
                echo json_encode([
                    'success' => true,
                    'difusion' => $difusion,
                    'destinatarios' => $destinatarios
                ]);
                break;
                
            case 'status':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $stats = $db->fetch(
                    "SELECT 
                        d.estado,
                        d.total_destinatarios,
                        d.enviados,
                        d.fallidos,
                        COUNT(CASE WHEN dd.estado = 'pendiente' THEN 1 END) as pendientes,
                        COUNT(CASE WHEN dd.estado = 'enviado' THEN 1 END) as enviados_count,
                        COUNT(CASE WHEN dd.estado = 'fallido' THEN 1 END) as fallidos_count
                    FROM difusiones d
                    LEFT JOIN difusion_destinatarios dd ON d.id = dd.difusion_id
                    WHERE d.id = ?
                    GROUP BY d.id",
                    [$input['id']]
                );
                
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log('Error en broadcast.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (isset($_GET['id'])) {
            $difusion = $db->fetch(
                "SELECT * FROM difusiones WHERE id = ?",
                [$_GET['id']]
            );
            
            if (!$difusion) {
                echo json_encode(['success' => false, 'error' => 'Difusión no encontrada']);
                exit;
            }
            
            $destinatarios = $db->fetchAll(
                "SELECT * FROM difusion_destinatarios WHERE difusion_id = ?",
                [$_GET['id']]
            );
            
            echo json_encode([
                'success' => true,
                'difusion' => $difusion,
                'destinatarios' => $destinatarios
            ]);
        } else {
            // Listar todas las difusiones con paginación
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $difusiones = $db->fetchAll(
                "SELECT * FROM difusiones ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
            
            $total = $db->fetch("SELECT COUNT(*) as total FROM difusiones")['total'];
            
            echo json_encode([
                'success' => true, 
                'difusiones' => $difusiones,
                'total' => $total
            ]);
        }
    } catch (Exception $e) {
        error_log('Error en broadcast.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

/**
 * Procesar envío de difusión en background
 */
function processBroadcast($difusionId, $delay = 2) {
    global $db, $whatsapp;
    
    try {
        // Obtener difusión
        $difusion = $db->fetch(
            "SELECT * FROM difusiones WHERE id = ?",
            [$difusionId]
        );
        
        if (!$difusion) {
            return;
        }
        
        // Actualizar estado
        $db->update('difusiones', [
            'estado' => 'enviando',
            'fecha_inicio' => date('Y-m-d H:i:s')
        ], 'id = ?', [$difusionId]);
        
        // Obtener destinatarios pendientes
        $destinatarios = $db->fetchAll(
            "SELECT * FROM difusion_destinatarios 
            WHERE difusion_id = ? AND estado = 'pendiente'",
            [$difusionId]
        );
        
        $enviados = 0;
        $fallidos = 0;
        
        foreach ($destinatarios as $dest) {
            try {
                // Preparar mensaje (reemplazar variables)
                $mensaje = $difusion['mensaje'];
                
                // Buscar datos del contacto
                $numero = str_replace(['@c.us', '@g.us'], '', $dest['numero']);
                $contacto = $db->fetch(
                    "SELECT * FROM contactos WHERE numero = ?",
                    [$numero]
                );
                
                if ($contacto) {
                    $mensaje = str_replace('{nombre}', $contacto['nombre'] ?? $numero, $mensaje);
                    $mensaje = str_replace('{numero}', $numero, $mensaje);
                    $mensaje = str_replace('{empresa}', $contacto['empresa'] ?? '', $mensaje);
                }
                
                // Enviar mensaje
                $response = $whatsapp->sendMessage($dest['numero'], $mensaje);
                
                if ($response && isset($response['success']) && $response['success']) {
                    $db->update('difusion_destinatarios', [
                        'estado' => 'enviado',
                        'fecha_envio' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$dest['id']]);
                    
                    $enviados++;
                } else {
                    throw new Exception('Error en respuesta de WhatsApp');
                }
                
                // Delay entre mensajes
                sleep($delay);
                
            } catch (Exception $e) {
                $db->update('difusion_destinatarios', [
                    'estado' => 'fallido',
                    'error' => $e->getMessage()
                ], 'id = ?', [$dest['id']]);
                
                $fallidos++;
                error_log("Error enviando a {$dest['numero']}: " . $e->getMessage());
            }
        }
        
        // Actualizar difusión
        $db->update('difusiones', [
            'estado' => 'completada',
            'enviados' => $enviados,
            'fallidos' => $fallidos,
            'fecha_fin' => date('Y-m-d H:i:s')
        ], 'id = ?', [$difusionId]);
        
        // Log final
        $db->insert('logs', [
            'usuario_id' => $_SESSION['user_id'] ?? null,
            'accion' => 'Difusión completada',
            'descripcion' => "ID: $difusionId - Enviados: $enviados, Fallidos: $fallidos",
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'sistema'
        ]);
        
    } catch (Exception $e) {
        error_log('Error en processBroadcast: ' . $e->getMessage());
        
        $db->update('difusiones', [
            'estado' => 'cancelada'
        ], 'id = ?', [$difusionId]);
    }
}