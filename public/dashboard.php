<?php
define('URBAN_SKIP_AUTH_ROUTER', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

header('Content-Type: text/html; charset=UTF-8');

$auth = new AuthController();
$user = $_SESSION['user'] ?? null;

if (!$user) {
    $user = $auth->verifySession(false);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Dashboard</title>
    <script>
        (function() {
            try {
                const darkMode = localStorage.getItem('urban_dark_mode') === 'true';
                const language = localStorage.getItem('urban_language') || 'pt';
                document.documentElement.classList.toggle('dark-mode', darkMode);
                document.documentElement.setAttribute('data-language', language);
            } catch (error) {}
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="ut-page ut-page-dashboard dashboard-page<?php echo $user ? '' : ' ut-dashboard-locked'; ?>">
    <?php include 'partials/navbar.php'; ?>

    <section class="page-header">
        <div class="container">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="ut-badge ut-badge-live mb-3"><i class="fas fa-chart-line"></i> Área pessoal</div>
                    <h1 class="h2 mb-2">Dashboard</h1>
                    <p class="mb-0 text-white-50">
                        Relatórios pessoais de mobilidade com base no teu histórico, favoritos e atividade local disponível.
                    </p>
                </div>
                <?php if ($user): ?>
                    <div class="ut-dashboard-hero-card">
                        <div class="small text-white-50">Sessão ativa</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($user['name'] ?? 'Utilizador'); ?></div>
                        <div class="small text-white-50"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <main class="container my-4 ut-dashboard" id="dashboardApp" data-authenticated="<?php echo $user ? '1' : '0'; ?>">
        <?php if (!$user): ?>
            <div class="ut-panel ut-dashboard-card">
                <div class="ut-empty-state">
                    <i class="fas fa-user-lock"></i>
                    <h3 class="h5 mb-2">Inicie sessão para abrir o seu dashboard</h3>
                    <p class="text-muted mb-4">
                        Aqui vai poder acompanhar o histórico de pesquisas, favoritos, padrões de uso e relatórios pessoais da app.
                    </p>
                    <button class="btn btn-urbano ut-btn ut-btn-primary" type="button" onclick="window.authManager?.showAuthModal()">
                        <i class="fas fa-sign-in-alt"></i>Entrar
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="ut-dashboard-shell">
                <section class="ut-panel ut-dashboard-summary">
                    <div class="ut-dashboard-summary__content">
                        <div class="ut-badge ut-badge-primary mb-3"><i class="fas fa-chart-pie"></i> Resumo pessoal</div>
                        <h2 class="h3 mb-2">Dashboard</h2>
                        <p class="text-muted mb-0">
                            Uma vista rápida da forma como usas o UrbanTraffic, com relatórios reais e listas compactas para navegar sem alongar a página.
                        </p>
                    </div>
                    <div class="ut-dashboard-summary__actions">
                        <div class="ut-dashboard-period-filter" id="reportTabs" aria-label="Filtro de período">
                            <button class="ut-report-tab active" type="button" data-range="today">Hoje</button>
                            <button class="ut-report-tab" type="button" data-range="week">7 dias</button>
                            <button class="ut-report-tab" type="button" data-range="month">30 dias</button>
                        </div>
                        <a class="btn btn-urbano ut-btn ut-btn-primary" href="index.php">
                            <i class="fas fa-route"></i>Planear nova viagem
                        </a>
                    </div>
                </section>

                <section class="ut-panel ut-dashboard-context">
                    <div class="ut-dashboard-context__profile">
                        <span class="ut-dashboard-context__eyebrow">Conta ativa</span>
                        <strong><?php echo htmlspecialchars($user['name'] ?? 'Utilizador'); ?></strong>
                        <span><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                    </div>
                    <div class="ut-dashboard-source">
                        <span class="ut-badge ut-badge-primary"><i class="fas fa-cloud"></i> Histórico da conta</span>
                        <span class="ut-badge ut-badge-live"><i class="fas fa-mobile-screen"></i> Dados locais reais</span>
                    </div>
                    <div class="ut-dashboard-context__note" id="dashboardPeriodSummary">
                        Hoje, semana ou mês: o dashboard adapta gráficos e listas ao período escolhido.
                    </div>
                </section>

                <section class="ut-dashboard-grid ut-dashboard-grid--stats" id="dashboardStats"></section>

                <section class="ut-dashboard-grid ut-dashboard-grid--charts">
                    <article class="ut-panel ut-chart-card ut-dashboard-chart ut-dashboard-chart--wide">
                        <div class="ut-chart-card__header">
                            <div>
                                <h3 class="h6 mb-1">Atividade ao longo do período</h3>
                                <p class="text-muted small mb-0" id="usageTrendMeta">Pesquisas agrupadas ao longo do período selecionado.</p>
                            </div>
                        </div>
                        <div class="ut-chart-stage">
                            <canvas id="usageTrendChart"></canvas>
                            <div class="ut-chart-empty d-none" id="usageTrendEmpty"></div>
                        </div>
                    </article>
                </section>

                <section class="ut-dashboard-grid ut-dashboard-grid--lists">
                    <article class="ut-panel ut-scroll-card" id="dashboardFavoritesSection">
                        <div class="ut-scroll-card__header">
                            <div>
                                <h3 class="h6 mb-1">Rotas favoritas</h3>
                                <p class="text-muted small mb-0" id="favoritesMeta">As rotas guardadas com acesso rápido.</p>
                            </div>
                        </div>
                        <div class="ut-scroll-list" id="favoritesScrollList"></div>
                    </article>

                    <article class="ut-panel ut-scroll-card" id="dashboardHistorySection">
                        <div class="ut-scroll-card__header">
                            <div>
                                <h3 class="h6 mb-1">Últimas pesquisas</h3>
                                <p class="text-muted small mb-0" id="historyMeta">Pesquisas guardadas na tua conta.</p>
                            </div>
                        </div>
                        <div class="ut-scroll-list" id="latestSearchesScrollList"></div>
                    </article>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'partials/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php if ($user): ?>
        <script>
            window.__dashboardBootstrapUser = <?php echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        </script>
    <?php endif; ?>
    <script src="js/preferences.js?v=20260427a"></script>
    <script src="js/auth.js?v=20260427h"></script>
    <?php if ($user): ?>
        <script src="js/dashboard.js?v=20260427d"></script>
    <?php endif; ?>
</body>
</html>
