<?php

require_once __DIR__ . '/GtfsRealtimeService.php';

class GtfsRouteService {

    private $pdo;
    private $stopsCache = [];
    private $realtimeService;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->realtimeService = new GtfsRealtimeService();
    }

    public function findRoutes($fromStopId, $toStopId) {
        $fromCoords = $this->getStopCoords($fromStopId);
        $toCoords   = $this->getStopCoords($toStopId);
        if (!$fromCoords || !$toCoords) return [];

        $direct = $this->findDirectRoutes($fromCoords, $toCoords);
        $transfer = $this->findTransferRoutes($fromCoords, $toCoords);
        $allRoutes = array_merge($direct, $transfer);
        if (empty($allRoutes)) return [];

        return $this->selectBestOptions($allRoutes);
    }

    // ==================== BUSCAR SERVICE IDs ATIVOS HOJE ====================
    private function getActiveServiceIdsForToday() {
        $today = date('Y-m-d');
        
        $stmt = $this->pdo->prepare("
            SELECT service_id FROM calendar_dates 
            WHERE date = ? AND exception_type = 1
        ");
        $stmt->execute([$today]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function findDirectRoutes($fromCoords, $toCoords) {
        $nearbyFrom = $this->getNearbyStops($fromCoords['stop_lat'], $fromCoords['stop_lon'], 15);
        $nearbyTo   = $this->getNearbyStops($toCoords['stop_lat'], $toCoords['stop_lon'], 15);
        if (empty($nearbyFrom) || empty($nearbyTo)) return [];

        $pf = implode(',', array_fill(0, count($nearbyFrom), '?'));
        $pt = implode(',', array_fill(0, count($nearbyTo), '?'));

        $params = array_merge($nearbyFrom, $nearbyTo);
        
        // Buscar service_ids ativos hoje
        $activeServiceIds = $this->getActiveServiceIdsForToday();
        $serviceFilter = '';
        if (!empty($activeServiceIds)) {
            $servicePlaceholders = implode(',', array_fill(0, count($activeServiceIds), '?'));
            $serviceFilter = " AND t.service_id IN ($servicePlaceholders)";
            $params = array_merge($params, $activeServiceIds);
        }

        $sql = "
            SELECT DISTINCT st1.trip_id, t.shape_id, r.route_short_name, r.route_id
            FROM stop_times st1
            JOIN trips t ON st1.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            WHERE st1.stop_id IN ($pf)
            AND EXISTS (
                SELECT 1 FROM stop_times st2
                WHERE st2.trip_id = st1.trip_id
                AND st2.stop_id IN ($pt)
            )
            $serviceFilter
            LIMIT 50
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $routes = [];

        foreach ($results as $row) {
            $stops = $this->getStopsFromTripCached($row['trip_id']);
            if (empty($stops)) continue;

            $fromIndexes = [];
            $toIndexes = [];

            foreach ($stops as $i => $stop) {
                if (in_array($stop['stop_id'], $nearbyFrom)) $fromIndexes[] = $i;
                if (in_array($stop['stop_id'], $nearbyTo)) $toIndexes[] = $i;
            }

            $bestFromIndex = -1;
            $bestToIndex = -1;

            foreach ($fromIndexes as $i) {
                foreach ($toIndexes as $j) {
                    if ($j > $i) {
                        $bestFromIndex = $i;
                        $bestToIndex = $j;
                        break 2;
                    }
                }
            }

            if ($bestFromIndex === -1 || $bestToIndex === -1) {
                if (!empty($fromIndexes) && !empty($toIndexes)) {
                    $bestFromIndex = min($fromIndexes[0], $toIndexes[0]);
                    $bestToIndex = max($fromIndexes[0], $toIndexes[0]);
                }
            }

            if ($bestFromIndex >= 0 && $bestToIndex >= 0 && $bestFromIndex != $bestToIndex) {
                if ($bestFromIndex < $bestToIndex) {
                    $segment = array_slice($stops, $bestFromIndex, $bestToIndex - $bestFromIndex + 1);
                } else {
                    $segment = array_slice($stops, $bestToIndex, $bestFromIndex - $bestToIndex + 1);
                    $segment = array_reverse($segment);
                }

                $totalTime = $this->calculateTravelTime($segment);
                
                $fullShapeCoords = $this->buildCoordinates($row['shape_id'], $stops);
                $coordinates = $this->getShapeSegment($fullShapeCoords, $segment);

                $routes[] = [
                    "type" => "direct",
                    "trip_id" => $row['trip_id'],
                    "route_id" => $row['route_id'],
                    "route_name" => $row['route_short_name'],
                    "coordinates" => $coordinates,
                    "stopsData" => $segment,
                    "total_time" => $totalTime,
                    "stop_count" => count($segment),
                    "transfers" => 0,
                    "first_stop" => $segment[0]['stop_name'],
                    "last_stop" => end($segment)['stop_name']
                ];
            }
        }
        return $routes;
    }

    private function findTransferRoutes($fromCoords, $toCoords) {
        $nearbyFrom = $this->getNearbyStops($fromCoords['stop_lat'], $fromCoords['stop_lon'], 8);
        $nearbyTo   = $this->getNearbyStops($toCoords['stop_lat'], $toCoords['stop_lon'], 8);
        if (empty($nearbyFrom) || empty($nearbyTo)) return [];

        $pf = implode(',', array_fill(0, count($nearbyFrom), '?'));
        $pt = implode(',', array_fill(0, count($nearbyTo), '?'));

        $params = array_merge($nearbyFrom, $nearbyTo);
        
        $activeServiceIds = $this->getActiveServiceIdsForToday();
        $serviceFilter = '';
        if (!empty($activeServiceIds)) {
            $servicePlaceholders = implode(',', array_fill(0, count($activeServiceIds), '?'));
            $serviceFilter = " AND t1.service_id IN ($servicePlaceholders) AND t2.service_id IN ($servicePlaceholders)";
            $params = array_merge($params, $activeServiceIds, $activeServiceIds);
        }

        $sql = "
            SELECT DISTINCT
                st1.trip_id AS trip1,
                st2.trip_id AS trip2,
                st1.stop_id AS transfer_stop,
                s.stop_name AS transfer_name,
                s.stop_lat AS transfer_lat,
                s.stop_lon AS transfer_lon,
                r1.route_short_name AS route1,
                r2.route_short_name AS route2,
                st1.departure_time AS time1,
                st2.arrival_time AS time2
            FROM stop_times st1
            JOIN stops s ON st1.stop_id = s.stop_id
            JOIN trips t1 ON st1.trip_id = t1.trip_id
            JOIN routes r1 ON t1.route_id = r1.route_id
            JOIN stop_times st2 ON st1.stop_id = st2.stop_id
            JOIN trips t2 ON st2.trip_id = t2.trip_id
            JOIN routes r2 ON t2.route_id = r2.route_id
            WHERE st1.stop_id IN ($pf)
            AND st2.stop_id IN ($pt)
            AND st1.trip_id != st2.trip_id
            AND r1.route_id != r2.route_id
            AND st2.arrival_time > st1.departure_time
            $serviceFilter
            LIMIT 30
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $routes = [];
        $seenCombinations = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $comboKey = $row['route1'] . '|' . $row['route2'];
            if (isset($seenCombinations[$comboKey])) continue;
            if (!$this->isValidTransfer($row['time1'], $row['time2'])) continue;
            
            $stops1 = $this->getStopsFromTripCached($row['trip1']);
            $stops2 = $this->getStopsFromTripCached($row['trip2']);
            if (empty($stops1) || empty($stops2)) continue;

            $transferStopId = $row['transfer_stop'];
            $pos1 = $this->findStopPosition($stops1, $transferStopId);
            $pos2 = $this->findStopPosition($stops2, $transferStopId);
            if ($pos1 === false || $pos2 === false) continue;
            
            $firstSegment = array_slice($stops1, 0, $pos1 + 1);
            $secondSegment = array_slice($stops2, $pos2);
            if (empty($firstSegment) || empty($secondSegment)) continue;
            
            $time1 = $this->calculateTravelTime($firstSegment);
            $time2 = $this->calculateTravelTime($secondSegment);
            $waitTime = $this->calculateWaitTime($row['time1'], $row['time2']);
            $totalTime = $time1 + $waitTime + $time2;
            $routeName = $row['route1'] . " → " . $row['route2'];
            
            $coords1 = $this->buildCoordinates(null, $firstSegment);
            $coords2 = $this->buildCoordinates(null, $secondSegment);
            
            $routes[] = [
                "type" => "transfer",
                "trip_id" => $row['trip1'] . "_" . $row['trip2'],
                "route_id" => $row['route1'] . "_" . $row['route2'],
                "route_name" => $routeName,
                "transfer_stop" => $row['transfer_name'],
                "transfer_stop_id" => $transferStopId,
                "wait_time" => $waitTime,
                "coordinates" => array_merge($coords1, $coords2),
                "stopsData" => array_merge($firstSegment, $secondSegment),
                "total_time" => $totalTime,
                "stop_count" => count($firstSegment) + count($secondSegment),
                "transfers" => 1,
                "segments" => [
                    [
                        "line" => $row['route1'],
                        "from" => $stops1[0]['stop_name'],
                        "to" => $row['transfer_name'],
                        "time" => $time1,
                        "stops" => count($firstSegment)
                    ],
                    [
                        "line" => $row['route2'],
                        "from" => $row['transfer_name'],
                        "to" => end($stops2)['stop_name'],
                        "time" => $time2,
                        "stops" => count($secondSegment),
                        "wait" => $waitTime
                    ]
                ]
            ];
            $seenCombinations[$comboKey] = true;
        }

        usort($routes, fn($a, $b) => $a['total_time'] <=> $b['total_time']);
        return array_slice($routes, 0, 10);
    }

    private function selectBestOptions($routes) {
        if (empty($routes)) return [];

        $direct = array_values(array_filter($routes, fn($r) => $r['type'] === 'direct'));
        $transfer = array_values(array_filter($routes, fn($r) => $r['type'] === 'transfer'));

        usort($direct, fn($a, $b) => $a['total_time'] <=> $b['total_time']);
        usort($transfer, fn($a, $b) => $a['total_time'] <=> $b['total_time']);

        $selected = [];
        $usedRouteIds = [];

        if (!empty($direct)) {
            $selected[] = $direct[0];
            $usedRouteIds[] = $direct[0]['route_id'];
            $bestTime = $direct[0]['total_time'];
        } else if (!empty($transfer)) {
            $selected[] = $transfer[0];
            $usedRouteIds[] = $transfer[0]['route_id'];
            $bestTime = $transfer[0]['total_time'];
        } else {
            return [];
        }

        if (count($direct) > 1) {
            foreach ($direct as $route) {
                if (!in_array($route['route_id'], $usedRouteIds) && $route['total_time'] <= $bestTime * 1.5) {
                    $selected[] = $route;
                    $usedRouteIds[] = $route['route_id'];
                    break;
                }
            }
        }

        if (count($selected) < 3 && !empty($transfer)) {
            foreach ($transfer as $route) {
                if (!in_array($route['route_id'], $usedRouteIds) && $route['total_time'] <= $bestTime * 1.5) {
                    $selected[] = $route;
                    break;
                }
            }
        }

        if (count($selected) < 3 && count($direct) > 1) {
            foreach ($direct as $route) {
                if (!in_array($route['route_id'], $usedRouteIds)) {
                    $selected[] = $route;
                    break;
                }
            }
        }

        $badges = [
            0 => "⚡ Mais rápida",
            1 => "🚌 Alternativa",
            2 => "🔄 Com transbordo"
        ];

        $result = [];
        foreach ($selected as $i => $route) {
            $result[] = $this->formatRoute($route, $i, $badges[$i] ?? "Alternativa");
        }
        return $result;
    }

    private function formatRoute($route, $index, $badge) {
    $lastStop = $route['stopsData'][count($route['stopsData']) - 1];
    
    $delay = 0;
    $delayText = "A horas";
    $estimatedTime = $route['total_time'];
    $vehicle = null;
    
    // ✅ BUSCAR PRÓXIMO HORÁRIO REAL
    $firstStopId = $route['stopsData'][0]['stop_id'];
    $nextBus = $this->getNextScheduledBus($route['trip_id'], $firstStopId);
    
    try {
        $tripUpdates = $this->realtimeService->getTripUpdates();
        $tripUpdate = $tripUpdates[$route['trip_id']] ?? null;
        if ($tripUpdate) {
            $delay = $tripUpdate['delay'] ?? 0;
            $delayText = $tripUpdate['delay_text'] ?? "A horas";
            $estimatedTime = max(1, $route['total_time'] + round($delay / 60));
            
            // ✅ Se houver atraso, ajustar o próximo horário
            if ($delay > 0 && $nextBus !== "Próximo horário não disponível") {
                $minutes = intval($nextBus);
                $newMinutes = $minutes + round($delay / 60);
                $nextBus = max(1, $newMinutes) . " min";
            }
        }
        $vehicle = $this->realtimeService->getVehiclePosition($route['trip_id']);
    } catch (Exception $e) {
        error_log("Erro ao buscar tempo real: " . $e->getMessage());
    }
    
    return [
        "id" => $index + 1,
        "type" => $route['type'] === 'direct' ? "Direto" : "Com transbordo",
        "badge" => $badge,
        "trip_id" => $route['trip_id'],
        "route_name" => $route['route_name'],
        "line" => $route['route_name'],
        "duration" => $estimatedTime,
        "scheduled_duration" => $route['total_time'],
        "duration_text" => $estimatedTime . " min",
        "delay" => $delay,
        "delay_text" => $delayText,
        "stops" => $route['stop_count'],
        "stop_count" => $route['stop_count'],
        "transfers" => $route['transfers'] ?? 0,
        "coordinates" => $route['coordinates'],
        "stopsData" => $route['stopsData'],
        "segments" => $route['segments'] ?? [
            [
                "from" => $route['stopsData'][0]['stop_name'],
                "to" => $lastStop['stop_name'],
                "line" => $route['route_name'],
                "time" => $estimatedTime,
                "stops" => $route['stop_count']
            ]
        ],
        "vehicle_position" => $vehicle ? [
            "lat" => $vehicle['latitude'],
            "lon" => $vehicle['longitude'],
            "speed" => $vehicle['speed'],
            "occupancy" => $vehicle['occupancy']
        ] : null,
        "transfer_stop" => $route['transfer_stop'] ?? null,
        "wait_time" => $route['wait_time'] ?? null,
        "nextBus" => $nextBus  // ✅ ADICIONAR O PRÓXIMO HORÁRIO REAL
    ];
}
    // ==================== FUNÇÕES AUXILIARES ====================
    
    private function getShapeSegment($shapeCoords, $stops) {
        if (empty($shapeCoords) || empty($stops)) return $shapeCoords;
        
        $firstStop = $stops[0];
        $lastStop = end($stops);
        
        $firstIndex = 0;
        $lastIndex = count($shapeCoords) - 1;
        $minDistFirst = PHP_FLOAT_MAX;
        $minDistLast = PHP_FLOAT_MAX;
        
        foreach ($shapeCoords as $idx => $coord) {
            $distFirst = abs($coord[0] - $firstStop['stop_lat']) + abs($coord[1] - $firstStop['stop_lon']);
            if ($distFirst < $minDistFirst) {
                $minDistFirst = $distFirst;
                $firstIndex = $idx;
            }
            
            $distLast = abs($coord[0] - $lastStop['stop_lat']) + abs($coord[1] - $lastStop['stop_lon']);
            if ($distLast < $minDistLast) {
                $minDistLast = $distLast;
                $lastIndex = $idx;
            }
        }
        
        if ($firstIndex < $lastIndex) {
            return array_slice($shapeCoords, $firstIndex, $lastIndex - $firstIndex + 1);
        } else {
            return array_slice($shapeCoords, $lastIndex, $firstIndex - $lastIndex + 1);
        }
    }

    private function toMinutes($time) {
        $parts = explode(':', $time);
        return (intval($parts[0]) * 60) + intval($parts[1]);
    }

    private function isValidTransfer($time1, $time2) {
        return ($this->toMinutes($time2) - $this->toMinutes($time1)) >= 3;
    }

    private function calculateWaitTime($time1, $time2) {
        $wait = $this->toMinutes($time2) - $this->toMinutes($time1);
        return max(1, min(30, $wait));
    }

    private function findStopPosition($stops, $stopId) {
        foreach ($stops as $index => $stop) {
            if ($stop['stop_id'] == $stopId) return $index;
        }
        return false;
    }

    private function getStopsFromTripCached($tripId) {
        if (!isset($this->stopsCache[$tripId])) {
            $this->stopsCache[$tripId] = $this->getStopsFromTrip($tripId);
        }
        return $this->stopsCache[$tripId];
    }

    private function buildCoordinates($shapeId, $stops) {
        if (!$shapeId) {
            return array_map(fn($s) => [floatval($s['stop_lat']), floatval($s['stop_lon'])], $stops);
        }

        $stmt = $this->pdo->prepare("
            SELECT shape_pt_lat, shape_pt_lon
            FROM shapes
            WHERE shape_id = ?
            AND shape_pt_sequence % 5 = 0
            ORDER BY shape_pt_sequence
            LIMIT 500
        ");
        $stmt->execute([$shapeId]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($points)) {
            return array_map(fn($s) => [floatval($s['stop_lat']), floatval($s['stop_lon'])], $stops);
        }
        return array_map(fn($p) => [floatval($p['shape_pt_lat']), floatval($p['shape_pt_lon'])], $points);
    }

    private function getStopsFromTrip($tripId) {
        $stmt = $this->pdo->prepare("
            SELECT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon
            FROM stop_times st
            JOIN stops s ON st.stop_id = s.stop_id
            WHERE st.trip_id = ?
            ORDER BY st.stop_sequence
        ");
        $stmt->execute([$tripId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNearbyStops($lat, $lon, $limit = 10) {
        $delta = 0.05;
        $stmt = $this->pdo->prepare("
            SELECT stop_id
            FROM stops
            WHERE stop_lat BETWEEN ? AND ?
            AND stop_lon BETWEEN ? AND ?
            ORDER BY (ABS(stop_lat - ?) + ABS(stop_lon - ?))
            LIMIT $limit
        ");
        $stmt->execute([$lat - $delta, $lat + $delta, $lon - $delta, $lon + $delta, $lat, $lon]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getStopCoords($stopId) {
        $stmt = $this->pdo->prepare("
            SELECT stop_lat, stop_lon
            FROM stops
            WHERE stop_id = ?
            LIMIT 1
        ");
        $stmt->execute([$stopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function calculateTravelTime($stops) {
        if (count($stops) < 2) return 5;
        $total = 0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $dist = $this->haversine($stops[$i]['stop_lat'], $stops[$i]['stop_lon'], $stops[$i+1]['stop_lat'], $stops[$i+1]['stop_lon']);
            $total += max(1, ($dist * 3) + 0.5);
        }
        return round($total);
    }
    
    private function haversine($lat1, $lon1, $lat2, $lon2) {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
    private function getNextScheduledBus($tripId, $stopId) {
    $now = date('H:i:s');
    
    $stmt = $this->pdo->prepare("
        SELECT departure_time 
        FROM stop_times 
        WHERE trip_id = ? 
        AND stop_id = ?
        AND departure_time > ?
        ORDER BY departure_time
        LIMIT 1
    ");
    $stmt->execute([$tripId, $stopId, $now]);
    $next = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($next) {
        $departure = strtotime($next['departure_time']);
        $nowTime = strtotime($now);
        $waitMinutes = round(($departure - $nowTime) / 60);
        return max(1, $waitMinutes) . " min";
    }
    
    return "Próximo horário não disponível";
}
}