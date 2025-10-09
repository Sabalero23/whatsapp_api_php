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
    // Obtener información de un rol
    if (isset($_GET['id'])) {
        try {
            $roleId = (int)$_GET['id'];
            
            // Obtener datos del rol
            $role = $db->fetch("
                SELECT id, nombre, descripcion, es_sistema, activo
                FROM roles
                WHERE id = ?
            ", [$roleId]);
            
            if (!$role) {
                echo json_encode(['success' => false, 'error' => 'Rol no encontrado']);
                exit;
            }
            
            // Obtener permisos del rol
            $permissions = Auth::getRolePermissions($roleId);
            
            echo json_encode([
                'success' => true,
                'role' => $role,
                'permissions' => $permissions
            ]);
            
        } catch (Exception $e) {
            error_log('Error en roles.php GET: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error del servidor']);
        }
    } else {
        // Listar todos los roles
        try {
            $roles = Auth::getAllRoles();
            echo json_encode(['success' => true, 'roles' => $roles]);
        } catch (Exception $e) {
            error_log('Error en roles.php GET: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error del servidor']);
        }
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar permisos para gestionar roles
    if (!$auth->hasPermission('manage_roles')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para gestionar roles']);
        exit;
    }
    
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
                // Validaciones
                if (empty($input['nombre'])) {
                    echo json_encode(['success' => false, 'error' => 'El nombre del rol es requerido']);
                    break;
                }
                
                // Verificar que el nombre no exista
                $existing = $db->fetch("SELECT id FROM roles WHERE nombre = ?", [$input['nombre']]);
                if ($existing) {
                    echo json_encode(['success' => false, 'error' => 'Ya existe un rol con ese nombre']);
                    break;
                }
                
                // Crear rol con permisos
                $permissions = $input['permissions'] ?? [];
                
                $roleId = Auth::createRole(
                    $input['nombre'],
                    $input['descripcion'] ?? '',
                    $permissions
                );
                
                // Log
                $auth->logActivity(
                    'Crear rol',
                    "Rol creado: {$input['nombre']}",
                    ['role_id' => $roleId, 'permissions_count' => count($permissions)]
                );
                
                echo json_encode(['success' => true, 'id' => $roleId]);
                break;
                
            case 'update':
                // Validaciones
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $roleId = (int)$input['id'];
                
                // Verificar que el rol existe
                $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
                if (!$role) {
                    echo json_encode(['success' => false, 'error' => 'Rol no encontrado']);
                    break;
                }
                
                // No permitir editar nombre de roles del sistema
                if ($role['es_sistema'] && isset($input['nombre']) && $input['nombre'] !== $role['nombre']) {
                    echo json_encode(['success' => false, 'error' => 'No se puede cambiar el nombre de un rol del sistema']);
                    break;
                }
                
                // Actualizar información básica si se proporciona
                if (isset($input['nombre']) || isset($input['descripcion'])) {
                    $updateData = [];
                    if (isset($input['nombre'])) $updateData['nombre'] = $input['nombre'];
                    if (isset($input['descripcion'])) $updateData['descripcion'] = $input['descripcion'];
                    
                    $db->update('roles', $updateData, 'id = ?', [$roleId]);
                }
                
                // Actualizar permisos
                if (isset($input['permissions']) && is_array($input['permissions'])) {
                    Auth::updateRolePermissions($roleId, $input['permissions']);
                }
                
                // Log
                $auth->logActivity(
                    'Actualizar rol',
                    "Rol actualizado: {$role['nombre']}",
                    ['role_id' => $roleId]
                );
                
                echo json_encode(['success' => true]);
                break;
                
            case 'delete':
                // Validaciones
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $roleId = (int)$input['id'];
                
                try {
                    // Obtener nombre del rol antes de eliminar
                    $role = $db->fetch("SELECT nombre FROM roles WHERE id = ?", [$roleId]);
                    
                    if (!$role) {
                        echo json_encode(['success' => false, 'error' => 'Rol no encontrado']);
                        break;
                    }
                    
                    Auth::deleteRole($roleId);
                    
                    // Log
                    $auth->logActivity(
                        'Eliminar rol',
                        "Rol eliminado: {$role['nombre']}",
                        ['role_id' => $roleId]
                    );
                    
                    echo json_encode(['success' => true]);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
                
            case 'toggle_status':
                // Activar/desactivar rol
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $roleId = (int)$input['id'];
                $activo = isset($input['activo']) ? (int)$input['activo'] : 0;
                
                // Verificar que no sea rol del sistema
                $role = $db->fetch("SELECT es_sistema FROM roles WHERE id = ?", [$roleId]);
                if ($role && $role['es_sistema']) {
                    echo json_encode(['success' => false, 'error' => 'No se puede desactivar un rol del sistema']);
                    break;
                }
                
                $db->update('roles', ['activo' => $activo], 'id = ?', [$roleId]);
                
                // Log
                $auth->logActivity(
                    $activo ? 'Activar rol' : 'Desactivar rol',
                    "Rol ID: {$roleId}",
                    ['role_id' => $roleId, 'activo' => $activo]
                );
                
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
        
    } catch (Exception $e) {
        error_log('Error en roles.php POST: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

exit;