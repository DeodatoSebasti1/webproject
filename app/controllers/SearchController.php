<?php
// /urban/app/controllers/SearchController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/AppEventService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = $_GET['q'] ?? '';
$eventLogger = new AppEventService($conn ?? null);

if (strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

// Tentar API Nominatim com fallback
$results = fetchFromNominatim($query);

// Se Nominatim falhar, usar fallback local
if (empty($results)) {
    error_log("Nominatim failed for query: $query, using fallback");
    $eventLogger->logApiError('nominatim', 'No results from Nominatim, using fallback', ['query' => $query]);
    $results = getFallbackResults($query);
}

echo json_encode($results);

/**
 * Buscar da API Nominatim com tratamento robusto de erros
 */
function fetchFromNominatim($query) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&addressdetails=1&limit=10&countrycodes=pt";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UrbanTraffic-App/1.0 (contact@urbantraffic.com)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Verificar erros de cURL
    if ($curlError) {
        error_log("Nominatim cURL Error: $curlError");
        return null;
    }

    // Verificar código HTTP
    if ($httpCode !== 200) {
        error_log("Nominatim HTTP Error: $httpCode");
        return null;
    }

    // Decodificar JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Nominatim JSON Error: " . json_last_error_msg());
        return null;
    }

    // Verificar se dados são válidos
    if (empty($data) || !is_array($data)) {
        error_log("Nominatim: Empty or invalid data");
        return null;
    }

    // Formatar resposta
    $results = [];
    foreach ($data as $item) {
        $results[] = [
            'display' => implode(',', array_slice(explode(',', $item['display_name']), 0, 3)),
            'full' => $item['display_name'],
            'type' => $item['type'],
            'class' => $item['class'],
            'lat' => $item['lat'],
            'lon' => $item['lon']
        ];
    }

    return $results;
}

/**
 * Fallback local quando API Nominatim falha
 */
function getFallbackResults($query) {
    // Lista de localizações comuns em Portugal como fallback
    $fallbackLocations = [
        'lisboa' => [
            ['display' => 'Lisboa, Portugal', 'full' => 'Lisboa, Portugal', 'type' => 'city', 'class' => 'place', 'lat' => '38.7223', 'lon' => '-9.1393'],
            ['display' => 'Baixa, Lisboa', 'full' => 'Baixa, Lisboa, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.7149', 'lon' => '-9.1377'],
            ['display' => 'Alfama, Lisboa', 'full' => 'Alfama, Lisboa, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.7135', 'lon' => '-9.1279'],
            ['display' => 'Belém, Lisboa', 'full' => 'Belém, Lisboa, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.6935', 'lon' => '-9.2043'],
            ['display' => 'Parque das Nações, Lisboa', 'full' => 'Parque das Nações, Lisboa, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.7678', 'lon' => '-9.0941']
        ],
        'porto' => [
            ['display' => 'Porto, Portugal', 'full' => 'Porto, Portugal', 'type' => 'city', 'class' => 'place', 'lat' => '41.1579', 'lon' => '-8.6291'],
            ['display' => 'Baixa, Porto', 'full' => 'Baixa, Porto, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '41.1459', 'lon' => '-8.6130'],
            ['display' => 'Ribeira, Porto', 'full' => 'Ribeira, Porto, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '41.1412', 'lon' => '-8.6127']
        ],
        'cascais' => [
            ['display' => 'Cascais, Portugal', 'full' => 'Cascais, Portugal', 'type' => 'town', 'class' => 'place', 'lat' => '38.6975', 'lon' => '-9.4210'],
            ['display' => 'Estoril, Cascais', 'full' => 'Estoril, Cascais, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.7104', 'lon' => '-9.3996']
        ],
        'sintra' => [
            ['display' => 'Sintra, Portugal', 'full' => 'Sintra, Portugal', 'type' => 'town', 'class' => 'place', 'lat' => '38.8009', 'lon' => '-9.3816'],
            ['display' => 'Centro Histórico, Sintra', 'full' => 'Centro Histórico, Sintra, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.7979', 'lon' => '-9.3894']
        ],
        'almada' => [
            ['display' => 'Almada, Portugal', 'full' => 'Almada, Portugal', 'type' => 'town', 'class' => 'place', 'lat' => '38.6788', 'lon' => '-9.1637'],
            ['display' => 'Cacilhas, Almada', 'full' => 'Cacilhas, Almada, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.6835', 'lon' => '-9.1504']
        ],
        'oeiras' => [
            ['display' => 'Oeiras, Portugal', 'full' => 'Oeiras, Portugal', 'type' => 'town', 'class' => 'place', 'lat' => '38.6910', 'lon' => '-9.3115'],
            ['display' => 'Caxias, Oeiras', 'full' => 'Caxias, Oeiras, Portugal', 'type' => 'suburb', 'class' => 'place', 'lat' => '38.7041', 'lon' => '-9.2745']
        ]
    ];

    $queryLower = strtolower($query);
    $results = [];

    // Buscar correspondências exatas ou parciais
    foreach ($fallbackLocations as $key => $locations) {
        if (strpos($key, $queryLower) !== false || strpos($queryLower, $key) !== false) {
            $results = array_merge($results, $locations);
            break;
        }
    }

    // Se não encontrou nada, adicionar algumas localizações genéricas
    if (empty($results)) {
        $results = [
            ['display' => 'Lisboa, Portugal', 'full' => 'Lisboa, Portugal', 'type' => 'city', 'class' => 'place', 'lat' => '38.7223', 'lon' => '-9.1393'],
            ['display' => 'Porto, Portugal', 'full' => 'Porto, Portugal', 'type' => 'city', 'class' => 'place', 'lat' => '41.1579', 'lon' => '-8.6291'],
            ['display' => 'Cascais, Portugal', 'full' => 'Cascais, Portugal', 'type' => 'town', 'class' => 'place', 'lat' => '38.6975', 'lon' => '-9.4210']
        ];
    }

    error_log("Generated " . count($results) . " fallback results for query: $query");
    return $results;
}
