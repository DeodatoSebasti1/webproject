// /urban/public/js/routes.js

const ROUTES_FETCH_TIMEOUT_MS = 12000;
const MAX_VISIBLE_ROUTES = 3;

let globalRoutes = [];
window.globalRoutes = [];
window.activeRouteIndex = null;

$(document).ready(() => {
    const params = new URLSearchParams(window.location.search);
    const fromLat = params.get('fromLat');
    const fromLon = params.get('fromLon');
    const toLat = params.get('toLat');
    const toLon = params.get('toLon');
    const departureTime = params.get('departureTime');
    const travelDate = params.get('travelDate');

    bindRouteEvents();

    if (fromLat && fromLon && toLat && toLon) {
        loadRoutes(fromLat, fromLon, toLat, toLon, departureTime, travelDate);
        return;
    }

    const origin = params.get('origin');
    const dest = params.get('dest');
    if (origin && dest) {
        loadRoutesByName(origin, dest, departureTime, travelDate);
        return;
    }

    showRoutesError('Parâmetros inválidos para carregar resultados.');
});

function bindRouteEvents() {
    $(document)
        .off('click.urbanRoutes keydown.urbanRoutes', '.route-card')
        .on('click.urbanRoutes', '.route-card', function(event) {
            if ($(event.target).closest('.favorite-btn, .route-start-btn').length) return;
            selectRoute(Number($(this).data('route-index')));
        })
        .on('keydown.urbanRoutes', '.route-card', function(event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            selectRoute(Number($(this).data('route-index')));
        });

    $(document)
        .off('click.urbanStartRoute', '.route-start-btn')
        .on('click.urbanStartRoute', '.route-start-btn', function(event) {
            event.preventDefault();
            event.stopPropagation();
            const routeIndex = Number($(this).data('route-index'));
            selectRoute(routeIndex);
            if (typeof window.showTripSummary === 'function') {
                window.showTripSummary();
            }
        });

    $(document)
        .off('click.urbanFavorite', '.favorite-btn')
        .on('click.urbanFavorite', '.favorite-btn', function(event) {
            event.stopPropagation();
        });
}

async function loadRoutes(fromLat, fromLon, toLat, toLon, departureTime = null, travelDate = null) {
    $("#routesList").html(buildRoutesLoadingHtml());

    try {
        const timeParam = departureTime ? `&departureTime=${encodeURIComponent(departureTime)}` : '';
        const dateParam = travelDate ? `&travelDate=${encodeURIComponent(travelDate)}` : '';
        const data = await fetchJsonWithTimeout(`/urban/public/api/routes?fromLat=${fromLat}&fromLon=${fromLon}&toLat=${toLat}&toLon=${toLon}${timeParam}${dateParam}`);
        if (data.status !== 'success') throw new Error(data.message || 'Não foi possível carregar as rotas.');
        processRoutes(data);
        if (data.origin) $('#originDisplay').text(data.origin.stop_name);
        if (data.destination) $('#destDisplay').text(data.destination.stop_name);
    } catch (error) {
        console.warn(error);
        showRoutesError(error.message || 'Não foi possível carregar rotas agora.');
    }
}

async function loadRoutesByName(origin, dest, departureTime = null, travelDate = null) {
    $("#routesList").html(buildRoutesLoadingHtml());

    try {
        const timeParam = departureTime ? `&departureTime=${encodeURIComponent(departureTime)}` : '';
        const dateParam = travelDate ? `&travelDate=${encodeURIComponent(travelDate)}` : '';
        const data = await fetchJsonWithTimeout(`/urban/public/api/routes?origin=${encodeURIComponent(origin)}&dest=${encodeURIComponent(dest)}${timeParam}${dateParam}`);
        if (data.status !== 'success') throw new Error(data.message || 'Não foi possível carregar as rotas.');
        processRoutes(data);
        $('#originDisplay').text(origin);
        $('#destDisplay').text(dest);
    } catch (error) {
        console.warn(error);
        showRoutesError(error.message || 'Não foi possível carregar rotas agora.');
    }
}

