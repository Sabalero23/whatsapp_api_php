<?php
// Verificar permiso de visualización
Auth::requirePermission('view_status');

// Obtener instancia de Auth
$auth = Auth::getInstance();

// Verificar permisos específicos
$canCreate = $auth->hasPermission('create_status');
$canDelete = $auth->hasPermission('delete_status');

// Las variables $db, $whatsapp, $user ya están disponibles desde index.php
$isReady = $whatsapp->isReady();

// Procesar formulario de nuevo estado
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_status') {
        try {
            $text = trim($_POST['status_text'] ?? '');
            $backgroundColor = $_POST['background_color'] ?? '#25D366';
            $font = $_POST['font'] ?? 'Arial';
            
            if (empty($text)) {
                throw new Exception('El texto del estado no puede estar vacío');
            }
            
            // Crear estado usando la API
            $result = $whatsapp->sendTextStatus($text, $backgroundColor, $font);
            
            // Guardar en BD usando el método insert()
            $db->insert('estados', [
                'usuario_id' => $_SESSION['user_id'],
                'texto' => $text,
                'background_color' => $backgroundColor,
                'font' => $font,
                'tipo' => 'texto',
                'fecha_publicacion' => date('Y-m-d H:i:s')
            ]);
            
            $message = 'Estado publicado correctamente';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error al publicar estado: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}


// Obtener estados propios recientes (últimas 24 horas)
$myStatuses = [];
try {
    $myStatuses = $db->fetchAll(
        "SELECT * FROM estados 
        WHERE usuario_id = ? AND fecha_publicacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY fecha_publicacion DESC",
        [$_SESSION['user_id']]
    );
} catch (Exception $e) {
    error_log("Error obteniendo mis estados: " . $e->getMessage());
}

// Obtener estados de contactos (simulación - WhatsApp Web.js no expone esto directamente)
$contactStatuses = [];
try {
    // Obtener últimos mensajes entrantes como proxy de "estados de contactos"
    $contactStatuses = $db->fetchAll(
        "SELECT 
            me.numero_remitente,
            c.nombre,
            COUNT(*) as total_mensajes,
            MAX(me.fecha_recepcion) as ultima_actividad
        FROM mensajes_entrantes me
        LEFT JOIN contactos c ON c.numero = me.numero_remitente
        WHERE me.fecha_recepcion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND me.numero_remitente != 'status@broadcast'
        GROUP BY me.numero_remitente, c.nombre
        ORDER BY ultima_actividad DESC
        LIMIT 20"
    );
} catch (Exception $e) {
    error_log("Error obteniendo actividad de contactos: " . $e->getMessage());
}
?>

<style>
* {
    box-sizing: border-box;
}

.status-container {
    max-width: 1400px;
    margin: 0 auto;
}

.status-header {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 8px 24px rgba(37, 211, 102, 0.2);
}

.status-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.tabs {
    display: flex;
    gap: 0;
    margin-bottom: 30px;
    background: white;
    border-radius: 12px;
    padding: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.tab {
    flex: 1;
    padding: 14px 24px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    color: #666;
    border-radius: 10px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab:hover {
    color: #25D366;
    background: #f0f9f4;
}

.tab.active {
    background: #25D366;
    color: white;
    box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 24px;
}

@media (max-width: 1024px) {
    .grid {
        grid-template-columns: 1fr;
    }
}

.card {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
}

.card h2 {
    margin: 0 0 24px 0;
    font-size: 20px;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 500;
    font-size: 14px;
}

.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.3s;
}

.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #25D366;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
    line-height: 1.6;
}

.color-picker {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin-top: 12px;
}

