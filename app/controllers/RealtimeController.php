<?php
// /urban/app/controllers/RealtimeController.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/GtfsRealtimeService.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'vehicles';
    $stopId = $_GET['stop_id'] ?? null;
    $tripId = $_GET['trip_id'] ?? null;
    
    $realtime = new GtfsRealtimeService();
    
    switch ($action) {
        case 'vehicles':
            $data = $realtime->getVehiclePositions();
            break;
            
        case 'updates':
            $data = $realtime->getTripUpdates();
            break;
            
        case 'alerts':
            $data = $realtime->getAlerts();
            break;
            
        case 'stop':
            if (!$stopId) throw new Exception("stop_id obrigatório");
            $data = $realtime->getStopRealtime($stopId);
            break;
            
        case 'vehicle':
            if (!$tripId) throw new Exception("trip_id obrigatório");
            $data = $realtime->getVehiclePosition($tripId);
            break;
            
        default:
            $data = ['error' => 'Ação inválida'];
    }
    
    echo json_encode([
        "status" => "success",
        "timestamp" => time(),
        "data" => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}