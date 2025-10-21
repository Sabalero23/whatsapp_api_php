<?php
// Verificar permiso de visualización
Auth::requirePermission('view_contacts');

// Obtener instancia de Auth
$auth = Auth::getInstance();

// Verificar permisos específicos
$canCreate = $auth->hasPermission('create_contacts');
$canEdit = $auth->hasPermission('edit_contacts');
$canDelete = $auth->hasPermission('delete_contacts');

// Obtener contactos de la base de datos
$page_num = $_GET['p'] ?? 1;
$limit = 50;
$offset = ($page_num - 1) * $limit;

$contacts = $db->fetchAll(
    "SELECT * FROM contactos 
    ORDER BY nombre ASC 
    LIMIT ? OFFSET ?",
    [$limit, $offset]
);

$total = $db->fetch("SELECT COUNT(*) as total FROM contactos")['total'];
$totalPages = ceil($total / $limit);
?>

<div class="page-header">
    <div>
        <h2>Contactos</h2>
        <p>Total: <?= number_format($total) ?> contactos</p>
    </div>
    <div style="display: flex; gap: 10px;">
    <?php if ($canCreate): ?>
        <button class="btn btn-primary" onclick="openModal('modalNewContact')">
            <i class="fas fa-plus"></i> Nuevo Contacto
        </button>
        <button class="btn btn-secondary" onclick="importContacts()">
            <i class="fas fa-file-import"></i> Importar
        </button>
    <?php endif; ?>
    
    <button class="btn btn-secondary" onclick="syncContacts()">
        <i class="fas fa-sync"></i> Sincronizar con WhatsApp
    </button>
</div>
</div>

<div class="card">
    <div class="card-body">
        <div style="margin-bottom: 20px; position: relative;">
            <input type="text" id="searchContacts" placeholder="Buscar contacto por nombre, número, empresa..." 
                   class="form-control" style="max-width: 400px;">
            <div id="searchLoader" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%);">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
        </div>
        
        <div id="contactsTableContainer">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Número</th>
                        <th>Empresa</th>
                        <th>Etiquetas</th>
                        <th>Último Contacto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="contactsTableBody">
                    <?php foreach ($contacts as $contact): ?>
                        <tr class="contact-item" data-nombre="<?= htmlspecialchars($contact['nombre'] ?? '') ?>" 
                            data-numero="<?= htmlspecialchars($contact['numero']) ?>"
                            data-empresa="<?= htmlspecialchars($contact['empresa'] ?? '') ?>">
                            <td>
                                <strong><?= htmlspecialchars($contact['nombre'] ?? $contact['numero']) ?></strong>
                                <?php if ($contact['favorito']): ?>
                                    <i class="fas fa-star" style="color: gold;"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($contact['numero']) ?></td>
                            <td><?= htmlspecialchars($contact['empresa'] ?? '-') ?></td>
                            <td>
                                <?php if ($contact['etiquetas']): ?>
                                    <?php foreach (explode(',', $contact['etiquetas']) as $tag): ?>
                                        <span class="badge"><?= htmlspecialchars(trim($tag)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $contact['ultimo_contacto'] ? 
                                    date('d/m/Y', strtotime($contact['ultimo_contacto'])) : '-' ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-icon" 
                                        onclick="sendMessageToContact('<?= htmlspecialchars($contact['numero']) ?>')">
                                    <i class="fas fa-comment"></i>
                                </button>
                                
                                <?php if ($canEdit): ?>
                                    <button class="btn btn-sm btn-icon" onclick="editContact(<?= $contact['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($canDelete): ?>
                                    <button class="btn btn-sm btn-icon" onclick="deleteContact(<?= $contact['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div id="paginationContainer" style="margin-top: 20px; text-align: center;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=contacts&p=<?= $i ?>" 
                       class="btn btn-sm <?= $i == $page_num ? 'btn-primary' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo Contacto -->
<div id="modalNewContact" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nuevo Contacto</h3>
            <button onclick="closeModal('modalNewContact')" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
        </div>
        <form id="formNewContact" onsubmit="return saveContact(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Número *</label>
                    <input type="text" name="numero" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" class="form-control">
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
                    <input type="text" name="etiquetas" class="form-control" 
                           placeholder="cliente, vip, proveedor">
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notas" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalNewContact')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
let searchTimeout = null;
let allContacts = [];

// Inicializar búsqueda
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchContacts');
    if (searchInput) {
        // Guardar todos los contactos para búsqueda rápida
        allContacts = Array.from(document.querySelectorAll('.contact-item')).map(row => ({
            element: row,
            nombre: row.dataset.nombre?.toLowerCase() || '',
            numero: row.dataset.numero?.toLowerCase() || '',
            empresa: row.dataset.empresa?.toLowerCase() || ''
        }));
        
        searchInput.addEventListener('input', handleSearch);
    }
});

