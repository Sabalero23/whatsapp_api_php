<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Sistema de chats individuales con multimedia completo
$chatsData = [];
$allChats = [];

try {
    $chatsResponse = $whatsapp->getChats(100);
    $chatsData = $chatsResponse['chats'] ?? [];
    
    // Filtrar solo chats individuales (excluir grupos)
    foreach ($chatsData as $chat) {
        if (!($chat['isGroup'] ?? false)) {
            $allChats[] = [
                'id' => $chat['id'],
                'name' => $chat['name'],
                'unreadCount' => $chat['unreadCount'] ?? 0,
                'timestamp' => $chat['timestamp'] ?? 0
            ];
        }
    }
    
    usort($allChats, function($a, $b) {
        return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
    });
    
} catch (Exception $e) {
    error_log($e->getMessage());
}

$selectedChat = $_GET['chat'] ?? null;
$messages = [];
$contactInfo = null;

if ($selectedChat) {
    try {
        $numero = str_replace(['@c.us', '@g.us'], '', $selectedChat);
        $contactInfo = $db->fetch("SELECT * FROM contactos WHERE numero = ?", [$numero]);
        
        if (!$contactInfo) {
            $contactInfo = ['numero' => $numero];
        }
        
        $messagesData = $whatsapp->getChatMessages($selectedChat, 50);
        $messages = $messagesData['messages'] ?? [];
        
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
?>

<style>
/* ============================================
   ESTILOS OPTIMIZADOS PARA SISTEMA DE CHATS
   Reemplaza todo el bloque <style> existente
   ============================================ */

:root {
    --chat-primary: #25D366;
    --chat-primary-dark: #20BA5A;
    --chat-bg: #f0f2f5;
    --chat-bubble-sent: #d9fdd3;
    --chat-bubble-received: #ffffff;
    --chat-header: #ededed;
    --chat-hover: #f5f6f6;
    --chat-active: #ebebeb;
    --chat-border: #e9edef;
    --chat-text: #111b21;
    --chat-text-light: #667781;
    --chat-unread: #25D366;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.08);
    --shadow-md: 0 2px 6px rgba(0,0,0,0.12);
}

.chats-container {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    height: calc(100vh - 160px);
    min-height: 500px;
    max-height: 800px;
    max-width: 1600px;
    width: 100%;
    margin: 0 auto;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    will-change: transform;
}

/* En móvil, ocupar toda la pantalla */
@media (max-width: 768px) {
    .chats-container {
        height: 100vh !important;
        max-height: 100vh !important;
        min-height: 100vh !important;
    }
}

/* ===== SIDEBAR ===== */
.chats-sidebar {
    border-right: 1px solid var(--chat-border);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: white;
}

.chats-header {
    padding: 18px 20px;
    background: var(--chat-header);
    border-bottom: 1px solid var(--chat-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chats-header h3 {
    margin: 0;
    font-size: 1.15em;
    font-weight: 600;
    color: var(--chat-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.chats-search {
    padding: 8px 12px;
    background: white;
    border-bottom: 1px solid var(--chat-border);
}

.chats-search input {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid var(--chat-border);
    border-radius: 8px;
    font-size: 0.9em;
    transition: all 0.2s;
    background: var(--chat-bg);
}

.chats-search input:focus {
    outline: none;
    border-color: var(--chat-primary);
    background: white;
}

.chats-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    contain: layout style paint;
}

.chats-list::-webkit-scrollbar {
    width: 6px;
}

.chats-list::-webkit-scrollbar-thumb {
    background: #b3b3b3;
    border-radius: 10px;
}

/* ===== CHAT ITEM ===== */
.chat-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--chat-border);
    cursor: pointer;
    transition: background 0.15s ease;
    display: flex;
    gap: 14px;
    text-decoration: none;
    color: inherit;
    position: relative;
}

.chat-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--chat-primary);
    opacity: 0;
    transition: opacity 0.2s;
}

.chat-item:hover {
    background: var(--chat-hover);
}

.chat-item.active {
    background: var(--chat-active);
}

.chat-item.active::before {
    opacity: 1;
}

.chat-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--chat-primary) 0%, #20BA5A 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4em;
    flex-shrink: 0;
    color: white;
    box-shadow: var(--shadow-sm);
}

