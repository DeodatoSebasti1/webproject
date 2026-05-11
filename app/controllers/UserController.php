<?php
// /urban/app/controllers/UserController.php

require_once __DIR__ . '/../../config/database.php';
if (!defined('URBAN_SKIP_AUTH_ROUTER')) {
    define('URBAN_SKIP_AUTH_ROUTER', true);
}
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/../services/AppEventService.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

class UserController {
    private $conn;
    private $auth;
    private $eventLogger;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->auth = new AuthController();
        $this->eventLogger = new AppEventService($conn);
    }
    
    /**
     * Adicionar rota aos favoritos
     */
    public function addFavorite() {
        $user = $this->auth->verifySession();
        if (!$user) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['route_name']) || !isset($data['origin_name']) || !isset($data['destination_name'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Dados incompletos. route_name, origin_name e destination_name são obrigatórios.'
            ]);
            return;
        }
        
        $routeName = $this->normalizeText($data['route_name'] ?? '', 160);
        $originName = $this->normalizeText($data['origin_name'] ?? '', 120);
        $destinationName = $this->normalizeText($data['destination_name'] ?? '', 120);
        $originLat = $this->normalizeCoordinate($data['origin_lat'] ?? null, -90, 90);
        $originLon = $this->normalizeCoordinate($data['origin_lon'] ?? null, -180, 180);
        $destinationLat = $this->normalizeCoordinate($data['destination_lat'] ?? null, -90, 90);
        $destinationLon = $this->normalizeCoordinate($data['destination_lon'] ?? null, -180, 180);
        $routeData = $this->normalizeOptionalJsonString($data['route_data'] ?? null, 65000);

        if ($routeName === '' || $originName === '' || $destinationName === '') {
            $this->respondError('Os nomes da rota, origem e destino são obrigatórios.', 422);
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO favorite_routes 
                (user_id, route_name, origin_name, destination_name, origin_lat, origin_lon, destination_lat, destination_lon, route_data)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                route_data = VALUES(route_data),
                created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $user['id'], $routeName, $originName, $destinationName,
                $originLat, $originLon, $destinationLat, $destinationLon, $routeData
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Rota adicionada aos favoritos.'
            ]);
            $this->eventLogger->log('favorite_added', [
                'route_name' => $routeName,
                'origin_name' => $originName,
                'destination_name' => $destinationName
            ], (int)$user['id'], 'success', 'favorite_route', $routeName);
            
        } catch (Exception $e) {
            error_log("UserController addFavorite error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao adicionar rota aos favoritos.'
            ]);
        }
    }
    
    /**
     * Remover rota dos favoritos
     */
    public function removeFavorite() {
        $user = $this->auth->verifySession();
        if (!$user) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['route_name']) || !isset($data['origin_name']) || !isset($data['destination_name'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Dados incompletos.'
            ]);
            return;
        }
        
        $routeName = $this->normalizeText($data['route_name'] ?? '', 160);
        $originName = $this->normalizeText($data['origin_name'] ?? '', 120);
        $destinationName = $this->normalizeText($data['destination_name'] ?? '', 120);

        if ($routeName === '' || $originName === '' || $destinationName === '') {
            $this->respondError('Dados inválidos para remover favorito.', 422);
            return;
        }

        try {
            $stmt = $this->conn->prepare("
                DELETE FROM favorite_routes 
                WHERE user_id = ? AND route_name = ? AND origin_name = ? AND destination_name = ?
            ");
            $stmt->execute([
                $user['id'], $routeName, $originName, $destinationName
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Rota removida dos favoritos.'
            ]);
            $this->eventLogger->log('favorite_removed', [
                'route_name' => $routeName,
                'origin_name' => $originName,
                'destination_name' => $destinationName
            ], (int)$user['id'], 'info', 'favorite_route', (string)$routeName);
            
        } catch (Exception $e) {
            error_log("UserController removeFavorite error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao remover rota dos favoritos.'
            ]);
        }
    }
    
    /**
     * Listar rotas favoritas
     */
    public function getFavorites() {
        $user = $this->auth->verifySession();
        if (!$user) return;

        $limit = min(500, max(1, (int)($_GET['limit'] ?? 10)));
        
        try {
            $stmt = $this->conn->prepare("
                SELECT route_name, origin_name, destination_name, origin_lat, origin_lon, 
                       destination_lat, destination_lon, route_data, created_at
                FROM favorite_routes 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$user['id']]);
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->respondSuccess(['favorites' => $favorites]);
            
        } catch (Exception $e) {
            error_log("UserController getFavorites error: " . $e->getMessage());
            $this->respondError('Erro ao obter favoritos.');
        }
    }
    
    /**
     * Verificar se uma rota está nos favoritos
     */
    public function isFavorite() {
        $user = $this->auth->verifySession();
        if (!$user) return;
        
        $routeName = $this->normalizeText($_GET['route_name'] ?? '', 160);
        $originName = $this->normalizeText($_GET['origin_name'] ?? '', 120);
        $destinationName = $this->normalizeText($_GET['destination_name'] ?? '', 120);
        
        if (!$routeName || !$originName || !$destinationName) {
            echo json_encode(['is_favorite' => false]);
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM favorite_routes 
                WHERE user_id = ? AND route_name = ? AND origin_name = ? AND destination_name = ?
            ");
            $stmt->execute([$user['id'], $routeName, $originName, $destinationName]);
            
            $isFavorite = $stmt->fetch() !== false;
            
            echo json_encode(['is_favorite' => $isFavorite]);
            
        } catch (Exception $e) {
            error_log("UserController isFavorite error: " . $e->getMessage());
            echo json_encode(['is_favorite' => false]);
        }
    }
    
    /**
     * Adicionar pesquisa ao histórico
     */
    public function addToHistory() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['origin_name']) || !isset($data['destination_name'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Dados incompletos.'
            ]);
            return;
        }
        
        $originName = $this->normalizeText($data['origin_name'] ?? '', 120);
        $destinationName = $this->normalizeText($data['destination_name'] ?? '', 120);
        $originLat = $this->normalizeCoordinate($data['origin_lat'] ?? null, -90, 90);
        $originLon = $this->normalizeCoordinate($data['origin_lon'] ?? null, -180, 180);
        $destinationLat = $this->normalizeCoordinate($data['destination_lat'] ?? null, -90, 90);
        $destinationLon = $this->normalizeCoordinate($data['destination_lon'] ?? null, -180, 180);

        if ($originName === '' || $destinationName === '') {
            $this->respondError('Origem e destino são obrigatórios.', 422);
            return;
        }
        
        $user = $this->getOptionalUser();
        $userId = $user['id'] ?? null;
        $sessionId = session_id();
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO search_history 
                (user_id, session_id, origin_name, destination_name, origin_lat, origin_lon, destination_lat, destination_lon)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $sessionId, $originName, $destinationName,
                $originLat, $originLon, $destinationLat, $destinationLon
            ]);
            
            // Manter apenas as 10 pesquisas mais recentes por utilizador/sessão
            $this->cleanupHistory($userId, $sessionId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Pesquisa adicionada ao histórico.'
            ]);
            $this->eventLogger->log('search_recorded', [
                'origin_name' => $originName,
                'destination_name' => $destinationName,
                'origin_lat' => $originLat,
                'origin_lon' => $originLon,
                'destination_lat' => $destinationLat,
                'destination_lon' => $destinationLon
            ], $userId ? (int)$userId : null, 'info', 'search_history');
            
        } catch (Exception $e) {
            error_log("UserController addToHistory error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao adicionar ao histórico.'
            ]);
        }
    }
    
    /**
     * Obter histórico de pesquisas
     */
    public function getHistory() {
        $user = $this->getOptionalUser();
        $userId = $user['id'] ?? null;
        $sessionId = session_id();
        $limit = min(500, max(1, (int)($_GET['limit'] ?? 10)));
        
        try {
            $stmt = $this->conn->prepare("
                SELECT origin_name, destination_name, origin_lat, origin_lon, destination_lat, destination_lon, searched_at
                FROM search_history 
                WHERE (user_id = ? OR (user_id IS NULL AND session_id = ?))
                ORDER BY searched_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$userId, $sessionId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->respondSuccess(['history' => $history]);
            
        } catch (Exception $e) {
            error_log("UserController getHistory error: " . $e->getMessage());
            $this->respondError('Erro ao obter histórico.');
        }
    }
    
    /**
     * Limpar histórico antigo (manter apenas 10 mais recentes)
     */
    private function cleanupHistory($userId, $sessionId) {
        try {
            if ($userId) {
                // Utilizador autenticado - limpar por user_id
                $stmt = $this->conn->prepare("
                    DELETE FROM search_history 
                    WHERE user_id = ? AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM search_history 
                            WHERE user_id = ? 
                            ORDER BY searched_at DESC 
                            LIMIT 10
                        ) AS recent
                    )
                ");
                $stmt->execute([$userId, $userId]);
            } else {
                // Utilizador não autenticado - limpar por session_id
                $stmt = $this->conn->prepare("
                    DELETE FROM search_history 
                    WHERE user_id IS NULL AND session_id = ? AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM search_history 
                            WHERE user_id IS NULL AND session_id = ? 
                            ORDER BY searched_at DESC 
                            LIMIT 10
                        ) AS recent
                    )
                ");
                $stmt->execute([$sessionId, $sessionId]);
            }
        } catch (Exception $e) {
            error_log("UserController cleanupHistory error: " . $e->getMessage());
        }
    }

    private function getOptionalUser() {
        ob_start();
        $user = $this->auth->verifySession(false);
        ob_end_clean();

        return $user;
    }

    private function respondSuccess(array $payload = []): void {
        echo json_encode(array_merge(['status' => 'success'], $payload));
    }

    private function respondError(string $message, int $statusCode = 500): void {
        http_response_code($statusCode);
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
    }

    private function normalizeText($value, int $maxLength): string {
        $normalized = preg_replace('/\s+/', ' ', trim((string)$value)) ?? '';
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function normalizeCoordinate($value, float $min, float $max): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float)$value;
        if (!is_finite($float) || $float < $min || $float > $max) {
            return null;
        }

        return $float;
    }

    private function normalizeOptionalJsonString($value, int $maxLength): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return null;
        }

        if (strlen($stringValue) > $maxLength) {
            return substr($stringValue, 0, $maxLength);
        }

        return $stringValue;
    }
}

// Router
$action = $_GET['action'] ?? '';
$userController = new UserController();

switch ($action) {
    case 'add_favorite':
        $userController->addFavorite();
        break;
    case 'remove_favorite':
        $userController->removeFavorite();
        break;
    case 'favorites':
        $userController->getFavorites();
        break;
    case 'is_favorite':
        $userController->isFavorite();
        break;
    case 'add_history':
        $userController->addToHistory();
        break;
    case 'history':
        $userController->getHistory();
        break;
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Ação inválida.'
        ]);
}
