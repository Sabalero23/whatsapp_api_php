<?php
// Verificar permisos
Auth::requirePermission('view_users');

$auth = Auth::getInstance();
$canCreate = $auth->hasPermission('create_users');
$canEdit = $auth->hasPermission('edit_users');
$canDelete = $auth->hasPermission('delete_users');
$canManageRoles = $auth->hasPermission('manage_roles');

// Obtener todos los usuarios
$usuarios = $db->fetchAll("
    SELECT 
        u.*,
        r.nombre as rol_nombre,
        r.id as rol_id,
        (SELECT COUNT(*) FROM logs WHERE usuario_id = u.id) as total_acciones
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
    ORDER BY u.activo DESC, r.nombre, u.nombre ASC
");

// Obtener roles disponibles
$roles = Auth::getAllRoles();

// Obtener todos los permisos agrupados por módulo
$permissions = Auth::getAllPermissions();
$permissionsByModule = [];
foreach ($permissions as $perm) {
    $permissionsByModule[$perm['modulo']][] = $perm;
}

// Estadísticas
$stats = [
    'total' => count($usuarios),
    'activos' => count(array_filter($usuarios, fn($u) => $u['activo'] == 1)),
    'roles' => count($roles)
];
?>

<style>
.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.users-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-box h3 {
    margin: 0;
    font-size: 2em;
    color: var(--primary);
    font-weight: 700;
}

.stat-box p {
    margin: 5px 0 0 0;
    color: var(--gray);
    font-size: 0.9em;
}

.tabs-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.tabs-header {
    display: flex;
    border-bottom: 2px solid var(--border);
    background: var(--light);
    overflow-x: auto;
}

.tab-btn {
    padding: 15px 25px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 0.95em;
    font-weight: 500;
    color: var(--gray);
    transition: all 0.3s;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
}

.tab-btn:hover {
    background: rgba(37, 211, 102, 0.1);
    color: var(--primary);
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: white;
}

.tab-btn i {
    margin-right: 8px;
}

.tabs-content {
    padding: 25px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.user-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s;
    display: grid;
    grid-template-columns: auto 1fr auto auto;
    gap: 20px;
    align-items: center;
}

.user-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateX(5px);
}

.user-card.inactive {
    opacity: 0.6;
    background: #f9fafb;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    font-weight: 700;
    text-transform: uppercase;
}

.user-info h4 {
    margin: 0 0 5px 0;
    font-size: 1.1em;
    color: var(--dark);
}

.user-info .user-meta {
    display: flex;
    gap: 15px;
    font-size: 0.85em;
    color: var(--gray);
    flex-wrap: wrap;
}

