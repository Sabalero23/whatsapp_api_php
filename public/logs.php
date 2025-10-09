<?php
/**
 * Visor de Logs PM2 en Tiempo Real
 * Muestra logs del servidor WhatsApp con auto-refresh
 */

$logFile = '/www/wwwroot/whatsapp.cellcomweb.com.ar/logs/combined.log';
$pm2LogOut = '/www/wwwroot/whatsapp.cellcomweb.com.ar/logs/pm2-out.log';
$pm2LogErr = '/www/wwwroot/whatsapp.cellcomweb.com.ar/logs/pm2-error.log';

// Determinar qué log mostrar
$source = $_GET['source'] ?? 'combined';
$lines = intval($_GET['lines'] ?? 100);
$autoRefresh = isset($_GET['auto']) && $_GET['auto'] === '1';

// Seleccionar archivo según source
switch ($source) {
    case 'pm2-out':
        $currentLog = $pm2LogOut;
        $title = 'PM2 Output Log';
        break;
    case 'pm2-error':
        $currentLog = $pm2LogErr;
        $title = 'PM2 Error Log';
        break;
    case 'combined':
    default:
        $currentLog = $logFile;
        $title = 'Combined Log';
        break;
}

// Leer últimas líneas del log
function tail($file, $lines = 100) {
    if (!file_exists($file)) {
        return "Archivo no encontrado: $file";
    }
    
    $output = shell_exec("tail -n $lines " . escapeshellarg($file));
    return $output ?: "Log vacío o sin permisos de lectura";
}

$logContent = tail($currentLog, $lines);

