<?php
// Verificar permiso de visualización
Auth::requirePermission('view_settings');

// Obtener instancia de Auth
$auth = Auth::getInstance();

// Verificar permiso de gestión
$canManage = $auth->hasPermission('manage_settings');

$config = [];
$configRows = $db->fetchAll("SELECT * FROM configuracion ORDER BY clave ASC");
foreach ($configRows as $row) {
    $config[$row['clave']] = $row['valor'];
}

// Obtener usuarios (solo para admin)
$usuarios = [];
if ($user['rol'] === 'admin') {
    $usuarios = $db->fetchAll("SELECT * FROM usuarios ORDER BY fecha_creacion DESC");
}
?>

<div class="page-header">
    <h2>Configuración del Sistema</h2>
</div>

<!-- Pestañas -->
<div class="tabs-container">
    <div class="tabs-header">
        <button class="tab-btn active" data-tab="general">
            <i class="fas fa-cog"></i> General
        </button>
        <button class="tab-btn" data-tab="horarios">
            <i class="fas fa-clock"></i> Horarios
        </button>
        <button class="tab-btn" data-tab="mensajes">
            <i class="fas fa-comment"></i> Mensajes
        </button>
        <?php if ($user['rol'] === 'admin'): ?>
        <button class="tab-btn" data-tab="usuarios">
            <i class="fas fa-users"></i> Usuarios
        </button>
        <?php endif; ?>
        <button class="tab-btn" data-tab="avanzado">
            <i class="fas fa-sliders-h"></i> Avanzado
        </button>
    </div>
    
    <!-- Contenido de pestañas -->
    <div class="tabs-content">
        
        <!-- Pestaña General -->
        <div class="tab-pane active" id="tab-general">
            <div class="card">
                <div class="card-body">
                    <form id="formGeneral" onsubmit="return saveSettings(event, 'general')">
                        <h4><i class="fas fa-cog"></i> Configuración General</h4>
                        
                        <div class="form-group">
                            <label>Delay entre mensajes (segundos)</label>
                            <input type="number" name="delay_entre_mensajes" class="form-control" 
                                   value="<?= htmlspecialchars($config['delay_entre_mensajes'] ?? 2) ?>" 
                                   min="1" max="60">
                            <small>Tiempo de espera entre mensajes en difusiones masivas</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Máximo de mensajes por hora</label>
                            <input type="number" name="max_mensajes_por_hora" class="form-control" 
                                   value="<?= htmlspecialchars($config['max_mensajes_por_hora'] ?? 100) ?>" 
                                   min="10" max="1000">
                            <small>Límite de mensajes que se pueden enviar por hora</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="respuestas_automaticas_activas" 
                                       <?= ($config['respuestas_automaticas_activas'] ?? '0') == '1' ? 'checked' : '' ?>>
                                Activar respuestas automáticas
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="bot_activo" 
                                       <?= ($config['bot_activo'] ?? '0') == '1' ? 'checked' : '' ?>>
                                Bot de respuestas activo
                            </label>
                        </div>
                        
                        <?php if ($canManage): ?>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Guardar
    </button>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-lock"></i> No tienes permiso para modificar la configuración
    </div>
