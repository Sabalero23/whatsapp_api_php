<!-- Modal de Administración de Grupos -->
<div id="modalGroupManagement" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-cog"></i> <span id="groupManagementTitle">Administrar Grupo</span></h3>
            <button onclick="closeModal('modalGroupManagement')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <!-- Tabs -->
            <div style="display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid var(--border);">
                <button class="group-tab active" data-tab="info" onclick="switchGroupTab('info')">
                    <i class="fas fa-info-circle"></i> Información
                </button>
                <button class="group-tab" data-tab="participants" onclick="switchGroupTab('participants')">
                    <i class="fas fa-users"></i> Participantes
                </button>
                <button class="group-tab" data-tab="settings" onclick="switchGroupTab('settings')">
                    <i class="fas fa-cog"></i> Configuración
                </button>
            </div>
            
            <!-- Tab: Información -->
            <div id="tabInfo" class="group-tab-content">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nombre del Grupo</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="groupNameInput" class="form-control" readonly>
                        <button class="btn btn-sm btn-primary" onclick="editGroupName()">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <div style="display: flex; gap: 8px; align-items: start;">
                        <textarea id="groupDescriptionInput" class="form-control" rows="3" readonly></textarea>
                        <button class="btn btn-sm btn-primary" onclick="editGroupDescription()">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-link"></i> Link de Invitación</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="groupInviteLink" class="form-control" readonly>
                        <button class="btn btn-sm btn-primary" onclick="copyInviteLink()">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="revokeInviteLink()">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Participantes -->
            <div id="tabParticipants" class="group-tab-content" style="display: none;">
                <div style="margin-bottom: 15px;">
                    <button class="btn btn-sm btn-primary" onclick="showAddParticipantsModal()">
                        <i class="fas fa-user-plus"></i> Agregar Participantes
                    </button>
                </div>
                
                <div id="participantsList" style="max-height: 400px; overflow-y: auto;">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
            
            <!-- Tab: Configuración -->
            <div id="tabSettings" class="group-tab-content" style="display: none;">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="groupOnlyAdmins">
                        <span><i class="fas fa-lock"></i> Solo administradores pueden enviar mensajes</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="groupEditInfo">
                        <span><i class="fas fa-edit"></i> Solo administradores pueden editar info del grupo</span>
                    </label>
                </div>
                
                <hr>
                
                <div class="form-group">
                    <button class="btn btn-danger" onclick="confirmLeaveGroup()" style="width: 100%;">
                        <i class="fas fa-sign-out-alt"></i> Salir del Grupo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar participantes -->
<div id="modalAddParticipants" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Agregar Participantes</h3>
            <button onclick="closeModal('modalAddParticipants')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray); margin-bottom: 15px;">
                Ingresa los números de teléfono, uno por línea
            </p>
            <textarea id="newParticipantsInput" class="form-control" rows="5" 
                      placeholder="549348230949&#10;549112345678"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal('modalAddParticipants')">Cancelar</button>
            <button class="btn btn-primary" onclick="addParticipantsToGroup()">
                <i class="fas fa-plus"></i> Agregar
            </button>
        </div>
    </div>
</div>

<style>
.group-tab {
    padding: 10px 15px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    font-size: 0.95em;
    color: var(--gray);
}

.group-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.group-tab:hover:not(.active) {
    color: var(--dark);
    background: var(--light);
}

