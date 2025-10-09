<?php
// Verificar permiso de visualización
Auth::requirePermission('view_stats');

// Obtener estadísticas (excluyendo status@broadcast)

// Estadísticas de hoy
$today = $db->fetch(
    "SELECT 
        COALESCE(mensajes_enviados, 0) as enviados,
        COALESCE(mensajes_recibidos, 0) as recibidos
    FROM estadisticas_diarias 
    WHERE fecha = CURDATE()"
);

// Si no hay registro, obtener de las tablas directamente
if (!$today || ($today['enviados'] == 0 && $today['recibidos'] == 0)) {
    $today = $db->fetch(
        "SELECT 
            (SELECT COUNT(*) FROM mensajes_salientes WHERE DATE(fecha_envio) = CURDATE()) as enviados,
            (SELECT COUNT(*) FROM mensajes_entrantes 
             WHERE DATE(fecha_recepcion) = CURDATE() 
             AND numero_remitente != 'status@broadcast') as recibidos"
    );
}

// Estadísticas del mes
$month = $db->fetch(
    "SELECT 
        SUM(COALESCE(mensajes_enviados, 0)) as enviados,
        SUM(COALESCE(mensajes_recibidos, 0)) as recibidos
    FROM estadisticas_diarias 
    WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())"
);

// Si no hay registros, obtener de las tablas directamente
if (!$month || ($month['enviados'] == 0 && $month['recibidos'] == 0)) {
    $month = $db->fetch(
        "SELECT 
            (SELECT COUNT(*) FROM mensajes_salientes 
             WHERE MONTH(fecha_envio) = MONTH(CURDATE()) 
             AND YEAR(fecha_envio) = YEAR(CURDATE())) as enviados,
            (SELECT COUNT(*) FROM mensajes_entrantes 
             WHERE MONTH(fecha_recepcion) = MONTH(CURDATE()) 
             AND YEAR(fecha_recepcion) = YEAR(CURDATE())
             AND numero_remitente != 'status@broadcast') as recibidos"
    );
}

// Últimos 7 días para gráfico (excluyendo status@broadcast)
$last7days = $db->fetchAll(
    "SELECT 
        DATE(d.fecha) as fecha,
        COALESCE(
            (SELECT COUNT(*) FROM mensajes_salientes 
             WHERE DATE(fecha_envio) = d.fecha), 0
        ) as enviados,
        COALESCE(
            (SELECT COUNT(*) FROM mensajes_entrantes 
             WHERE DATE(fecha_recepcion) = d.fecha 
             AND numero_remitente != 'status@broadcast'), 0
        ) as recibidos
    FROM (
        SELECT CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY as fecha
        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 
              UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 
              UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 
                    UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) AS b
    ) d
    WHERE d.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY d.fecha ASC
    LIMIT 7"
);

// Top contactos (últimos 30 días)
$topContacts = $db->fetchAll(
    "SELECT 
        ms.numero_destinatario as numero,
        COUNT(*) as total,
        c.nombre
    FROM mensajes_salientes ms
    LEFT JOIN contactos c ON c.numero = ms.numero_destinatario
    WHERE ms.fecha_envio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY ms.numero_destinatario
    ORDER BY total DESC
    LIMIT 10"
);

// Mensajes recibidos reales (últimos 30 días, sin status)
$topSenders = $db->fetchAll(
    "SELECT 
        me.numero_remitente as numero,
        COUNT(*) as total,
        c.nombre
    FROM mensajes_entrantes me
    LEFT JOIN contactos c ON c.numero = me.numero_remitente
    WHERE me.fecha_recepcion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND me.numero_remitente != 'status@broadcast'
    GROUP BY me.numero_remitente
    ORDER BY total DESC
    LIMIT 10"
);

?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<div class="stats-header">
    <h2>Estadísticas Detalladas</h2>
    <div style="font-size: 12px; color: #999;">
        <i class="fas fa-info-circle"></i> Excluyendo estados de WhatsApp
    </div>
</div>


<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3><i class="fas fa-chart-line"></i> Últimos 7 Días</h3>
    </div>
    <div class="card-body">
        <canvas id="statsChart" height="80"></canvas>
    </div>
</div>

<div class="section-row">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-paper-plane"></i> Top 10 Destinatarios</h3>
            <small style="color: #999;">Mensajes enviados (últimos 30 días)</small>
        </div>
        <div class="card-body">
            <?php if (empty($topContacts)): ?>
                <p class="empty-state">No hay datos suficientes</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Contacto</th>
                            <th>Mensajes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topContacts as $index => $contact): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?php if (!empty($contact['nombre'])): ?>
                                        <strong><?= htmlspecialchars($contact['nombre']) ?></strong>
                                        <br><small style="color: #999;"><?= htmlspecialchars($contact['numero']) ?></small>
                                    <?php else: ?>
                                        <?= htmlspecialchars($contact['numero']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= $contact['total'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-inbox"></i> Top 10 Remitentes</h3>
            <small style="color: #999;">Mensajes recibidos (últimos 30 días)</small>
        </div>
        <div class="card-body">
            <?php if (empty($topSenders)): ?>
                <p class="empty-state">No hay datos suficientes</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Contacto</th>
                            <th>Mensajes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topSenders as $index => $sender): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?php if (!empty($sender['nombre'])): ?>
                                        <strong><?= htmlspecialchars($sender['nombre']) ?></strong>
                                        <br><small style="color: #999;"><?= htmlspecialchars($sender['numero']) ?></small>
                                    <?php else: ?>
                                        <?= htmlspecialchars($sender['numero']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= $sender['total'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
const labels = <?= json_encode(array_map(function($d) { return date('d/m', strtotime($d['fecha'])); }, $last7days)) ?>;
const enviados = <?= json_encode(array_map(function($d) { return (int)$d['enviados']; }, $last7days)) ?>;
const recibidos = <?= json_encode(array_map(function($d) { return (int)$d['recibidos']; }, $last7days)) ?>;

const ctx = document.getElementById('statsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Enviados',
            data: enviados,
            borderColor: '#25D366',
            backgroundColor: 'rgba(37, 211, 102, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Recibidos',
            data: recibidos,
            borderColor: '#128C7E',
            backgroundColor: 'rgba(18, 140, 126, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { 
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 15
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: { 
            y: { 
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        }
    }
});
</script>

<style>
.stats-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 30px; 
}
.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
    font-style: italic;
}
</style>