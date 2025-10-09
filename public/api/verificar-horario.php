<?php
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'b8c1a3c243e89f8c12e401e00d14b9d8021d7e264c5885d20138045f7c569a0d') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

// Configurar zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');
$diaSemana = (int)date('w'); // 0=Domingo, 6=Sábado
$horaActual = date('H:i:s');

// Obtener horario del día
$horario = $db->fetch(
    "SELECT * FROM horarios_atencion WHERE dia_semana = ?",
    [$diaSemana]
);

$enHorario = false;

if ($horario && $horario['activo']) {
    // Verificar turno mañana
    if ($horario['manana_inicio'] && $horario['manana_fin']) {
        if ($horaActual >= $horario['manana_inicio'] && $horaActual <= $horario['manana_fin']) {
            $enHorario = true;
        }
    }
    
    // Verificar turno tarde
    if ($horario['tarde_inicio'] && $horario['tarde_fin']) {
        if ($horaActual >= $horario['tarde_inicio'] && $horaActual <= $horario['tarde_fin']) {
            $enHorario = true;
        }
    }
}

echo json_encode([
    'enHorario' => $enHorario,
    'dia' => $diaSemana,
    'hora' => $horaActual
]);