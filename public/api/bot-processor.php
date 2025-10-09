<?php
/**
 * Monitor de mensajes (SOLO estadísticas)
 * NO envía respuestas automáticas
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/WhatsAppClient.php';

$redisConfig = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null
];

$whatsapp = new WhatsAppClient(
    'http://localhost:3000',
    'b8c1a3c243e89f8c12e401e00d14b9d8021d7e264c5885d20138045f7c569a0d',
    $redisConfig
);

$db = Database::getInstance();

echo "📊 Monitor de estadísticas iniciado (NO envía mensajes)\n";

while (true) {
    try {
        $messages = $whatsapp->getIncomingMessages(10);
        
        foreach ($messages as $message) {
            // SOLO guardar estadísticas
            if (isset($message['fromMe']) && $message['fromMe']) continue;
            if (strpos($message['from'], 'status@broadcast') !== false) continue;
            
            $from = $message['from'];
            $body = $message['body'] ?? '';
            
            if (empty(trim($body))) continue;
            
            echo "📩 Mensaje guardado: $from\n";
            
            // Guardar en BD para estadísticas
            try {
                $db->insert('mensajes_entrantes', [
                    'numero_remitente' => $from,
                    'mensaje' => $body,
                    'fecha_recepcion' => date('Y-m-d H:i:s'),
                    'procesado' => 1
                ]);
            } catch (Exception $e) {
                echo "⚠️ Error guardando: " . $e->getMessage() . "\n";
            }
        }
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        sleep(5);
    }
}