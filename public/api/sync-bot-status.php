<?php
/**
 * Sincroniza el estado del bot desde MySQL a Redis
 * Ejecutar una vez para corregir el estado actual
 */

require_once __DIR__ . '/../../src/Database.php';

$db = Database::getInstance();

// Obtener estado actual de la BD
$botConfig = $db->fetch(
    "SELECT valor FROM configuracion WHERE clave = 'bot_activo'"
);

$estado = $botConfig['valor'] ?? '0';

// Conectar a Redis
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // Actualizar Redis
    $redis->set('whatsapp:bot_activo', $estado);
    
    echo "✓ Bot sincronizado correctamente\n";
    echo "Estado en MySQL: $estado\n";
    echo "Estado en Redis: " . $redis->get('whatsapp:bot_activo') . "\n";
    
    $redis->close();
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}