.user-info .user-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.role-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.role-Administrador { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.role-Operador { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.role-Visor { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

.user-actions {
    display: flex;
    gap: 8px;
}

.filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-bar input {
    flex: 1;
    min-width: 250px;
}

.filter-bar select {
    min-width: 150px;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: var(--light);
    border-radius: 8px;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.permission-item:has(input:checked) {
    background: rgba(37, 211, 102, 0.1);
    border-color: var(--primary);
}

.permission-item label {
    flex: 1;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9em;
}

.module-group {
    margin-bottom: 25px;
    padding: 20px;
    background: var(--light);
    border-radius: 10px;
}

.module-group h5 {
    margin: 0 0 15px 0;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.role-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.role-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.role-card h4 {
    margin: 0 0 10px 0;
    color: var(--dark);
}

.role-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.permissions-count {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(37, 211, 102, 0.1);
    color: var(--primary);
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    margin-left: 10px;
}

@media (max-width: 768px) {
    .user-card {
        grid-template-columns: auto 1fr;
        gap: 15px;
    }
    
    .user-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
    
    .role-badge {
        grid-column: 1 / -1;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="users-header">
    <div>
        <h2><i class="fas fa-users-cog"></i> Gestión de Usuarios y Roles</h2>
        <p style="color: var(--gray); margin: 5px 0 0 0;">
            Administra usuarios, roles y permisos de acceso al sistema
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <?php if ($canManageRoles): ?>
            <button class="btn btn-secondary" onclick="openRoleModal()">
                <i class="fas fa-user-tag"></i> Gestionar Roles
            </button>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <button class="btn btn-primary" onclick="openUserModal()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Estadísticas -->
<div class="users-stats">
    <div class="stat-box">
        <h3><?= $stats['total'] ?></h3>
        <p><i class="fas fa-users"></i> Total Usuarios</p>
    </div>
    <div class="stat-box">
        <h3><?= $stats['activos'] ?></h3>
        <p><i class="fas fa-check-circle"></i> Activos</p>
    </div>
    <div class="stat-box">
        <h3><?= $stats['roles'] ?></h3>
        <p><i class="fas fa-user-tag"></i> Roles Configurados</p>
    </div>
</div>

<!-- Pestañas -->
<div class="tabs-container">
    <div class="tabs-header">
        <button class="tab-btn active" data-tab="usuarios">
            <i class="fas fa-users"></i> Usuarios
        </button>
        <?php if ($canManageRoles): ?>
            <button class="tab-btn" data-tab="roles">
                <i class="fas fa-user-tag"></i> Roles y Permisos
            </button>
        <?php endif; ?>
    </div>
    
    <div class="tabs-content">
        
        <!-- Pestaña Usuarios -->
        <div class="tab-pane active" id="tab-usuarios">
            
            <!-- Filtros -->
            <div class="filter-bar">
                <input type="text" id="searchUser" class="form-control" placeholder="🔍 Buscar por nombre o usuario...">
                <select id="filterRole" class="form-control">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterStatus" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
            
            <!-- Lista de usuarios -->
            <div id="usersList">
                <?php if (empty($usuarios)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users" style="font-size: 64px; color: #d1d5db; margin-bottom: 15px;"></i>
                        <p>No hay usuarios registrados</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                        <div class="user-card <?= $u['activo'] ? '' : 'inactive' ?>" 
                             data-username="<?= strtolower($u['username']) ?>" 
                             data-nombre="<?= strtolower($u['nombre']) ?>"
                             data-role="<?= $u['rol_id'] ?>" 
                             data-status="<?= $u['activo'] ?>">
                            
                            <div class="user-avatar">
                                <?= strtoupper(substr($u['nombre'], 0, 2)) ?>
                            </div>
                            
                            <div class="user-info">
                                <h4>
                                    <?= htmlspecialchars($u['nombre']) ?>
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <span style="color: var(--primary); font-size: 0.8em;">(Tú)</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="user-meta">
                                    <span>
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($u['username']) ?>
                                    </span>
                                    <?php if ($u['email']): ?>
                                        <span>
                                            <i class="fas fa-envelope"></i>
                                            <?= htmlspecialchars($u['email']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= $u['ultimo_acceso'] ? 'Último acceso: ' . date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca ingresó' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($u['rol_nombre']): ?>
                                <span class="role-badge role-<?= htmlspecialchars($u['rol_nombre']) ?>">
                                    <?= htmlspecialchars($u['rol_nombre']) ?>
                                </span>
                            <?php else: ?>
                                <span class="role-badge" style="background: #ccc;">
                                    Sin Rol
                                </span>
                            <?php endif; ?>
                            
                            <div class="user-actions">
                                <?php if ($canEdit): ?>
                                    <label class="switch" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <input type="checkbox" 
                                               <?= $u['activo'] ? 'checked' : '' ?>
                                               <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>
                                               onchange="toggleUserStatus(<?= $u['id'] ?>, this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                    
                                    <button class="btn btn-sm btn-icon" onclick="editUser(<?= $u['id'] ?>)" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-icon" onclick="viewUserPermissions(<?= $u['id'] ?>)" title="Ver permisos">
                                    <i class="fas fa-key"></i>
                                </button>
                                
                                <?php if ($canDelete && $u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-icon btn-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nombre']) ?>')" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pestaña Roles y Permisos -->
        <?php if ($canManageRoles): ?>
        <div class="tab-pane" id="tab-roles">
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="openCreateRoleModal()">
                    <i class="fas fa-plus"></i> Crear Nuevo Rol
                </button>
            </div>
            
            <div id="rolesList">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $rolePermissions = Auth::getRolePermissions($role['id']);
                    $usersCount = $db->fetch("SELECT COUNT(*) as total FROM usuarios WHERE rol_id = ?", [$role['id']]);
                    ?>
                    <div class="role-card">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h4>
                                    <?= htmlspecialchars($role['nombre']) ?>
                                    <?php if ($role['es_sistema']): ?>
                                        <span class="badge badge-secondary">Sistema</span>
                                    <?php endif; ?>
                                </h4>
                                <p style="color: var(--gray); margin: 5px 0 10px 0;">
                                    <?= htmlspecialchars($role['descripcion']) ?>
                                </p>
                                <div style="font-size: 0.9em; color: var(--gray);">
                                    <i class="fas fa-users"></i>
                                    <?= $usersCount['total'] ?> usuario(s) con este rol
                                    <span class="permissions-count">
                                        <i class="fas fa-key"></i>
                                        <?= count($rolePermissions) ?> permisos
                                    </span>
                                </div>
                            </div>
                            
                            <div class="role-actions">
                                <button class="btn btn-sm btn-icon" onclick="viewRolePermissions(<?= $role['id'] ?>)" title="Ver permisos">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-icon" onclick="editRolePermissions(<?= $role['id'] ?>)" title="Editar permisos">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (!$role['es_sistema']): ?>
                                    <button class="btn btn-sm btn-icon btn-danger" onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['nombre']) ?>')" title="Eliminar rol">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Modal Crear/Editar Usuario -->
<div id="modalUser" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalUserTitle"><i class="fas fa-user-plus"></i> Nuevo Usuario</h3>
            <button onclick="closeModal('modalUser')" class="btn-close">&times;</button>
        </div>
        
        <form id="formUser" onsubmit="return saveUser(event)">
            <input type="hidden" name="id" id="userId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Usuario *</label>
                    <input type="text" name="username" id="userUsername" class="form-control" required>
                    <small>Solo letras, números y guiones bajos</small>
                </div>
                
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="nombre" id="userNombre" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="userEmail" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol_id" id="userRolId" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>">
                                <?= htmlspecialchars($role['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label>Contraseña *</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="userPassword" class="form-control">
                        <button type="button" class="btn btn-sm btn-icon" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"
                                onclick="togglePasswordVisibility('userPassword')" title="Mostrar/Ocultar">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small>Mínimo 6 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="activo" id="userActivo" checked>
                        Usuario activo
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalUser')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Permisos de Usuario -->
<div id="modalUserPermissions" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> Permisos de Usuario</h3>
            <button onclick="closeModal('modalUserPermissions')" class="btn-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="userPermissionsDetail"></div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('modalUserPermissions')">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Rol -->
<div id="modalRole" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 id="modalRoleTitle"><i class="fas fa-user-tag"></i> Crear Nuevo Rol</h3>
            <button onclick="closeModal('modalRole')" class="btn-close">&times;</button>
        </div>
        
        <form id="formRole" onsubmit="return saveRole(event)">
            <input type="hidden" name="id" id="roleId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre del Rol *</label>
                    <input type="text" name="nombre" id="rolNombre" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="rolDescripcion" class="form-control" rows="2"></textarea>
                </div>
                
                <h4 style="margin: 20px 0 15px 0;">
                    <i class="fas fa-key"></i> Permisos del Rol
                </h4>
                <p style="color: var(--gray); margin-bottom: 20px;">
                    Selecciona los permisos que tendrá este rol
                </p>
                
                <div id="permissionsContainer">
                    <?php foreach ($permissionsByModule as $module => $perms): ?>
                        <div class="module-group">
                            <h5>
                                <i class="fas fa-folder"></i>
                                <?= ucfirst($module) ?>
                                <span style="font-size: 0.8em; color: var(--gray);">(<?= count($perms) ?> permisos)</span>
                            </h5>
                            <div class="permissions-grid">
                                <?php foreach ($perms as $perm): ?>
                                    <div class="permission-item">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="<?= $perm['id'] ?>" 
                                               id="perm_<?= $perm['id'] ?>">
                                        <label for="perm_<?= $perm['id'] ?>">
                                            <?= htmlspecialchars($perm['descripcion'] ?: $perm['nombre']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalRole')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Rol
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Permisos de Rol -->
<div id="modalRolePermissions" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> Permisos del Rol</h3>
            <button onclick="closeModal('modalRolePermissions')" class="btn-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="rolePermissionsDetail"></div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('modalRolePermissions')">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
// Sistema de pestañas
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    });
});

// Filtros
document.getElementById('searchUser').addEventListener('input', filterUsers);
document.getElementById('filterRole').addEventListener('change', filterUsers);
document.getElementById('filterStatus').addEventListener('change', filterUsers);

function filterUsers() {
    const search = document.getElementById('searchUser').value.toLowerCase();
    const role = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;
    
    document.querySelectorAll('.user-card').forEach(card => {
        const username = card.dataset.username;
        const nombre = card.dataset.nombre;
        const cardRole = card.dataset.role;
        const cardStatus = card.dataset.status;
        
        const matchSearch = search === '' || username.includes(search) || nombre.includes(search);
        const matchRole = role === '' || cardRole === role;
        const matchStatus = status === '' || cardStatus === status;
        
        card.style.display = (matchSearch && matchRole && matchStatus) ? 'grid' : 'none';
    });
}

// Toggle visibilidad contraseña
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// GESTIÓN DE USUARIOS

function openUserModal(userId = null) {
    const modal = document.getElementById('modalUser');
    const form = document.getElementById('formUser');
    const title = document.getElementById('modalUserTitle');
    const passwordGroup = document.getElementById('passwordGroup');
    
    form.reset();
    
    if (userId) {
        title.innerHTML = '<i class="fas fa-user-edit"></i> Editar Usuario';
        passwordGroup.querySelector('label').textContent = 'Nueva Contraseña (dejar vacío para no cambiar)';
        passwordGroup.querySelector('input').required = false;
        loadUserData(userId);
    } else {
        title.innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Usuario';
        passwordGroup.querySelector('label').textContent = 'Contraseña *';
        passwordGroup.querySelector('input').required = true;
    }
    
    openModal('modalUser');
}

async function loadUserData(userId) {
    try {
        const response = await fetch(`api/users.php?id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            document.getElementById('userId').value = user.id;
            document.getElementById('userUsername').value = user.username;
            document.getElementById('userNombre').value = user.nombre;
            document.getElementById('userEmail').value = user.email || '';
            document.getElementById('userRolId').value = user.rol_id || '';
            document.getElementById('userActivo').checked = user.activo == 1;
        }
    } catch (error) {
        showNotification('Error al cargar usuario', 'error');
    }
}

async function saveUser(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    if (!/^[a-zA-Z0-9_]+$/.test(data.username)) {
        showNotification('El usuario solo puede contener letras, números y guiones bajos', 'error');
        return false;
    }
    
    if (!data.id && !data.password) {
        showNotification('La contraseña es obligatoria', 'error');
        return false;
    }
    
    if (data.password && data.password.length < 6) {
        showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
        return false;
    }
    
    data.activo = event.target.elements.activo.checked ? 1 : 0;
    data.action = data.id ? 'update' : 'create';
    
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(data.id ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente', 'success');
            closeModal('modalUser');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
    
    return false;
}

function editUser(userId) {
    openUserModal(userId);
}

async function toggleUserStatus(userId, status) {
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'toggle_status', 
                id: userId, 
                activo: status ? 1 : 0 
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`Usuario ${status ? 'activado' : 'desactivado'} correctamente`, 'success');
        } else {
            showNotification('Error: ' + result.error, 'error');
            event.target.checked = !status;
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
        event.target.checked = !status;
    }
}

async function deleteUser(userId, nombre) {
    if (!confirm(`¿Eliminar al usuario "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
        return;
    }
    
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: userId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Usuario eliminado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

async function viewUserPermissions(userId) {
    try {
        const response = await fetch(`api/users.php?action=get_permissions&id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            const permissions = data.permissions;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 5px 0;">${user.nombre}</h4>
                    <span class="role-badge role-${user.rol_nombre}">${user.rol_nombre}</span>
                </div>
            `;
            
            if (permissions.length === 0) {
                html += '<p style="color: var(--gray);">Este usuario no tiene permisos asignados.</p>';
            } else {
                const permsByModule = {};
                permissions.forEach(p => {
                    if (!permsByModule[p.modulo]) {
                        permsByModule[p.modulo] = [];
                    }
                    permsByModule[p.modulo].push(p);
                });
                
                Object.keys(permsByModule).forEach(module => {
                    html += `
                        <div class="module-group">
                            <h5><i class="fas fa-folder"></i> ${module.charAt(0).toUpperCase() + module.slice(1)}</h5>
                            <div class="permissions-grid">
                    `;
                    
                    permsByModule[module].forEach(perm => {
                        html += `
                            <div class="permission-item" style="background: rgba(37, 211, 102, 0.1); border-color: var(--primary);">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                <label style="color: var(--dark);">${perm.descripcion || perm.nombre}</label>
                            </div>
                        `;
                    });
                    
                    html += '</div></div>';
                });
            }
            
            document.getElementById('userPermissionsDetail').innerHTML = html;
            openModal('modalUserPermissions');
        }
    } catch (error) {
        showNotification('Error al cargar permisos', 'error');
    }
}

// GESTIÓN DE ROLES

function openRoleModal() {
    window.location.href = '?page=users#roles';
    document.querySelector('[data-tab="roles"]').click();
}

function openCreateRoleModal() {
    const modal = document.getElementById('modalRole');
    const form = document.getElementById('formRole');
    const title = document.getElementById('modalRoleTitle');
    
    form.reset();
    document.getElementById('roleId').value = '';
    title.innerHTML = '<i class="fas fa-user-tag"></i> Crear Nuevo Rol';
    
    openModal('modalRole');
}

async function editRolePermissions(roleId) {
    try {
        const response = await fetch(`api/roles.php?id=${roleId}`);
        const data = await response.json();
        
        if (data.success) {
            const role = data.role;
            const permissions = data.permissions;
            
            document.getElementById('roleId').value = role.id;
            document.getElementById('rolNombre').value = role.nombre;
            document.getElementById('rolDescripcion').value = role.descripcion || '';
            
            document.getElementById('modalRoleTitle').innerHTML = '<i class="fas fa-user-tag"></i> Editar Permisos del Rol';
            
            // Desmarcar todos
            document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Marcar permisos del rol
            permissions.forEach(perm => {
                const checkbox = document.getElementById('perm_' + perm.id);
                if (checkbox) checkbox.checked = true;
            });
            
            openModal('modalRole');
        }
    } catch (error) {
        showNotification('Error al cargar rol', 'error');
    }
}

async function saveRole(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {
        id: formData.get('id') || null,
        nombre: formData.get('nombre'),
        descripcion: formData.get('descripcion'),
        permissions: formData.getAll('permissions[]').map(id => parseInt(id))
    };
    
    if (!data.nombre) {
        showNotification('El nombre del rol es obligatorio', 'error');
        return false;
    }
    
    if (data.permissions.length === 0) {
        if (!confirm('No has seleccionado ningún permiso. ¿Deseas continuar?')) {
            return false;
        }
    }
    
    data.action = data.id ? 'update' : 'create';
    
    try {
        const response = await fetch('api/roles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(data.id ? 'Rol actualizado correctamente' : 'Rol creado correctamente', 'success');
            closeModal('modalRole');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
    
    return false;
}

async function viewRolePermissions(roleId) {
    try {
        const response = await fetch(`api/roles.php?id=${roleId}`);
        const data = await response.json();
        
        if (data.success) {
            const role = data.role;
            const permissions = data.permissions;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 5px 0;">${role.nombre}</h4>
                    <p style="color: var(--gray); margin: 5px 0;">${role.descripcion || ''}</p>
                </div>
            `;
            
            if (permissions.length === 0) {
                html += '<p style="color: var(--gray);">Este rol no tiene permisos asignados.</p>';
            } else {
                const permsByModule = {};
                permissions.forEach(p => {
                    if (!permsByModule[p.modulo]) {
                        permsByModule[p.modulo] = [];
                    }
                    permsByModule[p.modulo].push(p);
                });
                
                Object.keys(permsByModule).sort().forEach(module => {
                    html += `
                        <div class="module-group">
                            <h5><i class="fas fa-folder"></i> ${module.charAt(0).toUpperCase() + module.slice(1)}</h5>
                            <div class="permissions-grid">
                    `;
                    
                    permsByModule[module].forEach(perm => {
                        html += `
                            <div class="permission-item" style="background: rgba(37, 211, 102, 0.1); border-color: var(--primary);">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                <label style="color: var(--dark);">${perm.descripcion || perm.nombre}</label>
                            </div>
                        `;
                    });
                    
                    html += '</div></div>';
                });
            }
            
            document.getElementById('rolePermissionsDetail').innerHTML = html;
            openModal('modalRolePermissions');
        }
    } catch (error) {
        showNotification('Error al cargar permisos del rol', 'error');
    }
}

async function deleteRole(roleId, nombre) {
    if (!confirm(`¿Eliminar el rol "${nombre}"?\n\nEsta acción no se puede deshacer.\nLos usuarios con este rol quedarán sin rol asignado.`)) {
        return;
    }
    
    try {
        const response = await fetch('api/roles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: roleId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Rol eliminado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}
</script>