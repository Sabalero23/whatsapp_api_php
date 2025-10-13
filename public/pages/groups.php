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
:root {
    --group-primary: #25D366;
    --group-primary-dark: #128C7E;
    --group-bg: #f0f2f5;
    --group-border: #e9edef;
}

.groups-container {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 0;
    height: calc(100vh - 160px);
    min-height: 500px;
    max-height: 800px;
    max-width: 1600px;
    width: 100%;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.groups-sidebar {
    border-right: 1px solid var(--group-border);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: white;
}

.groups-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--group-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ededed;
}

.groups-search {
    padding: 8px 12px;
    background: white;
    border-bottom: 1px solid var(--group-border);
}

.groups-search input {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid var(--group-border);
    border-radius: 8px;
    font-size: 0.9em;
    background: var(--group-bg);
}

.groups-search input:focus {
    outline: none;
    border-color: var(--group-primary);
    background: white;
}

.groups-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.groups-list::-webkit-scrollbar {
    width: 6px;
}

.groups-list::-webkit-scrollbar-thumb {
    background: #b3b3b3;
    border-radius: 10px;
}

.group-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--group-border);
    cursor: pointer;
    transition: background 0.15s;
    display: flex;
    gap: 14px;
    text-decoration: none;
    color: inherit;
    position: relative;
}

.group-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--group-primary);
    opacity: 0;
    transition: opacity 0.2s;
}

.group-item:hover {
    background: #f5f6f6;
}

.group-item.active {
    background: #ebebeb;
}

.group-item.active::before {
    opacity: 1;
}

.group-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--group-primary) 0%, var(--group-primary-dark) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4em;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.group-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.group-name {
    font-weight: 600;
    font-size: 0.95em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 4px;
}

.group-participants {
    font-size: 0.85em;
    color: #667781;
}

.group-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: var(--group-bg);
}

