<?php
/**
 * GtfsRouteService — roteamento tipo “apps de transportes públicos” para rede GTFS
 * (otimizado para Carris Metropolitana: dados estáticos GTFS + API em tempo real).
 *
 * Motor principal: Dijkstra temporal no grafo (viagens + caminhadas curtas entre paragens).
 * Suporta vários transbordos, caminhada entre stops próximos (transferência “humana”),
 * tempos GTFS > 24h e filtro de serviços por calendar + calendar_dates.
 */

require_once __DIR__ . '/GtfsRealtimeService.php';

class GtfsRouteService {

    private $pdo;
    private $stopsCache = [];
    private $tripStopsCache = [];
    private $walkingNeighborsCache = [];
    private $geometryCache = [];
    private $realtimeService;
    private $requestStartedAt = 0.0;
    private $debugStats = [];
    private $lastDebugPayload = [];
    private $boardingTripsCache = [];
    private $tripDeparturesCache = [];
    private $tripSegmentCache = [];
    private $tripStopSequenceIndexCache = [];
    private $stopGoalDistanceCache = [];
    private $stopTransferPotentialCache = [];
    private $currentRouteBudgetSeconds = self::MAX_ROUTE_CALCULATION_SECONDS;
    private $lastSearchTimedOut = false;

    /** @var float Velocidade caminhada (m/s) ~ 5 km/h */
    private const WALK_SPEED_MPS = 1.39;

    /** Raio máximo para considerar caminhada entre paragens (transferência) — metros */
    private const MAX_TRANSFER_WALK_METERS = 550;

    /** Tempo mínimo após descer de um autocarro antes de embarcar noutro — segundos */
    private const MIN_TRANSFER_SECONDS = 90;

    /** Buffer após chegar a pé a uma paragem antes de embarcar — segundos */
    private const BOARD_AFTER_WALK_SECONDS = 45;

    /** Máximo de expansões na fila (segurança) */
    private const MAX_DIJKSTRA_POPS = 80000;

    /** Quantas alternativas tentar gerar */
    private const MAX_ALTERNATIVES = 5;

    /** Quantas rotas boas bastam para terminar cedo a pesquisa */
    private const EARLY_GOOD_ROUTES_TARGET = 3;

    /** Limite de viagens candidatas avaliadas por paragem num “pop” (performance) */
    private const MAX_TRIPS_PER_STOP_SCAN = 48;

    /** Janela inicial para procurar partidas a partir da hora escolhida */
    private const SEARCH_WINDOW_SECONDS = 7200;

    /** Janela de fallback para madrugada/fim de serviço, para encontrar o próximo autocarro útil */
    private const EXTENDED_SEARCH_WINDOW_SECONDS = 21600;

    /** Quantidade máxima de candidatos que entram na fase de montagem da resposta */
    private const MAX_LEG_CANDIDATES = 24;

    /** Tempo máximo por cálculo de rota para não bloquear a demo */
    private const MAX_ROUTE_CALCULATION_SECONDS = 6.0;

    /** Limites por fase para evitar explosão combinatória */
    private const MAX_DIRECT_PATHS = 4;
    private const MAX_ONE_TRANSFER_PATHS = 6;
    private const MAX_TWO_TRANSFER_PATHS = 4;
    private const MAX_TRANSFER_OPTIONS_PER_STOP = 6;
    private const MAX_BOARDING_ROWS = 28;
    private const MAX_SEGMENT_STOPS_SCAN = 60;
    private const MAX_DIRECTIONS_REFINEMENTS = 2;
    private const DIJKSTRA_DEPARTURE_WINDOW_SECONDS = 10800;
    private const DEPARTURE_CACHE_BUCKET_SECONDS = 300;
    private const MAX_STOP_POPS_PER_SEARCH = 2;
    private const MAX_TRIP_EXPANSIONS_PER_SEARCH = 2;
    private const MAX_DIJKSTRA_ALIGHT_OPTIONS = 16;
    private const ASSUMED_TRANSIT_SPEED_MPS = 7.8;
    private const MAX_DESTINATION_DETOUR_METERS = 2600;

    /** Número máximo de coordenadas a enviar para a API Directions */
    private const MAPBOX_MAX_WAYPOINTS = 12;

    /** Número máximo de paragens amostradas para validar encaixe da shape */
    private const MAX_SHAPE_VALIDATION_STOPS = 8;

    /** Distância máxima considerada "coberta" por uma shape */
    private const MAX_SHAPE_STOP_DISTANCE_METERS = 280;

    /** Percentagem mínima de paragens cobertas pela shape */
    private const MIN_SHAPE_STOP_COVERAGE_RATIO = 0.70;

    /** Distância máxima média stop->shape ao longo do segmento */
    private const MAX_SHAPE_AVERAGE_DISTANCE_METERS = 170;

    /** Distância máxima do primeiro/último stop à shape */
    private const MAX_SHAPE_ENDPOINT_DISTANCE_METERS = 300;

    /** Número mínimo de pontos na shape para a considerar útil */
    private const MIN_SHAPE_POINTS = 6;

    /** Comprimento mínimo aceitável da shape vs. percurso pelas paragens */
    private const MIN_SHAPE_LENGTH_RATIO = 0.60;