.chat-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.chat-name {
    font-weight: 600;
    font-size: 0.95em;
    color: var(--chat-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-preview {
    font-size: 0.85em;
    color: var(--chat-text-light);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-unread {
    background: var(--chat-unread);
    color: white;
    border-radius: 12px;
    padding: 2px 7px;
    font-size: 0.7em;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
    box-shadow: var(--shadow-sm);
}

/* ===== CHAT CONTENT ===== */
.chat-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: var(--chat-bg);
}

.chat-header-bar {
    padding: 12px 20px;
    background: var(--chat-header);
    border-bottom: 1px solid var(--chat-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-sm);
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s;
}

.chat-header-info:hover {
    background: rgba(0,0,0,0.05);
}

.chat-header-details {
    flex: 1;
    min-width: 0;
}

.chat-header-name {
    font-weight: 600;
    font-size: 1em;
    color: var(--chat-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-header-number {
    font-size: 0.8em;
    color: var(--chat-text-light);
    margin-top: 2px;
}

.contact-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #e3f2fd;
    color: #1976d2;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: 600;
}

.not-saved-badge {
    background: #fff3e0;
    color: #f57c00;
}

.chat-actions {
    display: flex;
    gap: 6px;
}

/* ===== MESSAGES CONTAINER ===== */
.messages-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px;
    background: #efeae2;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" opacity="0.03"><path d="M0 0h50v50H0zM50 50h50v50H50z"/></svg>');
    scroll-behavior: smooth;
    contain: layout style paint;
}

.messages-container::-webkit-scrollbar {
    width: 6px;
}

.messages-container::-webkit-scrollbar-thumb {
    background: #b3b3b3;
    border-radius: 10px;
}

/* ===== MESSAGE BUBBLE ===== */
.message-bubble {
    max-width: 65%;
    margin-bottom: 8px;
    display: flex;
    clear: both;
    animation: messageSlide 0.2s ease-out;
}

@keyframes messageSlide {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-bubble.sent {
    margin-left: auto;
    justify-content: flex-end;
}

.message-bubble.received {
    margin-right: auto;
    justify-content: flex-start;
}

.message-bubble.sending {
    opacity: 0.6;
}

.bubble-content {
    background: var(--chat-bubble-received);
    padding: 8px 12px 6px 12px;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
    max-width: 100%;
    position: relative;
}

.message-bubble.sent .bubble-content {
    background: var(--chat-bubble-sent);
}

.message-bubble.sending .bubble-content {
    border: 1px dashed var(--chat-primary);
    background: rgba(37, 211, 102, 0.05);
}

.message-text {
    white-space: pre-wrap;
    word-break: break-word;
    margin-bottom: 4px;
    line-height: 1.4;
    color: var(--chat-text);
    font-size: 0.93em;
}

/* ===== MEDIA ===== */
.message-media {
    display: block;
    margin-bottom: 6px;
    border-radius: 8px;
    overflow: hidden;
    max-width: 100%;
}

.message-media img {
    max-width: 100%;
    max-height: 350px;
    display: block;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.message-media img:hover {
    transform: scale(1.02);
}

.message-media video,
.message-media audio {
    max-width: 100%;
    display: block;
    border-radius: 8px;
}

.message-time {
    font-size: 0.68em;
    color: var(--chat-text-light);
    text-align: right;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
    margin-top: 2px;
}

/* ===== MEDIA PREVIEW ===== */
.media-preview {
    padding: 12px 16px;
    border-top: 1px solid var(--chat-border);
    background: white;
    display: none;
    align-items: center;
    gap: 12px;
    box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
}

.media-preview.active {
    display: flex;
}

.media-preview img,
.media-preview video {
    max-height: 70px;
    border-radius: 6px;
    box-shadow: var(--shadow-sm);
}

.media-preview-info {
    flex: 1;
    min-width: 0;
}

.media-preview-name {
    font-size: 0.88em;
    font-weight: 600;
    color: var(--chat-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.media-preview-size {
    font-size: 0.78em;
    color: var(--chat-text-light);
    margin-top: 2px;
}

.remove-media {
    background: #f44336;
    color: white;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.remove-media:hover {
    background: #d32f2f;
    transform: scale(1.05);
}

/* ===== INPUT AREA ===== */
.message-input-container {
    padding: 10px 16px;
    background: var(--chat-header);
    display: flex;
    gap: 8px;
    align-items: flex-end;
    box-shadow: 0 -1px 3px rgba(0,0,0,0.05);
}

.message-input {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 24px;
    font-size: 0.93em;
    resize: none;
    max-height: 100px;
    font-family: inherit;
    background: white;
    transition: box-shadow 0.2s;
}

.message-input:focus {
    outline: none;
    box-shadow: 0 0 0 2px var(--chat-primary);
}

.btn-send {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: var(--chat-primary);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15em;
    transition: all 0.25s ease;
    box-shadow: var(--shadow-md);
}

.btn-send:hover {
    background: var(--chat-primary-dark);
    transform: scale(1.08);
}

.btn-send:active {
    transform: scale(0.95);
}

.btn-send:disabled {
    background: #b3b3b3;
    cursor: not-allowed;
    transform: scale(1);
    box-shadow: none;
}

/* ===== EMPTY STATE ===== */
.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--chat-text-light);
}

.empty-chat i {
    font-size: 4em;
    margin-bottom: 16px;
    opacity: 0.2;
}

/* ===== MEDIA MODAL ===== */
.media-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.95);
    z-index: 10001;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.media-modal.active {
    display: flex;
}

.media-modal-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
}

.media-modal-header {
    position: absolute;
    top: -60px;
    right: 0;
    display: flex;
    gap: 12px;
}

.media-modal-btn {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}

.media-modal-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.1);
}

.media-modal-media {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 8px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .chats-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 80px);
        max-height: none;
        min-height: calc(100vh - 80px);
        border-radius: 0;
        margin-top: 0;
    }
    
    .chats-sidebar {
        display: <?= $selectedChat ? 'none' : 'flex' ?>;
    }
    
    .chat-content {
        display: <?= $selectedChat ? 'flex' : 'none' ?>;
    }
    
    .message-bubble {
        max-width: 85%;
    }
    
    .chat-avatar {
        width: 45px;
        height: 45px;
        font-size: 1.2em;
    }
    
    .chat-header-bar {
        padding: 10px 15px;
    }
    
    .messages-container {
        padding: 15px;
    }
    
    .message-input-container {
        padding: 8px 12px;
    }
    
    .btn-send {
        width: 44px;
        height: 44px;
    }
}

