<?php

require_once __DIR__ . '/../../config/database.php';

class RouteModel {

    private $pdo;
    private $cacheFile;

    public function __construct() {
        global $conn;
        $this->pdo = $conn;
        $this->cacheFile = realpath(__DIR__ . '/../storage/graph_cache.json');
    }

    /**
     * Buscar rotas entre duas paragens usando GTFS
     */
    public function getRoutes($fromStopId, $toStopId) {
        
        // Buscar rotas diretas do GTFS
        $sql = "
            SELECT DISTINCT t.trip_id, t.route_id, r.route_short_name
            FROM stop_times st1
            JOIN stop_times st2 ON st1.trip_id = st2.trip_id
            JOIN trips t ON st1.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            WHERE st1.stop_id = ? 
              AND st2.stop_id = ?
              AND st1.stop_sequence < st2.stop_sequence
            LIMIT 5
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$fromStopId, $toStopId]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $routes = [];
        
        foreach ($trips as $trip) {
            // Buscar stops da viagem
            $stops = $this->getStopsFromTrip($trip['trip_id']);
            
            // Buscar shapes
            $coordinates = $this->getShapeCoordinates($trip['trip_id']);
            
            $routes[] = [
                "type" => "direct",
                "trip_id" => $trip['trip_id'],
                "route_name" => $trip['route_short_name'],
                "total_time" => count($stops) * 2,
                "transfers" => 0,
                "stop_count" => count($stops),
                "coordinates" => $coordinates,
                "stopsData" => $stops,
                "segments" => [
                    [
                        "from" => $stops[0]['stop_name'] ?? "Origem",
                        "to" => end($stops)['stop_name'] ?? "Destino",
                        "line" => $trip['route_short_name'],
                        "time" => count($stops) * 2
                    ]
                ]
            ];
        }
        
        return $routes;
    }

    /**
     * Buscar stops de uma viagem
     */
    private function getStopsFromTrip($tripId) {
        $sql = "
            SELECT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon
            FROM stop_times st
            JOIN stops s ON st.stop_id = s.stop_id
            WHERE st.trip_id = ?
            ORDER BY st.stop_sequence
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tripId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar coordenadas do shape
     */
    private function getShapeCoordinates($tripId) {
        $sql = "
            SELECT sh.shape_pt_lat, sh.shape_pt_lon
            FROM trips t
            JOIN shapes sh ON t.shape_id = sh.shape_id
            WHERE t.trip_id = ?
            ORDER BY sh.shape_pt_sequence
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tripId]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $coordinates = [];
        foreach ($points as $point) {
            $coordinates[] = [
                floatval($point['shape_pt_lat']),
                floatval($point['shape_pt_lon'])
            ];
        }
        
        return $coordinates;
    }

    /**
     * Buscar paragens pelo nome (para o search)
     */
    public function findStopByName($name) {
        $sql = "SELECT stop_id, stop_name, stop_lat, stop_lon 
                FROM stops 
                WHERE stop_name LIKE ? 
                LIMIT 5";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["%$name%"]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}