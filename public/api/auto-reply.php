<?php
/**
 * API para gestión de respuestas automáticas
 * Maneja CRUD y toggle del bot
 */

// Evitar cualquier output antes del JSON
ob_start();

session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

// Limpiar cualquier output buffer previo
ob_clean();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();

// Manejar GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get':
                $id = (int)($_GET['id'] ?? 0);
                if (!$id) {
                    throw new Exception('ID requerido');
                }
                
                $respuesta = $db->fetch(
                    "SELECT * FROM respuestas_automaticas WHERE id = ?",
                    [$id]
                );
                
                if (!$respuesta) {
                    throw new Exception('Respuesta no encontrada');
                }
                
                echo json_encode(['success' => true, 'data' => $respuesta]);
                break;
                
            case 'list':
                $respuestas = $db->fetchAll(
                    "SELECT * FROM respuestas_automaticas 
                     ORDER BY prioridad DESC, palabra_clave ASC"
                );
                
                echo json_encode(['success' => true, 'data' => $respuestas]);
                break;
                
            case 'bot_status':
                $config = $db->fetch(
                    "SELECT valor FROM configuracion WHERE clave = 'bot_activo'"
                );
                
                echo json_encode([
                    'success' => true,
                    'activo' => ($config && $config['valor'] == '1')
                ]);
                break;
                
            default:
                throw new Exception('Acción GET no válida');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Manejar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            
            // ========== TOGGLE BOT (PRINCIPAL) ==========
            case 'toggle_bot':
    $nuevoEstado = (int)($input['estado'] ?? 0);
    
    // Verificar si existe la configuración
    $existe = $db->fetch(
        "SELECT id FROM configuracion WHERE clave = 'bot_activo'"
    );
    
    if ($existe) {
        // Actualizar
        $db->update(
            'configuracion',
            ['valor' => $nuevoEstado],
            'clave = ?',
            ['bot_activo']
        );
    } else {
        // Insertar
        $db->insert('configuracion', [
            'clave' => 'bot_activo',
            'valor' => $nuevoEstado,
            'descripcion' => 'Estado del bot de respuestas automáticas'
        ]);
    }
    
    // Sincronizar con Redis
    try {
        $redis = new Redis();
        if ($redis->connect('127.0.0.1', 6379)) {
            $redis->auth('cellcom538@@');
            $redis->set('whatsapp:bot_activo', $nuevoEstado);
            $redis->close();
        }
    } catch (Exception $e) {
        error_log('Error sincronizando Redis: ' . $e->getMessage());
    }
    
    // Log
    $db->insert('logs', [
        'usuario_id' => $_SESSION['user_id'],
        'accion' => 'bot_toggle',
        'descripcion' => 'Bot ' . ($nuevoEstado ? 'activado' : 'desactivado'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'estado' => (bool)$nuevoEstado,
        'message' => 'Bot ' . ($nuevoEstado ? 'activado' : 'desactivado') . ' correctamente'
    ]);
    break;

            
            // ========== CREAR RESPUESTA ==========
            case 'create':
                $palabraClave = trim($input['palabra_clave'] ?? '');
                $respuesta = trim($input['respuesta'] ?? '');
                $exacta = (int)($input['exacta'] ?? 0);
                $prioridad = (int)($input['prioridad'] ?? 0);
                $activa = (int)($input['activa'] ?? 1);
                
                // Validaciones
                if (empty($palabraClave)) {
                    throw new Exception('La palabra clave es requerida');
                }
                
                if (strlen($palabraClave) < 2) {
                    throw new Exception('La palabra clave debe tener al menos 2 caracteres');
                }
                
                if (empty($respuesta)) {
                    throw new Exception('La respuesta es requerida');
                }
                
                if (strlen($respuesta) < 5) {
                    throw new Exception('La respuesta debe tener al menos 5 caracteres');
                }
                
                if ($prioridad < 0 || $prioridad > 10) {
                    throw new Exception('La prioridad debe estar entre 0 y 10');
                }
                
                // Verificar duplicados
                $existe = $db->fetch(
                    "SELECT id FROM respuestas_automaticas 
                     WHERE palabra_clave = ? AND id != ?",
                    [$palabraClave, 0]
                );
                
                if ($existe) {
                    throw new Exception('Ya existe una respuesta con esta palabra clave');
                }
                
                // Insertar
                $id = $db->insert('respuestas_automaticas', [
                    'palabra_clave' => $palabraClave,
                    'respuesta' => $respuesta,
                    'exacta' => $exacta,
                    'prioridad' => $prioridad,
                    'activa' => $activa
                ]);
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'respuesta_automatica_crear',
                    'descripcion' => "Nueva respuesta automática: {$palabraClave}",
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
                
                echo json_encode([
                    'success' => true,
                    'id' => $id,
                    'message' => 'Respuesta creada correctamente'
                ]);
                break;
            
            // ========== ACTUALIZAR RESPUESTA ==========
            case 'update':
                $id = (int)($input['id'] ?? 0);
                
                if (!$id) {
                    throw new Exception('ID requerido');
                }
                
                $palabraClave = trim($input['palabra_clave'] ?? '');
                $respuesta = trim($input['respuesta'] ?? '');
                $exacta = (int)($input['exacta'] ?? 0);
                $prioridad = (int)($input['prioridad'] ?? 0);
                $activa = (int)($input['activa'] ?? 1);
                
                // Validaciones
                if (empty($palabraClave)) {
                    throw new Exception('La palabra clave es requerida');
                }
                
                if (empty($respuesta)) {
                    throw new Exception('La respuesta es requerida');
                }
                
                // Verificar que existe
                $existe = $db->fetch(
                    "SELECT id FROM respuestas_automaticas WHERE id = ?",
                    [$id]
                );
                
                if (!$existe) {
                    throw new Exception('Respuesta no encontrada');
                }
                
                // Verificar duplicados
                $duplicado = $db->fetch(
                    "SELECT id FROM respuestas_automaticas 
                     WHERE palabra_clave = ? AND id != ?",
                    [$palabraClave, $id]
                );
                
                if ($duplicado) {
                    throw new Exception('Ya existe otra respuesta con esta palabra clave');
                }
                
                // Actualizar
                $db->update(
                    'respuestas_automaticas',
                    [
                        'palabra_clave' => $palabraClave,
                        'respuesta' => $respuesta,
                        'exacta' => $exacta,
                        'prioridad' => $prioridad,
                        'activa' => $activa
                    ],
                    'id = ?',
                    [$id]
                );
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'respuesta_automatica_actualizar',
                    'descripcion' => "Respuesta actualizada: {$palabraClave}",
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Respuesta actualizada correctamente'
                ]);
                break;
            
            // ========== TOGGLE INDIVIDUAL ==========
            case 'toggle':
                $id = (int)($input['id'] ?? 0);
                $estado = (int)($input['estado'] ?? 0);
                
                if (!$id) {
                    throw new Exception('ID requerido');
                }
                
                // Verificar que existe
                $existe = $db->fetch(
                    "SELECT palabra_clave FROM respuestas_automaticas WHERE id = ?",
                    [$id]
                );
                
                if (!$existe) {
                    throw new Exception('Respuesta no encontrada');
                }
                
                // Actualizar
                $db->update(
                    'respuestas_automaticas',
                    ['activa' => $estado],
                    'id = ?',
                    [$id]
                );
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'respuesta_automatica_toggle',
                    'descripcion' => "Respuesta {$existe['palabra_clave']} " . ($estado ? 'activada' : 'desactivada'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Estado actualizado'
                ]);
                break;
            
            // ========== ELIMINAR ==========
            case 'delete':
                $id = (int)($input['id'] ?? 0);
                
                if (!$id) {
                    throw new Exception('ID requerido');
                }
                
                // Obtener datos antes de eliminar (para log)
                $respuesta = $db->fetch(
                    "SELECT palabra_clave FROM respuestas_automaticas WHERE id = ?",
                    [$id]
                );
                
                if (!$respuesta) {
                    throw new Exception('Respuesta no encontrada');
                }
                
                // Eliminar
                $db->delete('respuestas_automaticas', 'id = ?', [$id]);
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'respuesta_automatica_eliminar',
                    'descripcion' => "Respuesta eliminada: {$respuesta['palabra_clave']}",
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Respuesta eliminada correctamente'
                ]);
                break;
            
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Método no permitido
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
exit;