@media (max-width: 480px) {
    .chats-container {
        height: 100vh;
        min-height: 100vh;
    }
    
    .message-bubble {
        max-width: 90%;
    }
    
    .chat-name {
        font-size: 0.9em;
    }
    
    .chat-header-number {
        font-size: 0.75em;
    }
}

/* ===== LOADING STATES ===== */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}

/* ===== PERFORMANCE OPTIMIZATIONS ===== */
* {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.chats-list,
.messages-container {
    transform: translateZ(0);
    -webkit-overflow-scrolling: touch;
}
</style>

<div class="chats-container">
    <div class="chats-sidebar">
        <div class="chats-header">
            <h3 style="margin: 0; font-size: 1.1em;">
                <i class="fas fa-comments"></i> Chats
            </h3>
            <button class="btn btn-icon" onclick="showNewChatModal()" title="Nuevo chat">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        
        <div class="chats-search">
            <input type="text" id="searchChats" placeholder="Buscar..." autocomplete="off">
        </div>
        
        <div class="chats-list" id="chatsList">
            <?php if (empty($allChats)): ?>
                <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                    <i class="fas fa-comments"></i>
                    <p>No hay chats</p>
                </div>
            <?php else: ?>
                <?php foreach ($allChats as $chat): ?>
                    <a href="?page=chats&chat=<?= urlencode($chat['id']) ?>" 
                       class="chat-item <?= $selectedChat === $chat['id'] ? 'active' : '' ?>"
                       data-chat-id="<?= htmlspecialchars($chat['id']) ?>">
                        <div class="chat-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="chat-info">
                            <div class="chat-header">
                                <?php
// Buscar nombre del contacto en la base de datos
$chatNumero = str_replace(['@c.us', '@g.us'], '', $chat['id']);
$contactoNombre = $db->fetch("SELECT nombre FROM contactos WHERE numero = ?", [$chatNumero]);
$displayName = $contactoNombre ? $contactoNombre['nombre'] : $chat['name'];
?>
<span class="chat-name"><?= htmlspecialchars($displayName) ?></span>
                                <?php if (($chat['unreadCount'] ?? 0) > 0): ?>
                                    <span class="chat-unread"><?= $chat['unreadCount'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="chat-preview">
                                <?php if ($chat['timestamp']): ?>
                                    <?php
if ($chat['timestamp']) {
    echo date('H:i', $chat['timestamp']);
}
?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="chat-content">
        <?php if ($selectedChat): ?>
            <div class="chat-header-bar">
                <div class="chat-header-info" onclick="openContactModal()">
                    <div class="chat-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="chat-header-details">
                        <div class="chat-header-name">
                            <?= htmlspecialchars($contactInfo['nombre'] ?? explode('@', $selectedChat)[0]) ?>
                            <?php if ($contactInfo && isset($contactInfo['nombre'])): ?>
                                <span class="contact-badge">
                                    <i class="fas fa-address-book"></i> Contacto
                                </span>
                            <?php else: ?>
                                <span class="contact-badge not-saved-badge">
                                    <i class="fas fa-user-plus"></i> No guardado
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="chat-header-number">
                            <?= htmlspecialchars(explode('@', $selectedChat)[0]) ?>
                        </div>
                    </div>
                </div>
                <div class="chat-actions">
                    <button class="btn btn-icon" onclick="markChatAsRead('<?= htmlspecialchars($selectedChat) ?>')" title="Marcar como leído">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <a href="?page=chats" class="btn btn-icon" title="Cerrar">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <?php if (empty($messages)): ?>
                    <div class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <p>No hay mensajes</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-bubble <?= $msg['fromMe'] ? 'sent' : 'received' ?>" data-message-id="<?= htmlspecialchars($msg['id']) ?>">
                            <div class="bubble-content">
                                <?php if ($msg['hasMedia'] && !empty($msg['mediaUrl'])): ?>
                                    <?php
                                    $mediaType = $msg['type'] ?? 'document';
                                    $proxyUrl = 'api/media-proxy.php?file=' . urlencode($msg['mediaUrl']);
                                    ?>
                                    
                                    <?php if ($mediaType === 'image'): ?>
                                        <div class="message-media">
                                            <img src="<?= htmlspecialchars($proxyUrl) ?>" 
                                                 alt="Imagen" 
                                                 onclick="openMediaModal('<?= htmlspecialchars($proxyUrl, ENT_QUOTES) ?>', 'image')">
                                        </div>
                                    <?php elseif ($mediaType === 'video'): ?>
                                        <div class="message-media">
                                            <video src="<?= htmlspecialchars($proxyUrl) ?>" controls></video>
                                        </div>
                                    <?php elseif ($mediaType === 'audio' || $mediaType === 'ptt'): ?>
                                        <div class="message-media">
                                            <audio src="<?= htmlspecialchars($proxyUrl) ?>" controls></audio>
                                        </div>
                                    <?php else: ?>
                                        <div class="message-media">
                                            <i class="fas fa-file"></i>
                                            <a href="<?= htmlspecialchars($proxyUrl) ?>" target="_blank">
                                                Archivo adjunto
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($msg['body']): ?>
                                    <div class="message-text">
                                        <?= nl2br(htmlspecialchars($msg['body'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message-time">
                                    <?php
// Asegurarse de que el timestamp esté en la zona correcta
$messageTime = $msg['timestamp'];
// Si el timestamp viene en segundos Unix
$formattedTime = date('H:i', $messageTime);
?>
<?= $formattedTime ?>
                                    <?php if ($msg['fromMe']): ?>
                                        <span><i class="fas fa-check-double"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="media-preview" id="mediaPreview">
                <div id="mediaPreviewContent"></div>
                <div class="media-preview-info" id="mediaPreviewInfo"></div>
                <button class="remove-media" onclick="removeMediaPreview()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="message-input-container">
                <button class="btn btn-icon" onclick="document.getElementById('fileInput').click()" title="Adjuntar">
                    <i class="fas fa-paperclip"></i>
                </button>
                <input type="file" id="fileInput" style="display: none" 
                       accept="image/*,video/*,audio/*,.pdf,.doc,.docx" 
                       onchange="handleFileSelect(event)">
                <textarea 
                    id="messageInput" 
                    class="message-input" 
                    placeholder="Escribe un mensaje..."
                    rows="1"></textarea>
                <button class="btn-send" id="sendBtn" onclick="sendChatMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="empty-chat">
                <i class="fas fa-comments"></i>
                <p>Selecciona un chat</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="mediaModal" class="media-modal">
    <div class="media-modal-content">
        <div class="media-modal-header">
            <button class="media-modal-btn" onclick="closeMediaModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="mediaModalBody"></div>
    </div>
</div>

<div id="newChatModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3><i class="fas fa-comment"></i> Nuevo Chat</h3>
            <button onclick="closeModal('newChatModal')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Número (ej: 5493482309495)</label>
                <input type="text" id="newChatNumber" class="form-control" placeholder="549...">
            </div>
            <div class="form-group">
                <label>Mensaje</label>
                <textarea id="newChatMessage" class="form-control" rows="4"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('newChatModal')" class="btn">Cancelar</button>
            <button onclick="sendToNewNumber()" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Enviar
            </button>
        </div>
    </div>
</div>

<div id="contactModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-user-circle"></i>
                <span id="contactModalTitle">Información del contacto</span>
            </h3>
            <button onclick="closeContactModal()" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" id="contactModalBody"></div>
        <div class="modal-footer" id="contactModalFooter"></div>
    </div>
</div>

<script>
/* ============================================
   JAVASCRIPT OPTIMIZADO PARA SISTEMA DE CHATS
   Reemplaza todo el bloque <script> existente
   ============================================ */

// ===== CONFIGURACIÓN GLOBAL =====
const CONFIG = {
    MESSAGE_UPDATE_INTERVAL: 3000,
    CHATS_UPDATE_INTERVAL: 5000,
    SCROLL_THRESHOLD: 100,
    MAX_FILE_SIZE: 16 * 1024 * 1024
};

// ===== ESTADO DE LA APLICACIÓN =====
const AppState = {
    selectedChatId: '<?= htmlspecialchars($selectedChat ?? '') ?>',
    currentChatNumber: '<?= str_replace(['@c.us', '@g.us'], '', $selectedChat ?? '') ?>',
    currentContactInfo: <?= json_encode($contactInfo) ?>,
    lastMessageTimestamp: 0,
    messageIds: new Set(),
    selectedFile: null,
    lastChatsData: null,
    contactNamesCache: {},
    updateInterval: null,
    chatsUpdateInterval: null
};

// ===== UTILIDADES =====
const Utils = {
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    },
    
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    formatChatTime(timestamp) {
        if (!timestamp) return '';
        
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diff = now - date;
        
        // Hoy
        if (date.toDateString() === now.toDateString()) {
            return date.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        }
        
        // Ayer
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === yesterday.toDateString()) {
            return 'Ayer';
        }
        
        // Esta semana
        if (diff < 7 * 24 * 60 * 60 * 1000) {
            return ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][date.getDay()];
        }
        
        // Fecha completa
        return date.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' });
    },
    
    shouldAutoScroll(container) {
        const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
        return distanceFromBottom < CONFIG.SCROLL_THRESHOLD;
    },
    
    scrollToBottom(container, smooth = false) {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }
};

// ===== GESTOR DE MENSAJES =====
const MessageManager = {
    async update() {
        if (!AppState.selectedChatId) return;
        
        try {
            const url = `api/get-chat-messages.php?chatId=${encodeURIComponent(AppState.selectedChatId)}&after=${AppState.lastMessageTimestamp}&t=${Date.now()}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success && data.messages?.length > 0) {
                this.addNewMessages(data.messages, data.lastTimestamp);
            }
        } catch (error) {
            console.error('Error actualizando mensajes:', error);
        }
    },
    
    addNewMessages(messages, lastTimestamp) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        const wasAtBottom = Utils.shouldAutoScroll(container);
        
        // Remover mensajes temporales
        document.querySelectorAll('.message-bubble.sending').forEach(b => b.remove());
        
        let newMessagesCount = 0;
        const fragment = document.createDocumentFragment();
        
        messages.forEach(msg => {
            if (AppState.messageIds.has(msg.id)) return;
            
            AppState.messageIds.add(msg.id);
            newMessagesCount++;
            
            const bubble = this.createBubble(msg);
            fragment.appendChild(bubble);
            
            // Notificación para mensajes entrantes
            if (!msg.fromMe) {
                this.showMessageNotification();
            }
        });
        
        if (fragment.childNodes.length > 0) {
            container.appendChild(fragment);
            
            // Animar entrada
            requestAnimationFrame(() => {
                container.querySelectorAll('.message-bubble:not([data-animated])').forEach(bubble => {
                    bubble.dataset.animated = 'true';
                });
            });
        }
        
        if (lastTimestamp) {
            AppState.lastMessageTimestamp = lastTimestamp;
        }
        
        if (newMessagesCount > 0) {
            if (wasAtBottom) {
                setTimeout(() => Utils.scrollToBottom(container, true), 100);
            }
            
            // Actualizar lista de chats
            ChatListManager.update(true);
        }
    },
    
    createBubble(msg) {
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${msg.fromMe ? 'sent' : 'received'}`;
        bubble.dataset.messageId = msg.id;
        
        const time = msg.timestamp 
            ? new Date(msg.timestamp * 1000).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })
            : new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        
        let mediaHtml = '';
        if (msg.hasMedia && msg.mediaUrl) {
            mediaHtml = this.createMediaHtml(msg);
        }
        
        bubble.innerHTML = `
            <div class="bubble-content">
                ${mediaHtml}
                ${msg.body ? `<div class="message-text">${Utils.escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>` : ''}
                <div class="message-time">
                    ${time}
                    ${msg.fromMe ? '<span><i class="fas fa-check-double"></i></span>' : ''}
                </div>
            </div>
        `;
        
        return bubble;
    },
    
    createMediaHtml(msg) {
        const mediaType = msg.type || 'document';
        const proxyUrl = `api/media-proxy.php?file=${encodeURIComponent(msg.mediaUrl)}`;
        
        switch(mediaType) {
            case 'image':
                return `<div class="message-media">
                    <img src="${Utils.escapeHtml(proxyUrl)}" alt="Imagen" 
                         onclick="MediaModal.open('${Utils.escapeHtml(proxyUrl)}', 'image')" loading="lazy">
                </div>`;
            case 'video':
                return `<div class="message-media">
                    <video src="${Utils.escapeHtml(proxyUrl)}" controls preload="metadata"></video>
                </div>`;
            case 'audio':
            case 'ptt':
                return `<div class="message-media">
                    <audio src="${Utils.escapeHtml(proxyUrl)}" controls preload="metadata"></audio>
                </div>`;
            default:
                return `<div class="message-media">
                    <i class="fas fa-file"></i>
                    <a href="${Utils.escapeHtml(proxyUrl)}" target="_blank">Archivo adjunto</a>
                </div>`;
        }
    },
    
    showMessageNotification() {
        if (typeof showNotification === 'function') {
            showNotification('💬 Nuevo mensaje', 'info');
        }
    }
};

// ===== GESTOR DE ENVÍO DE MENSAJES =====
const SendManager = {
    async sendText() {
        const input = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const message = input.value.trim();
        
        if (!message && !AppState.selectedFile) return;
        if (!AppState.selectedChatId) return;
        
        if (AppState.selectedFile) {
            await this.sendMedia(message);
        } else {
            await this.sendTextMessage(message, input, sendBtn);
        }
    },
    
    async sendTextMessage(message, input, sendBtn) {
        const container = document.getElementById('messagesContainer');
        
        // Mensaje optimista
        const optimisticMessage = {
            id: 'temp_' + Date.now(),
            body: message,
            fromMe: true,
            timestamp: Math.floor(Date.now() / 1000)
        };
        
        const bubble = MessageManager.createBubble(optimisticMessage);
        bubble.classList.add('sending');
        container.appendChild(bubble);
        Utils.scrollToBottom(container, true);
        
        input.value = '';
        input.style.height = 'auto';
        this.toggleButton(sendBtn, true);
        
        try {
            const response = await fetch('api/send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    to: AppState.selectedChatId,
                    message: message
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                bubble.classList.remove('sending');
                setTimeout(() => MessageManager.update(), 1000);
            } else {
                bubble.remove();
                this.showError(result.error);
            }
        } catch (error) {
            bubble.remove();
            this.showError('Error de conexión');
        } finally {
            this.toggleButton(sendBtn, false);
        }
    },
    
    async sendMedia(caption) {
        const sendBtn = document.getElementById('sendBtn');
        this.toggleButton(sendBtn, true);
        
        const formData = new FormData();
        formData.append('file', AppState.selectedFile);
        formData.append('to', AppState.selectedChatId);
        formData.append('caption', caption);
        
        try {
            const response = await fetch('api/send-media.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('messageInput').value = '';
                MediaPreview.remove();
                this.showSuccess('Multimedia enviado');
                setTimeout(() => MessageManager.update(), 1000);
            } else {
                this.showError(result.error);
            }
        } catch (error) {
            this.showError('Error de conexión');
        } finally {
            this.toggleButton(sendBtn, false);
        }
    },
    
    toggleButton(button, disabled) {
        button.disabled = disabled;
        button.innerHTML = disabled 
            ? '<i class="fas fa-spinner fa-spin"></i>' 
            : '<i class="fas fa-paper-plane"></i>';
    },
    
    showError(message) {
        if (typeof showNotification === 'function') {
            showNotification('Error: ' + message, 'error');
        }
    },
    
    showSuccess(message) {
        if (typeof showNotification === 'function') {
            showNotification(message, 'success');
        }
    }
};

// ===== GESTOR DE LISTA DE CHATS =====
const ChatListManager = {
    hasChanged(newChats) {
        if (!AppState.lastChatsData) return true;
        if (newChats.length !== AppState.lastChatsData.length) return true;
        
        for (let i = 0; i < newChats.length; i++) {
            const newChat = newChats[i];
            const oldChat = AppState.lastChatsData.find(c => c.id === newChat.id);
            
            if (!oldChat) return true;
            if (newChat.timestamp !== oldChat.timestamp || 
                newChat.unreadCount !== oldChat.unreadCount) {
                return true;
            }
        }
        
        return false;
    },
    
    async update(forceUpdate = false) {
        try {
            const response = await fetch(`api/get-chats.php?t=${Date.now()}`);
            const data = await response.json();
            
            if (!data.success || !data.chats) return;
            
            const individualChats = this.filterValidChats(data.chats);
            
            if (!forceUpdate && !this.hasChanged(individualChats)) {
                return;
            }
            
            AppState.lastChatsData = JSON.parse(JSON.stringify(individualChats));
            this.render(individualChats);
            
            // Cargar nombres de contactos
            ContactManager.loadNames();
            
        } catch (error) {
            console.error('Error actualizando lista de chats:', error);
        }
    },
    
    filterValidChats(chats) {
        return chats.filter(chat => {
            if (chat.isGroup) return false;
            if (!chat.id || chat.id === '0@c.us') return false;
            const numero = chat.id.split('@')[0];
            return numero && numero !== '0' && numero.length >= 10;
        }).sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
    },
    
    render(chats) {
        const chatsList = document.getElementById('chatsList');
        if (!chatsList) return;
        
        const currentScroll = chatsList.scrollTop;
        const fragment = document.createDocumentFragment();
        
        chats.forEach(chat => {
            const chatElement = this.createChatElement(chat);
            fragment.appendChild(chatElement);
        });
        
        if (fragment.childNodes.length > 0) {
            chatsList.innerHTML = '';
            chatsList.appendChild(fragment);
        } else {
            chatsList.innerHTML = `
                <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                    <i class="fas fa-comments"></i>
                    <p>No hay chats</p>
                </div>
            `;
        }
        
        chatsList.scrollTop = currentScroll;
    },
    
    createChatElement(chat) {
        const isActive = AppState.selectedChatId === chat.id;
        const unreadCount = chat.unreadCount || 0;
        const timestamp = Utils.formatChatTime(chat.timestamp);
        const numero = chat.id.split('@')[0];
        const displayName = AppState.contactNamesCache[numero] || chat.name;
        const isNameLoaded = AppState.contactNamesCache[numero] ? 'true' : 'false';
        
        const a = document.createElement('a');
        a.href = `?page=chats&chat=${encodeURIComponent(chat.id)}`;
        a.className = `chat-item ${isActive ? 'active' : ''}`;
        a.dataset.chatId = chat.id;
        a.dataset.unread = unreadCount;
        
        a.innerHTML = `
            <div class="chat-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="chat-info">
                <div class="chat-header">
                    <span class="chat-name" data-chat-number="${Utils.escapeHtml(chat.id)}" data-name-loaded="${isNameLoaded}">
                        ${Utils.escapeHtml(displayName)}
                    </span>
                    ${unreadCount > 0 ? `<span class="chat-unread">${unreadCount}</span>` : ''}
                </div>
                <div class="chat-preview">${timestamp}</div>
            </div>
        `;
        
        return a;
    }
};

// ===== GESTOR DE CONTACTOS =====
const ContactManager = {
    async preloadAll() {
        try {
            const response = await fetch('api/get-all-contact-names.php');
            
            // Verificar que la respuesta sea exitosa
            if (!response.ok) {
                console.warn(`⚠️ Error HTTP: ${response.status}`);
                return;
            }
            
            // Verificar que la respuesta sea JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.warn('⚠️ La API no devolvió JSON válido');
                return;
            }
            
            const data = await response.json();
            
            if (data.success && Array.isArray(data.contactos)) {
                data.contactos.forEach(contacto => {
                    if (contacto.numero && contacto.nombre) {
                        AppState.contactNamesCache[contacto.numero] = contacto.nombre;
                    }
                });
                console.log(`✅ ${Object.keys(AppState.contactNamesCache).length} contactos precargados`);
            } else if (data.error) {
                console.warn('⚠️ Error al cargar contactos:', data.error);
            } else {
                console.warn('⚠️ No se recibieron contactos de la API');
            }
        } catch (error) {
            console.warn('⚠️ Error precargando contactos:', error.message);
        }
    },
    
    async loadNames() {
        const elements = document.querySelectorAll('.chat-name[data-chat-number]:not([data-name-loaded="true"])');
        if (elements.length === 0) return;
        
        for (const element of elements) {
            const chatId = element.dataset.chatNumber;
            let numero = chatId.includes('@') ? chatId.split('@')[0] : chatId;
            
            // Limpiar número (solo dígitos)
            numero = numero.replace(/[^0-9]/g, '');
            
            if (!numero || numero === '0' || numero.length < 10) {
                element.dataset.nameLoaded = 'true';
                continue;
            }
            
            // Verificar cache
            if (AppState.contactNamesCache[numero]) {
                element.textContent = AppState.contactNamesCache[numero];
                element.dataset.nameLoaded = 'true';
                continue;
            }
            
            // Cargar desde API
            try {
                const response = await fetch(`api/get-contact-name.php?numero=${encodeURIComponent(numero)}`);
                
                if (!response.ok) {
                    element.dataset.nameLoaded = 'true';
                    continue;
                }
                
                // Verificar que la respuesta sea JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    element.dataset.nameLoaded = 'true';
                    continue;
                }
                
                const data = await response.json();
                
                if (data.success && data.nombre) {
                    element.textContent = data.nombre;
                    AppState.contactNamesCache[numero] = data.nombre;
                }
                
                element.dataset.nameLoaded = 'true';
            } catch (error) {
                console.warn(`⚠️ No se pudo cargar nombre para ${numero}`);
                element.dataset.nameLoaded = 'true';
            }
        }
    },
    
    openModal() {
        const modal = document.getElementById('contactModal');
        const body = document.getElementById('contactModalBody');
        const footer = document.getElementById('contactModalFooter');
        
        if (AppState.currentContactInfo && AppState.currentContactInfo.nombre) {
            this.renderContactInfo(body, footer);
        } else {
            this.renderContactForm(body, footer);
        }
        
        modal.style.display = 'flex';
    },
    
    renderContactInfo(body, footer) {
        const info = AppState.currentContactInfo;
        body.innerHTML = `
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nombre</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${Utils.escapeHtml(info.nombre || '-')}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Número</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${Utils.escapeHtml(info.numero)}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${Utils.escapeHtml(info.email || '-')}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-building"></i> Empresa</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${Utils.escapeHtml(info.empresa || '-')}
                </div>
            </div>
        `;
        
        footer.innerHTML = `
            <button class="btn" onclick="ContactModal.close()">Cerrar</button>
            <button class="btn btn-primary" onclick="window.location.href='?page=contacts&edit=${info.id}'">
                <i class="fas fa-edit"></i> Editar
            </button>
        `;
    },
    
    renderContactForm(body, footer) {
        body.innerHTML = `
            <p style="margin-bottom: 20px; color: var(--gray);">
                <i class="fas fa-info-circle"></i> Este número no está guardado en tus contactos
            </p>
            <form id="formSaveContact">
                <div class="form-group">
                    <label>Número *</label>
                    <input type="text" name="numero" class="form-control" value="${Utils.escapeHtml(AppState.currentChatNumber)}" readonly>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Empresa</label>
                    <input type="text" name="empresa" class="form-control">
                </div>
            </form>
        `;
        
        footer.innerHTML = `
            <button class="btn" onclick="ContactModal.close()">Cancelar</button>
            <button class="btn btn-primary" onclick="ContactModal.save()">
                <i class="fas fa-save"></i> Guardar Contacto
            </button>
        `;
    },
    
    async save() {
        const form = document.getElementById('formSaveContact');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create', ...data })
            });
            
            const result = await response.json();
            
            if (result.success) {
                SendManager.showSuccess('Contacto guardado correctamente');
                this.close();
                setTimeout(() => location.reload(), 1500);
            } else {
                SendManager.showError(result.error);
            }
        } catch (error) {
            SendManager.showError('Error de conexión');
        }
    },
    
    close() {
        document.getElementById('contactModal').style.display = 'none';
    }
};

