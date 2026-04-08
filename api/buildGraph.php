<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once '../services/carrisApiService.php';

$cacheFile = __DIR__ . '/../graph_cache.json';

try {
    $carris = new CarrisApiService();
    $log    = [];

    // Buscar só os IDs das linhas (sem detalhes) para poupar memória
    $lines = $carris->getLines();
    $log[] = count($lines) . " linhas encontradas";

    $done = 0; $errors = 0;
    $graph = [];

    foreach ($lines as $line) {
        $lineId   = $line['id'];
        $patterns = $line['patterns'] ?? [];
        if (empty($patterns)) continue;

        try {
            $pattern = $carris->getPattern($patterns[0]);
            $path    = $pattern['path'] ?? [];

            for ($i = 0; $i < count($path) - 1; $i++) {
                $f = $path[$i]['stop']['id'];
                $t = $path[$i + 1]['stop']['id'];

                $distMeters = $path[$i + 1]['distance_delta'] ?? 0;
                // Se distance_delta for 0 ou muito pequeno, usar 2 min por paragem
                if ($distMeters > 100) {
                    $mins = ($distMeters / 1000) / 30 * 60; // 30 km/h média urbana
                } else {
                    $mins = 2.0; // estimativa padrão entre paragens
                }
                $mins = max(round($mins, 1), 0.5);

                if (!isset($graph[$f][$t]) || $mins < $graph[$f][$t]['time']) {
                    $graph[$f][$t] = ['time' => round($mins, 2), 'line' => $lineId];
                }
                // Garantir que o destino existe como nó
                if (!isset($graph[$t])) $graph[$t] = [];
            }
            $done++;

            // Libertar memória do pattern após processar
            unset($pattern, $path);

        } catch (Throwable $e) {
            $errors++;
        }
    }

    unset($lines); // libertar lista de linhas

    $log[] = "$done patterns processados, $errors erros";
    $log[] = count($graph) . " nós no grafo";

    file_put_contents($cacheFile, json_encode($graph));
    unset($graph);

    $log[] = "Cache guardado: graph_cache.json (" . round(filesize($cacheFile) / 1024) . " KB)";

    echo json_encode(["status" => "success", "log" => $log], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage(), "line" => $e->getLine()]);
}