<?php
// /urban/test_route.php - Script para diagnosticar erro 500 no RouteController

// Mostrar todos os erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Teste do RouteController</h2>";

// Testar 1: Requires
echo "<h3>1. Testando requires...</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "✅ database.php carregado<br>";
} catch (Exception $e) {
    echo "❌ Erro no database.php: " . $e->getMessage() . "<br>";
}

try {
    require_once __DIR__ . '/app/services/GtfsRouteService.php';
    echo "✅ GtfsRouteService.php carregado<br>";
} catch (Exception $e) {
    echo "❌ Erro no GtfsRouteService.php: " . $e->getMessage() . "<br>";
}

try {
    require_once __DIR__ . '/app/services/RouteCacheService.php';
    echo "✅ RouteCacheService.php carregado<br>";
} catch (Exception $e) {
    echo "❌ Erro no RouteCacheService.php: " . $e->getMessage() . "<br>";
}

// Testar 2: Conexão à base de dados
echo "<h3>2. Testando conexão...</h3>";
if (isset($conn) && $conn instanceof PDO) {
    echo "✅ Conexão PDO ativa<br>";
    
    try {
        $stmt = $conn->query("SELECT 1");
        echo "✅ Query de teste funcionou<br>";
    } catch (Exception $e) {
        echo "❌ Erro na query: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Conexão PDO NÃO está ativa<br>";
}

// Testar 3: Parâmetros GET
echo "<h3>3. Testando com parâmetros...</h3>";
$_GET['fromLat'] = '38.7223';
$_GET['fromLon'] = '-9.1393';
$_GET['toLat'] = '38.7678';
$_GET['toLon'] = '-9.0997';

echo "Parâmetros GET definidos:<br>";
echo "fromLat: " . $_GET['fromLat'] . "<br>";
echo "fromLon: " . $_GET['fromLon'] . "<br>";
echo "toLat: " . $_GET['toLat'] . "<br>";
echo "toLon: " . $_GET['toLon'] . "<br>";

// Testar 4: Tentar executar RouteController
echo "<h3>4. Testando RouteController...</h3>";
echo "A incluir RouteController...<br>";

ob_start();
try {
    require_once __DIR__ . '/app/controllers/RouteController.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "⚠️ Output antes do JSON: <pre>" . htmlspecialchars(substr($output, 0, 500)) . "</pre><br>";
    } else {
        echo "✅ RouteController carregado sem output<br>";
    }
} catch (Exception $e) {
    ob_get_clean();
    echo "❌ Erro no RouteController: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    ob_get_clean();
    echo "❌ Erro fatal no RouteController: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p>Se o teste parou antes do fim, há um erro fatal.</p>";
echo "<p><a href='index.php'>Voltar para a aplicação</a></p>";
?>
