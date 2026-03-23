<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

try {
    $carris = new CarrisApiService();
    $raw    = $carris->get('/vehicles');

    echo json_encode([
        "type"    => gettype($raw),
        "count"   => is_array($raw) ? count($raw) : null,
        "sample"  => is_array($raw) ? array_slice($raw, 0, 2) : $raw,
        "keys"    => is_array($raw) && count($raw) > 0 ? array_keys($raw[0]) : null,
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(["error" => $e->getMessage()]);
}