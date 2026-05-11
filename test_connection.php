<?php
// /urban/test_connection.php - Script para testar conexão e tabelas

require_once __DIR__ . '/config/database.php';

echo "<h2>UrbanTraffic - Teste de Conexão à Base de Dados</h2>";

// 1. Testar conexão básica
echo "<h3>1. Teste de conexão básica...</h3>";

if (isDatabaseConnected()) {
    echo "<p style='color: green;'>Conexão à base de dados: <strong>SUCESSO</strong></p>";
    echo "<p>Host: " . htmlspecialchars($host) . "</p>";
    echo "<p>Database: " . htmlspecialchars($dbname) . "</p>";
    echo "<p>Username: " . htmlspecialchars($username) . "</p>";
} else {
    echo "<p style='color: red;'>Conexão à base de dados: <strong>FALHOU</strong></p>";
    echo "<p>Verifique se o MySQL está a correr e se a base de dados 'urbandb' existe.</p>";
    exit;
}

// 2. Verificar se as tabelas necessárias existem
echo "<h3>2. Verificação de tabelas...</h3>";

$required_tables = [
    'users' => 'Tabela de utilizadores',
    'favorite_routes' => 'Rotas favoritas',
    'search_history' => 'Histórico de pesquisas',
    'user_sessions' => 'Sessões de utilizador',
    'route_cache' => 'Cache de rotas'
];

$existing_tables = [];
$missing_tables = [];

try {
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_tables as $table => $description) {
        if (in_array($table, $tables)) {
            $existing_tables[$table] = $description;
            echo "<p style='color: green;'>Tabela '$table': <strong>EXISTE</strong> - $description</p>";
        } else {
            $missing_tables[$table] = $description;
            echo "<p style='color: red;'>Tabela '$table': <strong>FALTA</strong> - $description</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro ao verificar tabelas: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Testar inserção/utilização se as tabelas existirem
if (empty($missing_tables)) {
    echo "<h3>3. Teste de operações na base de dados...</h3>";
    
    try {
        // Testar inserção na tabela users
        $test_email = 'test_' . time() . '@example.com';
        $stmt = $conn->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
        $stmt->execute([$test_email, password_hash('test123', PASSWORD_DEFAULT), 'Test User']);
        
        $user_id = $conn->lastInsertId();
        echo "<p style='color: green;'>Inserção na tabela 'users': <strong>SUCESSO</strong> (ID: $user_id)</p>";
        
        // Testar seleção
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<p style='color: green;'>Seleção na tabela 'users': <strong>SUCESSO</strong></p>";
            echo "<small>Email: " . htmlspecialchars($user['email']) . "</small>";
        }
        
        // Limpar teste
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        echo "<p style='color: blue;'>Dados de teste limpos.</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erro ao testar operações: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<h3>3. Ação necessária</h3>";
    echo "<p style='color: orange;'>Existem tabelas em falta. Execute o <a href='setup_database.php'>setup_database.php</a> para criar todas as tabelas.</p>";
}

// 4. Verificar permissões
echo "<h3>4. Verificação de permissões...</h3>";

try {
    $stmt = $conn->query("SHOW GRANTS FOR CURRENT_USER()");
    $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Permissões do utilizador atual:</p>";
    echo "<ul>";
    foreach ($grants as $grant) {
        echo "<li><small>" . htmlspecialchars($grant) . "</small></li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: orange;'>Não foi possível verificar permissões: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Voltar para a aplicação</a> | <a href='setup_database.php'>Executar Setup</a></p>";
?>
