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
            case 'create':
                // Validar que el número no exista ya
                $existing = $db->fetch(
                    "SELECT id FROM contactos WHERE numero = ?",
                    [$input['numero']]
                );
                
                if ($existing) {
                    echo json_encode(['success' => false, 'error' => 'Este número ya está registrado']);
                    break;
                }
                
                $contactId = $db->insert('contactos', [
                    'numero' => $input['numero'],
                    'nombre' => $input['nombre'] ?? null,
                    'email' => $input['email'] ?? null,
                    'empresa' => $input['empresa'] ?? null,
                    'etiquetas' => $input['etiquetas'] ?? null,
                    'notas' => $input['notas'] ?? null
                ]);
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Crear contacto',
                    'descripcion' => "Contacto: {$input['numero']} - {$input['nombre']}",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode(['success' => true, 'id' => $contactId]);
                break;
                
            case 'update':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $db->update('contactos', [
                    'nombre' => $input['nombre'] ?? null,
                    'email' => $input['email'] ?? null,
                    'empresa' => $input['empresa'] ?? null,
                    'etiquetas' => $input['etiquetas'] ?? null,
                    'notas' => $input['notas'] ?? null
                ], 'id = ?', [$input['id']]);
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Actualizar contacto',
                    'descripcion' => "ID: {$input['id']} - {$input['nombre']}",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'delete':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                // Obtener info antes de eliminar para el log
                $contact = $db->fetch(
                    "SELECT numero, nombre FROM contactos WHERE id = ?",
                    [$input['id']]
                );
                
                $db->delete('contactos', 'id = ?', [$input['id']]);
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Eliminar contacto',
                    'descripcion' => "ID: {$input['id']} - {$contact['nombre']} ({$contact['numero']})",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'get':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $contact = $db->fetch(
                    "SELECT * FROM contactos WHERE id = ?",
                    [$input['id']]
                );
                
                if ($contact) {
                    echo json_encode(['success' => true, 'contact' => $contact]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Contacto no encontrado']);
                }
                break;
                
            case 'sync':
                // Sincronizar contactos de WhatsApp
                $waContacts = $whatsapp->getContacts();
                $count = 0;
                $updated = 0;
                
                if (!isset($waContacts['contacts']) || !is_array($waContacts['contacts'])) {
                    echo json_encode(['success' => false, 'error' => 'No se pudieron obtener contactos de WhatsApp']);
                    break;
                }
                
                foreach ($waContacts['contacts'] as $contact) {
                    if (!isset($contact['number']) || !$contact['isMyContact']) continue;
                    
                    $existing = $db->fetch(
                        "SELECT id, nombre FROM contactos WHERE numero = ?",
                        [$contact['number']]
                    );
                    
                    if (!$existing) {
                        // Insertar nuevo contacto
                        $name = $contact['name'] ?? $contact['pushname'] ?? null;
                        if ($name) {
                            $db->insert('contactos', [
                                'numero' => $contact['number'],
                                'nombre' => $name
                            ]);
                            $count++;
                        }
                    } else {
                        // Actualizar nombre si está vacío o es diferente
                        $waName = $contact['name'] ?? $contact['pushname'] ?? null;
                        if ($waName && (empty($existing['nombre']) || $existing['nombre'] !== $waName)) {
                            $db->update('contactos', [
                                'nombre' => $waName
                            ], 'id = ?', [$existing['id']]);
                            $updated++;
                        }
                    }
                }
                
                $db->insert('logs', [
                    'usuario_id' => $_SESSION['user_id'],
                    'accion' => 'Sincronizar contactos',
                    'descripcion' => "$count nuevos contactos, $updated actualizados",
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                echo json_encode([
                    'success' => true, 
                    'count' => $count, 
                    'updated' => $updated,
                    'total' => $count + $updated
                ]);
                break;
                
            case 'toggle_favorite':
                if (!isset($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $contact = $db->fetch(
                    "SELECT favorito FROM contactos WHERE id = ?",
                    [$input['id']]
                );
                
                $newStatus = !$contact['favorito'];
                
                $db->update('contactos', [
                    'favorito' => $newStatus
                ], 'id = ?', [$input['id']]);
                
                echo json_encode(['success' => true, 'favorito' => $newStatus]);
                break;
                
            case 'search':
                $query = $input['query'] ?? '';
                
                if (strlen($query) < 2) {
                    echo json_encode(['success' => false, 'error' => 'Consulta muy corta']);
                    break;
                }
                
                $contacts = $db->fetchAll(
                    "SELECT * FROM contactos 
                    WHERE nombre LIKE ? 
                    OR numero LIKE ? 
                    OR empresa LIKE ? 
                    OR email LIKE ?
                    ORDER BY nombre ASC 
                    LIMIT 50",
                    ["%$query%", "%$query%", "%$query%", "%$query%"]
                );
                
                echo json_encode(['success' => true, 'contacts' => $contacts]);
                break;
                
            case 'get_all_numbers':
                $contacts = $db->fetchAll(
                    "SELECT numero FROM contactos WHERE bloqueado = 0 ORDER BY nombre ASC"
                );
                
                $numbers = array_map(function($c) {
                    return $c['numero'];
                }, $contacts);
                
                echo json_encode(['success' => true, 'numbers' => $numbers, 'total' => count($numbers)]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log('Error en contacts.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Manejar peticiones GET
    try {
        if (isset($_GET['id'])) {
            // Obtener un contacto específico
            $contact = $db->fetch(
                "SELECT * FROM contactos WHERE id = ?",
                [$_GET['id']]
            );
            
            if ($contact) {
                echo json_encode(['success' => true, 'contact' => $contact]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Contacto no encontrado']);
            }
        } elseif (isset($_GET['numero'])) {
            // Buscar por número
            $numero = str_replace(['@c.us', '@g.us'], '', $_GET['numero']);
            $contact = $db->fetch(
                "SELECT * FROM contactos WHERE numero = ?",
                [$numero]
            );
            
            if ($contact) {
                echo json_encode(['success' => true, 'contact' => $contact]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Contacto no encontrado', 'exists' => false]);
            }
        } elseif (isset($_GET['search'])) {
            // Búsqueda
            $query = $_GET['search'];
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => false, 'error' => 'Consulta muy corta']);
                exit;
            }
            
            $contacts = $db->fetchAll(
                "SELECT * FROM contactos 
                WHERE nombre LIKE ? 
                OR numero LIKE ? 
                OR empresa LIKE ? 
                OR email LIKE ?
                ORDER BY nombre ASC 
                LIMIT 50",
                ["%$query%", "%$query%", "%$query%", "%$query%"]
            );
            
            echo json_encode(['success' => true, 'contacts' => $contacts]);
        } elseif (isset($_GET['all'])) {
            // Obtener todos los contactos (con límite)
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $contacts = $db->fetchAll(
                "SELECT * FROM contactos 
                ORDER BY nombre ASC 
                LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
            
            $total = $db->fetch("SELECT COUNT(*) as total FROM contactos")['total'];
            
            echo json_encode([
                'success' => true, 
                'contacts' => $contacts,
                'total' => $total
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
        }
    } catch (Exception $e) {
        error_log('Error en contacts.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}