// ===== GESTOR DE PREVIEW DE MEDIOS =====
const MediaPreview = {
    handle(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        if (file.size > CONFIG.MAX_FILE_SIZE) {
            SendManager.showError('Archivo muy grande (máx 16MB)');
            event.target.value = '';
            return;
        }
        
        AppState.selectedFile = file;
        this.show(file);
    },
    
    show(file) {
        const preview = document.getElementById('mediaPreview');
        const content = document.getElementById('mediaPreviewContent');
        const info = document.getElementById('mediaPreviewInfo');
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                content.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else if (file.type.startsWith('video/')) {
            content.innerHTML = `<video src="${URL.createObjectURL(file)}" controls></video>`;
        } else {
            content.innerHTML = `<i class="fas fa-file" style="font-size: 2em; color: var(--primary);"></i>`;
        }
        
        const sizeKB = (file.size / 1024).toFixed(2);
        info.innerHTML = `
            <div class="media-preview-name">${Utils.escapeHtml(file.name)}</div>
            <div class="media-preview-size">${sizeKB} KB</div>
        `;
        
        preview.classList.add('active');
    },
    
    remove() {
        AppState.selectedFile = null;
        document.getElementById('fileInput').value = '';
        document.getElementById('mediaPreview').classList.remove('active');
        document.getElementById('mediaPreviewContent').innerHTML = '';
        document.getElementById('mediaPreviewInfo').innerHTML = '';
    }
};

