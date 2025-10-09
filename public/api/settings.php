<?php
ob_start();
session_start();
require_once __DIR__ . '/../../src/Database.php';
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

try {
    $db = Database::getInstance();
    
    foreach ($input as $clave => $valor) {
        $existe = $db->fetch(
            "SELECT id FROM configuracion WHERE clave = ?",
            [$clave]
        );
        
        if ($existe) {
            $db->update(
                'configuracion',
                ['valor' => $valor],
                'clave = ?',
                [$clave]
            );
        } else {
            $db->insert('configuracion', [
                'clave' => $clave,
                'valor' => $valor,
                'tipo' => 'string',
                'descripcion' => ''
            ]);
        }
        
        // Sincronizar con Redis solo si la clase existe
        if ($clave === 'bot_activo' && class_exists('Redis')) {
            try {
                @$redis = new Redis();
                if (@$redis->connect('127.0.0.1', 6379)) {
                    @$redis->auth('cellcom538@@');
                    @$redis->set('whatsapp:bot_activo', $valor);
                    @$redis->close();
                }
            } catch (Exception $e) {
                // Silenciar error de Redis
            }
        }
    }
    
    $db->insert('logs', [
        'usuario_id' => $_SESSION['user_id'],
        'accion' => 'actualizar_configuracion',
        'descripcion' => 'Configuración actualizada',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar']);
}

exit;