// /urban/public/js/dashboard.js - Dashboard do utilizador
(function() {
    const LOCAL_USAGE_KEY = 'urban_usage_events_v1';
    const chartRegistry = {};
    const RANGE_CONFIG = {
        today: { key: 'today', label: 'Hoje', days: 1 },
        week: { key: 'week', label: '7 dias', days: 7 },
        month: { key: 'month', label: '30 dias', days: 30 }
    };
    const state = {
        activeRange: 'today',
        payload: null
    };

    function notifyDashboard(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        console.warn(message);
    }

    function safeJsonParse(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getCssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function readLocalEvents(user) {
        const allEvents = safeJsonParse(localStorage.getItem(LOCAL_USAGE_KEY) || '[]', []);
        const key = user?.email || user?.id || '';
        return Array.isArray(allEvents)
            ? allEvents.filter((event) => !key || event.user_key === key)
            : [];
    }

    function withRouteCoordinates(item = {}, routeData = null) {
        return {
            ...item,
            origin_lat: item.origin_lat ?? routeData?.origin_lat ?? null,
            origin_lon: item.origin_lon ?? routeData?.origin_lon ?? null,
            destination_lat: item.destination_lat ?? routeData?.destination_lat ?? null,
            destination_lon: item.destination_lon ?? routeData?.destination_lon ?? null
        };
    }

    function normalizeHistory(history = []) {
        return history
            .map((item) => {
                const routeData = typeof item.route_data === 'string' ? safeJsonParse(item.route_data, null) : item.route_data;
                return {
                    ...withRouteCoordinates(item, routeData),
                    routeData,
                    searchedDate: new Date(item.searched_at),
                    routeKey: `${item.origin_name || 'Origem'}__${item.destination_name || 'Destino'}`
                };
            })
            .filter((item) => !Number.isNaN(item.searchedDate.getTime()))
            .sort((a, b) => b.searchedDate - a.searchedDate);
    }

    function normalizeFavorites(favorites = []) {
        return favorites
            .map((item) => {
                const routeData = typeof item.route_data === 'string' ? safeJsonParse(item.route_data, null) : item.route_data;
                return {
                    ...withRouteCoordinates(item, routeData),
                    routeData,
                    routeKey: `${item.origin_name || 'Origem'}__${item.destination_name || 'Destino'}`
                };
            })
            .sort((a, b) => String(a.route_name || '').localeCompare(String(b.route_name || '')));
    }

    function normalizeLocalEvents(events = []) {
        return events
            .map((item) => ({
                ...item,
                searchedDate: new Date(item.searched_at),
                routeKey: `${item.origin_name || 'Origem'}__${item.destination_name || 'Destino'}`
            }))
            .filter((item) => !Number.isNaN(item.searchedDate.getTime()))
            .sort((a, b) => b.searchedDate - a.searchedDate);
    }

    function getRangeStart(rangeKey) {
        const start = new Date();
        start.setHours(0, 0, 0, 0);

        if (rangeKey === 'week') {
            start.setDate(start.getDate() - 6);
        } else if (rangeKey === 'month') {
            start.setDate(start.getDate() - 29);
        }

        return start;
    }

    function getPreviousWindow(rangeKey) {
        const currentStart = getRangeStart(rangeKey);
        const previousStart = new Date(currentStart);
        previousStart.setDate(previousStart.getDate() - RANGE_CONFIG[rangeKey].days);
        return { start: previousStart, end: currentStart };
    }

    function filterItemsByRange(items, rangeKey) {
        const start = getRangeStart(rangeKey);
        return items.filter((item) => item.searchedDate >= start);
    }

    function filterItemsByPreviousRange(items, rangeKey) {
        const previousWindow = getPreviousWindow(rangeKey);
        return items.filter((item) => item.searchedDate >= previousWindow.start && item.searchedDate < previousWindow.end);
    }

    function groupCount(items, selector) {
        return items.reduce((accumulator, item) => {
            const key = selector(item);
            if (!key) return accumulator;
            accumulator[key] = (accumulator[key] || 0) + 1;
            return accumulator;
        }, {});
    }

    function topEntry(counts) {
        const entries = Object.entries(counts || {});
        if (!entries.length) return null;
        entries.sort((a, b) => b[1] - a[1]);
        return { label: entries[0][0], value: entries[0][1] };
    }

    function sum(items, selector) {
        return items.reduce((total, item) => total + (Number(selector(item)) || 0), 0);
    }

    function distinctCount(items, selector) {
        const keys = new Set();
        items.forEach((item) => {
            const key = selector(item);
            if (key) keys.add(key);
        });
        return keys.size;
    }

    function uniqueByRoute(items) {
        const seen = new Set();
        return items.filter((item) => {
            if (!item.routeKey || seen.has(item.routeKey)) return false;
            seen.add(item.routeKey);
            return true;
        });
    }

    function routeMatchesFavorite(historyItem, favoriteItem) {
        return historyItem.origin_name === favoriteItem.origin_name && historyItem.destination_name === favoriteItem.destination_name;
    }

    function formatRouteLabel(item) {
        if (!item) return 'Sem dados ainda';
        const origin = item.origin_name || item.originName || 'Origem';
        const destination = item.destination_name || item.destinationName || 'Destino';
        return `${origin} → ${destination}`;
    }

    function formatDurationMinutes(value) {
        const minutes = Number(value || 0);
        if (!minutes) return 'Sem dados';

        const hours = Math.floor(minutes / 60);
        const remainingMinutes = Math.round(minutes % 60);

        if (!hours) return `${remainingMinutes} min`;
        if (!remainingMinutes) return `${hours} h`;
        return `${hours} h ${remainingMinutes} min`;
    }

    function formatDateLabel(date) {
        return date.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' });
    }

    function formatDateTimeLabel(date) {
        return date.toLocaleString('pt-PT', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatHourLabel(date) {
        return `${String(date.getHours()).padStart(2, '0')}:00`;
    }

    function canOpenRoute(entry) {
        return [
            entry?.origin_lat,
            entry?.origin_lon,
            entry?.destination_lat,
            entry?.destination_lon
        ].every((value) => value !== null && value !== undefined && value !== '');
    }

    function buildResultsUrl(entry) {
        if (!entry || !canOpenRoute(entry)) return 'index.php';

        const params = new URLSearchParams({
            fromLat: entry.origin_lat,
            fromLon: entry.origin_lon,
            toLat: entry.destination_lat,
            toLon: entry.destination_lon,
            origin: entry.origin_name || 'Origem',
            dest: entry.destination_name || 'Destino'
        });

        return `results.php?${params.toString()}`;
    }

    function formatDelta(current, previous) {
        if (!current && !previous) {
            return { text: 'Sem histórico comparável', className: 'is-neutral', icon: 'fa-wave-square' };
        }

        if (!previous && current) {
            return { text: `+${current} face ao período anterior`, className: '', icon: 'fa-arrow-trend-up' };
        }

        const difference = current - previous;
        if (!difference) {
            return { text: 'Sem variação', className: 'is-neutral', icon: 'fa-minus' };
        }

        const percent = Math.round((Math.abs(difference) / previous) * 100);
        return {
            text: `${difference > 0 ? '+' : '-'}${percent}% vs anterior`,
            className: difference > 0 ? '' : 'is-negative',
            icon: difference > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'
        };
    }

    function formatDurationDelta(current, previous) {
        if (!current && !previous) {
            return { text: 'Sem histórico comparável', className: 'is-neutral', icon: 'fa-wave-square' };
        }

        if (!previous && current) {
            return { text: `+${formatDurationMinutes(current)} vs anterior`, className: '', icon: 'fa-arrow-trend-up' };
        }

        const difference = current - previous;
        if (!difference) {
            return { text: 'Sem variação', className: 'is-neutral', icon: 'fa-minus' };
        }

        return {
            text: `${difference > 0 ? '+' : '-'}${formatDurationMinutes(Math.abs(difference))} vs anterior`,
            className: difference > 0 ? '' : 'is-negative',
            icon: difference > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'
        };
    }

    function setText(id, text) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = text;
        }
    }

    function buildEmptyState(icon, title, description, includeButton = true) {
        return `
            <div class="ut-empty-state ${includeButton ? '' : 'ut-empty-state--compact'}">
                <i class="fas ${escapeHtml(icon)}"></i>
                <h3 class="h6 mb-2">${escapeHtml(title)}</h3>
                <p class="text-muted mb-${includeButton ? '3' : '0'}">${escapeHtml(description)}</p>
                ${includeButton ? `
                    <a class="btn btn-urbano ut-btn ut-btn-primary ut-btn-sm" href="index.php">
                        <i class="fas fa-route"></i>Planear viagem
                    </a>
                ` : ''}
            </div>
        `;
    }

    function renderLoadingState() {
        const statsContainer = document.getElementById('dashboardStats');
        if (statsContainer) {
            statsContainer.innerHTML = Array.from({ length: 4 }).map(() => `
                <article class="ut-panel ut-stat-card ut-dashboard-card">
                    <div class="ut-stat-card__head">
                        <div class="w-100">
                            <div class="ut-skeleton rounded-3 mb-2" style="height:14px;width:90px;"></div>
                            <div class="ut-skeleton rounded-3 mb-2" style="height:34px;width:120px;"></div>
                            <div class="ut-skeleton rounded-3" style="height:14px;width:170px;"></div>
                        </div>
                        <div class="ut-skeleton rounded-4" style="width:52px;height:52px;"></div>
                    </div>
                    <div class="ut-skeleton rounded-pill" style="height:30px;width:150px;"></div>
                </article>
            `).join('');
        }

        const pulseContainer = document.getElementById('dashboardPulseMetrics');
        if (pulseContainer) {
            pulseContainer.innerHTML = Array.from({ length: 4 }).map(() => `
                <div class="ut-dashboard-pulse__item">
                    <div class="ut-skeleton rounded-3" style="height:12px;width:88px;"></div>
                    <div class="ut-skeleton rounded-3" style="height:20px;width:120px;"></div>
                    <div class="ut-skeleton rounded-3" style="height:12px;width:132px;"></div>
                </div>
            `).join('');
        }

        const highlightsContainer = document.getElementById('dashboardHighlights');
        if (highlightsContainer) {
            highlightsContainer.innerHTML = Array.from({ length: 4 }).map(() => `
                <div class="ut-dashboard-insight">
                    <div class="ut-skeleton rounded-4" style="width:42px;height:42px;"></div>
                    <div class="w-100">
                        <div class="ut-skeleton rounded-3 mb-2" style="height:14px;width:130px;"></div>
                        <div class="ut-skeleton rounded-3 mb-2" style="height:12px;width:100%;"></div>
                        <div class="ut-skeleton rounded-3" style="height:12px;width:84%;"></div>
                    </div>
                </div>
            `).join('');
        }

        ['favoritesScrollList', 'viewedRoutesScrollList', 'latestSearchesScrollList', 'quickSuggestionsScrollList'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = `
                    <div class="ut-loading">
                        <span class="ut-loading-spinner"></span>
                        <span class="text-muted">A carregar dados...</span>
                    </div>
                `;
            }
        });

        [
            ['usageTrendChart', 'usageTrendEmpty'],
            ['peakHoursChart', 'peakHoursEmpty'],
            ['lineUsageChart', 'lineUsageEmpty'],
            ['favoritesVsViewedChart', 'favoritesVsViewedEmpty']
        ].forEach(([canvasId, emptyId]) => {
            const canvas = document.getElementById(canvasId);
            const emptyState = document.getElementById(emptyId);

            if (canvas) canvas.classList.add('d-none');
            if (emptyState) {
                emptyState.classList.remove('d-none');
                emptyState.innerHTML = `
                    <div class="ut-loading">
                        <span class="ut-loading-spinner"></span>
                        <span>A preparar gráfico...</span>
                    </div>
                `;
            }
        });
    }

    function renderOverview(rangeData) {
        const rangeLabel = RANGE_CONFIG[rangeData.rangeKey].label;
        const headline = document.getElementById('dashboardHeroHeadline');
        const subhead = document.getElementById('dashboardHeroSubhead');
        const pulseContainer = document.getElementById('dashboardPulseMetrics');
        const highlightsContainer = document.getElementById('dashboardHighlights');

        const searches = rangeData.history.length;
        const viewedRouteCount = distinctCount(
            rangeData.localEvents.length ? rangeData.localEvents : rangeData.history,
            (item) => item.routeKey
        );
        const usedFavorites = rangeData.favoriteUsage.filter((entry) => entry.uses > 0).length;
        const totalDuration = sum(rangeData.localEvents, (item) => item.duration);
        const topRouteEntry = topEntry(groupCount(rangeData.history, (item) => item.routeKey));
        const topRoute = topRouteEntry
            ? rangeData.history.find((item) => item.routeKey === topRouteEntry.label)
            : null;
        const peakHourEntry = topEntry(
            groupCount(rangeData.history.length ? rangeData.history : rangeData.localEvents, (item) => formatHourLabel(item.searchedDate))
        );
        const topLineEntry = topEntry(groupCount(rangeData.localEvents, (item) => item.line || item.route_name));

        if (headline) {
            headline.textContent = searches || viewedRouteCount || rangeData.favorites.length
                ? `${rangeLabel}: ${searches} pesquisas, ${viewedRouteCount} rotas consultadas e ${usedFavorites} favoritos em uso.`
                : `${rangeLabel}: ainda estamos à espera dos teus primeiros sinais de mobilidade.`;
        }

        if (subhead) {
            subhead.textContent = searches || viewedRouteCount || totalDuration
                ? `O teu dashboard junta histórico sincronizado, favoritos guardados e atividade local para mostrar padrões reais de uso sem precisar de abrir cada detalhe.`
                : 'Assim que fizeres pesquisas, guardares favoritos ou voltares a abrir percursos, este painel ganha contexto automaticamente.';
        }

        if (pulseContainer) {
            const pulseItems = [
                {
                    label: 'Rota líder',
                    value: topRoute ? formatRouteLabel(topRoute) : 'Sem destaque',
                    meta: topRouteEntry ? `${topRouteEntry.value} pesquisa${topRouteEntry.value === 1 ? '' : 's'} no período` : 'Ainda sem repetição suficiente'
                },
                {
                    label: 'Hora de pico',
                    value: peakHourEntry ? peakHourEntry.label : 'Sem padrão',
                    meta: peakHourEntry ? `${peakHourEntry.value} pesquisa${peakHourEntry.value === 1 ? '' : 's'} nessa janela` : 'Será mostrada com mais uso'
                },
                {
                    label: 'Linha em foco',
                    value: topLineEntry ? topLineEntry.label : 'Sem dados locais',
                    meta: topLineEntry ? `${topLineEntry.value} visualizaç${topLineEntry.value === 1 ? 'ão' : 'ões'} locais` : 'Depende das rotas abertas neste dispositivo'
                },
                {
                    label: 'Tempo acumulado',
                    value: formatDurationMinutes(totalDuration),
                    meta: totalDuration ? 'Estimado a partir das tuas visualizações recentes' : 'Soma disponível quando houver viagens abertas'
                }
            ];

            pulseContainer.innerHTML = pulseItems.map((item) => `
                <div class="ut-dashboard-pulse__item">
                    <div class="ut-dashboard-pulse__label">${escapeHtml(item.label)}</div>
                    <div class="ut-dashboard-pulse__value">${escapeHtml(item.value)}</div>
                    <div class="ut-dashboard-pulse__meta">${escapeHtml(item.meta)}</div>
                </div>
            `).join('');
        }

        if (highlightsContainer) {
            const highlightItems = [];

            highlightItems.push({
                icon: 'fa-layer-group',
                title: 'Cobertura do período',
                text: searches
                    ? `Tens ${searches} pesquisas guardadas e ${viewedRouteCount} percursos distintos observados em ${rangeLabel.toLowerCase()}.`
                    : `Ainda não há pesquisas suficientes em ${rangeLabel.toLowerCase()} para construir um retrato mais rico.`
            });

            highlightItems.push({
                icon: 'fa-heart-circle-check',
                title: 'Uso de favoritos',
                text: rangeData.favorites.length
                    ? `${usedFavorites} de ${rangeData.favorites.length} favoritos foram reutilizados neste período.`
                    : 'Quando guardares rotas, este bloco passa a mostrar reutilização real.'
            });

            highlightItems.push({
                icon: 'fa-clock-rotate-left',
                title: 'Último comportamento',
                text: rangeData.history[0]
                    ? `A tua pesquisa mais recente foi ${formatDateTimeLabel(rangeData.history[0].searchedDate)} para ${formatRouteLabel(rangeData.history[0])}.`
                    : 'Ainda não existe uma última pesquisa sincronizada para resumir aqui.'
            });

            highlightItems.push({
                icon: 'fa-compass-drafting',
                title: 'Próximo melhor atalho',
                text: topRoute
                    ? `Se esta rota continuar a repetir-se, vale a pena mantê-la visível nos favoritos para reabrir mais depressa.`
                    : 'Assim que uma rota se repetir, este painel começa a sugerir o atalho mais útil.'
            });

            highlightsContainer.innerHTML = highlightItems.map((item) => `
                <div class="ut-dashboard-insight">
                    <span class="ut-dashboard-insight__icon"><i class="fas ${escapeHtml(item.icon)}"></i></span>
                    <div class="ut-dashboard-insight__content">
                        <div class="ut-dashboard-insight__title">${escapeHtml(item.title)}</div>
                        <div class="ut-dashboard-insight__text">${escapeHtml(item.text)}</div>
                    </div>
                </div>
            `).join('');
        }
    }

    function renderStatCards(stats) {
        const container = document.getElementById('dashboardStats');
        if (!container) return;

        container.innerHTML = stats.map((stat) => `
            <article class="ut-panel ut-stat-card ut-dashboard-card">
                <div class="ut-stat-card__head">
                    <div>
                        <div class="ut-stat-label">${escapeHtml(stat.label)}</div>
                        <div class="ut-stat-value">${escapeHtml(stat.value)}</div>
                        <div class="ut-stat-note">${escapeHtml(stat.note)}</div>
                    </div>
                    <span class="ut-stat-icon"><i class="fas ${escapeHtml(stat.icon)}"></i></span>
                </div>
                ${stat.trend?.text ? `
                    <div class="ut-stat-card__trend ${escapeHtml(stat.trend.className || '')}">
                        <i class="fas ${escapeHtml(stat.trend.icon)}"></i>
                        <span>${escapeHtml(stat.trend.text)}</span>
                    </div>
                ` : ''}
            </article>
        `).join('');
    }

    function renderScrollList(containerId, items, emptyConfig) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!items.length) {
            container.innerHTML = buildEmptyState(
                emptyConfig.icon,
                emptyConfig.title,
                emptyConfig.description,
                emptyConfig.includeButton !== false
            );
            return;
        }

        container.innerHTML = items.map((item) => `
            <div class="ut-scroll-list-item">
                <div class="ut-scroll-list-item__content">
                    <div class="ut-scroll-list-item__title">${escapeHtml(item.title)}</div>
                    <div class="ut-scroll-list-item__meta">${escapeHtml(item.meta || '')}</div>
                    ${item.value ? `<div class="ut-scroll-list-item__value">${escapeHtml(item.value)}</div>` : ''}
                </div>
                ${item.url ? `
                    <a class="btn ut-btn ut-btn-secondary ut-btn-sm ut-scroll-list-item__action" href="${escapeHtml(item.url)}">
                        <i class="fas fa-arrow-up-right-from-square"></i>${escapeHtml(item.actionLabel || 'Abrir')}
                    </a>
                ` : ''}
            </div>
        `).join('');
    }

    function destroyChart(chartKey) {
        if (chartRegistry[chartKey]) {
            chartRegistry[chartKey].destroy();
            delete chartRegistry[chartKey];
        }
    }

    function renderChart(chartKey, canvasId, emptyId, config, emptyConfig) {
        const canvas = document.getElementById(canvasId);
        const emptyState = document.getElementById(emptyId);
        if (!canvas || typeof Chart === 'undefined') return;

        const datasetValues = config?.data?.datasets?.flatMap((dataset) => dataset.data || []) || [];
        const hasData = datasetValues.some((value) => Number(value) > 0);

        if (!hasData) {
            destroyChart(chartKey);
            canvas.classList.add('d-none');
            if (emptyState) {
                emptyState.classList.remove('d-none');
                emptyState.innerHTML = buildEmptyState(
                    emptyConfig.icon,
                    emptyConfig.title,
                    emptyConfig.description,
                    false
                );
            }
            return;
        }

        const textMuted = getCssVar('--text-muted', '#6b7280');
        const borderColor = getCssVar('--border-soft', 'rgba(148, 163, 184, 0.18)');
        const textMain = getCssVar('--text-main', '#10231a');

        destroyChart(chartKey);
        canvas.classList.remove('d-none');
        if (emptyState) {
            emptyState.classList.add('d-none');
            emptyState.innerHTML = '';
        }

        chartRegistry[chartKey] = new Chart(canvas, {
            ...config,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: config.type === 'doughnut' || config.type === 'pie',
                        position: 'bottom',
                        labels: {
                            color: textMuted,
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(16, 35, 26, 0.92)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 12
                    }
                },
                scales: config.type === 'doughnut' || config.type === 'pie' ? {} : {
                    x: {
                        ticks: {
                            color: textMuted
                        },
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textMuted,
                            precision: 0
                        },
                        grid: {
                            color: borderColor,
                            drawBorder: false
                        }
                    }
                },
                ...config.options
            }
        });

        if (canvas.parentElement) {
            canvas.parentElement.style.color = textMain;
        }
    }

    function createDailyTrend(history) {
        const counts = new Array(24).fill(0);
        history.forEach((item) => {
            counts[item.searchedDate.getHours()] += 1;
        });

        return {
            labels: Array.from({ length: 24 }, (_, hour) => `${String(hour).padStart(2, '0')}h`),
            values: counts
        };
    }

    function createMultiDayTrend(history, rangeKey) {
        const totalDays = RANGE_CONFIG[rangeKey].days;
        const labels = [];
        const values = [];
        const countsByDay = groupCount(history, (item) => item.searchedDate.toISOString().slice(0, 10));

        for (let offset = totalDays - 1; offset >= 0; offset--) {
            const day = new Date();
            day.setHours(0, 0, 0, 0);
            day.setDate(day.getDate() - offset);
            const isoKey = day.toISOString().slice(0, 10);
            labels.push(formatDateLabel(day));
            values.push(countsByDay[isoKey] || 0);
        }

        return { labels, values };
    }

    function buildTrendChart(rangeData, rangeKey) {
        const source = rangeData.history.length ? rangeData.history : rangeData.localEvents;
        const trend = rangeKey === 'today'
            ? createDailyTrend(source)
            : createMultiDayTrend(source, rangeKey);

        return {
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Pesquisas',
                    data: trend.values,
                    borderColor: getCssVar('--primary', '#2f9e53'),
                    backgroundColor: 'rgba(47, 158, 83, 0.18)',
                    fill: true,
                    tension: 0.34,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 4
                }]
            },
            type: 'line'
        };
    }

    function buildPeakHoursChart(rangeData) {
        const source = rangeData.history.length ? rangeData.history : rangeData.localEvents;
        const hourCounts = groupCount(source, (item) => formatHourLabel(item.searchedDate));
        const topHours = Object.entries(hourCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 6);

        return {
            type: 'bar',
            data: {
                labels: topHours.map(([label]) => label),
                datasets: [{
                    label: 'Pesquisas',
                    data: topHours.map(([, value]) => value),
                    backgroundColor: 'rgba(47, 158, 83, 0.75)',
                    borderRadius: 14,
                    maxBarThickness: 34
                }]
            },
            options: {
                indexAxis: 'y'
            }
        };
    }

    function buildLineUsageChart(rangeData) {
        const lineCounts = groupCount(rangeData.localEvents, (item) => item.line || item.route_name);
        const topLines = Object.entries(lineCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5);

        const palette = [
            'rgba(47, 158, 83, 0.88)',
            'rgba(89, 190, 118, 0.84)',
            'rgba(19, 120, 64, 0.82)',
            'rgba(112, 192, 135, 0.82)',
            'rgba(28, 78, 49, 0.78)'
        ];

        return {
            type: 'doughnut',
            data: {
                labels: topLines.map(([label]) => label),
                datasets: [{
                    data: topLines.map(([, value]) => value),
                    backgroundColor: palette,
                    borderColor: getCssVar('--surface', '#ffffff'),
                    borderWidth: 3
                }]
            },
            options: {
                cutout: '62%'
            }
        };
    }

    function buildFavoritesVsViewedChart(rangeData) {
        const favoritesUsed = rangeData.favoriteUsage.filter((entry) => entry.uses > 0).length;
        const viewedRoutes = distinctCount(rangeData.localEvents.length ? rangeData.localEvents : rangeData.history, (item) => item.routeKey);

        return {
            type: 'bar',
            data: {
                labels: ['Favoritos guardados', 'Favoritos usados', 'Rotas visualizadas'],
                datasets: [{
                    label: 'Total',
                    data: [rangeData.favorites.length, favoritesUsed, viewedRoutes],
                    backgroundColor: [
                        'rgba(47, 158, 83, 0.80)',
                        'rgba(89, 190, 118, 0.76)',
                    'rgba(19, 120, 64, 0.78)'
                    ],
                    borderRadius: 14,
                    maxBarThickness: 42
                }]
            }
        };
    }

    function computeRangeData(payload, rangeKey) {
        const history = filterItemsByRange(payload.history, rangeKey);
        const localEvents = filterItemsByRange(payload.localEvents, rangeKey);
        const previousHistory = filterItemsByPreviousRange(payload.history, rangeKey);
        const previousLocalEvents = filterItemsByPreviousRange(payload.localEvents, rangeKey);

        const favoriteUsage = payload.favorites
            .map((favorite) => ({
                ...favorite,
                uses: history.filter((item) => routeMatchesFavorite(item, favorite)).length
            }))
            .sort((a, b) => b.uses - a.uses || String(a.route_name || '').localeCompare(String(b.route_name || '')));

        return {
            rangeKey,
            history,
            localEvents,
            previousHistory,
            previousLocalEvents,
            favorites: payload.favorites,
            favoriteUsage
        };
    }

    function renderSummary(rangeData) {
        const rangeLabel = RANGE_CONFIG[rangeData.rangeKey].label;
        const searches = rangeData.history.length;
        const previousSearches = rangeData.previousHistory.length;
        const viewedRouteCount = distinctCount(
            rangeData.localEvents.length ? rangeData.localEvents : rangeData.history,
            (item) => item.routeKey
        );
        const previousViewedRouteCount = distinctCount(
            rangeData.previousLocalEvents.length ? rangeData.previousLocalEvents : rangeData.previousHistory,
            (item) => item.routeKey
        );
        const usedFavorites = rangeData.favoriteUsage.filter((entry) => entry.uses > 0).length;
        const totalDuration = sum(rangeData.localEvents, (item) => item.duration);
        const previousDuration = sum(rangeData.previousLocalEvents, (item) => item.duration);

        renderStatCards([
            {
                label: 'Pesquisas',
                value: String(searches),
                note: `${rangeLabel} com histórico guardado na tua conta`,
                icon: 'fa-magnifying-glass',
                trend: previousSearches ? formatDelta(searches, previousSearches) : null
            },
            {
                label: 'Rotas consultadas',
                value: String(viewedRouteCount),
                note: viewedRouteCount ? 'Percursos distintos realmente vistos' : 'Ainda sem rotas distintas no período',
                icon: 'fa-route',
                trend: previousViewedRouteCount ? formatDelta(viewedRouteCount, previousViewedRouteCount) : null
            },
            {
                label: 'Favoritos',
                value: String(rangeData.favorites.length),
                note: usedFavorites ? `${usedFavorites} favorito${usedFavorites === 1 ? '' : 's'} usado${usedFavorites === 1 ? '' : 's'} neste período` : 'Guardados na tua conta para acesso rápido',
                icon: 'fa-heart',
                trend: usedFavorites
                    ? { text: `${usedFavorites} em uso`, className: '', icon: 'fa-bookmark' }
                    : null
            },
            {
                label: 'Tempo estimado em deslocações',
                value: formatDurationMinutes(totalDuration),
                note: totalDuration ? 'Somado a partir das rotas visualizadas neste dispositivo' : 'Disponível quando houver visualizações locais',
                icon: 'fa-clock',
                trend: totalDuration && previousDuration
                    ? formatDurationDelta(totalDuration, previousDuration)
                    : null
            }
        ]);

        const periodSummaryText = searches || viewedRouteCount || rangeData.favorites.length
            ? `${rangeLabel}: ${searches} pesquisas guardadas, ${viewedRouteCount} rotas consultadas e ${usedFavorites} favoritos usados neste período.`
            : `${rangeLabel}: ainda não há dados suficientes. Planeia algumas viagens para veres relatórios personalizados.`;

        setText('dashboardPeriodSummary', periodSummaryText);
    }

    function renderCharts(rangeData) {
        const rangeLabel = RANGE_CONFIG[rangeData.rangeKey].label;
        setText('usageTrendMeta', `Pesquisas agrupadas ao longo de ${rangeLabel.toLowerCase()}.`);
        setText('peakHoursMeta', `Horários em que mais usaste a app durante ${rangeLabel.toLowerCase()}.`);
        setText('lineUsageMeta', `Linhas observadas nas rotas visualizadas em ${rangeLabel.toLowerCase()}.`);
        setText('favoritesVsViewedMeta', `Comparação entre favoritos guardados e rotas visualizadas em ${rangeLabel.toLowerCase()}.`);

        renderChart(
            'usageTrend',
            'usageTrendChart',
            'usageTrendEmpty',
            buildTrendChart(rangeData, rangeData.rangeKey),
            {
                icon: 'fa-chart-line',
                title: 'Ainda não há dados suficientes',
                description: 'Planeia algumas viagens para veres a evolução do uso ao longo do tempo.'
            }
        );

        renderChart(
            'peakHours',
            'peakHoursChart',
            'peakHoursEmpty',
            buildPeakHoursChart(rangeData),
            {
                icon: 'fa-clock',
                title: 'Ainda não há horários destacados',
                description: 'Quando houver pesquisas neste período, os teus horários mais usados aparecem aqui.'
            }
        );

        renderChart(
            'lineUsage',
            'lineUsageChart',
            'lineUsageEmpty',
            buildLineUsageChart(rangeData),
            {
                icon: 'fa-bus',
                title: 'Ainda não há linhas suficientes',
                description: 'As linhas mais consultadas surgem a partir das rotas visualizadas neste dispositivo.'
            }
        );

        renderChart(
            'favoritesVsViewed',
            'favoritesVsViewedChart',
            'favoritesVsViewedEmpty',
            buildFavoritesVsViewedChart(rangeData),
            {
                icon: 'fa-scale-balanced',
                title: 'Ainda não há comparação disponível',
                description: 'Guarda favoritos e volta a abrir rotas para comparar hábitos reais de uso.'
            }
        );
    }

    function buildFavoriteItems(rangeData) {
        return rangeData.favoriteUsage.slice(0, 8).map((entry) => ({
            title: entry.route_name || formatRouteLabel(entry),
            meta: formatRouteLabel(entry),
            value: entry.uses ? `${entry.uses} uso${entry.uses === 1 ? '' : 's'} neste período` : 'Ainda sem uso neste período',
            url: buildResultsUrl(entry),
            actionLabel: 'Abrir'
        }));
    }

    function buildViewedItems(rangeData) {
        return uniqueByRoute(rangeData.localEvents).slice(0, 8).map((entry) => ({
            title: entry.route_name || entry.line || formatRouteLabel(entry),
            meta: `${formatRouteLabel(entry)} · ${formatDateTimeLabel(entry.searchedDate)}`,
            value: entry.duration ? `${Math.round(entry.duration)} min estimados` : 'Sem tempo estimado',
            url: buildResultsUrl(entry),
            actionLabel: 'Ver'
        }));
    }

    function buildHistoryItems(rangeData) {
        return rangeData.history.slice(0, 8).map((entry) => ({
            title: formatRouteLabel(entry),
            meta: `Pesquisa guardada a ${formatDateTimeLabel(entry.searchedDate)}`,
            value: entry.route_name || entry.line || 'Rota consultada',
            url: buildResultsUrl(entry),
            actionLabel: 'Reabrir'
        }));
    }

    function buildSuggestionItems(rangeData) {
        const suggestions = [];
        const routeCounts = groupCount(rangeData.history, (item) => item.routeKey);
        const topRoute = topEntry(routeCounts);
        const topRouteItem = topRoute
            ? rangeData.history.find((entry) => entry.routeKey === topRoute.label)
            : null;
        const topFavorite = rangeData.favoriteUsage.find((entry) => entry.uses > 0) || rangeData.favoriteUsage[0] || null;
        const latestHistory = rangeData.history[0] || null;
        const topViewedLine = topEntry(groupCount(rangeData.localEvents, (entry) => entry.line || entry.route_name));

        if (topRouteItem) {
            suggestions.push({
                title: 'A tua rota mais repetida',
                meta: formatRouteLabel(topRouteItem),
                value: `${topRoute.value} pesquisas neste período`,
                url: buildResultsUrl(topRouteItem),
                actionLabel: 'Abrir'
            });
        }

        if (topFavorite) {
            suggestions.push({
                title: 'Poupa tempo com esta favorita',
                meta: topFavorite.route_name || formatRouteLabel(topFavorite),
                value: topFavorite.uses ? `${topFavorite.uses} usos neste período` : 'Guardada para acesso rápido',
                url: buildResultsUrl(topFavorite),
                actionLabel: 'Usar'
            });
        }

        if (latestHistory) {
            suggestions.push({
                title: 'Retomar última pesquisa',
                meta: formatRouteLabel(latestHistory),
                value: formatDateTimeLabel(latestHistory.searchedDate),
                url: buildResultsUrl(latestHistory),
                actionLabel: 'Retomar'
            });
        }

        if (topViewedLine) {
            suggestions.push({
                title: 'Linha em destaque',
                meta: String(topViewedLine.label).toLowerCase().startsWith('linha') ? topViewedLine.label : `Linha ${topViewedLine.label}`,
                value: `${topViewedLine.value} visualizações locais`,
                url: null,
                actionLabel: 'Detalhe'
            });
        }

        const deduplicated = [];
        const seenTitles = new Set();
        suggestions.forEach((item) => {
            if (!item || seenTitles.has(item.title)) return;
            seenTitles.add(item.title);
            deduplicated.push(item);
        });

        return deduplicated.slice(0, 8);
    }

    function renderLists(rangeData) {
        const rangeLabel = RANGE_CONFIG[rangeData.rangeKey].label.toLowerCase();
        setText('favoritesMeta', `Favoritos guardados e respetivo uso em ${rangeLabel}.`);
        setText('viewedMeta', `As rotas visualizadas recentemente neste dispositivo durante ${rangeLabel}.`);
        setText('historyMeta', `Pesquisas sincronizadas na tua conta ao longo de ${rangeLabel}.`);
        setText('suggestionsMeta', `Atalhos úteis gerados a partir dos teus padrões reais em ${rangeLabel}.`);

        renderScrollList('favoritesScrollList', buildFavoriteItems(rangeData), {
            icon: 'fa-heart',
            title: 'Ainda não há favoritos guardados',
            description: 'Guarda rotas para as veres aqui.',
            includeButton: false
        });

        renderScrollList('viewedRoutesScrollList', buildViewedItems(rangeData), {
            icon: 'fa-route',
            title: 'Ainda não há rotas visualizadas',
            description: 'As rotas recentes aparecem aqui.',
            includeButton: false
        });

        renderScrollList('latestSearchesScrollList', buildHistoryItems(rangeData), {
            icon: 'fa-clock-rotate-left',
            title: 'Ainda não há pesquisas guardadas',
            description: 'O histórico sincronizado aparece aqui.',
            includeButton: false
        });

        renderScrollList('quickSuggestionsScrollList', buildSuggestionItems(rangeData), {
            icon: 'fa-wand-magic-sparkles',
            title: 'Ainda não há dados suficientes',
            description: 'As sugestões surgem com o teu uso real.',
            includeButton: false
        });
    }

    function renderDashboard() {
        if (!state.payload) return;

        const rangeData = computeRangeData(state.payload, state.activeRange);
        renderOverview(rangeData);
        renderSummary(rangeData);
        renderCharts(rangeData);
        renderLists(rangeData);
    }

    function setupTabs() {
        document.querySelectorAll('.ut-report-tab').forEach((button) => {
            button.addEventListener('click', () => {
                const rangeKey = button.dataset.range;
                if (!RANGE_CONFIG[rangeKey]) return;

                state.activeRange = rangeKey;
                document.querySelectorAll('.ut-report-tab').forEach((item) => item.classList.toggle('active', item === button));
                renderDashboard();
            });
        });
    }

    async function fetchJson(url, headers) {
        const response = await fetch(url, { headers });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status} for ${url}`);
        }
        return response.json();
    }

    async function loadDashboard() {
        const app = document.getElementById('dashboardApp');
        if (!app || app.dataset.authenticated !== '1') return;

        const token = window.authManager?.getToken?.();
        const user = window.authManager?.getCurrentUser?.() || window.__dashboardBootstrapUser || null;
        if (!user) {
            notifyDashboard('Sessão não disponível para carregar o dashboard.', 'warning');
            return;
        }

        renderLoadingState();
        setupTabs();

        try {
            const authHeaders = token ? { Authorization: `Bearer ${token}` } : {};
            const [profileResult, favoritesResult, historyResult] = await Promise.allSettled([
                fetchJson('/urban/public/api/auth?action=profile', authHeaders),
                fetchJson('/urban/public/api/user?action=favorites&limit=200', authHeaders),
                fetchJson('/urban/public/api/user?action=history&limit=500', authHeaders)
            ]);

            state.payload = {
                user: profileResult.status === 'fulfilled' ? (profileResult.value.user || user) : user,
                favorites: normalizeFavorites(
                    favoritesResult.status === 'fulfilled' ? (favoritesResult.value.favorites || []) : []
                ),
                history: normalizeHistory(
                    historyResult.status === 'fulfilled' ? (historyResult.value.history || []) : []
                ),
                localEvents: normalizeLocalEvents(readLocalEvents(user))
            };

            renderDashboard();

            if (favoritesResult.status === 'rejected' || historyResult.status === 'rejected') {
                notifyDashboard('Alguns dados do dashboard ficaram indisponíveis, mas o resumo local continua visível.', 'warning');
            }
        } catch (error) {
            console.warn('Dashboard load error:', error);
            notifyDashboard('Não foi possível carregar o dashboard neste momento.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', loadDashboard, { once: true });
})();
