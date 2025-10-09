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
.chats-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 0;
    height: 78vh;
    min-height: 500px;
    max-height: 750px;
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.chats-sidebar {
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.chats-header {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chats-search {
    padding: 10px 15px;
    border-bottom: 1px solid var(--border);
}

.chats-search input {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.95em;
}

.chats-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.chat-item {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    gap: 12px;
    text-decoration: none;
    color: inherit;
}

.chat-item:hover,
.chat-item.active {
    background: var(--light);
}

.chat-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3em;
    flex-shrink: 0;
    color: white;
}

.chat-info {
    flex: 1;
    min-width: 0;
}

.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.chat-name {
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95em;
}

.chat-preview {
    font-size: 0.85em;
    color: var(--gray);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-unread {
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 0.7em;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
}

.chat-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.chat-header-bar {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
    cursor: pointer;
    padding: 5px;
    border-radius: 8px;
    transition: background 0.2s;
}

.chat-header-info:hover {
    background: var(--light);
}

.chat-header-details {
    flex: 1;
    min-width: 0;
}

.chat-header-name {
    font-weight: 600;
    font-size: 1.05em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-header-number {
    font-size: 0.85em;
    color: var(--gray);
}

.contact-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    margin-left: 8px;
}

.not-saved-badge {
    background: #fff3e0;
    color: #f57c00;
}

.chat-actions {
    display: flex;
    gap: 8px;
}

.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f0f2f5;
    scroll-behavior: smooth;
}

.message-bubble {
    max-width: 70%;
    margin-bottom: 12px;
    display: flex;
    clear: both;
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
    opacity: 0.7;
}

.bubble-content {
    background: white;
    padding: 10px 14px;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
    max-width: 100%;
}

.message-bubble.sent .bubble-content {
    background: #dcf8c6;
}

.message-bubble.sending .bubble-content {
    border: 1px dashed rgba(37, 211, 102, 0.3);
}

.message-text {
    white-space: pre-wrap;
    word-break: break-word;
    margin-bottom: 4px;
}

.message-media {
    display: block;
    margin-bottom: 8px;
    border-radius: 8px;
    overflow: hidden;
}

.message-media img {
    max-width: 100%;
    max-height: 400px;
    display: block;
    cursor: pointer;
    transition: transform 0.2s;
}

.message-media img:hover {
    transform: scale(1.02);
}

.message-media video {
    max-width: 100%;
    max-height: 400px;
    display: block;
}

.message-media audio {
    width: 100%;
    max-width: 300px;
}

.message-time {
    font-size: 0.7em;
    color: var(--gray);
    text-align: right;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
}

.media-preview {
    padding: 10px 15px;
    border-top: 1px solid var(--border);
    background: #f9f9f9;
    display: none;
    align-items: center;
    gap: 10px;
}

.media-preview.active {
    display: flex;
}

.media-preview img,
.media-preview video {
    max-height: 80px;
    border-radius: 8px;
}

.media-preview-info {
    flex: 1;
    min-width: 0;
}

.media-preview-name {
    font-size: 0.9em;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.media-preview-size {
    font-size: 0.8em;
    color: var(--gray);
}

.remove-media {
    background: #f44336;
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.message-input-container {
    padding: 12px 15px;
    border-top: 1px solid var(--border);
    background: white;
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.message-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 22px;
    font-size: 0.95em;
    resize: none;
    max-height: 100px;
    font-family: inherit;
}

.btn-send {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1em;
    transition: all 0.3s;
}

.btn-send:hover {
    background: var(--primary-dark);
    transform: scale(1.05);
}

.btn-send:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: scale(1);
}

.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--gray);
}

.empty-chat i {
    font-size: 3.5em;
    margin-bottom: 15px;
    opacity: 0.3;
}

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
    top: -50px;
    right: 0;
    display: flex;
    gap: 15px;
}

