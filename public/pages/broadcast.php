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
    "SELECT * FROM difusiones ORDER BY fecha_creacion DESC LIMIT 20"
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
                <button class="btn btn-primary" onclick="openModal('modalNewBroadcast')">
                    Crear Primera Difusión
                </button>
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
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-bullhorn"></i> Nueva Difusión</h3>
            <button onclick="closeModal('modalNewBroadcast')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <form id="formNewBroadcast" onsubmit="return saveBroadcast(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre de la Campaña *</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Promoción Navidad 2025">
                </div>
                
                <div class="form-group">
                    <label>Mensaje *</label>
                    <textarea name="mensaje" class="form-control" rows="6" required 
                              placeholder="Escribe tu mensaje aquí..."></textarea>
                    <small style="color: var(--gray);">
                        <i class="fas fa-info-circle"></i> Variables disponibles: <code>{nombre}</code>, <code>{numero}</code>, <code>{empresa}</code>
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Destinatarios *</label>
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
                    <textarea id="destinatarios" name="destinatarios" class="form-control" rows="6" 
                              placeholder="Un número por línea (sin espacios ni guiones)&#10;549348230949&#10;549112345678&#10;549351234567"></textarea>
                    <div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <small id="destinatariosCount" style="font-weight: 600; color: var(--primary);">0 destinatarios</small>
                        <small style="color: var(--gray);">Sin @c.us al final</small>
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
                <button type="button" class="btn" onclick="closeModal('modalNewBroadcast')">
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
// Contar destinatarios en tiempo real
document.getElementById('destinatarios')?.addEventListener('input', function() {
    const lines = this.value.split('\n').filter(l => l.trim());
    const count = lines.length;
    const counter = document.getElementById('destinatariosCount');
    counter.textContent = `${count} destinatario${count !== 1 ? 's' : ''}`;
    counter.style.color = count > 0 ? 'var(--success)' : 'var(--gray)';
});

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
            document.getElementById('destinatarios').value = data.numbers.join('\n');
            document.getElementById('destinatarios').dispatchEvent(new Event('input'));
            showNotification(`${data.total} contactos cargados`, 'success');
        } else {
            showNotification('Error: ' + data.error, 'error');
        }
    } catch (error) {
        console.error(error);
        showNotification('Error al cargar contactos', 'error');
    }
}

function clearDestinations() {
    document.getElementById('destinatarios').value = '';
    document.getElementById('destinatarios').dispatchEvent(new Event('input'));
}

async function selectByTag() {
    const tag = prompt('Ingrese la etiqueta a buscar:');
    if (!tag) return;
    
    try {
        const response = await fetch(`api/contacts.php?search=${encodeURIComponent(tag)}`);
        const data = await response.json();
        
        if (data.success && data.contacts) {
            const numbers = data.contacts.map(c => c.numero);
            document.getElementById('destinatarios').value = numbers.join('\n');
            document.getElementById('destinatarios').dispatchEvent(new Event('input'));
            showNotification(`${numbers.length} contactos encontrados`, 'success');
        } else {
            showNotification('No se encontraron contactos con esa etiqueta', 'warning');
        }
    } catch (error) {
        showNotification('Error en la búsqueda', 'error');
    }
}

async function saveBroadcast(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Determinar acción según botón presionado
    const action = event.submitter.name === 'send_now' ? 'send' : 'draft';
    
    // Procesar destinatarios
    const destinatarios = data.destinatarios.split('\n')
        .map(n => n.trim().replace(/[^0-9]/g, ''))
        .filter(n => n.length >= 10);
    
    if (destinatarios.length === 0) {
        showNotification('Debe agregar al menos un destinatario válido', 'error');
        return false;
    }
    
    // Confirmar envío
    if (action === 'send') {
        if (!confirm(`¿Confirma el envío inmediato a ${destinatarios.length} destinatarios?`)) {
            return false;
        }
    }
    
    // Deshabilitar botones
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
            closeModal('modalNewBroadcast');
            form.reset();
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
        showNotification('Error de conexión. Verifique que el servidor esté activo.', 'error');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.name === 'send_now' ? 
                '<i class="fas fa-paper-plane"></i> Enviar Ahora' : 
                '<i class="fas fa-save"></i> Guardar Borrador';
        });
    }
    
    return false;
}

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
        
        // Calcular estadísticas
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
                <h4 style="margin: 0 0 10px 0;">Mensaje</h4>
                <div style="white-space: pre-wrap; background: white; padding: 12px; border-radius: 6px;">
${escapeHtml(difusion.mensaje)}</div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px 0;">Destinatarios (${destinatarios.length})</h4>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Estado</th>
                                <th>Fecha Envío</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${destinatarios.map(d => `
                                <tr>
                                    <td>${escapeHtml(d.numero)}</td>
                                    <td>
                                        <span class="badge badge-${
                                            d.estado === 'enviado' ? 'success' : 
                                            d.estado === 'fallido' ? 'danger' : 'secondary'
                                        }">
                                            ${escapeHtml(d.estado)}
                                        </span>
                                    </td>
                                    <td>${d.fecha_envio ? new Date(d.fecha_envio).toLocaleString('es-AR') : '-'}</td>
                                    <td>${d.error ? `<small style="color: var(--danger);">${escapeHtml(d.error)}</small>` : '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9em; color: var(--gray);">
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
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
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
</style>