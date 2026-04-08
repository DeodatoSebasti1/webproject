<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    $carris = new CarrisApiService();
    $all    = $carris->getStops();

    $stops = array_map(fn($s) => [
        'stop_id'   => $s['id'],
        'stop_name' => $s['name'],
        'latitude'  => $s['lat'],
        'longitude' => $s['lon'],
        'locality'  => $s['locality'] ?? null,
        'lines'     => $s['lines']    ?? [],
    ], $all);

    if ($q !== '') {
        $qLower = mb_strtolower($q);
        $stops  = array_values(array_filter($stops,
            fn($s) => str_contains(mb_strtolower($s['stop_name']), $qLower)
        ));
    }

    echo json_encode(["status" => "success", "count" => count($stops), "stops" => $stops]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage(), "file" => $e->getFile(), "line" => $e->getLine()]);
}