<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../services/carrisApiService.php';

$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';

if ($from === '' || $to === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parâmetros 'from' e 'to' são obrigatórios."]);
    exit;
}
if ($from === $to) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Origem e destino não podem ser iguais."]);
    exit;
}

$cacheFile = __DIR__ . '/../graph_cache.json';

if (!file_exists($cacheFile)) {
    http_response_code(503);
    echo json_encode([
        "status"  => "error",
        "message" => "Grafo não encontrado. Acede primeiro a api/buildGraph.php para construir o cache."
    ]);
    exit;
}

try {
    $graph = json_decode(file_get_contents($cacheFile), true);

    if (!isset($graph[$from])) {
        echo json_encode(["status" => "success", "routes" => [], "message" => "Paragem de origem não encontrada no grafo."]);
        exit;
    }

    // Verificar se destino existe como nó ou como vizinho
    $destExists = isset($graph[$to]);
    if (!$destExists) {
        foreach ($graph as $neighbours) {
            if (isset($neighbours[$to])) { $destExists = true; break; }
        }
    }
    if (!$destExists) {
        echo json_encode(["status" => "success", "routes" => [], "message" => "Paragem de destino não encontrada no grafo."]);
        exit;
    }

    $primaryPath = dijkstra($graph, $from, $to);

    if (empty($primaryPath)) {
        echo json_encode(["status" => "success", "routes" => [], "message" => "Nenhuma rota encontrada entre as paragens indicadas."]);
        exit;
    }

    $carris    = new CarrisApiService();
    $stopNames = fetchStopNames($carris, array_unique($primaryPath));

    $routes   = [];
    $routes[] = buildRoute($carris, $graph, $primaryPath, $stopNames, "Rota mais rápida");

    $altPath = dijkstraFewestTransfers($graph, $from, $to);
    if (!empty($altPath) && $altPath !== $primaryPath) {
        $extra = array_diff(array_unique($altPath), array_keys($stopNames));
        if (!empty($extra)) {
            $stopNames += fetchStopNames($carris, array_values($extra));
        }
        $routes[] = buildRoute($carris, $graph, $altPath, $stopNames, "Menos transbordos");
    }

    echo json_encode(["status" => "success", "routes" => $routes, "generated_at" => date('c')]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage(), "file" => $e->getFile(), "line" => $e->getLine()]);
}

// ── Buscar nomes das paragens de uma vez ──────────────────
function fetchStopNames(CarrisApiService $carris, array $ids): array {
    $names = [];
    foreach ($ids as $id) {
        try {
            $s = $carris->getStop($id);
            $names[$id] = ['stop_id' => $id, 'stop_name' => $s['name'], 'latitude' => $s['lat'], 'longitude' => $s['lon']];
        } catch (Throwable $e) {
            $names[$id] = ['stop_id' => $id, 'stop_name' => $id, 'latitude' => null, 'longitude' => null];
        }
    }
    return $names;
}

// ── Dijkstra ──────────────────────────────────────────────
function dijkstra(array $graph, string $start, string $end): array {
    $dist = []; $prev = []; $visited = [];
    $queue = new SplPriorityQueue();
    $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

    foreach (array_keys($graph) as $n) { $dist[$n] = PHP_FLOAT_MAX; $prev[$n] = null; }
    $dist[$start] = 0;
    $queue->insert($start, 0);

    while (!$queue->isEmpty()) {
        $item = $queue->extract();
        $cur  = $item['data']; $curDist = -$item['priority'];
        if (isset($visited[$cur])) continue;
        $visited[$cur] = true;
        if ($cur === $end) break;
        foreach (($graph[$cur] ?? []) as $nb => $edge) {
            $nd = $curDist + $edge['time'];
            if ($nd < ($dist[$nb] ?? PHP_FLOAT_MAX)) {
                $dist[$nb] = $nd; $prev[$nb] = $cur;
                $queue->insert($nb, -$nd);
            }
        }
    }

    if (!array_key_exists($end, $prev) || ($prev[$end] === null && $end !== $start)) return [];
    $path = []; $cur = $end; $seen = [];
    while ($cur !== null) {
        if (isset($seen[$cur])) return [];
        $seen[$cur] = true;
        array_unshift($path, $cur);
        $cur = $prev[$cur] ?? null;
    }
    return $path[0] === $start ? $path : [];
}