// Manejar búsqueda
function handleSearch(e) {
    const query = e.target.value.trim().toLowerCase();
    const loader = document.getElementById('searchLoader');
    const pagination = document.getElementById('paginationContainer');
    
    clearTimeout(searchTimeout);
    
    // Mostrar loader
    if (query.length > 0) {
        loader.style.display = 'block';
    }
    
    searchTimeout = setTimeout(() => {
        if (query.length === 0) {
            // Mostrar todos los contactos
            allContacts.forEach(contact => {
                contact.element.style.display = '';
            });
            if (pagination) pagination.style.display = 'block';
            loader.style.display = 'none';
            return;
        }
        
        if (query.length < 2) {
            loader.style.display = 'none';
            return;
        }
        
        // Ocultar paginación durante búsqueda
        if (pagination) pagination.style.display = 'none';
        
        // Buscar en contactos cargados
        let foundCount = 0;
        allContacts.forEach(contact => {
            const matches = contact.nombre.includes(query) || 
                          contact.numero.includes(query) || 
                          contact.empresa.includes(query);
            
            if (matches) {
                contact.element.style.display = '';
                foundCount++;
            } else {
                contact.element.style.display = 'none';
            }
        });
        
        // Si no hay resultados en la página actual, buscar en servidor
        if (foundCount === 0 && query.length >= 2) {
            searchInServer(query);
        } else {
            loader.style.display = 'none';
        }
    }, 300);
}