function processRoutes(data) {
    if (!data || !Array.isArray(data.routes) || data.routes.length === 0) {
        showRoutesError(data?.message || 'Nenhuma rota encontrada para este percurso.');
        return;
    }

    const normalizedRoutes = data.routes.map((route, index) => normalizeRoute(route, data, index));
    const visibleRoutes = buildVisibleAlternatives(normalizedRoutes);

    globalRoutes = visibleRoutes;
    window.globalRoutes = visibleRoutes;
    window.activeRouteIndex = null;

    renderWalkInfo(data.walk_info);
    displayRoutes(visibleRoutes);

    if (visibleRoutes.length > 0) {
        if (window.authManager) {
            window.authManager.addToHistory(visibleRoutes[0]);
        }
    }
}

function normalizeRoute(route, data, index) {
    const segments = Array.isArray(route.segments) ? route.segments : [];
    const lineSequence = buildLineSequence(route, segments);
    const primaryLine = route.line || route.route_name || lineSequence[0] || 'Linha';

    return {
        id: route.id || index + 1,
        type: route.type === 'direct' || route.type === 'Direto' ? 'Direto' : 'Com transbordo',
        line: primaryLine,
        line_sequence: lineSequence,
        route_id: route.route_id || null,
        route_name: route.route_name || primaryLine,
        primary_trip_id: route.primary_trip_id || route._primary_trip_id || route.trip_id || null,
        trip_id: route.trip_id || null,
        from: data.origin?.stop_name || route.origin_name || 'Origem',
        to: data.destination?.stop_name || route.destination_name || 'Destino',
        origin_name: data.origin?.stop_name || route.origin_name || 'Origem',
        destination_name: data.destination?.stop_name || route.destination_name || 'Destino',
        origin_stop_id: route.origin_stop_id || data.origin?.stop_id || null,
        destination_stop_id: route.destination_stop_id || data.destination?.stop_id || null,
        origin_lat: Number(data.origin?.stop_lat || route.origin_lat || window.selectedOriginCoords?.lat || 0),
        origin_lon: Number(data.origin?.stop_lon || route.origin_lon || window.selectedOriginCoords?.lon || 0),
        destination_lat: Number(data.destination?.stop_lat || route.destination_lat || window.selectedDestinationCoords?.lat || 0),
        destination_lon: Number(data.destination?.stop_lon || route.destination_lon || window.selectedDestinationCoords?.lon || 0),
        duration: Number(route.duration || route.total_time || 0) || 0,
        bus_duration: Number(route.bus_duration || 0) || 0,
        duration_text: route.duration_text || null,
        departure_time: route.departure_time || '--:--',
        arrival_time: route.arrival_time || '--:--',
        total_time: Number(route.total_time || route.duration || 0) || 0,
        stop_count: Number(route.stop_count || route.stops || 0) || 0,
        stops: Number(route.stop_count || route.stops || 0) || 0,
        transfers: Number(route.transfers || 0) || 0,
        nextBus: route.nextBus || 'A calcular...',
        delay: Number(route.delay || 0) || 0,
        delay_text: route.delay_text || 'A horas',
        badge: route.badge || null,
        coordinates: route.coordinates || [],
        bounds: route.bounds || null,
        stopsData: Array.isArray(route.stopsData) ? route.stopsData : [],
        segments,
        geometry_stats: route.geometry_stats || null,
        transfer_stop: route.transfer_stop || null,
        wait_time: Number(route.wait_time || 0) || 0,
        walk_from: route.walk_from || null,
        walk_to: route.walk_to || null,
        total_walk_time: Number(route.total_walk_time || 0) || 0,
        total_walk_time_text: route.total_walk_time_text || null,
        is_live: Boolean(route.primary_trip_id || route.trip_id),
        alternative_key: buildAlternativeKey(route, data, lineSequence)
    };
}