.media-modal-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.media-modal-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.media-modal-media {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .chats-container {
        grid-template-columns: 1fr;
    }
    .chats-sidebar {
        display: <?= $selectedChat ? 'none' : 'flex' ?>;
    }
    .chat-content {
        display: <?= $selectedChat ? 'flex' : 'none' ?>;
    }
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
const selectedChatId = '<?= htmlspecialchars($selectedChat ?? '') ?>';
const currentChatNumber = '<?= str_replace(['@c.us', '@g.us'], '', $selectedChat ?? '') ?>';
let currentContactInfo = <?= json_encode($contactInfo) ?>;
let updateInterval = null;
let lastMessageTimestamp = 0;
let messageIds = new Set();
let selectedFile = null;
let chatsUpdateInterval = null;
let lastChatsCheck = Date.now();
let lastChatsData = null; // Cache de la última lista de chats
let contactNamesCache = {}; // Cache permanente de nombres de contactos

document.querySelectorAll('.message-bubble[data-message-id]').forEach(bubble => {
    messageIds.add(bubble.dataset.messageId);
});

if (messageIds.size > 0) {
    lastMessageTimestamp = Math.floor(Date.now() / 1000);
}

document.getElementById('searchChats')?.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('.chat-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
});

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const maxSize = 16 * 1024 * 1024;
    if (file.size > maxSize) {
        showNotification('Archivo muy grande (máx 16MB)', 'error');
        event.target.value = '';
        return;
    }
    
    selectedFile = file;
    
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
        <div class="media-preview-name">${file.name}</div>
        <div class="media-preview-size">${sizeKB} KB</div>
    `;
    
    preview.classList.add('active');
}

function removeMediaPreview() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('mediaPreview').classList.remove('active');
    document.getElementById('mediaPreviewContent').innerHTML = '';
    document.getElementById('mediaPreviewInfo').innerHTML = '';
}

async function sendChatMessage() {
    const input = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const message = input.value.trim();
    
    if (!message && !selectedFile) return;
    if (!selectedChatId) return;
    
    const container = document.getElementById('messagesContainer');
    
    if (selectedFile) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const formData = new FormData();
        formData.append('file', selectedFile);
        formData.append('to', selectedChatId);
        formData.append('caption', message);
        
        try {
            const response = await fetch('api/send-media.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                input.value = '';
                removeMediaPreview();
                showNotification('Multimedia enviado', 'success');
                setTimeout(() => updateMessages(), 1000);
            } else {
                showNotification('Error: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Error de conexión', 'error');
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        }
    } else {
        const optimisticMessage = {
            id: 'temp_' + Date.now(),
            body: message,
            fromMe: true,
            timestamp: Math.floor(Date.now() / 1000)
        };
        
        const bubble = createMessageBubble(optimisticMessage);
        bubble.classList.add('sending');
        container.appendChild(bubble);
        scrollToBottom(container);
        
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const response = await fetch('api/send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    to: selectedChatId,
                    message: message
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                bubble.classList.remove('sending');
                bubble.style.opacity = '1';
                setTimeout(() => updateMessages(), 1000);
            } else {
                bubble.remove();
                showNotification('Error: ' + result.error, 'error');
            }
        } catch (error) {
            bubble.remove();
            showNotification('Error de conexión', 'error');
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        }
    }
}

async function updateMessages() {
    if (!selectedChatId) return;
    
    try {
        const url = `api/get-chat-messages.php?chatId=${encodeURIComponent(selectedChatId)}&after=${lastMessageTimestamp}&t=${Date.now()}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            const container = document.getElementById('messagesContainer');
            const wasAtBottom = shouldAutoScroll(container);
            
            // Remover mensajes temporales
            document.querySelectorAll('.message-bubble.sending').forEach(b => b.remove());
            
            let newMessagesCount = 0;
            
            data.messages.forEach(msg => {
                if (messageIds.has(msg.id)) return;
                
                messageIds.add(msg.id);
                newMessagesCount++;
                
                const bubble = createMessageBubble(msg);
                bubble.style.opacity = '0';
                container.appendChild(bubble);
                
                requestAnimationFrame(() => {
                    bubble.style.transition = 'opacity 0.3s';
                    bubble.style.opacity = '1';
                });
                
                // Si es un mensaje entrante, mostrar notificación
                if (!msg.fromMe) {
                    showNotification('💬 Nuevo mensaje', 'info');
                }
            });
            
            if (data.lastTimestamp) {
                lastMessageTimestamp = data.lastTimestamp;
            }
            
            // Si hay mensajes nuevos, scroll automático si estaba cerca del final
            if (newMessagesCount > 0 && wasAtBottom) {
                setTimeout(() => scrollToBottom(container), 100);
            }
            
            // ✅ Actualizar la lista de chats SOLO si hay mensajes nuevos
            if (newMessagesCount > 0) {
                console.log('📬 Mensaje nuevo recibido, actualizando lista de chats...');
                updateChatsList(true); // Forzar actualización
            }
        }
    } catch (error) {
        console.error('Error actualizando:', error);
    }
}

