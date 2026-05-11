<?php
// /urban/app/services/GtfsRealtimeService.php

require_once __DIR__ . '/AppEventService.php';

class GtfsRealtimeService {
    
    // ✅ URLs da API oficial da Carris Metropolitana (JSON - mais estável)
    private $baseUrl = 'https://api.carrismetropolitana.pt/v2';
    private $vehiclesUrl = '/vehicles';
    private $alertsUrl = '/alerts';
    private $arrivalsUrl = '/arrivals/by_stop/';
    
    private $cache = [];
    private $pdo;
    private $eventLogger;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo;
        $this->eventLogger = new AppEventService($pdo);
    }
    
    /**
     * Verificar se a extensão protobuf está disponível
     */
    public function isProtobufAvailable() {
        return extension_loaded('protobuf');
    }
    
    /**
     * Buscar posições em tempo real dos veículos (Google Maps style)
     */
    public function getVehiclePositions() {
        if (isset($this->cache['vehicles']) && (time() - $this->cache['vehicles_time']) < 10) {
            return $this->cache['vehicles'];
        }
        
        $vehicles = $this->fetchFromApi($this->vehiclesUrl);
        
        if ($vehicles && is_array($vehicles)) {
            $formatted = $this->formatVehicles($vehicles);
            $this->cache['vehicles'] = $formatted;
            $this->cache['vehicles_time'] = time();
            error_log("✅ API Carris: " . count($formatted) . " veículos em tempo real");
            return $formatted;
        }
        
        // Fallback para simulação
        return $this->getSimulatedVehicles();
    }
    
    /**
     * Buscar alertas em tempo real
     */
    public function getAlerts() {
        if (isset($this->cache['alerts']) && (time() - $this->cache['alerts_time']) < 60) {
            return $this->cache['alerts'];
        }
        
        $alerts = $this->fetchFromApi($this->alertsUrl);
        
        if ($alerts && is_array($alerts)) {
            $formatted = $this->formatAlerts($alerts);
            $this->cache['alerts'] = $formatted;
            $this->cache['alerts_time'] = time();
            return $formatted;
        }
        
        return [];
    }
    
    /**
     * Buscar chegadas em tempo real para uma paragem com tempos precisos
     */
    public function getStopRealtime($stopId) {
        $url = $this->arrivalsUrl . $stopId;
        $arrivals = $this->fetchFromApi($url);
        
        if (!$arrivals || !is_array($arrivals)) {
            return $this->getFallbackArrivals($stopId);
        }
        
        $result = [];
        foreach ($arrivals as $arrival) {
            // Calcular tempo de chegada em minutos
            $etaMinutes = $this->calculateETA($arrival);
            
            $result[] = [
                'trip_id' => $arrival['trip_id'] ?? null,
                'route' => $arrival['route_short_name'] ?? $arrival['line'] ?? 'N/A',
                'scheduled' => $this->formatTime($arrival['scheduled_arrival'] ?? null),
                'estimated' => $this->formatTime($arrival['estimated_arrival'] ?? null),
                'eta_minutes' => $etaMinutes,
                'eta_text' => $etaMinutes <= 0 ? 'Agora' : "Chega em {$etaMinutes} min",
                'delay' => $arrival['delay'] ?? 0,
                'delay_text' => $this->formatDelay($arrival['delay'] ?? 0),
                'vehicle_id' => $arrival['vehicle_id'] ?? null,
                'status' => $this->getStatus($arrival['delay'] ?? 0),
                'is_realtime' => true,
                'data_source' => 'realtime',
                'source' => 'realtime'
            ];
        }
        
        // Ordenar por tempo de chegada
        usort($result, function($a, $b) {
            if ($a['eta_minutes'] <= 0 && $b['eta_minutes'] > 0) return -1;
            if ($a['eta_minutes'] > 0 && $b['eta_minutes'] <= 0) return 1;
            return $a['eta_minutes'] - $b['eta_minutes'];
        });
        
        return $result;
    }
    
    /**
     * Calcular tempo estimado de chegada em minutos
     */
    private function calculateETA($arrival) {
        $now = time();
        
        // Se temos tempo estimado da API
        if (isset($arrival['estimated_arrival']) && $arrival['estimated_arrival']) {
            $estimatedTime = is_numeric($arrival['estimated_arrival']) 
                ? $arrival['estimated_arrival'] 
                : strtotime($arrival['estimated_arrival']);
            
            if ($estimatedTime) {
                return max(0, round(($estimatedTime - $now) / 60));
            }
        }
        
        // Se temos tempo programado
        if (isset($arrival['scheduled_arrival']) && $arrival['scheduled_arrival']) {
            $scheduledTime = is_numeric($arrival['scheduled_arrival']) 
                ? $arrival['scheduled_arrival'] 
                : strtotime($arrival['scheduled_arrival']);
            
            if ($scheduledTime) {
                $delay = $arrival['delay'] ?? 0;
                $adjustedTime = $scheduledTime + $delay;
                return max(0, round(($adjustedTime - $now) / 60));
            }
        }
        
        // Fallback: estimar baseado na hora atual
        $currentHour = (int)date('H');
        if ($currentHour >= 6 && $currentHour <= 23) {
            return rand(5, 30); // 5-30 minutos durante o dia
        } else {
            return rand(15, 45); // 15-45 minutos durante a noite
        }
    }
    
    /**
     * Buscar tempo de chegada para um trip_id específico
     */
    public function getTripETA($tripId, $stopId) {
        // Primeiro tentar buscar da API
        $arrivals = $this->getStopRealtime($stopId);
        
        foreach ($arrivals as $arrival) {
            if ($arrival['trip_id'] === $tripId) {
                return $arrival;
            }
        }
        
        // Fallback: calcular baseado no GTFS estático
        return $this->getScheduledTripETA($tripId, $stopId);
    }
    
    /**
     * Calcular ETA baseado no horário GTFS programado
     */
    private function getScheduledTripETA($tripId, $stopId) {
        if (!$this->pdo) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT st.arrival_time, st.departure_time, r.route_short_name
            FROM stop_times st
            JOIN trips t ON st.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            WHERE st.trip_id = ? AND st.stop_id = ?
            ORDER BY st.stop_sequence
            LIMIT 1
        ");
        $stmt->execute([$tripId, $stopId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        $scheduledTime = $this->gtfsTimeToSeconds($result['arrival_time'] ?? $result['departure_time']);
        $now = time();
        $currentSeconds = $this->gtfsTimeToSeconds(date('H:i:s'));
        
        // Ajustar para o dia seguinte se o horário já passou
        if ($scheduledTime < $currentSeconds) {
            $scheduledTime += 24 * 3600; // Adicionar 24 horas
        }
        
        $etaMinutes = round(($scheduledTime - $currentSeconds) / 60);
        
        return [
            'trip_id' => $tripId,
            'route' => $result['route_short_name'],
            'scheduled' => $this->formatClockForUi($result['arrival_time'] ?? $result['departure_time']),
            'estimated' => $this->formatClockForUi($result['arrival_time'] ?? $result['departure_time']),
            'eta_minutes' => max(0, $etaMinutes),
            'eta_text' => $etaMinutes <= 0 ? 'Agora' : "Chega em {$etaMinutes} min",
            'delay' => 0,
            'delay_text' => 'A horas',
            'vehicle_id' => null,
            'status' => 'A horas',
            'is_realtime' => false,
            'data_source' => 'scheduled',
            'source' => 'estimated'
        ];
    }
    
    /**
     * Converter tempo GTFS para segundos
     */
    private function gtfsTimeToSeconds($time) {
        if (!$time) return 0;
        
        $parts = explode(':', $time);
        $hours = (int)($parts[0] ?? 0);
        $minutes = (int)($parts[1] ?? 0);
        $seconds = (int)($parts[2] ?? 0);
        
        return $hours * 3600 + $minutes * 60 + $seconds;
    }
    
    /**
     * Formatar hora GTFS para UI
     */
    private function formatClockForUi($time) {
        if (!$time) return '--:--';
        
        $seconds = $this->gtfsTimeToSeconds($time);
        $hours = intdiv($seconds, 3600) % 24;
        $minutes = intdiv($seconds % 3600, 60);
        
        return sprintf('%02d:%02d', $hours, $minutes);
    }
    
    /**
     * Buscar veículos por rota
     */
    public function getVehiclesByRoute($routeId) {
        $vehicles = $this->getVehiclePositions();
        return array_filter($vehicles, function($v) use ($routeId) {
            return $v['route_id'] == $routeId || $v['route_name'] == $routeId;
        });
    }

    public function findBestVehicleForRoute(?string $tripId, ?string $routeId, ?string $routeName = null, ?float $lat = null, ?float $lon = null) {
        $vehicles = $this->getVehiclePositions();
        if (empty($vehicles)) {
            return null;
        }

        if ($tripId) {
            foreach ($vehicles as $vehicle) {
                if (($vehicle['trip_id'] ?? null) === $tripId) {
                    return $vehicle;
                }
            }
        }

        $filtered = array_values(array_filter($vehicles, function ($vehicle) use ($routeId, $routeName) {
            $vehicleRouteId = (string)($vehicle['route_id'] ?? '');
            $vehicleRouteName = (string)($vehicle['route_name'] ?? '');
            $expectedRouteId = (string)($routeId ?? '');
            $expectedRouteName = (string)($routeName ?? '');

            return ($expectedRouteId !== '' && ($vehicleRouteId === $expectedRouteId || $vehicleRouteName === $expectedRouteId))
                || ($expectedRouteName !== '' && ($vehicleRouteId === $expectedRouteName || $vehicleRouteName === $expectedRouteName));
        }));

        if (empty($filtered)) {
            return null;
        }

        if ($lat !== null && $lon !== null) {
            usort($filtered, function ($a, $b) use ($lat, $lon) {
                $distanceA = $this->calculateDistance($lat, $lon, (float)($a['latitude'] ?? 0), (float)($a['longitude'] ?? 0));
                $distanceB = $this->calculateDistance($lat, $lon, (float)($b['latitude'] ?? 0), (float)($b['longitude'] ?? 0));
                return $distanceA <=> $distanceB;
            });
        }

        return $filtered[0] ?? null;
    }
    
    /**
     * Buscar veículos próximos a uma localização (Google Maps style)
     */
    public function getNearbyVehicles($lat, $lon, $radiusMeters = 500) {
        $vehicles = $this->getVehiclePositions();
        $nearby = [];
        
        foreach ($vehicles as $vehicle) {
            $distance = $this->calculateDistance($lat, $lon, $vehicle['latitude'], $vehicle['longitude']);
            if ($distance <= $radiusMeters) {
                $vehicle['distance_meters'] = round($distance);
                $vehicle['distance_text'] = $distance < 1000 ? round($distance) . ' m' : round($distance/1000, 1) . ' km';
                $nearby[] = $vehicle;
            }
        }
        
        usort($nearby, fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);
        return $nearby;
    }
    
    /**
     * Buscar atualizações de viagem (atrasos em tempo real)
     */
    public function getTripUpdates() {
        if (isset($this->cache['trips']) && (time() - $this->cache['trips_time']) < 10) {
            return $this->cache['trips'];
        }
        
        // Simular atrasos baseado nos veículos em tempo real
        $vehicles = $this->getVehiclePositions();
        $updates = [];
        
        foreach ($vehicles as $vehicle) {
            if ($vehicle['trip_id']) {
                $delay = 0;
                if ($vehicle['speed'] < 5 && $vehicle['speed'] > 0) {
                    $delay = rand(60, 180); // Trânsito lento
                } elseif ($vehicle['speed'] == 0 && $vehicle['current_status'] == 'IN_TRANSIT_TO') {
                    $delay = rand(180, 600); // Parado no trânsito
                } else {
                    $delay = rand(-30, 60); // Normal
                }
                
                $updates[$vehicle['trip_id']] = [
                    'trip_id' => $vehicle['trip_id'],
                    'route_id' => $vehicle['route_id'],
                    'delay' => $delay,
                    'delay_text' => $this->formatDelay($delay),
                    'is_realtime' => false,
                    'data_source' => 'estimated_from_vehicle',
                    'source' => 'estimated'
                ];
            }
        }
        
        $this->cache['trips'] = $updates;
        $this->cache['trips_time'] = time();
        
        return $updates;
    }
    
    /**
     * Buscar posição de um veículo específico
     */
    public function getVehiclePosition($tripId) {
        $vehicles = $this->getVehiclePositions();
        foreach ($vehicles as $vehicle) {
            if ($vehicle['trip_id'] == $tripId) {
                return $vehicle;
            }
        }
        return null;
    }
    
    /**
     * Buscar paragens de uma rota (da base de dados GTFS)
     */
    public function getStopsByRoute($routeId) {
        if (!$this->pdo) return [];
        
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon
            FROM routes r
            JOIN trips t ON r.route_id = t.route_id
            JOIN stop_times st ON t.trip_id = st.trip_id
            JOIN stops s ON st.stop_id = s.stop_id
            WHERE r.route_id = ? OR r.route_short_name = ?
            ORDER BY st.stop_sequence
        ");
        $stmt->execute([$routeId, $routeId]);
        
        $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $uniqueStops = [];
        $seenIds = [];
        foreach ($stops as $stop) {
            if (!in_array($stop['stop_id'], $seenIds)) {
                $seenIds[] = $stop['stop_id'];
                $uniqueStops[] = $stop;
            }
        }
        
        error_log("✅ Rota $routeId: " . count($uniqueStops) . " paragens encontradas");
        return $uniqueStops;
    }
    
    /**
     * Resumo do sistema para o dashboard (Google Maps style)
     */
    public function getSystemSummary() {
        $vehicles = $this->getVehiclePositions();
        $alerts = $this->getAlerts();
        $source = 'fallback';
        if (!empty($vehicles)) {
            $source = $vehicles[0]['source'] ?? $this->normalizeSource($vehicles[0]['data_source'] ?? 'fallback');
        }
        
        $routes = [];
        foreach ($vehicles as $vehicle) {
            $routeId = $vehicle['route_name'] ?? $vehicle['route_id'];
            if ($routeId) {
                if (!isset($routes[$routeId])) {
                    $routes[$routeId] = 0;
                }
                $routes[$routeId]++;
            }
        }
        
        return [
            'vehicles' => [
                'total' => count($vehicles),
                'active' => count(array_filter($vehicles, fn($v) => $v['speed'] > 0)),
                'by_route' => $routes
            ],
            'alerts' => [
                'active' => count($alerts)
            ],
            'timestamp' => time(),
            'data_source' => $source === 'realtime' ? 'realtime' : ($source === 'simulated' ? 'simulated' : ($source === 'estimated' ? 'scheduled' : 'fallback')),
            'source' => $source
        ];
    }
    
    // ==================== MÉTODOS PRIVADOS ====================
    
    /**
     * Requisição à API oficial
     */
    private function fetchFromApi($endpoint) {
        $url = $this->baseUrl . $endpoint;

        if (!function_exists('curl_init')) {
            return $this->handleApiFailure($endpoint, 'Extensão cURL indisponível');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: UrbanTraffic/1.0'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error) {
            return $this->handleApiFailure($endpoint, $error ?: 'Resposta vazia');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return $this->handleApiFailure($endpoint, "HTTP $httpCode");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->handleApiFailure($endpoint, json_last_error_msg());
        }

        return $data;
    }

    private function handleApiFailure($endpoint, $error) {
        error_log("API Carris failure for $endpoint: $error");
        $this->eventLogger->logApiError('carris_metropolitana', $error, ['endpoint' => $endpoint]);
        
        // Tentar cache antigo se disponível
        $cacheKey = $this->getCacheKey($endpoint);
        if (isset($this->cache[$cacheKey])) {
            error_log("Using stale cache for $endpoint due to API failure");
            return $this->cache[$cacheKey];
        }
        
        // Fallback para dados simulados baseados no endpoint
        return $this->getFallbackData($endpoint);
    }
    
    /**
     * Obter chave de cache para endpoint
     */
    private function getCacheKey($endpoint) {
        switch ($endpoint) {
            case $this->vehiclesUrl:
                return 'vehicles';
            case $this->alertsUrl:
                return 'alerts';
            default:
                return 'fallback_' . md5($endpoint);
        }
    }
    
    /**
     * Dados fallback quando API falha completamente
     */
    private function getFallbackData($endpoint) {
        error_log("Using fallback data for $endpoint");
        
        switch ($endpoint) {
            case $this->vehiclesUrl:
                return $this->getFallbackVehicles();
            case $this->alertsUrl:
                return $this->getFallbackAlerts();
            default:
                return [];
        }
    }
    
    /**
     * Fallback para veículos quando API falha
     */
    private function getFallbackVehicles() {
        // Dados simulados realistas para Lisboa
        $fallbackVehicles = [
            [
                'id' => 'fallback_1',
                'trip_id' => 'CARRIS_1_0',
                'route_id' => '1',
                'line_id' => '1',
                'lat' => 38.7223,
                'lon' => -9.1393,
                'latitude' => 38.7223,
                'longitude' => -9.1393,
                'bearing' => 45,
                'speed' => 25,
                'timestamp' => time(),
                'current_status' => 'IN_TRANSIT_TO',
                '_data_source' => 'fallback'
            ],
            [
                'id' => 'fallback_2',
                'trip_id' => 'CARRIS_15_0',
                'route_id' => '15',
                'line_id' => '15',
                'lat' => 38.7369,
                'lon' => -9.1426,
                'latitude' => 38.7369,
                'longitude' => -9.1426,
                'bearing' => 180,
                'speed' => 30,
                'timestamp' => time(),
                'current_status' => 'IN_TRANSIT_TO',
                '_data_source' => 'fallback'
            ],
            [
                'id' => 'fallback_3',
                'trip_id' => 'CARRIS_28_0',
                'route_id' => '28',
                'line_id' => '28',
                'lat' => 38.7078,
                'lon' => -9.1364,
                'latitude' => 38.7078,
                'longitude' => -9.1364,
                'bearing' => 90,
                'speed' => 20,
                'timestamp' => time(),
                'current_status' => 'STOPPED_AT',
                '_data_source' => 'fallback'
            ]
        ];
        
        error_log("Generated " . count($fallbackVehicles) . " fallback vehicles");
        return $fallbackVehicles;
    }
    
    /**
     * Fallback para alertas quando API falha
     */
    private function getFallbackAlerts() {
        return [
            [
                'id' => 'fallback_alert_1',
                'header_text' => 'Serviço Normal',
                'description_text' => 'Todos os serviços a operar normalmente',
                'cause' => 'UNKNOWN_CAUSE',
                'effect' => 'UNKNOWN_EFFECT',
                'severity_level' => 'INFO',
                'data_source' => 'fallback'
            ]
        ];
    }
    
    /**
     * Formatar veículos para o formato esperado (Google Maps compatible)
     */
    private function formatVehicles($vehicles) {
        $formatted = [];
        $validCount = 0;
        $invalidCount = 0;
        
        foreach ($vehicles as $vehicle) {
            // Tentar obter coordenadas de diferentes formas
            $lat = $vehicle['lat'] ?? $vehicle['latitude'] ?? null;
            $lon = $vehicle['lon'] ?? $vehicle['longitude'] ?? null;
            
            // Validar coordenadas
            if (!$lat || !$lon || !is_numeric($lat) || !is_numeric($lon) || $lat == 0 || $lon == 0) {
                $invalidCount++;
                continue;
            }
            
            // Obter nome da rota (prioridade: line_id, route_short_name, route_id)
            $routeName = $vehicle['line_id'] ?? $vehicle['route_short_name'] ?? $vehicle['route_id'] ?? null;
            
            // Se não tem nome da rota, tentar extrair do trip_id
            if (!$routeName && isset($vehicle['trip_id'])) {
                preg_match('/\|(\d+)_/', $vehicle['trip_id'], $matches);
                if (isset($matches[1])) {
                    $routeName = $matches[1];
                }
            }
            
            $formatted[] = [
                'vehicle_id' => $vehicle['id'] ?? 'unknown',
                'trip_id' => $vehicle['trip_id'] ?? null,
                'route_id' => $vehicle['route_id'] ?? null,
                'route_name' => $routeName,
                'latitude' => floatval($lat),
                'longitude' => floatval($lon),
                'bearing' => $vehicle['bearing'] ?? 0,
                'speed' => $vehicle['speed'] ?? 0,
                'timestamp' => $vehicle['timestamp'] ?? time(),
                'occupancy' => $vehicle['occupancy_status'] ?? 'NO_DATA_AVAILABLE',
                'current_status' => $vehicle['current_status'] ?? 'UNKNOWN',
                'stop_id' => $vehicle['stop_id'] ?? null,
                'capacity_total' => $vehicle['capacity_total'] ?? null,
                'wheelchair_accessible' => $vehicle['wheelchair_accessible'] ?? false,
                'is_realtime' => ($vehicle['_data_source'] ?? 'realtime') === 'realtime',
                'data_source' => $vehicle['_data_source'] ?? 'realtime',
                'source' => $this->normalizeSource($vehicle['_data_source'] ?? 'realtime')
            ];
            
            $validCount++;
        }
        
        error_log("📊 API Carris: $validCount veículos válidos (ignorados: $invalidCount)");
        return $formatted;
    }
    
    /**
     * Formatar alertas
     */
    private function formatAlerts($alerts) {
        $formatted = [];
        
        foreach ($alerts as $alert) {
            $formatted[] = [
                'id' => $alert['id'] ?? null,
                'header' => $alert['header_text'] ?? $alert['title'] ?? 'Alerta',
                'description' => $alert['description_text'] ?? $alert['description'] ?? '',
                'cause' => $alert['cause'] ?? 'UNKNOWN_CAUSE',
                'effect' => $alert['effect'] ?? 'UNKNOWN_EFFECT',
                'severity' => $alert['severity_level'] ?? 'INFO',
                'active_periods' => $alert['active_period'] ?? []
            ];
        }
        
        return $formatted;
    }
    
    private function formatTime($timestamp) {
        if (!$timestamp) return '--:--';
        if (is_numeric($timestamp)) {
            return date('H:i', $timestamp);
        }
        return date('H:i', strtotime($timestamp));
    }
    
    private function formatDelay($seconds) {
        $minutes = round(abs($seconds) / 60);
        if ($seconds > 0) return "+{$minutes} min";
        if ($seconds < 0) return "-{$minutes} min (adiantado)";
        return "A horas";
    }
    
    private function getStatus($delay) {
        if ($delay > 300) return "Muito atrasado";
        if ($delay > 120) return "Atrasado";
        if ($delay > 30) return "Levemente atrasado";
        if ($delay < -60) return "Adiantado";
        return "A horas";
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
    
    // ==================== FALLBACKS ====================
    
    private function getSimulatedVehicles() {
        if ($this->pdo) {
            $currentSeconds = $this->gtfsTimeToSeconds(date('H:i:s'));
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT t.trip_id, t.route_id, r.route_short_name,
                       s.stop_lat, s.stop_lon
                FROM trips t
                JOIN routes r ON t.route_id = r.route_id
                JOIN stop_times st ON t.trip_id = st.trip_id
                JOIN stops s ON st.stop_id = s.stop_id
                WHERE TIME_TO_SEC(st.departure_time) > ?
                LIMIT 20
            ");
            $stmt->execute([$currentSeconds]);
            $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $vehicles = [];
            foreach ($trips as $trip) {
                $vehicles[] = [
                    'vehicle_id' => 'SIM_' . $trip['trip_id'],
                    'trip_id' => $trip['trip_id'],
                    'route_id' => $trip['route_id'],
                    'route_name' => $trip['route_short_name'],
                    'latitude' => $trip['stop_lat'] + (rand(-100, 100) / 100000),
                    'longitude' => $trip['stop_lon'] + (rand(-100, 100) / 100000),
                    'bearing' => rand(0, 359),
                    'speed' => rand(20, 70) / 3.6,
                    'timestamp' => time(),
                    'occupancy' => 'SIMULATED',
                    'is_simulated' => true,
                    'is_realtime' => false,
                    'data_source' => 'simulated',
                    'source' => 'simulated'
                ];
            }
            return $vehicles;
        }
        
        return [];
    }
    
    private function getFallbackArrivals($stopId) {
        if (!$this->pdo) return [];

        $currentSeconds = $this->gtfsTimeToSeconds(date('H:i:s'));
        
        $stmt = $this->pdo->prepare("
            SELECT st.trip_id, st.departure_time, st.arrival_time, r.route_short_name
            FROM stop_times st
            JOIN trips t ON st.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            WHERE st.stop_id = ?
            AND TIME_TO_SEC(st.departure_time) > ?
            ORDER BY TIME_TO_SEC(st.departure_time)
            LIMIT 5
        ");
        $stmt->execute([$stopId, $currentSeconds]);
        $scheduled = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        
        foreach ($scheduled as $s) {
            $scheduledTime = $this->gtfsTimeToSeconds($s['departure_time'] ?? $s['arrival_time']);
            
            // Ajustar para o dia seguinte se o horário já passou
            if ($scheduledTime < $currentSeconds) {
                $scheduledTime += 24 * 3600;
            }
            
            $etaMinutes = round(($scheduledTime - $currentSeconds) / 60);
            
            $result[] = [
                'trip_id' => $s['trip_id'],
                'route' => $s['route_short_name'],
                'scheduled' => $this->formatClockForUi($s['departure_time'] ?? $s['arrival_time']),
                'estimated' => $this->formatClockForUi($s['departure_time'] ?? $s['arrival_time']),
                'eta_minutes' => max(0, $etaMinutes),
                'eta_text' => $etaMinutes <= 0 ? 'Agora' : "Chega em {$etaMinutes} min",
                'delay' => 0,
                'delay_text' => 'A horas',
                'vehicle_id' => null,
                'status' => 'A horas',
                'is_realtime' => false,
                'data_source' => 'scheduled',
                'source' => 'estimated'
            ];
        }
        
        return $result;
    }

    private function normalizeSource($source) {
        return match ($source) {
            'realtime' => 'realtime',
            'simulated' => 'simulated',
            'fallback' => 'fallback',
            'scheduled', 'estimated_from_vehicle' => 'estimated',
            default => 'fallback',
        };
    }
}