// Si es petición AJAX, devolver solo el contenido
if (isset($_GET['ajax'])) {
    header('Content-Type: text/plain');
    echo $logContent;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Logs - <?php echo $title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Monaco', 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        
        .header {
            background: #2d2d30;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .header h1 {
            color: #4ec9b0;
            font-size: 1.5em;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #1177bb;
        }
        
        .btn.active {
            background: #16825d;
        }
        
        .btn.danger {
            background: #d73a49;
        }
        
        .btn.danger:hover {
            background: #cb2431;
        }
        
        select {
            background: #3c3c3c;
            color: #d4d4d4;
            border: 1px solid #555;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9em;
        }
        
        .log-container {
            background: #1e1e1e;
            border: 1px solid #3c3c3c;
            border-radius: 8px;
            padding: 20px;
            min-height: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .log-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 0.85em;
            line-height: 1.6;
        }
        
        .log-line {
            padding: 2px 0;
        }
        
        .log-line.error {
            color: #f48771;
        }
        
        .log-line.warn {
            color: #dcdcaa;
        }
        
        .log-line.info {
            color: #4fc1ff;
        }
        
        .log-line.success {
            color: #4ec9b0;
        }
        
        .status-bar {
            background: #2d2d30;
            padding: 10px 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85em;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.active {
            background: #4ec9b0;
            animation: pulse 2s infinite;
        }
        
        .status-indicator.paused {
            background: #d4d4d4;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .filter-bar {
            background: #2d2d30;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-bar input {
            background: #3c3c3c;
            color: #d4d4d4;
            border: 1px solid #555;
            padding: 8px 12px;
            border-radius: 5px;
            flex: 1;
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .controls {
                width: 100%;
            }
            
            .btn, select {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 WhatsApp Logs Viewer</h1>
        <div class="controls">
            <select id="logSource" onchange="changeSource(this.value)">
                <option value="combined" <?php echo $source === 'combined' ? 'selected' : ''; ?>>Combined Log</option>
                <option value="pm2-out" <?php echo $source === 'pm2-out' ? 'selected' : ''; ?>>PM2 Output</option>
                <option value="pm2-error" <?php echo $source === 'pm2-error' ? 'selected' : ''; ?>>PM2 Errors</option>
            </select>
            
            <select id="logLines" onchange="changeLines(this.value)">
                <option value="50">50 líneas</option>
                <option value="100" <?php echo $lines === 100 ? 'selected' : ''; ?>>100 líneas</option>
                <option value="200" <?php echo $lines === 200 ? 'selected' : ''; ?>>200 líneas</option>
                <option value="500" <?php echo $lines === 500 ? 'selected' : ''; ?>>500 líneas</option>
            </select>
            
            <button class="btn <?php echo $autoRefresh ? 'active' : ''; ?>" id="autoRefreshBtn" onclick="toggleAutoRefresh()">
                <span id="refreshText"><?php echo $autoRefresh ? '⏸ Pausar' : '▶ Auto-Refresh'; ?></span>
            </button>
            
            <button class="btn" onclick="refreshNow()">🔄 Actualizar</button>
            
            <button class="btn danger" onclick="clearLogs()">🗑️ Limpiar</button>
        </div>
    </div>
    
    <div class="filter-bar">
        <input type="text" id="filterInput" placeholder="Filtrar logs... (ej: error, QR, autenticación)" onkeyup="filterLogs()">
        <button class="btn" onclick="document.getElementById('filterInput').value=''; filterLogs();">✕ Limpiar filtro</button>
    </div>
    
    <div class="log-container">
        <div class="log-content" id="logContent"><?php echo htmlspecialchars($logContent); ?></div>
    </div>
    
    <div class="status-bar">
        <div>
            <span class="status-indicator <?php echo $autoRefresh ? 'active' : 'paused'; ?>" id="statusIndicator"></span>
            <span id="statusText"><?php echo $autoRefresh ? 'Auto-refresh activo' : 'Auto-refresh pausado'; ?></span>
        </div>
        <div>
            Última actualización: <span id="lastUpdate"><?php echo date('H:i:s'); ?></span>
        </div>
    </div>

    <script>
        let autoRefresh = <?php echo $autoRefresh ? 'true' : 'false'; ?>;
        let refreshInterval = null;
        let currentSource = '<?php echo $source; ?>';
        let currentLines = <?php echo $lines; ?>;
        
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            const btn = document.getElementById('autoRefreshBtn');
            const text = document.getElementById('refreshText');
            const indicator = document.getElementById('statusIndicator');
            const statusText = document.getElementById('statusText');
            
            if (autoRefresh) {
                btn.classList.add('active');
                text.textContent = '⏸ Pausar';
                indicator.classList.add('active');
                indicator.classList.remove('paused');
                statusText.textContent = 'Auto-refresh activo';
                startAutoRefresh();
                updateURL();
            } else {
                btn.classList.remove('active');
                text.textContent = '▶ Auto-Refresh';
                indicator.classList.remove('active');
                indicator.classList.add('paused');
                statusText.textContent = 'Auto-refresh pausado';
                stopAutoRefresh();
                updateURL();
            }
        }
        
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(refreshNow, 3000); // Cada 3 segundos
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
        
        function refreshNow() {
            fetch(`?source=${currentSource}&lines=${currentLines}&ajax=1`)
                .then(response => response.text())
                .then(data => {
                    const logContent = document.getElementById('logContent');
                    const wasAtBottom = logContent.scrollHeight - logContent.scrollTop <= logContent.clientHeight + 50;
                    
                    logContent.textContent = data;
                    
                    // Hacer scroll al final si estaba cerca del final
                    if (wasAtBottom) {
                        logContent.scrollTop = logContent.scrollHeight;
                    }
                    
                    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                    filterLogs(); // Re-aplicar filtro
                })
                .catch(err => console.error('Error al actualizar logs:', err));
        }
        
        function changeSource(source) {
            currentSource = source;
            updateURL();
            window.location.href = `?source=${source}&lines=${currentLines}${autoRefresh ? '&auto=1' : ''}`;
        }
        
        function changeLines(lines) {
            currentLines = lines;
            updateURL();
            window.location.href = `?source=${currentSource}&lines=${lines}${autoRefresh ? '&auto=1' : ''}`;
        }
        
        function updateURL() {
            const url = new URL(window.location);
            url.searchParams.set('source', currentSource);
            url.searchParams.set('lines', currentLines);
            if (autoRefresh) {
                url.searchParams.set('auto', '1');
            } else {
                url.searchParams.delete('auto');
            }
            history.replaceState({}, '', url);
        }
        
        function filterLogs() {
            const filter = document.getElementById('filterInput').value.toLowerCase();
            const logContent = document.getElementById('logContent');
            const text = logContent.textContent;
            
            if (!filter) {
                // Sin filtro, mostrar todo
                return;
            }
            
            const lines = text.split('\n');
            const filtered = lines.filter(line => line.toLowerCase().includes(filter));
            logContent.textContent = filtered.join('\n');
        }
        
        function clearLogs() {
            if (confirm('¿Estás seguro de que quieres limpiar los logs?')) {
                // Nota: Esto solo limpia visualmente, no el archivo real
                document.getElementById('logContent').textContent = 'Logs limpiados (solo vista)...';
            }
        }
        
        // Iniciar auto-refresh si está activo
        if (autoRefresh) {
            startAutoRefresh();
        }
        
        // Auto-scroll al final al cargar
        document.getElementById('logContent').scrollTop = document.getElementById('logContent').scrollHeight;
    </script>
</body>
</html>