<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update':
                // Validaciones
                if (empty($input['nombre'])) {
                    echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
                    break;
                }
                
                // Obtener usuario actual
                $user = $db->fetch(
                    "SELECT * FROM usuarios WHERE id = ?",
                    [$_SESSION['user_id']]
                );
                
                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                    break;
                }
                
                // Preparar datos para actualizar
                $updateData = [
                    'nombre' => $input['nombre'],
                    'email' => $input['email'] ?? null
                ];
                
                // Si se está cambiando la contraseña
                if (!empty($input['new_password'])) {
                    // Validar contraseña actual
                    if (empty($input['current_password'])) {
                        echo json_encode(['success' => false, 'error' => 'Debe ingresar su contraseña actual']);
                        break;
                    }
                    
                    // Verificar contraseña actual
                    if (!password_verify($input['current_password'], $user['password'])) {
                        echo json_encode(['success' => false, 'error' => 'Contraseña actual incorrecta']);
                        break;
                    }
                    
                    // Validar longitud de nueva contraseña
                    if (strlen($input['new_password']) < 6) {
                        echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
                        break;
                    }
                    
                    // Hashear nueva contraseña
                    $updateData['password'] = password_hash($input['new_password'], PASSWORD_DEFAULT);
                }
                
                // Actualizar usuario
                $db->update('usuarios', $updateData, 'id = ?', [$_SESSION['user_id']]);
                
                // Actualizar nombre en sesión
                $_SESSION['username'] = $user['username'];
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Actualizar perfil',
                    'descripcion' => "Usuario actualizó su perfil",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Perfil actualizado correctamente',
                    'data' => [
                        'nombre' => $updateData['nombre'],
                        'email' => $updateData['email']
                    ]
                ]);
                break;
                
            case 'get':
                // Obtener datos del usuario actual
                $user = $db->fetch(
                    "SELECT id, username, nombre, email, rol, ultimo_acceso, fecha_creacion FROM usuarios WHERE id = ?",
                    [$_SESSION['user_id']]
                );
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                }
                break;
                
            case 'change_password':
                // Cambio de contraseña específico
                if (empty($input['current_password']) || empty($input['new_password'])) {
                    echo json_encode(['success' => false, 'error' => 'Todos los campos son requeridos']);
                    break;
                }
                
                // Obtener usuario
                $user = $db->fetch(
                    "SELECT password FROM usuarios WHERE id = ?",
                    [$_SESSION['user_id']]
                );
                
                // Verificar contraseña actual
                if (!password_verify($input['current_password'], $user['password'])) {
                    echo json_encode(['success' => false, 'error' => 'Contraseña actual incorrecta']);
                    break;
                }
                
                // Validar nueva contraseña
                if (strlen($input['new_password']) < 6) {
                    echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
                    break;
                }
                
                // Actualizar contraseña
                $db->update('usuarios', [
                    'password' => password_hash($input['new_password'], PASSWORD_DEFAULT)
                ], 'id = ?', [$_SESSION['user_id']]);
                
                // Log
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Cambiar contraseña',
                    'descripcion' => "Usuario cambió su contraseña",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log('Error en profile.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Obtener datos del usuario actual
        $user = $db->fetch(
            "SELECT id, username, nombre, email, rol, ultimo_acceso, fecha_creacion FROM usuarios WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        }
    } catch (Exception $e) {
        error_log('Error en profile.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor']);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}