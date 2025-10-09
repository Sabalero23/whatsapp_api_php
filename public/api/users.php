<?php
ob_start();
session_start();
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
ob_clean();

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$auth = Auth::getInstance();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Verificar permisos
    if (!$auth->hasPermission('view_users')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para ver usuarios']);
        exit;
    }
    
    try {
        if (isset($_GET['action']) && $_GET['action'] === 'get_permissions' && isset($_GET['id'])) {
            // Obtener permisos de un usuario
            $userId = (int)$_GET['id'];
            
            $user = $db->fetch("
                SELECT u.id, u.username, u.nombre, u.email, r.nombre as rol_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                WHERE u.id = ?
            ", [$userId]);
            
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                exit;
            }
            
            $permissions = $db->fetchAll("
                SELECT DISTINCT p.id, p.nombre, p.descripcion, p.modulo
                FROM usuarios u
                JOIN roles r ON u.rol_id = r.id
                JOIN rol_permisos rp ON r.id = rp.rol_id
                JOIN permisos p ON rp.permiso_id = p.id
                WHERE u.id = ?
                ORDER BY p.modulo, p.nombre
            ", [$userId]);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'permissions' => $permissions
            ]);
            
        } elseif (isset($_GET['id'])) {
            // Obtener un usuario específico
            $user = $db->fetch("
                SELECT u.*, r.nombre as rol_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                WHERE u.id = ?
            ", [$_GET['id']]);
            
            if ($user) {
                // No enviar la contraseña
                unset($user['password']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            }
            
        } else {
            // Listar todos los usuarios
            $users = $db->fetchAll("
                SELECT u.id, u.username, u.nombre, u.email, u.activo, 
                       u.ultimo_acceso, u.fecha_creacion,
                       r.id as rol_id, r.nombre as rol_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                ORDER BY u.fecha_creacion DESC
            ");
            
            echo json_encode(['success' => true, 'users' => $users]);
        }
        
    } catch (Exception $e) {
        error_log('Error en users.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                // Verificar permisos
                if (!$auth->hasPermission('create_users')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'No tienes permiso para crear usuarios']);
                    break;
                }
                
                // Validaciones
                if (empty($input['username']) || empty($input['nombre']) || empty($input['password'])) {
                    echo json_encode(['success' => false, 'error' => 'Campos requeridos faltantes']);
                    break;
                }
                
                if (strlen($input['password']) < 6) {
                    echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
                    break;
                }
                
                // Verificar que el usuario no exista
                $existing = $db->fetch("SELECT id FROM usuarios WHERE username = ?", [$input['username']]);
                if ($existing) {
                    echo json_encode(['success' => false, 'error' => 'El usuario ya existe']);
                    break;
                }
                
                // Validar rol
                if (!empty($input['rol_id'])) {
                    $roleExists = $db->fetch("SELECT id FROM roles WHERE id = ? AND activo = 1", [$input['rol_id']]);
                    if (!$roleExists) {
                        echo json_encode(['success' => false, 'error' => 'Rol no válido']);
                        break;
                    }
                }
                
                // Crear usuario
                $userId = $db->insert('usuarios', [
                    'username' => $input['username'],
                    'password' => password_hash($input['password'], PASSWORD_DEFAULT),
                    'nombre' => $input['nombre'],
                    'email' => $input['email'] ?? null,
                    'rol_id' => $input['rol_id'] ?? null,
                    'activo' => $input['activo'] ?? 1
                ]);
                
                // Log
                $auth->logActivity(
                    'Crear usuario',
                    "Usuario creado: {$input['username']}",
                    ['user_id' => $userId]
                );
                
                echo json_encode(['success' => true, 'id' => $userId]);
                break;
                
            case 'update':
                // Verificar permisos
                if (!$auth->hasPermission('edit_users')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'No tienes permiso para editar usuarios']);
                    break;
                }
                
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $userId = (int)$input['id'];
                
                // Obtener usuario actual
                $user = $db->fetch("SELECT * FROM usuarios WHERE id = ?", [$userId]);
                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                    break;
                }
                
                // Preparar datos para actualizar
                $updateData = [
                    'nombre' => $input['nombre'],
                    'email' => $input['email'] ?? null,
                    'rol_id' => $input['rol_id'] ?? null,
                    'activo' => $input['activo'] ?? 1
                ];
                
                // Si se proporciona nueva contraseña
                if (!empty($input['password'])) {
                    if (strlen($input['password']) < 6) {
                        echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
                        break;
                    }
                    $updateData['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
                }
                
                // Validar rol si se proporciona
                if (!empty($input['rol_id'])) {
                    $roleExists = $db->fetch("SELECT id FROM roles WHERE id = ? AND activo = 1", [$input['rol_id']]);
                    if (!$roleExists) {
                        echo json_encode(['success' => false, 'error' => 'Rol no válido']);
                        break;
                    }
                }
                
                // Actualizar
                $db->update('usuarios', $updateData, 'id = ?', [$userId]);
                
                // Log
                $auth->logActivity(
                    'Actualizar usuario',
                    "Usuario actualizado: {$user['username']}",
                    ['user_id' => $userId]
                );
                
                echo json_encode(['success' => true]);
                break;
                
            case 'delete':
                // Verificar permisos
                if (!$auth->hasPermission('delete_users')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'No tienes permiso para eliminar usuarios']);
                    break;
                }
                
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $userId = (int)$input['id'];
                
                // No permitir eliminar el propio usuario
                if ($userId == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'error' => 'No puedes eliminar tu propio usuario']);
                    break;
                }
                
                // Obtener info del usuario antes de eliminar
                $user = $db->fetch("SELECT username FROM usuarios WHERE id = ?", [$userId]);
                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                    break;
                }
                
                // Eliminar usuario
                $db->delete('usuarios', 'id = ?', [$userId]);
                
                // Log
                $auth->logActivity(
                    'Eliminar usuario',
                    "Usuario eliminado: {$user['username']}",
                    ['user_id' => $userId]
                );
                
                echo json_encode(['success' => true]);
                break;
                
            case 'toggle_status':
                // Verificar permisos
                if (!$auth->hasPermission('edit_users')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'No tienes permiso para editar usuarios']);
                    break;
                }
                
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $userId = (int)$input['id'];
                
                // No permitir desactivar el propio usuario
                if ($userId == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'error' => 'No puedes desactivar tu propio usuario']);
                    break;
                }
                
                $activo = isset($input['activo']) ? (int)$input['activo'] : 0;
                
                $db->update('usuarios', ['activo' => $activo], 'id = ?', [$userId]);
                
                // Log
                $auth->logActivity(
                    $activo ? 'Activar usuario' : 'Desactivar usuario',
                    "Usuario ID: {$userId}",
                    ['user_id' => $userId, 'activo' => $activo]
                );
                
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
        
    } catch (Exception $e) {
        error_log('Error en users.php POST: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

exit;