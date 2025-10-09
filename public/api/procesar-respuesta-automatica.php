<?php
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'b8c1a3c243e89f8c12e401e00d14b9d8021d7e264c5885d20138045f7c569a0d') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$numero = $input['numero'] ?? '';
$mensaje = $input['mensaje'] ?? '';

$db = Database::getInstance();

// Buscar respuesta automática
$mensajeLower = strtolower($mensaje);

$respuestas = $db->fetchAll(
    "SELECT * FROM respuestas_automaticas 
     WHERE activa = 1 
     ORDER BY prioridad DESC, id ASC"
);

// ⭐ IMPORTANTE: Solo procesar LA PRIMERA coincidencia
foreach ($respuestas as $respuesta) {
    $palabrasClave = array_map('trim', explode(',', $respuesta['palabra_clave']));
    
    foreach ($palabrasClave as $palabraClave) {
        if (empty($palabraClave)) continue;
        
        $palabraClave = strtolower($palabraClave);
        $coincide = false;
        
        if ($respuesta['exacta']) {
            $coincide = ($mensajeLower === $palabraClave);
        } else {
            $coincide = (strpos($mensajeLower, $palabraClave) !== false);
        }
        
        if ($coincide) {
            // Procesar TODAS las variables
            $respuestaTexto = $respuesta['respuesta'];
            
            // Variable {horarios}
            if (strpos($respuestaTexto, '{horarios}') !== false) {
                $horariosTexto = generarTextoHorarios($db);
                $respuestaTexto = str_replace('{horarios}', $horariosTexto, $respuestaTexto);
            }
            
            // Otras variables
            $respuestaTexto = str_replace('{numero}', $numero, $respuestaTexto);
            $respuestaTexto = str_replace('{fecha}', date('d/m/Y'), $respuestaTexto);
            $respuestaTexto = str_replace('{hora}', date('H:i'), $respuestaTexto);
            $respuestaTexto = str_replace('{nombre}', 'Cliente', $respuestaTexto);
            
            // Actualizar contador
            $db->query(
                "UPDATE respuestas_automaticas 
                 SET contador_usos = contador_usos + 1,
                     ultima_vez_usada = NOW()
                 WHERE id = ?",
                [$respuesta['id']]
            );
            
            // ⭐ DEVOLVER Y TERMINAR INMEDIATAMENTE
            echo json_encode(['respuesta' => $respuestaTexto]);
            exit; // ⭐ IMPORTANTE: Salir aquí
        }
    }
}

echo json_encode(['respuesta' => null]);

// Función para generar horarios
function generarTextoHorarios($db) {
    $dias = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado'
    ];
    
    $horarios = $db->fetchAll(
        "SELECT * FROM horarios_atencion ORDER BY dia_semana ASC"
    );
    
    if (empty($horarios)) {
        return "No hay horarios configurados.";
    }
    
    $resultado = "📅 *Nuestros horarios de atención:*\n\n";
    
    foreach ($horarios as $h) {
        $dia = $dias[$h['dia_semana']];
        
        if (!$h['activo']) {
            $resultado .= "❌ *{$dia}:* Cerrado\n";
            continue;
        }
        
        $turnos = [];
        
        if ($h['manana_inicio'] && $h['manana_fin']) {
            $turnos[] = substr($h['manana_inicio'], 0, 5) . ' a ' . substr($h['manana_fin'], 0, 5);
        }
        
        if ($h['tarde_inicio'] && $h['tarde_fin']) {
            $turnos[] = substr($h['tarde_inicio'], 0, 5) . ' a ' . substr($h['tarde_fin'], 0, 5);
        }
        
        if (empty($turnos)) {
            $resultado .= "❌ *{$dia}:* Cerrado\n";
        } else {
            $resultado .= "✅ *{$dia}:* " . implode(' y ', $turnos) . "\n";
        }
    }
    
    return trim($resultado);
}