.group-header-bar {
    padding: 12px 20px;
    background: #ededed;
    border-bottom: 1px solid var(--group-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.group-header-info {
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

.group-header-info:hover {
    background: rgba(0,0,0,0.05);
}

.group-header-details {
    flex: 1;
    min-width: 0;
}

.group-header-name {
    font-weight: 600;
    font-size: 1em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.group-header-members {
    font-size: 0.8em;
    color: #667781;
    margin-top: 2px;
}

.group-actions {
    display: flex;
    gap: 6px;
}

.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #efeae2;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" opacity="0.03"><path d="M0 0h50v50H0zM50 50h50v50H50z"/></svg>');
    scroll-behavior: smooth;
}

.message-bubble {
    max-width: 65%;
    margin-bottom: 8px;
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
    padding: 8px 12px 6px 12px;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
}

.message-bubble.sent .bubble-content {
    background: #d9fdd3;
}

.message-sender {
    font-weight: 600;
    font-size: 0.85em;
    color: var(--group-primary);
    margin-bottom: 3px;
}

.message-text {
    white-space: pre-wrap;
    word-break: break-word;
    margin-bottom: 4px;
    line-height: 1.4;
    font-size: 0.93em;
}

.message-time {
    font-size: 0.68em;
    color: #667781;
    text-align: right;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
}

.message-input-container {
    padding: 10px 16px;
    background: #ededed;
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
}

.message-input:focus {
    outline: none;
    box-shadow: 0 0 0 2px var(--group-primary);
}

.btn-send {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: var(--group-primary);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15em;
    transition: all 0.25s;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

.btn-send:hover {
    background: var(--group-primary-dark);
    transform: scale(1.08);
}

.btn-send:disabled {
    background: #b3b3b3;
    cursor: not-allowed;
    transform: scale(1);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #667781;
}

.empty-state i {
    font-size: 4em;
    margin-bottom: 16px;
    opacity: 0.2;
}

.participant-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--group-border);
    transition: background 0.2s;
}

.participant-item:hover {
    background: var(--group-bg);
}

.participant-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667781 0%, #4a5560 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.participant-info {
    flex: 1;
    min-width: 0;
}

.participant-name {
    font-weight: 500;
    font-size: 0.95em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.participant-number {
    font-size: 0.8em;
    color: #667781;
    margin-top: 2px;
}

.admin-badge {
    background: #dcf8c6;
    color: var(--group-primary);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.participant-actions {
    display: flex;
    gap: 4px;
}

.btn-icon {
    background: transparent;
    border: 1px solid var(--group-border);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    color: #667781;
}

.btn-icon:hover {
    background: var(--group-bg);
    border-color: var(--group-primary);
    color: var(--group-primary);
}

.btn-icon.danger:hover {
    border-color: #f44336;
    color: #f44336;
}

.tabs {
    display: flex;
    border-bottom: 1px solid var(--group-border);
    background: white;
}

.tab {
    flex: 1;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    font-weight: 500;
    font-size: 0.9em;
}

.tab:hover {
    background: var(--group-bg);
}

.tab.active {
    border-bottom-color: var(--group-primary);
    color: var(--group-primary);
}

.tab-content {
    display: none;
    padding: 20px;
    overflow-y: auto;
    max-height: 500px;
}

.tab-content.active {
    display: block;
}

@media (max-width: 768px) {
    .groups-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 80px);
    }
    
    .groups-sidebar {
        display: <?= $selectedGroup ? 'none' : 'flex' ?>;
    }
    
    .group-content {
        display: <?= $selectedGroup ? 'flex' : 'none' ?>;
    }
}

.group-unread-badge {
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    color: white;
    border-radius: 12px;
    padding: 4px 10px;
    font-size: 0.75em;
    font-weight: 700;
    min-width: 24px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    animation: badgePulse 0.6s ease;
}

@keyframes badgePulse {
    0%, 100% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-50%) scale(1.2); }
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
                <button class="btn btn-sm btn-primary" onclick="openModal('modalNewGroup')" title="Crear grupo">
                    <i class="fas fa-plus"></i>
                </button>
            <?php endif; ?>
        </div>
        
        <div class="groups-search">
            <input type="text" id="searchGroups" placeholder="Buscar grupos..." autocomplete="off">
        </div>
        
        <div class="groups-list" id="groupsList">
            <?php if (empty($groupsData['groups'])): ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <i class="fas fa-users"></i>
                    <p>No hay grupos disponibles</p>
                </div>
            <?php else: ?>
                <?php foreach ($groupsData['groups'] as $group): ?>
                    <?php
                    // Obtener contador de no leídos para este grupo
                    $groupUnreadCount = 0;
                    try {
                        // Obtener el chat completo para verificar unreadCount
                        $groupDetail = $whatsapp->getChat($group['id']);
                        $groupUnreadCount = $groupDetail['unreadCount'] ?? 0;
                    } catch (Exception $e) {
                        error_log("Error obteniendo unreadCount del grupo {$group['id']}: " . $e->getMessage());
                    }
                    ?>
                    <a href="?page=groups&group=<?= urlencode($group['id']) ?>" 
                       class="group-item <?= $selectedGroup === $group['id'] ? 'active' : '' ?>"
                       data-group-id="<?= htmlspecialchars($group['id']) ?>"
                       data-unread="<?= $groupUnreadCount ?>">
                        <div class="group-avatar">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="group-info">
                            <div class="group-name"><?= htmlspecialchars($group['name']) ?></div>
                            <div class="group-participants">
                                <?= $group['participants'] ?? 0 ?> participantes
                            </div>
                        </div>
                        <?php if ($groupUnreadCount > 0): ?>
                            <span class="group-unread-badge"><?= $groupUnreadCount ?></span>
                        <?php endif; ?>
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
                    <button class="btn-icon" onclick="markGroupAsRead('<?= htmlspecialchars($selectedGroup) ?>')" title="Marcar como leído">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <a href="?page=groups" class="btn-icon" title="Volver">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <!-- Mensajes del Grupo -->
            <div class="messages-container" id="groupMessagesContainer">
                <?php if (empty($groupMessages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No hay mensajes</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupMessages as $msg): ?>
                        <div class="message-bubble <?= $msg['fromMe'] ? 'sent' : 'received' ?>" data-message-id="<?= htmlspecialchars($msg['id']) ?>">
                            <div class="bubble-content">
                                <?php if (!$msg['fromMe']): ?>
                                    <div class="message-sender">
                                        <?php
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
                    <button class="btn-icon" onclick="document.getElementById('groupFileInput').click()" title="Adjuntar">
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
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>Selecciona un grupo</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo Grupo -->
<div id="modalNewGroup" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-users-plus"></i> Crear Nuevo Grupo</h3>
            <button onclick="closeModal('modalNewGroup')" class="btn-icon">&times;</button>
        </div>
        <form id="formNewGroup" onsubmit="return createGroup(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre del Grupo *</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Equipo de Ventas">
                </div>
                <div class="form-group">
                    <label>Participantes (un número por línea) *</label>
                    <textarea name="participantes" class="form-control" rows="6" 
                              placeholder="5493482309495&#10;5491123456789&#10;..." required></textarea>
                    <small class="form-text">Mínimo 2 participantes. Formato: código de país + número</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalNewGroup')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Crear Grupo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Info del Grupo -->
<div id="modalGroupInfo" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Información del Grupo</h3>
            <button onclick="closeModal('modalGroupInfo')" class="btn-icon">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('info')">
                    <i class="fas fa-info-circle"></i> Información
                </div>
                <div class="tab" onclick="switchTab('participants')">
                    <i class="fas fa-users"></i> Participantes
                </div>
                <div class="tab" onclick="switchTab('settings')">
                    <i class="fas fa-cog"></i> Configuración
                </div>
            </div>
            
            <!-- Tab Información -->
            <div id="tabInfo" class="tab-content active">
                <?php if ($groupInfo): ?>
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Nombre del Grupo</label>
                        <div style="padding: 12px; background: var(--group-bg); border-radius: 8px; font-weight: 500;">
                            <?= htmlspecialchars($groupInfo['name']) ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Descripción</label>
                        <div style="padding: 12px; background: var(--group-bg); border-radius: 8px;">
                            <?= htmlspecialchars($groupInfo['description'] ?? 'Sin descripción') ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Creado</label>
                        <div style="padding: 12px; background: var(--group-bg); border-radius: 8px;">
                            <?= isset($groupInfo['createdAt']) ? date('d/m/Y H:i', $groupInfo['createdAt']) : 'No disponible' ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-shield"></i> Creador</label>
                        <div style="padding: 12px; background: var(--group-bg); border-radius: 8px;">
                            <?= htmlspecialchars($groupInfo['owner'] ?? 'Desconocido') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Participantes -->
            <div id="tabParticipants" class="tab-content">
                <?php if ($groupInfo && isset($groupInfo['participants'])): ?>
                    <div style="margin-bottom: 15px;">
                        <button class="btn btn-sm btn-primary" onclick="openAddParticipantsModal()">
                            <i class="fas fa-user-plus"></i> Agregar Participantes
                        </button>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($groupInfo['participants'] as $participant): ?>
                            <?php 
                            $participantId = $participant['id']['_serialized'] ?? '';
                            $participantNumber = $participant['id']['user'] ?? 'Desconocido';
                            $isAdmin = $participant['isAdmin'] ?? false;
                            ?>
                            <div class="participant-item">
                                <div class="participant-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="participant-info">
                                    <div class="participant-name">
                                        <?= htmlspecialchars($participantNumber) ?>
                                        <?php if ($isAdmin): ?>
                                            <span class="admin-badge">
                                                <i class="fas fa-crown"></i> Admin
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="participant-number">+<?= htmlspecialchars($participantNumber) ?></div>
                                </div>
                                <?php if ($canManage): ?>
                                    <div class="participant-actions">
                                        <?php if ($isAdmin): ?>
                                            <button class="btn-icon" onclick="demoteParticipant('<?= htmlspecialchars($participantId) ?>')" title="Quitar admin">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-icon" onclick="promoteParticipant('<?= htmlspecialchars($participantId) ?>')" title="Promover a admin">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon danger" onclick="removeParticipant('<?= htmlspecialchars($participantId) ?>')" title="Eliminar">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Configuración -->
            <div id="tabSettings" class="tab-content">
                <?php if ($canManage): ?>
                    <!-- Editar Nombre -->
                    <div class="form-group">
                        <label><i class="fas fa-edit"></i> Cambiar Nombre del Grupo</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="editGroupName" class="form-control" 
                                   value="<?= htmlspecialchars($groupInfo['name'] ?? '') ?>">
                            <button class="btn btn-primary" onclick="updateGroupName()">
                                <i class="fas fa-check"></i> Guardar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Editar Descripción -->
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Cambiar Descripción</label>
                        <div style="display: flex; gap: 8px; flex-direction: column;">
                            <textarea id="editGroupDescription" class="form-control" rows="3"><?= htmlspecialchars($groupInfo['description'] ?? '') ?></textarea>
                            <button class="btn btn-primary" onclick="updateGroupDescription()" style="align-self: flex-end;">
                                <i class="fas fa-check"></i> Guardar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Código de Invitación -->
                    <div class="form-group">
                        <label><i class="fas fa-link"></i> Código de Invitación</label>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn btn-success" onclick="getInviteCode()">
                                <i class="fas fa-share-alt"></i> Obtener Link
                            </button>
                            <button class="btn btn-warning" onclick="revokeInviteCode()">
                                <i class="fas fa-times-circle"></i> Revocar Link
                            </button>
                        </div>
                        <div id="inviteCodeDisplay" style="margin-top: 10px; display: none;">
                            <input type="text" id="inviteCodeInput" class="form-control" readonly>
                            <button class="btn btn-sm btn-primary" onclick="copyInviteCode()" style="margin-top: 8px;">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Salir del Grupo -->
                    <div class="form-group">
                        <label><i class="fas fa-sign-out-alt"></i> Abandonar Grupo</label>
                        <button class="btn btn-danger" onclick="leaveGroup()">
                            <i class="fas fa-door-open"></i> Salir del Grupo
                        </button>
                        <small class="form-text" style="color: #f44336;">
                            Esta acción no se puede deshacer. Dejarás de recibir mensajes del grupo.
                        </small>
                    </div>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #667781;">
                        <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>No tienes permisos para modificar la configuración del grupo</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal('modalGroupInfo')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Agregar Participantes -->
<div id="modalAddParticipants" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Agregar Participantes</h3>
            <button onclick="closeModal('modalAddParticipants')" class="btn-icon">&times;</button>
        </div>
        <form id="formAddParticipants" onsubmit="return addParticipants(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Números de WhatsApp (uno por línea) *</label>
                    <textarea name="participantes" class="form-control" rows="6" 
                              placeholder="5493482309495&#10;5491123456789&#10;..." required></textarea>
                    <small class="form-text">Formato: código de país + número (sin espacios ni guiones)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalAddParticipants')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Agregar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const selectedGroupId = '<?= htmlspecialchars($selectedGroup ?? '') ?>';
let groupUpdateInterval = null;
let lastMessageTimestamp = 0;
let messageIds = new Set();

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    // Cargar IDs de mensajes existentes
    document.querySelectorAll('.message-bubble[data-message-id]').forEach(bubble => {
        messageIds.add(bubble.dataset.messageId);
    });

    if (messageIds.size > 0) {
        lastMessageTimestamp = Math.floor(Date.now() / 1000);
    }

    // Configurar textarea
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
        setTimeout(() => scrollToBottom(container), 100);
    }

    // Búsqueda de grupos
    const searchInput = document.getElementById('searchGroups');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            searchGroups(e.target.value.toLowerCase());
        });
    }

    // Iniciar actualización periódica
    if (selectedGroupId) {
        groupUpdateInterval = setInterval(updateGroupMessages, 3000);
    }
});

