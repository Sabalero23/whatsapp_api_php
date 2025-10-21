<?php
// Verificar permiso de visualización
Auth::requirePermission('view_broadcast');

// Obtener instancia de Auth
$auth = Auth::getInstance();

// Verificar permisos específicos
$canCreate = $auth->hasPermission('create_broadcast');
$canSend = $auth->hasPermission('send_broadcast');
$canDelete = $auth->hasPermission('delete_broadcast');

// Obtener difusiones
$difusiones = $db->fetchAll(
    "SELECT * FROM difusiones ORDER BY fecha_creacion DESC LIMIT 50"
);
?>

<div class="page-header">
    <div>
        <h2>Difusión Masiva</h2>
        <p>Envío de mensajes a múltiples contactos</p>
    </div>
    <?php if ($canCreate): ?>
    <button class="btn btn-primary" onclick="openModal('modalNewBroadcast')">
        <i class="fas fa-plus"></i> Nueva Difusión
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($difusiones)): ?>
            <div style="text-align: center; padding: 40px; color: var(--gray);">
                <i class="fas fa-bullhorn" style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>No hay difusiones creadas</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="openModal('modalNewBroadcast')">
                    Crear Primera Difusión
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Destinatarios</th>
                        <th>Progreso</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($difusiones as $dif): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dif['nombre']) ?></strong></td>
                            <td>
                                <?php
                                $badgeClass = 'secondary';
                                switch ($dif['estado']) {
                                    case 'completada': $badgeClass = 'success'; break;
                                    case 'enviando': $badgeClass = 'primary'; break;
                                    case 'programada': $badgeClass = 'info'; break;
                                    case 'cancelada': $badgeClass = 'danger'; break;
                                    case 'borrador': $badgeClass = 'secondary'; break;
                                }
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>">
                                    <?= htmlspecialchars($dif['estado']) ?>
                                </span>
                            </td>
                            <td><?= number_format($dif['total_destinatarios']) ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="flex: 1; background: #e0e0e0; border-radius: 10px; height: 8px; overflow: hidden;">
                                        <?php 
                                        $progress = $dif['total_destinatarios'] > 0 
                                            ? ($dif['enviados'] / $dif['total_destinatarios']) * 100 
                                            : 0;
                                        ?>
                                        <div style="width: <?= $progress ?>%; background: var(--success); height: 100%;"></div>
                                    </div>
                                    <small style="white-space: nowrap;">
                                        <?= $dif['enviados'] ?> / <?= $dif['total_destinatarios'] ?>
                                    </small>
                                </div>
                                <?php if ($dif['fallidos'] > 0): ?>
                                    <small style="color: var(--danger);">
                                        <?= $dif['fallidos'] ?> fallidos
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($dif['fecha_creacion'])) ?></div>
                                <small style="color: var(--gray);"><?= date('H:i', strtotime($dif['fecha_creacion'])) ?></small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-icon" onclick="viewBroadcast(<?= $dif['id'] ?>)" title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if (in_array($dif['estado'], ['completada', 'cancelada'])): ?>
                                    <button class="btn btn-sm btn-icon" onclick="resendBroadcast(<?= $dif['id'] ?>)" title="Reenviar">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($dif['estado'] === 'borrador' && $canSend): ?>
                                    <button class="btn btn-sm btn-icon" onclick="sendBroadcast(<?= $dif['id'] ?>)" title="Enviar ahora">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($dif['estado'], ['borrador', 'completada', 'cancelada']) && $canDelete): ?>
                                    <button class="btn btn-sm btn-icon" onclick="deleteBroadcast(<?= $dif['id'] ?>)" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Difusión -->