function createMessageBubble(msg) {
    const bubble = document.createElement('div');
    bubble.className = `message-bubble ${msg.fromMe ? 'sent' : 'received'}`;
    bubble.dataset.messageId = msg.id;
    
    // ✅ CORRECCIÓN CRÍTICA: El timestamp ya viene correcto desde el servidor
    // NO hacer ninguna conversión, solo formatear para mostrar
    let time;
    if (msg.timestamp) {
        // El timestamp viene en SEGUNDOS Unix, convertir a milisegundos para Date
        const date = new Date(msg.timestamp * 1000);
        
        // Formatear directamente sin conversiones de zona horaria
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        time = `${hours}:${minutes}`;
    } else {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        time = `${hours}:${minutes}`;
    }
    
    let mediaHtml = '';
    if (msg.hasMedia && msg.mediaUrl) {
        const mediaType = msg.type || 'document';
        const proxyUrl = `api/media-proxy.php?file=${encodeURIComponent(msg.mediaUrl)}`;
        
        if (mediaType === 'image') {
            mediaHtml = `
                <div class="message-media">
                    <img src="${escapeHtml(proxyUrl)}" 
                         alt="Imagen" 
                         onclick="openMediaModal('${escapeHtml(proxyUrl)}', 'image')">
                </div>`;
        } else if (mediaType === 'video') {
            mediaHtml = `
                <div class="message-media">
                    <video src="${escapeHtml(proxyUrl)}" controls></video>
                </div>`;
        } else if (mediaType === 'audio' || mediaType === 'ptt') {
            mediaHtml = `
                <div class="message-media">
                    <audio src="${escapeHtml(proxyUrl)}" controls></audio>
                </div>`;
        } else {
            mediaHtml = `
                <div class="message-media">
                    <i class="fas fa-file"></i>
                    <a href="${escapeHtml(proxyUrl)}" target="_blank">Archivo adjunto</a>
                </div>`;
        }
    }
    
    bubble.innerHTML = `
        <div class="bubble-content">
            ${mediaHtml}
            ${msg.body ? `<div class="message-text">${escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>` : ''}
            <div class="message-time">
                ${time}
                ${msg.fromMe ? '<span><i class="fas fa-check-double"></i></span>' : ''}
            </div>
        </div>
    `;
    
    return bubble;
}

function openMediaModal(mediaUrl, mediaType) {
    const modal = document.getElementById('mediaModal');
    const body = document.getElementById('mediaModalBody');
    
    if (mediaType === 'image') {
        body.innerHTML = `<img src="${mediaUrl}" class="media-modal-media" alt="Imagen">`;
    } else if (mediaType === 'video') {
        body.innerHTML = `<video src="${mediaUrl}" class="media-modal-media" controls autoplay></video>`;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMediaModal() {
    const modal = document.getElementById('mediaModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('mediaModalBody').innerHTML = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMediaModal();
        closeModal('newChatModal');
        closeContactModal();
    }
});

document.getElementById('mediaModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMediaModal();
    }
});