<?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Pestaña Horarios -->
        <div class="tab-pane" id="tab-horarios">
            <div class="card">
                <div class="card-body">
                    <form id="formHorarios" onsubmit="return saveHorarios(event)">
                        <h4><i class="fas fa-clock"></i> Horarios de Atención por Día</h4>
                        <p style="color: var(--gray); margin-bottom: 25px;">
                            Configura los horarios de atención para cada día de la semana. Puedes definir horario de mañana y tarde por separado.
                        </p>
                        
                        <div id="horariosContainer">
                            <!-- Se llena dinámicamente con JavaScript -->
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Horarios
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Pestaña Mensajes -->
        <div class="tab-pane" id="tab-mensajes">
            <div class="card">
                <div class="card-body">
                    <form id="formMensajes" onsubmit="return saveSettings(event, 'mensajes')">
                        <h4><i class="fas fa-comment"></i> Mensajes Automáticos</h4>
                        
                        <div class="form-group">
                            <label>Mensaje de bienvenida</label>
                            <textarea name="mensaje_bienvenida" class="form-control" rows="3"><?= htmlspecialchars($config['mensaje_bienvenida'] ?? '') ?></textarea>
                            <small>Mensaje que se envía automáticamente a nuevos contactos</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Mensaje fuera de horario</label>
                            <textarea name="mensaje_fuera_horario" class="form-control" rows="3"><?= htmlspecialchars($config['mensaje_fuera_horario'] ?? '') ?></textarea>
                            <small>Mensaje que se envía cuando escriben fuera del horario de atención</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Pestaña Usuarios -->
        <?php if ($user['rol'] === 'admin'): ?>
        <div class="tab-pane" id="tab-usuarios">
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h4><i class="fas fa-users"></i> Gestión de Usuarios</h4>
                    <button class="btn btn-primary" onclick="openUserModal()">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['nombre']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['rol'] === 'admin' ? 'danger' : ($u['rol'] === 'operador' ? 'primary' : 'secondary') ?>">
                                        <?= htmlspecialchars($u['rol']) ?>
                                    </span>
                                </td>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" 
                                               <?= $u['activo'] ? 'checked' : '' ?>
                                               <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>
                                               onchange="toggleUserStatus(<?= $u['id'] ?>, this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-icon" onclick="editUser(<?= $u['id'] ?>)" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-icon" onclick="deleteUser(<?= $u['id'] ?>)" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pestaña Avanzado -->
        <div class="tab-pane" id="tab-avanzado">
            <div class="card">
                <div class="card-body">
                    <h4><i class="fas fa-sliders-h"></i> Configuración Avanzada</h4>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Atención:</strong> Estas configuraciones son para usuarios avanzados. Modificarlas incorrectamente puede afectar el funcionamiento del sistema.
                    </div>
                    
                    <div style="display: grid; gap: 15px;">
                        <div class="info-box">
                            <h5><i class="fas fa-database"></i> Base de Datos</h5>
                            <button class="btn btn-secondary" onclick="optimizeDatabase()">
                                <i class="fas fa-sync"></i> Optimizar Base de Datos
                            </button>
                        </div>
                        
                        <div class="info-box">
                            <h5><i class="fas fa-trash"></i> Limpieza de Datos</h5>
                            <button class="btn btn-secondary" onclick="clearOldLogs()">
                                <i class="fas fa-broom"></i> Limpiar Logs Antiguos (+ de 30 días)
                            </button>
                        </div>
                        
                        <div class="info-box">
                            <h5><i class="fas fa-download"></i> Respaldo</h5>
                            <button class="btn btn-secondary" onclick="exportData()">
                                <i class="fas fa-file-export"></i> Exportar Datos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Modal Usuario -->
<div id="modalUser" class="modal">
    <div class="modal-content">
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
                    <select name="rol" id="userRol" class="form-control" required>
                        <option value="operador">Operador</option>
                        <option value="visor">Visor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label>Contraseña *</label>
                    <input type="password" name="password" id="userPassword" class="form-control">
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
                <button type="button" class="btn" onclick="closeModal('modalUser')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
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

.form-row {
    display: flex;
    gap: 20px;
}

.card h4 {
    margin: 0 0 20px 0;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
    color: white;
}

.badge-primary { background: var(--primary); }
.badge-danger { background: var(--danger); }
.badge-secondary { background: #6c757d; }

/* Switch toggle */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
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
    transition: 0.4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

input:disabled + .slider {
    opacity: 0.5;
    cursor: not-allowed;
}

.info-box {
    padding: 20px;
    background: var(--light);
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.info-box h5 {
    margin: 0 0 15px 0;
    color: var(--dark);
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
}

.horario-day-card {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s;
}

.horario-day-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.horario-day-card.inactive {
    opacity: 0.6;
    background: #f8f9fa;
}

.horario-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border);
}

.horario-header h5 {
    margin: 0;
    color: var(--dark);
    font-size: 1.1em;
}

.horario-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.horario-turno {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid var(--border);
}

.horario-turno h6 {
    margin: 0 0 10px 0;
    color: var(--primary);
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.horario-inputs {
    display: flex;
    gap: 10px;
    align-items: center;
}

.horario-inputs input {
    flex: 1;
}

.horario-inputs span {
    color: var(--gray);
    font-weight: 600;
}

@media (max-width: 768px) {
    .horario-body {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .tabs-header {
        flex-wrap: nowrap;
        overflow-x: auto;
    }
}
</style>

<script>
// Sistema de pestañas
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        // Desactivar todos
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        
        // Activar seleccionado
        this.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
        
        // Si es la pestaña de horarios, cargar datos
        if (tab === 'horarios') {
            loadHorarios();
        }
    });
});

// Cargar horarios
async function loadHorarios() {
    try {
        const response = await fetch('api/horarios.php');
        const data = await response.json();
        
        if (data.success) {
            renderHorarios(data.horarios);
        }
    } catch (error) {
        console.error('Error cargando horarios:', error);
    }
}

