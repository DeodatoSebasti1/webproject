<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

try {
    $carris = new CarrisApiService();

    // Buscar primeira linha e primeiro pattern
    $lines   = $carris->getLines();
    $line    = $lines[0];
    $pattern = $carris->getPattern($line['patterns'][0]);

    echo json_encode([
        "line"            => $line,
        "pattern_keys"    => array_keys($pattern),
        "pattern_sample"  => array_slice($pattern, 0, 3),
        // Mostrar as primeiras 3 entradas de cada array dentro do pattern
        "schedule_sample" => isset($pattern['schedule']) ? array_slice($pattern['schedule'], 0, 3) : null,
        "path_sample"     => isset($pattern['path'])     ? array_slice($pattern['path'],     0, 3) : null,
        "trips_sample"    => isset($pattern['trips'])    ? array_slice($pattern['trips'],     0, 2) : null,
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(["error" => $e->getMessage()]);
}