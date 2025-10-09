<?php
// Verificar permiso de visualización
Auth::requirePermission('view_templates');

// Obtener instancia de Auth
$auth = Auth::getInstance();

// Verificar permiso de gestión
$canManage = $auth->hasPermission('manage_templates');

$templates = $db->fetchAll("SELECT * FROM plantillas ORDER BY nombre ASC");
?>

<div class="page-header">
    <div>
        <h2>Plantillas de Mensajes</h2>
        <p>Mensajes predefinidos para envío rápido</p>
    </div>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openModal('modalNewTemplate')">
        <i class="fas fa-plus"></i> Nueva Plantilla
    </button>
<?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <div class="empty-state">
                <p>No hay plantillas creadas</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Contenido</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $tpl): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($tpl['nombre'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($tpl['categoria'] ?? 'general') ?></td>
                            <td><?= htmlspecialchars(substr($tpl['contenido'] ?? '', 0, 50)) ?>...</td>
                            <td>
                                <span class="badge <?= ($tpl['activa'] ?? 0) ? 'badge-success' : '' ?>">
                                    <?= ($tpl['activa'] ?? 0) ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td>
    <button class="btn btn-sm btn-icon" onclick="copyTemplate(<?= $tpl['id'] ?>, '<?= htmlspecialchars(addslashes($tpl['contenido'] ?? '')) ?>')">
        <i class="fas fa-copy"></i>
    </button>
    
    <?php if ($canManage): ?>
        <button class="btn btn-sm btn-icon" onclick="editTemplate(<?= $tpl['id'] ?>)">
            <i class="fas fa-edit"></i>
        </button>
        <button class="btn btn-sm btn-icon" onclick="deleteTemplate(<?= $tpl['id'] ?>)">
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

<!-- Modal Nueva Plantilla -->
<div id="modalNewTemplate" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nueva Plantilla</h3>
            <button onclick="closeModal('modalNewTemplate')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <form id="formNewTemplate" onsubmit="return saveTemplate(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria" class="form-control">
                        <option value="general">General</option>
                        <option value="ventas">Ventas</option>
                        <option value="soporte">Soporte</option>
                        <option value="cobranzas">Cobranzas</option>
                        <option value="marketing">Marketing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contenido *</label>
                    <textarea name="contenido" class="form-control" rows="5" required></textarea>
                    <small>Variables disponibles: {nombre}, {numero}, {empresa}</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalNewTemplate')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyTemplate(id, content) {
    copyToClipboard(content);
    showNotification('Plantilla copiada', 'success');
}

async function saveTemplate(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/templates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', ...data })
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Plantilla guardada', 'success');
            closeModal('modalNewTemplate');
            location.reload();
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
    return false;
}

async function deleteTemplate(id) {
    if (!confirm('¿Eliminar esta plantilla?')) return;
    
    try {
        const response = await fetch('api/templates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Plantilla eliminada', 'success');
            location.reload();
        }
    } catch (error) {
        showNotification('Error', 'error');
    }
}
</script>

<style>
.badge-success { background: var(--success); color: white; }
</style>