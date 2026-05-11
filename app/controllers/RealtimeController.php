<?php
// /urban/app/controllers/RealtimeController.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../services/GtfsRealtimeService.php';

header('Content-Type: application/json');

function realtimeResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function realtimeError(string $message, string $errorCode = 'invalid_params', int $status = 400, array $extra = []): void {
    realtimeResponse(array_merge([
        "status" => "error",
        "message" => $message,
        "error_code" => $errorCode
    ], $extra), $status);
}

function realtimeUnavailable(string $message, string $action, array $meta = []): void {
    realtimeResponse([
        "status" => "success",
        "available" => false,
        "action" => $action,
        "message" => $message,
        "error_code" => "realtime_unavailable",
        "timestamp" => time(),
        "data_source" => "unavailable",
        "source" => "unavailable",
        "data" => null,
        "meta" => $meta
    ]);
}

try {
    $action = $_GET['action'] ?? 'vehicles';
    $stopId = $_GET['stop_id'] ?? null;
    $tripId = $_GET['trip_id'] ?? null;
    
    $realtime = new GtfsRealtimeService();
    
    switch ($action) {
        case 'vehicles':
            $data = $realtime->getVehiclePositions();
            break;
            
        case 'updates':
            $data = $realtime->getTripUpdates();
            break;
            
        case 'alerts':
            $data = $realtime->getAlerts();
            break;
            
        case 'stop':
            if (!$stopId) realtimeError("stop_id obrigatório", "invalid_params");
            $data = $realtime->getStopRealtime($stopId);
            break;
            
        case 'vehicle':
            if (!$tripId) realtimeError("trip_id obrigatório", "invalid_params");
            $data = $realtime->getVehiclePosition($tripId);
            if (!$data) {
                realtimeUnavailable("Sem veículo realtime disponível para esta viagem", 'vehicle', [
                    'trip_id' => $tripId
                ]);
            }
            break;
            
        case 'eta':
            if (!$stopId) realtimeError("stop_id obrigatório", "invalid_params");
            $data = $realtime->getStopRealtime($stopId);
            break;
            
        case 'trip_eta':
            if (!$tripId || !$stopId) realtimeError("trip_id e stop_id obrigatórios", "invalid_params");
            $data = $realtime->getTripETA($tripId, $stopId);
            if (!$data) {
                realtimeUnavailable("Sem veículo realtime disponível para esta viagem", 'trip_eta', [
                    'trip_id' => $tripId,
                    'stop_id' => $stopId
                ]);
            }
            break;
            
        case 'route':
            $routeId = $_GET['route_id'] ?? null;
            $routeName = $_GET['route_name'] ?? null;
            $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
            $lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
            $showAll = (int)($_GET['showAll'] ?? 0) === 1;
            if (!$routeId && !$routeName && !$tripId) realtimeError("route_id, route_name ou trip_id obrigatório", "invalid_params");

            if ($showAll) {
                $allVehicles = $realtime->getVehiclePositions();
                $data = array_filter($allVehicles, function($vehicle) use ($routeId, $routeName, $tripId) {
                    if ($tripId && isset($vehicle['trip_id']) && $vehicle['trip_id'] === $tripId) {
                        return true;
                    }
                    if ($routeId && isset($vehicle['route_id']) && (string)$vehicle['route_id'] === (string)$routeId) {
                        return true;
                    }
                    if ($routeName && isset($vehicle['route_name']) && (string)$vehicle['route_name'] === (string)$routeName) {
                        return true;
                    }
                    return false;
                });
                $data = array_values($data);
            } else {
                $data = $realtime->findBestVehicleForRoute($tripId, $routeId, $routeName, $lat, $lon);
            }

            if ((is_array($data) && empty($data)) || $data === null) {
                realtimeUnavailable("Sem veículo realtime disponível para esta viagem", 'route', [
                    'trip_id' => $tripId,
                    'route_id' => $routeId,
                    'route_name' => $routeName
                ]);
            }
            break;
            
        case 'summary':
            $data = $realtime->getSystemSummary();
            break;
            
        default:
            realtimeError('Ação inválida', 'invalid_params', 400);
    }

    $dataSource = 'unknown';
    if (is_array($data)) {
        $first = $data;
        if (array_is_list($data)) {
            $first = $data[0] ?? [];
        }
        if (is_array($first) && isset($first['data_source'])) {
            $dataSource = $first['data_source'];
        } elseif (isset($data['data_source'])) {
            $dataSource = $data['data_source'];
        }
    }

    $source = 'fallback';
    if (is_array($data)) {
        $first = array_is_list($data) ? ($data[0] ?? []) : $data;
        if (is_array($first) && isset($first['source'])) {
            $source = $first['source'];
        } elseif (isset($data['source'])) {
            $source = $data['source'];
        } elseif ($dataSource === 'realtime') {
            $source = 'realtime';
        } elseif ($dataSource === 'simulated') {
            $source = 'simulated';
        } elseif (in_array($dataSource, ['scheduled', 'estimated_from_vehicle'], true)) {
            $source = 'estimated';
        }
    }
    
    realtimeResponse([
        "status" => "success",
        "available" => true,
        "action" => $action,
        "timestamp" => time(),
        "data_source" => $dataSource,
        "source" => $source,
        "data" => $data
    ]);
    
} catch (Exception $e) {
    realtimeError($e->getMessage(), 'internal_error', 500);
}
