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

// Cargar configuración
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

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Crear nuevo grupo
            $nombre = $input['nombre'] ?? '';
            $participantes = $input['participantes'] ?? [];
            
            if (empty($nombre) || empty($participantes)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->createGroup($nombre, $participantes);
            
            echo json_encode([
                'success' => true,
                'groupId' => $result['groupId'] ?? null,
                'message' => 'Grupo creado exitosamente'
            ]);
            break;
            
        case 'add_participants':
            // Agregar participantes a un grupo
            $groupId = $input['groupId'] ?? '';
            $participantes = $input['participantes'] ?? [];
            
            if (empty($groupId) || empty($participantes)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->addParticipants($groupId, $participantes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Participantes agregados exitosamente'
            ]);
            break;
            
        case 'remove_participant':
            // Remover participante de un grupo
            $groupId = $input['groupId'] ?? '';
            $participantId = $input['participantId'] ?? '';
            
            if (empty($groupId) || empty($participantId)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->removeParticipant($groupId, $participantId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Participante removido exitosamente'
            ]);
            break;
            
        case 'update_name':
            // Actualizar nombre del grupo
            $groupId = $input['groupId'] ?? '';
            $nombre = $input['nombre'] ?? '';
            
            if (empty($groupId) || empty($nombre)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->setGroupSubject($groupId, $nombre);
            
            echo json_encode([
                'success' => true,
                'message' => 'Nombre actualizado exitosamente'
            ]);
            break;
            
        case 'update_description':
            // Actualizar descripción del grupo
            $groupId = $input['groupId'] ?? '';
            $descripcion = $input['descripcion'] ?? '';
            
            if (empty($groupId) || empty($descripcion)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->setGroupDescription($groupId, $descripcion);
            
            echo json_encode([
                'success' => true,
                'message' => 'Descripción actualizada exitosamente'
            ]);
            break;
            
        case 'leave':
            // Salir del grupo
            $groupId = $input['groupId'] ?? '';
            
            if (empty($groupId)) {
                throw new Exception('Falta groupId');
            }
            
            $result = $whatsapp->leaveGroup($groupId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Has salido del grupo'
            ]);
            break;
            
        case 'get_invite_code':
            // Obtener código de invitación
            $groupId = $input['groupId'] ?? '';
            
            if (empty($groupId)) {
                throw new Exception('Falta groupId');
            }
            
            $inviteCode = $whatsapp->getGroupInviteCode($groupId);
            
            echo json_encode([
                'success' => true,
                'inviteCode' => $inviteCode,
                'inviteLink' => 'https://chat.whatsapp.com/' . $inviteCode
            ]);
            break;
            
        case 'revoke_invite':
            // Revocar link de invitación
            $groupId = $input['groupId'] ?? '';
            
            if (empty($groupId)) {
                throw new Exception('Falta groupId');
            }
            
            $result = $whatsapp->revokeGroupInviteCode($groupId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Link de invitación revocado'
            ]);
            break;
            
        case 'promote':
            // Promover a admin
            $groupId = $input['groupId'] ?? '';
            $participantId = $input['participantId'] ?? '';
            
            if (empty($groupId) || empty($participantId)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->promoteToAdmin($groupId, $participantId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Participante promovido a administrador'
            ]);
            break;
            
        case 'demote':
            // Quitar admin
            $groupId = $input['groupId'] ?? '';
            $participantId = $input['participantId'] ?? '';
            
            if (empty($groupId) || empty($participantId)) {
                throw new Exception('Faltan parámetros requeridos');
            }
            
            $result = $whatsapp->demoteAdmin($groupId, $participantId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Permisos de administrador removidos'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    error_log('Error en groups.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}