<div id="modalNewBroadcast" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3><i class="fas fa-bullhorn"></i> <span id="modalTitle">Nueva Difusión</span></h3>
            <button onclick="closeModalBroadcast()" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <form id="formNewBroadcast" onsubmit="return saveBroadcast(event)">
            <input type="hidden" id="difusionBaseId" name="difusion_base_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre de la Campaña *</label>
                    <input type="text" name="nombre" id="broadcastNombre" class="form-control" required placeholder="Ej: Promoción Navidad 2025">
                </div>
                
                <div class="form-group">
                    <label>Mensaje *</label>
                    <textarea name="mensaje" id="broadcastMensaje" class="form-control" rows="6" required 
                              placeholder="Escribe tu mensaje aquí..."></textarea>
                    <small style="color: var(--gray);">
                        <i class="fas fa-info-circle"></i> Variables disponibles: <code>{nombre}</code>, <code>{numero}</code>, <code>{empresa}</code>
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Destinatarios *</label>
                    
                    <!-- Búsqueda de contactos -->
                    <div style="position: relative; margin-bottom: 10px;">
                        <input type="text" 
                               id="searchContactInput" 
                               class="form-control" 
                               placeholder="Buscar contacto por nombre o número..."
                               autocomplete="off">
                        <div id="searchResults" class="search-results"></div>
                    </div>
                    
                    <!-- Botones de acción rápida -->
                    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-sm" onclick="selectAllContacts()">
                            <i class="fas fa-check-double"></i> Todos los Contactos
                        </button>
                        <button type="button" class="btn btn-sm" onclick="selectByTag()">
                            <i class="fas fa-tag"></i> Por Etiqueta
                        </button>
                        <button type="button" class="btn btn-sm" onclick="clearDestinations()">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                    
                    <!-- Lista de contactos seleccionados -->
                    <div id="selectedContactsList" class="selected-contacts-list">
                        <div style="padding: 20px; text-align: center; color: var(--gray); border: 2px dashed var(--border); border-radius: 8px; width: 100%;">
                            No hay contactos seleccionados
                        </div>
                    </div>
                    
                    <!-- Input oculto con números -->
                    <input type="hidden" id="destinatariosHidden" name="destinatarios">
                    
                    <div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <small id="destinatariosCount" style="font-weight: 600; color: var(--primary);">0 destinatarios</small>
                        <small style="color: var(--gray);">Haz clic en "x" para eliminar un contacto</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-clock"></i> Programar envío (opcional)
                    </label>
                    <input type="datetime-local" name="programada_para" class="form-control">
                    <small style="color: var(--gray);">Dejar vacío para envío inmediato</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-hourglass-half"></i> Delay entre mensajes (segundos)
                    </label>
                    <input type="number" name="delay" class="form-control" value="3" min="1" max="60">
                    <small style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle"></i> Recomendado: 3-5 segundos para evitar bloqueos de WhatsApp
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModalBroadcast()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="save_draft" class="btn">
                    <i class="fas fa-save"></i> Guardar Borrador
                </button>
                <button type="submit" name="send_now" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Enviar Ahora
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Detalles -->
<div id="modalViewBroadcast" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalles de Difusión</h3>
            <button onclick="closeModal('modalViewBroadcast')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" id="broadcastDetailsContent">
            <!-- Se llena dinámicamente -->
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal('modalViewBroadcast')">Cerrar</button>
        </div>
    </div>
</div>

<script>
// Estado global para manejar destinatarios seleccionados
let selectedContacts = new Set();
let contactsNames = {};
let searchTimeout = null;

// Inicializar búsqueda de contactos
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchContactInput');
    if (searchInput) {
        searchInput.addEventListener('input', handleContactSearch);
    }
});

// Buscar contactos mientras el usuario escribe
function handleContactSearch(e) {
    const query = e.target.value.trim();
    const resultsDiv = document.getElementById('searchResults');
    
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch('api/broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'search_contacts', query: query })
            });
            
            const data = await response.json();
            
            if (data.success && data.contacts.length > 0) {
                displaySearchResults(data.contacts);
            } else {
                resultsDiv.innerHTML = '<div class="search-result-item" style="color: var(--gray); cursor: default;">No se encontraron contactos</div>';
                resultsDiv.style.display = 'block';
            }
        } catch (error) {
            console.error('Error buscando contactos:', error);
        }
    }, 300);
}

