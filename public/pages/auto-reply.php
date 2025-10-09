<?php
// Verificar permiso de visualización
Auth::requirePermission('view_auto_reply');

// Obtener instancia de Auth para verificaciones adicionales
$auth = Auth::getInstance();

// Verificar permisos para acciones específicas
$canManage = $auth->hasPermission('manage_auto_reply');

// Obtener respuestas automáticas con estadísticas
$autoReplies = $db->fetchAll("
    SELECT * FROM respuestas_automaticas 
    ORDER BY prioridad DESC, palabra_clave ASC
");

// Obtener configuración del bot
$botConfig = $db->fetch("
    SELECT valor FROM configuracion 
    WHERE clave = 'bot_activo'
") ?? ['valor' => '0'];

$botActivo = (bool)$botConfig['valor'];

// Estadísticas generales
$statsHoy = $db->fetch("
    SELECT 
        COUNT(*) as total_respuestas,
        COUNT(DISTINCT numero_remitente) as usuarios_unicos
    FROM mensajes_entrantes 
    WHERE DATE(fecha_recepcion) = CURDATE()
    AND procesado = 1
");
?>

<style>
.auto-reply-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 25px;
}

.bot-status-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.bot-status-info h3 {
    margin: 0 0 8px 0;
    font-size: 1.4em;
}

.bot-status-info p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.95em;
}

.bot-toggle-btn {
    background: rgba(255,255,255,0.2);
    border: 2px solid white;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bot-toggle-btn:hover {
    background: white;
    color: #667eea;
}

.bot-toggle-btn.active {
    background: #10b981;
    border-color: #10b981;
}

.bot-toggle-btn.active:hover {
    background: #059669;
    border-color: #059669;
    color: white;
}

.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-mini-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.stat-mini-card h4 {
    margin: 0 0 8px 0;
    color: var(--gray);
    font-size: 0.85em;
    font-weight: 500;
    text-transform: uppercase;
}

.stat-mini-card .value {
    font-size: 2em;
    font-weight: 700;
    color: var(--primary);
}

.reply-item {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 12px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 20px;
    align-items: start;
    transition: all 0.2s;
}

.reply-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.reply-item.inactive {
    opacity: 0.6;
    background: #f9fafb;
}

.reply-content h4 {
    margin: 0 0 8px 0;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.reply-content p {
    margin: 0 0 12px 0;
    color: var(--text);
    line-height: 1.6;
}

.reply-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: var(--light);
    border-radius: 4px;
    font-size: 0.85em;
    color: var(--gray);
}

.meta-badge i {
    font-size: 0.9em;
}

.reply-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.empty-state-replies {
    text-align: center;
    padding: 60px 20px;
}

.empty-state-replies i {
    font-size: 4em;
    color: #ddd;
    margin-bottom: 20px;
}

.empty-state-replies h3 {
    color: var(--gray);
    margin-bottom: 10px;
}

.empty-state-replies p {
    color: var(--gray);
    margin-bottom: 25px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 26px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success);
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.modal-body .info-box {
    background: #f0f9ff;
    border-left: 4px solid #0ea5e9;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.modal-body .info-box p {
    margin: 0;
    color: #0c4a6e;
    font-size: 0.9em;
}

.variable-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.variable-chip {
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-family: 'Courier New', monospace;
    cursor: pointer;
    transition: all 0.2s;
}

.variable-chip:hover {
    background: #c7d2fe;
}

@media (max-width: 768px) {
    .reply-item {
        grid-template-columns: 1fr;
    }
    
    .reply-actions {
        flex-direction: row;
    }
}
</style>

<div class="page-header">
    <div>
        <h2><i class="fas fa-robot"></i> Respuestas Automáticas</h2>
        <p>Bot de respuestas inteligente basado en palabras clave</p>
    </div>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openModal('modalNewReply')">
        <i class="fas fa-plus"></i> Nueva Respuesta
    </button>
<?php endif; ?>
</div>

<!-- Estado del Bot -->
<div class="bot-status-card">
    <div class="bot-status-info">
        <h3>
            <i class="fas fa-robot"></i> Bot de Respuestas
        </h3>
        <p>
            Estado: <strong id="botStatusText"><?= $botActivo ? 'Activo' : 'Inactivo' ?></strong>
            • <?= count($autoReplies) ?> respuestas configuradas
        </p>
    </div>
    <button 
        class="bot-toggle-btn <?= $botActivo ? 'active' : '' ?>" 
        id="botToggleBtn"
        onclick="toggleBot()">
        <i class="fas fa-power-off"></i>
        <span id="botToggleBtnText"><?= $botActivo ? 'Desactivar' : 'Activar' ?></span>
    </button>
</div>

<!-- Estadísticas Mini -->
<div class="stats-mini-grid">
    <div class="stat-mini-card">
        <h4>Respuestas Hoy</h4>
        <div class="value"><?= number_format($statsHoy['total_respuestas'] ?? 0) ?></div>
    </div>
    <div class="stat-mini-card">
        <h4>Usuarios Atendidos</h4>
        <div class="value"><?= number_format($statsHoy['usuarios_unicos'] ?? 0) ?></div>
    </div>
    <div class="stat-mini-card">
        <h4>Respuestas Activas</h4>
        <div class="value"><?= count(array_filter($autoReplies, fn($r) => $r['activa'])) ?></div>
    </div>
    <div class="stat-mini-card">
        <h4>Tasa de Uso</h4>
        <div class="value">
            <?php
            $totalUsos = array_sum(array_column($autoReplies, 'contador_usos'));
            echo $totalUsos > 0 ? number_format($totalUsos) : '0';
            ?>
        </div>
    </div>
</div>

<!-- Lista de Respuestas -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Respuestas Configuradas</h3>
        <input 
            type="text" 
            id="searchReplies" 
            placeholder="Buscar respuesta..." 
            style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; width: 250px;">
    </div>
    <div class="card-body" id="repliesList">
        <?php if (empty($autoReplies)): ?>
            <div class="empty-state-replies">
                <i class="fas fa-robot"></i>
                <h3>No hay respuestas automáticas</h3>
                <p>Crea tu primera respuesta automática para comenzar a usar el bot</p>
                <button class="btn btn-primary" onclick="openModal('modalNewReply')">
                    <i class="fas fa-plus"></i> Crear Primera Respuesta
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($autoReplies as $reply): ?>
                <div class="reply-item <?= !$reply['activa'] ? 'inactive' : '' ?>" data-reply-id="<?= $reply['id'] ?>">
                    <div class="reply-content">
                        <h4>
                            <i class="fas fa-key"></i>
                            <strong><?= htmlspecialchars($reply['palabra_clave']) ?></strong>
                            <?php if (!$reply['activa']): ?>
                                <span class="badge" style="background: #6c757d; color: white;">Inactiva</span>
                            <?php endif; ?>
                        </h4>
                        <p><?= nl2br(htmlspecialchars($reply['respuesta'])) ?></p>
                        <div class="reply-meta">
                            <span class="meta-badge">
                                <i class="fas fa-<?= $reply['exacta'] ? 'equals' : 'search' ?>"></i>
                                <?= $reply['exacta'] ? 'Exacta' : 'Contiene' ?>
                            </span>
                            <span class="meta-badge">
                                <i class="fas fa-layer-group"></i>
                                Prioridad: <?= $reply['prioridad'] ?>
                            </span>
                            <span class="meta-badge">
                                <i class="fas fa-chart-line"></i>
                                Usada <?= $reply['contador_usos'] ?> veces
                            </span>
                            <span class="meta-badge">
                                <i class="fas fa-clock"></i>
                                <?= date('d/m/Y', strtotime($reply['fecha_creacion'])) ?>
                            </span>
                        </div>
                    </div>
                            <label class="switch" title="<?= $reply['activa'] ? 'Desactivar' : 'Activar' ?>">

                        <button 
                            class="btn btn-sm btn-icon" 
                            onclick="deleteReply(<?= $reply['id'] ?>)"
                            title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva/Editar Respuesta -->
<div id="modalNewReply" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-robot"></i> <span id="modalTitle">Nueva Respuesta Automática</span></h3>
            <button onclick="closeModal('modalNewReply')" class="btn-close">&times;</button>
        </div>
        <form id="formNewReply" onsubmit="return saveReply(event)">
            <input type="hidden" name="id" id="replyId">
            <div class="modal-body">
                <div class="info-box">
    <p><i class="fas fa-info-circle"></i> <strong>Tip:</strong> Usa variables como {nombre}, {horarios} para personalizar las respuestas. El bot procesará las respuestas en orden de prioridad.</p>
</div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Palabra Clave * <small>(sensible a mayúsculas/minúsculas)</small></label>
                    <input 
                        type="text" 
                        name="palabra_clave" 
                        id="palabraClave"
                        class="form-control" 
                        placeholder="Ejemplo: hola, precios, horarios, ubicación"
                        required>
                    <small style="color: var(--gray);">La palabra o frase que activará esta respuesta</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-comment-dots"></i> Respuesta * <small>(máx. 1000 caracteres)</small></label>
                    <textarea 
                        name="respuesta" 
                        id="respuestaTexto"
                        class="form-control" 
                        rows="6" 
                        maxlength="1000"
                        placeholder="Ejemplo: ¡Hola {nombre}! Gracias por contactarnos. ¿En qué podemos ayudarte?"
                        required></textarea>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                        <small style="color: var(--gray);">Variables disponibles:</small>
                        <small id="charCount" style="color: var(--gray);">0/1000</small>
                    </div>
                    <div class="variable-chips">
    <span class="variable-chip" onclick="insertVariable('{nombre}')">{nombre}</span>
    <span class="variable-chip" onclick="insertVariable('{numero}')">{numero}</span>
    <span class="variable-chip" onclick="insertVariable('{fecha}')">{fecha}</span>
    <span class="variable-chip" onclick="insertVariable('{hora}')">{hora}</span>
    <span class="variable-chip" onclick="insertVariable('{horarios}')" title="Muestra horarios de atención">{horarios}</span>
</div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sliders-h"></i> Tipo de Coincidencia</label>
                    <select name="exacta" id="tipoCoincidencia" class="form-control">
                        <option value="0">Contiene la palabra (recomendado - más flexible)</option>
                        <option value="1">Coincidencia exacta (más estricta)</option>
                    </select>
                    <small style="color: var(--gray);">
                        • <strong>Contiene:</strong> "hola mundo" activará con "hola"<br>
                        • <strong>Exacta:</strong> solo "hola" activará (sin texto adicional)
                    </small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> Prioridad (0-10)</label>
                    <input 
                        type="number" 
                        name="prioridad" 
                        id="prioridad"
                        class="form-control" 
                        value="5" 
                        min="0" 
                        max="10"
                        style="width: 120px;">
                    <small style="color: var(--gray);">Mayor número = mayor prioridad. Si múltiples respuestas coinciden, se usa la de mayor prioridad.</small>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="activa" id="activaCheck" checked style="width: auto;">
                        <i class="fas fa-toggle-on"></i> Activar inmediatamente
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalNewReply')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Guardar Respuesta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Probar Respuesta -->
<div id="modalTestReply" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-vial"></i> Probar Respuesta</h3>
            <button onclick="closeModal('modalTestReply')" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Palabra clave:</label>
                <input type="text" id="testKeyword" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>Respuesta que se enviará:</label>
                <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; border-left: 4px solid #0ea5e9;">
                    <p id="testResponse" style="margin: 0; white-space: pre-wrap;"></p>
                </div>
            </div>
            <div class="info-box">
                <p><i class="fas fa-info-circle"></i> Las variables se reemplazarán automáticamente con los datos del contacto al enviar.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('modalTestReply')">
                <i class="fas fa-check"></i> Entendido
            </button>
        </div>
    </div>
</div>

<script>
let botEstado = <?= $botActivo ? 'true' : 'false' ?>;

// ============ TOGGLE BOT ============
async function toggleBot() {
    const btn = document.getElementById('botToggleBtn');
    const btnText = document.getElementById('botToggleBtnText');
    const statusText = document.getElementById('botStatusText');
    
    btn.disabled = true;
    btnText.textContent = 'Procesando...';
    
    try {
        const response = await fetch('api/auto-reply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'toggle_bot',
                estado: !botEstado
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            botEstado = !botEstado;
            
            if (botEstado) {
                btn.classList.add('active');
                btnText.textContent = 'Desactivar';
                statusText.innerHTML = '<strong>Activo</strong>';
                showNotification('Bot activado correctamente', 'success');
            } else {
                btn.classList.remove('active');
                btnText.textContent = 'Activar';
                statusText.innerHTML = '<strong>Inactivo</strong>';
                showNotification('Bot desactivado', 'info');
            }
        } else {
            showNotification('Error: ' + (result.error || 'No se pudo cambiar el estado'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    } finally {
        btn.disabled = false;
    }
}

// ============ GUARDAR RESPUESTA ============
async function saveReply(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    data.activa = document.getElementById('activaCheck').checked ? 1 : 0;
    
    // Validaciones
    if (data.palabra_clave.trim().length < 2) {
        showNotification('La palabra clave debe tener al menos 2 caracteres', 'error');
        return false;
    }
    
    if (data.respuesta.trim().length < 5) {
        showNotification('La respuesta debe tener al menos 5 caracteres', 'error');
        return false;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    try {
        const action = data.id ? 'update' : 'create';
        
        const response = await fetch('api/auto-reply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                action === 'create' ? 'Respuesta creada correctamente' : 'Respuesta actualizada',
                'success'
            );
            closeModal('modalNewReply');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error: ' + (result.error || 'No se pudo guardar'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
    
    return false;
}

// ============ EDITAR RESPUESTA ============
async function editReply(id) {
    try {
        const response = await fetch(`api/auto-reply.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const reply = result.data;
            
            document.getElementById('modalTitle').textContent = 'Editar Respuesta Automática';
            document.getElementById('replyId').value = reply.id;
            document.getElementById('palabraClave').value = reply.palabra_clave;
            document.getElementById('respuestaTexto').value = reply.respuesta;
            document.getElementById('tipoCoincidencia').value = reply.exacta;
            document.getElementById('prioridad').value = reply.prioridad;
            document.getElementById('activaCheck').checked = reply.activa == 1;
            
            updateCharCount();
            openModal('modalNewReply');
        } else {
            showNotification('No se pudo cargar la respuesta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// ============ ELIMINAR RESPUESTA ============
async function deleteReply(id) {
    if (!confirm('¿Estás seguro de eliminar esta respuesta automática?\n\nEsta acción no se puede deshacer.')) {
        return;
    }
    
    try {
        const response = await fetch('api/auto-reply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Respuesta eliminada correctamente', 'success');
            document.querySelector(`[data-reply-id="${id}"]`).remove();
            
            // Si no quedan respuestas, recargar para mostrar empty state
            if (document.querySelectorAll('.reply-item').length === 0) {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showNotification('Error: ' + (result.error || 'No se pudo eliminar'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// ============ TOGGLE INDIVIDUAL ============
async function toggleReply(id, estado) {
    try {
        const response = await fetch('api/auto-reply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle', id, estado })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const item = document.querySelector(`[data-reply-id="${id}"]`);
            if (estado) {
                item.classList.remove('inactive');
            } else {
                item.classList.add('inactive');
            }
            showNotification(
                estado ? 'Respuesta activada' : 'Respuesta desactivada',
                'success'
            );
        } else {
            showNotification('Error al cambiar el estado', 'error');
            location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// ============ PROBAR RESPUESTA ============
function testReply(keyword, response) {
    document.getElementById('testKeyword').value = keyword;
    document.getElementById('testResponse').textContent = response;
    openModal('modalTestReply');
}

// ============ INSERTAR VARIABLE ============
function insertVariable(variable) {
    const textarea = document.getElementById('respuestaTexto');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    
    updateCharCount();
}

// ============ CONTADOR DE CARACTERES ============
function updateCharCount() {
    const textarea = document.getElementById('respuestaTexto');
    const count = document.getElementById('charCount');
    if (textarea && count) {
        count.textContent = `${textarea.value.length}/1000`;
        count.style.color = textarea.value.length > 900 ? '#dc3545' : 'var(--gray)';
    }
}

// ============ BÚSQUEDA ============
document.getElementById('searchReplies')?.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const items = document.querySelectorAll('.reply-item');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
});

// ============ EVENT LISTENERS ============
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('respuestaTexto');
    if (textarea) {
        textarea.addEventListener('input', updateCharCount);
    }
    
    // Reset form al abrir modal
    document.getElementById('modalNewReply')?.addEventListener('click', function(e) {
        if (e.target === this) {
            resetForm();
        }
    });
});

function resetForm() {
    document.getElementById('formNewReply').reset();
    document.getElementById('replyId').value = '';
    document.getElementById('modalTitle').textContent = 'Nueva Respuesta Automática';
    document.getElementById('activaCheck').checked = true;
    updateCharCount();
}

// ============ ATAJOS DE TECLADO ============
document.addEventListener('keydown', function(e) {
    // ESC para cerrar modales
    if (e.key === 'Escape') {
        closeModal('modalNewReply');
        closeModal('modalTestReply');
    }
    
    // Ctrl/Cmd + S para guardar (en el modal)
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        const modal = document.getElementById('modalNewReply');
        if (modal && modal.style.display === 'flex') {
            e.preventDefault();
            document.getElementById('formNewReply').dispatchEvent(new Event('submit'));
        }
    }
});

// ============ VALIDACIÓN EN TIEMPO REAL ============
document.getElementById('palabraClave')?.addEventListener('blur', function() {
    this.value = this.value.trim();
});

console.log('✅ Sistema de respuestas automáticas cargado correctamente');
console.log('🤖 Bot estado:', botEstado ? 'ACTIVO' : 'INACTIVO');
</script>