.group-tab-content {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.participant-item {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.participant-item:hover {
    background: var(--light);
}

.participant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.participant-info {
    flex: 1;
    min-width: 0;
}

.participant-name {
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.participant-number {
    font-size: 0.85em;
    color: var(--gray);
}

.participant-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.75em;
    font-weight: 600;
}

.participant-badge.admin {
    background: #fff3e0;
    color: #f57c00;
}

.participant-actions {
    display: flex;
    gap: 5px;
}
</style>

<script>
let currentGroupId = null;
let currentGroupData = null;

// Abrir modal de administración
function openGroupManagementModal(groupId, groupData) {
    currentGroupId = groupId;
    currentGroupData = groupData;
    
    // Llenar información básica
    document.getElementById('groupNameInput').value = groupData.name || '';
    document.getElementById('groupDescriptionInput').value = groupData.description || '';
    
    // Cargar link de invitación
    loadInviteLink(groupId);
    
    // Cargar participantes
    loadParticipants(groupData.participants || []);
    
    // Mostrar modal
    openModal('modalGroupManagement');
}

// Cambiar de tab
function switchGroupTab(tabName) {
    // Ocultar todos los tabs
    document.querySelectorAll('.group-tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    // Remover clase active de todos los botones
    document.querySelectorAll('.group-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Mostrar tab seleccionado
    document.getElementById('tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1)).style.display = 'block';
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
}

// Cargar link de invitación
async function loadInviteLink(groupId) {
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_invite_code',
                groupId: groupId
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.inviteLink) {
            document.getElementById('groupInviteLink').value = result.inviteLink;
        }
    } catch (error) {
        console.error('Error cargando link:', error);
    }
}

// Cargar participantes
function loadParticipants(participants) {
    const container = document.getElementById('participantsList');
    
    if (!participants || participants.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--gray); padding: 20px;">No hay participantes</p>';
        return;
    }
    
    container.innerHTML = participants.map(participant => {
        const id = participant.id?._serialized || participant.id;
        const number = participant.id?.user || id.split('@')[0];
        const isAdmin = participant.isAdmin || false;
        const isSuperAdmin = participant.isSuperAdmin || false;
        
        return `
            <div class="participant-item" data-participant-id="${id}">
                <div class="participant-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="participant-info">
                    <div class="participant-name">${number}</div>
                    ${isAdmin ? '<span class="participant-badge admin"><i class="fas fa-crown"></i> Admin</span>' : ''}
                    ${isSuperAdmin ? '<span class="participant-badge admin"><i class="fas fa-star"></i> Super Admin</span>' : ''}
                </div>
                <div class="participant-actions">
                    ${!isSuperAdmin ? `
                        ${!isAdmin ? 
                            `<button class="btn btn-sm btn-icon" onclick="promoteParticipant('${id}')" title="Promover a admin">
                                <i class="fas fa-arrow-up"></i>
                            </button>` : 
                            `<button class="btn btn-sm btn-icon" onclick="demoteParticipant('${id}')" title="Quitar admin">
                                <i class="fas fa-arrow-down"></i>
                            </button>`
                        }
                        <button class="btn btn-sm btn-icon" onclick="removeParticipant('${id}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

// Editar nombre del grupo
async function editGroupName() {
    const input = document.getElementById('groupNameInput');
    
    if (input.readOnly) {
        input.readOnly = false;
        input.focus();
        input.select();
        return;
    }
    
    const newName = input.value.trim();
    
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
                groupId: currentGroupId,
                nombre: newName
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Nombre actualizado', 'success');
            input.readOnly = true;
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Editar descripción del grupo
async function editGroupDescription() {
    const input = document.getElementById('groupDescriptionInput');
    
    if (input.readOnly) {
        input.readOnly = false;
        input.focus();
        return;
    }
    
    const newDescription = input.value.trim();
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_description',
                groupId: currentGroupId,
                descripcion: newDescription
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Descripción actualizada', 'success');
            input.readOnly = true;
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Copiar link de invitación
function copyInviteLink() {
    const input = document.getElementById('groupInviteLink');
    input.select();
    document.execCommand('copy');
    showNotification('Link copiado al portapapeles', 'success');
}

// Revocar link de invitación
async function revokeInviteLink() {
    if (!confirm('¿Estás seguro de revocar el link actual? Se generará uno nuevo.')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'revoke_invite',
                groupId: currentGroupId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Link revocado', 'success');
            loadInviteLink(currentGroupId);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Mostrar modal para agregar participantes
function showAddParticipantsModal() {
    openModal('modalAddParticipants');
}

// Agregar participantes al grupo
async function addParticipantsToGroup() {
    const input = document.getElementById('newParticipantsInput');
    const numbers = input.value.split('\n')
        .map(n => n.trim())
        .filter(n => n.length > 0);
    
    if (numbers.length === 0) {
        showNotification('Debes ingresar al menos un número', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_participants',
                groupId: currentGroupId,
                participantes: numbers
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participantes agregados', 'success');
            closeModal('modalAddParticipants');
            input.value = '';
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Promover participante a admin
async function promoteParticipant(participantId) {
    if (!confirm('¿Promover a este participante como administrador?')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'promote',
                groupId: currentGroupId,
                participantId: participantId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participante promovido', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Quitar admin a participante
async function demoteParticipant(participantId) {
    if (!confirm('¿Remover permisos de administrador?')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'demote',
                groupId: currentGroupId,
                participantId: participantId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Permisos removidos', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Eliminar participante
async function removeParticipant(participantId) {
    if (!confirm('¿Estás seguro de eliminar a este participante del grupo?')) {
        return;
    }
    
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove_participant',
                groupId: currentGroupId,
                participantId: participantId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participante eliminado', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Confirmar salida del grupo
function confirmLeaveGroup() {
    if (!confirm('¿Estás seguro de salir de este grupo? Esta acción no se puede deshacer.')) {
        return;
    }
    
    leaveGroup();
}

// Salir del grupo
async function leaveGroup() {
    try {
        const response = await fetch('api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'leave',
                groupId: currentGroupId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Has salido del grupo', 'success');
            closeModal('modalGroupManagement');
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

// Función global para abrir desde grupos
function showGroupActions() {
    if (typeof currentGroupData !== 'undefined') {
        openGroupManagementModal(currentGroupId, currentGroupData);
    }
}