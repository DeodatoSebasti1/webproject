// /urban/public/js/routes.js

let globalRoutes = [];
window.currentPolyline = null;
window.stopMarkers = [];

$(document).ready(function () {
    console.log("routes.js carregado");
    const params = new URLSearchParams(window.location.search);
    const fromLat = params.get('fromLat');
    const fromLon = params.get('fromLon');
    const toLat = params.get('toLat');
    const toLon = params.get('toLon');

    if (!fromLat || !fromLon || !toLat || !toLon) {
        const origin = params.get('origin');
        const dest = params.get('dest');
        if (origin && dest) loadRoutesByName(origin, dest);
        else alert("Parâmetros inválidos");
        return;
    }
    loadRoutes(fromLat, fromLon, toLat, toLon);
});

async function loadRoutes(fromLat, fromLon, toLat, toLon) {
    $("#routesList").html('<div class="text-center p-4"><div class="spinner-border text-success"></div><p class="mt-2">A calcular melhor rota...</p></div>');
    try {
        const res = await fetch(`/urban/app/controllers/RouteController.php?fromLat=${fromLat}&fromLon=${fromLon}&toLat=${toLat}&toLon=${toLon}`);
        const data = await res.json();
        if (data.status !== "success") throw new Error(data.message);
        processRoutes(data);
        if (data.origin) $('#originDisplay').text(data.origin.stop_name);
        if (data.destination) $('#destDisplay').text(data.destination.stop_name);
    } catch (e) {
        console.error(e);
        $("#routesList").html(`<p class="text-danger">Erro ao carregar rotas: ${e.message}</p>`);
    }
}

async function loadRoutesByName(origin, dest) {
    $("#routesList").html('<div class="text-center p-4"><div class="spinner-border text-success"></div><p class="mt-2">A calcular melhor rota...</p></div>');
    try {
        const res = await fetch(`/urban/app/controllers/RouteController.php?origin=${encodeURIComponent(origin)}&dest=${encodeURIComponent(dest)}`);
        const data = await res.json();
        if (data.status !== "success") throw new Error(data.message);
        processRoutes(data);
        $('#originDisplay').text(origin);
        $('#destDisplay').text(dest);
    } catch (e) {
        console.error(e);
        $("#routesList").html(`<p class="text-danger">Erro ao carregar rotas: ${e.message}</p>`);
    }
}

function processRoutes(data) {
    const routes = data.routes.map((route, index) => ({
        id: route.id || index + 1,
        type: route.type === 'direct' ? "Direto" : "Com transbordo",
        line: route.line || route.route_name,
        route_name: route.route_name,
        from: data.origin?.stop_name || 'Origem',
        to: data.destination?.stop_name || 'Destino',
        duration: route.duration,
        bus_duration: route.bus_duration,
        duration_text: route.duration_text,
        total_time: route.total_time || route.duration,
        stops: route.stop_count,
        stop_count: route.stop_count,
        nextBus: route.nextBus || "5 min",
        badge: route.badge,
        coordinates: route.coordinates,
        stopsData: route.stopsData,
        segments: route.segments,
        transfers: route.transfers || 0,
        wait_time: route.wait_time,
        transfer_stop: route.transfer_stop,
        walk_from: route.walk_from,
        walk_to: route.walk_to,
        total_walk_time: route.total_walk_time,
        total_walk_time_text: route.total_walk_time_text
    }));

    if (data.walk_info) {
        let walkHtml = '';
        if (data.walk_info.from.distance_text !== '0 m')
            walkHtml += `<span class="badge bg-info me-2">🚶 ${data.walk_info.from.distance_text} a pé</span>`;
        if (data.walk_info.to.distance_text !== '0 m')
            walkHtml += `<span class="badge bg-info">🚶 ${data.walk_info.to.distance_text} a pé</span>`;
        if (walkHtml) $('#walkInfo').html(walkHtml).show();
    }

    displayRoutes(routes);
    if (routes.length > 0) selectRoute(0);
}

