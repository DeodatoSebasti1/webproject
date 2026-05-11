<?php

$host = getenv('URBAN_DB_HOST') ?: "localhost";
$port = getenv('URBAN_DB_PORT') ?: "3306";
$dbname = getenv('URBAN_DB_NAME') ?: "urbandb";
$username = getenv('URBAN_DB_USER') ?: "root";
$password = getenv('URBAN_DB_PASSWORD');
if ($password === false) {
    $password = "";
}

$conn = null;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    
    // Testar conexão
    $conn->query("SELECT 1");
    
} catch(PDOException $e) {
    // Log do erro em vez de mostrar ao utilizador
    error_log("Database connection error: " . $e->getMessage());

    if (defined('URBAN_API_REQUEST') && URBAN_API_REQUEST) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Serviço de base de dados indisponível.'
        ]);
        exit;
    }
    
    if (!defined('SUPPRESS_DB_ERROR_OUTPUT') || !SUPPRESS_DB_ERROR_OUTPUT) {
        // Em ambiente de desenvolvimento, mostrar erro detalhado
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>";
            echo "<h3>Erro na ligação à base de dados</h3>";
            echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>Host:</strong> " . htmlspecialchars($host) . "</p>";
            echo "<p><strong>Database:</strong> " . htmlspecialchars($dbname) . "</p>";
            echo "<p><strong>Port:</strong> " . htmlspecialchars($port) . "</p>";
            echo "</div>";
        } else {
            // Em produção, mostrar mensagem genérica
            echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px; text-align: center;'>";
            echo "<h3>Serviço Indisponível</h3>";
            echo "<p>Estamos a ter dificuldades técnicas. Por favor, tente novamente mais tarde.</p>";
            echo "</div>";
        }
    }
    
    // Parar execução em caso de erro crítico
    if (!defined('ALLOW_DB_FAILURE') || !ALLOW_DB_FAILURE) {
        exit;
    }
}

// Função helper para verificar se a conexão está ativa
function isDatabaseConnected() {
    global $conn;
    return $conn instanceof PDO;
}

// Função helper para reconectar se necessário
function reconnectDatabase() {
    global $conn, $host, $dbname, $username, $password;
    
    if ($conn instanceof PDO) {
        return $conn; // Já conectado
    }
    
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $conn = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        return $conn;
    } catch(PDOException $e) {
        error_log("Database reconnection failed: " . $e->getMessage());
        return null;
    }
}
