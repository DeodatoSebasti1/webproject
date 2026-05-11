<?php

require_once __DIR__ . '/../../config/database.php';
if (!defined('URBAN_SKIP_AUTH_ROUTER')) {
    define('URBAN_SKIP_AUTH_ROUTER', true);
}
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/../services/RouteCacheService.php';
require_once __DIR__ . '/../services/AppEventService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

class AdminController {
    private $conn;
    private $auth;
    private $hasRoleColumn;
    private $cacheService;
    private $eventLogger;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->auth = new AuthController();
        $this->hasRoleColumn = $this->columnExists('users', 'role');
        $this->cacheService = new RouteCacheService();
        $this->eventLogger = new AppEventService($conn);
    }

    public function handle(string $action): void {
        $user = $this->auth->verifySession(false);
        if (!$user || (($user['role'] ?? 'user') !== 'admin')) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Acesso restrito a administradores.'
            ]);
            return;
        }

        $days = $this->resolveDays();

        try {
            switch ($action) {
                case 'stats':
                    $this->respondSuccess($this->getStats($days));
                    return;
                case 'recent_users':
                    $this->respondSuccess($this->getRecentUsers());
                    return;
                case 'recent_searches':
                    $this->respondSuccess($this->getRecentSearches());
                    return;
                case 'popular_routes':
                    $this->respondSuccess($this->getPopularRoutes());
                    return;
                case 'searches_by_day':
                    $this->respondSuccess($this->getSearchesByDay());
                    return;
                case 'users_by_day':
                    $this->respondSuccess($this->getUsersByDay());
                    return;
                case 'favorites_by_route':
                    $this->respondSuccess($this->getFavoritesByRoute());
                    return;
                case 'search_users':
                    $this->respondSuccess($this->searchUsers());
                    return;
                case 'user_details':
                    $this->respondSuccess($this->getUserDetails());
                    return;
                case 'clear_cache':
                    $this->requirePost();
                    $this->respondSuccess($this->clearRouteCache($user));
                    return;
                case 'export_metrics_csv':
                    $this->exportMetricsCsv($days, $user);
                    return;
                case 'dashboard':
                    $this->respondSuccess($this->buildDashboardPayload($days, $user));
                    return;
                default:
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Ação inválida.'
                    ]);
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        } catch (Throwable $e) {
            error_log('AdminController action error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao executar ação administrativa.'
            ]);
        }
    }

    private function buildDashboardPayload(int $days, array $user): array {
        $stats = $this->getStats($days);
        $popularRoutes = $this->getPopularRoutes();
        $favoritesByRoute = $this->getFavoritesByRoute();
        $searchPairs = $this->getTopSearchPairs($days);
        $peakHours = $this->getPeakHours($days);
        $eventMix = $this->getEventMix($days);
        $recentEvents = $this->getRecentEvents();
        $tableHealth = $this->getTableHealth();

        return [
            'meta' => [
                'days' => $days,
                'generated_at' => date('c'),
                'admin_name' => $user['name'] ?? 'Administrador',
                'admin_email' => $user['email'] ?? ''
            ],
            'stats' => $stats,
            'highlights' => [
                'top_route' => $popularRoutes[0] ?? null,
                'top_pair' => $searchPairs[0] ?? null,
                'peak_hour' => $peakHours[0] ?? null,
                'top_event' => $eventMix[0] ?? null,
            ],
            'charts' => [
                'searches_by_day' => $this->getSearchesByDay($days),
                'users_by_day' => $this->getUsersByDay($days),
                'favorites_by_route' => $favoritesByRoute,
                'popular_routes' => $popularRoutes,
                'event_mix' => $eventMix,
                'peak_hours' => $peakHours,
                'search_pairs' => $searchPairs,
            ],
            'tables' => [
                'recent_users' => $this->getRecentUsers(),
                'recent_searches' => $this->getRecentSearches(),
                'recent_events' => $recentEvents,
            ],
            'health' => [
                'tables' => $tableHealth,
                'cache' => $this->getCacheHealth(),
                'events' => [
                    'available' => $this->tableExists('app_events'),
                    'recent_total' => $stats['events_period'],
                    'latest_created_at' => $recentEvents[0]['created_at'] ?? null
                ]
            ]
        ];
    }

    private function getStats(int $days): array {
        $usersTotal = $this->scalar("SELECT COUNT(*) FROM users");
        $searchesTotal = $this->scalar("SELECT COUNT(*) FROM search_history");
        $favoritesTotal = $this->scalar("SELECT COUNT(*) FROM favorite_routes");
        $cacheTotal = $this->tableExists('route_cache') ? $this->scalar("SELECT COUNT(*) FROM route_cache") : 0;
        $eventsTotal = $this->tableExists('app_events') ? $this->scalar("SELECT COUNT(*) FROM app_events") : 0;
        $activeSessions = $this->scalar("SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()");
        $activeUsers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE expires_at > NOW()");

        return [
            'users_total' => $usersTotal,
            'users_period' => $this->scalarPrepared(
                "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            ),
            'admin_total' => $this->hasRoleColumn ? $this->scalarPrepared("SELECT COUNT(*) FROM users WHERE role = 'admin'", []) : 0,
            'searches_total' => $searchesTotal,
            'searches_period' => $this->scalarPrepared(
                "SELECT COUNT(*) FROM search_history WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            ),
            'favorites_total' => $favoritesTotal,
            'favorites_period' => $this->scalarPrepared(
                "SELECT COUNT(*) FROM favorite_routes WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            ),
            'route_cache_total' => $cacheTotal,
            'route_cache_fresh' => $this->tableExists('route_cache')
                ? $this->scalar("SELECT COUNT(*) FROM route_cache WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
                : 0,
            'events_total' => $eventsTotal,
            'events_period' => $this->tableExists('app_events')
                ? $this->scalarPrepared("SELECT COUNT(*) FROM app_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$days])
                : 0,
            'active_sessions' => $activeSessions,
            'active_users' => $activeUsers,
            'logged_users_period' => $this->scalarPrepared(
                "SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL AND last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            ),
            'searches_per_user' => $usersTotal > 0 ? round($searchesTotal / $usersTotal, 2) : 0,
            'favorites_per_user' => $usersTotal > 0 ? round($favoritesTotal / $usersTotal, 2) : 0,
            'session_coverage' => $usersTotal > 0 ? round(($activeUsers / $usersTotal) * 100, 1) : 0,
            'last_updated_at' => date('c'),
        ];
    }

    private function searchUsers(): array {
        if (!$this->tableExists('users')) {
            return [];
        }

        $term = trim((string)($_GET['q'] ?? ''));
        if ($term === '') {
            return [];
        }

        $roleSelect = $this->hasRoleColumn ? ', u.role' : '';
        return $this->fetchAllPrepared("
            SELECT
                u.id,
                u.name,
                u.email,
                u.created_at,
                u.last_login
                {$roleSelect},
                COUNT(DISTINCT f.id) AS favorites_total,
                COUNT(DISTINCT h.id) AS searches_total,
                COUNT(DISTINCT CASE WHEN s.expires_at > NOW() THEN s.id END) AS active_sessions
            FROM users u
            LEFT JOIN favorite_routes f ON f.user_id = u.id
            LEFT JOIN search_history h ON h.user_id = u.id
            LEFT JOIN user_sessions s ON s.user_id = u.id
            WHERE u.name LIKE ? OR u.email LIKE ?
            GROUP BY u.id, u.name, u.email, u.created_at, u.last_login" . ($this->hasRoleColumn ? ", u.role" : "") . "
            ORDER BY u.last_login DESC, u.created_at DESC
            LIMIT 20
        ", ["%{$term}%", "%{$term}%"]);
    }

    private function getUserDetails(): array {
        if (!$this->tableExists('users')) {
            return [];
        }

        $userId = (int)($_GET['id'] ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('Utilizador inválido.');
        }

        $roleSelect = $this->hasRoleColumn ? ', u.role' : '';
        $user = $this->fetchOnePrepared("
            SELECT
                u.id,
                u.name,
                u.email,
                u.created_at,
                u.last_login,
                u.is_active
                {$roleSelect}
            FROM users u
            WHERE u.id = ?
            LIMIT 1
        ", [$userId]);

        if (!$user) {
            throw new InvalidArgumentException('Utilizador não encontrado.');
        }

        return [
            'profile' => $user,
            'stats' => [
                'favorites_total' => $this->scalarPrepared("SELECT COUNT(*) FROM favorite_routes WHERE user_id = ?", [$userId]),
                'searches_total' => $this->scalarPrepared("SELECT COUNT(*) FROM search_history WHERE user_id = ?", [$userId]),
                'active_sessions' => $this->scalarPrepared("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND expires_at > NOW()", [$userId]),
            ],
            'recent_searches' => $this->fetchAllPrepared("
                SELECT origin_name, destination_name, searched_at
                FROM search_history
                WHERE user_id = ?
                ORDER BY searched_at DESC
                LIMIT 5
            ", [$userId]),
            'recent_favorites' => $this->fetchAllPrepared("
                SELECT route_name, origin_name, destination_name, created_at
                FROM favorite_routes
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ", [$userId]),
        ];
    }

    private function clearRouteCache(array $adminUser): array {
        if (!$this->tableExists('route_cache')) {
            return [
                'cleared_entries' => 0,
                'message' => 'Tabela route_cache não existe.'
            ];
        }

        $before = $this->scalar("SELECT COUNT(*) FROM route_cache");
        $this->cacheService->clearAllCache();
        $after = $this->scalar("SELECT COUNT(*) FROM route_cache");
        $cleared = max(0, $before - $after);

        $this->eventLogger->log(
            'admin_cache_cleared',
            ['before' => $before, 'after' => $after, 'cleared' => $cleared],
            isset($adminUser['id']) ? (int)$adminUser['id'] : null,
            'warning',
            'route_cache',
            'route_cache'
        );

        return [
            'cleared_entries' => $cleared,
            'remaining_entries' => $after,
            'message' => $cleared > 0
                ? "Cache limpo com sucesso ({$cleared} entradas removidas)."
                : 'Cache já estava vazio.'
        ];
    }

    private function exportMetricsCsv(int $days, array $user): void {
        $dashboard = $this->buildDashboardPayload($days, $user);
        $filename = 'urban_admin_metrics_' . date('Ymd_His') . '.csv';

        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['section', 'metric', 'value']);

        foreach (($dashboard['stats'] ?? []) as $metric => $value) {
            fputcsv($out, ['stats', $metric, is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }

        foreach (($dashboard['highlights'] ?? []) as $metric => $value) {
            fputcsv($out, ['highlights', $metric, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$value]);
        }

        foreach (($dashboard['health']['cache'] ?? []) as $metric => $value) {
            fputcsv($out, ['health_cache', $metric, is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }

        foreach (($dashboard['tables']['recent_users'] ?? []) as $row) {
            fputcsv($out, ['recent_user', $row['email'] ?? '', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }

        fclose($out);
        exit;
    }

    private function getRecentUsers(): array {
        if (!$this->tableExists('users')) {
            return [];
        }

        $roleSelect = $this->columnExists('users', 'role') ? ', role' : '';
        return $this->fetchAll("
            SELECT id, name, email, created_at, last_login {$roleSelect}
            FROM users
            ORDER BY created_at DESC
            LIMIT 8
        ");
    }

    private function getRecentSearches(): array {
        if (!$this->tableExists('search_history')) {
            return [];
        }

        return $this->fetchAll("
            SELECT origin_name, destination_name, searched_at, user_id, session_id
            FROM search_history
            ORDER BY searched_at DESC
            LIMIT 8
        ");
    }

    private function getPopularRoutes(): array {
        if ($this->tableExists('app_events')) {
            $rows = $this->fetchAll("
                SELECT 
                    COALESCE(
                        NULLIF(entity_id, ''),
                        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.first_route_name')),
                        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.route_name'))
                    ) AS route_label,
                    COUNT(*) AS total
                FROM app_events
                WHERE event_type IN ('route_calculated', 'favorite_added')
                GROUP BY route_label
                HAVING route_label IS NOT NULL AND route_label <> ''
                ORDER BY total DESC
                LIMIT 8
            ");
            if (!empty($rows)) {
                return $rows;
            }
        }

        return $this->fetchAll("
            SELECT route_name AS route_label, COUNT(*) AS total
            FROM favorite_routes
            GROUP BY route_name
            ORDER BY total DESC
            LIMIT 8
        ");
    }

    private function getSearchesByDay(int $days = 14): array {
        if (!$this->tableExists('search_history')) {
            return [];
        }

        return $this->fetchAllPrepared("
            SELECT DATE(searched_at) AS label, COUNT(*) AS total
            FROM search_history
            WHERE searched_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(searched_at)
            ORDER BY label ASC
        ", [$days]);
    }

    private function getUsersByDay(int $days = 14): array {
        if (!$this->tableExists('users')) {
            return [];
        }

        return $this->fetchAllPrepared("
            SELECT DATE(created_at) AS label, COUNT(*) AS total
            FROM users
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY label ASC
        ", [$days]);
    }

    private function getFavoritesByRoute(): array {
        if (!$this->tableExists('favorite_routes')) {
            return [];
        }

        return $this->fetchAll("
            SELECT route_name AS label, COUNT(*) AS total
            FROM favorite_routes
            GROUP BY route_name
            ORDER BY total DESC
            LIMIT 8
        ");
    }

    private function getTopSearchPairs(int $days): array {
        if (!$this->tableExists('search_history')) {
            return [];
        }

        return $this->fetchAllPrepared("
            SELECT CONCAT(origin_name, ' → ', destination_name) AS label, COUNT(*) AS total
            FROM search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY origin_name, destination_name
            ORDER BY total DESC, label ASC
            LIMIT 8
        ", [$days]);
    }

    private function getPeakHours(int $days): array {
        if (!$this->tableExists('search_history')) {
            return [];
        }

        return $this->fetchAllPrepared("
            SELECT LPAD(HOUR(searched_at), 2, '0') AS hour_value, CONCAT(LPAD(HOUR(searched_at), 2, '0'), ':00') AS label, COUNT(*) AS total
            FROM search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY HOUR(searched_at)
            ORDER BY total DESC, hour_value ASC
            LIMIT 8
        ", [$days]);
    }

    private function getEventMix(int $days): array {
        if (!$this->tableExists('app_events')) {
            return [];
        }

        return $this->fetchAllPrepared("
            SELECT event_type AS label, severity, COUNT(*) AS total
            FROM app_events
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY event_type, severity
            ORDER BY total DESC, event_type ASC
            LIMIT 8
        ", [$days]);
    }

    private function getRecentEvents(): array {
        if (!$this->tableExists('app_events')) {
            return [];
        }

        return $this->fetchAll("
            SELECT event_type, severity, entity_type, entity_id, created_at,
                   JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.email')) AS payload_email,
                   JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.route_name')) AS payload_route,
                   JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.origin_name')) AS payload_origin,
                   JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.destination_name')) AS payload_destination
            FROM app_events
            ORDER BY created_at DESC
            LIMIT 10
        ");
    }

    private function getCacheHealth(): array {
        if (!$this->tableExists('route_cache')) {
            return [
                'available' => false,
                'entries' => 0,
                'fresh_entries' => 0,
                'latest_update' => null,
                'oldest_update' => null
            ];
        }

        return [
            'available' => true,
            'entries' => $this->scalar("SELECT COUNT(*) FROM route_cache"),
            'fresh_entries' => $this->scalar("SELECT COUNT(*) FROM route_cache WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'latest_update' => $this->scalarValue("SELECT MAX(updated_at) FROM route_cache"),
            'oldest_update' => $this->scalarValue("SELECT MIN(updated_at) FROM route_cache")
        ];
    }

    private function getTableHealth(): array {
        $tables = ['users', 'search_history', 'favorite_routes', 'user_sessions', 'route_cache', 'app_events'];
        $health = [];

        foreach ($tables as $table) {
            $available = $this->tableExists($table);
            $health[] = [
                'table' => $table,
                'available' => $available,
                'rows' => $available ? $this->scalar("SELECT COUNT(*) FROM {$table}") : 0
            ];
        }

        return $health;
    }

    private function scalar(string $sql) {
        try {
            return (int)$this->conn->query($sql)->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    private function fetchOnePrepared(string $sql, array $params): ?array {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log('AdminController prepared single query error: ' . $e->getMessage());
            return null;
        }
    }

    private function scalarPrepared(string $sql, array $params) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    private function scalarValue(string $sql): ?string {
        try {
            $value = $this->conn->query($sql)->fetchColumn();
            return $value !== false ? (string)$value : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function fetchAll(string $sql): array {
        try {
            $stmt = $this->conn->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            error_log('AdminController query error: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchAllPrepared(string $sql, array $params): array {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            error_log('AdminController prepared query error: ' . $e->getMessage());
            return [];
        }
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function respondSuccess($data): void {
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    private function requirePost(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Método inválido.'
            ]);
            exit;
        }
    }

    private function resolveDays(): int {
        $days = (int)($_GET['days'] ?? 30);
        if ($days < 7) {
            return 7;
        }
        if ($days > 180) {
            return 180;
        }
        return $days;
    }
}

$action = $_GET['action'] ?? 'dashboard';
(new AdminController())->handle($action);
