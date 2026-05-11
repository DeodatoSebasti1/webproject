<?php

require_once __DIR__ . '/../services/CarrisApiService.php';
require_once __DIR__ . '/../../config/database.php';

class LineModel {

    private $carris;
    private $pdo;

    public function __construct() {
        global $conn;
        $this->carris = new CarrisApiService();
        $this->pdo = $conn instanceof PDO ? $conn : null;
    }

    public function getLines() {
        $lines = $this->carris->getLines();
        return array_values(array_map([$this, 'normalizeLine'], $lines));
    }

    public function getLineStops($patternId) {
        $pattern = $this->carris->getPattern($patternId);
        return $pattern['path'] ?? [];
    }

    public function getLineStopsByRoute($routeId): array {
        if (!$this->pdo) {
            return ['stops' => [], 'coordinates' => []];
        }

        $tripStmt = $this->pdo->prepare("
            SELECT t.trip_id, t.shape_id
            FROM trips t
            JOIN stop_times st ON st.trip_id = t.trip_id
            WHERE t.route_id = ?
            GROUP BY t.trip_id, t.shape_id
            ORDER BY COUNT(st.stop_id) DESC
            LIMIT 1
        ");
        $tripStmt->execute([$routeId]);
        $trip = $tripStmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            return ['stops' => [], 'coordinates' => []];
        }

        $stopsStmt = $this->pdo->prepare("
            SELECT s.stop_id, s.stop_name, s.stop_lat, s.stop_lon, st.stop_sequence
            FROM stop_times st
            JOIN stops s ON s.stop_id = st.stop_id
            WHERE st.trip_id = ?
            ORDER BY st.stop_sequence
        ");
        $stopsStmt->execute([$trip['trip_id']]);

        $stops = [];
        $fallbackCoordinates = [];
        foreach ($stopsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lat = (float)$row['stop_lat'];
            $lng = (float)$row['stop_lon'];
            if (!$lat || !$lng) {
                continue;
            }

            $stops[] = [
                'id' => $row['stop_id'],
                'nome' => $row['stop_name'],
                'lat' => $lat,
                'lng' => $lng,
            ];
            $fallbackCoordinates[] = [$lat, $lng];
        }

        $shapeCoordinates = $this->fetchShapeCoordinates($trip['shape_id'] ?? null, $stops, $fallbackCoordinates);

        return [
            'stops' => $stops,
            'coordinates' => $shapeCoordinates,
            'shape_id' => $trip['shape_id'] ?? null,
            'geometry_source' => count($shapeCoordinates) > count($fallbackCoordinates) ? 'shape' : 'stops',
        ];
    }

    private function fetchShapeCoordinates($shapeId, array $stops, array $fallbackCoordinates): array {
        if (!$shapeId || count($stops) < 2) {
            return $fallbackCoordinates;
        }

        $stmt = $this->pdo->prepare("
            SELECT shape_pt_lat, shape_pt_lon, shape_pt_sequence
            FROM shapes
            WHERE shape_id = ?
            ORDER BY shape_pt_sequence
        ");
        $stmt->execute([$shapeId]);

        $shape = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $point) {
            $lat = (float)$point['shape_pt_lat'];
            $lng = (float)$point['shape_pt_lon'];
            if ($lat && $lng) {
                $shape[] = ['lat' => $lat, 'lng' => $lng];
            }
        }

        if (count($shape) < 2) {
            return $fallbackCoordinates;
        }

        $firstStop = $stops[0];
        $lastStop = $stops[count($stops) - 1];
        $startIndex = $this->findClosestShapePoint($shape, $firstStop['lat'], $firstStop['lng']);
        $endIndex = $this->findClosestShapePoint($shape, $lastStop['lat'], $lastStop['lng']);

        if ($startIndex < 0 || $endIndex < 0) {
            return $this->shapeToCoordinates($shape);
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

        return $this->shapeToCoordinates($segment);
    }

    private function shapeToCoordinates(array $shape): array {
        return array_map(fn($point) => [$point['lat'], $point['lng']], $shape);
    }

    private function findClosestShapePoint(array $shape, $lat, $lng): int {
        $closestIndex = -1;
        $closestDistance = PHP_FLOAT_MAX;

        foreach ($shape as $index => $point) {
            $distance = (($lat - $point['lat']) * ($lat - $point['lat'])) + (($lng - $point['lng']) * ($lng - $point['lng']));
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestIndex = $index;
            }
        }

        return $closestIndex;
    }

    private function normalizeLine(array $line): array {
        $id = $line['id'] ?? $line['line_id'] ?? $line['short_name'] ?? '';
        $shortName = $line['short_name'] ?? $id;
        $longName = $line['long_name'] ?? $line['name'] ?? $line['display_name'] ?? '';
        $type = $this->detectLineType($line);

        return [
            'id' => (string)$id,
            'short_name' => (string)$shortName,
            'name' => $longName ?: (string)$shortName,
            'long_name' => $longName ?: (string)$shortName,
            'area' => $this->detectArea((string)$id),
            'color' => $this->normalizeHexColor($line['color'] ?? $line['route_color'] ?? null, '4CAF50'),
            'text_color' => $this->normalizeHexColor($line['text_color'] ?? $line['route_text_color'] ?? null, 'FFFFFF'),
            'type' => $type['type'],
            'type_label' => $type['label'],
            'badges' => $type['badges'],
            'municipalities' => $line['municipalities'] ?? [],
            'localities' => $line['localities'] ?? [],
            'routes' => $line['routes'] ?? [],
            'patterns' => $line['patterns'] ?? [],
            'pattern_id' => $this->firstPatternId($line['patterns'] ?? []),
        ];
    }

    private function firstPatternId(array $patterns): ?string {
        if (!$patterns) {
            return null;
        }

        $firstPattern = reset($patterns);
        if (is_array($firstPattern)) {
            return $firstPattern['id'] ?? $firstPattern['pattern_id'] ?? null;
        }

        return (string)$firstPattern;
    }

    private function detectArea(string $lineId): string {
        $firstDigit = substr($lineId, 0, 1);
        return in_array($firstDigit, ['1', '2', '3', '4'], true) ? $firstDigit : 'all';
    }

    private function normalizeHexColor($color, string $fallback): string {
        $color = strtoupper(trim((string)$color));
        if (preg_match('/^[0-9A-F]{6}$/', $color)) {
            return '#' . $color;
        }
        if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
            return $color;
        }

        return '#' . $fallback;
    }

