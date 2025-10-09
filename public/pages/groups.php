<?php
// Verificar permiso de visualización
Auth::requirePermission('view_groups');

// Obtener instancia de Auth
$auth = Auth::getInstance();

// Verificar permisos específicos
$canManage = $auth->hasPermission('manage_groups');

// Obtener grupos de WhatsApp
$groupsData = [];
try {
    $groupsData = $whatsapp->getGroups();
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Detectar si se está viendo un grupo específico
$selectedGroup = $_GET['group'] ?? null;
$groupMessages = [];
$groupInfo = null;

if ($selectedGroup) {
    try {
        // Obtener información del grupo
        $groupInfo = $whatsapp->getGroup($selectedGroup);
        
        // Obtener mensajes del grupo
        $messagesData = $whatsapp->getChatMessages($selectedGroup, 50);
        $groupMessages = $messagesData['messages'] ?? [];
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
?>

<style>
.groups-container {
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

.groups-sidebar {
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.groups-header {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.groups-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.group-item {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    gap: 12px;
    text-decoration: none;
    color: inherit;
}

.group-item:hover, .group-item.active {
    background: var(--light);
}

.group-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3em;
    flex-shrink: 0;
}

.group-info {
    flex: 1;
    min-width: 0;
}

.group-name {
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95em;
    margin-bottom: 3px;
}

.group-participants {
    font-size: 0.85em;
    color: var(--gray);
}

.group-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.group-header-bar {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.group-header-info {
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

.group-header-info:hover {
    background: var(--light);
}

.group-header-details {
    flex: 1;
    min-width: 0;
}

.group-header-name {
    font-weight: 600;
    font-size: 1.05em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.group-header-members {
    font-size: 0.85em;
    color: var(--gray);
}

.group-actions {
    display: flex;
    gap: 8px;
}

.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f0f2f5;
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

.bubble-content {
    background: white;
    padding: 10px 14px;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
}

.message-bubble.sent .bubble-content {
    background: #dcf8c6;
}

.message-sender {
    font-weight: 600;
    font-size: 0.85em;
    color: #25D366;
    margin-bottom: 3px;
}

.message-text {
    white-space: pre-wrap;
    word-break: break-word;
    margin-bottom: 4px;
}

.message-time {
    font-size: 0.7em;
    color: var(--gray);
    text-align: right;
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
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--gray);
}

.empty-state i {
    font-size: 3.5em;
    margin-bottom: 15px;
    opacity: 0.3;
}

@media (max-width: 768px) {
    .groups-container {
        grid-template-columns: 1fr;
    }
    .groups-sidebar {
        display: <?= $selectedGroup ? 'none' : 'flex' ?>;
    }
    .group-content {
        display: <?= $selectedGroup ? 'flex' : 'none' ?>;
    }
}
</style>

<div class="groups-container">
    <!-- Sidebar de Grupos -->
    <div class="groups-sidebar">
        <div class="groups-header">
            <h3 style="margin: 0; font-size: 1.1em;">
                <i class="fas fa-users"></i> Mis Grupos
            </h3>
            <?php if ($canManage): ?>
    <button class="btn btn-sm btn-primary" onclick="openModal('modalNewGroup')">
        <i class="fas fa-plus"></i>
    </button>
<?php endif; ?>
        </div>
        
        <div class="groups-list">
            <?php if (empty($groupsData['groups'])): ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <i class="fas fa-users"></i>
                    <p>No hay grupos disponibles</p>
                </div>
            <?php else: ?>
                <?php foreach ($groupsData['groups'] as $group): ?>
                    <a href="?page=groups&group=<?= urlencode($group['id']) ?>" 
                       class="group-item <?= $selectedGroup === $group['id'] ? 'active' : '' ?>"
                       data-group-id="<?= htmlspecialchars($group['id']) ?>">
                        <div class="group-avatar">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="group-info">
                            <div class="group-name"><?= htmlspecialchars($group['name']) ?></div>
                            <div class="group-participants">
                                <?= $group['participants'] ?? 0 ?> participantes
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Contenido del Grupo -->
    <div class="group-content">
        <?php if ($selectedGroup && $groupInfo): ?>
            <!-- Header del Grupo -->
            <div class="group-header-bar">
                <div class="group-header-info" onclick="openGroupInfoModal()">
                    <div class="group-avatar">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="group-header-details">
                        <div class="group-header-name">
                            <?= htmlspecialchars($groupInfo['name']) ?>
                        </div>
                        <div class="group-header-members">
                            <?= count($groupInfo['participants'] ?? []) ?> participantes
                        </div>
                    </div>
                </div>
                <div class="group-actions">
                    <button class="btn btn-icon" onclick="markGroupAsRead('<?= htmlspecialchars($selectedGroup) ?>')" title="Marcar como leído">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <a href="?page=groups" class="btn btn-icon" title="Volver">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <!-- Mensajes del Grupo -->
            <div class="messages-container" id="groupMessagesContainer">
                <?php if (empty($groupMessages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No hay mensajes en este grupo</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupMessages as $msg): ?>
                        <div class="message-bubble <?= $msg['fromMe'] ? 'sent' : 'received' ?>" data-message-id="<?= htmlspecialchars($msg['id']) ?>">
                            <div class="bubble-content">
                                <?php if (!$msg['fromMe']): ?>
                                    <div class="message-sender">
                                        <?php
                                        // Extraer nombre del participante
                                        $senderId = $msg['from'];
                                        $senderName = 'Participante';
                                        if (isset($groupInfo['participants'])) {
                                            foreach ($groupInfo['participants'] as $participant) {
                                                if (isset($participant['id']['_serialized']) && $participant['id']['_serialized'] === $senderId) {
                                                    $senderName = $participant['id']['user'];
                                                    break;
                                                }
                                            }
                                        }
                                        echo htmlspecialchars($senderName);
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($msg['body']): ?>
                                    <div class="message-text">
                                        <?= nl2br(htmlspecialchars($msg['body'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message-time">
                                    <?= date('H:i', $msg['timestamp']) ?>
                                    <?php if ($msg['fromMe']): ?>
                                        <span><i class="fas fa-check-double"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Input de Mensaje -->
            <?php if ($canManage): ?>
    <div class="message-input-container">
        <button class="btn btn-icon" onclick="document.getElementById('groupFileInput').click()">
            <i class="fas fa-paperclip"></i>
        </button>
        <input type="file" id="groupFileInput" style="display: none" accept="image/*,video/*,audio/*">
        <textarea id="groupMessageInput" class="message-input" 
                  placeholder="Escribe un mensaje al grupo..." rows="1"></textarea>
        <button class="btn-send" id="groupSendBtn" onclick="sendGroupMessage()">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
<?php else: ?>
    <div style="padding: 15px; text-align: center; background: #f9f9f9;">
        <i class="fas fa-lock"></i> No tienes permiso para enviar mensajes
    </div>
<?php endif; ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>Selecciona un grupo para ver los mensajes</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo Grupo -->
<div id="modalNewGroup" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Crear Nuevo Grupo</h3>
            <button onclick="closeModal('modalNewGroup')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <form id="formNewGroup" onsubmit="return createGroup(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre del Grupo *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Participantes (un número por línea) *</label>
                    <textarea name="participantes" class="form-control" rows="5" 
                              placeholder="549348230949&#10;549112345678" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalNewGroup')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Grupo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Info del Grupo -->
<div id="modalGroupInfo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Información del Grupo</h3>
            <button onclick="closeModal('modalGroupInfo')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" id="groupInfoBody">
            <!-- Se llena dinámicamente -->
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal('modalGroupInfo')">Cerrar</button>
        </div>
    </div>
</div>

<script>
const selectedGroupId = '<?= htmlspecialchars($selectedGroup ?? '') ?>';
let groupUpdateInterval = null;
let lastMessageTimestamp = 0;
let messageIds = new Set();

// Cargar IDs de mensajes iniciales
document.querySelectorAll('.message-bubble[data-message-id]').forEach(bubble => {
    messageIds.add(bubble.dataset.messageId);
});

// Calcular timestamp inicial
if (messageIds.size > 0) {
    const today = new Date();
    lastMessageTimestamp = Math.floor(today.getTime() / 1000);
}

// Crear grupo
async function createGroup(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const participantes = formData.get('participantes').split('\n').filter(n => n.trim());
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                nombre: formData.get('nombre'),
                participantes: participantes
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Grupo creado exitosamente', 'success');
            closeModal('modalNewGroup');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
    return false;
}

// Enviar mensaje al grupo
async function sendGroupMessage() {
    const input = document.getElementById('groupMessageInput');
    const sendBtn = document.getElementById('groupSendBtn');
    const message = input.value.trim();
    
    if (!message || !selectedGroupId) return;
    
    // Agregar mensaje optimista inmediatamente
    const optimisticMessageId = 'temp_' + Date.now();
    const optimisticMessage = {
        id: optimisticMessageId,
        body: message,
        fromMe: true,
        timestamp: Math.floor(Date.now() / 1000)
    };
    
    const container = document.getElementById('groupMessagesContainer');
    const bubble = createMessageBubble(optimisticMessage);
    bubble.classList.add('sending');
    bubble.style.opacity = '0.7';
    container.appendChild(bubble);
    scrollToBottom(container);
    
    // Limpiar input
    input.value = '';
    input.style.height = 'auto';
    
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                to: selectedGroupId,
                message: message
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Remover clase de enviando
            bubble.classList.remove('sending');
            bubble.style.opacity = '1';
            
            // Forzar actualización para obtener el mensaje real
            setTimeout(() => updateGroupMessages(), 500);
        } else {
            // Remover mensaje optimista si falló
            bubble.remove();
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        // Remover mensaje optimista si falló
        bubble.remove();
        showNotification('Error de conexión', 'error');
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
    }
}

// Actualizar mensajes del grupo con detección de nuevos
async function updateGroupMessages() {
    if (!selectedGroupId) return;
    
    try {
        const url = `api/get-chat-messages.php?chatId=${encodeURIComponent(selectedGroupId)}&after=${lastMessageTimestamp}&t=${Date.now()}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            console.log(`📨 ${data.messages.length} mensajes nuevos`);
            
            const container = document.getElementById('groupMessagesContainer');
            const wasAtBottom = shouldAutoScroll(container);
            
            data.messages.forEach(msg => {
                if (messageIds.has(msg.id)) return;
                
                messageIds.add(msg.id);
                const bubble = createMessageBubble(msg);
                container.appendChild(bubble);
            });
            
            if (data.lastTimestamp) {
                lastMessageTimestamp = data.lastTimestamp;
            }
            
            if (wasAtBottom) {
                scrollToBottom(container);
            }
        }
    } catch (error) {
        console.error('Error actualizando mensajes:', error);
    }
}

// Crear elemento de mensaje
function createMessageBubble(msg) {
    const bubble = document.createElement('div');
    bubble.className = `message-bubble ${msg.fromMe ? 'sent' : 'received'}`;
    bubble.dataset.messageId = msg.id;
    
    const time = new Date(msg.timestamp * 1000).toLocaleTimeString('es-AR', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const senderName = msg.fromMe ? '' : (msg.from ? msg.from.split('@')[0] : 'Participante');
    
    bubble.innerHTML = `
        <div class="bubble-content">
            ${!msg.fromMe ? `<div class="message-sender">${senderName}</div>` : ''}
            ${msg.body ? `<div class="message-text">${escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>` : ''}
            <div class="message-time">
                ${time}
                ${msg.fromMe ? '<span><i class="fas fa-check-double"></i></span>' : ''}
            </div>
        </div>
    `;
    
    return bubble;
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

// Marcar grupo como leído
async function markGroupAsRead(groupId) {
    try {
        await fetch('api/chats.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', chatId: groupId })
        });
        showNotification('Marcado como leído', 'success');
    } catch (error) {
        console.error('Error:', error);
    }
}

// Abrir modal de información del grupo
function openGroupInfoModal() {
    const modal = document.getElementById('modalGroupInfo');
    const body = document.getElementById('groupInfoBody');
    
    <?php if ($groupInfo): ?>
    body.innerHTML = `
        <div class="form-group">
            <label><i class="fas fa-users"></i> Nombre del Grupo</label>
            <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                <?= htmlspecialchars($groupInfo['name']) ?>
            </div>
        </div>
        <div class="form-group">
            <label><i class="fas fa-info-circle"></i> Descripción</label>
            <div style="padding: 10px; background: var(--light); border-radius: 6px;">
                <?= htmlspecialchars($groupInfo['description'] ?? 'Sin descripción') ?>
            </div>
        </div>
        <div class="form-group">
            <label><i class="fas fa-user-friends"></i> Participantes (<?= count($groupInfo['participants'] ?? []) ?>)</label>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($groupInfo['participants'] ?? [] as $participant): ?>
                    <div style="padding: 8px; border-bottom: 1px solid var(--border);">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($participant['id']['user']) ?>
                        <?php if ($participant['isAdmin']): ?>
                            <span style="color: #25D366; margin-left: 8px;">
                                <i class="fas fa-crown"></i> Admin
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    `;
    <?php endif; ?>
    
    openModal('modalGroupInfo');
}

// Auto-resize textarea
const groupInput = document.getElementById('groupMessageInput');
if (groupInput) {
    groupInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
    
    groupInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendGroupMessage();
        }
    });
}

// Auto-scroll inicial
const container = document.getElementById('groupMessagesContainer');
if (container) {
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 100);
}

// Actualización periódica de mensajes
if (selectedGroupId) {
    groupUpdateInterval = setInterval(updateGroupMessages, 3000);
}

// Limpiar interval al salir
window.addEventListener('beforeunload', () => {
    if (groupUpdateInterval) clearInterval(groupUpdateInterval);
});
</script>