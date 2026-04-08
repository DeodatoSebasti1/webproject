<?php
// /urban/scripts/import_gtfs_official.php

// Incluir database.php que define $conn
require_once __DIR__ . '/../config/database.php';

// Usar a conexão $conn que já está definida
global $conn;
$pdo = $conn;

$gtfsPath = __DIR__ . '/../gtfs/';

echo "🚀 Importando GTFS Oficial da Carris Metropolitana...\n";
echo "📁 Pasta: $gtfsPath\n\n";

// Verificar conexão
if (!$pdo) {
    die("❌ Erro de conexão com o banco de dados\n");
}
echo "✅ Conexão com banco de dados OK\n\n";

// Verificar se os arquivos existem
$filesToCheck = ['stops.txt', 'routes.txt', 'trips.txt', 'stop_times.txt', 'shapes.txt'];
foreach ($filesToCheck as $file) {
    $filePath = $gtfsPath . $file;
    if (file_exists($filePath)) {
        $size = round(filesize($filePath) / 1024 / 1024, 2);
        echo "✅ $file encontrado (" . $size . " MB)\n";
    } else {
        echo "❌ $file NÃO encontrado!\n";
    }
}

echo "\n";

// Função para importar arquivos CSV
function importCSV($pdo, $filePath, $table, $columns) {
    if (!file_exists($filePath)) {
        echo "⚠️ Arquivo $filePath não encontrado\n";
        return false;
    }
    
    echo "📥 Importando $table...\n";
    
    // Limpar tabela
    try {
        $pdo->exec("TRUNCATE TABLE $table");
    } catch (Exception $e) {
        echo "⚠️ Tabela $table não existe, criando...\n";
    }
    
    // Abrir arquivo para leitura
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        echo "❌ Não foi possível abrir $filePath\n";
        return false;
    }
    
    // Ler cabeçalho
    $header = fgetcsv($handle);
    
    // Preparar query de inserção
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    
    $count = 0;
    $batch = [];
    $batchSize = 1000;
    
    while (($row = fgetcsv($handle)) !== false) {
        $data = [];
        foreach ($columns as $col) {
            $index = array_search($col, $header);
            $data[] = $index !== false ? ($row[$index] ?? null) : null;
        }
        $batch[] = $data;
        $count++;
        
        if (count($batch) >= $batchSize) {
            $pdo->beginTransaction();
            foreach ($batch as $item) {
                try {
                    $stmt->execute($item);
                } catch (Exception $e) {
                    // Ignorar erros de duplicados
                }
            }
            $pdo->commit();
            echo "   $count registos importados...\n";
            $batch = [];
        }
    }
    
    // Inserir restante
    if (count($batch) > 0) {
        $pdo->beginTransaction();
        foreach ($batch as $item) {
            try {
                $stmt->execute($item);
            } catch (Exception $e) {
                // Ignorar erros
            }
        }
        $pdo->commit();
    }
    
    fclose($handle);
    echo "✅ $table importada: $count registos\n\n";
    return true;
}

try {
    // Importar stops
    importCSV($pdo, $gtfsPath . 'stops.txt', 'stops', 
        ['stop_id', 'stop_name', 'stop_lat', 'stop_lon', 'stop_code', 'stop_desc', 'zone_id', 'stop_url', 'location_type', 'parent_station']);
    
    // Importar routes
    importCSV($pdo, $gtfsPath . 'routes.txt', 'routes', 
        ['route_id', 'agency_id', 'route_short_name', 'route_long_name', 'route_desc', 'route_type', 'route_url', 'route_color', 'route_text_color']);
    
    // Importar trips
    importCSV($pdo, $gtfsPath . 'trips.txt', 'trips', 
        ['route_id', 'service_id', 'trip_id', 'trip_headsign', 'trip_short_name', 'direction_id', 'block_id', 'shape_id', 'wheelchair_accessible', 'bikes_allowed']);
    
    // Importar stop_times (pode demorar)
    echo "📥 Importando stop_times (pode demorar alguns minutos)...\n";
    importCSV($pdo, $gtfsPath . 'stop_times.txt', 'stop_times', 
        ['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence', 'stop_headsign', 'pickup_type', 'drop_off_type', 'shape_dist_traveled']);
    
    // Importar shapes
    echo "📥 Importando shapes (pode demorar)...\n";
    importCSV($pdo, $gtfsPath . 'shapes.txt', 'shapes', 
        ['shape_id', 'shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence', 'shape_dist_traveled']);
    
    echo "\n✅ Importação concluída!\n";
    
    // Verificar resultados
    $result = $pdo->query("SELECT COUNT(*) as total FROM stops")->fetch();
    echo "📊 Total de stops: " . $result['total'] . "\n";
    
    $result = $pdo->query("SELECT COUNT(*) as total FROM routes")->fetch();
    echo "📊 Total de routes: " . $result['total'] . "\n";
    
    $result = $pdo->query("SELECT COUNT(*) as total FROM trips")->fetch();
    echo "📊 Total de trips: " . $result['total'] . "\n";
    
    $result = $pdo->query("SELECT COUNT(*) as total FROM stop_times")->fetch();
    echo "📊 Total de stop_times: " . $result['total'] . "\n";
    
    // Verificar linha 2772
    $result = $pdo->query("SELECT COUNT(*) as total FROM routes WHERE route_short_name = '2772'")->fetch();
    echo "📊 Linha 2772 existe: " . ($result['total'] > 0 ? "SIM" : "NÃO") . "\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}