// ===== MODAL DE MEDIOS =====
const MediaModal = {
    open(mediaUrl, mediaType) {
        const modal = document.getElementById('mediaModal');
        const body = document.getElementById('mediaModalBody');
        
        if (mediaType === 'image') {
            body.innerHTML = `<img src="${mediaUrl}" class="media-modal-media" alt="Imagen">`;
        } else if (mediaType === 'video') {
            body.innerHTML = `<video src="${mediaUrl}" class="media-modal-media" controls autoplay></video>`;
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    },
    
    close() {
        const modal = document.getElementById('mediaModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        document.getElementById('mediaModalBody').innerHTML = '';
    }
};

// ===== MODAL DE CONTACTO =====
const ContactModal = {
    open: () => ContactManager.openModal(),
    close: () => ContactManager.close(),
    save: () => ContactManager.save()
};

// ===== FUNCIONES GLOBALES (para mantener compatibilidad) =====
window.sendChatMessage = () => SendManager.sendText();
window.handleFileSelect = (e) => MediaPreview.handle(e);
window.removeMediaPreview = () => MediaPreview.remove();
window.openMediaModal = (url, type) => MediaModal.open(url, type);
window.closeMediaModal = () => MediaModal.close();
window.openContactModal = () => ContactModal.open();
window.closeContactModal = () => ContactModal.close();

window.markChatAsRead = async (chatId) => {
    try {
        await fetch('api/chats.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', chatId })
        });
        SendManager.showSuccess('Marcado como leído');
    } catch (error) {
        console.error('Error:', error);
    }
};

