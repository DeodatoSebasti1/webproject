<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$navUser = $_SESSION['user'] ?? null;

if (!is_array($navUser) && !empty($_COOKIE['urban_auth_token'])) {
    require_once __DIR__ . '/../../config/database.php';

    if (isset($conn) && $conn instanceof PDO) {
        try {
            $stmt = $conn->prepare("
                SELECT u.id, u.email, u.name, u.role
                FROM user_sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([trim((string)$_COOKIE['urban_auth_token'])]);
            $navUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (is_array($navUser)) {
                $_SESSION['user'] = [
                    'id' => (int)$navUser['id'],
                    'email' => (string)$navUser['email'],
                    'name' => (string)$navUser['name'],
                    'role' => (string)($navUser['role'] ?? 'user')
                ];
            }
        } catch (Throwable $error) {
            $navUser = null;
        }
    }
}

$dashboardHref = (is_array($navUser) && (($navUser['role'] ?? 'user') === 'admin')) ? 'admin.php' : 'dashboard.php';
$dashboardActive = in_array($currentPage, ['dashboard.php', 'admin.php'], true) ? 'active' : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark ut-navbar sticky-top">
    <div class="container">

        <a class="navbar-brand ut-brand" href="index.php" aria-label="UrbanTraffic">
            <img src="img/logo.png" alt="UrbanTraffic logo">
            <span class="ut-brand__text">
                <span class="ut-brand__eyebrow">Mobilidade Urbana</span>
                <span class="ut-brand__wordmark">
                    <span class="fw-bold" style="color: var(--verde-urbano);">Urban</span>
                    <span class="fw-bold text-white">Traffic</span>
                </span>
            </span>
        </a>

        <button class="navbar-toggler ut-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Abrir navegação">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">

                <li class="nav-item">
                    <a class="nav-link ut-nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="index.php" data-i18n="navDirections">Direções</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link ut-nav-link <?php echo $currentPage === 'lines.php' ? 'active' : ''; ?>" href="lines.php" data-i18n="navLines">Linhas</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link ut-nav-link <?php echo $currentPage === 'configuracoes.php' ? 'active' : ''; ?>" href="configuracoes.php" data-i18n="navSettings">Configurações</a>
                </li>

                <li class="nav-item" id="dashboardNavItem" style="display: none;">
                    <a class="nav-link ut-nav-link <?php echo $dashboardActive; ?>" href="<?php echo htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8'); ?>" data-i18n="navDashboard" data-dashboard-link="main">Dashboard</a>
                </li>

                <!-- Botões de autenticação -->
                <li class="nav-item" id="authNavSection">
                    <div class="ut-auth-actions">
                        <button class="btn ut-btn ut-btn-secondary ut-btn-sm" id="loginBtn" onclick="authManager.showAuthModal()">
                            <i class="fas fa-sign-in-alt"></i><span data-i18n="navLogin">Login</span>
                        </button>
                    </div>
                </li>

                <!-- Informações do utilizador autenticado -->
                <li class="nav-item dropdown" id="userNavSection" style="display: none;">
                    <a class="nav-link ut-nav-link dropdown-toggle ut-user-menu__trigger" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <span id="userName">Utilizador</span>
                    </a>
                    <ul class="dropdown-menu ut-dropdown-menu dropdown-menu-end ut-user-menu">
                        <li><h6 class="dropdown-header" id="userEmail">email@example.com</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8'); ?>" data-dashboard-link="menu">
                            <i class="fas fa-chart-pie me-2"></i> <span data-i18n="navDashboard">Dashboard</span>
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="authManager.showFavorites()">
                            <i class="fas fa-heart me-2"></i> <span data-i18n="navFavorites">Favoritos</span>
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="authManager.showHistory()">
                            <i class="fas fa-history me-2"></i> <span data-i18n="navHistory">Histórico</span>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" id="logoutBtn">
                            <i class="fas fa-sign-out-alt me-2"></i> <span data-i18n="navLogout">Logout</span>
                        </a></li>
                    </ul>
                </li>

            </ul>
        </div>

    </div>
</nav>
