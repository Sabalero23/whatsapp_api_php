<?php
/**
 * Cliente PHP completo para WhatsApp Web API
 * Incluye todas las funcionalidades de WhatsApp Web
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;

class WhatsAppClient
{
    private string $apiUrl;
    private string $apiKey;
    private PredisClient $redis;
    private int $timeout = 180;
    private int $maxRetries = 3;

    public function __construct(string $apiUrl, string $apiKey, array $redisConfig = [])
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        
        $this->redis = new PredisClient([
            'scheme' => 'tcp',
            'host'   => $redisConfig['host'] ?? '127.0.0.1',
            'port'   => $redisConfig['port'] ?? 6379,
            'password' => $redisConfig['password'] ?? null,
        ]);
    }

    // ==================== ESTADO Y CONEXIÓN ====================

    public function getStatus(): array
{
    // Tu API no tiene /api/status, así que usamos /api/chats como verificación
    try {
        $chats = $this->makeRequest('GET', '/api/chats?limit=1');
        
        return [
            'success' => true,
            'isReady' => isset($chats['chats']) && is_array($chats['chats']),
            'authenticated' => isset($chats['chats']) && is_array($chats['chats'])
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'isReady' => false,
            'authenticated' => false,
            'error' => $e->getMessage()
        ];
    }
}

public function isReady(): bool
{
    try {
        // Intentar obtener chats directamente
        // Si funciona, estamos conectados
        $chats = $this->makeRequest('GET', '/api/chats?limit=1');
        
        // Si recibimos un array de chats, estamos listos
        return isset($chats['chats']) && is_array($chats['chats']);
        
    } catch (Exception $e) {
        // Si hay cualquier error, no estamos listos
        return false;
    }
}

    public function getQRCode(): ?string
    {
        try {
            $response = $this->makeRequest('GET', '/api/qr');
            return $response['qr'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function logout(): array
    {
        return $this->makeRequest('POST', '/api/logout');
    }

    public function restart(): array
    {
        return $this->makeRequest('POST', '/api/restart');
    }

    // ==================== INFORMACIÓN DE CUENTA ====================

    public function getMe(): array
    {
        return $this->makeRequest('GET', '/api/me');
    }

    public function getPhoneNumber(): ?string
    {
        try {
            $me = $this->getMe();
            return $me['number'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    // ==================== ENVÍO DE MENSAJES ====================

    public function sendMessage(string $to, string $message): array
    {
        if (!$this->isReady()) {
            throw new Exception('WhatsApp no está listo');
        }

        return $this->makeRequest('POST', '/api/send', [
            'to' => $this->formatNumber($to),
            'message' => $message
        ]);
    }

    public function sendMediaMessage(string $to, string $message, string $mediaPath, ?string $filename = null): array
    {
        if (!file_exists($mediaPath)) {
            throw new Exception("Archivo no encontrado: $mediaPath");
        }

        return $this->makeRequest('POST', '/api/send-media', [
            'to' => $this->formatNumber($to),
            'message' => $message,
            'mediaPath' => $mediaPath,
            'filename' => $filename
        ]);
    }

    public function sendImage(string $to, string $imagePath, ?string $caption = null): array
    {
        return $this->sendMediaMessage($to, $caption ?? '', $imagePath);
    }

    public function sendVideo(string $to, string $videoPath, ?string $caption = null): array
    {
        return $this->sendMediaMessage($to, $caption ?? '', $videoPath);
    }

    public function sendAudio(string $to, string $audioPath): array
    {
        return $this->sendMediaMessage($to, '', $audioPath);
    }

    public function sendDocument(string $to, string $documentPath, ?string $filename = null): array
    {
        return $this->sendMediaMessage($to, '', $documentPath, $filename);
    }

    public function sendLocation(string $to, float $latitude, float $longitude, ?string $description = null): array
    {
        return $this->makeRequest('POST', '/api/send-location', [
            'to' => $this->formatNumber($to),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'description' => $description
        ]);
    }

    public function sendContact(string $to, array $contactData): array
    {
        return $this->makeRequest('POST', '/api/send-contact', [
            'to' => $this->formatNumber($to),
            'contact' => $contactData
        ]);
    }

    public function sendButtons(string $to, string $message, array $buttons): array
    {
        return $this->makeRequest('POST', '/api/send-buttons', [
            'to' => $this->formatNumber($to),
            'message' => $message,
            'buttons' => $buttons
        ]);
    }

    public function sendList(string $to, string $message, string $buttonText, array $sections): array
    {
        return $this->makeRequest('POST', '/api/send-list', [
            'to' => $this->formatNumber($to),
            'message' => $message,
            'buttonText' => $buttonText,
            'sections' => $sections
        ]);
    }

    public function replyToMessage(string $messageId, string $message): array
    {
        return $this->makeRequest('POST', '/api/reply', [
            'messageId' => $messageId,
            'message' => $message
        ]);
    }

    public function forwardMessage(string $messageId, string $to): array
    {
        return $this->makeRequest('POST', '/api/forward', [
            'messageId' => $messageId,
            'to' => $this->formatNumber($to)
        ]);
    }

    // ==================== MENSAJES CON COLA ====================

    public function queueMessage(string $to, string $message, ?array $media = null): string
    {
        $messageId = 'msg_' . time() . '_' . bin2hex(random_bytes(4));
        
        $data = [
            'to' => $this->formatNumber($to),
            'message' => $message,
            'media' => $media,
            'messageId' => $messageId
        ];

        $this->redis->lpush('whatsapp:outgoing_queue', json_encode($data));
        
        return $messageId;
    }

    public function getMessageResult(string $messageId, int $timeout = 30): ?array
    {
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            $result = $this->redis->get("whatsapp:result:$messageId");
            
            if ($result) {
                return json_decode($result, true);
            }
            
            sleep(1);
        }
        
        return null;
    }

    // ==================== GESTIÓN DE MENSAJES ====================

    public function deleteMessage(string $messageId, bool $forEveryone = false): array
    {
        return $this->makeRequest('POST', '/api/delete-message', [
            'messageId' => $messageId,
            'forEveryone' => $forEveryone
        ]);
    }

    public function starMessage(string $messageId): array
    {
        return $this->makeRequest('POST', '/api/star-message', [
            'messageId' => $messageId
        ]);
    }

    public function unstarMessage(string $messageId): array
    {
        return $this->makeRequest('POST', '/api/unstar-message', [
            'messageId' => $messageId
        ]);
    }

    public function markAsRead(string $chatId): array
{
    // Asegurar formato correcto
    if (!strpos($chatId, '@')) {
        $chatId = $this->formatNumber($chatId) . '@c.us';
    }
    
    return $this->makeRequest('POST', '/api/mark-read', [
        'chatId' => $chatId
    ]);
}

public function markAllAsRead(): array
{
    return $this->makeRequest('POST', '/api/mark-all-read');
}


    public function markAsUnread(string $chatId): array
{
    // Asegurar formato correcto
    if (!strpos($chatId, '@')) {
        $chatId = $this->formatNumber($chatId) . '@c.us';
    }
    
    return $this->makeRequest('POST', '/api/mark-unread', [
        'chatId' => $chatId
    ]);
}

    // ==================== CHATS ====================

    public function getChats(int $limit = 50): array
    {
        return $this->makeRequest('GET', "/api/chats?limit=$limit");
    }

    public function getChat(string $chatId): array
    {
        return $this->makeRequest('GET', '/api/chat/' . $this->formatNumber($chatId));
    }

    public function getChatMessages(string $chatId, int $limit = 50): array
{
    // Asegurar formato correcto
    if (!strpos($chatId, '@')) {
        $chatId = $this->formatNumber($chatId) . '@c.us';
    }
    
    // Codificar el chatId para la URL
    $encodedChatId = urlencode($chatId);
    
    return $this->makeRequest('GET', "/api/chat/{$encodedChatId}/messages?limit={$limit}");
}


    public function searchMessages(string $query, ?string $chatId = null, int $limit = 50): array
    {
        $params = ['query' => $query, 'limit' => $limit];
        if ($chatId) {
            $params['chatId'] = $this->formatNumber($chatId);
        }
        return $this->makeRequest('POST', '/api/search-messages', $params);
    }

    public function archiveChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/archive-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    public function unarchiveChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/unarchive-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    public function pinChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/pin-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    public function unpinChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/unpin-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    public function muteChat(string $chatId, ?int $duration = null): array
    {
        return $this->makeRequest('POST', '/api/mute-chat', [
            'chatId' => $this->formatNumber($chatId),
            'duration' => $duration
        ]);
    }

    public function unmuteChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/unmute-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    public function clearChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/clear-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    public function deleteChat(string $chatId): array
    {
        return $this->makeRequest('POST', '/api/delete-chat', [
            'chatId' => $this->formatNumber($chatId)
        ]);
    }

    // ==================== CONTACTOS ====================

    public function getContacts(): array
    {
        return $this->makeRequest('GET', '/api/contacts');
    }

    public function getContact(string $contactId): array
    {
        return $this->makeRequest('GET', '/api/contact/' . $this->formatNumber($contactId));
    }

    public function getContactProfilePicture(string $contactId): ?string
    {
        try {
            $response = $this->makeRequest('GET', '/api/contact/' . $this->formatNumber($contactId) . '/profile-pic');
            return $response['profilePic'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function blockContact(string $contactId): array
    {
        return $this->makeRequest('POST', '/api/block-contact', [
            'contactId' => $this->formatNumber($contactId)
        ]);
    }

    public function unblockContact(string $contactId): array
    {
        return $this->makeRequest('POST', '/api/unblock-contact', [
            'contactId' => $this->formatNumber($contactId)
        ]);
    }

    public function getBlockedContacts(): array
    {
        return $this->makeRequest('GET', '/api/blocked-contacts');
    }

    // ==================== GRUPOS ====================

    public function createGroup(string $name, array $participants): array
    {
        return $this->makeRequest('POST', '/api/create-group', [
            'name' => $name,
            'participants' => array_map([$this, 'formatNumber'], $participants)
        ]);
    }

    public function getGroups(): array
    {
        return $this->makeRequest('GET', '/api/groups');
    }

    public function getGroup(string $groupId): array
    {
        return $this->makeRequest('GET', '/api/group/' . $groupId);
    }

    public function addParticipants(string $groupId, array $participants): array
    {
        return $this->makeRequest('POST', '/api/group/add-participants', [
            'groupId' => $groupId,
            'participants' => array_map([$this, 'formatNumber'], $participants)
        ]);
    }

    public function removeParticipant(string $groupId, string $participantId): array
    {
        return $this->makeRequest('POST', '/api/group/remove-participant', [
            'groupId' => $groupId,
            'participantId' => $this->formatNumber($participantId)
        ]);
    }

    public function promoteToAdmin(string $groupId, string $participantId): array
    {
        return $this->makeRequest('POST', '/api/group/promote', [
            'groupId' => $groupId,
            'participantId' => $this->formatNumber($participantId)
        ]);
    }

    public function demoteAdmin(string $groupId, string $participantId): array
    {
        return $this->makeRequest('POST', '/api/group/demote', [
            'groupId' => $groupId,
            'participantId' => $this->formatNumber($participantId)
        ]);
    }

    public function setGroupSubject(string $groupId, string $subject): array
    {
        return $this->makeRequest('POST', '/api/group/set-subject', [
            'groupId' => $groupId,
            'subject' => $subject
        ]);
    }

    public function setGroupDescription(string $groupId, string $description): array
    {
        return $this->makeRequest('POST', '/api/group/set-description', [
            'groupId' => $groupId,
            'description' => $description
        ]);
    }

    public function setGroupPicture(string $groupId, string $imagePath): array
    {
        return $this->makeRequest('POST', '/api/group/set-picture', [
            'groupId' => $groupId,
            'imagePath' => $imagePath
        ]);
    }

    public function leaveGroup(string $groupId): array
    {
        return $this->makeRequest('POST', '/api/group/leave', [
            'groupId' => $groupId
        ]);
    }

    public function getGroupInviteCode(string $groupId): ?string
    {
        try {
            $response = $this->makeRequest('GET', '/api/group/' . $groupId . '/invite-code');
            return $response['inviteCode'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function revokeGroupInviteCode(string $groupId): array
    {
        return $this->makeRequest('POST', '/api/group/revoke-invite', [
            'groupId' => $groupId
        ]);
    }

    // ==================== ESTADOS (STORIES) ====================

    public function sendStatus(string $message, ?string $mediaPath = null): array
    {
        return $this->makeRequest('POST', '/api/send-status', [
            'message' => $message,
            'mediaPath' => $mediaPath
        ]);
    }

    public function getStatuses(): array
    {
        return $this->makeRequest('GET', '/api/statuses');
    }

    // ==================== PERFIL ====================

    public function setProfileName(string $name): array
    {
        return $this->makeRequest('POST', '/api/set-profile-name', [
            'name' => $name
        ]);
    }

    public function setProfileStatus(string $status): array
    {
        return $this->makeRequest('POST', '/api/set-profile-status', [
            'status' => $status
        ]);
    }

    public function setProfilePicture(string $imagePath): array
    {
        return $this->makeRequest('POST', '/api/set-profile-picture', [
            'imagePath' => $imagePath
        ]);
    }

    public function removeProfilePicture(): array
    {
        return $this->makeRequest('POST', '/api/remove-profile-picture');
    }

    // ==================== VALIDACIÓN ====================

    public function validateNumber(string $number): bool
    {
        try {
            $response = $this->makeRequest('POST', '/api/validate', [
                'number' => $this->formatNumber($number)
            ]);
            
            return $response['valid'] ?? false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getNumberId(string $number): ?string
    {
        try {
            $response = $this->makeRequest('POST', '/api/get-number-id', [
                'number' => $this->formatNumber($number)
            ]);
            
            return $response['numberId'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    // ==================== MENSAJES ENTRANTES ====================

    public function getIncomingMessages(int $limit = 10): array
    {
        $messages = [];
        
        for ($i = 0; $i < $limit; $i++) {
            $messageJson = $this->redis->rpop('whatsapp:incoming_messages');
            
            if (!$messageJson) {
                break;
            }
            
            $messages[] = json_decode($messageJson, true);
        }
        
        return $messages;
    }

    public function listenForMessages(callable $callback, int $timeout = 0): void
    {
        $startTime = time();
        
        while (true) {
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                break;
            }
            
            $result = $this->redis->brpop(['whatsapp:incoming_messages'], 5);
            
            if ($result) {
                $message = json_decode($result[1], true);
                call_user_func($callback, $message);
            }
        }
    }

    // ==================== UTILIDADES ====================

    private function formatNumber(string $number): string
{
    // Si ya tiene @ (es un ID completo de chat o grupo), devolverlo tal cual
    if (strpos($number, '@') !== false) {
        return $number;
    }
    
    // Si contiene un guion, es un ID de grupo sin @g.us
    // Formato de grupo: XXXXXXXXXX-XXXXXXXXXX
    if (strpos($number, '-') !== false) {
        // Es un grupo, no tocar el guion
        return $number;
    }
    
    // Es un número de teléfono normal, limpiar solo caracteres especiales
    return preg_replace('/[^0-9]/', '', $number);
}

    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->apiUrl . $endpoint;
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $ch = curl_init();
                
                $headers = [
                    'Content-Type: application/json',
                    'X-API-Key: ' . $this->apiKey
                ];

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_CUSTOMREQUEST => $method
                ]);

                if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                
                curl_close($ch);

                if ($error) {
                    throw new Exception("Error de cURL: $error");
                }

                if ($httpCode >= 500) {
                    throw new Exception("Error del servidor: HTTP $httpCode");
                }

                $result = json_decode($response, true);

                if ($httpCode >= 400) {
                    $errorMsg = $result['error'] ?? 'Error desconocido';
                    throw new Exception("Error de API: $errorMsg (HTTP $httpCode)");
                }

                return $result ?? [];

            } catch (Exception $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts < $this->maxRetries) {
                    sleep(pow(2, $attempts));
                }
            }
        }

        throw new Exception(
            "Fallo después de {$this->maxRetries} intentos: " . 
            ($lastException ? $lastException->getMessage() : 'Error desconocido')
        );
    }

    public function __destruct()
    {
        if ($this->redis) {
            $this->redis->disconnect();
        }
    }
    
    /**
 * Enviar estado de texto
 */
public function sendTextStatus($text, $backgroundColor = '#25D366', $font = 'Arial'): array
{
    return $this->makeRequest('POST', '/api/send-status', [
        'text' => $text,
        'backgroundColor' => $backgroundColor,
        'font' => $font
    ]);
}

/**
 * Eliminar estado
 */
public function deleteStatus($statusId): array
{
    return $this->makeRequest('POST', '/api/delete-status', [
        'statusId' => $statusId
    ]);
}

/**
 * Obtener estados de contactos
 */
public function getContactStatuses(): array
{
    return $this->makeRequest('GET', '/api/statuses');
}
}