function buildVisibleAlternatives(routes) {
    const uniqueRoutes = [];
    routes.forEach((route) => {
        if (!uniqueRoutes.some((existing) => routesAreEquivalent(existing, route))) {
            uniqueRoutes.push(route);
        }
    });

    const fallbackBadges = ['Recomendada', 'Mais rápida', 'Alternativa'];
    return uniqueRoutes.slice(0, MAX_VISIBLE_ROUTES).map((route, index) => ({
        ...route,
        badge: route.badge || fallbackBadges[index] || 'Alternativa'
    }));
}

function displayRoutes(routes) {
    if (!Array.isArray(routes) || routes.length === 0) {
        showRoutesError('Nenhuma rota encontrada para este percurso.');
        return;
    }

    let html = '';

    routes.forEach((route, index) => {
        const walkTime = route.total_walk_time || 0;
        const badgeClass = index === 0 ? 'ut-badge-primary' : 'ut-badge-live';
        const realtimeClass = route.delay > 0 ? 'text-warning' : 'text-success';
        const linesHtml = route.line_sequence.length > 0
            ? route.line_sequence.map((line) => `<span class="route-line-chip"><i class="fas fa-bus"></i>${escapeHtml(line)}</span>`).join('')
            : `<span class="route-line-chip"><i class="fas fa-bus"></i>${escapeHtml(route.line)}</span>`;
        const geometryHint = buildGeometryHint(route);
        const sequenceHtml = buildRouteSequenceHtml(route);

        html += `
            <div class="route-card" data-route-index="${index}" role="button" tabindex="0">
                <div class="route-headline">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="ut-badge ${badgeClass}">${escapeHtml(route.badge || 'Alternativa')}</span>
                        ${route.is_live
                            ? `<span class="small fw-semibold ${realtimeClass}" data-route-delay="${index}"><i class="fas fa-signal me-1"></i>${escapeHtml(route.delay_text)}</span>`
                            : `<span class="small text-muted" data-route-delay="${index}">Sem realtime</span>`}
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold lh-1">${route.duration} min</div>
                        <small class="text-muted">${escapeHtml(route.departure_time)} → ${escapeHtml(route.arrival_time)}</small>
                    </div>
                </div>

                <div class="mt-3 mb-2 d-flex flex-wrap gap-2">
                    ${linesHtml}
                </div>

                <div class="small d-flex flex-wrap gap-3 text-muted mb-3">
                    <span><i class="fas fa-person-walking me-1"></i>${walkTime} min a pé</span>
                    <span><i class="fas fa-signs-post me-1"></i>${route.stop_count} paragens</span>
                    <span><i class="fas fa-right-left me-1"></i>${route.transfers} transbordos</span>
                </div>

                ${geometryHint ? `<div class="small text-muted mb-2">${geometryHint}</div>` : ''}

                <div class="small mb-3">
                    <div><i class="fas fa-circle text-success me-2" style="font-size: 8px;"></i>${escapeHtml(route.from)}</div>
                    <div class="text-muted my-1"><i class="fas fa-arrow-down ms-1 me-2" style="font-size: 10px;"></i>${escapeHtml(route.type)}</div>
                    <div><i class="fas fa-circle text-danger me-2" style="font-size: 8px;"></i>${escapeHtml(route.to)}</div>
                </div>

                <div class="route-card__sequence">
                    ${sequenceHtml}
                </div>

                <div class="d-flex justify-content-between align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-danger favorite-btn" data-route="${escapeAttribute(JSON.stringify(route))}" aria-label="Guardar favorito">
                        <i class="far fa-heart"></i>
                    </button>
                    <div class="small text-muted flex-grow-1 text-center">Próximo: <span data-route-next="${index}">${escapeHtml(route.nextBus)}</span></div>
                    <button class="btn-start-route route-start-btn" type="button" data-route-index="${index}">
                        <i class="fas fa-play me-2"></i>Iniciar trajeto
                    </button>
                </div>

                <div class="route-card__journey-state" data-journey-state="${index}" hidden></div>
            </div>
        `;
    });

    $('#routesList').html(html);
    $('#routeCount').text(routes.length);
}

