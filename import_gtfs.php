<?php

require_once "config/database.php";

function importCSV($pdo, $file, $table) {

    echo "Importando $table...\n";

    $handle = fopen($file, "r");

    $headers = fgetcsv($handle);

    while (($row = fgetcsv($handle)) !== false) {

        $data = array_combine($headers, $row);

        $columns = implode(",", array_keys($data));
        $placeholders = implode(",", array_fill(0, count($data), "?"));

        $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
        $stmt->execute(array_values($data));
    }

    fclose($handle);

    echo "✔ $table importado\n";
}

// IMPORTAÇÃO
importCSV($conn, "gtfs/stops.txt", "stops");
importCSV($conn, "gtfs/routes.txt", "routes");
importCSV($conn, "gtfs/trips.txt", "trips");
importCSV($conn, "gtfs/stop_times.txt", "stop_times");

echo "🚀 IMPORTAÇÃO COMPLETA!";