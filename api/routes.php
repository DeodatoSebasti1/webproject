<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

$line_id = isset($_GET['line_id']) ? trim($_GET['line_id']) : null;

try {
    $carris = new CarrisApiService();

    if ($line_id) {
        $pattern  = $carris->getPattern($line_id);
        $schedule = $pattern['schedule'] ?? [];
        $stops = array_map(fn($s, $i) => [
            'stop_order'     => $i + 1,
            'stop_id'        => $s['stop_id'],
            'stop_name'      => $s['stop_name'] ?? $s['stop_id'],
            'arrival_time'   => $s['arrival_time']   ?? null,
            'departure_time' => $s['departure_time'] ?? null,
        ], $schedule, array_keys($schedule));

        echo json_encode(["status" => "success", "stops" => $stops]);
    } else {
        $lines  = $carris->getLines();
        $routes = array_map(fn($l) => [
            'route_id'       => $l['id'],
            'route_name'     => $l['long_name']  ?? $l['short_name'] ?? $l['id'],
            'short_name'     => $l['short_name'] ?? $l['id'],
            'color'          => $l['color']      ?? null,
            'municipalities' => $l['municipalities'] ?? [],
            'patterns'       => $l['patterns']   ?? [],
        ], $lines);

        echo json_encode(["status" => "success", "routes" => $routes]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage(), "file" => $e->getFile(), "line" => $e->getLine()]);
}