function selectRoute(index) {
    const route = globalRoutes[index];
    if (!route) return;

    if (typeof window.handleRouteSelectionChange === 'function') {
        window.handleRouteSelectionChange(index);
    }

    $('.route-card').removeClass('active');
    $('.route-card').eq(index).addClass('active');
    window.activeRouteIndex = index;

    try {
        if (typeof window.mapboxDrawRoute === 'function') {
            window.mapboxDrawRoute(route);
        }
    } catch (error) {
        console.warn('Erro ao desenhar rota no mapa:', error);
        window.pendingRouteIndex = index;
    }

    updateSelectedRouteInfo(route);
    buildRouteSteps(route);

    if (typeof window.syncInlineJourneyState === 'function') {
        window.syncInlineJourneyState(index);
    }
}

function updateSelectedRouteInfo(route) {
    $('#selectedRouteInfo').show();
    $('#selectedLine').text(route.line || '-');
    $('#arrivalTime').text(route.arrival_time || '--:--');

    if (route.stopsData && route.stopsData.length > 0) {
        const firstStopId = route.stopsData[0].stop_id;
        const primaryTripId = route.primary_trip_id || route.trip_id;

        if (firstStopId && primaryTripId) {
            fetchRealtimeETA(primaryTripId, firstStopId);
            return;
        }
    }

    $('#nextBus').text(route.nextBus || 'Indisponível');
}

function buildRouteSteps(route) {
    window.routeSteps = [];

    if (!Array.isArray(route.segments) || route.segments.length === 0) return;

    route.segments.forEach((seg) => {
        window.routeSteps.push({
            description: `${seg.from_name || seg.from?.name || seg.from || ''} → ${seg.to_name || seg.to?.name || seg.to || ''}`,
            distance: seg.type === 'bus' ? `${seg.stops || '?'} paragens` : (seg.type === 'walk' ? 'Caminhada' : 'Transbordo'),
            time: seg.duration_minutes || seg.time || 0,
            line: seg.line || null,
            wait: seg.wait || null
        });
    });
}

function buildGeometryHint(route) {
    const stats = route.geometry_stats || {};
    if (Number(stats.fallback_straight_line || 0) > 0) {
        return '<i class="fas fa-route me-1"></i>Parte do trajeto usa geometria aproximada.';
    }
    if (Number(stats.fallback_stop_to_stop || 0) > 0) {
        return '<i class="fas fa-map-signs me-1"></i>Alguns segmentos seguem apenas a sequência de paragens.';
    }
    return '';
}

function buildRouteSequenceHtml(route) {
    if (!Array.isArray(route.segments) || route.segments.length === 0) {
        return '';
    }

    const items = route.segments.map((segment) => {
        if (segment.type === 'walk') {
            return `Caminhar até ${escapeHtml(segment.to_name || segment.to?.name || 'a próxima paragem')}`;
        }
        if (segment.type === 'transfer') {
            const waitText = Number(segment.wait || 0) > 0 ? ` e esperar ${Number(segment.wait)} min` : '';
            return `Transbordo em ${escapeHtml(segment.to_name || segment.from_name || 'paragem')}${waitText}`;
        }
        const line = escapeHtml(segment.line || 'linha');
        const board = escapeHtml(segment.from_name || segment.from?.name || 'paragem');
        const alight = escapeHtml(segment.to_name || segment.to?.name || 'paragem');
        return `Apanhar ${line} em ${board} e sair em ${alight}`;
    });

    return `
        <div class="small mb-3">
            ${items.map((item, index) => `<div class="text-muted mb-1"><strong>${index + 1}.</strong> ${item}</div>`).join('')}
        </div>
    `;
}

function renderWalkInfo(walkInfo) {
    if (!walkInfo) {
        $('#walkInfo').hide().empty();
        return;
    }

    const pills = [];
    if (walkInfo.from?.distance_text && walkInfo.from.distance_text !== '0 m') {
        pills.push(`<span class="badge bg-info me-2">🚶 ${escapeHtml(walkInfo.from.distance_text)} a pé</span>`);
    }
    if (walkInfo.to?.distance_text && walkInfo.to.distance_text !== '0 m') {
        pills.push(`<span class="badge bg-info">🚶 ${escapeHtml(walkInfo.to.distance_text)} a pé</span>`);
    }

    if (pills.length > 0) {
        $('#walkInfo').html(pills.join('')).show();
    } else {
        $('#walkInfo').hide().empty();
    }
}