window.showNewChatModal = () => {
    document.getElementById('newChatModal').style.display = 'flex';
    document.getElementById('newChatNumber').focus();
};

window.closeModal = (modalId) => {
    document.getElementById(modalId).style.display = 'none';
};

window.sendToNewNumber = async () => {
    const number = document.getElementById('newChatNumber').value.trim().replace(/[^0-9]/g, '');
    const message = document.getElementById('newChatMessage').value.trim();
    
    if (!number || !message) {
        SendManager.showError('Completa todos los campos');
        return;
    }
    
    try {
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ to: number, message })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal('newChatModal');
            SendManager.showSuccess('Mensaje enviado');
            setTimeout(() => location.href = `?page=chats&chat=${number}@c.us`, 1500);
        } else {
            SendManager.showError(result.error);
        }
    } catch (error) {
        SendManager.showError('Error de conexión');
    }
};

// ===== BÚSQUEDA DE CHATS =====
const searchChats = Utils.debounce(function(filter) {
    const items = document.querySelectorAll('.chat-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
}, 300);

// ===== INICIALIZACIÓN =====
function initializeChat() {
    // Inicializar IDs de mensajes existentes
    document.querySelectorAll('.message-bubble[data-message-id]').forEach(bubble => {
        AppState.messageIds.add(bubble.dataset.messageId);
    });
    
    if (AppState.messageIds.size > 0) {
        AppState.lastMessageTimestamp = Math.floor(Date.now() / 1000);
    }
    
    // Configurar búsqueda
    const searchInput = document.getElementById('searchChats');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchChats(e.target.value.toLowerCase());
        });
    }
    
    // Configurar textarea
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
        
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }
    
    // Scroll inicial
    const container = document.getElementById('messagesContainer');
    if (container) {
        setTimeout(() => Utils.scrollToBottom(container), 100);
    }
    
    // Eventos de teclado
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            MediaModal.close();
            closeModal('newChatModal');
            ContactModal.close();
        }
    });
    
    // Click fuera del modal
    document.getElementById('mediaModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'mediaModal') MediaModal.close();
    });
    
    document.getElementById('contactModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'contactModal') ContactModal.close();
    });
    
    // Precargar contactos
    ContactManager.preloadAll().then(() => {
        if (!AppState.selectedChatId) {
            ChatListManager.update(true);
        }
    });
    
    // Iniciar actualizaciones
    if (AppState.selectedChatId) {
        AppState.updateInterval = setInterval(() => MessageManager.update(), CONFIG.MESSAGE_UPDATE_INTERVAL);
        AppState.chatsUpdateInterval = setInterval(() => ChatListManager.update(false), CONFIG.CHATS_UPDATE_INTERVAL);
        console.log('✅ Sistema de actualización en tiempo real activo');
    } else {
        AppState.chatsUpdateInterval = setInterval(() => ChatListManager.update(false), CONFIG.CHATS_UPDATE_INTERVAL);
        console.log('✅ Verificación de cambios en chats iniciada');
    }
    
    // Limpiar intervalos al salir
    window.addEventListener('beforeunload', () => {
        if (AppState.updateInterval) clearInterval(AppState.updateInterval);
        if (AppState.chatsUpdateInterval) clearInterval(AppState.chatsUpdateInterval);
    });
    
    console.log('✅ Sistema de chats inicializado correctamente');
}

// Iniciar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChat);
} else {
    initializeChat();
}
</script>