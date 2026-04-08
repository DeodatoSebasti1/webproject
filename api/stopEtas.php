<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

$stop_id = isset($_GET['stop_id']) ? trim($_GET['stop_id']) : null;

if (!$stop_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parâmetro 'stop_id' obrigatório."]);
    exit;
}

try {
    $carris   = new CarrisApiService();
    $etas     = $carris->getStopEtas($stop_id);

    $arrivals = array_map(fn($e) => [
        'line_id'           => $e['line_id']          ?? null,
        'headsign'          => $e['headsign']          ?? null,
        'scheduled_arrival' => $e['scheduled_arrival'] ?? null,
        'estimated_arrival' => $e['estimated_arrival'] ?? null,
        'vehicle_id'        => $e['vehicle_id']        ?? null,
    ], $etas);

    usort($arrivals, fn($a, $b) =>
        strcmp($a['estimated_arrival'] ?? '99:99', $b['estimated_arrival'] ?? '99:99')
    );

    echo json_encode(["status" => "success", "stop_id" => $stop_id, "arrivals" => $arrivals]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage(), "file" => $e->getFile(), "line" => $e->getLine()]);
}