// Mostrar resultados de búsqueda
function displaySearchResults(contacts) {
    const resultsDiv = document.getElementById('searchResults');
    
    resultsDiv.innerHTML = '';
    
    contacts.forEach(contact => {
        const isSelected = selectedContacts.has(contact.numero);
        const displayName = contact.nombre || contact.numero;
        const tags = contact.etiquetas ? `<small style="color: var(--gray);">${contact.etiquetas}</small>` : '';
        
        contactsNames[contact.numero] = displayName;
        
        const itemDiv = document.createElement('div');
        itemDiv.className = 'search-result-item' + (isSelected ? ' selected' : '');
        itemDiv.innerHTML = `
            <div>
                <strong>${displayName}</strong>
                <small style="color: var(--gray); display: block;">${contact.numero}</small>
                ${tags}
            </div>
            <div>
                ${isSelected ? '<i class="fas fa-check-circle" style="color: var(--success);"></i>' : '<i class="fas fa-plus-circle" style="color: var(--primary);"></i>'}
            </div>
        `;
        
        // Agregar evento click con closure para mantener los valores
        itemDiv.addEventListener('click', function() {
            toggleContact(contact.numero, displayName, itemDiv);
        });
        
        resultsDiv.appendChild(itemDiv);
    });
    
    resultsDiv.style.display = 'block';
}

// Agregar o quitar contacto
function toggleContact(numero, nombre, element) {
    console.log('Toggle contact:', numero, nombre); // Debug
    
    if (selectedContacts.has(numero)) {
        selectedContacts.delete(numero);
        console.log('Contacto eliminado:', numero);
    } else {
        selectedContacts.add(numero);
        contactsNames[numero] = nombre;
        console.log('Contacto agregado:', numero);
    }
    
    updateSelectedContactsList();
    
    // Actualizar visual del item si existe
    if (element) {
        const icon = element.querySelector('.fas');
        if (selectedContacts.has(numero)) {
            element.classList.add('selected');
            if (icon) {
                icon.className = 'fas fa-check-circle';
                icon.style.color = 'var(--success)';
            }
        } else {
            element.classList.remove('selected');
            if (icon) {
                icon.className = 'fas fa-plus-circle';
                icon.style.color = 'var(--primary)';
            }
        }
    }
    
    console.log('Total seleccionados:', selectedContacts.size); // Debug
}