function showRoutesError(message) {
    globalRoutes = [];
    window.globalRoutes = [];
    window.activeRouteIndex = null;
    $('#routeCount').text('0');
    $('#selectedRouteInfo').hide();
    $('#walkInfo').hide().empty();
    $('#routesList').html(`
        <div class="ut-empty-state">
            <i class="fas fa-route"></i>
            <h6 class="mb-2">Não foi possível apresentar rotas</h6>
            <p class="text-muted mb-0">${escapeHtml(message)}</p>
        </div>
    `);
}

function buildRoutesLoadingHtml() {
    return `
        <div class="ut-loading">
            <div class="spinner-border text-success" role="status" aria-hidden="true"></div>
            <p class="text-muted mb-0">A calcular melhores rotas...</p>
        </div>
    `;
}

function buildLineSequence(route, segments) {
    if (Array.isArray(route.line_sequence) && route.line_sequence.length > 0) {
        return route.line_sequence.filter(Boolean).map((line) => String(line).trim());
    }

    return [...new Set((segments || [])
        .filter((segment) => segment?.type === 'bus' && segment?.line)
        .map((segment) => String(segment.line).trim()))];
}

function buildAlternativeKey(route, data, lineSequence) {
    return [
        lineSequence.join('>'),
        route.primary_trip_id || route.trip_id || 'no-trip',
        route.origin_stop_id || data.origin?.stop_id || 'origin',
        route.destination_stop_id || data.destination?.stop_id || 'destination',
        Number(route.transfers || 0),
        Math.round((Number(route.duration || route.total_time || 0) || 0) / 2)
    ].join('|');
}

function routesAreEquivalent(left, right) {
    if (!left || !right) return false;

    const sameLines = (left.line_sequence || []).join('>') === (right.line_sequence || []).join('>');
    const sameTrip = String(left.primary_trip_id || left.trip_id || '') === String(right.primary_trip_id || right.trip_id || '');
    const sameOrigin = String(left.origin_stop_id || left.from || '') === String(right.origin_stop_id || right.from || '');
    const sameDestination = String(left.destination_stop_id || left.to || '') === String(right.destination_stop_id || right.to || '');
    const sameTransfers = Number(left.transfers || 0) === Number(right.transfers || 0);
    const similarDuration = Math.abs((Number(left.duration || 0) || 0) - (Number(right.duration || 0) || 0)) <= 3;

    return sameLines && sameTrip && sameOrigin && sameDestination && sameTransfers && similarDuration;
}

function fetchJsonWithTimeout(url, timeoutMs = ROUTES_FETCH_TIMEOUT_MS) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);

    return fetch(url, { signal: controller.signal })
        .then(async (response) => {
            const text = await response.text();
            let data = null;

            try {
                data = text ? JSON.parse(text) : null;
            } catch (error) {
                throw new Error('Resposta inválida do servidor.');
            } finally {
                window.clearTimeout(timer);
            }

            if (!response.ok) {
                throw new Error(buildRouteErrorMessage(data, response.status));
            }

            if (data?.status !== 'success') {
                throw new Error(buildRouteErrorMessage(data, response.status));
            }

            return data;
        })
        .catch((error) => {
            window.clearTimeout(timer);
            if (error.name === 'AbortError') {
                throw new Error('A pesquisa demorou demasiado tempo. Tente novamente.');
            }
            throw error;
        });
}

