<?php
define('ALLOW_DB_FAILURE', true);
define('SUPPRESS_DB_ERROR_OUTPUT', true);
require_once __DIR__ . '/../config/database.php';

function fetchLinesFromDatabase($conn) {
    if (!$conn instanceof PDO) {
        return [];
    }

    $routesStmt = $conn->query("
        SELECT route_id, route_short_name, route_long_name, route_color, route_text_color, path_type, circular, school
        FROM routes
        ORDER BY route_short_name, route_id
    ");

    $routes = $routesStmt->fetchAll(PDO::FETCH_ASSOC);

    $lines = [];

    foreach ($routes as $route) {
        $number = $route['route_short_name'] ?: $route['route_id'];
        $area = detectLineArea($number);
        [$origin, $destination] = splitRouteName($route['route_long_name'] ?: $number);
        $lineType = detectLineType($route);

        $lines[] = [
            'id' => $route['route_id'],
            'numero' => $number,
            'nome' => $route['route_long_name'] ?: $number,
            'area' => $area,
            'color' => normalizeHexColor($route['route_color'] ?? null, '4CAF50'),
            'text_color' => normalizeHexColor($route['route_text_color'] ?? null, 'FFFFFF'),
            'type' => $lineType['type'],
            'type_label' => $lineType['label'],
            'badges' => $lineType['badges'],
            'origem' => $origin,
            'destino' => $destination,
            'paragens' => [],
            'coordenadas' => [],
            'stops_count' => null,
            'shape_id' => null,
        ];
    }

    return $lines;
}

function splitRouteName(string $name): array {
    foreach ([' - ', ' – ', ' — ', ' → '] as $separator) {
        if (strpos($name, $separator) !== false) {
            $parts = array_map('trim', explode($separator, $name, 2));
            return [$parts[0] ?: $name, $parts[1] ?? $name];
        }
    }

    return [$name, $name];
}

function normalizeHexColor($color, string $fallback): string {
    $color = strtoupper(trim((string)$color));
    if (preg_match('/^[0-9A-F]{6}$/', $color)) {
        return '#' . $color;
    }

    return '#' . $fallback;
}

function detectLineType(array $route): array {
    $pathType = (int)($route['path_type'] ?? 0);
    $rawName = (string)($route['route_long_name'] ?? '');
    $name = function_exists('mb_strtolower') ? mb_strtolower($rawName, 'UTF-8') : strtolower($rawName);
    $color = strtoupper((string)($route['route_color'] ?? ''));

    $labels = [
        1 => ['type' => 'local', 'label' => 'Local'],
        2 => ['type' => 'regional', 'label' => 'Regional'],
        3 => ['type' => 'intermunicipal', 'label' => 'Intermunicipal'],
    ];

    $base = $labels[$pathType] ?? ['type' => 'regular', 'label' => 'Regular'];
    $badges = [$base['label']];

    if ((int)($route['circular'] ?? 0) === 1 || str_contains($name, 'circular')) {
        $badges[] = 'Circular';
    }

    if ((int)($route['school'] ?? 0) === 1 || str_contains($name, 'escola') || str_contains($name, 'escolar')) {
        $badges[] = 'Escolar';
    }

    if (str_contains($name, 'noturna') || str_contains($name, 'madrugada')) {
        $badges[] = 'Noturna';
    }

    if ($color === 'FDB71A' || str_contains($name, 'via a8') || str_contains($name, 'expresso')) {
        $badges[] = 'Rápida';
    }

    if ($color === '0C807E') {
        $badges[] = 'Praia/Sazonal';
    }

    return [
        'type' => $base['type'],
        'label' => $base['label'],
        'badges' => array_values(array_unique($badges)),
    ];
}

function fetchRepresentativeTrips(PDO $conn, array $routeIds): array {
    $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
    $stmt = $conn->prepare("
        SELECT route_id, MIN(trip_id) AS trip_id, MIN(shape_id) AS shape_id
        FROM trips
        WHERE route_id IN ($placeholders)
        GROUP BY route_id
    ");
    $stmt->execute($routeIds);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!empty($row['trip_id'])) {
            $map[$row['route_id']] = $row;
        }
    }

    return $map;
}

