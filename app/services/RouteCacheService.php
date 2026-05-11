<?php
// /urban/app/services/RouteCacheService.php

require_once __DIR__ . '/../../config/database.php';

class RouteCacheService {
    private $conn;
    private $cache = [];
    private $cacheTimeout = 300; // 5 minutos
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Obter rota do cache
     */
    public function getRoute($originLat, $originLon, $destLat, $destLon, $departureTime = null) {
        $cacheKey = $this->generateCacheKey($originLat, $originLon, $destLat, $destLon, $departureTime);
        
        // Verificar cache em memória
        if (isset($this->cache[$cacheKey]) && (time() - $this->cache[$cacheKey]['timestamp']) < $this->cacheTimeout) {
            error_log("Cache HIT (memory): $cacheKey");
            return $this->cache[$cacheKey]['data'];
        }
        
        // Verificar cache na base de dados
        $cachedRoute = $this->getCachedRouteFromDB($originLat, $originLon, $destLat, $destLon, $departureTime);
        if ($cachedRoute) {
            error_log("Cache HIT (database): $cacheKey");
            
            // Armazenar em memória
            $this->cache[$cacheKey] = [
                'data' => $cachedRoute,
                'timestamp' => time()
            ];
            
            return $cachedRoute;
        }
        
        error_log("Cache MISS: $cacheKey");
        return null;
    }
    
    /**
     * Guardar rota no cache
     */
    public function setRoute($originLat, $originLon, $destLat, $destLon, $routeData, $departureTime = null) {
        $cacheKey = $this->generateCacheKey($originLat, $originLon, $destLat, $destLon, $departureTime);
        
        // Armazenar em memória
        $this->cache[$cacheKey] = [
            'data' => $routeData,
            'timestamp' => time()
        ];
        
        // Armazenar na base de dados
        $this->setCachedRouteInDB($originLat, $originLon, $destLat, $destLon, $routeData, $departureTime);
        
        error_log("Cache SET: $cacheKey");
    }
    
    /**
     * Limpar cache antigo
     */
    public function cleanupCache() {
        // Limpar cache em memória
        foreach ($this->cache as $key => $value) {
            if ((time() - $value['timestamp']) > $this->cacheTimeout) {
                unset($this->cache[$key]);
            }
        }
        
        // Limpar cache na base de dados
        $this->cleanupCacheDB();
    }
    
