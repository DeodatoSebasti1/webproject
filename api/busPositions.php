<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

try {
    $carris   = new CarrisApiService();
    $vehicles = $carris->getVehicles();

    // Filtrar só veículos com posição ativa
    $buses = [];
    foreach ($vehicles as $v) {
        if (!isset($v['lat']) || !isset($v['lon']) || !isset($v['timestamp'])) continue;

        $buses[] = [
            'bus_id'      => $v['id'],
            'plate_number'=> $v['license_plate'] ?? $v['id'],
            'route_name'  => $v['line_id']        ?? 'N/D',
            'latitude'    => $v['lat'],
            'longitude'   => $v['lon'],
            'speed'       => isset($v['speed']) ? round((float)$v['speed'], 1) : null,
            'heading'     => $v['bearing']        ?? null,
            'status'      => $v['current_status'] ?? null,
            'stop_id'     => $v['stop_id']        ?? null,
            'recorded_at' => date('c', (int)$v['timestamp']),
        ];
    }

    echo json_encode([
        "status"    => "success",
        "timestamp" => date('c'),
        "count"     => count($buses),
        "buses"     => $buses
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}