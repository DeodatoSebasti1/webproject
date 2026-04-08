<?php
// /urban/app/services/GtfsRealtimeService.php

class GtfsRealtimeService {
    
    private $tripUpdatesUrl = 'https://www.carrismetropolitana.pt/gtfs-realtime/trip_updates.pb';
    private $vehiclePositionsUrl = 'https://www.carrismetropolitana.pt/gtfs-realtime/vehicle_positions.pb';
    private $alertsUrl = 'https://www.carrismetropolitana.pt/gtfs-realtime/alerts.pb';
    
    private $cache = [];
    private $protobufAvailable = false;
    
    public function __construct() {
        $this->protobufAvailable = extension_loaded('protobuf');
        if (!$this->protobufAvailable) {
            error_log("⚠️ Extensão protobuf não carregada. Usando dados simulados.");
        }
    }
    
    /**
     * Buscar posições em tempo real dos veículos
     */
    public function getVehiclePositions() {
        if (isset($this->cache['vehicles']) && (time() - $this->cache['vehicles_time']) < 30) {
            return $this->cache['vehicles'];
        }
        
        $vehicles = [];
        
        // Tentar feed protobuf primeiro (dados reais)
        if ($this->protobufAvailable) {
            $data = $this->fetchProtobufFeed($this->vehiclePositionsUrl);
            if ($data) {
                $vehicles = $this->parseVehiclePositions($data);
                if (!empty($vehicles)) {
                    $this->cache['vehicles'] = $vehicles;
                    $this->cache['vehicles_time'] = time();
                    return $vehicles;
                }
            }
        }
        
        // Fallback: API v2
        $vehicles = $this->getVehiclesFromApi();
        
        // Último recurso: dados simulados
        if (empty($vehicles)) {
            $vehicles = $this->getSimulatedVehicles();
        }
        
        $this->cache['vehicles'] = $vehicles;
        $this->cache['vehicles_time'] = time();
        
        return $vehicles;
    }
    
    /**
     * Buscar atualizações de viagem (atrasos em tempo real)
     */
    public function getTripUpdates() {
        if (isset($this->cache['trips']) && (time() - $this->cache['trips_time']) < 30) {
            return $this->cache['trips'];
        }
        
        $updates = [];
        
        // Tentar feed protobuf primeiro (dados reais)
        if ($this->protobufAvailable) {
            $data = $this->fetchProtobufFeed($this->tripUpdatesUrl);
            if ($data) {
                $updates = $this->parseTripUpdates($data);
                if (!empty($updates)) {
                    $this->cache['trips'] = $updates;
                    $this->cache['trips_time'] = time();
                    return $updates;
                }
            }
        }
        
        // Fallback: dados simulados
        $updates = $this->getSimulatedUpdates();
        
        $this->cache['trips'] = $updates;
        $this->cache['trips_time'] = time();
        
        return $updates;
    }
    
    /**
     * Buscar chegadas em tempo real para uma paragem
     */
    public function getStopRealtime($stopId) {
        // Primeiro tentar obter atrasos em tempo real
        $tripUpdates = $this->getTripUpdates();
        
        // Buscar horários programados da paragem
        $scheduled = $this->getScheduledStopTimes($stopId);
        
        $stopTimes = [];
        foreach ($scheduled as $s) {
            $delay = $tripUpdates[$s['trip_id']]['delay'] ?? 0;
            $estimatedTimestamp = strtotime($s['scheduled']) + $delay;
            $estimated = date('H:i', $estimatedTimestamp);
            
            $stopTimes[] = [
                'trip_id' => $s['trip_id'],
                'route' => $s['route'],
                'scheduled' => $s['scheduled'],
                'estimated' => $estimated,
                'delay' => $delay,
                'delay_text' => $this->formatDelay($delay),
                'status' => $this->getStatus($delay)
            ];
        }
        
        // Ordenar por horário estimado
        usort($stopTimes, function($a, $b) {
            return strtotime($a['estimated']) - strtotime($b['estimated']);
        });
        
        return $stopTimes;
    }
    
    /**
     * Buscar posição de um veículo específico
     */
    public function getVehiclePosition($tripId) {
        $vehicles = $this->getVehiclePositions();
        foreach ($vehicles as $v) {
            if ($v['trip_id'] == $tripId) {
                return $v;
            }
        }
        return null;
    }
    
    // ==================== MÉTODOS PRIVADOS ====================
    
