<?php
// /urban/public/router.php - Roteador principal

// Obter a rota solicitada
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/router.php';

// Parse da URL para encontrar o controller e action
$path = parse_url($request_uri, PHP_URL_PATH) ?: '/';

/**
 * Normaliza o path para algo relativo à pasta public.
 * Exemplos:
 * - /urban/public/lines      => /lines
 * - /urban/public/api/routes => /api/routes
 * - /lines                   => /lines
 */
function normalizeRouterPath(string $path, string $scriptName): string
{
    $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $normalized = '/' . ltrim($path, '/');

    if ($baseDir !== '' && $baseDir !== '.' && $baseDir !== '/') {
        if ($normalized === $baseDir) {
            return '/';
        }

        if (strpos($normalized, $baseDir . '/') === 0) {
            $trimmed = substr($normalized, strlen($baseDir));
            return $trimmed === '' ? '/' : $trimmed;
        }
    }

    return $normalized === '' ? '/' : $normalized;
}

function pathMatches(string $path, array $candidates): bool
{
    foreach ($candidates as $candidate) {
        if ($path === $candidate) {
            return true;
        }
    }

    return false;
}

$normalizedPath = normalizeRouterPath($path, $script_name);

// Em endpoints JSON, warnings no ecrã quebram response.json().
$isApiRequest = strpos($normalizedPath, '/api/') === 0;
if ($isApiRequest && !defined('URBAN_API_REQUEST')) {
    define('URBAN_API_REQUEST', true);
}
ini_set('display_errors', $isApiRequest ? 0 : 1);
ini_set('display_startup_errors', $isApiRequest ? 0 : 1);
error_reporting(E_ALL);

// ==================== ROTAS DA API (retornam JSON) ====================

if (pathMatches($normalizedPath, ['/api/routes', '/RouteController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/RouteController.php';
    exit;
}

if (pathMatches($normalizedPath, ['/api/realtime', '/RealtimeController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/RealtimeController.php';
    exit;
}

if (pathMatches($normalizedPath, ['/api/search', '/SearchController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/SearchController.php';
    exit;
}

if (pathMatches($normalizedPath, ['/api/auth', '/AuthController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/AuthController.php';
    exit;
}

if (pathMatches($normalizedPath, ['/api/user', '/UserController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/UserController.php';
    exit;
}

if (pathMatches($normalizedPath, ['/api/admin', '/AdminController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/AdminController.php';
    exit;
}

if ($normalizedPath === '/api/line/stops') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/LineController.php';
    (new LineController())->stops();
    exit;
}

if (pathMatches($normalizedPath, ['/api/lines', '/LineController.php'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../app/controllers/LineController.php';
    (new LineController())->index();
    exit;
}

// ==================== ROTAS DE PÁGINAS (retornam HTML) ====================

// Página inicial
if (pathMatches($normalizedPath, ['/', '/index.php'])) {
    // Remove o header JSON para páginas HTML
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/index.php';
    exit;
}

// Página de resultados
if (pathMatches($normalizedPath, ['/results', '/results.php'])) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/results.php';
    exit;
}

// Página de linhas
if (pathMatches($normalizedPath, ['/lines', '/lines.php'])) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/lines.php';
    exit;
}

// Página de configurações
if (pathMatches($normalizedPath, ['/configuracoes', '/configuracoes.php'])) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/configuracoes.php';
    exit;
}

// Página dashboard do utilizador
if (pathMatches($normalizedPath, ['/dashboard', '/dashboard.php'])) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/dashboard.php';
    exit;
}

if (pathMatches($normalizedPath, ['/admin', '/admin.php'])) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/admin.php';
    exit;
}

// Arquivos estáticos (CSS, JS, imagens) - verificar se existem e servir
$static_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
$ext = pathinfo($normalizedPath, PATHINFO_EXTENSION);
if (in_array($ext, $static_extensions)) {
    $file_path = __DIR__ . $normalizedPath;
    if (file_exists($file_path)) {
        // Servir o arquivo estático diretamente
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml'
        ];
        $mime = $mime_types[$ext] ?? 'application/octet-stream';
        header("Content-Type: $mime");
        readfile($file_path);
        exit;
    }
}

// Se não encontrar nada, retornar 404
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Rota não encontrada']);
