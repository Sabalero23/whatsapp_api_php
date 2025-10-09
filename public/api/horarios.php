<?php
session_start();
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        if (!isset($input['horarios']) || !is_array($input['horarios'])) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            exit;
        }
        
        // Guardar cada horario
        foreach ($input['horarios'] as $horario) {
            $diaSemana = (int)$horario['dia_semana'];
            
            // Verificar si existe el registro
            $existing = $db->fetch(
                "SELECT id FROM horarios_atencion WHERE dia_semana = ?",
                [$diaSemana]
            );
            
            $data = [
                'dia_semana' => $diaSemana,
                'activo' => (int)$horario['activo'],
                'manana_inicio' => $horario['manana_inicio'] ?: null,
                'manana_fin' => $horario['manana_fin'] ?: null,
                'tarde_inicio' => $horario['tarde_inicio'] ?: null,
                'tarde_fin' => $horario['tarde_fin'] ?: null
            ];
            
            if ($existing) {
                // Actualizar
                $db->update('horarios_atencion', $data, 'dia_semana = ?', [$diaSemana]);
            } else {
                // Insertar
                $db->insert('horarios_atencion', $data);
            }
        }
        
        // Log
        $db->insert('logs', [
            'usuario_id' => $_SESSION['user_id'],
            'accion' => 'Actualizar horarios de atención',
            'descripcion' => 'Horarios actualizados',
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Horarios guardados correctamente']);
        
    } catch (Exception $e) {
        error_log('Error en horarios.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Obtener todos los horarios
        $horarios = $db->fetchAll(
            "SELECT * FROM horarios_atencion ORDER BY dia_semana ASC"
        );
        
        // Si no hay horarios, crear los por defecto
        if (empty($horarios)) {
            $diasDefault = [
                ['dia_semana' => 0, 'activo' => 0], // Domingo
                ['dia_semana' => 1, 'activo' => 1, 'manana_inicio' => '08:00', 'manana_fin' => '12:00', 'tarde_inicio' => '14:00', 'tarde_fin' => '18:00'],
                ['dia_semana' => 2, 'activo' => 1, 'manana_inicio' => '08:00', 'manana_fin' => '12:00', 'tarde_inicio' => '14:00', 'tarde_fin' => '18:00'],
                ['dia_semana' => 3, 'activo' => 1, 'manana_inicio' => '08:00', 'manana_fin' => '12:00', 'tarde_inicio' => '14:00', 'tarde_fin' => '18:00'],
                ['dia_semana' => 4, 'activo' => 1, 'manana_inicio' => '08:00', 'manana_fin' => '12:00', 'tarde_inicio' => '14:00', 'tarde_fin' => '18:00'],
                ['dia_semana' => 5, 'activo' => 1, 'manana_inicio' => '08:00', 'manana_fin' => '12:00', 'tarde_inicio' => '14:00', 'tarde_fin' => '18:00'],
                ['dia_semana' => 6, 'activo' => 0] // Sábado
            ];
            
            foreach ($diasDefault as $dia) {
                $db->insert('horarios_atencion', $dia);
            }
            
            $horarios = $db->fetchAll(
                "SELECT * FROM horarios_atencion ORDER BY dia_semana ASC"
            );
        }
        
        echo json_encode(['success' => true, 'horarios' => $horarios]);
        
    } catch (Exception $e) {
        error_log('Error en horarios.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error del servidor']);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}