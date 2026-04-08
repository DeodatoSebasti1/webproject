<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Resultados</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet-polylinedecorator@1.6.0/dist/leaflet-polylinedecorator.js"></script>
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        #map { height: 600px; width: 100%; border-radius: 10px; z-index: 1; background: #e8ecf1; }
        .results-layout { display: grid; grid-template-columns: 350px 1fr; gap: 20px; margin-top: 20px; }
        .routes-panel { max-height: 600px; overflow-y: auto; padding-right: 10px; }
        .route-card { cursor: pointer; transition: all 0.3s ease; border-left: 4px solid transparent; background: white; border-radius: 10px; margin-bottom: 15px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .route-card:hover { transform: translateX(-5px); box-shadow: 0 5px 20px rgba(76, 175, 80, 0.2); }
        .route-card.active { border-left-color: #4CAF50; background-color: #f1f9f1; }
        .btn-start-route { background: #4CAF50; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; width: 100%; }
        .btn-start-route:hover { background: #45a049; transform: translateY(-2px); }
        .btn-start-route:disabled { background: #cccccc; cursor: not-allowed; }
        .navigation-panel { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 600px; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 1000; display: none; padding: 20px; border-left: 5px solid #4CAF50; }
        .step-indicator { display: flex; align-items: center; margin-bottom: 15px; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #4CAF50; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .navigation-progress { height: 5px; background: #e0e0e0; border-radius: 5px; margin: 15px 0; }
        .progress-bar-green { height: 100%; background: #4CAF50; border-radius: 5px; width: 0%; transition: width 0.3s ease; }
        .navigation-buttons { display: flex; gap: 10px; margin-top: 15px; }
        .btn-nav { flex: 1; padding: 10px; border: none; border-radius: 5px; font-weight: 500; }
        .btn-prev { background: #e0e0e0; }
        .btn-next { background: #4CAF50; color: white; }
        .btn-exit { background: #f44336; color: white; }
        .segment-item { padding: 4px 0; border-bottom: 1px dashed #eee; }
        .segment-item:last-child { border-bottom: none; }
        .segments-details { background: #f8f9fa; border-radius: 6px; padding: 8px; margin-top: 8px; }
        .bus-marker { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.2); opacity: 0.7; } 100% { transform: scale(1); opacity: 1; } }
        @media (max-width: 768px) { .results-layout { grid-template-columns: 1fr; } .routes-panel { max-height: 300px; } .navigation-panel { width: 95%; padding: 15px; } }
    </style>
</head>
<body>

    <?php include 'partials/navbar.php'; ?>
    
    <section class="py-3" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center text-white">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-route me-2"></i>
                        <span id="originDisplay">-</span> → <span id="destDisplay">-</span>
                    </h5>
                    <div id="walkInfo" class="small mt-1" style="display: none;"></div>
                </div>
                <button class="btn btn-light btn-sm" onclick="window.location.href='index.html'">
                    <i class="fas fa-arrow-left me-2"></i>Nova Pesquisa
                </button>
            </div>
        </div>
    </section>

    <section class="container my-4">
        <div class="results-layout">
            <div class="routes-panel">
                <h5 class="mb-3">
                    <i class="fas fa-route me-2" style="color: #4CAF50;"></i>
                    Percursos sugeridos
                    <span class="badge ms-2" style="background: #4CAF50; color: white;" id="routeCount">0</span>
                </h5>
                <div id="routesList">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3" style="color: #4CAF50;"></i>
                        <p>A calcular melhores rotas...</p>
                    </div>
                </div>
                <button class="btn-start-route mt-3" id="startRouteBtn" onclick="startNavigation()" disabled>
                    <i class="fas fa-play me-2"></i>Iniciar Trajeto
                </button>
                <div class="card mt-3 border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <i class="fas fa-clock me-2" style="color: #4CAF50;"></i>Tempo Real
                        <span class="badge bg-success ms-2">AO VIVO</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Autocarros ativos:</span>
                            <span class="fw-bold" id="activeBuses">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Última atualização:</span>
                            <span class="fw-bold" id="lastUpdate">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div id="map"></div>
                <div class="card mt-3 border-0 shadow-sm" id="selectedRouteInfo" style="display: none;">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-4"><small class="text-muted d-block">Próximo</small><strong id="nextBus">-</strong></div>
                            <div class="col-4"><small class="text-muted d-block">Chegada</small><strong id="arrivalTime">-</strong></div>
                            <div class="col-4"><small class="text-muted d-block">Linha</small><strong id="selectedLine">-</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="navigation-panel" id="navigationPanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="fas fa-bus me-2" style="color: #4CAF50;"></i>Trajeto em Andamento</h6>
            <span class="badge" style="background: #4CAF50; color: white;" id="currentStep">Passo 1/5</span>
        </div>
        <div class="step-indicator">
            <div class="step-circle" id="stepNumber">1</div>
            <div class="step-description">
                <strong id="stepTitle">A caminho da paragem</strong>
                <p class="mb-0 text-muted small" id="stepDetail">-</p>
            </div>
        </div>
        <div class="navigation-progress"><div class="progress-bar-green" id="progressBar"></div></div>
        <div class="d-flex justify-content-between text-muted small mb-3">
            <span id="timeRemaining">- min restantes</span>
            <span id="distanceRemaining">- km</span>
        </div>
        <div class="navigation-buttons">
            <button class="btn-nav btn-prev" onclick="previousStep()" disabled><i class="fas fa-chevron-left me-2"></i>Anterior</button>
            <button class="btn-nav btn-next" onclick="nextStep()">Próximo<i class="fas fa-chevron-right ms-2"></i></button>
            <button class="btn-nav btn-exit" onclick="exitNavigation()"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <small class="text-white-50">Dados em tempo real da Carris Metropolitana</small>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
    let map;
    let vehicleMarkers = {};
    let allLayers = [];
    
    function initMap(center = [38.7223, -9.1393], zoom = 13) {
        if (!document.getElementById('map')) return;
        map = L.map('map').setView(center, zoom);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: "© OpenStreetMap © CartoDB",
            maxZoom: 19
        }).addTo(map);
        return map;
    }
    
    function updateVehicles() {
        fetch('/urban/app/controllers/RealtimeController.php?action=vehicles')
            .then(res => res.json())
            .then(data => {
                Object.values(vehicleMarkers).forEach(marker => map.removeLayer(marker));
                vehicleMarkers = {};
                
                if (data.data && Array.isArray(data.data)) {
                    const validVehicles = data.data.filter(v => {
                        const lat = parseFloat(v.latitude);
                        const lon = parseFloat(v.longitude);
                        return !isNaN(lat) && !isNaN(lon) && lat !== null && lon !== null;
                    });
                    
                    validVehicles.forEach(vehicle => {
                        const icon = L.divIcon({
                            html: `<div style="background: #4CAF50; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>`,
                            iconSize: [20, 20],
                            className: 'bus-marker'
                        });
                        const marker = L.marker([parseFloat(vehicle.latitude), parseFloat(vehicle.longitude)], { icon }).addTo(map);
                        marker.bindPopup(`<b>Linha ${vehicle.route_id ?? 'N/A'}</b><br>Velocidade: ${vehicle.speed ?? 0} km/h`);
                        vehicleMarkers[vehicle.vehicle_id || Math.random()] = marker;
                    });
                    $('#activeBuses').text(Object.keys(vehicleMarkers).length);
                }
            })
            .catch(err => console.error('Erro ao buscar veículos:', err));
    }
    
    function drawRoute(coordinates, color = '#4CAF50', weight = 5) {
        clearRoutes();
        const polyline = L.polyline(coordinates, { color: color, weight: weight, opacity: 0.8, smoothFactor: 1 }).addTo(map);
        allLayers.push(polyline);
        if (typeof L.PolylineDecorator !== 'undefined') {
            L.polylineDecorator(polyline, {
                patterns: [{ offset: '10%', repeat: '15%', symbol: L.Symbol.arrowHead({ pixelSize: 10, polygon: false, pathOptions: { stroke: true, color: color } }) }]
            }).addTo(map);
        }
        try { map.fitBounds(polyline.getBounds(), { padding: [50, 50] }); } catch(e) { console.warn(e); }
    }
    
    function clearRoutes() {
        allLayers.forEach(layer => { if (map) map.removeLayer(layer); });
        allLayers = [];
    }
    
    function drawStops(stops) {
        if (!map || !stops) return;
        stops.forEach(stop => {
            const lat = parseFloat(stop.stop_lat);
            const lon = parseFloat(stop.stop_lon);
            if (isNaN(lat) || isNaN(lon)) return;
            const marker = L.circleMarker([lat, lon], { radius: 5, color: '#4CAF50', fillOpacity: 0.8, weight: 2, fillColor: '#4CAF50' })
                .addTo(map).bindPopup(`<b>${stop.stop_name}</b><br><small>Paragem GTFS</small>`);
            allLayers.push(marker);
        });
    }
    
    let navigationActive = false;
    let navigationInterval = null;
    let currentStep = 0;
    let routeSteps = [];
    
    function startNavigation() {
        if (!window.routeSteps || window.routeSteps.length === 0) { alert("Não há passos de navegação disponíveis"); return; }
        routeSteps = window.routeSteps;
        currentStep = 0;
        navigationActive = true;
        $('#navigationPanel').fadeIn(300);
        $('#startRouteBtn').prop('disabled', true);
        updateNavigationStep();
        startNavigationTimer();
    }
    
    function updateNavigationStep() {
        if (!routeSteps.length || currentStep < 0 || currentStep >= routeSteps.length) return;
        const step = routeSteps[currentStep];
        const totalSteps = routeSteps.length;
        $('#stepNumber').text(currentStep + 1);
        $('#currentStep').text(`Passo ${currentStep + 1}/${totalSteps}`);
        $('#stepTitle').text(step.line ? `Linha ${step.line}` : `Passo ${currentStep + 1}`);
        $('#stepDetail').text(`${step.description || `${step.from} → ${step.to}`}${step.wait ? ` (espera: ${step.wait} min)` : ''}`);
        const progress = ((currentStep + 1) / totalSteps) * 100;
        $('#progressBar').css('width', progress + '%');
        updateRemainingTime();
        $('#prevStepBtn').prop('disabled', currentStep === 0);
    }
    
    function updateRemainingTime() {
        let remainingTime = 0;
        for (let i = currentStep; i < routeSteps.length; i++) remainingTime += routeSteps[i].time || 0;
        $('#timeRemaining').text(`${remainingTime} min restantes`);
    }
    
    function nextStep() {
        if (currentStep < routeSteps.length - 1) { currentStep++; updateNavigationStep(); }
        else { completeNavigation(); }
    }
    
    function previousStep() {
        if (currentStep > 0) { currentStep--; updateNavigationStep(); }
    }
    
    function completeNavigation() {
        alert('✅ Chegou ao destino! Obrigado por utilizar o UrbanTraffic.');
        exitNavigation();
    }
    
    function exitNavigation() {
        navigationActive = false;
        $('#navigationPanel').fadeOut(300);
        $('#startRouteBtn').prop('disabled', false);
        if (navigationInterval) clearInterval(navigationInterval);
    }
    
    function startNavigationTimer() {
        if (navigationInterval) clearInterval(navigationInterval);
        navigationInterval = setInterval(() => { if (navigationActive) updateRemainingTime(); }, 60000);
    }
    
    function startRealtimeUpdates() {
        setInterval(() => {
            $('#lastUpdate').text(new Date().toLocaleTimeString());
            updateVehicles();
        }, 30000);
    }
    
    $(document).ready(function() {
        initMap();
        startRealtimeUpdates();
        updateVehicles();
        
        const params = new URLSearchParams(window.location.search);
        const fromLat = params.get('fromLat');
        const fromLon = params.get('fromLon');
        const toLat = params.get('toLat');
        const toLon = params.get('toLon');
        const origin = params.get('origin');
        const dest = params.get('dest');
        
        if (fromLat && fromLon && toLat && toLon) {
            $('#originDisplay').text(`${fromLat}, ${fromLon}`);
            $('#destDisplay').text(`${toLat}, ${toLon}`);
            if (typeof window.loadRoutes === 'function') window.loadRoutes(null, null);
            else console.error("loadRoutes não encontrada");
        } else if (origin && dest) {
            $('#originDisplay').text(decodeURIComponent(origin));
            $('#destDisplay').text(decodeURIComponent(dest));
            if (typeof window.loadRoutes === 'function') window.loadRoutes(origin, dest);
            else console.error("loadRoutes não encontrada");
        } else {
            console.log("Sem parâmetros válidos na URL");
        }
    });
    </script>
    
    <script src="js/routes.js"></script>
</body>
</html>