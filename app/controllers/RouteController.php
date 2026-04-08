<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/GtfsRouteService.php';
require_once __DIR__ . '/../models/StopModel.php';

header('Content-Type: application/json');

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

// ==================== FUNÇÃO PARA ENCONTRAR STOP COM ROTAS ====================
function findStopWithRoutes($lat, $lon, $targetStopId, $conn) {
    $stopModel = new StopModel();
    
    // Raio crescente: 100m, 200m, 500m, 1km, 2km
    $radii = [0.001, 0.002, 0.005, 0.01, 0.02];
    
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
    
    $origin = $_GET['origin'] ?? null;
    $dest = $_GET['dest'] ?? null;
    $fromLat = $_GET['fromLat'] ?? null;
    $fromLon = $_GET['fromLon'] ?? null;
    $toLat = $_GET['toLat'] ?? null;
    $toLon = $_GET['toLon'] ?? null;
    
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
        }
    }

    if (!$fromLat || !$fromLon || !$toLat || !$toLon) {
        echo json_encode([
            "status" => "error",
            "message" => "Coordenadas em falta. Use fromLat, fromLon, toLat, toLon"
        ]);
        exit;
    }

    // Buscar stop de destino primeiro (para usar como alvo)
    $stopModel = new StopModel();
    $toStop = $stopModel->findNearestStop($toLat, $toLon);
    
    if (!$toStop) {
        echo json_encode(["status" => "error", "message" => "Não foram encontradas paragens próximas ao destino"]);
        exit;
    }
    
    // Buscar stop de origem que tenha rotas para o destino
    $originResult = findStopWithRoutes($fromLat, $fromLon, $toStop['stop_id'], $conn);
    
    if (!$originResult) {
        echo json_encode(["status" => "error", "message" => "Não foram encontradas rotas a partir das paragens próximas"]);
        exit;
    }
    
    $fromStop = $originResult['stop'];
    $walkFromDistance = $originResult['walk_distance'];
    $walkFromTime = $originResult['walk_time'];
    
    // Calcular caminhada do destino
    $walkToDistance = haversineDistance($originalToLat, $originalToLon, $toStop['stop_lat'], $toStop['stop_lon']);
    $walkToTime = calculateWalkTime($walkToDistance);

    // Usar GTFS SERVICE
    $gtfsService = new GtfsRouteService($conn);
    $routes = $gtfsService->findRoutes($fromStop['stop_id'], $toStop['stop_id']);

    $formattedRoutes = [];
    
    foreach ($routes as $route) {
        $totalWalkTime = $walkFromTime + $walkToTime;
        $adjustedDuration = $route['duration'] + $totalWalkTime;
        
        $formattedRoutes[] = [
            "id" => $route['id'],
            "type" => $route['type'] === 'direct' ? "Direto" : "Com transbordo",
            "badge" => $route['badge'],
            "line" => $route['line'],
            "route_name" => $route['route_name'],
            "from" => $fromStop['stop_name'],
            "to" => $toStop['stop_name'],
            "duration" => $adjustedDuration,
            "bus_duration" => $route['duration'],
            "duration_text" => $adjustedDuration . " min",
            "stops" => $route['stops'],
            "stop_count" => $route['stop_count'],
            "transfers" => $route['transfers'] ?? 0,
            "coordinates" => $route['coordinates'],
            "stopsData" => $route['stopsData'],
            "segments" => $route['segments'],
            "wait_time" => $route['wait_time'] ?? null,
            "transfer_stop" => $route['transfer_stop'] ?? null,
            "nextBus" => rand(3, 12) . " min",
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
            "total_walk_time" => $totalWalkTime
        ];
    }

    echo json_encode([
        "status" => "success",
        "routes" => $formattedRoutes,
        "origin" => $fromStop,
        "destination" => $toStop,
        "walk_info" => [
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
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro em RouteController: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Erro interno: " . $e->getMessage()
    ]);
}