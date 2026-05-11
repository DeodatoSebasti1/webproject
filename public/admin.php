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

$isAdmin = is_array($user) && (($user['role'] ?? '') === 'admin');

if (!$user) {
    header('Location: index.php');
    exit;
}

if (!$isAdmin) {
    http_response_code(403);
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic Backoffice</title>
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
    <style>
        :root {
            --admin-bg: #eef4f1;
            --admin-surface: rgba(255,255,255,0.92);
            --admin-ink: #10231a;
            --admin-muted: #587061;
            --admin-line: rgba(16,35,26,0.08);
            --admin-shadow: 0 22px 46px rgba(16,35,26,0.08);
            --admin-hero: linear-gradient(135deg, #0f3d28 0%, #1f7a45 55%, #75c96c 100%);
            --admin-primary: #1d8f45;
            --admin-success: #0f9d58;
            --admin-info: #2563eb;
            --admin-warning: #f59e0b;
            --admin-accent: #8b5cf6;
            --admin-neutral: #334155;
        }
        body {
            background:
                radial-gradient(circle at top left, rgba(117,201,108,0.24), transparent 32%),
                linear-gradient(180deg, #f3f8f5 0%, #eef4f1 48%, #e8f0eb 100%);
            color: var(--admin-ink);
            font-family: 'Inter', sans-serif;
        }
        .admin-shell {
            display: grid;
            gap: 1.5rem;
        }
        .admin-shell.is-loading {
            opacity: .72;
            pointer-events: none;
        }
        .admin-hero {
            background: var(--admin-hero);
            border-radius: 30px;
            color: #fff;
            padding: 2rem;
            box-shadow: 0 24px 64px rgba(15,61,40,.28);
            position: relative;
            overflow: hidden;
        }
        .admin-hero::after {
            content: '';
            position: absolute;
            inset: auto -8% -18% auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.18), transparent 68%);
        }
        .admin-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .18em;
            color: rgba(255,255,255,.74);
        }
        .admin-hero h1 {
            font-size: clamp(2rem, 3vw, 3rem);
            font-weight: 800;
            margin: .8rem 0 .7rem;
        }
        .admin-hero p {
            max-width: 720px;
            color: rgba(255,255,255,.78);
        }
        .admin-hero__meta {
            display: grid;
            gap: .9rem;
            justify-items: end;
            text-align: right;
        }
        .admin-pill {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 999px;
            color: #fff;
            padding: .68rem 1rem;
            font-size: .92rem;
            backdrop-filter: blur(12px);
        }
        .admin-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--admin-surface);
            border: 1px solid var(--admin-line);
            border-radius: 24px;
            padding: 1rem 1.1rem;
            box-shadow: var(--admin-shadow);
        }
        .admin-range-switcher {
            display: flex;
            gap: .65rem;
            flex-wrap: wrap;
        }
        .admin-toolbar__actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            align-items: center;
            justify-content: flex-end;
        }
        .admin-range-btn {
            border: 0;
            background: #e8efe9;
            color: var(--admin-muted);
            padding: .72rem 1rem;
            border-radius: 999px;
            font-weight: 700;
            transition: .2s ease;
        }
        .admin-range-btn.active,
        .admin-range-btn:hover {
            background: var(--admin-primary);
            color: #fff;
            transform: translateY(-1px);
        }
        .admin-action-btn {
            border: 0;
            border-radius: 999px;
            padding: .72rem 1rem;
            font-weight: 700;
            background: #10231a;
            color: #fff;
            transition: .2s ease;
        }
        .admin-action-btn:hover {
            transform: translateY(-1px);
            background: #163425;
        }
        .admin-action-btn--soft {
            background: #e8efe9;
            color: var(--admin-ink);
        }
        .admin-action-btn--danger {
            background: #b91c1c;
            color: #fff;
        }
        .admin-section-card {
            background: var(--admin-surface);
            border: 1px solid var(--admin-line);
            border-radius: 24px;
            box-shadow: var(--admin-shadow);
            padding: 1.35rem;
        }
        .admin-section-card h2,
        .admin-section-card h3 {
            margin: 0;
            font-weight: 800;
        }
        .admin-section-card__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .admin-section-card__header p {
            margin: .35rem 0 0;
            color: var(--admin-muted);
        }
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-stat-card {
            border-radius: 22px;
            padding: 1.2rem;
            min-height: 138px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .admin-stat-card::after {
            content: '';
            position: absolute;
            right: -22px;
            bottom: -32px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255,255,255,.12);
        }
        .admin-tone-primary { background: linear-gradient(135deg, #0f3d28, #1d8f45); }
        .admin-tone-success { background: linear-gradient(135deg, #0b6b57, #13a388); }
        .admin-tone-info { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
        .admin-tone-accent { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
        .admin-tone-warning { background: linear-gradient(135deg, #b45309, #f59e0b); }
        .admin-tone-neutral { background: linear-gradient(135deg, #1e293b, #475569); }
        .admin-stat-card__top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .admin-stat-card__label {
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: rgba(255,255,255,.7);
        }
        .admin-stat-card__icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.14);
            font-size: 1rem;
        }
        .admin-stat-card__value {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.05;
            white-space: nowrap;
        }
        .admin-stat-card__meta {
            margin-top: .45rem;
            color: rgba(255,255,255,.8);
            max-width: none;
            font-size: .84rem;
        }
        .admin-highlights-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-highlight-card {
            padding: 1rem;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff, #f8fbf9);
            border: 1px solid rgba(16,35,26,0.07);
            min-width: 0;
        }
        .admin-highlight-card__title {
            color: var(--admin-muted);
            font-size: .8rem;
            margin-bottom: .45rem;
        }
        .admin-highlight-card__value {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: .35rem;
            line-height: 1.25;
            word-break: break-word;
        }
        .admin-highlight-card__detail {
            color: #425a4c;
            font-size: .82rem;
            line-height: 1.35;
        }
        .admin-grid-2 {
            display: grid;
            grid-template-columns: 1.4fr .9fr;
            gap: 1.5rem;
        }
        .admin-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.5rem;
        }
        .admin-user-search-grid {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 1.5rem;
        }
        .admin-chart-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }
        .admin-chart-grid .admin-section-card--wide {
            grid-column: span 2;
        }
        .admin-chart-wrap {
            position: relative;
            min-height: 300px;
        }
        .admin-chart-wrap--compact {
            min-height: 210px;
        }
        .admin-insight-stack {
            display: grid;
            gap: 1rem;
        }
        .admin-health-scroll {
            max-height: 220px;
            overflow: auto;
            padding-right: .35rem;
        }
        .admin-chart-scroll {
            max-height: 250px;
            overflow: auto;
            padding-right: .35rem;
        }
        .admin-search-form {
            display: flex;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .admin-search-input {
            flex: 1;
            border: 1px solid rgba(16,35,26,0.12);
            border-radius: 16px;
            padding: .88rem 1rem;
            background: rgba(255,255,255,.95);
            color: var(--admin-ink);
        }
        .admin-search-results {
            display: grid;
            gap: .75rem;
            max-height: 360px;
            overflow: auto;
            padding-right: .25rem;
        }
        .admin-user-result {
            border: 1px solid rgba(16,35,26,0.08);
            border-radius: 18px;
            padding: 1rem;
            background: linear-gradient(180deg, #ffffff, #f8fbf9);
            cursor: pointer;
            transition: .2s ease;
        }
        .admin-user-result:hover,
        .admin-user-result.is-active {
            border-color: rgba(29,143,69,0.35);
            box-shadow: 0 14px 32px rgba(16,35,26,0.08);
            transform: translateY(-1px);
        }
        .admin-user-result__top {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .5rem;
        }
        .admin-user-result__meta {
            color: var(--admin-muted);
            font-size: .88rem;
        }
        .admin-user-detail {
            border: 1px solid rgba(16,35,26,0.08);
            border-radius: 20px;
            padding: 1.1rem;
            background: linear-gradient(180deg, #ffffff, #f8fbf9);
        }
        .admin-user-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .85rem;
            margin-top: 1rem;
        }
        .admin-user-detail-stat {
            border: 1px solid rgba(16,35,26,0.06);
            border-radius: 16px;
            padding: .9rem;
            background: rgba(255,255,255,.75);
        }
        .admin-user-detail-stat__label {
            color: var(--admin-muted);
            font-size: .82rem;
            margin-bottom: .25rem;
        }
        .admin-user-detail-stat__value {
            font-size: 1.08rem;
            font-weight: 800;
        }
        .admin-mini-list {
            margin-top: 1rem;
            display: grid;
            gap: .65rem;
        }
        .admin-mini-item {
            padding: .8rem .9rem;
            border-radius: 14px;
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(16,35,26,0.05);
        }
        .admin-health-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .72rem 0;
            border-bottom: 1px solid var(--admin-line);
        }
        .admin-health-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .admin-health-row__meta {
            color: var(--admin-muted);
            font-size: .88rem;
            margin-top: .2rem;
        }
        .admin-health-row__value {
            font-weight: 800;
            color: var(--admin-primary);
        }
        .admin-table-wrapper {
            overflow: auto;
        }
        .admin-table-wrapper--scroll {
            max-height: 320px;
            overflow: auto;
            border-radius: 18px;
        }
        .admin-table-wrapper--scroll thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: rgba(248, 251, 249, 0.96);
            backdrop-filter: blur(6px);
        }
        .admin-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .admin-table th {
            color: var(--admin-muted);
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            padding: .9rem .8rem;
            border-bottom: 1px solid var(--admin-line);
            white-space: nowrap;
        }
        .admin-table td {
            padding: 1rem .8rem;
            border-bottom: 1px solid rgba(16,35,26,0.05);
            vertical-align: top;
        }
        .admin-table tbody tr:last-child td {
            border-bottom: 0;
        }
        .admin-table-strong {
            font-weight: 700;
            color: var(--admin-ink);
        }
        .admin-table-muted,
        .admin-empty-row {
            color: var(--admin-muted);
            font-size: .9rem;
        }
        .admin-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 88px;
            border-radius: 999px;
            padding: .42rem .72rem;
            font-size: .82rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .admin-badge--success { background: rgba(29,143,69,.12); color: #126733; }
        .admin-badge--neutral,
        .admin-badge--info { background: rgba(37,99,235,.12); color: #1d4ed8; }
        .admin-badge--warning { background: rgba(245,158,11,.14); color: #a16207; }
        .admin-badge--error { background: rgba(239,68,68,.14); color: #b91c1c; }
        .admin-badge--accent { background: rgba(139,92,246,.14); color: #6d28d9; }
        .admin-restricted {
            max-width: 760px;
            margin: 0 auto;
        }
        .admin-restricted .alert {
            border-radius: 18px;
        }
        @media (max-width: 991px) {
            .admin-hero__meta {
                justify-items: start;
                text-align: left;
            }
            .admin-stats-grid,
            .admin-highlights-grid,
            .admin-user-search-grid,
            .admin-grid-3,
            .admin-grid-2,
            .admin-chart-grid {
                grid-template-columns: 1fr;
            }
            .admin-chart-grid .admin-section-card--wide {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4 py-lg-5">
    <section class="admin-hero mb-4">
        <div class="row g-4 align-items-end">
            <div class="col-lg-8">
                <div class="admin-eyebrow"><i class="fas fa-shield-halved"></i> Backoffice / Controlo Operacional</div>
                <h1 data-i18n="adminTitle">Monitorização do UrbanTraffic</h1>
                <p data-i18n="adminSubtitle">Utilizadores, pesquisas, favoritos, cache, eventos e visão operacional consolidada num painel executivo.</p>
            </div>
            <div class="col-lg-4">
                <div class="admin-hero__meta">
                    <div class="admin-pill"><i class="fas fa-bolt"></i><span id="adminGeneratedAt">A preparar dados...</span></div>
                    <div class="admin-pill"><i class="fas fa-user-shield"></i><span id="adminWelcome"><?= htmlspecialchars(($user['name'] ?? 'Administrador') . ' · ' . ($user['email'] ?? 'visitante')); ?></span></div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$isAdmin): ?>
        <div class="admin-section-card admin-restricted">
            <div class="card-body p-1">
                <h2 class="h4 mb-3"><i class="fas fa-lock me-2 text-success"></i>Acesso restrito</h2>
                <p class="mb-2">Esta área é exclusiva para utilizadores com perfil <code>admin</code>.</p>
                <p class="mb-3 text-muted">O dashboard pessoal mantém-se em <code>dashboard.php</code>. O backoffice administrativo fica separado em <code>admin.php</code>.</p>
                <div class="alert alert-light border mb-0">
                    <div class="fw-semibold mb-2">Guia rápido para ambiente local</div>
                    <ol class="mb-0 ps-3">
                        <li>Criar conta normal no site.</li>
                        <li>Executar <code>UPDATE users SET role = 'admin' WHERE email = 'teuemail@exemplo.com';</code></li>
                        <li>Fazer logout e login novamente.</li>
                        <li>Reabrir <code>/urban/public/admin.php</code>.</li>
                    </ol>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-shell" id="adminShell">
            <section class="admin-toolbar">
                <div>
                    <h2 class="h5 mb-1">Centro de comando administrativo</h2>
                    <p class="mb-0 text-muted" id="adminPeriodSummary">Análise consolidada da operação em curso.</p>
                </div>
                <div class="admin-range-switcher">
                    <button class="admin-range-btn" type="button" data-admin-range="7">7 dias</button>
                    <button class="admin-range-btn active" type="button" data-admin-range="30">30 dias</button>
                    <button class="admin-range-btn" type="button" data-admin-range="90">90 dias</button>
                </div>
                <div class="admin-toolbar__actions">
                    <button class="admin-action-btn admin-action-btn--soft" type="button" id="adminExportCsvBtn">
                        <i class="fas fa-file-csv me-2"></i>Exportar CSV
                    </button>
                    <button class="admin-action-btn admin-action-btn--danger" type="button" id="adminClearCacheBtn">
                        <i class="fas fa-broom-ball me-2"></i>Limpar cache
                    </button>
                </div>
            </section>

            <section class="admin-stats-grid" id="adminStatsGrid"></section>

            <section class="admin-section-card">
                <div class="admin-section-card__header">
                    <div>
                        <h2 class="h5">Destaques executivos</h2>
                        <p>Leituras rápidas para decidir, validar performance e acompanhar comportamento da plataforma.</p>
                    </div>
                </div>
                <div class="admin-highlights-grid" id="adminHighlightsGrid"></div>
            </section>

            <section class="admin-grid-2">
                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Tendência de pesquisas</h3>
                            <p>Evolução diária da procura na plataforma para o período selecionado.</p>
                        </div>
                    </div>
                    <div class="admin-chart-wrap">
                        <canvas id="searchesChart"></canvas>
                    </div>
                </article>

                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Saúde da base de dados</h3>
                            <p>Disponibilidade e volume das principais tabelas analíticas.</p>
                        </div>
                    </div>
                    <div class="admin-health-scroll" id="adminTableHealth"></div>
                </article>
            </section>

            <section class="admin-grid-3">
                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Registos de utilizadores</h3>
                            <p>Cadência de crescimento da base de contas.</p>
                        </div>
                    </div>
                    <div class="admin-chart-wrap admin-chart-wrap--compact">
                        <canvas id="usersChart"></canvas>
                    </div>
                </article>

                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Distribuição de favoritos</h3>
                            <p>Rotas que concentram mais intenções de retorno.</p>
                        </div>
                    </div>
                    <div class="admin-chart-wrap admin-chart-wrap--compact">
                        <canvas id="favoritesChart"></canvas>
                    </div>
                </article>

                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Rotas mais relevantes</h3>
                            <p>Popularidade agregada por rota em favoritos e eventos.</p>
                        </div>
                    </div>
                    <div class="admin-chart-wrap admin-chart-wrap--compact">
                        <canvas id="routesChart"></canvas>
                    </div>
                </article>
            </section>

            <section class="admin-user-search-grid">
                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Procurar utilizadores</h3>
                            <p>Pesquisa por nome ou email e abre detalhes básicos sem ações destrutivas.</p>
                        </div>
                    </div>
                    <form class="admin-search-form" id="adminUserSearchForm">
                        <input class="admin-search-input" type="search" id="adminUserSearchInput" placeholder="Ex.: maria@exemplo.com ou Maria">
                        <button class="admin-action-btn" type="submit">Procurar</button>
                    </form>
                    <div class="admin-search-results" id="adminUserSearchResults">
                        <div class="admin-empty-row">Procura um utilizador para ver resultados.</div>
                    </div>
                </article>

                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Detalhe do utilizador</h3>
                            <p>Perfil, atividade recente, favoritos e sessões ativas.</p>
                        </div>
                    </div>
                    <div class="admin-user-detail" id="adminUserDetail">
                        <div class="admin-empty-row">Seleciona um utilizador nos resultados da pesquisa.</div>
                    </div>
                </article>
            </section>

            <section class="admin-grid-2">
                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Mix de eventos</h3>
                            <p>Tipos de acontecimentos registados pelo sistema.</p>
                        </div>
                    </div>
                    <div class="admin-chart-wrap admin-chart-wrap--compact">
                        <canvas id="eventsChart"></canvas>
                    </div>
                </article>

                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Horas de maior utilização</h3>
                            <p>Concentração de pesquisas por hora do dia.</p>
                        </div>
                    </div>
                    <div class="admin-chart-scroll">
                        <div class="admin-chart-wrap admin-chart-wrap--compact">
                            <canvas id="peakHoursChart"></canvas>
                        </div>
                    </div>
                </article>
            </section>

            <section class="admin-section-card">
                <div class="admin-section-card__header">
                    <div>
                        <h3 class="h6">Pares origem-destino dominantes</h3>
                        <p>Os percursos com maior procura efetiva no período selecionado.</p>
                    </div>
                </div>
                <div class="admin-chart-scroll">
                    <div class="admin-chart-wrap admin-chart-wrap--compact">
                        <canvas id="pairsChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="admin-grid-2">
                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Últimos utilizadores</h3>
                            <p>Visão rápida de novos registos, papéis e atividade recente, com scroll interno para manter a página compacta.</p>
                        </div>
                    </div>
                    <div class="admin-table-wrapper admin-table-wrapper--scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Utilizador</th>
                                    <th>Email</th>
                                    <th>Perfil</th>
                                    <th>Criação</th>
                                </tr>
                            </thead>
                            <tbody id="recentUsersTable">
                                <tr><td colspan="4" class="admin-empty-row">A carregar...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="admin-section-card">
                    <div class="admin-section-card__header">
                        <div>
                            <h3 class="h6">Últimas pesquisas</h3>
                            <p>Pedidos recentes para monitorizar procura e comportamento sem alongar o backoffice.</p>
                        </div>
                    </div>
                    <div class="admin-table-wrapper admin-table-wrapper--scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody id="recentSearchesTable">
                                <tr><td colspan="3" class="admin-empty-row">A carregar...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="admin-section-card">
                <div class="admin-section-card__header">
                    <div>
                        <h3 class="h6">Linha temporal de eventos</h3>
                        <p>Registos recentes de login, pesquisas, favoritos e indicadores operacionais com leitura compacta.</p>
                    </div>
                </div>
                <div class="admin-table-wrapper admin-table-wrapper--scroll">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Severidade</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody id="recentEventsTable">
                            <tr><td colspan="3" class="admin-empty-row">A carregar...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/preferences.js?v=20260427a"></script>
<script src="js/auth.js?v=20260427h"></script>
<?php if ($isAdmin): ?>
<script src="js/admin.js?v=20260428b"></script>
<?php endif; ?>
</body>
</html>