// Actualizar lista visual de contactos seleccionados
function updateSelectedContactsList() {
    const listDiv = document.getElementById('selectedContactsList');
    const hiddenInput = document.getElementById('destinatariosHidden');
    const counter = document.getElementById('destinatariosCount');
    
    console.log('Actualizando lista, contactos:', Array.from(selectedContacts)); // Debug
    
    if (!listDiv || !hiddenInput || !counter) {
        console.error('Elementos no encontrados');
        return;
    }
    
    if (selectedContacts.size === 0) {
        listDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--gray); border: 2px dashed var(--border); border-radius: 8px; width: 100%;">No hay contactos seleccionados</div>';
        hiddenInput.value = '';
    } else {
        const contactsArray = Array.from(selectedContacts);
        listDiv.innerHTML = contactsArray.map(numero => {
            const nombre = contactsNames[numero] || numero;
            return `
                <div class="selected-contact-chip">
                    <span>${nombre}</span>
                    <button type="button" onclick="event.preventDefault(); removeContact('${numero}')" title="Eliminar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }).join('');
        
        hiddenInput.value = JSON.stringify(contactsArray);
        console.log('Hidden input value:', hiddenInput.value); // Debug
    }
    
    const count = selectedContacts.size;
    counter.textContent = `${count} destinatario${count !== 1 ? 's' : ''}`;
    counter.style.color = count > 0 ? 'var(--success)' : 'var(--gray)';
}

// Eliminar contacto individual
function removeContact(numero) {
    event.preventDefault();
    event.stopPropagation();
    console.log('Removiendo contacto:', numero); // Debug
    selectedContacts.delete(numero);
    delete contactsNames[numero];
    updateSelectedContactsList();
}

// Seleccionar todos los contactos
async function selectAllContacts() {
    try {
        showNotification('Cargando contactos...', 'info');
        
        const response = await fetch('api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_all_numbers' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            selectedContacts = new Set(data.numbers);
            updateSelectedContactsList();
            showNotification(`${data.total} contactos cargados`, 'success');
        } else {
            showNotification('Error: ' + data.error, 'error');
        }
    } catch (error) {
        console.error(error);
        showNotification('Error al cargar contactos', 'error');
    }
}

// Limpiar destinatarios
function clearDestinations() {
    selectedContacts.clear();
    contactsNames = {};
    updateSelectedContactsList();
    document.getElementById('searchContactInput').value = '';
    document.getElementById('searchResults').style.display = 'none';
}

// Seleccionar por etiqueta
async function selectByTag() {
    const tag = prompt('Ingrese la etiqueta a buscar:');
    if (!tag) return;
    
    try {
        const response = await fetch(`api/contacts.php?search=${encodeURIComponent(tag)}`);
        const data = await response.json();
        
        if (data.success && data.contacts) {
            data.contacts.forEach(c => {
                selectedContacts.add(c.numero);
                contactsNames[c.numero] = c.nombre || c.numero;
            });
            updateSelectedContactsList();
            showNotification(`${data.contacts.length} contactos agregados`, 'success');
        } else {
            showNotification('No se encontraron contactos con esa etiqueta', 'warning');
        }
    } catch (error) {
        showNotification('Error en la búsqueda', 'error');
    }
}

// Guardar difusión
async function saveBroadcast(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    const action = event.submitter.name === 'send_now' ? 'send' : 'draft';
    
    const destinatariosJson = document.getElementById('destinatariosHidden').value;
    let destinatarios = [];
    
    try {
        destinatarios = destinatariosJson ? JSON.parse(destinatariosJson) : [];
    } catch (e) {
        showNotification('Error al procesar destinatarios', 'error');
        return false;
    }
    
    if (destinatarios.length === 0) {
        showNotification('Debe agregar al menos un destinatario válido', 'error');
        return false;
    }
    
    if (action === 'send') {
        if (!confirm(`¿Confirma el envío inmediato a ${destinatarios.length} destinatarios?`)) {
            return false;
        }
    }
    
    const buttons = form.querySelectorAll('button[type="submit"]');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    });
    
    try {
        const response = await fetch('api/broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: action,
                nombre: data.nombre,
                mensaje: data.mensaje,
                destinatarios: destinatarios,
                programada_para: data.programada_para || null,
                delay: parseInt(data.delay) || 3
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                action === 'send' ? 'Difusión iniciada correctamente' : 'Borrador guardado',
                'success'
            );
            closeModalBroadcast();
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('Error: ' + result.error, 'error');
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = btn.name === 'send_now' ? 
                    '<i class="fas fa-paper-plane"></i> Enviar Ahora' : 
                    '<i class="fas fa-save"></i> Guardar Borrador';
            });
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.name === 'send_now' ? 
                '<i class="fas fa-paper-plane"></i> Enviar Ahora' : 
                '<i class="fas fa-save"></i> Guardar Borrador';
        });
    }
    
    return false;
}

// Cerrar modal y limpiar
function closeModalBroadcast() {
    closeModal('modalNewBroadcast');
    document.getElementById('formNewBroadcast').reset();
    document.getElementById('modalTitle').textContent = 'Nueva Difusión';
    document.getElementById('difusionBaseId').value = '';
    selectedContacts.clear();
    contactsNames = {};
    updateSelectedContactsList();
    document.getElementById('searchResults').style.display = 'none';
}

// Reenviar difusión
async function resendBroadcast(id) {
    try {
        const response = await fetch(`api/broadcast.php?id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            showNotification('Error al cargar difusión', 'error');
            return;
        }
        
        const difusion = data.difusion;
        const destinatarios = data.destinatarios || [];
        
        selectedContacts = new Set(destinatarios.map(d => d.numero.replace('@c.us', '')));
        contactsNames = {};
        
        document.getElementById('modalTitle').textContent = 'Reenviar Difusión';
        document.getElementById('broadcastNombre').value = difusion.nombre + ' (Reenvío)';
        document.getElementById('broadcastMensaje').value = difusion.mensaje;
        document.getElementById('difusionBaseId').value = id;
        
        updateSelectedContactsList();
        openModal('modalNewBroadcast');
        
    } catch (error) {
        console.error(error);
        showNotification('Error al cargar datos', 'error');
    }
}