    /**
     * Gerar chave de cache única
     */
    private function normalizeDepartureTime($departureTime): string {
        if (!$departureTime) {
            return date('H:i');
        }

        $parts = explode(':', (string)$departureTime);
        $hour = isset($parts[0]) ? max(0, (int)$parts[0]) : 0;
        $minute = isset($parts[1]) ? max(0, (int)$parts[1]) : 0;

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function generateCacheKey($originLat, $originLon, $destLat, $destLon, $departureTime = null) {
        // Arredondar coordenadas para 4 casas decimais (~10m precisão)
        $originLat = round($originLat, 4);
        $originLon = round($originLon, 4);
        $destLat = round($destLat, 4);
        $destLon = round($destLon, 4);
        
        $departureTime = $this->normalizeDepartureTime($departureTime);

        return md5("{$originLat}_{$originLon}_{$destLat}_{$destLon}_{$departureTime}");
    }
    
    /**
     * Obter rota cacheada da base de dados
     */
    private function getCachedRouteFromDB($originLat, $originLon, $destLat, $destLon, $departureTime = null) {
        try {
            $departureTime = $this->normalizeDepartureTime($departureTime);
            $stmt = $this->conn->prepare("
                SELECT route_data, created_at 
                FROM route_cache 
                WHERE ABS(origin_lat - ?) < 0.0001 
                AND ABS(origin_lon - ?) < 0.0001 
                AND ABS(destination_lat - ?) < 0.0001 
                AND ABS(destination_lon - ?) < 0.0001 
                AND departure_time = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$originLat, $originLon, $destLat, $destLon, $departureTime]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $decoded = json_decode($result['route_data'], true);
                if ($this->isCompatibleCachedRoute($decoded)) {
                    return $decoded;
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("RouteCacheService getCachedRouteFromDB error: " . $e->getMessage());
            return null;
        }
    }

    private function isCompatibleCachedRoute($cachedRoute): bool {
        if (!is_array($cachedRoute) || !isset($cachedRoute['routes']) || !is_array($cachedRoute['routes'])) {
            return false;
        }

        foreach ($cachedRoute['routes'] as $route) {
            if (!is_array($route)) {
                return false;
            }

            foreach (($route['segments'] ?? []) as $segment) {
                if (!is_array($segment)) {
                    return false;
                }

                if (!array_key_exists('geometry_quality', $segment)) {
                    return false;
                }

                if (($segment['type'] ?? '') === 'bus' && ($segment['geometry_source'] ?? '') === 'mapbox_driving-traffic') {
                    return false;
                }
            }
        }

        return true;
    }
    
    /**
     * Guardar rota cacheada na base de dados
     */
    private function setCachedRouteInDB($originLat, $originLon, $destLat, $destLon, $routeData, $departureTime = null) {
        try {
            $departureTime = $this->normalizeDepartureTime($departureTime);
            // Remover entradas antigas para a mesma rota
            $stmt = $this->conn->prepare("
                DELETE FROM route_cache 
                WHERE ABS(origin_lat - ?) < 0.0001 
                AND ABS(origin_lon - ?) < 0.0001 
                AND ABS(destination_lat - ?) < 0.0001 
                AND ABS(destination_lon - ?) < 0.0001
                AND departure_time = ?
            ");
            $stmt->execute([$originLat, $originLon, $destLat, $destLon, $departureTime]);
            
            // Inserir nova entrada
            $stmt = $this->conn->prepare("
                INSERT INTO route_cache 
                (origin_lat, origin_lon, destination_lat, destination_lon, departure_time, route_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $originLat, $originLon, $destLat, $destLon, $departureTime,
                json_encode($routeData)
            ]);
            
        } catch (Exception $e) {
            error_log("RouteCacheService setCachedRouteInDB error: " . $e->getMessage());
        }
    }
    
    /**
     * Limpar cache antigo na base de dados
     */
    private function cleanupCacheDB() {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM route_cache 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                error_log("Cache cleanup: removed $deleted old entries");
            }
            
        } catch (Exception $e) {
            error_log("RouteCacheService cleanupCacheDB error: " . $e->getMessage());
        }
    }
    
    /**
     * Obter estatísticas do cache
     */
    public function getCacheStats() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total_entries,
                    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as recent_entries,
                    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as daily_entries
                FROM route_cache
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats['memory_entries'] = count($this->cache);
            $stats['cache_timeout'] = $this->cacheTimeout;
            
            return $stats;
        } catch (Exception $e) {
            error_log("RouteCacheService getCacheStats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Limpar todo o cache
     */
    public function clearAllCache() {
        // Limpar cache em memória
        $this->cache = [];
        
        // Limpar cache na base de dados
        try {
            $stmt = $this->conn->prepare("DELETE FROM route_cache");
            $stmt->execute();
            
            error_log("All cache cleared");
        } catch (Exception $e) {
            error_log("RouteCacheService clearAllCache error: " . $e->getMessage());
        }
    }
    
    /**
     * Pré-carregar cache para rotas populares
     */
    public function preloadPopularRoutes() {
        try {
            // Obter rotas mais pesquisadas do histórico
            $stmt = $this->conn->prepare("
                SELECT origin_lat, origin_lon, destination_lat, destination_lon,
                       COUNT(*) as search_count
                FROM search_history 
                WHERE origin_lat IS NOT NULL AND destination_lat IS NOT NULL
                GROUP BY origin_lat, origin_lon, destination_lat, destination_lon
                HAVING search_count > 5
                ORDER BY search_count DESC
                LIMIT 20
            ");
            $stmt->execute();
            $popularRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $preloaded = 0;
            foreach ($popularRoutes as $route) {
                $cached = $this->getCachedRouteFromDB(
                    $route['origin_lat'], $route['origin_lon'],
                    $route['destination_lat'], $route['destination_lon']
                );
                
                if ($cached) {
                    $cacheKey = $this->generateCacheKey(
                        $route['origin_lat'], $route['origin_lon'],
                        $route['destination_lat'], $route['destination_lon'],
                        $route['departure_time'] ?? null
                    );
                    
                    $this->cache[$cacheKey] = [
                        'data' => $cached,
                        'timestamp' => time()
                    ];
                    
                    $preloaded++;
                }
            }
            
            error_log("Preloaded $preloaded popular routes into memory cache");
            return $preloaded;
            
        } catch (Exception $e) {
            error_log("RouteCacheService preloadPopularRoutes error: " . $e->getMessage());
            return 0;
        }
    }
}