// ==================== FUNCIÓN PARA DETECTAR SI HAY CAMBIOS EN CHATS ====================
function hasChatsChanged(newChats) {
    if (!lastChatsData) return true;
    
    // Comparar número de chats
    if (newChats.length !== lastChatsData.length) return true;
    
    // Comparar cada chat (timestamp y unreadCount)
    for (let i = 0; i < newChats.length; i++) {
        const newChat = newChats[i];
        const oldChat = lastChatsData.find(c => c.id === newChat.id);
        
        if (!oldChat) return true;
        
        // Si cambió el timestamp o el contador de no leídos
        if (newChat.timestamp !== oldChat.timestamp || 
            newChat.unreadCount !== oldChat.unreadCount) {
            return true;
        }
    }
    
    return false;
}

// ==================== FUNCIÓN PARA FORMATEAR HORA DE CHAT ====================
function formatChatTime(timestamp) {
    const date = new Date(timestamp * 1000);
    const now = new Date();
    const diff = now - date;
    
    // Si es hoy
    if (date.toDateString() === now.toDateString()) {
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    }
    
    // Si es ayer
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) {
        return 'Ayer';
    }
    
    // Si es esta semana
    if (diff < 7 * 24 * 60 * 60 * 1000) {
        const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        return days[date.getDay()];
    }
    
    // Fecha completa
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    return `${day}/${month}`;
}

// ==================== FUNCIÓN PARA CARGAR NOMBRES DE CONTACTOS (CON CACHE) ====================
async function loadContactNamesOnce() {
    const chatElements = document.querySelectorAll('.chat-name[data-chat-number]:not([data-name-loaded])');
    
    if (chatElements.length === 0) {
        return;
    }
    
    console.log(`📝 Cargando nombres para ${chatElements.length} chats nuevos...`);
    
    for (const element of chatElements) {
        const chatId = element.dataset.chatNumber;
        
        let numero = chatId;
        if (chatId.includes('@')) {
            numero = chatId.split('@')[0];
        }
        
        if (!numero || numero === '0' || numero.length < 10) {
            element.dataset.nameLoaded = 'true';
            continue;
        }
        
        // ✅ Verificar si ya está en cache
        if (contactNamesCache[numero]) {
            element.textContent = contactNamesCache[numero];
            element.dataset.nameLoaded = 'true';
            continue;
        }
        
        // Si no está en cache, hacer petición
        try {
            const response = await fetch(`api/get-contact-name.php?numero=${encodeURIComponent(numero)}`);
            const data = await response.json();
            
            if (data.success && data.nombre) {
                element.textContent = data.nombre;
                contactNamesCache[numero] = data.nombre; // ✅ Guardar en cache
                element.dataset.nameLoaded = 'true';
                console.log(`✅ Nombre cargado y guardado en cache: ${data.nombre}`);
            } else {
                element.dataset.nameLoaded = 'true';
            }
        } catch (error) {
            console.error('Error cargando nombre de contacto:', error);
            element.dataset.nameLoaded = 'true';
        }
    }
}

// ==================== FUNCIÓN PARA PRECARGAR TODOS LOS NOMBRES AL INICIO ====================
async function preloadAllContactNames() {
    try {
        console.log('📚 Precargando todos los nombres de contactos...');
        
        const response = await fetch('api/get-all-contact-names.php');
        const data = await response.json();
        
        if (data.success && data.contactos) {
            data.contactos.forEach(contacto => {
                contactNamesCache[contacto.numero] = contacto.nombre;
            });
            console.log(`✅ ${Object.keys(contactNamesCache).length} contactos cargados`);
        }
    } catch (error) {
        console.error('Error precargando nombres:', error);
    }
}

