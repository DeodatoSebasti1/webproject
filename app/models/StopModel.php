<?php

require_once __DIR__ . '/../../config/database.php';

class StopModel {

    private $pdo;

    public function __construct() {
        global $conn;
        $this->pdo = $conn;
    }

    /**
     * Buscar a paragem mais próxima de uma coordenada
     */
    public function findNearestStop($lat, $lon) {
        $sql = "
            SELECT stop_id, stop_name, stop_lat, stop_lon,
                   (ABS(stop_lat - ?) + ABS(stop_lon - ?)) as distance
            FROM stops
            WHERE stop_lat BETWEEN ? - 0.1 AND ? + 0.1
              AND stop_lon BETWEEN ? - 0.1 AND ? + 0.1
            ORDER BY distance
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lat, $lon, $lat, $lat, $lon, $lon]);
        
        $stop = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stop) {
            return [
                "stop_id" => $stop['stop_id'],
                "stop_name" => $stop['stop_name'],
                "stop_lat" => $stop['stop_lat'],
                "stop_lon" => $stop['stop_lon']
            ];
        }
        
        return null;
    }

    /**
     * Buscar paragem pelo nome
     */
    public function findStopByName($name) {
        $sql = "SELECT stop_id, stop_name, stop_lat, stop_lon 
                FROM stops 
                WHERE stop_name LIKE ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["%$name%"]);
        
        $stop = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stop) {
            return [
                "stop_id" => $stop['stop_id'],
                "stop_name" => $stop['stop_name'],
                "stop_lat" => $stop['stop_lat'],
                "stop_lon" => $stop['stop_lon']
            ];
        }
        
        return null;
    }

    /**
     * Buscar todas as paragens (opcional)
     */
    public function getAllStops() {
        $sql = "SELECT stop_id, stop_name, stop_lat, stop_lon FROM stops LIMIT 1000";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar paragens por ID
     */
    public function getStopById($stopId) {
        $sql = "SELECT stop_id, stop_name, stop_lat, stop_lon 
                FROM stops 
                WHERE stop_id = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$stopId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}