function fetchStopsForTrips(PDO $conn, array $tripIds): array {
    $tripIds = array_values(array_filter(array_unique($tripIds)));
    if (empty($tripIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
    $stmt = $conn->prepare("
        SELECT st.trip_id, s.stop_id, s.stop_name, s.stop_lat, s.stop_lon, st.stop_sequence
        FROM stop_times st
        JOIN stops s ON s.stop_id = st.stop_id
        WHERE st.trip_id IN ($placeholders)
        ORDER BY st.trip_id, st.stop_sequence
    ");
    $stmt->execute($tripIds);

    $byTrip = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byTrip[$row['trip_id']][] = $row;
    }

    return $byTrip;
}

function fetchShapeCoordinates($shapeStmt, $shapeId, array $stops, array $fallbackCoordinates) {
    if (!$shapeId) {
        return $fallbackCoordinates;
    }

    $shapeStmt->execute([$shapeId]);
    $points = $shapeStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($points) < 2) {
        return $fallbackCoordinates;
    }

    $shape = array_map(function($point) {
        return [
            'lat' => (float)$point['shape_pt_lat'],
            'lng' => (float)$point['shape_pt_lon'],
        ];
    }, $points);

    $startIndex = findClosestShapePoint($shape, $stops[0]['lat'], $stops[0]['lng']);
    $endIndex = findClosestShapePoint($shape, $stops[count($stops) - 1]['lat'], $stops[count($stops) - 1]['lng']);

    if ($startIndex < 0 || $endIndex < 0) {
        return array_map(fn($point) => [$point['lat'], $point['lng']], $shape);
    }

    if ($startIndex > $endIndex) {
        $shape = array_reverse($shape);
        $count = count($shape);
        $startIndex = $count - 1 - $startIndex;
        $endIndex = $count - 1 - $endIndex;
    }

    $segment = array_slice($shape, $startIndex, $endIndex - $startIndex + 1);

    if (count($segment) < 2) {
        return $fallbackCoordinates;
    }

    return array_map(fn($point) => [$point['lat'], $point['lng']], $segment);
}

function findClosestShapePoint(array $shape, $lat, $lng) {
    $closestIndex = -1;
    $closestDistance = PHP_FLOAT_MAX;

    foreach ($shape as $index => $point) {
        $distance = squaredDistance($lat, $lng, $point['lat'], $point['lng']);
        if ($distance < $closestDistance) {
            $closestDistance = $distance;
            $closestIndex = $index;
        }
    }

    return $closestIndex;
}

function squaredDistance($lat1, $lng1, $lat2, $lng2) {
    return (($lat1 - $lat2) * ($lat1 - $lat2)) + (($lng1 - $lng2) * ($lng1 - $lng2));
}

function detectLineArea($lineNumber) {
    $lineNumber = (string)$lineNumber;
    $firstDigit = substr($lineNumber, 0, 1);

    return in_array($firstDigit, ['1', '2', '3', '4'], true) ? $firstDigit : 'other';
}

$lines = fetchLinesFromDatabase($conn);
$areaCounts = [
    'all' => count($lines),
    '1' => 0,
    '2' => 0,
    '3' => 0,
    '4' => 0,
];
$typeCounts = [
    'all' => count($lines),
    'local' => 0,
    'regional' => 0,
    'intermunicipal' => 0,
];

foreach ($lines as $line) {
    if (isset($areaCounts[$line['area']])) {
        $areaCounts[$line['area']]++;
    }
    if (isset($typeCounts[$line['type']])) {
        $typeCounts[$line['type']]++;
    }
}

$linesJson = json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Linhas Carris Metropolitana</title>
    <script>
        (function() {
            try {
                const darkMode = localStorage.getItem('urban_dark_mode') === 'true';
                const language = localStorage.getItem('urban_language') || 'pt';
                document.documentElement.classList.toggle('dark-mode', darkMode);
                document.documentElement.setAttribute('data-language', language);
            } catch (error) {}
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        :root {
            --verde-urbano: #4CAF50;
            --cinza-urbano: #5A6B7A;
            --cinza-claro: #E8ECF1;
            --preto-suave: #2C3E50;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cinza-claro);
            color: var(--preto-suave);
        }

        .lines-toolbar {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 18px;
        }

        .area-filter {
            border: 1px solid #dbe4ea;
            background: #fff;
            color: var(--preto-suave);
            border-radius: 8px;
            padding: 9px 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .area-filter:hover,
        .area-filter.active {
            background: var(--verde-urbano);
            border-color: var(--verde-urbano);
            color: #fff;
        }

        .area-filter .badge {
            background: rgba(44, 62, 80, 0.12);
            color: inherit;
        }

        .line-card {
            border: none;
            border-left: 5px solid var(--line-color, var(--verde-urbano));
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }

        .line-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(44, 62, 80, 0.14);
        }

        .line-number {
            min-width: 76px;
            height: 58px;
            border-radius: 8px;
            background: var(--line-color, var(--verde-urbano));
            color: var(--line-text-color, #fff);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1;
        }

        .line-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .line-kind {
            border: 1px solid color-mix(in srgb, var(--line-color, var(--verde-urbano)) 40%, #ffffff);
            background: color-mix(in srgb, var(--line-color, var(--verde-urbano)) 12%, #ffffff);
            color: #24313a;
            border-radius: 8px;
            padding: 3px 8px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .line-path {
            font-weight: 600;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .line-meta {
            color: var(--cinza-urbano);
            font-size: 0.92rem;
        }

        .btn-outline-urbano {
            border: 1px solid var(--verde-urbano);
            color: var(--verde-urbano);
            background: #fff;
            border-radius: 8px;
            font-weight: 600;
            padding: 8px 14px;
        }

        .btn-outline-urbano:hover {
            background: var(--verde-urbano);
            color: #fff;
        }

        .details-line-number {
            min-width: 70px;
            height: 52px;
            border-radius: 8px;
            background: var(--verde-urbano);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            font-weight: 700;
            font-size: 1.45rem;
        }

        .stops-list {
            max-height: 230px;
            overflow-y: auto;
            border: 1px solid #e7ecef;
            border-radius: 8px;
            padding: 12px 16px;
        }

        .stops-list li {
            margin: 7px 0;
        }

        #routeMap {
            height: min(68vh, 560px);
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e7ecef;
            background: #e8ecf1;
        }

        .route-map-shell {
            position: relative;
        }

        .map-toggle-container {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .map-toggle-container .btn {
            padding: 6px 12px;
            font-size: 12px;
        }

        .map-toggle-container .btn.active {
            background: var(--verde-urbano);
            color: #fff;
        }

        @media (max-width: 768px) {
            .line-number {
                min-width: 64px;
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body class="ut-page ut-page-lines">
    <?php include 'partials/navbar.php'; ?>

    <section class="page-header">
        <div class="container text-center">
            <h1 class="h2 mb-2">
                <i class="fas fa-bus me-2"></i>
                <span data-i18n="linesTitle">Linhas Carris Metropolitana</span>
            </h1>
            <p class="mb-0">Consulte percursos, paragens e mapa de cada linha</p>
        </div>
    </section>

    <main class="container my-5">
        <div class="lines-toolbar mb-4 ut-panel">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold" for="lineSearch">
                        <i class="fas fa-search me-2" style="color: var(--verde-urbano);"></i>
                        Pesquisar linha
                    </label>
                    <input class="form-control form-control-lg ut-input" id="lineSearch" type="search" placeholder="Ex: 3702, Lisboa, Almada...">
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="line-meta mt-lg-4">
                        <span class="ut-badge ut-badge-primary me-2" id="lineCount"><?php echo count($lines); ?></span>
                        linhas disponíveis
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3" id="areaFilters">
                <button class="area-filter active" type="button" data-area="all">
                    Todas <span class="badge ms-1"><?php echo $areaCounts['all']; ?></span>
                </button>
                <button class="area-filter" type="button" data-area="1">
                    Área 1 <span class="badge ms-1"><?php echo $areaCounts['1']; ?></span>
                </button>
                <button class="area-filter" type="button" data-area="2">
                    Área 2 <span class="badge ms-1"><?php echo $areaCounts['2']; ?></span>
                </button>
                <button class="area-filter" type="button" data-area="3">
                    Área 3 <span class="badge ms-1"><?php echo $areaCounts['3']; ?></span>
                </button>
                <button class="area-filter" type="button" data-area="4">
                    Área 4 <span class="badge ms-1"><?php echo $areaCounts['4']; ?></span>
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3" id="typeFilters">
                <button class="area-filter active" type="button" data-type="all">
                    Todos os tipos <span class="badge ms-1"><?php echo $typeCounts['all']; ?></span>
                </button>
                <button class="area-filter" type="button" data-type="local">
                    Local <span class="badge ms-1"><?php echo $typeCounts['local']; ?></span>
                </button>
                <button class="area-filter" type="button" data-type="regional">
                    Regional <span class="badge ms-1"><?php echo $typeCounts['regional']; ?></span>
                </button>
                <button class="area-filter" type="button" data-type="intermunicipal">
                    Intermunicipal <span class="badge ms-1"><?php echo $typeCounts['intermunicipal']; ?></span>
                </button>
            </div>
        </div>

        <?php if (empty($lines)): ?>
            <div class="alert alert-warning border-0 shadow-sm">
                <h2 class="h5">
                    <i class="fas fa-triangle-exclamation me-2"></i>
                    Nenhuma linha encontrada
                </h2>
                <p class="mb-0">Confirme se o MySQL está ligado e se as tabelas GTFS têm dados em routes, trips, stop_times e stops.</p>
            </div>
        <?php else: ?>
            <div class="row g-4" id="linesGrid"></div>
        <?php endif; ?>
    </main>

    <div class="modal fade" id="lineDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex gap-3 align-items-center">
                        <span class="details-line-number" id="detailsNumber"></span>
                        <div>
                            <h2 class="h5 mb-1" id="detailsTitle"></h2>
                            <div class="text-muted" id="detailsRoute"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <small class="text-muted d-block">Total de paragens</small>
                            <strong id="detailsStopsCount"></strong>
                        </div>
                        <button class="btn btn-urbano ut-btn ut-btn-primary" id="showMapBtn" type="button">
                            <i class="fas fa-map-location-dot me-2"></i>Ver rota no mapa
                        </button>
                    </div>
                    <ol class="stops-list mb-0" id="detailsStopsList"></ol>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="routeMapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="h5 mb-1" id="mapTitle"></h2>
                        <div class="text-muted" id="mapSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body route-map-shell">
                    <div id="routeMap" class="ut-map-canvas"></div>
                    <div class="map-toggle-container ut-floating-controls">
                        <div class="btn-group" role="group" aria-label="Tipo de mapa">
                            <button type="button" class="btn btn-sm btn-light active" id="lineMapViewDefault">
                                <i class="fas fa-map"></i> Mapa
                            </button>
                            <button type="button" class="btn btn-sm btn-light" id="lineMapViewSatellite">
                                <i class="fas fa-satellite"></i> Satélite
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'partials/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/preferences.js?v=20260427a"></script>
    <script src="js/map.js?v=20260427b"></script>
    <script src="js/auth.js?v=20260427h"></script>

    <script>
        const transportLines = <?php echo $linesJson ?: '[]'; ?>;
        let selectedLine = null;
        let routeMap = null;
        let routeMapLayers = [];
        let routeMapLayerType = 'default';
        let activeArea = 'all';
        let activeType = 'all';

        const detailsModal = new bootstrap.Modal(document.getElementById('lineDetailsModal'));
        const mapModal = new bootstrap.Modal(document.getElementById('routeMapModal'));

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function lineStyle(line) {
            return `--line-color: ${line.color || '#4CAF50'}; --line-text-color: ${line.text_color || '#FFFFFF'};`;
        }

        function renderBadges(line) {
            const badges = line.badges && line.badges.length ? line.badges : [line.type_label || 'Regular'];
            return `<div class="line-badges">${badges.map(badge => `<span class="line-kind">${escapeHtml(badge)}</span>`).join('')}</div>`;
        }

        function renderLineCards(lines) {
            const grid = document.getElementById('linesGrid');
            const count = document.getElementById('lineCount');

            if (count) {
                count.textContent = lines.length;
            }

            if (!grid) return;

            if (!lines.length) {
                grid.innerHTML = `
                    <div class="col-12">
                        <div class="ut-empty-state mb-0">
                            <i class="fas fa-bus"></i>
                            <p class="mb-0">Nenhuma linha corresponde à pesquisa.</p>
                        </div>
                    </div>
                `;
                return;
            }

            grid.innerHTML = lines.map(line => `
                <div class="col-md-6 col-xl-4">
                    <article class="line-card p-4" style="${lineStyle(line)}">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="line-number">${escapeHtml(line.numero)}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="line-path">${escapeHtml(line.origem)} → ${escapeHtml(line.destino)}</div>
                                <div class="line-meta mt-2">
                                    <i class="fas fa-location-dot me-1"></i>${line.stops_count || line.paragens.length || '...'} paragens
                                    <span class="ms-2">Área ${escapeHtml(line.area)}</span>
                                </div>
                                ${renderBadges(line)}
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button class="btn-outline-urbano ut-btn ut-btn-secondary" type="button" data-line-id="${escapeHtml(line.id)}">
                                Ver detalhes
                            </button>
                        </div>
                    </article>
                </div>
            `).join('');
        }

        async function ensureLineDetails(line) {
            if (!line || (line.paragens && line.paragens.length > 0)) {
                return line;
            }

            const response = await fetch(`/urban/public/api/line/stops?route_id=${encodeURIComponent(line.id)}`);
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || data.error || 'Não foi possível carregar paragens');
            }

            line.paragens = data.stops || [];
            line.coordenadas = data.coordinates || line.paragens.map(stop => [stop.lat, stop.lng]);
            line.geometry_source = data.geometry_source || 'stops';
            line.shape_id = data.shape_id || null;
            line.stops_count = line.paragens.length;
            return line;
        }

        async function openLineDetails(lineId) {
            selectedLine = transportLines.find(line => String(line.id) === String(lineId));
            if (!selectedLine) return;

            document.getElementById('detailsNumber').textContent = selectedLine.numero;
            document.getElementById('detailsNumber').style.background = selectedLine.color || '#4CAF50';
            document.getElementById('detailsNumber').style.color = selectedLine.text_color || '#FFFFFF';
            document.getElementById('detailsTitle').textContent = `Linha ${selectedLine.numero}`;
            document.getElementById('detailsRoute').textContent = `${selectedLine.origem} → ${selectedLine.destino}`;
            document.getElementById('detailsStopsCount').textContent = 'A carregar...';
            document.getElementById('detailsStopsList').innerHTML = '<li>A carregar paragens...</li>';

            detailsModal.show();

            try {
                selectedLine = await ensureLineDetails(selectedLine);
                if (selectedLine.paragens.length >= 2) {
                    selectedLine.origem = selectedLine.paragens[0].nome;
                    selectedLine.destino = selectedLine.paragens[selectedLine.paragens.length - 1].nome;
                }

                document.getElementById('detailsRoute').textContent = `${selectedLine.origem} → ${selectedLine.destino}`;
                document.getElementById('detailsStopsCount').textContent = selectedLine.paragens.length;
                document.getElementById('detailsStopsList').innerHTML = selectedLine.paragens
                    .map(stop => `<li>${escapeHtml(stop.nome)}</li>`)
                    .join('');
            } catch (error) {
                document.getElementById('detailsStopsCount').textContent = '--';
                document.getElementById('detailsStopsList').innerHTML = `<li class="text-danger">${escapeHtml(error.message)}</li>`;
            }
        }

        function clearMapLayers() {
            routeMapLayers.forEach(layer => layer.remove());
            routeMapLayers = [];
        }

        function openRouteMap() {
            if (!selectedLine || !selectedLine.coordenadas.length) return;

            document.getElementById('mapTitle').textContent = `Linha ${selectedLine.numero}`;
            const source = selectedLine.geometry_source === 'shape' ? 'traçado GTFS' : 'paragens';
            document.getElementById('mapSubtitle').textContent = `${selectedLine.origem} → ${selectedLine.destino} · ${source}`;
            mapModal.show();
        }

        document.getElementById('routeMapModal').addEventListener('shown.bs.modal', () => {
            if (!selectedLine) return;

            if (!routeMap) {
                routeMap = window.UrbanMap
                    ? window.UrbanMap.createMap('routeMap', { layer: routeMapLayerType })
                    : L.map('routeMap').setView([38.7223, -9.1393], 13);
            }

            routeMap.invalidateSize();
            clearMapLayers();

            const routeLine = window.UrbanMap
                ? window.UrbanMap.drawPolyline(routeMap, selectedLine.coordenadas, {
                    color: selectedLine.color || '#4CAF50',
                    weight: 5,
                    opacity: 0.9,
                    fit: false
                })
                : L.polyline(selectedLine.coordenadas, {
                    color: selectedLine.color || '#4CAF50',
                    weight: 5,
                    opacity: 0.9
                }).addTo(routeMap);

            routeMapLayers.push(routeLine);

            selectedLine.paragens.forEach((stop, index) => {
                const isFirst = index === 0;
                const isLast = index === selectedLine.paragens.length - 1;
                const marker = L.circleMarker([stop.lat, stop.lng], {
                    radius: isFirst || isLast ? 7 : 5,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: isFirst ? (selectedLine.color || '#4CAF50') : (isLast ? '#2C3E50' : '#5A6B7A'),
                    fillOpacity: 1
                }).bindPopup(`<strong>${escapeHtml(stop.nome)}</strong>`);

                marker.addTo(routeMap);
                routeMapLayers.push(marker);
            });

            routeMap.fitBounds(routeLine.getBounds(), { padding: [35, 35] });
        });

        function setLineMapLayer(type) {
            routeMapLayerType = type;
            if (routeMap && window.UrbanMap) {
                window.UrbanMap.setTileLayer(routeMap, type);
            }

            document.getElementById('lineMapViewDefault').classList.toggle('active', type === 'default');
            document.getElementById('lineMapViewSatellite').classList.toggle('active', type === 'satellite');
        }

        document.getElementById('lineMapViewDefault').addEventListener('click', () => setLineMapLayer('default'));
        document.getElementById('lineMapViewSatellite').addEventListener('click', () => setLineMapLayer('satellite'));

        document.addEventListener('click', event => {
            const button = event.target.closest('[data-line-id]');
            if (!button) return;
            openLineDetails(button.dataset.lineId);
        });

        document.getElementById('showMapBtn').addEventListener('click', openRouteMap);

        function applyFilters() {
            const query = document.getElementById('lineSearch').value.trim().toLowerCase();
            const filtered = transportLines.filter(line => {
                const matchesArea = activeArea === 'all' || String(line.area) === activeArea;
                const matchesType = activeType === 'all' || String(line.type) === activeType;
                const matchesSearch = !query
                    || String(line.numero).toLowerCase().includes(query)
                    || String(line.nome).toLowerCase().includes(query)
                    || String(line.type_label).toLowerCase().includes(query)
                    || (line.badges || []).some(badge => String(badge).toLowerCase().includes(query))
                    || String(line.origem).toLowerCase().includes(query)
                    || String(line.destino).toLowerCase().includes(query);

                return matchesArea && matchesType && matchesSearch;
            });

            renderLineCards(filtered);
        }

        document.getElementById('lineSearch').addEventListener('input', applyFilters);

        document.getElementById('areaFilters').addEventListener('click', event => {
            const button = event.target.closest('[data-area]');
            if (!button) return;

            activeArea = button.dataset.area;
            document.querySelectorAll('.area-filter').forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            applyFilters();
        });

        document.getElementById('typeFilters').addEventListener('click', event => {
            const button = event.target.closest('[data-type]');
            if (!button) return;

            activeType = button.dataset.type;
            document.querySelectorAll('#typeFilters .area-filter').forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            applyFilters();
        });

        renderLineCards(transportLines);
    </script>
</body>
</html>
