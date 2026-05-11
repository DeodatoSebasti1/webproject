<?php
// /urban/test_auth.php - Script para testar AuthController isoladamente

echo "<h2>UrbanTraffic - Teste do AuthController</h2>";

// Iniciar sessão
session_start();

// Testar require do database
echo "<h3>1. Teste do require database.php...</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "<p style='color: green;'>require_once database.php: <strong>SUCESSO</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>require_once database.php: <strong>FALHOU</strong> - " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Testar conexão
echo "<h3>2. Teste de conexão à base de dados...</h3>";
if (isDatabaseConnected()) {
    echo "<p style='color: green;'>Conexão: <strong>SUCESSO</strong></p>";
} else {
    echo "<p style='color: red;'>Conexão: <strong>FALHOU</strong></p>";
    exit;
}

// Testar se a tabela users existe
echo "<h3>3. Teste da tabela users...</h3>";
try {
    global $conn;
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    $tables = $stmt->fetchAll();
    
    if (!empty($tables)) {
        echo "<p style='color: green;'>Tabela 'users': <strong>EXISTE</strong></p>";
    } else {
        echo "<p style='color: red;'>Tabela 'users': <strong>NÃO EXISTE</strong></p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro ao verificar tabela users: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Testar inserção simples
echo "<h3>4. Teste de inserção na tabela users...</h3>";
try {
    $test_email = 'test_auth_' . time() . '@example.com';
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
    $stmt->execute([$test_email, password_hash('test123', PASSWORD_DEFAULT), 'Test Auth']);
    
    $user_id = $conn->lastInsertId();
    echo "<p style='color: green;'>Inserção: <strong>SUCESSO</strong> (ID: $user_id)</p>";
    
    // Limpar
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Inserção: <strong>FALHOU</strong> - " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Testar o AuthController
echo "<h3>5. Teste do AuthController...</h3>";
try {
    require_once __DIR__ . '/app/controllers/AuthController.php';
    echo "<p style='color: green;'>require_once AuthController.php: <strong>SUCESSO</strong></p>";
    
    // Criar instância
    $auth = new AuthController();
    echo "<p style='color: green;'>Instância AuthController: <strong>SUCESSO</strong></p>";
    
    // Testar método register com dados válidos
    echo "<h4>5.1 Teste do método register...</h4>";
    
    // Simular JSON input
    $test_data = [
        'name' => 'Test User',
        'email' => 'test_register_' . time() . '@example.com',
        'password' => 'test123'
    ];
    
    // Capturar output
    ob_start();
    
    // Simular o ambiente
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'register';
    
    // Simular php://input
    file_put_contents('php://memory', json_encode($test_data));
    
    $auth->register();
    
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    if ($result && $result['status'] === 'success') {
        echo "<p style='color: green;'>Método register: <strong>SUCESSO</strong></p>";
        echo "<small>User ID: " . $result['user']['id'] . "</small>";
        
        // Limpar utilizador de teste
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$result['user']['id']]);
        } catch (Exception $e) {
            echo "<p style='color: orange;'>Aviso: Não foi possível limpar utilizador de teste</p>";
        }
    } else {
        echo "<p style='color: red;'>Método register: <strong>FALHOU</strong></p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>AuthController: <strong>FALHOU</strong> - " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Voltar para a aplicação</a></p>";
?>