    /** Comprimento máximo aceitável da shape vs. percurso pelas paragens */
    private const MAX_SHAPE_LENGTH_RATIO = 4.20;
    private const STRAIGHT_LINE_ROUTE_PENALTY = 7.5;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->realtimeService = new GtfsRealtimeService($pdo);
    }

    private function getMapboxAccessToken(): string {
        $token = getenv('URBAN_MAPBOX_TOKEN');
        return is_string($token) ? trim($token) : '';
    }

    /**
     * Itinerários entre duas paragens GTFS.
     *
     * @param string $fromStopId
     * @param string $toStopId
     * @param string|null $departureTime H:i:s (default: agora)
     * @param string|null $travelDate Y-m-d (default: hoje)
     * @return array Lista de rotas formatadas para a UI (mesmo contrato antigo)
     */
    public function findRoutes($fromStopId, $toStopId, $departureTime = null, $travelDate = null) {
        $this->requestStartedAt = microtime(true);
        $this->resetDebugStats($fromStopId, $toStopId);
        $this->boardingTripsCache = [];
        $this->tripDeparturesCache = [];
        $this->tripSegmentCache = [];
        $this->stopGoalDistanceCache = [];
        $this->stopTransferPotentialCache = [];
        $this->lastSearchTimedOut = false;

        if (!$departureTime) {
            $departureTime = date('H:i:s');
        }

        if (!$travelDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$travelDate)) {
            $travelDate = date('Y-m-d');
        }

        $fromCoords = $this->getStopCoords($fromStopId);
        $toCoords = $this->getStopCoords($toStopId);
        if (!$fromCoords || !$toCoords) {
            $this->logDebugSummary('missing_stop_coords', 0, 0);
            return [];
        }

        $originDistanceMeters = $this->haversineMeters(
            (float)$fromCoords['stop_lat'],
            (float)$fromCoords['stop_lon'],
            (float)$toCoords['stop_lat'],
            (float)$toCoords['stop_lon']
        );
        $this->currentRouteBudgetSeconds = $originDistanceMeters >= 6500 ? 6.4 : self::MAX_ROUTE_CALCULATION_SECONDS;

        $serviceIds = $this->getActiveServiceIdsForDate($travelDate);
        $depSec = $this->gtfsTimeToSeconds($departureTime);

        $paths = $this->findCandidatePaths($fromStopId, $toStopId, $depSec, $serviceIds, self::SEARCH_WINDOW_SECONDS);

        if (empty($paths)) {
            $paths = $this->findCandidatePaths($fromStopId, $toStopId, $depSec, $serviceIds, self::EXTENDED_SEARCH_WINDOW_SECONDS);
        }

        $alternatives = [];
        foreach ($paths as $path) {
            $route = $this->pathToInternalRoute($path);
            if ($route) {
                $alternatives[] = $route;
                if (count($alternatives) >= self::EARLY_GOOD_ROUTES_TARGET && !$this->shouldKeepSearchingAlternatives($alternatives)) {
                    break;
                }
            }
        }

        if (empty($alternatives)) {
            $this->logDebugSummary($this->lastSearchTimedOut ? 'timeout_no_route' : 'no_alternatives', count($paths), 0);
            return [];
        }

        $result = $this->selectBestOptions($alternatives);
        $this->logDebugSummary('success', count($paths), count($result));
        return $result;
    }

    private function findCandidatePaths(string $fromStopId, string $toStopId, int $depSec, array $serviceIds, int $windowSeconds): array {
        $paths = [];
        $this->markStage('direct_start');
        foreach ($this->findDirectCandidatePaths($fromStopId, $toStopId, $depSec, $serviceIds, self::MAX_DIRECT_PATHS, $windowSeconds) as $path) {
            $paths[] = $path;
        }
        $this->markStage('direct_done');
        if ($this->shouldStopCandidateSearch($paths, true)) {
            return $this->dedupeAndSortPaths($paths);
        }

        if (!$this->hasTimedOut()) {
            $this->markStage('one_transfer_start');
            foreach ($this->findOneTransferCandidatePaths($fromStopId, $toStopId, $depSec, $serviceIds, self::MAX_ONE_TRANSFER_PATHS, $windowSeconds) as $path) {
                $paths[] = $path;
                if ($this->shouldStopCandidateSearch($paths, false)) {
                    break;
                }
            }
            $this->markStage('one_transfer_done');
        }
        if ($this->shouldStopCandidateSearch($paths, false)) {
            return $this->dedupeAndSortPaths($paths);
        }

        if (!$this->hasTimedOut() && count($paths) < 2) {
            $this->markStage('dijkstra_start');
            foreach ($this->findDijkstraCandidatePaths($fromStopId, $toStopId, $depSec, $serviceIds, 2) as $path) {
                $paths[] = $path;
                if ($this->shouldStopCandidateSearch($paths, false)) {
                    break;
                }
            }
            $this->markStage('dijkstra_done');
        }

        if (
            !$this->hasTimedOut()
            && empty($paths)
            && (microtime(true) - $this->requestStartedAt) < (self::MAX_ROUTE_CALCULATION_SECONDS * 0.6)
        ) {
            $this->markStage('two_transfer_start');
            foreach ($this->findTwoTransferCandidatePaths($fromStopId, $toStopId, $depSec, $serviceIds, self::MAX_TWO_TRANSFER_PATHS, $windowSeconds) as $path) {
                $paths[] = $path;
                if ($this->shouldStopCandidateSearch($paths, false)) {
                    break;
                }
            }
            $this->markStage('two_transfer_done');
        }

        return $this->dedupeAndSortPaths($paths);
    }

    private function findDirectCandidatePaths(string $fromStopId, string $toStopId, int $depSec, array $serviceIds, int $limit, int $windowSeconds = self::SEARCH_WINDOW_SECONDS): array {
        $rows = $this->fetchDirectLegs($fromStopId, $toStopId, $depSec, $depSec + $windowSeconds, $serviceIds, $limit);
        $paths = [];

        foreach ($rows as $row) {
            $segment = $this->fetchTripSegment($row['trip_id'], (int)$row['board_sequence'], (int)$row['alight_sequence']);
            if (count($segment) < 2) {
                continue;
            }

            $paths[] = [
                'legs' => [[
                    'mode' => 'ride',
                    'trip_id' => $row['trip_id'],
                    'route_id' => $row['route_id'],
                    'route_short_name' => $row['route_short_name'],
                    'shape_id' => $row['shape_id'],
                    'segment' => $segment,
                    'board_stop_id' => $fromStopId,
                ]],
                'arrival_sec' => $this->gtfsTimeToSeconds($row['arrival_time'] ?? $row['alight_departure_time'] ?? '0:00:00'),
            ];
        }

        return $paths;
    }

    private function findOneTransferCandidatePaths(string $fromStopId, string $toStopId, int $depSec, array $serviceIds, int $limit, int $windowSeconds = self::SEARCH_WINDOW_SECONDS): array {
        $firstLegs = $this->buildReachableLegsFromBoardings($fromStopId, $depSec, $depSec + $windowSeconds, $serviceIds, self::MAX_LEG_CANDIDATES);
        $paths = [];
        $seen = [];

        foreach ($firstLegs as $first) {
            if ($this->hasTimedOut()) {
                break;
            }

            if (count($paths) >= $limit) {
                break;
            }

            $firstArrivalSec = $this->gtfsTimeToSeconds($first['arrival_time'] ?? $first['alight_departure_time'] ?? '0:00:00');
            $transferStops = [['stop_id' => $first['alight_stop_id'], 'walk_seconds' => 0]];

            foreach ($this->getWalkingNeighbors($first['alight_stop_id']) as $neighbor) {
                if ($neighbor['walk_seconds'] <= 300) {
                    $transferStops[] = $neighbor;
                }
            }

            foreach ($transferStops as $transferStop) {
                if ($this->hasTimedOut()) {
                    break 2;
                }

                if (count($paths) >= $limit) {
                    break;
                }

                $walkSeconds = (int)($transferStop['walk_seconds'] ?? 0);
                $secondMinDepart = $firstArrivalSec + self::MIN_TRANSFER_SECONDS + $walkSeconds;
                $secondLegs = $this->fetchDirectLegs($transferStop['stop_id'], $toStopId, $secondMinDepart, $secondMinDepart + $windowSeconds, $serviceIds, 2);

                foreach ($secondLegs as $second) {
                    $this->incrementCombinations();
                    if ($second['trip_id'] === $first['trip_id']) {
                        continue;
                    }

                    $key = $first['trip_id'] . '|' . $first['alight_stop_id'] . '|' . $transferStop['stop_id'] . '|' . $second['trip_id'];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $firstSegment = $this->fetchTripSegment($first['trip_id'], (int)$first['board_sequence'], (int)$first['alight_sequence']);
                    $secondSegment = $this->fetchTripSegment($second['trip_id'], (int)$second['board_sequence'], (int)$second['alight_sequence']);
                    if (count($firstSegment) < 2 || count($secondSegment) < 2) {
                        continue;
                    }

                    $legs = [[
                        'mode' => 'ride',
                        'trip_id' => $first['trip_id'],
                        'route_id' => $first['route_id'],
                        'route_short_name' => $first['route_short_name'],
                        'shape_id' => $first['shape_id'],
                        'segment' => $firstSegment,
                        'board_stop_id' => $fromStopId,
                    ]];

                    if ($transferStop['stop_id'] !== $first['alight_stop_id']) {
                        $legs[] = [
                            'mode' => 'walk',
                            'from' => $first['alight_stop_id'],
                            'to' => $transferStop['stop_id'],
                        ];
                    }

                    $legs[] = [
                        'mode' => 'ride',
                        'trip_id' => $second['trip_id'],
                        'route_id' => $second['route_id'],
                        'route_short_name' => $second['route_short_name'],
                        'shape_id' => $second['shape_id'],
                        'segment' => $secondSegment,
                        'board_stop_id' => $transferStop['stop_id'],
                    ];

                    $paths[] = [
                        'legs' => $legs,
                        'arrival_sec' => $this->gtfsTimeToSeconds($second['arrival_time'] ?? $second['alight_departure_time'] ?? '0:00:00'),
                    ];
                }
            }
        }

        usort($paths, fn($a, $b) => ($a['arrival_sec'] ?? PHP_INT_MAX) <=> ($b['arrival_sec'] ?? PHP_INT_MAX));
        return array_slice($paths, 0, $limit);
    }

    private function findTwoTransferCandidatePaths(string $fromStopId, string $toStopId, int $depSec, array $serviceIds, int $limit, int $windowSeconds = self::SEARCH_WINDOW_SECONDS): array {
        $firstLegs = $this->buildReachableLegsFromBoardings($fromStopId, $depSec, $depSec + $windowSeconds, $serviceIds, self::MAX_LEG_CANDIDATES);
        $paths = [];
        $seen = [];

        foreach ($firstLegs as $first) {
            if ($this->hasTimedOut()) {
                break;
            }

            if (count($paths) >= $limit) {
                break;
            }

            $firstArrivalSec = $this->gtfsTimeToSeconds($first['arrival_time'] ?? $first['alight_departure_time'] ?? '0:00:00');
            $firstTransferStops = $this->buildTransferStopOptions($first['alight_stop_id'], 360);

            foreach ($firstTransferStops as $firstTransferStop) {
                if ($this->hasTimedOut()) {
                    break 2;
                }

                if (count($paths) >= $limit) {
                    break;
                }

                $firstWalkSeconds = (int)($firstTransferStop['walk_seconds'] ?? 0);
                $secondMinDepart = $firstArrivalSec + self::MIN_TRANSFER_SECONDS + $firstWalkSeconds;
                $secondLegs = $this->buildReachableLegsFromBoardings(
                    $firstTransferStop['stop_id'],
                    $secondMinDepart,
                    $secondMinDepart + $windowSeconds,
                    $serviceIds,
                    max(18, intdiv(self::MAX_LEG_CANDIDATES, 2))
                );

                foreach ($secondLegs as $second) {
                    if ($this->hasTimedOut()) {
                        break 3;
                    }

                    if (count($paths) >= $limit) {
                        break 2;
                    }

                    if ($second['trip_id'] === $first['trip_id']) {
                        continue;
                    }

                    $secondArrivalSec = $this->gtfsTimeToSeconds($second['arrival_time'] ?? $second['alight_departure_time'] ?? '0:00:00');
                    $secondTransferStops = $this->buildTransferStopOptions($second['alight_stop_id'], 420);

                    foreach ($secondTransferStops as $secondTransferStop) {
                        if ($this->hasTimedOut()) {
                            break 4;
                        }

                        $secondWalkSeconds = (int)($secondTransferStop['walk_seconds'] ?? 0);
                        $thirdMinDepart = $secondArrivalSec + self::MIN_TRANSFER_SECONDS + $secondWalkSeconds;
                        $thirdLegs = $this->fetchDirectLegs($secondTransferStop['stop_id'], $toStopId, $thirdMinDepart, $thirdMinDepart + $windowSeconds, $serviceIds, 2);

                        foreach ($thirdLegs as $third) {
                            $this->incrementCombinations();
                            if ($third['trip_id'] === $first['trip_id'] || $third['trip_id'] === $second['trip_id']) {
                                continue;
                            }

                            $key = implode('|', [
                                $first['trip_id'],
                                $firstTransferStop['stop_id'],
                                $second['trip_id'],
                                $secondTransferStop['stop_id'],
                                $third['trip_id'],
                            ]);
                            if (isset($seen[$key])) {
                                continue;
                            }
                            $seen[$key] = true;

                            $firstSegment = $this->fetchTripSegment($first['trip_id'], (int)$first['board_sequence'], (int)$first['alight_sequence']);
                            $secondSegment = $this->fetchTripSegment($second['trip_id'], (int)$second['board_sequence'], (int)$second['alight_sequence']);
                            $thirdSegment = $this->fetchTripSegment($third['trip_id'], (int)$third['board_sequence'], (int)$third['alight_sequence']);

                            if (count($firstSegment) < 2 || count($secondSegment) < 2 || count($thirdSegment) < 2) {
                                continue;
                            }

                            $legs = [[
                                'mode' => 'ride',
                                'trip_id' => $first['trip_id'],
                                'route_id' => $first['route_id'],
                                'route_short_name' => $first['route_short_name'],
                                'shape_id' => $first['shape_id'],
                                'segment' => $firstSegment,
                                'board_stop_id' => $fromStopId,
                            ]];

                            if ($firstTransferStop['stop_id'] !== $first['alight_stop_id']) {
                                $legs[] = [
                                    'mode' => 'walk',
                                    'from' => $first['alight_stop_id'],
                                    'to' => $firstTransferStop['stop_id'],
                                ];
                            }

                            $legs[] = [
                                'mode' => 'ride',
                                'trip_id' => $second['trip_id'],
                                'route_id' => $second['route_id'],
                                'route_short_name' => $second['route_short_name'],
                                'shape_id' => $second['shape_id'],
                                'segment' => $secondSegment,
                                'board_stop_id' => $firstTransferStop['stop_id'],
                            ];

                            if ($secondTransferStop['stop_id'] !== $second['alight_stop_id']) {
                                $legs[] = [
                                    'mode' => 'walk',
                                    'from' => $second['alight_stop_id'],
                                    'to' => $secondTransferStop['stop_id'],
                                ];
                            }

                            $legs[] = [
                                'mode' => 'ride',
                                'trip_id' => $third['trip_id'],
                                'route_id' => $third['route_id'],
                                'route_short_name' => $third['route_short_name'],
                                'shape_id' => $third['shape_id'],
                                'segment' => $thirdSegment,
                                'board_stop_id' => $secondTransferStop['stop_id'],
                            ];

                            $paths[] = [
                                'legs' => $legs,
                                'arrival_sec' => $this->gtfsTimeToSeconds($third['arrival_time'] ?? $third['alight_departure_time'] ?? '0:00:00'),
                            ];

                            if (count($paths) >= $limit) {
                                break 4;
                            }
                        }
                    }
                }
            }
        }

        usort($paths, fn($a, $b) => ($a['arrival_sec'] ?? PHP_INT_MAX) <=> ($b['arrival_sec'] ?? PHP_INT_MAX));
        return array_slice($paths, 0, $limit);
    }

    private function findDijkstraCandidatePaths(string $fromStopId, string $toStopId, int $depSec, array $serviceIds, int $limit): array {
        $paths = [];
        $forbiddenTripIds = [];

        for ($i = 0; $i < $limit; $i++) {
            if ($this->hasTimedOut()) {
                break;
            }

            $path = $this->findShortestPath($fromStopId, $toStopId, $depSec, $serviceIds, $forbiddenTripIds);
            if (!$path) {
                break;
            }

            $paths[] = $path;

            foreach ($path['legs'] as $leg) {
                if (($leg['mode'] ?? '') === 'ride' && !empty($leg['trip_id'])) {
                    $forbiddenTripIds[$leg['trip_id']] = true;
                    break;
                }
            }
        }

        return $paths;
    }

    private function buildTransferStopOptions(string $stopId, int $maxWalkSeconds): array {
        $options = [['stop_id' => $stopId, 'walk_seconds' => 0]];
        foreach ($this->getWalkingNeighbors($stopId) as $neighbor) {
            if (($neighbor['walk_seconds'] ?? PHP_INT_MAX) <= $maxWalkSeconds) {
                $options[] = $neighbor;
            }
        }
        return array_slice($options, 0, self::MAX_TRANSFER_OPTIONS_PER_STOP);
    }

    private function dedupeAndSortPaths(array $paths): array {
        $unique = [];

        foreach ($paths as $path) {
            if (empty($path['legs']) || !is_array($path['legs'])) {
                continue;
            }

            $signatureParts = [];
            foreach ($path['legs'] as $leg) {
                if (($leg['mode'] ?? '') === 'ride') {
                    $signatureParts[] = 'ride:' . ($leg['route_id'] ?? '') . ':' . ($leg['trip_id'] ?? '');
                } elseif (($leg['mode'] ?? '') === 'walk') {
                    $signatureParts[] = 'walk:' . ($leg['from'] ?? '') . '>' . ($leg['to'] ?? '');
                }
            }

            $signature = implode('|', $signatureParts);
            if ($signature === '') {
                continue;
            }

            if (!isset($unique[$signature]) || (($path['arrival_sec'] ?? PHP_INT_MAX) < ($unique[$signature]['arrival_sec'] ?? PHP_INT_MAX))) {
                $unique[$signature] = $path;
            }
        }

        $paths = array_values($unique);
        usort($paths, fn($a, $b) => ($a['arrival_sec'] ?? PHP_INT_MAX) <=> ($b['arrival_sec'] ?? PHP_INT_MAX));
        return array_slice($paths, 0, max(self::MAX_ALTERNATIVES * 2, 8));
    }

    private function fetchDirectLegs(string $fromStopId, string $toStopId, int $minDepartSec, int $maxDepartSec, array $serviceIds, int $limit): array {
        $boardings = $this->fetchBoardingTripsFromStop($fromStopId, $minDepartSec, $maxDepartSec, $serviceIds, max(8, min(self::MAX_BOARDING_ROWS, $limit * 6)));
        $legs = [];

        foreach ($boardings as $boarding) {
            $stops = $this->getStopsFromTripCached($boarding['trip_id']);
            $sequenceIndex = $this->getTripStopSequenceIndex($boarding['trip_id']);
            $boardIdx = $sequenceIndex[(int)$boarding['board_sequence']] ?? null;

            if ($boardIdx === null) {
                continue;
            }

            for ($idx = $boardIdx + 1; $idx < count($stops); $idx++) {
                $alight = $stops[$idx];
                if (($alight['stop_id'] ?? '') !== $toStopId) {
                    continue;
                }

                $legs[] = [
                    'trip_id' => $boarding['trip_id'],
                    'board_sequence' => $boarding['board_sequence'],
                    'alight_sequence' => $alight['stop_sequence'],
                    'departure_time' => $boarding['departure_time'],
                    'arrival_time' => $alight['arrival_time'] ?: $alight['departure_time'],
                    'alight_departure_time' => $alight['departure_time'],
                    'route_id' => $boarding['route_id'],
                    'shape_id' => $boarding['shape_id'],
                    'route_short_name' => $boarding['route_short_name'],
                ];
                break;
            }

            if (count($legs) >= $limit) {
                break;
            }
        }

        usort($legs, fn($a, $b) => $this->gtfsTimeToSeconds($a['arrival_time'] ?? '0:00:00') <=> $this->gtfsTimeToSeconds($b['arrival_time'] ?? '0:00:00'));
        return array_slice($legs, 0, $limit);
    }

    private function buildReachableLegsFromBoardings(string $fromStopId, int $minDepartSec, int $maxDepartSec, array $serviceIds, int $limit): array {
        $boardings = $this->fetchBoardingTripsFromStop($fromStopId, $minDepartSec, $maxDepartSec, $serviceIds, self::MAX_BOARDING_ROWS);
        $legs = [];

        foreach ($boardings as $boarding) {
            $stops = $this->getStopsFromTripCached($boarding['trip_id']);
            $sequenceIndex = $this->getTripStopSequenceIndex($boarding['trip_id']);
            $boardIdx = $sequenceIndex[(int)$boarding['board_sequence']] ?? null;

            if ($boardIdx === null) {
                continue;
            }

            $maxIdx = min(count($stops) - 1, $boardIdx + self::MAX_SEGMENT_STOPS_SCAN);
            for ($idx = $boardIdx + 1; $idx <= $maxIdx; $idx++) {
                $alight = $stops[$idx];
                $legs[] = [
                    'trip_id' => $boarding['trip_id'],
                    'route_id' => $boarding['route_id'],
                    'route_short_name' => $boarding['route_short_name'],
                    'shape_id' => $boarding['shape_id'],
                    'board_sequence' => $boarding['board_sequence'],
                    'alight_sequence' => $alight['stop_sequence'],
                    'alight_stop_id' => $alight['stop_id'],
                    'departure_time' => $boarding['departure_time'],
                    'arrival_time' => $alight['arrival_time'] ?: $alight['departure_time'],
                    'alight_departure_time' => $alight['departure_time'],
                ];

                if (count($legs) >= $limit) {
                    break 2;
                }
            }
        }

        usort($legs, fn($a, $b) => $this->gtfsTimeToSeconds($a['arrival_time'] ?? '0:00:00') <=> $this->gtfsTimeToSeconds($b['arrival_time'] ?? '0:00:00'));
        return $legs;
    }

    private function fetchBoardingTripsFromStop(string $stopId, int $minDepartSec, int $maxDepartSec, array $serviceIds, int $limit): array {
        $bucketStartSec = intdiv($minDepartSec, self::DEPARTURE_CACHE_BUCKET_SECONDS) * self::DEPARTURE_CACHE_BUCKET_SECONDS;
        $bucketEndSec = max($bucketStartSec + self::DEPARTURE_CACHE_BUCKET_SECONDS, $maxDepartSec);
        $minDepartTime = $this->secondsToGtfsTime($bucketStartSec);
        $maxDepartTime = $this->secondsToGtfsTime($bucketEndSec);
        $serviceKey = implode(',', $serviceIds);
        $cacheKey = implode('|', [$stopId, $minDepartTime, $maxDepartTime, $serviceKey, max(1, $limit)]);
        if (isset($this->boardingTripsCache[$cacheKey])) {
            return $this->boardingTripsCache[$cacheKey];
        }

        $params = [$stopId, $minDepartTime, $maxDepartTime];
        $serviceSql = '';
        $innerLimit = max(120, min(420, $limit * 10));

        if (!empty($serviceIds)) {
            $serviceSql = ' WHERE t.service_id IN (' . implode(',', array_fill(0, count($serviceIds), '?')) . ')';
            $params = array_merge($params, $serviceIds);
            $sql = "
                SELECT st.trip_id,
                       st.stop_sequence AS board_sequence,
                       st.departure_time,
                       t.route_id,
                       t.shape_id,
                       r.route_short_name
                FROM (
                    SELECT trip_id, stop_sequence, departure_time
                    FROM stop_times
                    WHERE stop_id = ?
                      AND departure_time BETWEEN ? AND ?
                    ORDER BY departure_time ASC
                    LIMIT $innerLimit
                ) st
                JOIN trips t ON t.trip_id = st.trip_id
                JOIN routes r ON r.route_id = t.route_id
                $serviceSql
                ORDER BY st.departure_time ASC
                LIMIT " . max(1, $limit);
        } else {
            $sql = "
                SELECT st.trip_id,
                       st.stop_sequence AS board_sequence,
                       st.departure_time,
                       t.route_id,
                       t.shape_id,
                       r.route_short_name
                FROM stop_times st
                JOIN trips t ON t.trip_id = st.trip_id
                JOIN routes r ON r.route_id = t.route_id
                WHERE st.stop_id = ?
                  AND st.departure_time BETWEEN ? AND ?
                ORDER BY st.departure_time ASC
                LIMIT " . max(1, $limit);
        }

        $startedAt = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->recordQueryStat('fetchBoardingTripsFromStop', microtime(true) - $startedAt, count($rows));
        $out = [];
        foreach ($rows as $row) {
            $depSec = $this->gtfsTimeToSeconds($row['departure_time'] ?? '0:00:00');
            if ($depSec < $minDepartSec || $depSec > $maxDepartSec) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        $this->boardingTripsCache[$cacheKey] = $out;
        return $out;
    }

    private function fetchReachableLegsFromStop(string $fromStopId, int $minDepartSec, int $maxDepartSec, array $serviceIds, int $limit): array {
        $serviceSql = '';
        $params = [$fromStopId, $minDepartSec, $maxDepartSec];

        if (!empty($serviceIds)) {
            $serviceSql = ' AND t.service_id IN (' . implode(',', array_fill(0, count($serviceIds), '?')) . ')';
            $params = array_merge($params, $serviceIds);
        }

        $sql = "
            SELECT st1.trip_id,
                   st1.stop_sequence AS board_sequence,
                   st2.stop_sequence AS alight_sequence,
                   st2.stop_id AS alight_stop_id,
                   st1.departure_time,
                   st2.arrival_time,
                   st2.departure_time AS alight_departure_time,
                   t.route_id,
                   t.shape_id,
                   r.route_short_name
            FROM stop_times st1
            JOIN stop_times st2 ON st2.trip_id = st1.trip_id
            JOIN trips t ON t.trip_id = st1.trip_id
            JOIN routes r ON r.route_id = t.route_id
            WHERE st1.stop_id = ?
              AND st2.stop_sequence > st1.stop_sequence
              AND TIME_TO_SEC(st1.departure_time) BETWEEN ? AND ?
              $serviceSql
            ORDER BY st1.departure_time ASC, st2.arrival_time ASC
            LIMIT " . max(1, $limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchTripSegment(string $tripId, int $boardSequence, int $alightSequence): array {
        $cacheKey = $tripId . '|' . $boardSequence . '|' . $alightSequence;
        if (isset($this->tripSegmentCache[$cacheKey])) {
            return $this->tripSegmentCache[$cacheKey];
        }

        $stops = $this->getStopsFromTripCached($tripId);
        $sequenceIndex = $this->getTripStopSequenceIndex($tripId);
        $startIndex = $sequenceIndex[$boardSequence] ?? null;
        $endIndex = $sequenceIndex[$alightSequence] ?? null;

        if ($startIndex === null || $endIndex === null || $endIndex < $startIndex) {
            $this->tripSegmentCache[$cacheKey] = [];
            return [];
        }

        $this->tripSegmentCache[$cacheKey] = array_slice($stops, $startIndex, $endIndex - $startIndex + 1);
        return $this->tripSegmentCache[$cacheKey];
    }

    // -------------------------------------------------------------------------
    // Calendário / serviços ativos
    // -------------------------------------------------------------------------

    /**
     * Serviços GTFS em vigor numa data (calendar + calendar_dates).
     * Fallback: todos os service_id presentes em trips.
     */
    private function getActiveServiceIdsForDate(string $date): array {
        $removed = $this->fetchCalendarDateServiceIds($date, 2);
        $added = $this->fetchCalendarDateServiceIds($date, 1);

        $fromCalendar = $this->fetchServiceIdsFromCalendarTable($date);

        if (empty($fromCalendar) && empty($added)) {
            // Não há tabela calendar neste projeto e o feed pode estar fora da data
            // corrente. Sem serviços ativos explícitos, não aplicar IN gigante.
            return [];
        }

        $active = $fromCalendar;
        if (!empty($removed)) {
            $active = array_values(array_diff($active, $removed));
        }
        if (!empty($added)) {
            $active = array_values(array_unique(array_merge($active, $added)));
        }

        return $active;
    }

    private function fetchAllServiceIdsFromTrips(): array {
        try {
            $stmt = $this->pdo->query("SELECT DISTINCT service_id FROM trips");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function fetchCalendarDateServiceIds(string $date, int $exceptionType): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT service_id FROM calendar_dates WHERE date = ? AND exception_type = ?"
            );
            $stmt->execute([$date, $exceptionType]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    private function fetchServiceIdsFromCalendarTable(string $date): array {
        try {
            $dayCol = strtolower(date('l', strtotime($date)));
            $allowed = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            if (!in_array($dayCol, $allowed, true)) {
                return [];
            }

            $sql = "SELECT service_id FROM calendar WHERE start_date <= ? AND end_date >= ? AND `$dayCol` = 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$date, $date]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Motor: Dijkstra temporal (viagem + caminhada)
    // -------------------------------------------------------------------------

    /**
     * @param array<string,bool> $forbiddenTripIds
     */
    private function findShortestPath(
        string $fromStopId,
        string $toStopId,
        int $depSec,
        array $serviceIds,
        array $forbiddenTripIds
    ): ?array {
        $inf = PHP_INT_MAX;
        $best = [];
        $edgeTo = [];
        $toCoords = $this->getStopCoords($toStopId);
        if (!$toCoords) {
            return null;
        }
        $goalLat = (float)$toCoords['stop_lat'];
        $goalLon = (float)$toCoords['stop_lon'];
        /** @var array<string,string> origin|walk|ride — como se chegou ao stop com o melhor tempo */
        $reachMode = [];
        $reachMode[$fromStopId] = 'origin';
        $stopPops = [];
        $tripExpansions = [];

        $heap = new SplMinHeap();

        $best[$fromStopId] = $depSec;
        $heap->insert([
            $depSec + $this->estimateRemainingTravelSeconds($this->goalDistanceMeters($fromStopId, $goalLat, $goalLon)),
            $depSec,
            $fromStopId
        ]);

        $servicePlaceholders = '';
        $serviceParamsRoute = [];
        if (!empty($serviceIds)) {
            $servicePlaceholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $serviceParamsRoute = $serviceIds;
        }

        $pops = 0;

        while (!$heap->isEmpty() && $pops < self::MAX_DIJKSTRA_POPS) {
            if ($this->hasTimedOut()) {
                return null;
            }

            [$priority, $t, $stop] = $heap->extract();
            $pops++;

            if ($t > ($best[$stop] ?? $inf)) {
                continue;
            }

            $stopPops[$stop] = ($stopPops[$stop] ?? 0) + 1;
            if ($stopPops[$stop] > self::MAX_STOP_POPS_PER_SEARCH) {
                continue;
            }

            if ($stop === $toStopId) {
                return $this->buildPathFromEdgeTo($fromStopId, $toStopId, $edgeTo, $best[$toStopId]);
            }

            $currentDistanceMeters = $this->goalDistanceMeters($stop, $goalLat, $goalLon);

            foreach ($this->getWalkingNeighbors($stop) as $nb) {
                $wstop = $nb['stop_id'];
                $walkSec = $nb['walk_seconds'];
                $walkDistanceMeters = $this->goalDistanceMeters($wstop, $goalLat, $goalLon);
                if (!$this->shouldExploreCandidateStop($currentDistanceMeters, $walkDistanceMeters, $wstop, $wstop === $toStopId, true)) {
                    continue;
                }
                $nt = $t + $walkSec;
                if ($nt < ($best[$wstop] ?? $inf)) {
                    $best[$wstop] = $nt;
                    $reachMode[$wstop] = 'walk';
                    $edgeTo[$wstop] = ['mode' => 'walk', 'from' => $stop, 'to' => $wstop];
                    $heap->insert([
                        $nt + $this->priorityAdjustmentSeconds($walkDistanceMeters, $wstop),
                        $nt,
                        $wstop
                    ]);
                }
            }

            $mode = $reachMode[$stop] ?? 'ride';
            if ($stop === $fromStopId) {
                $needDepart = $t;
            } elseif ($mode === 'walk') {
                $needDepart = $t + self::BOARD_AFTER_WALK_SECONDS;
            } else {
                $needDepart = $t + self::MIN_TRANSFER_SECONDS;
            }

            $tripRows = $this->fetchTripDeparturesFromStop($stop, $needDepart, $servicePlaceholders, $serviceParamsRoute);
            $scanned = 0;

            foreach ($tripRows as $row) {
                if ($scanned >= self::MAX_TRIPS_PER_STOP_SCAN) {
                    break;
                }
                $scanned++;

                $tripId = $row['trip_id'];
                if (!empty($forbiddenTripIds[$tripId])) {
                    continue;
                }
                $tripExpansions[$tripId] = ($tripExpansions[$tripId] ?? 0) + 1;
                if ($tripExpansions[$tripId] > self::MAX_TRIP_EXPANSIONS_PER_SEARCH) {
                    continue;
                }

                $depStr = $row['departure_time'] ?? '';
                $depAtS = $this->gtfsTimeToSeconds($depStr);
                if ($depAtS < $needDepart) {
                    continue;
                }

                $stops = $this->getStopsFromTripCached($tripId);
                if (count($stops) < 2) {
                    continue;
                }

                $boardIdx = null;
                foreach ($stops as $i => $srow) {
                    if ($srow['stop_id'] === $stop) {
                        $boardIdx = $i;
                        break;
                    }
                }
                if ($boardIdx === null) {
                    continue;
                }

                $shapeId = $row['shape_id'] ?? null;
                $routeId = $row['route_id'] ?? null;
                $routeName = $row['route_short_name'] ?? '';
                $candidateAlightIndexes = $this->selectPromisingAlightIndexes(
                    $stops,
                    $boardIdx,
                    $toStopId,
                    $currentDistanceMeters,
                    $goalLat,
                    $goalLon
                );

                foreach ($candidateAlightIndexes as $j) {
                    $arrStr = $stops[$j]['arrival_time'] ?: $stops[$j]['departure_time'];
                    $arrSec = $this->gtfsTimeToSeconds($arrStr);
                    if ($arrSec < $depAtS) {
                        continue;
                    }

                    $nt = $arrSec;
                    $jStop = $stops[$j]['stop_id'];
                    $candidateDistanceMeters = $this->goalDistanceMeters($jStop, $goalLat, $goalLon);
                    if (!$this->shouldExploreCandidateStop($currentDistanceMeters, $candidateDistanceMeters, $jStop, $jStop === $toStopId, false)) {
                        continue;
                    }

                    if ($nt < ($best[$jStop] ?? $inf)) {
                        $best[$jStop] = $nt;
                        $reachMode[$jStop] = 'ride';
                        $edgeTo[$jStop] = [
                            'mode' => 'ride',
                            'trip_id' => $tripId,
                            'route_id' => $routeId,
                            'route_short_name' => $routeName,
                            'shape_id' => $shapeId,
                            'board_idx' => $boardIdx,
                            'alight_idx' => $j,
                            'board_stop_id' => $stop,
                            'departure_time' => $depStr,
                            'arrival_time' => $arrStr,
                        ];
                        $heap->insert([
                            $nt + $this->priorityAdjustmentSeconds($candidateDistanceMeters, $jStop),
                            $nt,
                            $jStop
                        ]);
                    }
                }
            }
        }

        return null;
    }

    private function hasTimedOut(): bool {
        $timedOut = $this->requestStartedAt > 0 && (microtime(true) - $this->requestStartedAt) >= $this->currentRouteBudgetSeconds;
        if ($timedOut) {
            $this->lastSearchTimedOut = true;
        }
        return $timedOut;
    }

    private function resetDebugStats(string $fromStopId, string $toStopId): void {
        $this->debugStats = [
            'from_stop_id' => $fromStopId,
            'to_stop_id' => $toStopId,
            'stages' => [],
            'queries' => [],
            'queries_rows' => [],
            'combinations_tested' => 0,
            'candidate_paths' => 0,
            'alternatives_built' => 0,
            'errors' => [],
        ];
    }

    private function markStage(string $stage): void {
        $this->debugStats['stages'][$stage] = round((microtime(true) - $this->requestStartedAt) * 1000, 2);
    }

    private function recordQueryStat(string $queryName, float $durationSeconds, int $rowCount = 0): void {
        if (!isset($this->debugStats['queries'][$queryName])) {
            $this->debugStats['queries'][$queryName] = 0.0;
            $this->debugStats['queries_rows'][$queryName] = 0;
        }

        $this->debugStats['queries'][$queryName] += $durationSeconds;
        $this->debugStats['queries_rows'][$queryName] += $rowCount;
    }

    private function recordError(string $scope, string $message): void {
        $this->debugStats['errors'][] = $scope . ': ' . $message;
    }

    private function incrementCombinations(int $count = 1): void {
        $this->debugStats['combinations_tested'] = (int)($this->debugStats['combinations_tested'] ?? 0) + $count;
    }

    private function shouldStopCandidateSearch(array $paths, bool $directOnly): bool {
        $unique = $this->dedupeAndSortPaths($paths);
        $this->debugStats['candidate_paths'] = count($unique);

        if (count($unique) >= self::EARLY_GOOD_ROUTES_TARGET) {
            return true;
        }

        if ($directOnly && count($unique) >= 2) {
            return true;
        }

        return $this->hasTimedOut() || (microtime(true) - $this->requestStartedAt) >= 2.2;
    }

    private function shouldKeepSearchingAlternatives(array $alternatives): bool {
        if (count($alternatives) < self::EARLY_GOOD_ROUTES_TARGET) {
            return true;
        }

        $scores = array_map(fn($route) => $this->scoreRoute($route), $alternatives);
        sort($scores);
        $best = $scores[0] ?? PHP_INT_MAX;
        $third = $scores[2] ?? PHP_INT_MAX;

        return $third > ($best * 1.35) && !$this->hasTimedOut();
    }

    private function logDebugSummary(string $status, int $candidateCount, int $resultCount): void {
        $this->debugStats['candidate_paths'] = $candidateCount;
        $this->debugStats['alternatives_built'] = $resultCount;
        $totalMs = round((microtime(true) - $this->requestStartedAt) * 1000, 2);

        $queryMs = [];
        foreach (($this->debugStats['queries'] ?? []) as $queryName => $seconds) {
            $queryMs[$queryName] = round($seconds * 1000, 2);
        }

        $payload = [
            'status' => $status,
            'total_ms' => $totalMs,
            'from_stop_id' => $this->debugStats['from_stop_id'] ?? null,
            'to_stop_id' => $this->debugStats['to_stop_id'] ?? null,
            'candidate_paths' => $candidateCount,
            'combinations_tested' => $this->debugStats['combinations_tested'] ?? 0,
            'result_count' => $resultCount,
            'stage_ms' => $this->debugStats['stages'] ?? [],
            'query_ms' => $queryMs,
            'query_rows' => $this->debugStats['queries_rows'] ?? [],
            'errors' => $this->debugStats['errors'] ?? [],
        ];

        $this->lastDebugPayload = $payload;
        error_log('[GtfsRouteService] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function getLastDebugPayload(): array {
        return $this->lastDebugPayload;
    }

    private function goalDistanceMeters(string $stopId, float $goalLat, float $goalLon): float {
        $cacheKey = $stopId . '|' . $goalLat . '|' . $goalLon;
        if (isset($this->stopGoalDistanceCache[$cacheKey])) {
            return $this->stopGoalDistanceCache[$cacheKey];
        }

        $coords = $this->getStopCoords($stopId);
        if (!$coords) {
            $this->stopGoalDistanceCache[$cacheKey] = PHP_FLOAT_MAX / 8;
            return $this->stopGoalDistanceCache[$cacheKey];
        }

        $this->stopGoalDistanceCache[$cacheKey] = $this->haversineMeters(
            (float)$coords['stop_lat'],
            (float)$coords['stop_lon'],
            $goalLat,
            $goalLon
        );

        return $this->stopGoalDistanceCache[$cacheKey];
    }

    private function getStopTransferPotential(string $stopId): int {
        if (isset($this->stopTransferPotentialCache[$stopId])) {
            return $this->stopTransferPotentialCache[$stopId];
        }

        $neighbors = $this->getWalkingNeighbors($stopId);
        $potential = count($neighbors);
        $this->stopTransferPotentialCache[$stopId] = $potential;
        return $potential;
    }

    private function estimateRemainingTravelSeconds(float $distanceMeters): int {
        if (!is_finite($distanceMeters) || $distanceMeters <= 0) {
            return 0;
        }

        return max(120, (int)round($distanceMeters / self::ASSUMED_TRANSIT_SPEED_MPS));
    }

    private function priorityAdjustmentSeconds(float $distanceMeters, string $stopId): int {
        $transferPotential = min(6, $this->getStopTransferPotential($stopId));
        $bonusSeconds = $transferPotential * 35;
        return max(0, $this->estimateRemainingTravelSeconds($distanceMeters) - $bonusSeconds);
    }

    private function shouldExploreCandidateStop(
        float $currentDistanceMeters,
        float $candidateDistanceMeters,
        string $stopId,
        bool $isDestination,
        bool $isWalk
    ): bool {
        if ($isDestination) {
            return true;
        }

        if (!is_finite($candidateDistanceMeters)) {
            return false;
        }

        if ($candidateDistanceMeters <= $currentDistanceMeters + 350) {
            return true;
        }

        if (($candidateDistanceMeters - $currentDistanceMeters) > self::MAX_DESTINATION_DETOUR_METERS) {
            return false;
        }

        $transferPotential = $this->getStopTransferPotential($stopId);
        if ($transferPotential >= ($isWalk ? 2 : 3)) {
            return true;
        }

        return false;
    }

    private function selectPromisingAlightIndexes(
        array $stops,
        int $boardIdx,
        string $toStopId,
        float $currentDistanceMeters,
        float $goalLat,
        float $goalLon
    ): array {
        $candidates = [];
        $seen = [];
        $lastIndex = count($stops) - 1;

        for ($j = $boardIdx + 1; $j < count($stops); $j++) {
            $stopId = $stops[$j]['stop_id'] ?? null;
            if (!$stopId) {
                continue;
            }

            $distanceMeters = $this->goalDistanceMeters($stopId, $goalLat, $goalLon);
            $isDestination = $stopId === $toStopId;
            $isPeriodicSample = (($j - $boardIdx) % 10) === 0;
            $isLastStop = $j === $lastIndex;
            $transferPotential = $this->getStopTransferPotential($stopId);

            if (
                !$isDestination
                && !$isPeriodicSample
                && !$isLastStop
                && !$this->shouldExploreCandidateStop($currentDistanceMeters, $distanceMeters, $stopId, false, false)
                && $transferPotential < 4
            ) {
                continue;
            }

            if (isset($seen[$j])) {
                continue;
            }
            $seen[$j] = true;

            $progressMeters = max(0, $currentDistanceMeters - $distanceMeters);
            $score = $distanceMeters
                - min(6, $transferPotential) * 180
                - min(1800, $progressMeters) * 0.25
                + max(0, $j - $boardIdx - 24) * 20;

            if ($isDestination) {
                $score -= 5000;
            } elseif ($isLastStop) {
                $score += 250;
            } elseif ($isPeriodicSample) {
                $score -= 120;
            }

            $candidates[] = [
                'index' => $j,
                'score' => $score,
            ];
        }

        usort($candidates, fn($a, $b) => ($a['score'] <=> $b['score']) ?: ($a['index'] <=> $b['index']));
        $selected = array_slice($candidates, 0, self::MAX_DIJKSTRA_ALIGHT_OPTIONS);
        usort($selected, fn($a, $b) => $a['index'] <=> $b['index']);

        return array_values(array_map(fn($row) => $row['index'], $selected));
    }

    private function fetchTripDeparturesFromStop(
        string $stopId,
        int $minDepartSec,
        string $servicePlaceholders,
        array $serviceParams
    ): array {
        $bucketStartSec = intdiv($minDepartSec, self::DEPARTURE_CACHE_BUCKET_SECONDS) * self::DEPARTURE_CACHE_BUCKET_SECONDS;
        $maxDepartSec = $minDepartSec + self::DIJKSTRA_DEPARTURE_WINDOW_SECONDS;
        $bucketEndSec = $bucketStartSec + self::DIJKSTRA_DEPARTURE_WINDOW_SECONDS + self::DEPARTURE_CACHE_BUCKET_SECONDS;
        $minDepartTime = $this->secondsToGtfsTime($bucketStartSec);
        $maxDepartTime = $this->secondsToGtfsTime($bucketEndSec);
        $cacheKey = $stopId . '|' . $minDepartTime . '|' . $maxDepartTime . '|' . md5($servicePlaceholders . '|' . implode(',', $serviceParams));
        if (isset($this->tripDeparturesCache[$cacheKey])) {
            return $this->tripDeparturesCache[$cacheKey];
        }
        $params = [$stopId, $minDepartTime, $maxDepartTime];
        $innerLimit = 220;

        if ($servicePlaceholders !== '') {
            $sql = "
                SELECT st.trip_id,
                       st.departure_time,
                       st.stop_sequence,
                       t.route_id,
                       t.shape_id,
                       r.route_short_name
                FROM (
                    SELECT trip_id, departure_time, stop_sequence
                    FROM stop_times
                    WHERE stop_id = ?
                      AND departure_time BETWEEN ? AND ?
                    ORDER BY departure_time ASC
                    LIMIT $innerLimit
                ) st
                JOIN trips t ON st.trip_id = t.trip_id
                JOIN routes r ON t.route_id = r.route_id
                WHERE t.service_id IN ($servicePlaceholders)
                ORDER BY st.departure_time ASC
                LIMIT 140
            ";
            $params = array_merge($params, $serviceParams);
        } else {
            $sql = "
                SELECT st.trip_id,
                       st.departure_time,
                       st.stop_sequence,
                       t.route_id,
                       t.shape_id,
                       r.route_short_name
                FROM stop_times st
                JOIN trips t ON st.trip_id = t.trip_id
                JOIN routes r ON t.route_id = r.route_id
                WHERE st.stop_id = ?
                  AND st.departure_time BETWEEN ? AND ?
                ORDER BY st.departure_time ASC
                LIMIT 140
            ";
        }

        try {
            $startedAt = microtime(true);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->recordQueryStat('fetchTripDeparturesFromStop', microtime(true) - $startedAt, count($rows));
        } catch (Exception $e) {
            $this->recordError('fetchTripDeparturesFromStop', $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $ds = $this->gtfsTimeToSeconds($row['departure_time'] ?? '0:00:00');
            if ($ds >= $minDepartSec && $ds <= $maxDepartSec) {
                $out[] = $row;
            }
        }
        $this->tripDeparturesCache[$cacheKey] = $out;
        return $out;
    }

    private function buildPathFromEdgeTo(string $fromStopId, string $toStopId, array $edgeTo, int $arrivalSec): ?array {
        $legsRev = [];
        $cur = $toStopId;

        $guard = 0;
        while ($cur !== $fromStopId && $guard++ < 500) {
            if (!isset($edgeTo[$cur])) {
                return null;
            }
            $e = $edgeTo[$cur];
            if ($e['mode'] === 'walk') {
                $legsRev[] = [
                    'mode' => 'walk',
                    'from' => $e['from'],
                    'to' => $e['to'],
                ];
                $cur = $e['from'];
                continue;
            }

            $tripId = $e['trip_id'];
            $stops = $this->getStopsFromTripCached($tripId);
            $bi = $e['board_idx'];
            $ai = $e['alight_idx'];
            $segment = array_slice($stops, $bi, $ai - $bi + 1);

            $legsRev[] = [
                'mode' => 'ride',
                'trip_id' => $tripId,
                'route_id' => $e['route_id'],
                'route_short_name' => $e['route_short_name'],
                'shape_id' => $e['shape_id'],
                'segment' => $segment,
                'board_stop_id' => $e['board_stop_id'],
            ];
            $cur = $e['board_stop_id'];
        }

        if ($cur !== $fromStopId) {
            return null;
        }

        $legs = array_reverse($legsRev);

        return [
            'legs' => $legs,
            'arrival_sec' => $arrivalSec,
        ];
    }

    /**
     * Converte caminho interno para o formato usado por formatRoute / UI.
     */
    private function pathToInternalRoute(array $path): ?array {
        $legs = $path['legs'];
        $rideLegs = array_values(array_filter($legs, fn($l) => $l['mode'] === 'ride'));

        if (empty($rideLegs)) {
            return null;
        }

        $allStopsData = [];
        $coordinates = [];
        $segmentsUi = [];
        $tripIds = [];
        $routeIds = [];
        $geometryStats = [
            'gtfs_shape' => 0,
            'fallback_stop_to_stop' => 0,
            'fallback_straight_line' => 0,
            'street_routed' => 0,
        ];

        $firstDep = null;
        $lastArr = null;
        $transferNames = [];

        foreach ($rideLegs as $idx => $rl) {
            $seg = $rl['segment'];
            if (empty($seg)) {
                continue;
            }

            $tripIds[] = $rl['trip_id'];
            $routeIds[] = $rl['route_id'];

            $shapeId = $rl['shape_id'];
            $geometry = $this->buildCoordinates($shapeId, $seg, false);
            $geometryQuality = $geometry['geometry_quality'] ?? 'fallback_stop_to_stop';
            $geometryStats[$geometryQuality] = ($geometryStats[$geometryQuality] ?? 0) + 1;
            $coords = $geometry['coordinates_lonlat'];
            if (!empty($coordinates) && !empty($coords)) {
                $lastCoord = $coordinates[count($coordinates) - 1];
                $firstCoord = $coords[0];
                if (abs($lastCoord[0] - $firstCoord[0]) > 0.000001 || abs($lastCoord[1] - $firstCoord[1]) > 0.000001) {
                    $coordinates[] = $firstCoord;
                }
            }
            $coordinates = array_merge($coordinates, $coords);

            if (!empty($allStopsData)) {
                $prevLast = $allStopsData[count($allStopsData) - 1];
                $nextFirst = $seg[0];
                if (($prevLast['stop_id'] ?? null) === ($nextFirst['stop_id'] ?? null)) {
                    array_shift($seg);
                }
            }
            foreach ($seg as $row) {
                $allStopsData[] = $row;
            }

            $line = $rl['route_short_name'] ?: '—';
            $fromName = $seg[0]['stop_name'] ?? '';
            $toName = $seg[count($seg) - 1]['stop_name'] ?? '';

            $depT = $seg[0]['departure_time'] ?? '';
            $arrT = $seg[count($seg) - 1]['arrival_time'] ?? $seg[count($seg) - 1]['departure_time'] ?? '';

            if ($firstDep === null && $depT) {
                $firstDep = $depT;
            }
            $lastArr = $arrT ?: $lastArr;

            $durMin = $this->calculateTravelTime($seg);

            $wait = null;
            if ($idx > 0) {
                $prevLeg = $rideLegs[$idx - 1];
                $prevArr = end($prevLeg['segment']);
                $prevArrSec = $this->gtfsTimeToSeconds($prevArr['arrival_time'] ?: $prevArr['departure_time'] ?? '0:00:00');
                $curDepSec = $this->gtfsTimeToSeconds($seg[0]['departure_time'] ?? '0:00:00');
                $wait = max(0, (int)round(($curDepSec - $prevArrSec) / 60));
                $transferNames[] = $seg[0]['stop_name'] ?? '';
            }

            $segmentsUi[] = [
                'type' => 'bus',
                'line' => $line,
                'route_id' => $rl['route_id'],
                'trip_id' => $rl['trip_id'],
                'shape_id' => $shapeId,
                'from_stop_id' => $seg[0]['stop_id'] ?? null,
                'to_stop_id' => $seg[count($seg) - 1]['stop_id'] ?? null,
                'from_name' => $fromName,
                'to_name' => $toName,
                'from_lat' => isset($seg[0]['stop_lat']) ? (float)$seg[0]['stop_lat'] : null,
                'from_lon' => isset($seg[0]['stop_lon']) ? (float)$seg[0]['stop_lon'] : null,
                'to_lat' => isset($seg[count($seg) - 1]['stop_lat']) ? (float)$seg[count($seg) - 1]['stop_lat'] : null,
                'to_lon' => isset($seg[count($seg) - 1]['stop_lon']) ? (float)$seg[count($seg) - 1]['stop_lon'] : null,
                'from' => [
                    'name' => $fromName,
                    'latitude' => isset($seg[0]['stop_lat']) ? (float)$seg[0]['stop_lat'] : null,
                    'longitude' => isset($seg[0]['stop_lon']) ? (float)$seg[0]['stop_lon'] : null,
                    'stop_id' => $seg[0]['stop_id'] ?? null,
                ],
                'to' => [
                    'name' => $toName,
                    'latitude' => isset($seg[count($seg) - 1]['stop_lat']) ? (float)$seg[count($seg) - 1]['stop_lat'] : null,
                    'longitude' => isset($seg[count($seg) - 1]['stop_lon']) ? (float)$seg[count($seg) - 1]['stop_lon'] : null,
                    'stop_id' => $seg[count($seg) - 1]['stop_id'] ?? null,
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $geometry['coordinates_lonlat'],
                ],
                'coordinates' => $geometry['coordinates_lonlat'],
                'geometry_source' => $geometry['source'],
                'geometry_quality' => $geometryQuality,
                'duration_minutes' => $durMin,
                'time' => $durMin,
                'stop_count' => count($seg),
                'stops' => count($seg),
                'departure' => $depT,
                'arrival_time' => $arrT,
                'wait' => $wait,
                'markers' => [
                    [
                        'kind' => 'board',
                        'type' => 'stop',
                        'name' => $fromName,
                        'stop_id' => $seg[0]['stop_id'] ?? null,
                        'stop_name' => $fromName,
                        'coordinates' => [
                            isset($seg[0]['stop_lon']) ? (float)$seg[0]['stop_lon'] : null,
                            isset($seg[0]['stop_lat']) ? (float)$seg[0]['stop_lat'] : null,
                        ],
                        'action' => 'Embarcar',
                        'arrival_time' => $this->formatClockForUi($seg[0]['arrival_time'] ?? $seg[0]['departure_time'] ?? null),
                        'departure_time' => $this->formatClockForUi($seg[0]['departure_time'] ?? null),
                        'line' => $line,
                    ],
                    [
                        'kind' => 'alight',
                        'type' => 'stop',
                        'name' => $toName,
                        'stop_id' => $seg[count($seg) - 1]['stop_id'] ?? null,
                        'stop_name' => $toName,
                        'coordinates' => [
                            isset($seg[count($seg) - 1]['stop_lon']) ? (float)$seg[count($seg) - 1]['stop_lon'] : null,
                            isset($seg[count($seg) - 1]['stop_lat']) ? (float)$seg[count($seg) - 1]['stop_lat'] : null,
                        ],
                        'action' => 'Desembarcar',
                        'arrival_time' => $this->formatClockForUi($seg[count($seg) - 1]['arrival_time'] ?? $seg[count($seg) - 1]['departure_time'] ?? null),
                        'departure_time' => $this->formatClockForUi($seg[count($seg) - 1]['departure_time'] ?? null),
                        'line' => $line,
                    ],
                ],
                'debug' => [
                    'trip_id' => $rl['trip_id'],
                    'shape_id' => $shapeId,
                    'from_stop_id' => $seg[0]['stop_id'] ?? null,
                    'to_stop_id' => $seg[count($seg) - 1]['stop_id'] ?? null,
                    'shape_points_total' => $geometry['shape_points_total'],
                    'geometry_points_returned' => $geometry['cut_points_count'],
                    'geometry_source' => $geometry['source'],
                    'geometry_quality' => $geometryQuality,
                ],
            ];
        }

        $segmentsUi = $this->injectTransferSegments($segmentsUi, $legs);

        $totalRideMin = 0;
        foreach ($rideLegs as $rl) {
            $totalRideMin += $this->calculateTravelTime($rl['segment']);
        }

        $walkLegs = array_filter($legs, fn($l) => $l['mode'] === 'walk');
        foreach ($walkLegs as $wl) {
            $totalRideMin += max(1, (int)ceil($this->walkSecondsBetweenStops($wl['from'], $wl['to']) / 60));
        }

        $firstSeg = $rideLegs[0]['segment'];
        $lastSeg = $rideLegs[count($rideLegs) - 1]['segment'];
        $firstTripDepSec = $this->gtfsTimeToSeconds($firstSeg[0]['departure_time'] ?? '0:00:00');
        $lastStopRow = $lastSeg[count($lastSeg) - 1];
        $lastTripArrSec = $this->gtfsTimeToSeconds(
            $lastStopRow['arrival_time'] ?? $lastStopRow['departure_time'] ?? '0:00:00'
        );
        $spanMin = max(1, (int)round(($lastTripArrSec - $firstTripDepSec) / 60));

        $transfers = count($rideLegs) - 1;
        $type = $transfers === 0 ? 'direct' : 'transfer';

        $tripIdJoined = implode('_', $tripIds);
        $routeIdJoined = implode('_', array_filter($routeIds, fn($x) => $x !== null && $x !== ''));

        $routeNameParts = [];
        foreach ($rideLegs as $rl) {
            $routeNameParts[] = $rl['route_short_name'] ?: '?';
        }
        $routeName = implode(' → ', $routeNameParts);

        $transferStop = null;
        $waitFirstTransfer = null;
        if ($transfers >= 1 && isset($segmentsUi[1])) {
            $transferStop = $transferNames[0] ?? ($rideLegs[1]['segment'][0]['stop_name'] ?? null);
            $waitFirstTransfer = $segmentsUi[1]['wait'] ?? null;
        }

        return [
            'type' => $type,
            'trip_id' => $tripIdJoined,
            'route_id' => $routeIdJoined ?: $tripIdJoined,
            'route_name' => $routeName,
            'legs' => $legs,
            'coordinates' => $coordinates,
            'stopsData' => $allStopsData,
            'total_time' => max($totalRideMin, $spanMin),
            'departure_time' => $this->formatClockForUi($firstDep),
            'arrival_time' => $this->formatClockForUi($lastArr),
            'stop_count' => count($allStopsData),
            'transfers' => $transfers,
            'segments' => $segmentsUi,
            'transfer_stop' => $transferStop,
            'wait_time' => $waitFirstTransfer,
            'geometry_stats' => $geometryStats,
            '_primary_trip_id' => $tripIds[0],
        ];
    }

    // -------------------------------------------------------------------------
    // Selecção / formatação para UI + tempo real Carris
    // -------------------------------------------------------------------------

    private function selectBestOptions(array $routes): array {
        if (empty($routes)) {
            return [];
        }

        // Dedup de alternativas antes da ordenação final, para não mostrar variações praticamente iguais.
        $routes = $this->dedupeRouteAlternatives($routes);
        if (empty($routes)) {
            return [];
        }

        usort($routes, function ($a, $b) {
            return $this->scoreRoute($a) <=> $this->scoreRoute($b);
        });

        $direct = array_values(array_filter($routes, fn($r) => ($r['type'] ?? '') === 'direct'));
        $transfer = array_values(array_filter($routes, fn($r) => ($r['type'] ?? '') === 'transfer'));

        $selected = [];
        $usedKeys = [];

        $pick = function ($r) use (&$selected, &$usedKeys) {
            $key = $this->buildAlternativeIdentityKey($r);
            if (isset($usedKeys[$key])) {
                return;
            }
            $usedKeys[$key] = true;
            $selected[] = $r;
        };

        $bestTime = $this->scoreRoute($routes[0]);

        if (!empty($direct)) {
            $pick($direct[0]);
            $bestTime = $this->scoreRoute($direct[0]);
        } elseif (!empty($transfer)) {
            $pick($transfer[0]);
            $bestTime = $this->scoreRoute($transfer[0]);
        }

        $lessWalking = $this->pickRouteByPreference($routes, $selected, function (array $route): array {
            return [
                $this->estimateRouteWalkingMinutes($route),
                $this->scoreRoute($route),
            ];
        });
        if ($lessWalking) {
            $pick($lessWalking);
        }

        $differentAlternative = $this->pickRouteByPreference($routes, $selected, function (array $route): array {
            return [
                $this->routeLineSignature($route),
                $this->scoreRoute($route),
            ];
        }, true);
        if ($differentAlternative) {
            $pick($differentAlternative);
        }

        foreach ($routes as $r) {
            if (count($selected) >= 3) {
                break;
            }
            if ($this->scoreRoute($r) <= $bestTime * 1.2) {
                $pick($r);
            }
        }

        foreach ($routes as $r) {
            if (count($selected) >= 3) {
                break;
            }
            $pick($r);
        }

        $badges = [
            0 => 'Recomendada',
            1 => 'Menos caminhada',
            2 => 'Alternativa',
        ];

        $result = [];
        foreach (array_slice($selected, 0, 3) as $i => $route) {
            $route = $this->refineRouteGeometryForPresentation($route);
            $result[] = $this->formatRoute($route, $i, $badges[$i] ?? 'Alternativa');
        }

        return $result;
    }

    private function dedupeRouteAlternatives(array $routes): array {
        $deduped = [];

        foreach ($routes as $route) {
            $duplicateIndex = null;
            foreach ($deduped as $index => $existing) {
                if ($this->routesAreEquivalent($existing, $route)) {
                    $duplicateIndex = $index;
                    break;
                }
            }

            if ($duplicateIndex === null) {
                $deduped[] = $route;
                continue;
            }

            if ($this->scoreRoute($route) < $this->scoreRoute($deduped[$duplicateIndex])) {
                $deduped[$duplicateIndex] = $route;
            }
        }

        return $deduped;
    }

    private function routesAreEquivalent(array $left, array $right): bool {
        $samePrimaryTrip = (string)($left['_primary_trip_id'] ?? '') !== ''
            && (string)($left['_primary_trip_id'] ?? '') === (string)($right['_primary_trip_id'] ?? '');
        $sameLineSequence = $this->routeLineSignature($left) === $this->routeLineSignature($right);
        $sameEndpoints = $this->routeEndpointSignature($left) === $this->routeEndpointSignature($right);
        $sameTransfers = (int)($left['transfers'] ?? 0) === (int)($right['transfers'] ?? 0);
        $similarDuration = abs((int)($left['total_time'] ?? 0) - (int)($right['total_time'] ?? 0)) <= 4;

        return $sameEndpoints && $sameTransfers && $similarDuration && ($samePrimaryTrip || $sameLineSequence);
    }

    private function buildAlternativeIdentityKey(array $route): string {
        return implode('|', [
            $this->routeLineSignature($route),
            $this->routeEndpointSignature($route),
            (string)($route['_primary_trip_id'] ?? ''),
            (string)($route['transfers'] ?? 0),
        ]);
    }

    private function routeLineSignature(array $route): string {
        $parts = [];
        foreach (($route['legs'] ?? []) as $leg) {
            if (($leg['mode'] ?? '') !== 'ride') {
                continue;
            }
            $parts[] = ($leg['route_short_name'] ?? '') . ':' . ($leg['route_id'] ?? '');
        }

        return implode('>', $parts);
    }

    private function routeEndpointSignature(array $route): string {
        $stops = $route['stopsData'] ?? [];
        $first = $stops[0]['stop_id'] ?? '';
        $last = $stops[count($stops) - 1]['stop_id'] ?? '';
        return $first . '>' . $last;
    }

    private function estimateRouteWalkingMinutes(array $route): int {
        $walkMinutes = 0;
        foreach (($route['legs'] ?? []) as $leg) {
            if (($leg['mode'] ?? '') === 'walk') {
                $walkMinutes += max(1, (int)ceil($this->walkSecondsBetweenStops($leg['from'], $leg['to']) / 60));
            }
        }
        return $walkMinutes;
    }

    private function pickRouteByPreference(array $routes, array $alreadySelected, callable $sortTupleBuilder, bool $requireDifferentLineSignature = false): ?array {
        if (empty($routes)) {
            return null;
        }

        $selectedLineSignatures = array_map(fn($route) => $this->routeLineSignature($route), $alreadySelected);
        $candidates = array_values(array_filter($routes, function (array $route) use ($alreadySelected, $selectedLineSignatures, $requireDifferentLineSignature) {
            foreach ($alreadySelected as $selected) {
                if ($this->routesAreEquivalent($selected, $route)) {
                    return false;
                }
            }

            if ($requireDifferentLineSignature && in_array($this->routeLineSignature($route), $selectedLineSignatures, true)) {
                return false;
            }

            return true;
        }));

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (array $a, array $b) use ($sortTupleBuilder) {
            $left = $sortTupleBuilder($a);
            $right = $sortTupleBuilder($b);
            return $left <=> $right;
        });

        return $candidates[0] ?? null;
    }

    private function refineRouteGeometryForPresentation(array $route): array {
        $segments = $route['segments'] ?? [];
        $route['segments'] = $segments;
        $route['geometry_stats'] = $this->collectGeometryStatsFromSegments($segments);
        $route['coordinates'] = $this->mergeLonLatCoordinatesFromSegments($segments, $route['coordinates'] ?? []);
        return $route;
    }

    private function collectGeometryStatsFromSegments(array $segments): array {
        $stats = [
            'gtfs_shape' => 0,
            'fallback_stop_to_stop' => 0,
            'fallback_straight_line' => 0,
            'street_routed' => 0,
        ];

        foreach ($segments as $segment) {
            $quality = $segment['geometry_quality'] ?? null;
            if ($quality && isset($stats[$quality])) {
                $stats[$quality]++;
            }
        }

        return $stats;
    }

    private function mergeLonLatCoordinatesFromSegments(array $segments, array $fallback = []): array {
        $merged = [];

        foreach ($segments as $segment) {
            $coordinates = $segment['geometry']['coordinates'] ?? ($segment['coordinates'] ?? []);
            foreach ($coordinates as $coord) {
                if (!is_array($coord) || count($coord) < 2) {
                    continue;
                }

                $lon = (float)$coord[0];
                $lat = (float)$coord[1];
                if (!is_finite($lon) || !is_finite($lat)) {
                    continue;
                }

                if (!empty($merged)) {
                    $last = $merged[count($merged) - 1];
                    if (abs($last[0] - $lon) < 0.000001 && abs($last[1] - $lat) < 0.000001) {
                        continue;
                    }
                }

                $merged[] = [$lon, $lat];
            }
        }

        return !empty($merged) ? $merged : $fallback;
    }

    private function formatRoute(array $route, int $index, string $badge): array {
        $stopsData = $route['stopsData'] ?? [];
        $lastStop = $stopsData[count($stopsData) - 1] ?? null;

        $delay = 0;
        $delayText = 'A horas';
        $estimatedTime = $route['total_time'] ?? 0;
        $vehicle = null;

        $tripKey = $route['trip_id'] ?? '';
        $primaryTripId = $route['_primary_trip_id'] ?? (strpos($tripKey, '_') !== false ? explode('_', $tripKey)[0] : $tripKey);

        $firstStopId = $stopsData[0]['stop_id'] ?? null;
        $nextBus = $primaryTripId && $firstStopId
            ? $this->getNextScheduledBus($primaryTripId, $firstStopId)
            : '—';

        try {
            $tripUpdates = $this->realtimeService->getTripUpdates();
            $tripUpdate = $tripUpdates[$primaryTripId] ?? null;
            if ($tripUpdate) {
                $delay = $tripUpdate['delay'] ?? 0;
                $delayText = $tripUpdate['delay_text'] ?? 'A horas';
                $estimatedTime = max(1, ($route['total_time'] ?? 0) + (int)round($delay / 60));

                if ($delay > 0 && is_numeric($nextBus)) {
                    $newMinutes = (int)$nextBus + (int)round($delay / 60);
                    $nextBus = max(1, $newMinutes) . ' min';
                }
            }
            $vehicle = $this->realtimeService->getVehiclePosition($primaryTripId);
        } catch (Exception $e) {
            error_log('GtfsRouteService realtime: ' . $e->getMessage());
        }

        $segments = $route['segments'] ?? [];
        if (empty($segments) && $lastStop) {
            $segments = [[
                'type' => 'bus',
                'from' => $stopsData[0]['stop_name'] ?? '',
                'to' => $lastStop['stop_name'] ?? '',
                'line' => $route['route_name'] ?? '',
                'time' => $estimatedTime,
                'stops' => $route['stop_count'] ?? count($stopsData),
            ]];
        }

        return [
            'id' => $index + 1,
            'type' => ($route['type'] ?? '') === 'direct' ? 'Direto' : 'Com transbordo',
            'badge' => $badge,
            'trip_id' => $tripKey,
            'route_id' => $route['route_id'] ?? null,
            'route_name' => $route['route_name'] ?? '',
            'line' => $route['route_name'] ?? '',
            'duration' => $estimatedTime,
            'scheduled_duration' => $route['total_time'] ?? $estimatedTime,
            'duration_text' => $estimatedTime . ' min',
            'delay' => $delay,
            'delay_text' => $delayText,
            'stops' => $route['stop_count'] ?? count($stopsData),
            'stop_count' => $route['stop_count'] ?? count($stopsData),
            'transfers' => $route['transfers'] ?? 0,
            'coordinates' => $route['coordinates'] ?? [],
            'stopsData' => $stopsData,
            'segments' => $segments,
            'geometry_stats' => $route['geometry_stats'] ?? null,
            'vehicle_position' => $vehicle ? [
                'lat' => $vehicle['latitude'],
                'lon' => $vehicle['longitude'],
                'speed' => $vehicle['speed'],
                'occupancy' => $vehicle['occupancy'],
            ] : null,
            'transfer_stop' => $route['transfer_stop'] ?? null,
            'wait_time' => $route['wait_time'] ?? null,
            'nextBus' => $nextBus,
            'departure_time' => $route['departure_time'] ?? null,
            'arrival_time' => $route['arrival_time'] ?? null,
            'primary_trip_id' => $primaryTripId,
            '_primary_trip_id' => $primaryTripId,
        ];
    }

    private function scoreRoute(array $route): float {
        $total = (float)($route['total_time'] ?? 0);
        $transfers = (int)($route['transfers'] ?? 0);
        $walkPenalty = 0.0;
        $waitPenalty = 0.0;
        $geometryPenalty = 0.0;

        foreach (($route['segments'] ?? []) as $segment) {
            if (isset($segment['wait']) && $segment['wait'] !== null) {
                $waitPenalty += (float)$segment['wait'];
            }

            if (($segment['geometry_quality'] ?? '') === 'fallback_straight_line') {
                $geometryPenalty += self::STRAIGHT_LINE_ROUTE_PENALTY;
            }
        }

        if (!empty($route['legs'])) {
            foreach ($route['legs'] as $leg) {
                if (($leg['mode'] ?? '') === 'walk') {
                    $walkPenalty += max(1.0, $this->walkSecondsBetweenStops($leg['from'], $leg['to']) / 60);
                }
            }
        }

        return $total + ($walkPenalty * 1.15) + ($transfers * 6) + ($waitPenalty * 0.45) + $geometryPenalty;
    }

    // -------------------------------------------------------------------------
    // Caminhada entre paragens
    // -------------------------------------------------------------------------

    private function getWalkingNeighbors(string $stopId): array {
        if (isset($this->walkingNeighborsCache[$stopId])) {
            return $this->walkingNeighborsCache[$stopId];
        }

        $coords = $this->getStopCoords($stopId);
        if (!$coords) {
            $this->walkingNeighborsCache[$stopId] = [];
            return [];
        }

        $lat = (float)$coords['stop_lat'];
        $lon = (float)$coords['stop_lon'];
        $delta = 0.0075;

        $stmt = $this->pdo->prepare("
            SELECT stop_id, stop_lat, stop_lon
            FROM stops
            WHERE stop_lat BETWEEN ? AND ?
              AND stop_lon BETWEEN ? AND ?
              AND stop_id <> ?
            LIMIT 80
        ");
        $stmt->execute([$lat - $delta, $lat + $delta, $lon - $delta, $lon + $delta, $stopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $d = $this->haversineMeters($lat, $lon, (float)$r['stop_lat'], (float)$r['stop_lon']);
            if ($d <= self::MAX_TRANSFER_WALK_METERS && $d > 3) {
                $sec = max(45, (int)round($d / self::WALK_SPEED_MPS));
                $out[] = ['stop_id' => $r['stop_id'], 'walk_seconds' => $sec, 'distance_m' => (int)round($d)];
            }
        }

        usort($out, fn($a, $b) => ($a['walk_seconds'] ?? PHP_INT_MAX) <=> ($b['walk_seconds'] ?? PHP_INT_MAX));
        $this->walkingNeighborsCache[$stopId] = array_slice($out, 0, 8);
        return $this->walkingNeighborsCache[$stopId];
    }

    private function walkSecondsBetweenStops(string $fromId, string $toId): int {
        foreach ($this->getWalkingNeighbors($fromId) as $nb) {
            if ($nb['stop_id'] === $toId) {
                return $nb['walk_seconds'];
            }
        }
        $a = $this->getStopCoords($fromId);
        $b = $this->getStopCoords($toId);
        if (!$a || !$b) {
            return 120;
        }
        $d = $this->haversineMeters(
            (float)$a['stop_lat'],
            (float)$a['stop_lon'],
            (float)$b['stop_lat'],
            (float)$b['stop_lon']
        );
        return max(45, (int)round($d / self::WALK_SPEED_MPS));
    }

    // -------------------------------------------------------------------------
    // Shapes / geometria
    // -------------------------------------------------------------------------

    private function buildCoordinates($shapeId, array $stops, bool $allowRoadFallback = true): array {
        $fallback = $this->getStraightLine($stops);
        $fallbackQuality = count($fallback) > 2 ? 'fallback_stop_to_stop' : 'fallback_straight_line';

        if (empty($stops) || count($stops) < 2) {
            return [
                'coordinates_latlon' => $fallback,
                'coordinates_lonlat' => $this->latLonPairsToLonLat($fallback),
                'source' => 'fallback_straight_line',
                'geometry_quality' => 'fallback_straight_line',
                'shape_points_total' => 0,
                'cut_points_count' => count($fallback),
            ];
        }

        $firstStop = $stops[0];
        $lastStop = $stops[count($stops) - 1];

        if ($shapeId) {
            $stmt = $this->pdo->prepare("
                SELECT shape_pt_lat, shape_pt_lon, shape_pt_sequence
                FROM shapes
                WHERE shape_id = ?
                ORDER BY shape_pt_sequence
            ");
            $stmt->execute([$shapeId]);
            $allPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($allPoints)) {
                $shapeCoords = [];
                foreach ($allPoints as $point) {
                    $shapeCoords[] = [
                        'lat' => floatval($point['shape_pt_lat']),
                        'lon' => floatval($point['shape_pt_lon']),
                        'seq' => intval($point['shape_pt_sequence']),
                    ];
                }

                $shapeMatch = $this->matchShapeSegmentToStops($shapeCoords, $stops);
                if ($shapeMatch) {
                    return [
                        'coordinates_latlon' => $shapeMatch['segment_coords'],
                        'coordinates_lonlat' => $this->latLonPairsToLonLat($shapeMatch['segment_coords']),
                        'source' => 'gtfs_shape',
                        'geometry_quality' => 'gtfs_shape',
                        'shape_points_total' => count($shapeCoords),
                        'cut_points_count' => count($shapeMatch['segment_coords']),
                    ];
                }
            }
        }

        if ($allowRoadFallback && !$this->hasTimedOut()) {
            $premiumRoadGeometry = $this->buildRoadGeometryFromStops($stops, 'driving-traffic');
            if ($premiumRoadGeometry) {
                return $premiumRoadGeometry;
            }
        }

        return [
            'coordinates_latlon' => $fallback,
            'coordinates_lonlat' => $this->latLonPairsToLonLat($fallback),
            'source' => $fallbackQuality,
            'geometry_quality' => $fallbackQuality,
            'shape_points_total' => 0,
            'cut_points_count' => count($fallback),
        ];
    }

    public function buildWalkingGeometry(float $fromLat, float $fromLon, float $toLat, float $toLon): array {
        $fallback = [
            [$fromLat, $fromLon],
            [$toLat, $toLon],
        ];

        $coords = [
            ['stop_lat' => $fromLat, 'stop_lon' => $fromLon],
            ['stop_lat' => $toLat, 'stop_lon' => $toLon],
        ];

        $premium = $this->buildRoadGeometryFromStops($coords, 'walking');
        if ($premium) {
            return [
                'coordinates_lonlat' => $premium['coordinates_lonlat'],
                'source' => $premium['source'],
                'geometry_quality' => $premium['geometry_quality'] ?? 'street_routed',
                'cut_points_count' => $premium['cut_points_count'],
            ];
        }

        return [
            'coordinates_lonlat' => $this->latLonPairsToLonLat($fallback),
            'source' => 'fallback_straight_line',
            'geometry_quality' => 'fallback_straight_line',
            'cut_points_count' => 2,
        ];
    }

    private function findClosestPointOnShape(array $shapeCoords, $targetLat, $targetLon): int {
        $minDistance = PHP_FLOAT_MAX;
        $closestIndex = -1;

        foreach ($shapeCoords as $idx => $point) {
            $distance = abs($point['lat'] - $targetLat) + abs($point['lon'] - $targetLon);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestIndex = $idx;
            }
        }

        return $closestIndex;
    }

    private function matchShapeSegmentToStops(array $shapeCoords, array $stops): ?array {
        if (count($shapeCoords) < self::MIN_SHAPE_POINTS || count($stops) < 2) {
            return null;
        }

        $validationStops = count($stops) <= self::MAX_SEGMENT_STOPS_SCAN
            ? array_values($stops)
            : $this->sampleStopsForRoadGeometry($stops, self::MAX_SHAPE_VALIDATION_STOPS);

        $projections = [];
        $cursor = 0;

        foreach ($validationStops as $stop) {
            if (!isset($stop['stop_lat'], $stop['stop_lon'])) {
                return null;
            }

            $match = $this->findClosestPointOnShapeFromIndex(
                $shapeCoords,
                (float)$stop['stop_lat'],
                (float)$stop['stop_lon'],
                $cursor
            );

            if ($match === null) {
                return null;
            }

            $projections[] = $match;
            $cursor = $match['index'];
        }

        if (count($projections) < 2) {
            return null;
        }

        $matchedIndices = array_column($projections, 'index');
        $distances = array_column($projections, 'distance');
        $coveredStops = array_values(array_filter($distances, fn($distance) => $distance <= self::MAX_SHAPE_STOP_DISTANCE_METERS));
        $coverageRatio = count($coveredStops) / max(1, count($distances));
        $avgDistance = array_sum($distances) / count($distances);
        $maxDistance = max($distances);

        if ($coverageRatio < self::MIN_SHAPE_STOP_COVERAGE_RATIO) {
            return null;
        }

        if ($avgDistance > self::MAX_SHAPE_AVERAGE_DISTANCE_METERS || $maxDistance > (self::MAX_SHAPE_STOP_DISTANCE_METERS * 1.35)) {
            return null;
        }

        $startIdx = $matchedIndices[0];
        $endIdx = $matchedIndices[count($matchedIndices) - 1];
        if ($startIdx >= $endIdx) {
            return null;
        }

        $segmentCoords = [];
        for ($i = $startIdx; $i <= $endIdx; $i++) {
            $segmentCoords[] = [$shapeCoords[$i]['lat'], $shapeCoords[$i]['lon']];
        }

        if (count($segmentCoords) < 2) {
            return null;
        }

        $firstStop = $stops[0];
        $lastStop = $stops[count($stops) - 1];
        $startDistance = $this->haversineMeters(
            (float)$firstStop['stop_lat'],
            (float)$firstStop['stop_lon'],
            (float)$segmentCoords[0][0],
            (float)$segmentCoords[0][1]
        );
        $endDistance = $this->haversineMeters(
            (float)$lastStop['stop_lat'],
            (float)$lastStop['stop_lon'],
            (float)$segmentCoords[count($segmentCoords) - 1][0],
            (float)$segmentCoords[count($segmentCoords) - 1][1]
        );

        if ($startDistance > self::MAX_SHAPE_ENDPOINT_DISTANCE_METERS || $endDistance > self::MAX_SHAPE_ENDPOINT_DISTANCE_METERS) {
            return null;
        }

        $shapeLengthMeters = $this->polylineLengthMetersLatLon($segmentCoords);
        $stopsLengthMeters = $this->polylineLengthMetersFromStops($stops);
        if ($stopsLengthMeters > 0) {
            $lengthRatio = $shapeLengthMeters / $stopsLengthMeters;
            if ($lengthRatio < self::MIN_SHAPE_LENGTH_RATIO || $lengthRatio > self::MAX_SHAPE_LENGTH_RATIO) {
                return null;
            }
        }

        $snappedCoords = $this->buildGeometryFollowingStops($shapeCoords, $stops);
        if (count($snappedCoords) < 2) {
            return null;
        }

        return [
            'segment_coords' => $snappedCoords,
            'matched_indices' => $matchedIndices,
            'distances_m' => $distances,
            'coverage_ratio' => $coverageRatio,
            'shape_length_m' => $shapeLengthMeters,
            'stops_length_m' => $stopsLengthMeters,
        ];
    }

    private function findClosestPointOnShapeFromIndex(array $shapeCoords, float $targetLat, float $targetLon, int $startIndex = 0): ?array {
        $minDistance = PHP_FLOAT_MAX;
        $closestIndex = -1;

        $count = count($shapeCoords);
        for ($idx = max(0, $startIndex); $idx < $count; $idx++) {
            $point = $shapeCoords[$idx];
            $distance = $this->haversineMeters(
                (float)$targetLat,
                (float)$targetLon,
                (float)$point['lat'],
                (float)$point['lon']
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestIndex = $idx;
            }

            // Pequena otimização: se já estamos muito perto, não vale a pena continuar.
            if ($distance <= 12) {
                break;
            }
        }

        if ($closestIndex === -1) {
            return null;
        }

        return [
            'index' => $closestIndex,
            'distance' => $minDistance,
        ];
    }

    private function buildGeometryFollowingStops(array $shapeCoords, array $stops): array {
        if (count($shapeCoords) < 2 || count($stops) < 2) {
            return $this->getStraightLine($stops);
        }

        $projections = [];
        $cursor = 0;
        foreach ($stops as $stop) {
            if (!isset($stop['stop_lat'], $stop['stop_lon'])) {
                return $this->getStraightLine($stops);
            }

            $projection = $this->findClosestPointOnShapeFromIndex(
                $shapeCoords,
                (float)$stop['stop_lat'],
                (float)$stop['stop_lon'],
                $cursor
            );

            if ($projection === null) {
                return $this->getStraightLine($stops);
            }

            $projections[] = $projection;
            $cursor = $projection['index'];
        }

        $geometry = [];
        $this->appendLatLonPoint($geometry, [(float)$stops[0]['stop_lat'], (float)$stops[0]['stop_lon']]);
        $previousIndex = $projections[0]['index'];

        for ($stopIndex = 1; $stopIndex < count($stops); $stopIndex++) {
            $currentIndex = $projections[$stopIndex]['index'];
            for ($shapeIndex = $previousIndex + 1; $shapeIndex <= $currentIndex; $shapeIndex++) {
                $this->appendLatLonPoint($geometry, [
                    (float)$shapeCoords[$shapeIndex]['lat'],
                    (float)$shapeCoords[$shapeIndex]['lon'],
                ]);
            }

            $this->appendLatLonPoint($geometry, [
                (float)$stops[$stopIndex]['stop_lat'],
                (float)$stops[$stopIndex]['stop_lon'],
            ]);
            $previousIndex = $currentIndex;
        }

        return count($geometry) >= 2 ? $geometry : $this->getStraightLine($stops);
    }

    private function appendLatLonPoint(array &$geometry, array $point): void {
        if (count($point) < 2) {
            return;
        }

        $lat = (float)$point[0];
        $lon = (float)$point[1];
        if (!is_finite($lat) || !is_finite($lon)) {
            return;
        }

        if (!empty($geometry)) {
            $last = $geometry[count($geometry) - 1];
            if (abs($last[0] - $lat) < 0.000001 && abs($last[1] - $lon) < 0.000001) {
                return;
            }
        }

        $geometry[] = [$lat, $lon];
    }

    private function polylineLengthMetersLatLon(array $coordinates): float {
        if (count($coordinates) < 2) {
            return 0.0;
        }

        $total = 0.0;
        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $from = $coordinates[$i];
            $to = $coordinates[$i + 1];
            if (!is_array($from) || !is_array($to) || count($from) < 2 || count($to) < 2) {
                continue;
            }

            $total += $this->haversineMeters(
                (float)$from[0],
                (float)$from[1],
                (float)$to[0],
                (float)$to[1]
            );
        }

        return $total;
    }

    private function polylineLengthMetersFromStops(array $stops): float {
        $coordinates = [];
        foreach ($stops as $stop) {
            if (!isset($stop['stop_lat'], $stop['stop_lon'])) {
                continue;
            }
            $coordinates[] = [(float)$stop['stop_lat'], (float)$stop['stop_lon']];
        }

        return $this->polylineLengthMetersLatLon($coordinates);
    }

    private function buildRoadGeometryFromStops(array $stops, string $profile): ?array {
        $sampledStops = $this->sampleStopsForRoadGeometry($stops, self::MAPBOX_MAX_WAYPOINTS);
        if (count($sampledStops) < 2) {
            return null;
        }

        $coordinatePairs = [];
        foreach ($sampledStops as $stop) {
            if (!isset($stop['stop_lat'], $stop['stop_lon'])) {
                continue;
            }

            $lat = (float)$stop['stop_lat'];
            $lon = (float)$stop['stop_lon'];
            if (!is_finite($lat) || !is_finite($lon)) {
                continue;
            }

            $coordinatePairs[] = [$lon, $lat];
        }

        if (count($coordinatePairs) < 2) {
            return null;
        }

        $cacheKey = $profile . ':' . md5(json_encode($coordinatePairs));
        if (isset($this->geometryCache[$cacheKey])) {
            return $this->geometryCache[$cacheKey];
        }

        $coordString = implode(';', array_map(
            fn($pair) => rtrim(rtrim(sprintf('%.6F', $pair[0]), '0'), '.') . ',' . rtrim(rtrim(sprintf('%.6F', $pair[1]), '0'), '.'),
            $coordinatePairs
        ));

        $mapboxToken = $this->getMapboxAccessToken();
        if ($mapboxToken === '') {
            $this->geometryCache[$cacheKey] = null;
            return null;
        }

        $url = sprintf(
            'https://api.mapbox.com/directions/v5/mapbox/%s/%s?geometries=geojson&overview=full&steps=false&continue_straight=true&access_token=%s',
            rawurlencode($profile),
            $coordString,
            rawurlencode($mapboxToken)
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "Accept: application/json\r\nUser-Agent: UrbanTraffic/1.0\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            $this->geometryCache[$cacheKey] = null;
            return null;
        }

        $json = json_decode($raw, true);
        $coordinates = $json['routes'][0]['geometry']['coordinates'] ?? null;

        if (!is_array($coordinates) || count($coordinates) < 2) {
            $this->geometryCache[$cacheKey] = null;
            return null;
        }

        $latLon = [];
        foreach ($coordinates as $coord) {
            if (!is_array($coord) || count($coord) < 2) {
                continue;
            }
            $lon = (float)$coord[0];
            $lat = (float)$coord[1];
            if (!is_finite($lat) || !is_finite($lon)) {
                continue;
            }
            $latLon[] = [$lat, $lon];
        }

        if (count($latLon) < 2) {
            $this->geometryCache[$cacheKey] = null;
            return null;
        }

        $result = [
            'coordinates_latlon' => $latLon,
            'coordinates_lonlat' => $coordinates,
            'source' => 'mapbox_' . $profile,
            'geometry_quality' => $profile === 'walking' ? 'street_routed' : 'fallback_stop_to_stop',
            'shape_points_total' => count($coordinates),
            'cut_points_count' => count($coordinates),
        ];

        $this->geometryCache[$cacheKey] = $result;
        return $result;
    }

    private function sampleStopsForRoadGeometry(array $stops, int $maxPoints): array {
        $total = count($stops);
        if ($total <= $maxPoints) {
            return $stops;
        }

        $sampled = [];
        for ($i = 0; $i < $maxPoints; $i++) {
            $index = (int)round($i * ($total - 1) / max(1, $maxPoints - 1));
            $sampled[] = $stops[$index];
        }

        return $sampled;
    }

    private function getStraightLine(array $stops): array {
        $coords = [];
        foreach ($stops as $stop) {
            $coords[] = [floatval($stop['stop_lat']), floatval($stop['stop_lon'])];
        }
        return $coords;
    }

    private function latLonPairsToLonLat(array $coordinates): array {
        $converted = [];
        foreach ($coordinates as $pair) {
            if (!is_array($pair) || count($pair) < 2) {
                continue;
            }
            $converted[] = [(float)$pair[1], (float)$pair[0]];
        }
        return $converted;
    }

    private function injectTransferSegments(array $segmentsUi, array $legs): array {
        if (empty($segmentsUi)) {
            return $segmentsUi;
        }

        $result = [];
        $busIndex = 0;

        foreach ($legs as $leg) {
            if (($leg['mode'] ?? '') === 'ride') {
                if (isset($segmentsUi[$busIndex])) {
                    $result[] = $segmentsUi[$busIndex];
                    $busIndex++;
                }
                continue;
            }

            if (($leg['mode'] ?? '') !== 'walk') {
                continue;
            }

            $fromCoords = $this->getStopCoords($leg['from']);
            $toCoords = $this->getStopCoords($leg['to']);
            if (!$fromCoords || !$toCoords) {
                continue;
            }

            $durationMinutes = max(1, (int)ceil($this->walkSecondsBetweenStops($leg['from'], $leg['to']) / 60));
            $nextBus = $segmentsUi[$busIndex] ?? null;
            $previousBus = !empty($result) ? $result[count($result) - 1] : null;
            $fromLine = $previousBus['line'] ?? null;
            $toLine = $nextBus['line'] ?? null;
            $fromTripId = $previousBus['trip_id'] ?? null;
            $toTripId = $nextBus['trip_id'] ?? null;
            $arrivalTime = $previousBus['arrival_time'] ?? null;
            $departureTime = $nextBus['departure'] ?? null;
            $waitTime = $nextBus['wait'] ?? null;
            $instruction = $toLine
                ? sprintf('Sair da linha %s e apanhar a linha %s', $fromLine ?: 'anterior', $toLine)
                : 'Efetuar transbordo entre paragens';
            $walkingGeometry = $this->buildWalkingGeometry(
                (float)$fromCoords['stop_lat'],
                (float)$fromCoords['stop_lon'],
                (float)$toCoords['stop_lat'],
                (float)$toCoords['stop_lon']
            );

            $result[] = [
                'type' => 'transfer',
                'line' => null,
                'route_id' => null,
                'trip_id' => null,
                'shape_id' => null,
                'from_stop_id' => $leg['from'],
                'to_stop_id' => $leg['to'],
                'from_name' => $fromCoords['stop_name'] ?? 'Transbordo',
                'to_name' => $toCoords['stop_name'] ?? 'Transbordo',
                'from_lat' => (float)$fromCoords['stop_lat'],
                'from_lon' => (float)$fromCoords['stop_lon'],
                'to_lat' => (float)$toCoords['stop_lat'],
                'to_lon' => (float)$toCoords['stop_lon'],
                'from' => [
                    'name' => $fromCoords['stop_name'] ?? 'Transbordo',
                    'latitude' => (float)$fromCoords['stop_lat'],
                    'longitude' => (float)$fromCoords['stop_lon'],
                    'stop_id' => $leg['from'],
                ],
                'to' => [
                    'name' => $toCoords['stop_name'] ?? 'Transbordo',
                    'latitude' => (float)$toCoords['stop_lat'],
                    'longitude' => (float)$toCoords['stop_lon'],
                    'stop_id' => $leg['to'],
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $walkingGeometry['coordinates_lonlat'],
                ],
                'coordinates' => $walkingGeometry['coordinates_lonlat'],
                'geometry_source' => $walkingGeometry['source'] ?? 'fallback_stop_to_stop',
                'geometry_quality' => $walkingGeometry['geometry_quality'] ?? 'fallback_stop_to_stop',
                'duration_minutes' => $durationMinutes,
                'time' => $durationMinutes,
                'stop_count' => 0,
                'stops' => 0,
                'wait' => $waitTime,
                'next_line' => $toLine,
                'arrival_time' => $arrivalTime,
                'departure_time' => $departureTime,
                'markers' => [
                    [
                        'kind' => 'transfer',
                        'type' => 'transfer',
                        'name' => $fromCoords['stop_name'] ?? 'Transbordo',
                        'stop_id' => $leg['from'],
                        'stop_name' => $fromCoords['stop_name'] ?? 'Transbordo',
                        'coordinates' => [(float)$fromCoords['stop_lon'], (float)$fromCoords['stop_lat']],
                        'action' => $instruction,
                        'instruction' => $instruction,
                        'line' => $toLine,
                        'arrival_time' => $arrivalTime,
                        'departure_time' => $departureTime,
                        'wait_time' => $waitTime,
                        'from_line' => $fromLine,
                        'to_line' => $toLine,
                        'from_trip_id' => $fromTripId,
                        'to_trip_id' => $toTripId,
                    ],
                    [
                        'kind' => 'transfer',
                        'type' => 'transfer',
                        'name' => $toCoords['stop_name'] ?? 'Transbordo',
                        'stop_id' => $leg['to'],
                        'stop_name' => $toCoords['stop_name'] ?? 'Transbordo',
                        'coordinates' => [(float)$toCoords['stop_lon'], (float)$toCoords['stop_lat']],
                        'action' => $toLine ?? null ? 'Apanhar linha ' . $toLine : 'Concluir transbordo',
                        'instruction' => $instruction,
                        'line' => $toLine,
                        'arrival_time' => $arrivalTime,
                        'departure_time' => $departureTime,
                        'wait_time' => $waitTime,
                        'from_line' => $fromLine,
                        'to_line' => $toLine,
                        'from_trip_id' => $fromTripId,
                        'to_trip_id' => $toTripId,
                    ],
                ],
                'debug' => [
                    'from_stop_id' => $leg['from'],
                    'to_stop_id' => $leg['to'],
                    'shape_points_total' => $walkingGeometry['cut_points_count'] ?? 2,
                    'geometry_points_returned' => $walkingGeometry['cut_points_count'] ?? 2,
                    'geometry_source' => $walkingGeometry['source'] ?? 'fallback_stop_to_stop',
                    'geometry_quality' => $walkingGeometry['geometry_quality'] ?? 'fallback_stop_to_stop',
                ],
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // GTFS tempo / viagens
    // -------------------------------------------------------------------------

    private function gtfsTimeToSeconds(string $time): int {
        $time = trim($time);
        if ($time === '') {
            return 0;
        }
        $parts = explode(':', $time);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        $s = (int)($parts[2] ?? 0);
        return $h * 3600 + $m * 60 + $s;
    }

    private function secondsToGtfsTime(int $seconds): string {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /** Formata hora GTFS (incl. >24h) para HH:MM na UI */
    private function formatClockForUi(?string $gtfsTime): ?string {
        if ($gtfsTime === null || $gtfsTime === '') {
            return null;
        }
        $gtfsTime = trim($gtfsTime);
        $sec = $this->gtfsTimeToSeconds($gtfsTime);
        $h = intdiv($sec, 3600) % 24;
        $m = intdiv($sec % 3600, 60);
        return sprintf('%02d:%02d', $h, $m);
    }

    private function getStopsFromTripCached(string $tripId): array {
        if (!isset($this->tripStopsCache[$tripId])) {
            $this->tripStopsCache[$tripId] = $this->getStopsFromTrip($tripId);
        }
        return $this->tripStopsCache[$tripId];
    }

    private function getStopsFromTrip(string $tripId): array {
        $startedAt = microtime(true);
        $stmt = $this->pdo->prepare("
            SELECT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon,
                   st.departure_time, st.arrival_time, st.stop_sequence
            FROM stop_times st
            JOIN stops s ON st.stop_id = s.stop_id
            WHERE st.trip_id = ?
            ORDER BY st.stop_sequence
        ");
        $stmt->execute([$tripId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->recordQueryStat('getStopsFromTrip', microtime(true) - $startedAt, count($rows));
        return $rows;
    }

    private function getTripStopSequenceIndex(string $tripId): array {
        if (isset($this->tripStopSequenceIndexCache[$tripId])) {
            return $this->tripStopSequenceIndexCache[$tripId];
        }

        $index = [];
        foreach ($this->getStopsFromTripCached($tripId) as $position => $stopRow) {
            $sequence = isset($stopRow['stop_sequence']) ? (int)$stopRow['stop_sequence'] : null;
            if ($sequence === null) {
                continue;
            }
            $index[$sequence] = $position;
        }

        $this->tripStopSequenceIndexCache[$tripId] = $index;
        return $index;
    }

    private function getShapeIdForTrip(string $tripId): ?string {
        $stmt = $this->pdo->prepare("SELECT shape_id FROM trips WHERE trip_id = ? LIMIT 1");
        $stmt->execute([$tripId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['shape_id'] ?? null;
    }

    private function calculateTravelTime(array $stops): int {
        if (count($stops) < 2) {
            return 1;
        }

        $first = $stops[0];
        $last = $stops[count($stops) - 1];

        if (!empty($first['departure_time']) && !empty($last['arrival_time'])) {
            $start = $this->gtfsTimeToSeconds($first['departure_time']);
            $end = $this->gtfsTimeToSeconds($last['arrival_time']);
            $diff = $end - $start;
            if ($diff > 0) {
                return max(1, (int)round($diff / 60));
            }
        }

        $total = 0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $dist = $this->haversineKm(
                $stops[$i]['stop_lat'],
                $stops[$i]['stop_lon'],
                $stops[$i + 1]['stop_lat'],
                $stops[$i + 1]['stop_lon']
            );
            $total += max(1.0, ($dist * 2) + 0.5);
        }
        return max(1, (int)round($total));
    }

    private function haversineKm($lat1, $lon1, $lat2, $lon2): float {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float {
        return $this->haversineKm($lat1, $lon1, $lat2, $lon2) * 1000;
    }

    private function getStopCoords(string $stopId): ?array {
        if (isset($this->stopsCache[$stopId])) {
            return $this->stopsCache[$stopId];
        }
        $stmt = $this->pdo->prepare("
            SELECT stop_name, stop_lat, stop_lon FROM stops WHERE stop_id = ? LIMIT 1
        ");
        $stmt->execute([$stopId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->stopsCache[$stopId] = $row ?: null;
        return $this->stopsCache[$stopId];
    }

    private function getNextScheduledBus(string $tripId, string $stopId) {
        $nowSec = $this->gtfsTimeToSeconds(date('H:i:s'));
        $nowTime = $this->secondsToGtfsTime($nowSec);

        $stmt = $this->pdo->prepare("
            SELECT departure_time
            FROM stop_times
            WHERE trip_id = ?
              AND stop_id = ?
              AND departure_time > ?
            ORDER BY departure_time
            LIMIT 1
        ");
        $stmt->execute([$tripId, $stopId, $nowTime]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($next) {
            $departure = $this->gtfsTimeToSeconds($next['departure_time']);
            $waitMinutes = (int)round(($departure - $nowSec) / 60);
            return max(1, $waitMinutes);
        }

        return 'Próximo horário não disponível';
    }
}