function renderHorarios(horarios) {
    const container = document.getElementById('horariosContainer');
    const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const iconos = ['☀️', '🌅', '🌅', '🌅', '🌅', '🌅', '🌙'];
    
    let html = '';
    
    dias.forEach((dia, index) => {
        const horario = horarios.find(h => h.dia_semana == index) || {
            dia_semana: index,
            activo: 0,
            manana_inicio: '08:00',
            manana_fin: '12:00',
            tarde_inicio: '14:00',
            tarde_fin: '18:00'
        };
        
        const isActivo = horario.activo == 1;
        
        html += `
            <div class="horario-day-card ${!isActivo ? 'inactive' : ''}" data-day="${index}">
                <div class="horario-header">
                    <h5>${iconos[index]} ${dia}</h5>
                    <label class="switch">
                        <input type="checkbox" 
                               name="horario[${index}][activo]" 
                               ${isActivo ? 'checked' : ''}
                               onchange="toggleDaySchedule(${index}, this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="horario-body" id="schedule-${index}">
                    <div class="horario-turno">
                        <h6><i class="fas fa-sun"></i> Turno Mañana</h6>
                        <div class="horario-inputs">
                            <input type="time" 
                                   name="horario[${index}][manana_inicio]" 
                                   class="form-control"
                                   value="${horario.manana_inicio || ''}"
                                   ${!isActivo ? 'disabled' : ''}>
                            <span>a</span>
                            <input type="time" 
                                   name="horario[${index}][manana_fin]" 
                                   class="form-control"
                                   value="${horario.manana_fin || ''}"
                                   ${!isActivo ? 'disabled' : ''}>
                        </div>
                    </div>
                    
                    <div class="horario-turno">
                        <h6><i class="fas fa-moon"></i> Turno Tarde</h6>
                        <div class="horario-inputs">
                            <input type="time" 
                                   name="horario[${index}][tarde_inicio]" 
                                   class="form-control"
                                   value="${horario.tarde_inicio || ''}"
                                   ${!isActivo ? 'disabled' : ''}>
                            <span>a</span>
                            <input type="time" 
                                   name="horario[${index}][tarde_fin]" 
                                   class="form-control"
                                   value="${horario.tarde_fin || ''}"
                                   ${!isActivo ? 'disabled' : ''}>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function toggleDaySchedule(day, enabled) {
    const card = document.querySelector(`.horario-day-card[data-day="${day}"]`);
    const inputs = card.querySelectorAll('input[type="time"]');
    
    if (enabled) {
        card.classList.remove('inactive');
        inputs.forEach(input => input.disabled = false);
    } else {
        card.classList.add('inactive');
        inputs.forEach(input => input.disabled = true);
    }
}

async function saveHorarios(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    // Organizar datos por día
    const horarios = [];
    
    for (let i = 0; i <= 6; i++) {
        const activo = formData.get(`horario[${i}][activo]`) ? 1 : 0;
        
        horarios.push({
            dia_semana: i,
            activo: activo,
            manana_inicio: formData.get(`horario[${i}][manana_inicio]`) || null,
            manana_fin: formData.get(`horario[${i}][manana_fin]`) || null,
            tarde_inicio: formData.get(`horario[${i}][tarde_inicio]`) || null,
            tarde_fin: formData.get(`horario[${i}][tarde_fin]`) || null
        });
    }
    
    try {
        const response = await fetch('api/horarios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ horarios: horarios })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Horarios guardados correctamente', 'success');
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error(error);
        showNotification('Error de conexión', 'error');
    }
    
    return false;
}

// Guardar configuración
async function saveSettings(event, section) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {};
    
    // Convertir checkboxes correctamente
    for (let [key, value] of formData.entries()) {
        const element = event.target.elements[key];
        if (element && element.type === 'checkbox') {
            data[key] = element.checked ? '1' : '0';
        } else {
            data[key] = value;
        }
    }
    
    // IMPORTANTE: Agregar checkboxes NO marcados
    const checkboxes = event.target.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        if (!formData.has(checkbox.name)) {
            data[checkbox.name] = '0';
        }
    });
    
    console.log('Datos a enviar:', data); // Para debug
    
    try {
        const response = await fetch('api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Configuración guardada correctamente', 'success');
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
    return false;
}

// Gestión de usuarios
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
            document.getElementById('userRol').value = user.rol;
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
            showNotification(data.id ? 'Usuario actualizado' : 'Usuario creado', 'success');
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

async function deleteUser(userId) {
    if (!confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')) return;
    
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: userId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Usuario eliminado', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
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
            showNotification(`Usuario ${status ? 'activado' : 'desactivado'}`, 'success');
        } else {
            showNotification('Error: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Funciones avanzadas
async function optimizeDatabase() {
    if (!confirm('¿Optimizar la base de datos? Esto puede tomar unos momentos.')) return;
    
    showNotification('Optimizando base de datos...', 'info');
    // Implementar llamada al API
}

async function clearOldLogs() {
    if (!confirm('¿Eliminar logs de más de 30 días?')) return;
    
    try {
        const response = await fetch('api/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_logs' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`${result.deleted} registros eliminados`, 'success');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

async function exportData() {
    showNotification('Generando exportación...', 'info');
    window.location.href = 'api/export.php';
}
</script>