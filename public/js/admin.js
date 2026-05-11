(() => {
    const charts = {};
    let currentDays = 30;
    let activeUserResultId = null;

    function getRequestHeaders(extraHeaders = {}) {
        const liveToken = window.authManager?.getToken?.();
        return {
            ...(liveToken ? { Authorization: `Bearer ${liveToken}` } : {}),
            ...extraHeaders
        };
    }

    async function fetchAdminDashboard(days) {
        const response = await fetch(`/urban/public/api/admin?action=dashboard&days=${days}`, {
            headers: getRequestHeaders()
        });
        const payload = await response.json();
        if (!response.ok || payload.status !== 'success') {
            throw new Error(payload.message || 'Não foi possível carregar o backoffice.');
        }
        return payload.data;
    }

    async function fetchAdminJson(action, options = {}) {
        const response = await fetch(`/urban/public/api/admin?action=${action}`, {
            ...options,
            headers: getRequestHeaders(options.headers || {})
        });
        const payload = await response.json();
        if (!response.ok || payload.status !== 'success') {
            throw new Error(payload.message || 'Não foi possível executar a ação administrativa.');
        }
        return payload.data;
    }

    function formatNumber(value, digits = 0) {
        return Number(value || 0).toLocaleString('pt-PT', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    function formatDateTime(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('pt-PT');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    function renderHeader(data) {
        setText('adminGeneratedAt', `Atualizado em ${formatDateTime(data.meta?.generated_at)}`);
        setText('adminPeriodSummary', `Análise dos últimos ${data.meta?.days || currentDays} dias com foco em crescimento, utilização e saúde operacional.`);
        setText('adminWelcome', `${data.meta?.admin_name || 'Administrador'} · ${data.meta?.admin_email || ''}`);
    }

    function renderStats(stats) {
        const cards = [
            {
                id: 'statUsers',
                label: 'Utilizadores',
                value: formatNumber(stats.users_total),
                meta: `${formatNumber(stats.users_period)} novos no período`,
                tone: 'primary',
                icon: 'fa-users'
            },
            {
                id: 'statSessions',
                label: 'Sessões ativas',
                value: formatNumber(stats.active_sessions),
                meta: `${formatNumber(stats.active_users)} utilizadores ativos · ${formatNumber(stats.session_coverage, 1)}% da base`,
                tone: 'success',
                icon: 'fa-user-check'
            },
            {
                id: 'statSearches',
                label: 'Pesquisas',
                value: formatNumber(stats.searches_total),
                meta: `${formatNumber(stats.searches_period)} no período`,
                tone: 'info',
                icon: 'fa-magnifying-glass-chart'
            },
            {
                id: 'statFavorites',
                label: 'Favoritos',
                value: formatNumber(stats.favorites_total),
                meta: `${formatNumber(stats.favorites_period)} novos favoritos`,
                tone: 'accent',
                icon: 'fa-heart-circle-check'
            },
            {
                id: 'statCache',
                label: 'Cache de rotas',
                value: formatNumber(stats.route_cache_total),
                meta: `${formatNumber(stats.route_cache_fresh)} atualizados nas últimas 24h`,
                tone: 'warning',
                icon: 'fa-database'
            },
            {
                id: 'statEvents',
                label: 'Eventos',
                value: formatNumber(stats.events_total),
                meta: `${formatNumber(stats.events_period)} eventos recentes`,
                tone: 'neutral',
                icon: 'fa-wave-square'
            }
        ];

        const container = document.getElementById('adminStatsGrid');
        if (!container) return;

        container.innerHTML = cards.map((card) => `
            <article class="admin-stat-card admin-tone-${card.tone}" id="${card.id}">
                <div class="admin-stat-card__top">
                    <span class="admin-stat-card__label">${card.label}</span>
                    <span class="admin-stat-card__icon"><i class="fas ${card.icon}"></i></span>
                </div>
                <div class="admin-stat-card__value">${card.value}</div>
                <div class="admin-stat-card__meta">${card.meta}</div>
            </article>
        `).join('');
    }

    function renderHighlights(stats, highlights, health) {
        const entries = [
            {
                title: 'Rota mais procurada',
                value: highlights.top_route?.route_label || 'Sem dados',
                detail: `${formatNumber(highlights.top_route?.total)} ocorrências agregadas`
            },
            {
                title: 'Par origem-destino líder',
                value: highlights.top_pair?.label || 'Sem dados',
                detail: `${formatNumber(highlights.top_pair?.total)} pesquisas`
            },
            {
                title: 'Hora de pico',
                value: highlights.peak_hour?.label || 'Sem dados',
                detail: `${formatNumber(highlights.peak_hour?.total)} pesquisas nesse período`
            },
            {
                title: 'Evento dominante',
                value: highlights.top_event?.label || 'Sem dados',
                detail: `${formatNumber(highlights.top_event?.total)} registos ${highlights.top_event?.severity ? `(${highlights.top_event.severity})` : ''}`
            },
            {
                title: 'Administradores',
                value: formatNumber(stats.admin_total),
                detail: `${formatNumber(stats.logged_users_period)} utilizadores com login recente`
            },
            {
                title: 'Saúde do cache',
                value: health.cache?.available ? 'Operacional' : 'Indisponível',
                detail: health.cache?.available
                    ? `${formatNumber(health.cache.entries)} entradas · última atualização ${formatDateTime(health.cache.latest_update)}`
                    : 'Tabela de cache não encontrada'
            }
        ];

        const container = document.getElementById('adminHighlightsGrid');
        if (!container) return;

        container.innerHTML = entries.map((entry) => `
            <article class="admin-highlight-card">
                <div class="admin-highlight-card__title">${escapeHtml(entry.title)}</div>
                <div class="admin-highlight-card__value">${escapeHtml(entry.value)}</div>
                <div class="admin-highlight-card__detail">${escapeHtml(entry.detail)}</div>
            </article>
        `).join('');
    }

    function renderTableHealth(health) {
        const container = document.getElementById('adminTableHealth');
        if (!container) return;

        container.innerHTML = (health.tables || []).map((row) => `
            <div class="admin-health-row">
                <div>
                    <strong>${escapeHtml(row.table)}</strong>
                    <div class="admin-health-row__meta">${row.available ? 'Disponível' : 'Indisponível'}</div>
                </div>
                <div class="admin-health-row__value">${row.available ? formatNumber(row.rows) : '—'}</div>
            </div>
        `).join('');
    }

    function destroyChart(key) {
        if (charts[key]) {
            charts[key].destroy();
            delete charts[key];
        }
    }

    function baseChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#1f2937',
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.92)',
                    titleColor: '#ffffff',
                    bodyColor: '#e5eef6',
                    padding: 12,
                    cornerRadius: 12
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.2)'
                    },
                    ticks: {
                        color: '#475569',
                        precision: 0
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#475569'
                    }
                }
            }
        };
    }

    function renderChart(key, canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        destroyChart(key);
        charts[key] = new Chart(canvas, config);
    }

    function renderCharts(chartsData) {
        renderChart('searchesTrend', 'searchesChart', {
            type: 'line',
            data: {
                labels: (chartsData.searches_by_day || []).map((row) => row.label),
                datasets: [{
                    label: 'Pesquisas',
                    data: (chartsData.searches_by_day || []).map((row) => Number(row.total || 0)),
                    borderColor: '#1d8f45',
                    backgroundColor: 'rgba(29, 143, 69, 0.14)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: baseChartOptions()
        });

        renderChart('usersTrend', 'usersChart', {
            type: 'bar',
            data: {
                labels: (chartsData.users_by_day || []).map((row) => row.label),
                datasets: [{
                    label: 'Novos utilizadores',
                    data: (chartsData.users_by_day || []).map((row) => Number(row.total || 0)),
                    backgroundColor: '#2563eb',
                    borderRadius: 12,
                    maxBarThickness: 28
                }]
            },
            options: baseChartOptions()
        });

        renderChart('favoritesMix', 'favoritesChart', {
            type: 'doughnut',
            data: {
                labels: (chartsData.favorites_by_route || []).map((row) => row.label),
                datasets: [{
                    data: (chartsData.favorites_by_route || []).map((row) => Number(row.total || 0)),
                    backgroundColor: ['#14532d', '#1d8f45', '#2fb25e', '#73c991', '#b5e2c3', '#0f766e', '#14b8a6', '#7dd3c7'],
                    borderWidth: 0
                }]
            },
            options: {
                ...baseChartOptions(),
                cutout: '68%',
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });

        renderChart('routesBar', 'routesChart', {
            type: 'bar',
            data: {
                labels: (chartsData.popular_routes || []).map((row) => row.route_label || row.label),
                datasets: [{
                    label: 'Popularidade',
                    data: (chartsData.popular_routes || []).map((row) => Number(row.total || 0)),
                    backgroundColor: '#8b5cf6',
                    borderRadius: 12,
                    maxBarThickness: 26
                }]
            },
            options: {
                ...baseChartOptions(),
                indexAxis: 'y'
            }
        });

        renderChart('eventsMix', 'eventsChart', {
            type: 'polarArea',
            data: {
                labels: (chartsData.event_mix || []).map((row) => row.label),
                datasets: [{
                    data: (chartsData.event_mix || []).map((row) => Number(row.total || 0)),
                    backgroundColor: ['rgba(239, 68, 68, 0.78)', 'rgba(59, 130, 246, 0.78)', 'rgba(34, 197, 94, 0.78)', 'rgba(249, 115, 22, 0.78)', 'rgba(168, 85, 247, 0.78)', 'rgba(6, 182, 212, 0.78)'],
                    borderWidth: 0
                }]
            },
            options: {
                ...baseChartOptions(),
                scales: {
                    r: {
                        grid: { color: 'rgba(148, 163, 184, 0.18)' },
                        ticks: { display: false }
                    }
                }
            }
        });

        renderChart('peakHours', 'peakHoursChart', {
            type: 'bar',
            data: {
                labels: (chartsData.peak_hours || []).map((row) => row.label),
                datasets: [{
                    label: 'Pesquisas',
                    data: (chartsData.peak_hours || []).map((row) => Number(row.total || 0)),
                    backgroundColor: '#f59e0b',
                    borderRadius: 12,
                    maxBarThickness: 28
                }]
            },
            options: baseChartOptions()
        });

        renderChart('searchPairs', 'pairsChart', {
            type: 'bar',
            data: {
                labels: (chartsData.search_pairs || []).map((row) => row.label),
                datasets: [{
                    label: 'Pesquisas',
                    data: (chartsData.search_pairs || []).map((row) => Number(row.total || 0)),
                    backgroundColor: '#0f766e',
                    borderRadius: 12,
                    maxBarThickness: 24
                }]
            },
            options: {
                ...baseChartOptions(),
                indexAxis: 'y'
            }
        });
    }

    function renderTable(id, rows, mapper, emptyCols) {
        const tbody = document.getElementById(id);
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${emptyCols}" class="admin-empty-row">Sem dados disponíveis.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(mapper).join('');
    }

    function renderTables(tables) {
        renderTable('recentUsersTable', tables.recent_users, (row) => `
            <tr>
                <td>
                    <div class="admin-table-strong">${escapeHtml(row.name || '—')}</div>
                    <div class="admin-table-muted">Último login: ${formatDateTime(row.last_login)}</div>
                </td>
                <td>${escapeHtml(row.email || '—')}</td>
                <td><span class="admin-badge ${((row.role || 'user') === 'admin') ? 'admin-badge--success' : 'admin-badge--neutral'}">${escapeHtml(row.role || 'user')}</span></td>
                <td>${formatDateTime(row.created_at)}</td>
            </tr>
        `, 4);

        renderTable('recentSearchesTable', tables.recent_searches, (row) => `
            <tr>
                <td>
                    <div class="admin-table-strong">${escapeHtml(row.origin_name || '—')}</div>
                    <div class="admin-table-muted">${escapeHtml(row.user_id ? `Utilizador #${row.user_id}` : 'Sessão anónima')}</div>
                </td>
                <td>${escapeHtml(row.destination_name || '—')}</td>
                <td>${formatDateTime(row.searched_at)}</td>
            </tr>
        `, 3);

        renderTable('recentEventsTable', tables.recent_events, (row) => {
            const summary = row.payload_route || [row.payload_origin, row.payload_destination].filter(Boolean).join(' → ') || row.payload_email || row.entity_id || 'Sem detalhe';
            return `
                <tr>
                    <td>
                        <div class="admin-table-strong">${escapeHtml(row.event_type || '—')}</div>
                        <div class="admin-table-muted">${escapeHtml(summary)}</div>
                    </td>
                    <td><span class="admin-badge admin-badge--${escapeHtml(row.severity || 'neutral')}">${escapeHtml(row.severity || 'info')}</span></td>
                    <td>${formatDateTime(row.created_at)}</td>
                </tr>
            `;
        }, 3);
    }

    function updateRangeButtons(days) {
        document.querySelectorAll('[data-admin-range]').forEach((button) => {
            button.classList.toggle('active', Number(button.dataset.adminRange) === Number(days));
        });
    }

    function setLoadingState(isLoading) {
        const shell = document.getElementById('adminShell');
        if (shell) {
            shell.classList.toggle('is-loading', !!isLoading);
        }
    }

    function renderUserSearchResults(rows) {
        const container = document.getElementById('adminUserSearchResults');
        if (!container) return;

        if (!rows || rows.length === 0) {
            container.innerHTML = '<div class="admin-empty-row">Sem utilizadores encontrados para esta pesquisa.</div>';
            return;
        }

        container.innerHTML = rows.map((row) => `
            <article class="admin-user-result${Number(row.id) === Number(activeUserResultId) ? ' is-active' : ''}" data-user-result-id="${Number(row.id)}">
                <div class="admin-user-result__top">
                    <div>
                        <div class="admin-table-strong">${escapeHtml(row.name || '—')}</div>
                        <div class="admin-user-result__meta">${escapeHtml(row.email || '—')}</div>
                    </div>
                    <span class="admin-badge ${((row.role || 'user') === 'admin') ? 'admin-badge--success' : 'admin-badge--neutral'}">${escapeHtml(row.role || 'user')}</span>
                </div>
                <div class="admin-user-result__meta">
                    Favoritos: ${formatNumber(row.favorites_total)} · Pesquisas: ${formatNumber(row.searches_total)} · Sessões ativas: ${formatNumber(row.active_sessions)}
                </div>
            </article>
        `).join('');
    }

    function renderUserDetails(data) {
        const container = document.getElementById('adminUserDetail');
        if (!container) return;

        if (!data || !data.profile) {
            container.innerHTML = '<div class="admin-empty-row">Seleciona um utilizador nos resultados da pesquisa.</div>';
            return;
        }

        const profile = data.profile;
        const recentSearches = data.recent_searches || [];
        const recentFavorites = data.recent_favorites || [];
        const stats = data.stats || {};

        container.innerHTML = `
            <div class="admin-table-strong">${escapeHtml(profile.name || '—')}</div>
            <div class="admin-table-muted">${escapeHtml(profile.email || '—')}</div>
            <div class="admin-user-detail-grid">
                <div class="admin-user-detail-stat">
                    <div class="admin-user-detail-stat__label">Perfil</div>
                    <div class="admin-user-detail-stat__value">${escapeHtml(profile.role || 'user')}</div>
                </div>
                <div class="admin-user-detail-stat">
                    <div class="admin-user-detail-stat__label">Estado</div>
                    <div class="admin-user-detail-stat__value">${profile.is_active ? 'Ativo' : 'Inativo'}</div>
                </div>
                <div class="admin-user-detail-stat">
                    <div class="admin-user-detail-stat__label">Sessões ativas</div>
                    <div class="admin-user-detail-stat__value">${formatNumber(stats.active_sessions)}</div>
                </div>
                <div class="admin-user-detail-stat">
                    <div class="admin-user-detail-stat__label">Pesquisas</div>
                    <div class="admin-user-detail-stat__value">${formatNumber(stats.searches_total)}</div>
                </div>
                <div class="admin-user-detail-stat">
                    <div class="admin-user-detail-stat__label">Favoritos</div>
                    <div class="admin-user-detail-stat__value">${formatNumber(stats.favorites_total)}</div>
                </div>
                <div class="admin-user-detail-stat">
                    <div class="admin-user-detail-stat__label">Último login</div>
                    <div class="admin-user-detail-stat__value">${escapeHtml(formatDateTime(profile.last_login))}</div>
                </div>
            </div>
            <div class="admin-mini-list">
                <div class="admin-table-strong">Últimas pesquisas</div>
                ${recentSearches.length ? recentSearches.map((item) => `
                    <div class="admin-mini-item">
                        <div>${escapeHtml(item.origin_name || '—')} → ${escapeHtml(item.destination_name || '—')}</div>
                        <div class="admin-table-muted">${escapeHtml(formatDateTime(item.searched_at))}</div>
                    </div>
                `).join('') : '<div class="admin-empty-row">Sem pesquisas recentes.</div>'}
            </div>
            <div class="admin-mini-list">
                <div class="admin-table-strong">Favoritos recentes</div>
                ${recentFavorites.length ? recentFavorites.map((item) => `
                    <div class="admin-mini-item">
                        <div>${escapeHtml(item.route_name || '—')}</div>
                        <div class="admin-table-muted">${escapeHtml(item.origin_name || '—')} → ${escapeHtml(item.destination_name || '—')}</div>
                    </div>
                `).join('') : '<div class="admin-empty-row">Sem favoritos recentes.</div>'}
            </div>
        `;
    }

    async function handleExportCsv() {
        const response = await fetch(`/urban/public/api/admin?action=export_metrics_csv&days=${currentDays}`, {
            headers: getRequestHeaders()
        });

        if (!response.ok) {
            throw new Error('Não foi possível exportar o CSV.');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const disposition = response.headers.get('Content-Disposition') || '';
        const filenameMatch = disposition.match(/filename=\"?([^"]+)\"?/i);

        link.href = url;
        link.download = filenameMatch ? filenameMatch[1] : `urban_admin_metrics_${Date.now()}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    async function handleClearCache() {
        const confirmed = window.confirm('Pretendes limpar a cache de rotas agora? Esta ação remove apenas a cache e pode tornar as próximas pesquisas ligeiramente mais lentas até ser repovoada.');
        if (!confirmed) {
            return;
        }

        const result = await fetchAdminJson('clear_cache', {
            method: 'POST'
        });

        if (window.showToast) {
            window.showToast(result.message || 'Cache limpo com sucesso.', 'success');
        }

        await loadDashboard(currentDays);
    }

    async function performUserSearch(query) {
        const trimmed = String(query || '').trim();
        const container = document.getElementById('adminUserSearchResults');
        if (!container) return;

        if (!trimmed) {
            activeUserResultId = null;
            container.innerHTML = '<div class="admin-empty-row">Procura um utilizador para ver resultados.</div>';
            renderUserDetails(null);
            return;
        }

        container.innerHTML = '<div class="admin-empty-row">A procurar utilizadores...</div>';
        const rows = await fetchAdminJson(`search_users&q=${encodeURIComponent(trimmed)}`);
        activeUserResultId = rows[0]?.id ?? null;
        renderUserSearchResults(rows);

        if (activeUserResultId) {
            const details = await fetchAdminJson(`user_details&id=${encodeURIComponent(activeUserResultId)}`);
            renderUserDetails(details);
        } else {
            renderUserDetails(null);
        }
    }

    async function openUserDetails(userId) {
        activeUserResultId = Number(userId);
        document.querySelectorAll('[data-user-result-id]').forEach((element) => {
            element.classList.toggle('is-active', Number(element.dataset.userResultId) === activeUserResultId);
        });
        const details = await fetchAdminJson(`user_details&id=${encodeURIComponent(activeUserResultId)}`);
        renderUserDetails(details);
    }

    async function loadDashboard(days = currentDays) {
        currentDays = days;
        updateRangeButtons(days);
        setLoadingState(true);

        try {
            const data = await fetchAdminDashboard(days);
            renderHeader(data);
            renderStats(data.stats || {});
            renderHighlights(data.stats || {}, data.highlights || {}, data.health || {});
            renderTableHealth(data.health || {});
            renderCharts(data.charts || {});
            renderTables(data.tables || {});
        } finally {
            setLoadingState(false);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-admin-range]').forEach((button) => {
            button.addEventListener('click', () => {
                const nextDays = Number(button.dataset.adminRange || 30);
                loadDashboard(nextDays).catch((error) => {
                    console.warn(error);
                    if (window.showToast) {
                        window.showToast(error.message || 'Erro ao atualizar o backoffice.', 'error');
                    }
                });
            });
        });

        document.getElementById('adminExportCsvBtn')?.addEventListener('click', async () => {
            try {
                await handleExportCsv();
                if (window.showToast) {
                    window.showToast('CSV exportado com sucesso.', 'success');
                }
            } catch (error) {
                console.warn(error);
                if (window.showToast) {
                    window.showToast(error.message || 'Erro ao exportar CSV.', 'error');
                }
            }
        });

        document.getElementById('adminClearCacheBtn')?.addEventListener('click', async () => {
            try {
                await handleClearCache();
            } catch (error) {
                console.warn(error);
                if (window.showToast) {
                    window.showToast(error.message || 'Erro ao limpar cache.', 'error');
                }
            }
        });

        document.getElementById('adminUserSearchForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                await performUserSearch(document.getElementById('adminUserSearchInput')?.value || '');
            } catch (error) {
                console.warn(error);
                if (window.showToast) {
                    window.showToast(error.message || 'Erro ao procurar utilizadores.', 'error');
                }
            }
        });

        document.getElementById('adminUserSearchResults')?.addEventListener('click', async (event) => {
            const card = event.target.closest('[data-user-result-id]');
            if (!card) return;
            try {
                await openUserDetails(card.dataset.userResultId);
            } catch (error) {
                console.warn(error);
                if (window.showToast) {
                    window.showToast(error.message || 'Erro ao carregar o detalhe do utilizador.', 'error');
                }
            }
        });

        loadDashboard().catch((error) => {
            console.warn(error);
            if (window.showToast) {
                window.showToast(error.message || 'Erro ao carregar o backoffice.', 'error');
            }
        });
    });
})();
