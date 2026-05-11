<?php
// /urban/setup_database.php

$dbHost = getenv('URBAN_DB_HOST') ?: 'localhost';
$dbPort = getenv('URBAN_DB_PORT') ?: '3306';
$dbName = getenv('URBAN_DB_NAME') ?: 'urbandb';
$dbUser = getenv('URBAN_DB_USER') ?: 'root';
$dbPassword = getenv('URBAN_DB_PASSWORD');
if ($dbPassword === false) {
    $dbPassword = '';
}

function connectPdo(string $dsn, string $user, string $password): PDO {
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
}

function tableExists(PDO $conn, string $table): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $conn, string $table, string $index): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function runStatement(PDO $conn, string $sql, string $message): void {
    echo '<div>' . htmlspecialchars($message) . '...</div>';
    $conn->exec($sql);
}

echo "<h2>UrbanTraffic - Setup da Base de Dados</h2>";
echo "<p><strong>Host:</strong> " . htmlspecialchars($dbHost) . ":" . htmlspecialchars($dbPort) . "</p>";
echo "<p><strong>Base de dados:</strong> " . htmlspecialchars($dbName) . "</p>";

try {
    $serverConn = connectPdo("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPassword);
    $serverConn->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green;'>Base de dados '{$dbName}' criada/verificada com sucesso.</p>";

    $conn = connectPdo("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPassword);

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela users");

    if (!columnExists($conn, 'users', 'role')) {
        runStatement($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER name", "A adicionar coluna role em users");
    }

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS favorite_routes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            route_name VARCHAR(255) NOT NULL,
            origin_name VARCHAR(255) NOT NULL,
            destination_name VARCHAR(255) NOT NULL,
            origin_lat DECIMAL(10,8) NOT NULL,
            origin_lon DECIMAL(11,8) NOT NULL,
            destination_lat DECIMAL(10,8) NOT NULL,
            destination_lon DECIMAL(11,8) NOT NULL,
            route_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_route (user_id, origin_name, destination_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela favorite_routes");

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS search_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            session_id VARCHAR(255) NULL,
            origin_name VARCHAR(255) NOT NULL,
            destination_name VARCHAR(255) NOT NULL,
            origin_lat DECIMAL(10,8) NOT NULL,
            origin_lon DECIMAL(11,8) NOT NULL,
            destination_lat DECIMAL(10,8) NOT NULL,
            destination_lon DECIMAL(11,8) NOT NULL,
            searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela search_history");

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela user_sessions");

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS route_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            origin_lat DECIMAL(10,8) NOT NULL,
            origin_lon DECIMAL(11,8) NOT NULL,
            destination_lat DECIMAL(10,8) NOT NULL,
            destination_lon DECIMAL(11,8) NOT NULL,
            departure_time VARCHAR(8) NOT NULL DEFAULT '00:00',
            route_data JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela route_cache");

    if (!columnExists($conn, 'route_cache', 'departure_time')) {
        runStatement($conn, "ALTER TABLE route_cache ADD COLUMN departure_time VARCHAR(8) NOT NULL DEFAULT '00:00' AFTER destination_lon", "A adicionar departure_time em route_cache");
    }

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS app_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            event_type VARCHAR(80) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            entity_type VARCHAR(80) NULL,
            entity_id VARCHAR(255) NULL,
            payload_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_app_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela app_events");

    runStatement($conn, "
        CREATE TABLE IF NOT EXISTS access_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            path VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL DEFAULT 'GET',
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_access_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", "A criar tabela access_logs");

    $indexes = [
        ['users', 'idx_email', "CREATE INDEX idx_email ON users(email)"],
        ['users', 'idx_created_at', "CREATE INDEX idx_created_at ON users(created_at)"],
        ['users', 'idx_role', "CREATE INDEX idx_role ON users(role)"],
        ['favorite_routes', 'idx_user_id', "CREATE INDEX idx_user_id ON favorite_routes(user_id)"],
        ['favorite_routes', 'idx_favorite_routes_user_created', "CREATE INDEX idx_favorite_routes_user_created ON favorite_routes(user_id, created_at)"],
        ['favorite_routes', 'idx_favorite_routes_created_at', "CREATE INDEX idx_favorite_routes_created_at ON favorite_routes(created_at)"],
        ['favorite_routes', 'idx_route_name', "CREATE INDEX idx_route_name ON favorite_routes(route_name)"],
        ['search_history', 'idx_user_id', "CREATE INDEX idx_user_id ON search_history(user_id)"],
        ['search_history', 'idx_search_history_user_searched', "CREATE INDEX idx_search_history_user_searched ON search_history(user_id, searched_at)"],
        ['search_history', 'idx_search_history_created_at', "CREATE INDEX idx_search_history_created_at ON search_history(searched_at)"],
        ['search_history', 'idx_session_id', "CREATE INDEX idx_session_id ON search_history(session_id)"],
        ['search_history', 'idx_searched_at', "CREATE INDEX idx_searched_at ON search_history(searched_at)"],
        ['user_sessions', 'idx_session_token', "CREATE INDEX idx_session_token ON user_sessions(session_token)"],
        ['user_sessions', 'idx_user_id', "CREATE INDEX idx_user_id ON user_sessions(user_id)"],
        ['user_sessions', 'idx_expires_at', "CREATE INDEX idx_expires_at ON user_sessions(expires_at)"],
        ['route_cache', 'idx_origin_dest', "CREATE INDEX idx_origin_dest ON route_cache(origin_lat, origin_lon, destination_lat, destination_lon, departure_time)"],
        ['route_cache', 'idx_created_at', "CREATE INDEX idx_created_at ON route_cache(created_at)"],
        ['route_cache', 'idx_updated_at', "CREATE INDEX idx_updated_at ON route_cache(updated_at)"],
        ['app_events', 'idx_app_events_type_created', "CREATE INDEX idx_app_events_type_created ON app_events(event_type, created_at)"],
        ['app_events', 'idx_app_events_user_created', "CREATE INDEX idx_app_events_user_created ON app_events(user_id, created_at)"],
        ['access_logs', 'idx_access_logs_path_created', "CREATE INDEX idx_access_logs_path_created ON access_logs(path, created_at)"],
    ];

    foreach ($indexes as [$table, $indexName, $sql]) {
        if (!tableExists($conn, $table) || indexExists($conn, $table, $indexName)) {
            continue;
        }
        runStatement($conn, $sql, "A criar índice {$indexName}");
    }

    $optionalGtfsIndexes = [
        ['stop_times', 'idx_stop_times_trip_stop', "CREATE INDEX idx_stop_times_trip_stop ON stop_times(trip_id, stop_sequence)"],
        ['stop_times', 'idx_stop_times_stop_time', "CREATE INDEX idx_stop_times_stop_time ON stop_times(stop_id, departure_time)"],
        ['stop_times', 'idx_stop_times_stop_depart_trip_seq', "CREATE INDEX idx_stop_times_stop_depart_trip_seq ON stop_times(stop_id, departure_time, trip_id, stop_sequence)"],
        ['trips', 'idx_trips_route_service', "CREATE INDEX idx_trips_route_service ON trips(route_id, service_id)"],
        ['stops', 'idx_stops_location', "CREATE INDEX idx_stops_location ON stops(stop_lat, stop_lon)"],
        ['routes', 'idx_routes_short_name', "CREATE INDEX idx_routes_short_name ON routes(route_short_name)"],
    ];

    foreach ($optionalGtfsIndexes as [$table, $indexName, $sql]) {
        if (!tableExists($conn, $table) || indexExists($conn, $table, $indexName)) {
            continue;
        }
        runStatement($conn, $sql, "A criar índice GTFS {$indexName}");
    }

    echo "<h3 style='color: green;'>Setup concluído com sucesso!</h3>";
    echo "<p>Backoffice, autenticação, favoritos, histórico, cache e logging académico estão preparados.</p>";
    echo "<p><strong>Nota:</strong> as tabelas GTFS (stops, trips, routes, stop_times, shapes...) continuam a ter de ser importadas separadamente para o motor de rotas funcionar totalmente.</p>";
    echo "<p><a href='public/index.php'>Ir para a aplicação</a> | <a href='public/admin.php'>Ir para o backoffice</a></p>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Erro durante o setup:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Verifique se o MySQL do XAMPP está ativo e, se necessário, use <code>URBAN_DB_HOST=127.0.0.1</code> para evitar problemas de socket.</p>";
}
