-- Tabela de cache para rotas
-- UrbanTraffic - Melhoria 3

CREATE TABLE IF NOT EXISTS route_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origin_lat DECIMAL(10,8) NOT NULL,
    origin_lon DECIMAL(11,8) NOT NULL,
    destination_lat DECIMAL(10,8) NOT NULL,
    destination_lon DECIMAL(11,8) NOT NULL,
    departure_time VARCHAR(8) NOT NULL DEFAULT '00:00',
    route_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para performance
    INDEX idx_origin_dest (origin_lat, origin_lon, destination_lat, destination_lon, departure_time),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at),
    
    -- Índice espacial para busca por proximidade (se suportado)
    -- SPATIAL INDEX idx_spatial_origin (POINT(origin_lon, origin_lat)),
    -- SPATIAL INDEX idx_spatial_dest (POINT(destination_lon, destination_lat))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações adicionais para performance
-- SET GLOBAL innodb_buffer_pool_size = 1G;  -- Ajustar conforme RAM disponível
-- SET GLOBAL query_cache_size = 64M;

-- Estatísticas da tabela
ANALYZE TABLE route_cache;