// Buscar en servidor
async function searchInServer(query) {
    const loader = document.getElementById('searchLoader');
    const tbody = document.getElementById('contactsTableBody');
    
    try {
        loader.style.display = 'block';
        
        const response = await fetch(`api/contacts.php?search=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success && data.contacts) {
            if (data.contacts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--gray);">
                            <i class="fas fa-search" style="font-size: 2em; margin-bottom: 10px; display: block; opacity: 0.3;"></i>
                            No se encontraron contactos que coincidan con "${query}"
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = data.contacts.map(contact => `
                    <tr class="contact-item">
                        <td>
                            <strong>${escapeHtml(contact.nombre || contact.numero)}</strong>
                            ${contact.favorito ? '<i class="fas fa-star" style="color: gold;"></i>' : ''}
                        </td>
                        <td>${escapeHtml(contact.numero)}</td>
                        <td>${escapeHtml(contact.empresa || '-')}</td>
                        <td>
                            ${contact.etiquetas ? 
                                contact.etiquetas.split(',').map(tag => 
                                    `<span class="badge">${escapeHtml(tag.trim())}</span>`
                                ).join(' ') : '-'
                            }
                        </td>
                        <td>
                            ${contact.ultimo_contacto ? 
                                new Date(contact.ultimo_contacto).toLocaleDateString('es-AR') : '-'}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-icon" onclick="sendMessageToContact('${escapeHtml(contact.numero)}')">
                                <i class="fas fa-comment"></i>
                            </button>
                            <?php if ($canEdit): ?>
                            <button class="btn btn-sm btn-icon" onclick="editContact(${contact.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button class="btn btn-sm btn-icon" onclick="deleteContact(${contact.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                `).join('');
                
                showNotification(`${data.contacts.length} contacto(s) encontrado(s)`, 'success');
            }
        } else {
            showNotification('Error al buscar contactos', 'error');
        }
    } catch (error) {
        console.error('Error en búsqueda:', error);
        showNotification('Error de conexión', 'error');
    } finally {
        loader.style.display = 'none';
    }
}

async function saveContact(event) {
    event.preventDefault();
    const form = event.target;
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
            showNotification('Contacto guardado', 'success');
            closeModal('modalNewContact');
            location.reload();
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
    
    return false;
}

function sendMessageToContact(numero) {
    window.location.href = `?page=chats&chat=${numero}@c.us`;
}

async function deleteContact(id) {
    if (!confirm('¿Eliminar este contacto?')) return;
    
    try {
        const response = await fetch('api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Contacto eliminado', 'success');
            location.reload();
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

async function syncContacts() {
    showNotification('Sincronizando contactos...', 'info');
    
    try {
        const response = await fetch('api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sync' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`${result.total || result.count} contactos sincronizados`, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

async function editContact(id) {
    try {
        const response = await fetch(`api/contacts.php?id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            showNotification('Error al cargar contacto', 'error');
            return;
        }
        
        const contact = data.contact;
        
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'modalEditContact';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Editar Contacto</h3>
                    <button onclick="this.closest('.modal').remove()" style="border: none; background: none; font-size: 1.5em; cursor: pointer;">&times;</button>
                </div>
                <form id="formEditContact" onsubmit="return updateContact(event, ${id})">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Número *</label>
                            <input type="text" name="numero" class="form-control" value="${escapeHtml(contact.numero)}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="${escapeHtml(contact.nombre || '')}">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="${escapeHtml(contact.email || '')}">
                        </div>
                        <div class="form-group">
                            <label>Empresa</label>
                            <input type="text" name="empresa" class="form-control" value="${escapeHtml(contact.empresa || '')}">
                        </div>
                        <div class="form-group">
                            <label>Etiquetas (separadas por coma)</label>
                            <input type="text" name="etiquetas" class="form-control" 
                                   placeholder="cliente, vip, proveedor"
                                   value="${escapeHtml(contact.etiquetas || '')}">
                        </div>
                        <div class="form-group">
                            <label>Notas</label>
                            <textarea name="notas" class="form-control" rows="3">${escapeHtml(contact.notas || '')}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" onclick="this.closest('.modal').remove()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function updateContact(event, id) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', id: id, ...data })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Contacto actualizado', 'success');
            form.closest('.modal').remove();
            location.reload();
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
    
    return false;
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

function importContacts() {
    showNotification('Función de importación en desarrollo', 'info');
}
</script>



<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h2 {
    margin: 0 0 8px 0;
    font-size: 1.6em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header p {
    color: var(--gray);
    margin: 0;
    font-size: 0.95em;
}

.page-header > div:last-child {
    display: flex;
    gap: 10px;
}

.card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-body {
    padding: 20px;
}

#searchContacts {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.95em;
    margin-bottom: 20px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead th {
    background: var(--light);
    padding: 12px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9em;
    color: var(--gray);
    border-bottom: 2px solid var(--border);
}

.table tbody td {
    padding: 15px 12px;
    border-bottom: 1px solid var(--border);
}

.table tbody tr:hover {
    background: var(--light);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--primary);
    color: white;
    border-radius: 4px;
    font-size: 0.8em;
    margin-right: 6px;
    margin-bottom: 4px;
}

.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.95em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-secondary {
    background: var(--secondary);
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-sm {
    padding: 8px 12px;
    font-size: 0.85em;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: transparent;
    color: var(--gray);
    border: 1px solid var(--border);
    margin: 0 3px;
}

.btn-icon:hover {
    background: var(--light);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-icon i.fa-trash {
    color: var(--danger);
}

.btn-icon:hover i.fa-trash {
    color: white;
}

.btn-icon:has(i.fa-trash):hover {
    background: var(--danger);
    border-color: var(--danger);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.3em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header button {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.2s;
}

.modal-header button:hover {
    color: var(--text);
    transform: rotate(90deg);
}

.modal-body {
    padding: 20px;
    max-height: calc(90vh - 180px);
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text);
}

.form-group label small {
    font-weight: 400;
    color: var(--gray);
    font-size: 0.85em;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.95em;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4em;
    color: #ddd;
    margin-bottom: 20px;
    display: block;
}

.empty-state h3 {
    color: var(--gray);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 25px;
}

/* Pagination */
.pagination {
    margin-top: 20px;
    text-align: center;
    display: flex;
    justify-content: center;
    gap: 5px;
}

.pagination .btn-sm {
    min-width: 36px;
    padding: 8px 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .page-header > div:last-child {
        width: 100%;
        flex-direction: column;
    }
    
    .page-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    #searchContacts {
        max-width: 100%;
    }
    
    .table {
        display: block;
        overflow-x: auto;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody {
        display: block;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px;
    }
    
    .table tbody td {
        display: block;
        padding: 8px 0;
        border: none;
        text-align: right;
    }
    
    .table tbody td:before {
        content: attr(data-label);
        float: left;
        font-weight: 600;
        color: var(--gray);
    }
    
    .modal-content {
        margin: 0;
        max-height: 100vh;
        border-radius: 0;
    }
}
</style>