// ==================== FUNCIÓN PARA ACTUALIZAR LISTA DE CHATS ====================
async function updateChatsList(forceUpdate = false) {
    try {
        const response = await fetch(`api/get-chats.php?t=${Date.now()}`);
        const data = await response.json();
        
        if (data.success && data.chats) {
            // ✅ Filtrar chats válidos
            const individualChats = data.chats.filter(chat => {
                if (chat.isGroup) return false;
                if (!chat.id || chat.id === '0@c.us') return false;
                const numero = chat.id.split('@')[0];
                if (!numero || numero === '0' || numero.length < 10) return false;
                return true;
            });
            
            // ✅ SOLO actualizar si hay cambios reales o es forzado
            if (!forceUpdate && !hasChatsChanged(individualChats)) {
                // console.log('📋 No hay cambios en la lista de chats'); // Silenciado
                return;
            }
            
            console.log('🔄 Lista actualizada');
            
            // Guardar nueva data en cache
            lastChatsData = JSON.parse(JSON.stringify(individualChats));
            
            const chatsList = document.getElementById('chatsList');
            if (!chatsList) return;
            
            const currentScroll = chatsList.scrollTop;
            
            // Ordenar por timestamp
            individualChats.sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
            
            // Reconstruir la lista
            let html = '';
            
            individualChats.forEach(chat => {
                const chatId = chat.id;
                const isActive = selectedChatId === chatId;
                const unreadCount = chat.unreadCount || 0;
                const timestamp = chat.timestamp ? formatChatTime(chat.timestamp) : '';
                
                // ✅ Obtener nombre del cache o usar el nombre de WhatsApp
                const numero = chatId.split('@')[0];
                const displayName = contactNamesCache[numero] || chat.name;
                const isNameLoaded = contactNamesCache[numero] ? 'true' : 'false';
                
                html += `
                    <a href="?page=chats&chat=${encodeURIComponent(chatId)}" 
                       class="chat-item ${isActive ? 'active' : ''}"
                       data-chat-id="${escapeHtml(chatId)}"
                       data-unread="${unreadCount}">
                        <div class="chat-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="chat-info">
                            <div class="chat-header">
                                <span class="chat-name" data-chat-number="${escapeHtml(chatId)}" data-name-loaded="${isNameLoaded}">${escapeHtml(displayName)}</span>
                                ${unreadCount > 0 ? `<span class="chat-unread">${unreadCount}</span>` : ''}
                            </div>
                            <div class="chat-preview">
                                ${timestamp}
                            </div>
                        </div>
                    </a>
                `;
            });
            
            if (html) {
                chatsList.innerHTML = html;
            } else {
                chatsList.innerHTML = `
                    <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                        <i class="fas fa-comments"></i>
                        <p>No hay chats</p>
                    </div>
                `;
            }
            
            // Restaurar scroll
            chatsList.scrollTop = currentScroll;
            
            // Cargar nombres de contactos SOLO para elementos sin nombre cargado
            loadContactNamesOnce();
        }
    } catch (error) {
        console.error('Error actualizando lista de chats:', error);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function shouldAutoScroll(container) {
    const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
    return distanceFromBottom < 100;
}

function scrollToBottom(container) {
    container.scrollTop = container.scrollHeight;
}

async function markChatAsRead(chatId) {
    try {
        await fetch('api/chats.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', chatId: chatId })
        });
        showNotification('Marcado como leído', 'success');
    } catch (error) {
        console.error('Error:', error);
    }
}