    private function fetchProtobufFeed($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // ✅ SEGUIR REDIRECIONAMENTOS
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'UrbanTraffic/1.0');
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Erro ao buscar feed protobuf: HTTP $httpCode - $url -> $finalUrl");
            return null;
        }
        
        return $data;
    }
    
    private function parseVehiclePositions($data) {
        if (!class_exists('transit_realtime\FeedMessage')) {
            error_log("Classe transit_realtime\FeedMessage não encontrada");
            return [];
        }
        
        try {
            $feed = new transit_realtime\FeedMessage();
            $feed->parse($data);
            
            $vehicles = [];
            foreach ($feed->getEntity() as $entity) {
                if ($entity->hasVehicle()) {
                    $v = $entity->getVehicle();
                    $vehicles[] = [
                        'vehicle_id' => $v->getVehicle()->getId(),
                        'trip_id' => $v->getTrip()->getTripId(),
                        'route_id' => $v->getTrip()->getRouteId(),
                        'latitude' => $v->getPosition()->getLatitude(),
                        'longitude' => $v->getPosition()->getLongitude(),
                        'bearing' => $v->getPosition()->getBearing(),
                        'speed' => $v->getPosition()->getSpeed(),
                        'timestamp' => $v->getTimestamp(),
                        'occupancy' => $this->getOccupancyStatus($v->getOccupancyStatus())
                    ];
                }
            }
            return $vehicles;
        } catch (Exception $e) {
            error_log("Erro ao parsear feed de veículos: " . $e->getMessage());
            return [];
        }
    }
    
    private function parseTripUpdates($data) {
        if (!class_exists('transit_realtime\FeedMessage')) {
            return [];
        }
        
        try {
            $feed = new transit_realtime\FeedMessage();
            $feed->parse($data);
            
            $updates = [];
            foreach ($feed->getEntity() as $entity) {
                if ($entity->hasTripUpdate()) {
                    $tu = $entity->getTripUpdate();
                    $trip = $tu->getTrip();
                    
                    $delay = 0;
                    foreach ($tu->getStopTimeUpdate() as $stopUpdate) {
                        if ($stopUpdate->hasDeparture()) {
                            $delay = $stopUpdate->getDeparture()->getDelay();
                            break;
                        }
                    }
                    
                    $updates[$trip->getTripId()] = [
                        'trip_id' => $trip->getTripId(),
                        'route_id' => $trip->getRouteId(),
                        'delay' => $delay,
                        'delay_text' => $this->formatDelay($delay)
                    ];
                }
            }
            return $updates;
        } catch (Exception $e) {
            error_log("Erro ao parsear feed de atualizações: " . $e->getMessage());
            return [];
        }
    }
    
    private function getVehiclesFromApi() {
        $data = $this->fetchEndpoint('/vehicles');
        if (!$data) return [];
        
        $vehicles = [];
        foreach ($data as $vehicle) {
            $lat = $vehicle['latitude'] ?? $vehicle['position']['latitude'] ?? null;
            $lon = $vehicle['longitude'] ?? $vehicle['position']['longitude'] ?? null;
            
            if ($lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon) && $lat != 0 && $lon != 0) {
                $vehicles[] = [
                    'vehicle_id' => $vehicle['id'] ?? $vehicle['vehicle_id'] ?? 'unknown',
                    'trip_id' => $vehicle['trip_id'] ?? $vehicle['trip']['id'] ?? null,
                    'route_id' => $vehicle['route_id'] ?? $vehicle['route']['id'] ?? null,
                    'latitude' => floatval($lat),
                    'longitude' => floatval($lon),
                    'bearing' => $vehicle['bearing'] ?? $vehicle['position']['heading'] ?? 0,
                    'speed' => $vehicle['speed'] ?? $vehicle['position']['speed'] ?? 0,
                    'timestamp' => $vehicle['timestamp'] ?? time(),
                    'occupancy' => $this->getOccupancyStatus($vehicle['occupancy'] ?? null)
                ];
            }
        }
        
        return $vehicles;
    }
    
    private function getScheduledStopTimes($stopId) {
        global $conn;
        
        $sql = "
            SELECT st.trip_id, st.departure_time, r.route_short_name
            FROM stop_times st
            JOIN trips t ON st.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            WHERE st.stop_id = ?
            AND st.departure_time > TIME(NOW())
            ORDER BY st.departure_time
            LIMIT 10
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$stopId]);
        $scheduled = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($scheduled as $s) {
            $result[] = [
                'trip_id' => $s['trip_id'],
                'route' => $s['route_short_name'],
                'scheduled' => date('H:i', strtotime($s['departure_time']))
            ];
        }
        
        return $result;
    }
    
    private function fetchEndpoint($endpoint) {
        $url = 'https://api.carrismetropolitana.pt/v2' . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: UrbanTraffic/1.0'
        ]);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Erro ao buscar API Carris: HTTP $httpCode - $url");
            return null;
        }
        
        return json_decode($data, true);
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
    
    private function getOccupancyStatus($status) {
        $map = [
            0 => "DESCONHECIDO",
            1 => "VAZIO",
            2 => "POUCOS LUGARES",
            3 => "LUGARES DISPONÍVEIS",
            4 => "LOTAÇÃO MÁXIMA"
        ];
        return $map[$status] ?? "DESCONHECIDO";
    }
    
    // ==================== DADOS SIMULADOS (FALLBACK) ====================
    
    private function getSimulatedVehicles() {
        return [
            [
                'vehicle_id' => '001',
                'trip_id' => '[89CJD]2722_0_2|1|1|0440',
                'route_id' => '2722_0',
                'latitude' => 38.7689,
                'longitude' => -9.1281,
                'bearing' => 90,
                'speed' => 30,
                'timestamp' => time(),
                'occupancy' => 'LUGARES DISPONÍVEIS'
            ]
        ];
    }
    
    private function getSimulatedUpdates() {
        return [
            '[89CJD]2722_0_2|1|1|0440' => [
                'trip_id' => '[89CJD]2722_0_2|1|1|0440',
                'route_id' => '2722_0',
                'delay' => 0,
                'delay_text' => 'A horas'
            ]
        ];
    }
}