    private function detectLineType(array $line): array {
        $pathType = (int)($line['path_type'] ?? 0);
        $rawName = (string)($line['long_name'] ?? $line['name'] ?? $line['display_name'] ?? '');
        $name = function_exists('mb_strtolower') ? mb_strtolower($rawName, 'UTF-8') : strtolower($rawName);
        $color = strtoupper(trim((string)($line['color'] ?? $line['route_color'] ?? '')));
        $color = ltrim($color, '#');

        $labels = [
            1 => ['type' => 'local', 'label' => 'Local'],
            2 => ['type' => 'regional', 'label' => 'Regional'],
            3 => ['type' => 'intermunicipal', 'label' => 'Intermunicipal'],
        ];

        $base = $labels[$pathType] ?? ['type' => 'regular', 'label' => 'Regular'];
        $badges = [$base['label']];

        if ((int)($line['circular'] ?? 0) === 1 || str_contains($name, 'circular')) {
            $badges[] = 'Circular';
        }
        if ((int)($line['school'] ?? 0) === 1 || str_contains($name, 'escola') || str_contains($name, 'escolar')) {
            $badges[] = 'Escolar';
        }
        if (str_contains($name, 'noturna') || str_contains($name, 'madrugada')) {
            $badges[] = 'Noturna';
        }
        if ($color === 'FDB71A' || str_contains($name, 'via a8') || str_contains($name, 'expresso')) {
            $badges[] = 'Rápida';
        }

        return [
            'type' => $base['type'],
            'label' => $base['label'],
            'badges' => array_values(array_unique($badges)),
        ];
    }
}