function showNewChatModal() {
    document.getElementById('newChatModal').style.display = 'flex';
    document.getElementById('newChatNumber').focus();
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openContactModal() {
    const modal = document.getElementById('contactModal');
    const body = document.getElementById('contactModalBody');
    const footer = document.getElementById('contactModalFooter');
    
    if (currentContactInfo && currentContactInfo.nombre) {
        body.innerHTML = `
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nombre</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${escapeHtml(currentContactInfo.nombre || '-')}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Número</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${escapeHtml(currentContactInfo.numero)}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${escapeHtml(currentContactInfo.email || '-')}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-building"></i> Empresa</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${escapeHtml(currentContactInfo.empresa || '-')}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-tags"></i> Etiquetas</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${escapeHtml(currentContactInfo.etiquetas || '-')}
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> Notas</label>
                <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                    ${escapeHtml(currentContactInfo.notas || '-')}
                </div>
            </div>
        `;
        
        footer.innerHTML = `
            <button class="btn" onclick="closeContactModal()">Cerrar</button>
            <button class="btn btn-primary" onclick="editContact(${currentContactInfo.id})">
                <i class="fas fa-edit"></i> Editar
            </button>
        `;
    } else {
        body.innerHTML = `
            <p style="margin-bottom: 20px; color: var(--gray);">
                <i class="fas fa-info-circle"></i> Este número no está guardado en tus contactos
            </p>
            <form id="formSaveContact">
                <div class="form-group">
                    <label>Número *</label>
                    <input type="text" name="numero" class="form-control" value="${escapeHtml(currentChatNumber)}" readonly>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Empresa</label>
                    <input type="text" name="empresa" class="form-control">
                </div>
                <div class="form-group">
                    <label>Etiquetas (separadas por coma)</label>
                    <input type="text" name="etiquetas" class="form-control" placeholder="cliente, vip, proveedor">
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notas" class="form-control" rows="3"></textarea>
                </div>
            </form>
        `;
        
        footer.innerHTML = `
            <button class="btn" onclick="closeContactModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="saveNewContact()">
                <i class="fas fa-save"></i> Guardar Contacto
            </button>
        `;
    }
    
    modal.style.display = 'flex';
}

function closeContactModal() {
    document.getElementById('contactModal').style.display = 'none';
}

async function saveNewContact() {
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
            showNotification('Contacto guardado correctamente', 'success');
            closeContactModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

function editContact(contactId) {
    closeContactModal();
    window.location.href = `?page=contacts&edit=${contactId}`;
}

document.getElementById('contactModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeContactModal();
    }
});

async function sendToNewNumber() {
    const number = document.getElementById('newChatNumber').value.trim().replace(/[^0-9]/g, '');
    const message = document.getElementById('newChatMessage').value.trim();
    
    if (!number || !message) {
        showNotification('Completa todos los campos', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ to: number, message: message })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal('newChatModal');
            showNotification('Mensaje enviado', 'success');
            setTimeout(() => location.href = `?page=chats&chat=${number}@c.us`, 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

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

const container = document.getElementById('messagesContainer');
if (container) {
    setTimeout(() => scrollToBottom(container), 100);
}

// ✅ Precargar TODOS los nombres al inicio
preloadAllContactNames().then(() => {
    // Después de precargar, actualizar la lista para mostrar los nombres
    if (!selectedChatId) {
        updateChatsList(true);
    }
});

if (selectedChatId) {
    // Actualizar mensajes del chat abierto cada 3 segundos
    updateInterval = setInterval(updateMessages, 3000);
    console.log('✅ Actualización de mensajes iniciada (cada 3s)');
    
    // ✅ Verificar cambios en lista de chats cada 5 segundos (sin recargar HTML)
    chatsUpdateInterval = setInterval(() => updateChatsList(false), 5000);
    console.log('✅ Sistema de actualización en tiempo real activo');
} else {
    // Si no hay chat abierto, verificar cambios en la lista
    chatsUpdateInterval = setInterval(() => updateChatsList(false), 5000);
    console.log('✅ Verificación de cambios en chats iniciada (cada 5s)');
}

window.addEventListener('beforeunload', () => {
    if (updateInterval) clearInterval(updateInterval);
    if (chatsUpdateInterval) clearInterval(chatsUpdateInterval);
});

console.log('Sistema de chats completo inicializado');
console.log('Chat:', selectedChatId);
console.log('Contacto:', currentContactInfo);
</script>