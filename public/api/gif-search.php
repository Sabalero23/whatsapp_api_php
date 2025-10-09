<?php
/**
 * Proxy para búsqueda de GIFs en Tenor
 * Evita exponer la API key en el cliente
 */

header('Content-Type: application/json');

// IMPORTANTE: Reemplaza con tu propia API key de Tenor
// Obtén una gratis en: https://developers.google.com/tenor/guides/quickstart
$TENOR_API_KEY = 'TU_API_KEY_AQUI';

// Si no tienes API key, usa Giphy como alternativa
$USE_GIPHY = false; // Cambia a true si usas Giphy
$GIPHY_API_KEY = 'TnThbBFUaXkEACL1JK8tn5biA0jIUmRA';

try {
    $query = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    
    if (empty($query)) {
        echo json_encode([
            'success' => false,
            'error' => 'Query vacío'
        ]);
        exit;
    }
    
    if ($USE_GIPHY) {
        // Usar Giphy API (alternativa)
        $url = sprintf(
            'https://api.giphy.com/v1/gifs/search?api_key=%s&q=%s&limit=%d&lang=es',
            $GIPHY_API_KEY,
            urlencode($query),
            $limit
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Error en la API de Giphy');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['data'])) {
            throw new Exception('Respuesta inválida de Giphy');
        }
        
        $results = [];
        foreach ($data['data'] as $gif) {
            $results[] = [
                'url' => $gif['images']['original']['url'],
                'preview' => $gif['images']['fixed_height_small']['url'],
                'title' => $gif['title'] ?? 'GIF'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
        
    } else {
        // Usar Tenor API
        $url = sprintf(
            'https://tenor.googleapis.com/v2/search?q=%s&key=%s&limit=%d&locale=es_AR&media_filter=gif,tinygif',
            urlencode($query),
            $TENOR_API_KEY,
            $limit
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Error en la API de Tenor: HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['results'])) {
            throw new Exception('Respuesta inválida de Tenor');
        }
        
        $results = [];
        foreach ($data['results'] as $gif) {
            $results[] = [
                'url' => $gif['media_formats']['gif']['url'] ?? '',
                'preview' => $gif['media_formats']['tinygif']['url'] ?? '',
                'title' => $gif['content_description'] ?? 'GIF'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error en gif-search.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'results' => []
    ]);
}