function displayRoutes(routes) {
    globalRoutes = routes;
    let html = "";

    routes.forEach((route, index) => {
        const badgeClass = route.badge?.includes("rápida") ? "bg-success" : (route.badge?.includes("transbordos") ? "bg-info" : "bg-warning");
        const walkTime = route.total_walk_time || 0;
        
        let walkText = '';
        if (route.walk_from && route.walk_from.distance > 0)
            walkText += `<div class="small text-muted mt-1"><i class="fas fa-walking me-1"></i> Caminhar ${route.walk_from.distance_text} até ${route.walk_from.stop_name}</div>`;
        if (route.walk_to && route.walk_to.distance > 0)
            walkText += `<div class="small text-muted"><i class="fas fa-walking me-1"></i> Caminhar ${route.walk_to.distance_text} do destino</div>`;

        let segmentsHtml = '';
        if (route.segments && route.segments.length > 0) {
            segmentsHtml = `<div class="segments-details mt-2"><div class="fw-bold small mb-1">📋 Percurso detalhado:</div>`;
            route.segments.forEach(seg => {
                segmentsHtml += `<div class="segment-item small mb-1">
                    <i class="fas fa-bus me-1"></i> <strong>${seg.line || 'Autocarro'}</strong> — ${seg.from} → ${seg.to}
                    <span class="text-muted ms-2">⏱️ ${seg.time} min</span>
                    ${seg.stops ? `<span class="text-muted ms-2">🚏 ${seg.stops} paragens</span>` : ''}
                    ${seg.wait ? `<span class="badge bg-warning ms-2">⏳ espera ${seg.wait} min</span>` : ''}
                </div>`;
            });
            segmentsHtml += `</div>`;
        }

        html += `
        <div class="route-card ${index === 0 ? 'active' : ''}" onclick='selectRoute(${index})'>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge ${badgeClass}" style="background: #4CAF50; color: white;">${route.badge || (index === 0 ? '⚡ Mais rápida' : '🔄 Alternativa')}</span>
                    <strong>${route.duration} min</strong>
                </div>
                <div class="mb-2">
                    <span class="fw-bold">${route.line}</span>
                    <small class="text-muted ms-2">🚏 ${route.stop_count} paragens</small>
                    ${walkTime > 0 ? `<small class="text-muted ms-2">🚶 +${walkTime} min a pé</small>` : ''}
                    ${route.transfers > 0 ? `<span class="badge bg-secondary ms-2">🔄 ${route.transfers} transbordo</span>` : ''}
                </div>
                ${walkText}
                <div class="small mt-2">
                    <i class="fas fa-circle text-success me-2" style="font-size: 8px;"></i>${route.from}<br>
                    <i class="fas fa-arrow-down text-muted ms-1 me-2" style="font-size: 10px;"></i>${route.type}<br>
                    <i class="fas fa-circle text-danger me-2" style="font-size: 8px;"></i>${route.to}
                </div>
                ${route.transfer_stop ? `
                <hr class="my-2">
                <div class="small text-muted">
                    <i class="fas fa-exchange-alt me-1"></i> Transbordo em: ${route.transfer_stop}
                    ${route.wait_time ? `(espera: ${route.wait_time} min)` : ''}
                </div>
                ` : ''}
                ${segmentsHtml}
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <small><i class="far fa-clock me-1"></i>Próximo: ${route.nextBus}</small>
                    <span class="badge bg-light text-dark">${route.type}</span>
                </div>
            </div>
        </div>`;
    });

    $("#routesList").html(html);
    $("#routeCount").text(routes.length);
}

function selectRoute(index) {
    const route = globalRoutes[index];
    if (!route) return;
    console.log("Rota selecionada:", route);

    $('.route-card').removeClass('active');
    $('.route-card').eq(index).addClass('active');

    drawRouteOnMap(route);
    
    $('#selectedRouteInfo').show();
    $('#nextBus').text(route.nextBus || '5 min');
    $('#selectedLine').text(route.line);
    
    const now = new Date();
    const duration = parseInt(route.duration);
    const arrival = new Date(now.getTime() + duration * 60000);
    $('#arrivalTime').text(arrival.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }));
    
    if (route.segments && route.segments.length > 0) {
        window.routeSteps = [];
        route.segments.forEach((seg, idx) => {
            window.routeSteps.push({
                description: `${seg.from} → ${seg.to}`,
                distance: `${seg.stops || '?'} paragens`,
                time: seg.time,
                line: seg.line,
                wait: seg.wait
            });
        });
    }
    $('#startRouteBtn').prop('disabled', false);
}

function drawRouteOnMap(route) {
    if (typeof map === 'undefined' || !map) { console.warn("Mapa não inicializado"); return; }
    if (!route.coordinates || route.coordinates.length === 0) { console.warn("Sem coordenadas para desenhar"); return; }

    if (window.currentPolyline) map.removeLayer(window.currentPolyline);
    if (window.stopMarkers) window.stopMarkers.forEach(m => map.removeLayer(m));
    window.stopMarkers = [];

    window.currentPolyline = L.polyline(route.coordinates, { color: "#4CAF50", weight: 6, opacity: 0.8 }).addTo(map);
    
    if (typeof L.PolylineDecorator !== 'undefined') {
        L.polylineDecorator(window.currentPolyline, {
            patterns: [{ offset: '10%', repeat: '15%', symbol: L.Symbol.arrowHead({ pixelSize: 10, polygon: false, pathOptions: { stroke: true, color: "#4CAF50" } }) }]
        }).addTo(map);
    }

    try { map.fitBounds(window.currentPolyline.getBounds(), { padding: [50, 50] }); } catch(e) { console.warn(e); }

    if (route.stopsData && route.stopsData.length > 0) {
        route.stopsData.forEach(stop => {
            const lat = parseFloat(stop.stop_lat);
            const lon = parseFloat(stop.stop_lon);
            if (isNaN(lat) || isNaN(lon)) return;
            const marker = L.circleMarker([lat, lon], { radius: 5, color: '#4CAF50', fillOpacity: 0.8, weight: 2, fillColor: '#4CAF50' })
                .addTo(map).bindPopup(`<b>${stop.stop_name}</b><br><small>Paragem GTFS</small>`);
            window.stopMarkers.push(marker);
        });
        console.log(`✅ Desenhados ${route.stopsData.length} stops`);
    }
}

window.loadRoutes = loadRoutes;
window.selectRoute = selectRoute;
window.drawRouteOnMap = drawRouteOnMap;