// ===== BÚSQUEDA =====
function searchGroups(filter) {
    const items = document.querySelectorAll('.group-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
}

// ===== TABS =====
function switchTab(tabName) {
    // Ocultar todos los tabs
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Mostrar tab seleccionado
    const tabButtons = document.querySelectorAll('.tab');
    tabButtons.forEach(tab => {
        if (tab.textContent.toLowerCase().includes(tabName)) {
            tab.classList.add('active');
        }
    });
    
    const contentId = 'tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
    const content = document.getElementById(contentId);
    if (content) {
        content.classList.add('active');
    }
}

// ===== CREAR GRUPO =====
async function createGroup(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const participantes = formData.get('participantes')
        .split('\n')
        .map(n => n.trim())
        .filter(n => n && n.length >= 10);
    
    if (participantes.length < 2) {
        showNotification('Debes agregar al menos 2 participantes', 'error');
        return false;
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    
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
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Crear Grupo';
    }
    
    return false;
}

// ===== ENVIAR MENSAJE =====
async function sendGroupMessage() {
    const input = document.getElementById('groupMessageInput');
    const sendBtn = document.getElementById('groupSendBtn');
    const message = input.value.trim();
    
    if (!message || !selectedGroupId) {
        console.error('Falta mensaje o groupId');
        return;
    }
    
    console.log('📤 Enviando mensaje:', {
        to: selectedGroupId,
        message: message,
        messageLength: message.length
    });
    
    const container = document.getElementById('groupMessagesContainer');
    
    // Deshabilitar input mientras se envía
    input.disabled = true;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const requestBody = {
            to: selectedGroupId,
            message: message
        };
        
        console.log('📦 Enviando request:', requestBody);
        
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });
        
        console.log('📡 Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Respuesta no es JSON:', text);
            throw new Error('La respuesta del servidor no es JSON válida');
        }
        
        const result = await response.json();
        console.log('📨 Response data:', result);
        
        if (result.success) {
            // Limpiar input
            input.value = '';
            input.style.height = 'auto';
            
            console.log('✅ Mensaje enviado con ID:', result.messageId);
            
            // Mensaje optimista
            const optimisticMessage = {
                id: result.messageId || 'temp_' + Date.now(),
                body: message,
                fromMe: true,
                timestamp: result.timestamp || Math.floor(Date.now() / 1000)
            };
            
            const bubble = createMessageBubble(optimisticMessage);
            container.appendChild(bubble);
            scrollToBottom(container);
            
            if (typeof showNotification === 'function') {
                showNotification('Mensaje enviado', 'success');
            }
            
            // Actualizar mensajes después de 2 segundos
            setTimeout(() => {
                console.log('🔄 Actualizando mensajes del grupo...');
                updateGroupMessages();
            }, 2000);
            
        } else {
            console.error('❌ Error del servidor:', result.error);
            if (typeof showNotification === 'function') {
                showNotification('Error: ' + (result.error || 'No se pudo enviar'), 'error');
            } else {
                alert('Error: ' + (result.error || 'No se pudo enviar'));
            }
        }
    } catch (error) {
        console.error('❌ Error al enviar mensaje:', error);
        if (typeof showNotification === 'function') {
            showNotification('Error de conexión: ' + error.message, 'error');
        } else {
            alert('Error de conexión: ' + error.message);
        }
    } finally {
        // Rehabilitar input
        input.disabled = false;
        input.focus();
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
    }
}