.color-option {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 12px;
    border: 3px solid transparent;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.color-option:hover {
    transform: scale(1.05);
}

.color-option.selected {
    border-color: #25D366;
    transform: scale(1.1);
}

.color-option.selected::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 18px;
    font-weight: bold;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.status-preview {
    background: #25D366;
    border-radius: 16px;
    padding: 50px 30px;
    text-align: center;
    color: white;
    font-size: 18px;
    line-height: 1.6;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 24px 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    word-wrap: break-word;
}

.btn {
    background: #25D366;
    color: white;
    padding: 14px 28px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn:hover {
    background: #20bd5a;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
}

.btn:disabled {
    background: #d0d0d0;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    background: #f5f5f5;
    color: #333;
}

.btn-secondary:hover {
    background: #e8e8e8;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.statuses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.status-item {
    background: white;
    border-radius: 14px;
    overflow: hidden;
    border: 2px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.3s;
}

.status-item:hover {
    border-color: #25D366;
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.status-mini {
    height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 15px;
    padding: 20px;
    text-align: center;
    line-height: 1.5;
    word-wrap: break-word;
}

.status-info {
    padding: 12px 16px;
    background: #fafafa;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #666;
}

.status-info span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert.success {
    background: #dcf8c6;
    color: #075e54;
    border: 2px solid #25D366;
}

.alert.error {
    background: #ffe0e0;
    color: #c62828;
    border: 2px solid #f44336;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
}

.contact-status-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: white;
    border-radius: 12px;
    border: 2px solid #f0f0f0;
    transition: all 0.3s;
    cursor: pointer;
}

.contact-status-item:hover {
    border-color: #25D366;
    background: #f9fdf9;
    transform: translateX(4px);
}

.contact-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #25D366, #128C7E);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2);
}

.contact-info {
    flex: 1;
    min-width: 0;
}

.contact-name {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a1a;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.contact-meta {
    font-size: 13px;
    color: #999;
    display: flex;
    align-items: center;
    gap: 12px;
}

.contact-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.activity-badge {
    background: #dcf8c6;
    color: #075e54;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

small {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #999;
}
</style>

<div class="status-container">
    <div class="status-header">
        <h1>
            <i class="fas fa-circle-notch"></i>
            Estados de WhatsApp
        </h1>
        <p>Comparte momentos que desaparecen en 24 horas</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('create')">
            <i class="fas fa-plus-circle"></i>
            Crear Estado
        </button>
        <button class="tab" onclick="switchTab('my-statuses')">
            <i class="fas fa-user"></i>
            Mis Estados
        </button>
        <button class="tab" onclick="switchTab('contacts')">
            <i class="fas fa-users"></i>
            Actividad de Contactos
        </button>
    </div>
    
    <!-- TAB: Crear Estado -->
    <div id="tab-create" class="tab-content active">
        <div class="grid">
            <div class="card">
                <h2>
                    <i class="fas fa-edit"></i>
                    Nuevo Estado
                </h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_status">
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-align-left"></i>
                            Mensaje
                        </label>
                        <textarea 
                            name="status_text" 
                            id="statusText"
                            placeholder="¿Qué quieres compartir?"
                            required
                            maxlength="700"></textarea>
                        <small>Máximo 700 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-palette"></i>
                            Color de fondo
                        </label>
                        <input type="hidden" name="background_color" id="bgColor" value="#25D366">
                        <div class="color-picker">
                            <div class="color-option selected" style="background: #25D366" data-color="#25D366"></div>
                            <div class="color-option" style="background: #128C7E" data-color="#128C7E"></div>
                            <div class="color-option" style="background: #075e54" data-color="#075e54"></div>
                            <div class="color-option" style="background: #34b7f1" data-color="#34b7f1"></div>
                            <div class="color-option" style="background: #ea4335" data-color="#ea4335"></div>
                            <div class="color-option" style="background: #fbbc04" data-color="#fbbc04"></div>
                            <div class="color-option" style="background: #9c27b0" data-color="#9c27b0"></div>
                            <div class="color-option" style="background: #ff6f00" data-color="#ff6f00"></div>
                            <div class="color-option" style="background: #000000" data-color="#000000"></div>
                            <div class="color-option" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)" data-color="#667eea"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-font"></i>
                            Tipografía
                        </label>
                        <select name="font" id="fontSelect">
                            <option value="Arial">Sans-serif</option>
                            <option value="Serif">Serif</option>
                            <option value="monospace">Monospace</option>
                        </select>
                    </div>
                    
                    <?php if ($canCreate): ?>
    <form method="POST">
        <!-- Contenido del formulario -->
        <button type="submit" class="btn" style="width: 100%;">
            <i class="fas fa-paper-plane"></i>
            Publicar Estado
        </button>
    </form>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-lock"></i> No tienes permiso para crear estados
    </div>
<?php endif; ?>
            </div>
            
            <div class="card">
                <h2>
                    <i class="fas fa-eye"></i>
                    Vista Previa
                </h2>
                <div class="status-preview" id="statusPreview">
                    Escribe algo para ver cómo se verá tu estado...
                </div>
                <small style="text-align: center; color: #999;">
                    <i class="fas fa-info-circle"></i>
                    Los estados desaparecen automáticamente después de 24 horas
                </small>
            </div>
        </div>
    </div>
    
    <!-- TAB: Mis Estados -->
    <div id="tab-my-statuses" class="tab-content">
        <div class="card">
            <h2>
                <i class="fas fa-clock"></i>
                Mis Estados Activos (24h)
            </h2>
            
            <?php if (empty($myStatuses)): ?>
                <div class="empty-state">
                    <i class="fas fa-circle-notch"></i>
                    <p>No has publicado ningún estado en las últimas 24 horas</p>
                </div>
            <?php else: ?>
                <div class="statuses-grid">
                    <?php foreach ($myStatuses as $status): ?>
                        <div class="status-item">
                            <div class="status-mini" style="background: <?= htmlspecialchars($status['background_color']) ?>; font-family: <?= htmlspecialchars($status['font']) ?>">
                                <?= htmlspecialchars(substr($status['texto'], 0, 100)) ?>
                                <?= strlen($status['texto']) > 100 ? '...' : '' ?>
                            </div>
                            <div class="status-info">
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?= date('H:i', strtotime($status['fecha_publicacion'])) ?>
                                </span>
                                <span>
                                    <i class="fas fa-eye"></i>
                                    <?= $status['vistas'] ?? 0 ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- TAB: Estados de Contactos -->
    <div id="tab-contacts" class="tab-content">
        <div class="card">
            <h2>
                <i class="fas fa-users"></i>
                Actividad Reciente de Contactos
            </h2>
            
            <?php if (empty($contactStatuses)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>No hay actividad reciente de contactos</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($contactStatuses as $contact): ?>
                        <div class="contact-status-item">
                            <div class="contact-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="contact-info">
                                <div class="contact-name">
                                    <?= htmlspecialchars($contact['nombre'] ?: $contact['numero_remitente']) ?>
                                </div>
                                <div class="contact-meta">
                                    <span>
                                        <i class="fas fa-comment"></i>
                                        <?= $contact['total_mensajes'] ?> mensaje<?= $contact['total_mensajes'] != 1 ? 's' : '' ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= date('d/m H:i', strtotime($contact['ultima_actividad'])) ?>
                                    </span>
                                </div>
                            </div>
                            <span class="activity-badge">Activo</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const statusText = document.getElementById('statusText');
const statusPreview = document.getElementById('statusPreview');
const bgColorInput = document.getElementById('bgColor');
const fontSelect = document.getElementById('fontSelect');
const colorOptions = document.querySelectorAll('.color-option');

// Actualizar vista previa
statusText?.addEventListener('input', updatePreview);
fontSelect?.addEventListener('change', updatePreview);

function updatePreview() {
    const text = statusText.value.trim();
    const font = fontSelect.value;
    
    statusPreview.textContent = text || 'Escribe algo para ver cómo se verá tu estado...';
    statusPreview.style.fontFamily = font;
}

// Selección de color
colorOptions.forEach(option => {
    option.addEventListener('click', function() {
        colorOptions.forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        
        const color = this.dataset.color;
        bgColorInput.value = color;
        statusPreview.style.background = this.style.background;
        
        // Ajustar color de texto según brillo del fondo
        if (color.startsWith('#')) {
            const brightness = getBrightness(color);
            statusPreview.style.color = brightness > 128 ? '#000' : '#fff';
        } else {
            statusPreview.style.color = '#fff';
        }
    });
});

function getBrightness(color) {
    const rgb = color.match(/\w\w/g).map(x => parseInt(x, 16));
    return (rgb[0] * 299 + rgb[1] * 587 + rgb[2] * 114) / 1000;
}

// Contador de caracteres
statusText?.addEventListener('input', function() {
    const remaining = 700 - this.value.length;
    const counter = this.nextElementSibling;
    counter.textContent = `Máximo 700 caracteres (${remaining} restantes)`;
    counter.style.color = remaining < 50 ? '#ea4335' : '#999';
});

// Cambiar tabs
function switchTab(tabName) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.closest('.tab').classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}
</script>