// ── Dijkstra com penalização por transbordo ───────────────
function dijkstraFewestTransfers(array $graph, string $start, string $end): array {
    $PENALTY = 10.0;
    $dist = []; $prev = []; $visited = [];
    $queue = new SplPriorityQueue();
    $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

    $sk = "$start|null"; $dist[$sk] = 0; $prev[$sk] = null;
    $queue->insert([$start, null], 0);

    while (!$queue->isEmpty()) {
        $item = $queue->extract();
        [$cur, $curLine] = $item['data'];
        $curDist = -$item['priority'];
        $key = "$cur|" . ($curLine ?? 'null');
        if (isset($visited[$key])) continue;
        $visited[$key] = true;
        if ($cur === $end) break;
        foreach (($graph[$cur] ?? []) as $nb => $edge) {
            $penalty = ($curLine !== null && $edge['line'] !== $curLine) ? $PENALTY : 0;
            $nd = $curDist + $edge['time'] + $penalty;
            $nk = "$nb|{$edge['line']}";
            if ($nd < ($dist[$nk] ?? PHP_FLOAT_MAX)) {
                $dist[$nk] = $nd; $prev[$nk] = $key;
                $queue->insert([$nb, $edge['line']], -$nd);
            }
        }
    }

    $bestKey = null; $bestDist = PHP_FLOAT_MAX;
    foreach ($dist as $k => $d) {
        [$sid] = explode('|', $k, 2);
        if ($sid === $end && $d < $bestDist) { $bestDist = $d; $bestKey = $k; }
    }
    if ($bestKey === null) return [];

    $keys = []; $seen = []; $cur = $bestKey;
    while ($cur !== null) {
        if (isset($seen[$cur])) break;
        $seen[$cur] = true; $keys[] = $cur;
        $cur = $prev[$cur] ?? null;
    }
    return array_map(fn($k) => explode('|', $k, 2)[0], array_reverse($keys));
}

// ── Construir objeto rota ─────────────────────────────────
function buildRoute(CarrisApiService $carris, array $graph, array $path, array $stopNames, string $label): array {
    $totalTime = 0.0; $transfers = 0; $lastLine = null; $segments = [];

    for ($i = 0; $i < count($path) - 1; $i++) {
        $f = $path[$i]; $t = $path[$i + 1];
        $edge = $graph[$f][$t] ?? null;
        $time = $edge ? $edge['time'] : 2.0;
        $line = $edge ? $edge['line'] : 'N/D';

        if ($lastLine !== null && $line !== $lastLine) $transfers++;
        $lastLine = $line; $totalTime += $time;

        $etaData = [];
        try {
            $etas = $carris->getStopEtas($f);
            foreach ($etas as $e) {
                if (($e['line_id'] ?? '') === $line) {
                    $etaData = ['estimated' => $e['estimated_arrival'] ?? null, 'scheduled' => $e['scheduled_arrival'] ?? null];
                    break;
                }
            }
        } catch (Throwable $e) {}

        $segments[] = [
            "from"              => $stopNames[$f] ?? ['stop_id' => $f, 'stop_name' => $f],
            "to"                => $stopNames[$t] ?? ['stop_id' => $t, 'stop_name' => $t],
            "route_name"        => $line,
            "travel_time"       => round($time, 1),
            "estimated_arrival" => $etaData['estimated'] ?? null,
            "scheduled_arrival" => $etaData['scheduled'] ?? null,
        ];
    }

    return [
        "label"             => $label,
        "stops"             => array_values(array_map(fn($id) => $stopNames[$id] ?? ['stop_id' => $id, 'stop_name' => $id], $path)),
        "segments"          => $segments,
        "total_travel_time" => round($totalTime, 1),
        "transfers"         => $transfers,
        "stop_count"        => count($path)
    ];
}