function buildRouteErrorMessage(data, statusCode = 0) {
    const errorCode = data?.error_code || '';
    if (errorCode === 'timeout') {
        return 'O cálculo da rota demorou demasiado tempo. Tente novamente.';
    }
    if (errorCode === 'no_route') {
        return data?.message || 'Não existe rota disponível para esta data e horário.';
    }
    if (errorCode === 'invalid_params') {
        return data?.message || 'Parâmetros inválidos para calcular a rota.';
    }
    if (errorCode === 'internal_error') {
        return 'Ocorreu um erro interno ao calcular a rota.';
    }
    return data?.message || (statusCode ? `Erro HTTP ${statusCode}` : 'Não foi possível carregar as rotas.');
}

async function fetchRealtimeETA(tripId, stopId) {
    try {
        const response = await fetch(`/urban/public/api/realtime?action=trip_eta&trip_id=${encodeURIComponent(tripId)}&stop_id=${encodeURIComponent(stopId)}`);
        const data = await response.json();

        if (data.status === 'success' && data.available === false) {
            const fallbackMessage = data.message || 'Sem veículo realtime disponível para esta viagem';
            $('#nextBus').text(fallbackMessage);
            $(`[data-route-next="${Number(window.activeRouteIndex || 0)}"]`).text(fallbackMessage);
            return null;
        }

        if (data.status === 'success' && data.data) {
            const eta = data.data;
            const etaText = eta.eta_text || 'A calcular...';
            const isRealtime = Boolean(eta.is_realtime);
            const source = eta.source || data.source || eta.data_source || (isRealtime ? 'realtime' : 'estimated');
            const sourceText = source === 'realtime'
                ? 'AO VIVO'
                : (source === 'estimated' ? 'Estimado' : (source === 'simulated' ? 'Simulado' : 'Fallback'));
            const routeIndex = Number(window.activeRouteIndex || 0);
            const route = globalRoutes[routeIndex];
            const delayText = eta.delay_text || (isRealtime ? 'Ao vivo' : sourceText);

            $('#nextBus').html(`
                ${escapeHtml(etaText)}
                <small class="${isRealtime ? 'text-success' : 'text-muted'} ms-1">${escapeHtml(sourceText)}</small>
            `);

            $(`[data-route-next="${routeIndex}"]`).text(etaText);
            const delayElement = $(`[data-route-delay="${routeIndex}"]`);
            delayElement
                .removeClass('text-success text-warning text-danger text-muted')
                .addClass(isRealtime ? (Number(eta.delay || 0) > 180 ? 'text-warning' : 'text-success') : 'text-muted')
                .html(`<i class="fas fa-signal me-1"></i>${escapeHtml(delayText)}`);

            if (route) {
                route.nextBus = etaText;
                route.delay = Number(eta.delay || 0) || 0;
                route.delay_text = delayText;
                route.is_live = isRealtime;
            }

            $('#nextBus').removeClass('text-success text-warning fw-bold');
            if (isRealtime && Number(eta.eta_minutes || 0) <= 5) {
                $('#nextBus').addClass('text-success fw-bold');
            } else if (isRealtime && Number(eta.eta_minutes || 0) <= 10) {
                $('#nextBus').addClass('text-warning fw-bold');
            }
            return eta;
        }
    } catch (error) {
        console.warn('Erro ao buscar ETA:', error);
    }

    $('#nextBus').text('Indisponível');
    $(`[data-route-next="${Number(window.activeRouteIndex || 0)}"]`).text('Indisponível');
    return null;
}

function startRealtimeETAUpdates() {
    window.setInterval(() => {
        const route = globalRoutes[window.activeRouteIndex];
        if (!route || !route.stopsData || route.stopsData.length === 0) return;

        const firstStopId = route.stopsData[0].stop_id;
        const primaryTripId = route.primary_trip_id || route.trip_id;
        if (firstStopId && primaryTripId) {
            fetchRealtimeETA(primaryTripId, firstStopId);
        }
    }, window.UrbanPreferences?.getRealtimeRefreshMs?.() || 10000);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char]);
}

function escapeAttribute(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

window.loadRoutes = loadRoutes;
window.loadRoutesByName = loadRoutesByName;
window.selectRoute = selectRoute;
window.fetchRealtimeETA = fetchRealtimeETA;

window.setTimeout(startRealtimeETAUpdates, 2000);