// ===== ACTUALIZAR MENSAJES =====
async function updateGroupMessages() {
    if (!selectedGroupId) return;
    
    try {
        const url = `api/get-chat-messages.php?chatId=${encodeURIComponent(selectedGroupId)}&after=${lastMessageTimestamp}&t=${Date.now()}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            const container = document.getElementById('groupMessagesContainer');
            const wasAtBottom = shouldAutoScroll(container);
            
            // Remover mensajes temporales
            document.querySelectorAll('.message-bubble.sending').forEach(b => b.remove());
            
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

// ===== CREAR ELEMENTO DE MENSAJE =====
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
            ${!msg.fromMe ? `<div class="message-sender">${escapeHtml(senderName)}</div>` : ''}
            ${msg.body ? `<div class="message-text">${escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>` : ''}
            <div class="message-time">
                ${time}
                ${msg.fromMe ? '<span><i class="fas fa-check-double"></i></span>' : ''}
            </div>
        </div>
    `;
    
    return bubble;
}

// ===== UTILIDADES =====
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
    container.scrollTo({
        top: container.scrollHeight,
        behavior: 'smooth'
    });
}

// ===== MARCAR COMO LEÍDO =====
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

// ===== ABRIR MODAL INFO =====
function openGroupInfoModal() {
    openModal('modalGroupInfo');
}

// ===== ACTUALIZAR NOMBRE DEL GRUPO =====
async function updateGroupName() {
    const newName = document.getElementById('editGroupName').value.trim();
    
    if (!newName) {
        showNotification('El nombre no puede estar vacío', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_name',
                groupId: selectedGroupId,
                nombre: newName
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Nombre actualizado exitosamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== ACTUALIZAR DESCRIPCIÓN =====
async function updateGroupDescription() {
    const newDescription = document.getElementById('editGroupDescription').value.trim();
    
    if (!newDescription) {
        showNotification('La descripción no puede estar vacía', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_description',
                groupId: selectedGroupId,
                descripcion: newDescription
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Descripción actualizada exitosamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== OBTENER CÓDIGO DE INVITACIÓN =====
async function getInviteCode() {
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_invite_code',
                groupId: selectedGroupId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const display = document.getElementById('inviteCodeDisplay');
            const input = document.getElementById('inviteCodeInput');
            input.value = result.inviteLink;
            display.style.display = 'block';
            showNotification('Código de invitación obtenido', 'success');
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== COPIAR CÓDIGO DE INVITACIÓN =====
function copyInviteCode() {
    const input = document.getElementById('inviteCodeInput');
    input.select();
    document.execCommand('copy');
    showNotification('Link copiado al portapapeles', 'success');
}

// ===== REVOCAR CÓDIGO DE INVITACIÓN =====
async function revokeInviteCode() {
    if (!confirm('¿Estás seguro de que quieres revocar el link de invitación? El link actual dejará de funcionar.')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'revoke_invite',
                groupId: selectedGroupId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('inviteCodeDisplay').style.display = 'none';
            showNotification('Link de invitación revocado', 'success');
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== ABRIR MODAL AGREGAR PARTICIPANTES =====
function openAddParticipantsModal() {
    closeModal('modalGroupInfo');
    openModal('modalAddParticipants');
}

// ===== AGREGAR PARTICIPANTES =====
async function addParticipants(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const participantes = formData.get('participantes')
        .split('\n')
        .map(n => n.trim())
        .filter(n => n && n.length >= 10);
    
    if (participantes.length === 0) {
        showNotification('Debes agregar al menos un participante', 'error');
        return false;
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_participants',
                groupId: selectedGroupId,
                participantes: participantes
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participantes agregados exitosamente', 'success');
            closeModal('modalAddParticipants');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Agregar';
    }
    
    return false;
}

// ===== ELIMINAR PARTICIPANTE =====
async function removeParticipant(participantId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este participante del grupo?')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove_participant',
                groupId: selectedGroupId,
                participantId: participantId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participante eliminado exitosamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== PROMOVER A ADMINISTRADOR =====
async function promoteParticipant(participantId) {
    if (!confirm('¿Quieres promover a este participante como administrador del grupo?')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'promote',
                groupId: selectedGroupId,
                participantId: participantId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participante promovido a administrador', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== DEGRADAR ADMINISTRADOR =====
async function demoteParticipant(participantId) {
    if (!confirm('¿Quieres quitar los permisos de administrador a este participante?')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'demote',
                groupId: selectedGroupId,
                participantId: participantId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Permisos de administrador removidos', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== SALIR DEL GRUPO =====
async function leaveGroup() {
    if (!confirm('¿Estás seguro de que quieres salir de este grupo? Esta acción no se puede deshacer.')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'leave',
                groupId: selectedGroupId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Has salido del grupo', 'success');
            setTimeout(() => {
                window.location.href = '?page=groups';
            }, 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// ===== LIMPIAR AL SALIR =====
window.addEventListener('beforeunload', () => {
    if (groupUpdateInterval) {
        clearInterval(groupUpdateInterval);
    }
});

// ===== ACTUALIZAR CONTADORES DE NO LEÍDOS EN GRUPOS =====
async function updateGroupUnreadCounts() {
    try {
        const response = await fetch('api/get-chats.php?t=' + Date.now());
        const data = await response.json();
        
        if (!data.success || !data.chats) return;
        
        // Filtrar solo grupos
        const groups = data.chats.filter(chat => chat.isGroup);
        
        // Actualizar cada grupo en la lista
        groups.forEach(group => {
            const groupItem = document.querySelector(`.group-item[data-group-id="${group.id}"]`);
            if (!groupItem) return;
            
            const unreadCount = group.unreadCount || 0;
            const currentUnread = parseInt(groupItem.dataset.unread) || 0;
            
            // Solo actualizar si cambió
            if (unreadCount !== currentUnread) {
                groupItem.dataset.unread = unreadCount;
                
                let badge = groupItem.querySelector('.group-unread-badge');
                
                if (unreadCount > 0) {
                    if (badge) {
                        badge.textContent = unreadCount;
                        badge.style.animation = 'badgePulse 0.6s ease';
                    } else {
                        badge = document.createElement('span');
                        badge.className = 'group-unread-badge';
                        badge.textContent = unreadCount;
                        groupItem.appendChild(badge);
                    }
                } else if (badge) {
                    badge.remove();
                }
            }
        });
        
        console.log('✅ Contadores de grupos actualizados');
        
    } catch (error) {
        console.error('Error actualizando contadores de grupos:', error);
    }
}

// Actualizar contadores cada 5 segundos
setInterval(updateGroupUnreadCounts, 5000);

// Actualizar inmediatamente al cargar
setTimeout(updateGroupUnreadCounts, 2000);
</script>