// Enviar borrador
async function sendBroadcast(id) {
    if (!confirm('¿Iniciar envío de esta difusión?')) return;
    
    try {
        const response = await fetch('api/broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send', id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Difusión iniciada', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error(error);
        showNotification('Error de conexión', 'error');
    }
}

// Eliminar difusión
async function deleteBroadcast(id) {
    if (!confirm('¿Eliminar esta difusión? Esta acción no se puede deshacer.')) return;
    
    try {
        const response = await fetch('api/broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Difusión eliminada', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error(error);
        showNotification('Error de conexión', 'error');
    }
}

// Ver detalles de difusión (historial tipo conversación)
async function viewBroadcast(id) {
    try {
        const response = await fetch(`api/broadcast.php?id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            showNotification('Error al cargar difusión', 'error');
            return;
        }
        
        const difusion = data.difusion;
        const destinatarios = data.destinatarios || [];
        
        const enviados = destinatarios.filter(d => d.estado === 'enviado').length;
        const fallidos = destinatarios.filter(d => d.estado === 'fallido').length;
        const pendientes = destinatarios.filter(d => d.estado === 'pendiente').length;
        
        const content = document.getElementById('broadcastDetailsContent');
        content.innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="background: var(--light); padding: 15px; border-radius: 8px;">
                    <div style="color: var(--gray); font-size: 0.9em; margin-bottom: 5px;">Estado</div>
                    <div style="font-size: 1.2em; font-weight: 600;">${escapeHtml(difusion.estado)}</div>
                </div>
                <div style="background: var(--light); padding: 15px; border-radius: 8px;">
                    <div style="color: var(--gray); font-size: 0.9em; margin-bottom: 5px;">Total</div>
                    <div style="font-size: 1.2em; font-weight: 600;">${difusion.total_destinatarios}</div>
                </div>
                <div style="background: #e8f5e9; padding: 15px; border-radius: 8px;">
                    <div style="color: var(--gray); font-size: 0.9em; margin-bottom: 5px;">Enviados</div>
                    <div style="font-size: 1.2em; font-weight: 600; color: var(--success);">${enviados}</div>
                </div>
                <div style="background: #ffebee; padding: 15px; border-radius: 8px;">
                    <div style="color: var(--gray); font-size: 0.9em; margin-bottom: 5px;">Fallidos</div>
                    <div style="font-size: 1.2em; font-weight: 600; color: var(--danger);">${fallidos}</div>
                </div>
            </div>
            
            <div style="background: var(--light); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0;"><i class="fas fa-comment-dots"></i> Mensaje Enviado</h4>
                <div class="message-bubble">
                    ${escapeHtml(difusion.mensaje).replace(/\n/g, '<br>')}
                    <div class="message-time">${new Date(difusion.fecha_creacion).toLocaleString('es-AR')}</div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <h4 style="margin: 0 0 15px 0;"><i class="fas fa-history"></i> Historial de Envíos</h4>
                <div class="broadcast-history">
                    ${destinatarios.map(d => {
                        const statusIcon = d.estado === 'enviado' ? '✓✓' : 
                                          d.estado === 'fallido' ? '✗' : '⏱';
                        const statusColor = d.estado === 'enviado' ? 'var(--success)' : 
                                           d.estado === 'fallido' ? 'var(--danger)' : 'var(--gray)';
                        
                        return `
                            <div class="history-item ${d.estado}">
                                <div class="history-contact">
                                    <i class="fas fa-user-circle"></i>
                                    <strong>${escapeHtml(d.numero)}</strong>
                                </div>
                                <div class="history-status" style="color: ${statusColor};">
                                    <span class="status-icon">${statusIcon}</span>
                                    <span>${d.estado}</span>
                                </div>
                                <div class="history-time">
                                    ${d.fecha_envio ? new Date(d.fecha_envio).toLocaleString('es-AR', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        day: '2-digit',
                                        month: '2-digit'
                                    }) : '-'}
                                </div>
                                ${d.error ? `<div class="history-error"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(d.error)}</div>` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9em; color: var(--gray); padding-top: 15px; border-top: 1px solid var(--border);">
                <div>
                    <strong>Creada:</strong> ${new Date(difusion.fecha_creacion).toLocaleString('es-AR')}
                </div>
                <div>
                    <strong>Por:</strong> ${escapeHtml(difusion.creado_por || 'Sistema')}
                </div>
                ${difusion.fecha_inicio ? `
                    <div>
                        <strong>Iniciada:</strong> ${new Date(difusion.fecha_inicio).toLocaleString('es-AR')}
                    </div>
                ` : ''}
                ${difusion.fecha_fin ? `
                    <div>
                        <strong>Finalizada:</strong> ${new Date(difusion.fecha_fin).toLocaleString('es-AR')}
                    </div>
                ` : ''}
            </div>
        `;
        
        openModal('modalViewBroadcast');
        
    } catch (error) {
        console.error(error);
        showNotification('Error al cargar detalles', 'error');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Cerrar resultados de búsqueda al hacer clic fuera
document.addEventListener('click', function(e) {
    const searchInput = document.getElementById('searchContactInput');
    const searchResults = document.getElementById('searchResults');
    
    if (searchInput && searchResults && 
        !searchInput.contains(e.target) && 
        !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
    }
});
</script>

<style>
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 100;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-top: 5px;
}

.search-result-item {
    padding: 12px 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.search-result-item:hover {
    background: var(--light);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item.selected {
    background: #e8f5e9;
}

.selected-contacts-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 15px;
    background: var(--light);
    border-radius: 8px;
    min-height: 100px;
    max-height: 300px;
    overflow-y: auto;
}

.selected-contact-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: white;
    border: 1px solid var(--primary);
    border-radius: 20px;
    font-size: 0.9em;
    color: var(--primary);
    white-space: nowrap;
}

.selected-contact-chip button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--danger);
    padding: 0;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.selected-contact-chip button:hover {
    background: var(--danger);
    color: white;
}

.message-bubble {
    background: white;
    padding: 15px;
    border-radius: 12px;
    border-left: 4px solid var(--primary);
    white-space: pre-wrap;
    word-wrap: break-word;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.message-time {
    font-size: 0.8em;
    color: var(--gray);
    margin-top: 8px;
    text-align: right;
}

.broadcast-history {
    background: var(--light);
    border-radius: 8px;
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.history-item {
    background: white;
    padding: 12px;
    margin-bottom: 8px;
    border-radius: 8px;
    border-left: 3px solid var(--gray);
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 10px;
    align-items: center;
    transition: all 0.2s;
}

.history-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.history-item:last-child {
    margin-bottom: 0;
}

.history-item.enviado {
    border-left-color: var(--success);
}

.history-item.fallido {
    border-left-color: var(--danger);
}

.history-item.pendiente {
    border-left-color: var(--gray);
}

.history-contact {
    display: flex;
    align-items: center;
    gap: 8px;
}

.history-contact i {
    color: var(--primary);
    font-size: 1.2em;
}

.history-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 0.9em;
}

.status-icon {
    font-size: 1.1em;
}

.history-time {
    font-size: 0.85em;
    color: var(--gray);
    text-align: right;
}

.history-error {
    grid-column: 1 / -1;
    padding: 8px;
    background: #ffebee;
    border-radius: 6px;
    font-size: 0.85em;
    color: var(--danger);
    margin-top: 5px;
}

.history-error i {
    margin-right: 5px;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
    color: white;
}

.badge-success { background: var(--success); }
.badge-warning { background: var(--warning); color: var(--dark); }
.badge-danger { background: var(--danger); }
.badge-primary { background: var(--primary); }
.badge-info { background: #17a2b8; }
.badge-secondary { background: #6c757d; }

/* Responsive */
@media (max-width: 768px) {
    .history-item {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .history-status,
    .history-time {
        text-align: left;
    }
    
    .selected-contacts-list {
        max-height: 200px;
    }
}
</style>