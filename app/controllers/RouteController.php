<?php
// /urban/app/controllers/RouteController.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/StopModel.php';
require_once __DIR__ . '/../services/GtfsRouteService.php';
require_once __DIR__ . '/../services/RouteCacheService.php';
require_once __DIR__ . '/../services/AppEventService.php';

header('Content-Type: application/json');

$routeRequestStartedAt = microtime(true);
$routeTimings = [];
$routeDeadlineSeconds = 10.5;

function routeMarkTiming(string $label): void {
    global $routeRequestStartedAt, $routeTimings;
    $routeTimings[$label] = round((microtime(true) - $routeRequestStartedAt) * 1000, 2);
}

function routeLog(string $message, array $context = []): void {
    $payload = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    error_log('[RouteController] ' . $message . $payload);
}

function routeHasTimedOut(): bool {
    global $routeRequestStartedAt, $routeDeadlineSeconds;
    return (microtime(true) - $routeRequestStartedAt) >= $routeDeadlineSeconds;
}

function routeIsDevelopment(): bool {
    if (defined('ENVIRONMENT')) {
        return ENVIRONMENT === 'development';
    }

    $serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
    $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    return in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
        || in_array($remoteAddr, ['127.0.0.1', '::1', ''], true);
}

function routeRespondError(string $message, string $errorCode, int $httpStatus = 400, array $extra = []): void {
    http_response_code($httpStatus);
    echo json_encode(array_merge([
        "status" => "error",
        "message" => $message,
        "error_code" => $errorCode,
        "routes" => []
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para calcular distância em metros (Haversine)
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

function calculateWalkTime($distanceMeters) {
    $speedMps = 1.39;
    $timeSeconds = $distanceMeters / $speedMps;
    return round($timeSeconds / 60);
}

function buildLinearCoordinates(float $fromLat, float $fromLon, float $toLat, float $toLon): array {
    return [
        [$fromLon, $fromLat],
        [$toLon, $toLat],
    ];
}

function mergeSegmentCoordinates(array $segments, array $fallbackLatLon = []): array {
    $merged = [];

    foreach ($segments as $segment) {
        $coordinates = $segment['geometry']['coordinates'] ?? ($segment['coordinates'] ?? []);
        foreach ($coordinates as $coord) {
            if (!is_array($coord) || count($coord) < 2) {
                continue;
            }

            $lon = (float)$coord[0];
            $lat = (float)$coord[1];
            if ($merged) {
                $last = $merged[count($merged) - 1];
                if (abs($last[0] - $lon) < 0.000001 && abs($last[1] - $lat) < 0.000001) {
                    continue;
                }
            }
            $merged[] = [$lon, $lat];
        }
    }

    return !empty($merged) ? $merged : $fallbackLatLon;
}

function buildBoundsFromSegments(array $segments, array $fallbackCoordinates = []): ?array {
    $minLon = $minLat = PHP_FLOAT_MAX;
    $maxLon = $maxLat = -PHP_FLOAT_MAX;
    $found = false;

    foreach ($segments as $segment) {
        $coordinates = $segment['geometry']['coordinates'] ?? ($segment['coordinates'] ?? []);
        foreach ($coordinates as $coord) {
            if (!is_array($coord) || count($coord) < 2) {
                continue;
            }
            $lon = (float)$coord[0];
            $lat = (float)$coord[1];
            $minLon = min($minLon, $lon);
            $minLat = min($minLat, $lat);
            $maxLon = max($maxLon, $lon);
            $maxLat = max($maxLat, $lat);
            $found = true;
        }
    }

    if (!$found) {
        foreach ($fallbackCoordinates as $coord) {
            if (!is_array($coord) || count($coord) < 2) {
                continue;
            }
            $lon = (float)$coord[0];
            $lat = (float)$coord[1];
            $minLon = min($minLon, $lon);
            $minLat = min($minLat, $lat);
            $maxLon = max($maxLon, $lon);
            $maxLat = max($maxLat, $lat);
            $found = true;
        }
    }

    if (!$found) {
        return null;
    }

    return [
        [$minLon, $minLat],
        [$maxLon, $maxLat],
    ];
}

function sanitizeSegmentsForDebug(array $segments, bool $debugEnabled): array {
    if ($debugEnabled) {
        return $segments;
    }

    foreach ($segments as &$segment) {
        unset($segment['debug']);
    }

    return $segments;
}

function sanitizeSegmentMarkers(array $segments): array {
    foreach ($segments as &$segment) {
        $markers = [];
        foreach (($segment['markers'] ?? []) as $marker) {
            $coordinates = $marker['coordinates'] ?? null;
            if (!is_array($coordinates) || count($coordinates) < 2) {
                continue;
            }
            $lon = (float)$coordinates[0];
            $lat = (float)$coordinates[1];
            if (!is_finite($lon) || !is_finite($lat)) {
                continue;
            }
            $markers[] = [
                'kind' => $marker['kind'] ?? 'point',
                'name' => $marker['name'] ?? 'Ponto',
                'coordinates' => [$lon, $lat],
                'action' => $marker['action'] ?? '',
                'line' => $marker['line'] ?? null,
                'type' => $marker['type'] ?? ($marker['kind'] ?? 'point'),
                'stop_id' => $marker['stop_id'] ?? null,
                'stop_name' => $marker['stop_name'] ?? ($marker['name'] ?? 'Ponto'),
                'arrival_time' => $marker['arrival_time'] ?? null,
                'departure_time' => $marker['departure_time'] ?? null,
                'wait_time' => $marker['wait_time'] ?? null,
                'from_line' => $marker['from_line'] ?? null,
                'to_line' => $marker['to_line'] ?? null,
                'from_trip_id' => $marker['from_trip_id'] ?? null,
                'to_trip_id' => $marker['to_trip_id'] ?? null,
                'instruction' => $marker['instruction'] ?? ($marker['action'] ?? ''),
            ];
        }
        $segment['markers'] = $markers;
    }

    return $segments;
}

function buildBoundingDelta(float $lat, float $meters): array {
    $latDelta = $meters / 111320;
    $lonDivisor = max(0.2, cos(deg2rad($lat)) * 111320);
    $lonDelta = $meters / $lonDivisor;
    return [$latDelta, $lonDelta];
}

function normalizeTextParam($value, int $maxLength = 120): ?string {
    if ($value === null) {
        return null;
    }

    $normalized = preg_replace('/\s+/', ' ', trim((string)$value)) ?? '';
    if ($normalized === '') {
        return null;
    }

    return mb_strlen($normalized) > $maxLength ? mb_substr($normalized, 0, $maxLength) : $normalized;
}

function normalizeCoordinateParam($value, float $min, float $max): ?float {
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $float = (float)$value;
    if (!is_finite($float) || $float < $min || $float > $max) {
        return null;
    }

    return $float;
}

function normalizeDepartureTimeParam($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = trim((string)$value);
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $normalized) ? $normalized : null;
}

function normalizeTravelDateParam($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $normalized);
    if (!$date || $date->format('Y-m-d') !== $normalized) {
        return null;
    }

    return $normalized;
}

function findNearbyStopsProgressive(float $lat, float $lon, PDO $conn, array $radiiMeters = [500, 900, 1400, 2200, 3200], int $perRadius = 8, int $maxTotal = 16): array {
    $results = [];
    $seen = [];

    foreach ($radiiMeters as $radiusMeters) {
        [$latDelta, $lonDelta] = buildBoundingDelta($lat, $radiusMeters);

        $stmt = $conn->prepare("
            SELECT stop_id, stop_name, stop_lat, stop_lon,
                   (ABS(stop_lat - ?) + ABS(stop_lon - ?)) AS approx_distance
            FROM stops
            WHERE stop_lat BETWEEN ? AND ?
              AND stop_lon BETWEEN ? AND ?
            ORDER BY approx_distance ASC
            LIMIT 160
        ");
        $stmt->execute([$lat, $lon, $lat - $latDelta, $lat + $latDelta, $lon - $lonDelta, $lon + $lonDelta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $stop) {
            $stopId = $stop['stop_id'] ?? null;
            if (!$stopId || isset($seen[$stopId])) {
                continue;
            }

            $distance = haversineDistance($lat, $lon, (float)$stop['stop_lat'], (float)$stop['stop_lon']);
            if ($distance > $radiusMeters + 25) {
                continue;
            }

            $seen[$stopId] = true;
            $results[] = [
                'stop_id' => $stop['stop_id'],
                'stop_name' => $stop['stop_name'],
                'stop_lat' => $stop['stop_lat'],
                'stop_lon' => $stop['stop_lon'],
                'walk_distance' => (int)round($distance),
                'walk_time' => calculateWalkTime($distance),
                'radius' => $radiusMeters,
            ];
        }

        usort($results, fn($a, $b) => ($a['walk_distance'] ?? PHP_INT_MAX) <=> ($b['walk_distance'] ?? PHP_INT_MAX));
        if (count($results) >= $maxTotal) {
            break;
        }

        if (!empty($rows)) {
            $results = array_slice($results, 0, max($maxTotal, $perRadius * 3));
        }
    }

    usort($results, fn($a, $b) => ($a['walk_distance'] ?? PHP_INT_MAX) <=> ($b['walk_distance'] ?? PHP_INT_MAX));
    return array_slice($results, 0, $maxTotal);
}

// ==================== FUNÇÃO PARA ENCONTRAR STOP COM ROTAS ====================
function findStopWithRoutes($lat, $lon, $targetStopId, $conn) {
    $stopModel = new StopModel();
    
    // Raio crescente: 100m, 200m, 500m, 1km, 2km, 5km, 11km
    $radii = [0.001, 0.002, 0.005, 0.01, 0.02, 0.05, 0.1];
    
    foreach ($radii as $delta) {
        // Buscar stops neste raio
        $sql = "
            SELECT stop_id, stop_name, stop_lat, stop_lon,
                   (ABS(stop_lat - ?) + ABS(stop_lon - ?)) as distance
            FROM stops
            WHERE stop_lat BETWEEN ? AND ?
            AND stop_lon BETWEEN ? AND ?
            ORDER BY distance
            LIMIT 20
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $lat, $lon,
            $lat - $delta, $lat + $delta,
            $lon - $delta, $lon + $delta
        ]);
        $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stops as $stop) {
            // Verificar se este stop tem rotas para o destino
            $sql2 = "
                SELECT COUNT(DISTINCT st1.trip_id) as total_trips
                FROM stop_times st1
                JOIN stop_times st2 ON st1.trip_id = st2.trip_id
                WHERE st1.stop_id = ?
                AND st2.stop_id = ?
                AND st2.stop_sequence > st1.stop_sequence
                LIMIT 1
            ";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->execute([$stop['stop_id'], $targetStopId]);
            $result = $stmt2->fetch();
            
            if ($result['total_trips'] > 0) {
                $distance = haversineDistance($lat, $lon, $stop['stop_lat'], $stop['stop_lon']);
                return [
                    "stop" => $stop,
                    "walk_distance" => round($distance),
                    "walk_time" => calculateWalkTime($distance)
                ];
            }
        }
    }
    
    return null;
}

try {
    routeMarkTiming('start');

    // ==================== ENDPOINT PARA ENCONTRAR STOP MAIS PRÓXIMO COM ROTAS ====================
    if (isset($_GET['findNearestStop']) && $_GET['findNearestStop'] == 1) {
        $lat = $_GET['lat'] ?? null;
        $lon = $_GET['lon'] ?? null;
        $targetStopId = $_GET['targetStopId'] ?? null;
        
        if (!$lat || !$lon) {
            echo json_encode(["status" => "error", "message" => "Coordenadas em falta"]);
            exit;
        }
        
        // Se temos um destino alvo, encontrar stop com rotas
        if ($targetStopId) {
            $result = findStopWithRoutes($lat, $lon, $targetStopId, $conn);
            
            if ($result) {
                echo json_encode([
                    "status" => "success",
                    "stop" => $result['stop'],
                    "walk_distance" => $result['walk_distance'],
                    "walk_time" => $result['walk_time']
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Nenhuma paragem encontrada com rotas para o destino"
                ]);
            }
        } else {
            // Fallback: stop mais próximo sem verificar rotas
            $stopModel = new StopModel();
            $nearestStop = $stopModel->findNearestStop($lat, $lon);
            
            if ($nearestStop) {
                $distance = haversineDistance($lat, $lon, $nearestStop['stop_lat'], $nearestStop['stop_lon']);
                echo json_encode([
                    "status" => "success",
                    "stop" => $nearestStop,
                    "walk_distance" => round($distance),
                    "walk_time" => calculateWalkTime($distance)
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Nenhuma paragem encontrada"]);
            }
        }
        exit;
    }
    
    // ==================== ENDPOINT PRINCIPAL DE ROTAS ====================
    
    $origin = normalizeTextParam($_GET['origin'] ?? null, 120);
    $dest = normalizeTextParam($_GET['dest'] ?? null, 120);
    $fromLat = normalizeCoordinateParam($_GET['fromLat'] ?? null, -90, 90);
    $fromLon = normalizeCoordinateParam($_GET['fromLon'] ?? null, -180, 180);
    $toLat = normalizeCoordinateParam($_GET['toLat'] ?? null, -90, 90);
    $toLon = normalizeCoordinateParam($_GET['toLon'] ?? null, -180, 180);
    $departureTime = normalizeDepartureTimeParam($_GET['departureTime'] ?? $_GET['departure_time'] ?? null);
    $travelDate = normalizeTravelDateParam($_GET['travelDate'] ?? $_GET['travel_date'] ?? null) ?? date('Y-m-d');
    $debugEnabled = routeIsDevelopment() && isset($_GET['debug']) && $_GET['debug'] == '1';

    if ((isset($_GET['departureTime']) || isset($_GET['departure_time'])) && $departureTime === null) {
        routeRespondError("Hora de partida inválida. Use o formato HH:MM.", "invalid_params");
    }

    if ((isset($_GET['travelDate']) || isset($_GET['travel_date'])) && normalizeTravelDateParam($_GET['travelDate'] ?? $_GET['travel_date'] ?? null) === null) {
        routeRespondError("Data da viagem inválida. Use o formato YYYY-MM-DD.", "invalid_params");
    }
    
    // Inicializar cache de rotas
    $routeCache = new RouteCacheService();
    routeMarkTiming('cache_initialized');
    
    // Tentar obter do cache primeiro
    $cacheEligibleDate = $travelDate === date('Y-m-d');
    if (!$debugEnabled && $cacheEligibleDate && $fromLat && $fromLon && $toLat && $toLon) {
        $cachedRoute = $routeCache->getRoute($fromLat, $fromLon, $toLat, $toLon, $departureTime);
        if ($cachedRoute) {
            routeMarkTiming('cache_hit');
            $payload = [
                "status" => "success",
                "message" => "Rotas obtidas do cache.",
                "cached" => true,
                "timestamp" => time(),
                "routes" => $cachedRoute['routes'],
                "origin" => $cachedRoute['origin'],
                "destination" => $cachedRoute['destination'],
                "walk_info" => $cachedRoute['walk_info'] ?? null,
                "travel_date" => $travelDate,
            ];
            if ($debugEnabled) {
                $payload["debug"] = ["timings_ms" => $routeTimings];
            }
            echo json_encode($payload);
            exit;
        }
    }
    routeMarkTiming('cache_checked');
    
    $originalFromLat = $fromLat;
    $originalFromLon = $fromLon;
    $originalToLat = $toLat;
    $originalToLon = $toLon;

    if ($origin && $dest && !$fromLat) {
        $stopModel = new StopModel();
        $fromStop = $stopModel->findStopByName($origin);
        $toStop = $stopModel->findStopByName($dest);
        
        if ($fromStop && $toStop) {
            $fromLat = $fromStop['stop_lat'];
            $fromLon = $fromStop['stop_lon'];
            $toLat = $toStop['stop_lat'];
            $toLon = $toStop['stop_lon'];
            $originalFromLat = $fromLat;
            $originalFromLon = $fromLon;
            $originalToLat = $toLat;
            $originalToLon = $toLon;
        }
    }

    if ($fromLat === null || $fromLon === null || $toLat === null || $toLon === null) {
        routeRespondError("Coordenadas inválidas ou em falta. Use fromLat, fromLon, toLat, toLon.", "invalid_params");
    }

    // Procurar vários candidatos de origem/destino em raio progressivo.
    $stopModel = new StopModel();
    $originCandidates = findNearbyStopsProgressive((float)$fromLat, (float)$fromLon, $conn);
    $destinationCandidates = findNearbyStopsProgressive((float)$toLat, (float)$toLon, $conn);
    routeMarkTiming('candidate_stops_found');

    if ($debugEnabled) {
        routeLog('Candidatos iniciais', [
            'from' => ['lat' => $fromLat, 'lon' => $fromLon],
            'to' => ['lat' => $toLat, 'lon' => $toLon],
            'origin_candidates' => array_slice($originCandidates, 0, 8),
            'destination_candidates' => array_slice($destinationCandidates, 0, 8),
        ]);
    }

    if (empty($originCandidates)) {
        $nearestOrigin = $stopModel->findNearestStop($fromLat, $fromLon);
        if ($nearestOrigin) {
            $originCandidates[] = [
                'stop_id' => $nearestOrigin['stop_id'],
                'stop_name' => $nearestOrigin['stop_name'],
                'stop_lat' => $nearestOrigin['stop_lat'],
                'stop_lon' => $nearestOrigin['stop_lon'],
                'walk_distance' => (int)round(haversineDistance($fromLat, $fromLon, $nearestOrigin['stop_lat'], $nearestOrigin['stop_lon'])),
                'walk_time' => calculateWalkTime(haversineDistance($fromLat, $fromLon, $nearestOrigin['stop_lat'], $nearestOrigin['stop_lon'])),
                'radius' => 2000,
            ];
        }
    }

    if (empty($destinationCandidates)) {
        $nearestDestination = $stopModel->findNearestStop($toLat, $toLon);
        if ($nearestDestination) {
            $destinationCandidates[] = [
                'stop_id' => $nearestDestination['stop_id'],
                'stop_name' => $nearestDestination['stop_name'],
                'stop_lat' => $nearestDestination['stop_lat'],
                'stop_lon' => $nearestDestination['stop_lon'],
                'walk_distance' => (int)round(haversineDistance($toLat, $toLon, $nearestDestination['stop_lat'], $nearestDestination['stop_lon'])),
                'walk_time' => calculateWalkTime(haversineDistance($toLat, $toLon, $nearestDestination['stop_lat'], $nearestDestination['stop_lon'])),
                'radius' => 2000,
            ];
        }
    }

    if (empty($originCandidates) || empty($destinationCandidates)) {
        routeRespondError("Não foram encontradas paragens próximas suficientes para calcular a rota.", "no_route");
    }

    if ($originalFromLat === null || $originalFromLon === null) {
        $originalFromLat = $fromLat;
        $originalFromLon = $fromLon;
    }

    if ($originalToLat === null || $originalToLon === null) {
        $originalToLat = $toLat;
        $originalToLon = $toLon;
    }

    $gtfsService = new GtfsRouteService($conn);
    $candidatePairs = [];
    $pairDebug = [];

    foreach (array_slice($originCandidates, 0, 8) as $originCandidate) {
        foreach (array_slice($destinationCandidates, 0, 8) as $destinationCandidate) {
            if (($originCandidate['stop_id'] ?? null) === ($destinationCandidate['stop_id'] ?? null)) {
                continue;
            }

            $candidatePairs[] = [
                'from' => $originCandidate,
                'to' => $destinationCandidate,
                'walk_score' => ($originCandidate['walk_distance'] ?? 0) + ($destinationCandidate['walk_distance'] ?? 0),
            ];
        }
    }

    usort($candidatePairs, fn($a, $b) => ($a['walk_score'] ?? PHP_INT_MAX) <=> ($b['walk_score'] ?? PHP_INT_MAX));
    $candidatePairs = array_slice($candidatePairs, 0, 12);

    $bestSelection = null;
    $bestScore = PHP_INT_MAX;
    $successfulPairs = 0;

    foreach ($candidatePairs as $pair) {
        if (routeHasTimedOut()) {
            routeLog('Timeout antes de concluir todos os pares candidatos', ['pairs_considered' => count($candidatePairs)]);
            break;
        }

        $candidateRoutes = $gtfsService->findRoutes($pair['from']['stop_id'], $pair['to']['stop_id'], $departureTime, $travelDate);
        if ($debugEnabled) {
            $pairDebug[] = [
                'from_stop_id' => $pair['from']['stop_id'],
                'from_stop_name' => $pair['from']['stop_name'],
                'to_stop_id' => $pair['to']['stop_id'],
                'to_stop_name' => $pair['to']['stop_name'],
                'walk_score' => $pair['walk_score'],
                'routes_found' => count($candidateRoutes),
                'service_debug' => $gtfsService->getLastDebugPayload(),
            ];
        }
        if (empty($candidateRoutes)) {
            continue;
        }

        $successfulPairs++;

        $walkScore = ($pair['from']['walk_time'] ?? 0) + ($pair['to']['walk_time'] ?? 0);
        $routeScore = ($candidateRoutes[0]['duration'] ?? PHP_INT_MAX) + $walkScore;

        if ($routeScore < $bestScore) {
            $bestScore = $routeScore;
            $bestSelection = [
                'fromStop' => $pair['from'],
                'toStop' => $pair['to'],
                'routes' => $candidateRoutes,
            ];
        }

        if ($successfulPairs >= 2 || $bestScore <= 40) {
            break;
        }
    }
    routeMarkTiming('gtfs_routes_found');

    if ($bestSelection) {
        $fromStop = $bestSelection['fromStop'];
        $toStop = $bestSelection['toStop'];
        $routes = $bestSelection['routes'];
        $walkFromDistance = (int)$fromStop['walk_distance'];
        $walkFromTime = (int)$fromStop['walk_time'];
        $walkToDistance = (int)$toStop['walk_distance'];
        $walkToTime = (int)$toStop['walk_time'];
    } else {
        $toStop = $destinationCandidates[0];
        $originResult = findStopWithRoutes($fromLat, $fromLon, $toStop['stop_id'], $conn);
        routeMarkTiming('origin_direct_stop_checked');

        if ($originResult) {
            $fromStop = $originResult['stop'];
            $walkFromDistance = $originResult['walk_distance'];
            $walkFromTime = $originResult['walk_time'];
        } else {
            $fromStop = $originCandidates[0];
            $walkFromDistance = (int)$fromStop['walk_distance'];
            $walkFromTime = (int)$fromStop['walk_time'];
        }

        $walkToDistance = (int)round(haversineDistance($originalToLat, $originalToLon, $toStop['stop_lat'], $toStop['stop_lon']));
        $walkToTime = calculateWalkTime($walkToDistance);
        $routes = $gtfsService->findRoutes($fromStop['stop_id'], $toStop['stop_id'], $departureTime, $travelDate);
    }

    routeLog('Paragens selecionadas', [
        'from_stop_id' => $fromStop['stop_id'],
        'from_stop_name' => $fromStop['stop_name'],
        'to_stop_id' => $toStop['stop_id'],
        'to_stop_name' => $toStop['stop_name'],
        'origin_candidates' => count($originCandidates),
        'destination_candidates' => count($destinationCandidates),
        'candidate_pairs_checked' => count($candidatePairs),
    ]);

    // Se mesmo assim não houver rotas, retornar erro informativo
    if (empty($routes)) {
        $serviceDebug = $gtfsService->getLastDebugPayload();
        $status = ($serviceDebug['status'] ?? null) === 'timeout_no_route' ? 'timeout' : 'no_route';
        routeRespondError(
            $status === 'timeout'
                ? "O cálculo da rota excedeu o tempo esperado. Tente novamente."
                : "Nenhuma rota encontrada para este percurso nesta data e horário.",
            $status,
            $status === 'timeout' ? 504 : 404,
            [
                "debug" => $debugEnabled ? [
                    "timings_ms" => $routeTimings,
                    "origin_candidates" => array_slice($originCandidates, 0, 8),
                    "destination_candidates" => array_slice($destinationCandidates, 0, 8),
                    "candidate_pairs" => $pairDebug,
                    "service_debug" => $serviceDebug,
                ] : null
            ]
        );
    }

    $walkFromGeometry = null;
    $walkToGeometry = null;

    if ($walkFromDistance > 0) {
        $walkFromGeometry = $gtfsService->buildWalkingGeometry(
            (float)$originalFromLat,
            (float)$originalFromLon,
            (float)$fromStop['stop_lat'],
            (float)$fromStop['stop_lon']
        );
    }

    if ($walkToDistance > 0) {
        $walkToGeometry = $gtfsService->buildWalkingGeometry(
            (float)$toStop['stop_lat'],
            (float)$toStop['stop_lon'],
            (float)$originalToLat,
            (float)$originalToLon
        );
    }

    $formattedRoutes = [];
    
    foreach ($routes as $route) {
        $totalWalkTime = $walkFromTime + $walkToTime;
        $adjustedDuration = $route['duration'] + $totalWalkTime;
        $segments = is_array($route['segments'] ?? null) ? $route['segments'] : [];

        if ($walkFromDistance > 0) {
            array_unshift($segments, [
                'type' => 'walk',
                'line' => null,
                'route_id' => null,
                'trip_id' => null,
                'shape_id' => null,
                'from_stop_id' => null,
                'to_stop_id' => $fromStop['stop_id'],
                'from_name' => 'Origem',
                'to_name' => $fromStop['stop_name'],
                'from_lat' => (float)$originalFromLat,
                'from_lon' => (float)$originalFromLon,
                'to_lat' => (float)$fromStop['stop_lat'],
                'to_lon' => (float)$fromStop['stop_lon'],
                'from' => [
                    'name' => 'Origem',
                    'latitude' => (float)$originalFromLat,
                    'longitude' => (float)$originalFromLon,
                    'stop_id' => null,
                ],
                'to' => [
                    'name' => $fromStop['stop_name'],
                    'latitude' => (float)$fromStop['stop_lat'],
                    'longitude' => (float)$fromStop['stop_lon'],
                    'stop_id' => $fromStop['stop_id'],
                ],
                'coordinates' => $walkFromGeometry['coordinates_lonlat'] ?? buildLinearCoordinates((float)$originalFromLat, (float)$originalFromLon, (float)$fromStop['stop_lat'], (float)$fromStop['stop_lon']),
                'geometry_source' => $walkFromGeometry['source'] ?? 'origin-stop',
                'geometry_quality' => $walkFromGeometry['geometry_quality'] ?? 'fallback_straight_line',
                'duration_minutes' => $walkFromTime,
                'time' => $walkFromTime,
                'stop_count' => 0,
                'stops' => 0,
                'wait' => null,
                'debug' => [
                    'from_stop_id' => null,
                    'to_stop_id' => $fromStop['stop_id'],
                    'shape_points_total' => 2,
                    'cut_points_count' => 2,
                    'geometry_points_returned' => 2,
                    'geometry_source' => $walkFromGeometry['source'] ?? 'origin-stop',
                    'geometry_quality' => $walkFromGeometry['geometry_quality'] ?? 'fallback_straight_line',
                ],
            ]);
        }

        if ($walkToDistance > 0) {
            $segments[] = [
                'type' => 'walk',
                'line' => null,
                'route_id' => null,
                'trip_id' => null,
                'shape_id' => null,
                'from_stop_id' => $toStop['stop_id'],
                'to_stop_id' => null,
                'from_name' => $toStop['stop_name'],
                'to_name' => 'Destino',
                'from_lat' => (float)$toStop['stop_lat'],
                'from_lon' => (float)$toStop['stop_lon'],
                'to_lat' => (float)$originalToLat,
                'to_lon' => (float)$originalToLon,
                'from' => [
                    'name' => $toStop['stop_name'],
                    'latitude' => (float)$toStop['stop_lat'],
                    'longitude' => (float)$toStop['stop_lon'],
                    'stop_id' => $toStop['stop_id'],
                ],
                'to' => [
                    'name' => 'Destino',
                    'latitude' => (float)$originalToLat,
                    'longitude' => (float)$originalToLon,
                    'stop_id' => null,
                ],
                'coordinates' => $walkToGeometry['coordinates_lonlat'] ?? buildLinearCoordinates((float)$toStop['stop_lat'], (float)$toStop['stop_lon'], (float)$originalToLat, (float)$originalToLon),
                'geometry_source' => $walkToGeometry['source'] ?? 'stop-destination',
                'geometry_quality' => $walkToGeometry['geometry_quality'] ?? 'fallback_straight_line',
                'duration_minutes' => $walkToTime,
                'time' => $walkToTime,
                'stop_count' => 0,
                'stops' => 0,
                'wait' => null,
                'debug' => [
                    'from_stop_id' => $toStop['stop_id'],
                    'to_stop_id' => null,
                    'shape_points_total' => 2,
                    'cut_points_count' => 2,
                    'geometry_points_returned' => 2,
                    'geometry_source' => $walkToGeometry['source'] ?? 'stop-destination',
                    'geometry_quality' => $walkToGeometry['geometry_quality'] ?? 'fallback_straight_line',
                ],
            ];
        }

        if ($walkFromDistance > 0) {
            $segments[0]['geometry'] = [
                'type' => 'LineString',
                'coordinates' => $segments[0]['coordinates'],
            ];
            $segments[0]['markers'] = [
                [
                    'kind' => 'origin',
                    'name' => 'Origem',
                    'coordinates' => [(float)$originalFromLon, (float)$originalFromLat],
                    'action' => 'Ponto de partida',
                    'line' => null,
                ],
                [
                    'kind' => 'board',
                    'name' => $fromStop['stop_name'],
                    'coordinates' => [(float)$fromStop['stop_lon'], (float)$fromStop['stop_lat']],
                    'action' => 'Seguir a pé até à paragem',
                    'line' => null,
                ],
            ];
            $segments[0]['debug']['geometry_points_returned'] = 2;
        }

        if ($walkToDistance > 0) {
            $lastSegmentIndex = count($segments) - 1;
            $segments[$lastSegmentIndex]['geometry'] = [
                'type' => 'LineString',
                'coordinates' => $segments[$lastSegmentIndex]['coordinates'],
            ];
            $segments[$lastSegmentIndex]['markers'] = [
                [
                    'kind' => 'alight',
                    'name' => $toStop['stop_name'],
                    'coordinates' => [(float)$toStop['stop_lon'], (float)$toStop['stop_lat']],
                    'action' => 'Início da caminhada final',
                    'line' => null,
                ],
                [
                    'kind' => 'destination',
                    'name' => 'Destino',
                    'coordinates' => [(float)$originalToLon, (float)$originalToLat],
                    'action' => 'Destino final',
                    'line' => null,
                ],
            ];
            $segments[$lastSegmentIndex]['debug']['geometry_points_returned'] = 2;
        }

        $segments = sanitizeSegmentMarkers($segments);
        $segments = sanitizeSegmentsForDebug($segments, $debugEnabled);
        $routeCoordinates = mergeSegmentCoordinates($segments, $route['coordinates'] ?? []);
        $bounds = buildBoundsFromSegments($segments, $routeCoordinates);
        
        $formattedRoutes[] = [
            "id" => $route['id'],
            "type" => in_array($route['type'], ['direct', 'Direto'], true) ? "Direto" : "Com transbordo",
            "badge" => $route['badge'],
            "line" => $route['line'],
            "route_id" => $route['route_id'] ?? null,
            "route_name" => $route['route_name'],
            "from" => $fromStop['stop_name'],
            "to" => $toStop['stop_name'],
            "duration" => $adjustedDuration,
            "bus_duration" => $route['duration'],
            "duration_text" => $adjustedDuration . " min",
            "stops" => $route['stops'],
            "stop_count" => $route['stop_count'],
            "transfers" => $route['transfers'] ?? 0,
            "coordinates" => $routeCoordinates,
            "stopsData" => $route['stopsData'],
            "segments" => $segments,
            "geometry_stats" => $route['geometry_stats'] ?? null,
            "bounds" => $bounds,
            "wait_time" => $route['wait_time'] ?? null,
            "transfer_stop" => $route['transfer_stop'] ?? null,
            "nextBus" => $route['nextBus'] ?? "Próximo horário não disponível",
            "departure_time" => $route['departure_time'] ?? null,
            "arrival_time" => $route['arrival_time'] ?? null,
            "delay" => $route['delay'] ?? 0,
            "delay_text" => $route['delay_text'] ?? "A horas",
            "primary_trip_id" => $route['primary_trip_id'] ?? $route['_primary_trip_id'] ?? null,
            "_primary_trip_id" => $route['_primary_trip_id'] ?? $route['primary_trip_id'] ?? null,
            "walk_from" => [
                "distance" => $walkFromDistance,
                "distance_text" => $walkFromDistance . " m",
                "time" => $walkFromTime,
                "time_text" => $walkFromTime . " min",
                "stop_name" => $fromStop['stop_name']
            ],
            "walk_to" => [
                "distance" => round($walkToDistance),
                "distance_text" => round($walkToDistance) . " m",
                "time" => $walkToTime,
                "time_text" => $walkToTime . " min",
                "stop_name" => $toStop['stop_name']
            ],
            "total_walk_time" => $totalWalkTime,
            "debug_enabled" => $debugEnabled
        ];
    }

    // Preparar dados para cache
    $cacheData = [
        'routes' => $formattedRoutes,
        'origin' => $fromStop,
        'destination' => $toStop,
        'walk_info' => [
            "from" => [
                "distance" => $walkFromDistance,
                "distance_text" => $walkFromDistance . " m",
                "time" => $walkFromTime,
                "stop" => $fromStop
            ],
            "to" => [
                "distance" => round($walkToDistance),
                "distance_text" => round($walkToDistance) . " m",
                "time" => $walkToTime,
                "stop" => $toStop
            ]
        ]
    ];
    
    // Guardar no cache para requests futuros
    if (!$debugEnabled && $cacheEligibleDate && $originalFromLat && $originalFromLon && $originalToLat && $originalToLon) {
        $routeCache->setRoute($originalFromLat, $originalFromLon, $originalToLat, $originalToLon, $cacheData, $departureTime);
    }

    $eventLogger = new AppEventService($conn);
    $eventLogger->log('route_calculated', [
        'origin_name' => $fromStop['stop_name'],
        'destination_name' => $toStop['stop_name'],
        'routes_found' => count($formattedRoutes),
        'first_route_name' => $formattedRoutes[0]['route_name'] ?? null,
        'from_stop_id' => $fromStop['stop_id'],
        'to_stop_id' => $toStop['stop_id'],
        'departure_time' => $departureTime,
        'travel_date' => $travelDate
    ], $eventLogger->resolveUserIdFromRequest(), 'success', 'route', $formattedRoutes[0]['route_name'] ?? null);

    routeMarkTiming('response_ready');
    routeLog('Pedido concluído', ['timings_ms' => $routeTimings, 'routes' => count($formattedRoutes)]);
    
    $responsePayload = [
        "status" => "success",
        "message" => count($formattedRoutes) > 0 ? "Rotas calculadas com sucesso." : "Nenhuma rota encontrada.",
        "cached" => false,
        "timestamp" => time(),
        "routes" => $formattedRoutes,
        "origin" => $fromStop,
        "destination" => $toStop,
        "travel_date" => $travelDate,
        "walk_info" => $cacheData['walk_info'],
    ];
    if ($debugEnabled) {
        $responsePayload["debug"] = [
            "timings_ms" => $routeTimings,
            "origin_candidates" => array_slice($originCandidates, 0, 8),
            "destination_candidates" => array_slice($destinationCandidates, 0, 8),
            "candidate_pairs" => $pairDebug,
        ];
    }
    echo json_encode($responsePayload, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    routeMarkTiming('failed');
    routeLog('Erro fatal', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timings_ms' => $routeTimings
    ]);
    routeRespondError(
        "Erro interno ao calcular rota.",
        "internal_error",
        500,
        [
            "debug" => $debugEnabled ?? false ? [
                "error" => $e->getMessage(),
                "timings_ms" => $routeTimings
            ] : null
        ]
    );
}
