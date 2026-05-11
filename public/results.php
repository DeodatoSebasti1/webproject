<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Resultados</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.15.0/mapbox-gl.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        #map { height: 640px; min-height: 520px; width: 100%; border-radius: 18px; z-index: 1; background: #e8f5e9; }
        .results-layout { display: grid; grid-template-columns: minmax(320px, 380px) minmax(0, 1fr); gap: 20px; margin-top: 20px; align-items: start; }
        .routes-panel { max-height: 640px; overflow-y: auto; padding-right: 6px; }
        .route-card { cursor: pointer; transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease, background-color 0.22s ease; border: 1px solid rgba(76, 175, 80, 0.14); border-left: 4px solid transparent; background: rgba(255, 255, 255, 0.96); border-radius: 18px; margin-bottom: 14px; padding: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); }
        .route-card:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(46, 125, 50, 0.13); }
        .route-card.active { border-left-color: #4CAF50; border-color: rgba(76, 175, 80, 0.32); background: linear-gradient(180deg, rgba(241, 249, 241, 0.98), rgba(255, 255, 255, 0.98)); box-shadow: 0 18px 38px rgba(76, 175, 80, 0.16); }
        .route-card--journey-active { box-shadow: 0 18px 42px rgba(46, 125, 50, 0.22); }
        .route-card__sequence { display: none; }
        .route-card.active .route-card__sequence { display: block; }
        .btn-start-route { background: linear-gradient(135deg, #4CAF50 0%, #3f9b43 100%); color: white; border: none; padding: 11px 16px; border-radius: 12px; font-weight: 700; width: auto; box-shadow: 0 10px 18px rgba(76, 175, 80, 0.24); }
        .btn-start-route:hover { background: linear-gradient(135deg, #45a049 0%, #36893b 100%); transform: translateY(-1px); }
        .btn-start-route:disabled { background: #cccccc; cursor: not-allowed; box-shadow: none; }

        .navigation-panel { display: none !important; }

        .trip-summary, .walking-phase, .bus-phase { padding: 20px; }

        .trip-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-item:last-child { border-bottom: none; }

        .instruction-step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .instruction-icon {
            width: 40px;
            height: 40px;
            background: #f5f5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .instruction-text { flex: 1; }
        .instruction-distance { font-size: 12px; color: #666; }

        .eta-countdown {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            text-align: center;
            margin: 10px 0;
        }

        .next-stops-list { list-style: none; padding: 0; margin: 15px 0; }

        .next-stops-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .stop-eta {
            background: #e8f5e9;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #4CAF50;
        }

        .notification-toast {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            z-index: 1001;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { top: 0; opacity: 0; }
            to { top: 80px; opacity: 1; }
        }

        .progress-bar-container {
            background: #e0e0e0;
            border-radius: 10px;
            height: 6px;
            margin: 15px 0;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: #4CAF50;
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
        }

        .btn-nav {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            margin: 0 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .map-toggle-container {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .map-toggle-container .btn {
            padding: 6px 12px;
            font-size: 12px;
        }

        .map-toggle-container .btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .btn-primary-nav { background: #4CAF50; color: white; }
        .btn-secondary-nav { background: #e0e0e0; color: #333; }
        .btn-danger-nav { background: #f44336; color: white; }
        .btn-nav:hover { transform: translateY(-2px); opacity: 0.9; }

        .marker-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 6px rgba(0,0,0,0.4);
        }

        .marker-bus {
            border-radius: 6px;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.35));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .realtime-badge-live {
            background: #4CAF50 !important;
            color: #fff !important;
        }

        .realtime-badge-estimated,
        .realtime-badge-fallback,
        .realtime-badge-simulated {
            background: #E8F5E9 !important;
            color: #2E7D32 !important;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }

        .marker-bus svg {
            display: block;
        }

        .segment-item {
            border: 1px solid rgba(76, 175, 80, 0.16);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #f8fff8;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .segment-item:hover,
        .segment-item.active {
            border-color: #4CAF50;
            background: #e8f5e9;
            transform: translateX(2px);
        }

        .segment-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .segment-pill.walk { background: rgba(129, 199, 132, 0.18); color: #2E7D32; }
        .segment-pill.bus { background: #4CAF50; color: #fff; }
        .segment-pill.transfer { background: rgba(139, 195, 74, 0.18); color: #33691E; }

        .route-card__top,
        .route-card__headline,
        .route-card__actions,
        .route-card__places {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .route-card__top { align-items: flex-start; margin-bottom: 12px; }
        .route-card__eyebrow { display: flex; flex-wrap: wrap; gap: 8px; }
        .route-card__duration { text-align: right; min-width: 88px; }
        .route-card__duration strong { display: block; font-size: 1.65rem; line-height: 1; color: #15321c; }
        .route-card__duration span { display: block; margin-top: 4px; font-size: 0.72rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .route-card__headline { align-items: flex-start; margin-bottom: 12px; }
        .route-card__favorite { border-radius: 12px; width: 38px; height: 38px; padding: 0; flex-shrink: 0; }
        .route-card__schedule { margin-top: 8px; font-size: 0.9rem; color: #52606d; font-weight: 600; }
        .route-card__schedule span { color: #4CAF50; }
        .route-line-chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(76, 175, 80, 0.14); color: #256a2a; font-weight: 700; }
        .route-card__places { justify-content: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.92rem; color: #1f2937; }
        .route-card__place { display: inline-flex; align-items: center; gap: 8px; min-width: 0; font-weight: 600; }
        .route-card__place span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .route-card__arrow { color: #7cb342; font-size: 0.82rem; flex-shrink: 0; }
        .route-point__dot { width: 10px; height: 10px; border-radius: 999px; display: inline-flex; flex-shrink: 0; box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.08); }
        .route-point__dot--start { background: #4CAF50; }
        .route-point__dot--end { background: #2E7D32; }
        .route-card__chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .route-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #f3f7f3; color: #38503d; font-size: 0.78rem; font-weight: 700; }
        .route-chip--live { background: rgba(76, 175, 80, 0.14); color: #246b2b; }
        .route-card__meta { display: grid; gap: 6px; margin-bottom: 12px; color: #5f6b76; font-size: 0.84rem; }
        .route-card__meta div { display: inline-flex; align-items: center; gap: 8px; }
        .route-segments-compact { display: grid; gap: 8px; margin-bottom: 12px; }
        .route-segment-compact { width: 100%; text-align: left; display: grid; gap: 8px; background: #fbfefb; }
        .route-segment-compact__text { display: block; color: #213325; font-weight: 600; }
        .route-segment-compact__meta { display: block; color: #6b7280; font-size: 0.8rem; }
        .route-card__actions { margin-top: 4px; align-items: stretch; }
        .route-card__next { display: flex; flex-direction: column; justify-content: center; min-width: 84px; }
        .route-card__next .label { font-size: 0.72rem; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; }
        .route-card__next strong { color: #1d3f23; font-size: 1rem; }
        .route-card__actions .btn-start-route { flex: 1; }
        .route-card__journey-state { margin-top: 12px; border-top: 1px solid rgba(76, 175, 80, 0.14); padding-top: 12px; }
        .route-inline-state { border-radius: 16px; background: linear-gradient(180deg, rgba(232, 245, 233, 0.72), rgba(255, 255, 255, 0.98)); border: 1px solid rgba(76, 175, 80, 0.18); overflow: hidden; }
        .route-inline-state__header { padding: 14px 16px; background: linear-gradient(135deg, #4CAF50 0%, #3f9b43 100%); color: #fff; }
        .route-inline-state__header small { opacity: 0.88; }
        .route-inline-state__body { padding: 14px 16px; }
        .route-inline-state__actions { display: flex; gap: 10px; margin-top: 14px; }
        .route-inline-state__actions .btn-nav { margin: 0; border-radius: 12px; }
        .route-inline-state__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .route-inline-state__metric { background: rgba(255, 255, 255, 0.82); border-radius: 12px; padding: 10px 12px; }
        .route-inline-state__metric small { display: block; color: #6b7280; }
        .route-inline-state__metric strong { display: block; margin-top: 4px; color: #1d3f23; }
        .ut-map-shell { min-width: 0; }
        .ut-map-frame { padding: 10px; }
        .ut-selected-route-info { margin-top: 12px; }

        @media (max-width: 768px) {
            .results-layout { grid-template-columns: 1fr; }
            .ut-map-shell { order: -1; }
            .routes-panel { max-height: none; overflow: visible; padding-right: 0; }
            .route-card { padding: 14px; }
            .route-card__top,
            .route-card__headline,
            .route-card__actions,
            .route-card__places { flex-wrap: wrap; }
            .route-card__duration { text-align: left; min-width: 0; }
            .route-inline-state__grid { grid-template-columns: 1fr; }
            #map { height: 55vh; min-height: 360px; }
        }
    </style>
</head>
<body class="ut-page ut-page-results">

    <?php include 'partials/navbar.php'; ?>

    <section class="py-3 ut-results-hero">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center text-white flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-route me-2"></i>
                        <span id="originDisplay">-</span> → <span id="destDisplay">-</span>
                    </h5>
                    <div id="journeyMetaDisplay" class="small mt-1 text-white-50"></div>
                    <div id="walkInfo" class="small mt-1" style="display: none;"></div>
                </div>
                <button class="btn ut-btn ut-btn-secondary ut-btn-sm" onclick="window.location.href='index.php'">
                    <i class="fas fa-arrow-left me-2"></i><span data-i18n="resultsNewSearch">Nova Pesquisa</span>
                </button>
            </div>
        </div>
    </section>

    <section class="container my-4">
        <div class="results-layout ut-results-layout">
            <div class="routes-panel ut-panel ut-routes-panel">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-2">
                    <i class="fas fa-route me-2" style="color: #4CAF50;"></i>
                    <span data-i18n="resultsSuggestedRoutes">Percursos sugeridos</span>
                        </h5>
                        <p class="text-muted small mb-0">Escolha a rota com melhor tempo, menos transbordos e estado ao vivo.</p>
                    </div>
                    <span class="ut-badge ut-badge-primary" id="routeCount">0</span>
                </div>
                <div id="routesList">
                    <div class="ut-loading">
                        <div class="spinner-border text-success" role="status" aria-hidden="true"></div>
                        <p class="text-muted mb-0">A calcular melhores rotas...</p>
                    </div>
                </div>
                <div class="card mt-3 border-0 shadow-sm ut-card">
                    <div class="card-header bg-white border-0">
                        <i class="fas fa-clock me-2" style="color: #4CAF50;"></i><span data-i18n="resultsRealtime">Tempo Real</span>
                        <span class="ut-badge ut-badge-live ms-2">Ao vivo</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span data-i18n="resultsActiveBuses">Autocarros ativos:</span>
                            <span class="fw-bold" id="activeBuses">-</span>
                        </div>
                    <div class="d-flex justify-content-between">
                        <span data-i18n="resultsLastUpdate">Última atualização:</span>
                        <span class="fw-bold" id="lastUpdate">-</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span data-i18n="resultsSource">Fonte:</span>
                        <span class="fw-bold" id="realtimeSourceLabel">-</span>
                    </div>
                </div>
            </div>
            </div>

            <div class="ut-map-shell">
                <div class="ut-panel ut-map-frame" style="position: relative;">
                <div id="mapPlaceholder" class="ut-map-placeholder" style="height: 500px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                    <div class="text-center">
                        <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">A carregar mapa...</p>
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">A carregar...</span>
                        </div>
                    </div>
                </div>

                <div id="map" class="ut-map-canvas" style="height: 500px; display: none;"></div>

                <div class="map-toggle-container ut-floating-controls">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-light active" id="mapViewDefault">
                            <i class="fas fa-map"></i> Mapa
                        </button>
                        <button type="button" class="btn btn-sm btn-light" id="mapViewSatellite">
                            <i class="fas fa-satellite"></i> Satélite
                        </button>
                    </div>
                </div>
                </div>

                <div class="card mt-3 border-0 shadow-sm ut-card ut-selected-route-info" id="selectedRouteInfo" style="display: none;">
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

    <div class="navigation-panel" id="navigationPanel"></div>

    <?php include 'partials/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.15.0/mapbox-gl.js"></script>
    <script src="js/preferences.js?v=20260427a"></script>
    <script src="js/auth.js?v=20260427h"></script>
    <script src="js/routes.js?v=20260505b"></script>

    <script>
    window.URBAN_MAPBOX_TOKEN = <?php echo json_encode(getenv('URBAN_MAPBOX_TOKEN') ?: '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    mapboxgl.accessToken = window.URBAN_MAPBOX_TOKEN || '';

    let map;
    let vehicleMarkers = {};
    let userLocation = null;
    let userMarker = null;
    let currentBusMarker = null;
    let currentBusMarkerRecord = null;
    let busTrackingInterval = null;
    let watchPositionId = null;
    let currentTripId = null;
    let currentRoute = null;
    let currentPhase = 'summary';
    let remainingWalkDistance = 0;
    let countdownInterval = null;
    let mapReady = false;
    let currentLayerType = 'mapbox';
    let selectedRouteBusMarker = null;
    let selectedRouteBusInterval = null;
    let selectedRouteId = null;
    let selectedRouteMarkerRecord = null;
    let selectedRouteBusAlertState = {
        routeKey: null,
        approachingNotified: false,
        arrivedNotified: false
    };
    const SHOW_ALL_VEHICLES = false;
    window.pendingRouteIndex = null;

    const styles = {
        mapbox: 'mapbox://styles/mapbox/streets-v12',
        satellite: 'mapbox://styles/mapbox/standard-satellite'
    };

    window.inlineJourneyState = {
        routeIndex: null,
        html: '',
        phase: 'summary'
    };

    function canUseLocationSetting() {
        return window.UrbanPreferences?.canUseLocation?.() !== false;
    }

    function canUseNotificationsSetting() {
        return window.UrbanPreferences?.canUseNotifications?.() !== false;
    }

    function getRealtimeRefreshMs() {
        return window.UrbanPreferences?.getRealtimeRefreshMs?.() || 10000;
    }

    function isReducedMotionEnabled() {
        return window.UrbanPreferences?.getAnimationEnabled?.() === false;
    }

    function setInlineJourneyState(content, phase = 'summary') {
        if (window.inlineJourneyState.routeIndex === null || window.inlineJourneyState.routeIndex === undefined) {
            return;
        }

        const stateElement = $(`.route-card__journey-state[data-journey-state="${window.inlineJourneyState.routeIndex}"]`);
        if (!stateElement.length) return;

        $('.route-card__journey-state').attr('hidden', true).empty();
        $('.route-card').removeClass('route-card--journey-active');

        stateElement.html(content).attr('hidden', false);
        stateElement.closest('.route-card').addClass('route-card--journey-active');

        window.inlineJourneyState.html = content;
        window.inlineJourneyState.phase = phase;
    }

    function clearInlineJourneyState() {
        $('.route-card__journey-state').attr('hidden', true).empty();
        $('.route-card').removeClass('route-card--journey-active');
        window.inlineJourneyState = {
            routeIndex: null,
            html: '',
            phase: 'summary'
        };
    }

    window.handleRouteSelectionChange = function(nextIndex) {
        if (window.inlineJourneyState.routeIndex !== null && window.inlineJourneyState.routeIndex !== nextIndex) {
            exitNavigation(true);
        }
    };

    window.syncInlineJourneyState = function(selectedIndex) {
        if (window.inlineJourneyState.routeIndex === selectedIndex && window.inlineJourneyState.html) {
            setInlineJourneyState(window.inlineJourneyState.html, window.inlineJourneyState.phase);
        }
    };

    function formatJourneyMeta(dateValue, timeValue) {
        const parts = [];

        if (dateValue) {
            const parsedDate = new Date(`${dateValue}T00:00:00`);
            if (!Number.isNaN(parsedDate.getTime())) {
                parts.push(parsedDate.toLocaleDateString('pt-PT', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }));
            }
        }

        if (timeValue) {
            parts.push(`Partida às ${timeValue}`);
        }

        return parts.join(' · ');
    }

    function initMap(center = [-9.1393, 38.7223], zoom = 13) {
        if (!document.getElementById('map')) return;

        map = new mapboxgl.Map({
            container: 'map',
            style: styles.mapbox,
            center,
            zoom
        });

        window.map = map;

        map.addControl(new mapboxgl.NavigationControl(), 'top-left');

        map.on('load', () => {
            ensureRouteSource();
            mapReady = true;
            $('#mapPlaceholder').hide();
            $('#map').show();
            requestMapResize();

            startRealtimeUpdates();

            if (window.pendingRouteIndex !== null && window.globalRoutes && globalRoutes[window.pendingRouteIndex]) {
                drawRouteSegments(globalRoutes[window.pendingRouteIndex]);
                window.pendingRouteIndex = null;
            } else if (window.globalRoutes && globalRoutes.length > 0) {
                drawRouteSegments(globalRoutes[0]);
            }
        });

        map.on('style.load', () => {
            ensureRouteSource();
            redrawVehicleMarkers();
            if (window.currentRouteForRedraw) {
                drawRouteSegments(window.currentRouteForRedraw, false);
            }
            requestMapResize();
        });

        return map;
    }

    function requestMapResize(delay = 0) {
        if (!map) return;
        window.setTimeout(() => {
            if (!map) return;
            try {
                map.resize();
            } catch (error) {
                console.warn('Map resize falhou:', error);
            }
        }, delay);
    }

    function isMapStyleReady() {
        return !!(map && (!map.isStyleLoaded || map.isStyleLoaded()));
    }

    function runWhenStyleReady(callback) {
        if (!map) return;

        if (isMapStyleReady()) {
            callback();
            return;
        }

        map.once('style.load', callback);
    }

    function ensureRouteSource() {
        if (!map || !isMapStyleReady()) return false;

        [
            'route-stops-circle',
            'route-stops-label'
        ].forEach((layerId) => {
            if (map.getLayer(layerId)) {
                map.removeLayer(layerId);
            }
        });

        if (map.getSource('route-stops')) {
            map.removeSource('route-stops');
        }

        if (!map.getSource('selected-route')) {
            map.addSource('selected-route', {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    geometry: {
                        type: 'LineString',
                        coordinates: []
                    }
                }
            });
        }

        if (!map.getLayer('selected-route-line')) {
            map.addLayer({
                id: 'selected-route-line',
                type: 'line',
                source: 'selected-route',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#2E7D32',
                    'line-width': 2,
                    'line-opacity': 0
                }
            });
        }

        if (!map.getSource('route-segments')) {
            map.addSource('route-segments', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: []
                }
            });
        }

        if (!map.getLayer('route-segments-bus-line')) {
            map.addLayer({
                id: 'route-segments-bus-line',
                type: 'line',
                source: 'route-segments',
                filter: ['==', ['get', 'segment_type'], 'bus'],
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': [
                        'match',
                        ['get', 'geometry_quality'],
                        'gtfs_shape', '#1B5E20',
                        'fallback_stop_to_stop', '#607D8B',
                        'fallback_straight_line', '#90A4AE',
                        '#1B5E20'
                    ],
                    'line-width': [
                        'match',
                        ['get', 'geometry_quality'],
                        'fallback_straight_line', 6,
                        11
                    ],
                    'line-opacity': [
                        'match',
                        ['get', 'geometry_quality'],
                        'fallback_straight_line', 0.08,
                        'fallback_stop_to_stop', 0.12,
                        0.18
                    ]
                }
            });
        }

        if (!map.getLayer('route-segments-bus-line-core')) {
            map.addLayer({
                id: 'route-segments-bus-line-core',
                type: 'line',
                source: 'route-segments',
                filter: ['==', ['get', 'segment_type'], 'bus'],
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': [
                        'match',
                        ['get', 'geometry_quality'],
                        'gtfs_shape', '#2E7D32',
                        'fallback_stop_to_stop', '#78909C',
                        'fallback_straight_line', '#B0BEC5',
                        '#2E7D32'
                    ],
                    'line-width': [
                        'match',
                        ['get', 'geometry_quality'],
                        'fallback_straight_line', 3,
                        'fallback_stop_to_stop', 4.5,
                        7
                    ],
                    'line-opacity': [
                        'match',
                        ['get', 'geometry_quality'],
                        'fallback_straight_line', 0.42,
                        'fallback_stop_to_stop', 0.72,
                        0.96
                    ],
                    'line-dasharray': [
                        'match',
                        ['get', 'geometry_quality'],
                        'fallback_straight_line', ['literal', [0.8, 1.6]],
                        ['literal', [1, 0]]
                    ]
                }
            });
        }

        if (!map.getLayer('route-segments-walk-line')) {
            map.addLayer({
                id: 'route-segments-walk-line',
                type: 'line',
                source: 'route-segments',
                filter: ['==', ['get', 'segment_type'], 'walk'],
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#1B5E20',
                    'line-width': 7,
                    'line-opacity': 0.14
                }
            });
        }

        if (!map.getLayer('route-segments-walk-line-core')) {
            map.addLayer({
                id: 'route-segments-walk-line-core',
                type: 'line',
                source: 'route-segments',
                filter: ['==', ['get', 'segment_type'], 'walk'],
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': [
                        'match',
                        ['get', 'geometry_quality'],
                        'street_routed', '#81C784',
                        'fallback_straight_line', '#C5E1A5',
                        '#81C784'
                    ],
                    'line-width': 4.5,
                    'line-opacity': 0.95,
                    'line-dasharray': [
                        'match',
                        ['get', 'geometry_quality'],
                        'fallback_straight_line', ['literal', [0.8, 2.2]],
                        ['literal', [1.2, 1.8]]
                    ]
                }
            });
        }

        if (!map.getLayer('route-segments-transfer-line')) {
            map.addLayer({
                id: 'route-segments-transfer-line',
                type: 'line',
                source: 'route-segments',
                filter: ['==', ['get', 'segment_type'], 'transfer'],
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#33691E',
                    'line-width': 7,
                    'line-opacity': 0.14
                }
            });
        }

        if (!map.getLayer('route-segments-transfer-line-core')) {
            map.addLayer({
                id: 'route-segments-transfer-line-core',
                type: 'line',
                source: 'route-segments',
                filter: ['==', ['get', 'segment_type'], 'transfer'],
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#AED581',
                    'line-width': 4,
                    'line-opacity': 0.95,
                    'line-dasharray': [0.8, 1.8]
                }
            });
        }

        if (!map.getSource('route-points')) {
            map.addSource('route-points', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: []
                }
            });
        }

        if (!map.getLayer('route-points-circle')) {
            map.addLayer({
                id: 'route-points-circle',
                type: 'circle',
                source: 'route-points',
                paint: {
                    'circle-radius': [
                        'match',
                        ['get', 'kind'],
                        'origin', 8,
                        'destination', 8,
                        'transfer', 7,
                        'board', 7,
                        'alight', 7,
                        'stop', 5.5,
                        5
                    ],
                    'circle-color': [
                        'match',
                        ['get', 'kind'],
                        'origin', '#4CAF50',
                        'destination', '#2E7D32',
                        'transfer', '#9CCC65',
                        'board', '#43A047',
                        'alight', '#66BB6A',
                        'stop', '#F1F8E9',
                        '#81C784'
                    ],
                    'circle-stroke-color': [
                        'match',
                        ['get', 'kind'],
                        'stop', '#2E7D32',
                        '#ffffff'
                    ],
                    'circle-stroke-width': [
                        'match',
                        ['get', 'kind'],
                        'stop', 1.6,
                        2
                    ],
                    'circle-opacity': 0.98
                }
            });
        }

        if (!map.getLayer('route-points-label')) {
            map.addLayer({
                id: 'route-points-label',
                type: 'symbol',
                source: 'route-points',
                filter: ['match', ['get', 'kind'], ['origin', 'destination', 'board', 'alight', 'transfer'], true, false],
                layout: {
                    'text-field': ['get', 'name'],
                    'text-font': ['Open Sans Semibold', 'Arial Unicode MS Bold'],
                    'text-size': 11,
                    'text-offset': [0, 1.2],
                    'text-anchor': 'top',
                    'text-max-width': 14
                },
                paint: {
                    'text-color': '#16381B',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 1.25,
                    'text-opacity': 0.94
                },
                minzoom: 13
            });
        }

        if (!map.getSource('route-segment-focus')) {
            map.addSource('route-segment-focus', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: []
                }
            });
        }

        if (!map.getLayer('route-segment-focus-line')) {
            map.addLayer({
                id: 'route-segment-focus-line',
                type: 'line',
                source: 'route-segment-focus',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#C8E6C9',
                    'line-width': 11,
                    'line-opacity': 0.35
                }
            });
        }

        if (!map.getSource('walking-route')) {
            map.addSource('walking-route', {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    geometry: {
                        type: 'LineString',
                        coordinates: []
                    }
                }
            });
        }

        if (!map.getLayer('walking-route-line')) {
            map.addLayer({
                id: 'walking-route-line',
                type: 'line',
                source: 'walking-route',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#66BB6A',
                    'line-width': 5,
                    'line-opacity': 0.85,
                    'line-dasharray': [2, 2]
                }
            });
        }

        if (!window.routePointClickBound) {
            map.on('click', 'route-points-circle', (event) => {
                const feature = event.features && event.features[0];
                if (!feature) return;
                const props = feature.properties || {};
                const debug = props.debug ? `<div class="small text-muted mt-2"><code>${escapeHtml(props.debug)}</code></div>` : '';
                const isTransfer = props.type === 'transfer' || props.kind === 'transfer';
                const popupHtml = isTransfer
                    ? `
                        <div class="card border-0 route-stop-popup" style="min-width: 250px;">
                            <div class="card-body p-3">
                                <strong>Transbordo em ${escapeHtml(props.stop_name || props.name || 'paragem')}</strong>
                                ${props.from_line ? `<div class="small mt-2">Sair do autocarro <strong>${escapeHtml(props.from_line)}</strong></div>` : ''}
                                ${props.wait_time !== '' ? `<div class="small mt-1">Esperar <strong>${escapeHtml(String(props.wait_time))} min</strong></div>` : ''}
                                ${props.to_line ? `<div class="small mt-1">Apanhar <strong>${escapeHtml(props.to_line)}</strong>${props.departure_time ? ` às <strong>${escapeHtml(props.departure_time)}</strong>` : ''}</div>` : ''}
                                ${props.instruction ? `<div class="small text-muted mt-2">${escapeHtml(props.instruction)}</div>` : ''}
                                ${debug}
                            </div>
                        </div>
                    `
                    : `
                        <div class="card border-0 route-stop-popup" style="min-width: 220px;">
                            <div class="card-body p-2">
                                <strong>${escapeHtml(props.stop_name || props.name || 'Ponto')}</strong>
                                <div class="small mt-1">${escapeHtml(props.action || '')}</div>
                                ${props.line ? `<div class="small text-success fw-semibold mt-1">Linha ${escapeHtml(props.line)}</div>` : ''}
                                ${props.arrival_time ? `<div class="small text-muted mt-1">Chegada: ${escapeHtml(props.arrival_time)}</div>` : ''}
                                ${props.departure_time ? `<div class="small text-muted mt-1">Partida: ${escapeHtml(props.departure_time)}</div>` : ''}
                                ${props.stop_id ? `<div class="small text-muted mt-1">Paragem: ${escapeHtml(props.stop_id)}</div>` : ''}
                                ${debug}
                            </div>
                        </div>
                    `;
                new mapboxgl.Popup({ offset: 14 })
                    .setLngLat(feature.geometry.coordinates)
                    .setHTML(popupHtml)
                    .addTo(map);
            });

            map.on('mouseenter', 'route-points-circle', () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'route-points-circle', () => {
                map.getCanvas().style.cursor = '';
            });
            window.routePointClickBound = true;
        }

        return true;
    }

    function toggleMapLayer(type) {
        if (!map) return;
        const styleUrl = styles[type] || styles.mapbox;
        currentLayerType = type;

        $('#mapViewDefault').toggleClass('active', type === 'mapbox');
        $('#mapViewSatellite').toggleClass('active', type === 'satellite');

        map.setStyle(styleUrl);
    }

    function normalizeLineNumber(vehicle) {
        const raw = String(vehicle.route_name || vehicle.line || vehicle.route_id || '');
        const match = raw.match(/\d{4}/);
        return match ? parseInt(match[0], 10) : null;
    }

    function getVehicleColor(vehicle) {
        if (vehicle && vehicle.__selected) return '#2E7D32';

        const label = String(vehicle.route_name || vehicle.route_id || '').toLowerCase();
        const lineNumber = normalizeLineNumber(vehicle || {});

        if (label.includes('regional') || label.includes('express') || label.includes('rápida') || label.includes('rapida')) {
            return '#2E7D32';
        }

        if (lineNumber >= 1000 && lineNumber <= 1999) return '#4CAF50';
        if (lineNumber >= 2000 && lineNumber <= 2999) return '#43A047';
        if (lineNumber >= 3000 && lineNumber <= 3999) return '#66BB6A';
        if (lineNumber >= 4000 && lineNumber <= 4999) return '#81C784';

        return '#4CAF50';
    }

    function createBusSvg(color, size) {
        const stroke = color === '#4CAF50' || color === '#2E7D32' ? '#ffffff' : '#1f2933';
        const plate = color === '#4CAF50' || color === '#2E7D32' ? '#ffffff' : '#1f2933';

        return `
            <svg width="${size}" height="${size}" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="4" y="3" width="16" height="16" rx="3" fill="${color}" stroke="${stroke}" stroke-width="1.6"/>
                <path d="M7 6.5h10v4H7z" fill="white" opacity="0.92"/>
                <circle cx="8" cy="17" r="1.7" fill="${plate}"/>
                <circle cx="16" cy="17" r="1.7" fill="${plate}"/>
                <path d="M7 13h2.5M14.5 13H17" stroke="${plate}" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        `;
    }

    function createMapboxMarker(color, size = 24, label = '') {
        const el = document.createElement('div');
        el.className = 'marker-bus';
        el.style.width = `${size}px`;
        el.style.height = `${size}px`;
        el.innerHTML = label || createBusSvg(color, size);
        return el;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function formatVehicleTimestamp(timestamp) {
        if (!timestamp) return '--:--';
        const numeric = Number(timestamp);
        const date = Number.isFinite(numeric)
            ? new Date(numeric * 1000)
            : new Date(timestamp);
        return Number.isNaN(date.getTime()) ? '--:--' : date.toLocaleTimeString('pt-PT');
    }

    function formatOccupancy(value) {
        const raw = String(value || 'NO_DATA_AVAILABLE').toUpperCase();
        const labels = {
            EMPTY: 'Vazia',
            MANY_SEATS_AVAILABLE: 'Muitos lugares',
            FEW_SEATS_AVAILABLE: 'Poucos lugares',
            STANDING_ROOM_ONLY: 'Média',
            CRUSHED_STANDING_ROOM_ONLY: 'Cheia',
            FULL: 'Cheia',
            NOT_ACCEPTING_PASSENGERS: 'Sem embarque',
            NO_DATA_AVAILABLE: 'Sem dados'
        };
        return labels[raw] || raw.replaceAll('_', ' ').toLowerCase();
    }

    function vehicleStatusText(vehicle) {
        const speed = Number(vehicle.speed || 0);
        const currentStatus = String(vehicle.current_status || '').toUpperCase();
        if (speed > 0.5 || currentStatus === 'IN_TRANSIT_TO') return 'Em movimento';
        return 'Parado';
    }

    function getVehicleHeadsign(vehicle) {
        return vehicle.trip_headsign
            || vehicle.headsign
            || vehicle.destination
            || vehicle.destination_name
            || window.currentRouteForRedraw?.to
            || 'Destino não disponível';
    }

    function getNextStopInfoForVehicle(vehicle) {
        const route = window.currentRouteForRedraw;
        if (!route || !route.stopsData || route.stopsData.length === 0) {
            return { name: vehicle.stop_id || 'Indisponível', eta: 'Indisponível' };
        }

        let bestIndex = 0;
        let bestDistance = Infinity;
        route.stopsData.forEach((stop, index) => {
            const distance = calculateDistance(
                Number(vehicle.latitude),
                Number(vehicle.longitude),
                Number(stop.stop_lat),
                Number(stop.stop_lon)
            );
            if (distance < bestDistance) {
                bestDistance = distance;
                bestIndex = index;
            }
        });

        const speedMps = Number(vehicle.speed || 0);
        const nextStop = route.stopsData[Math.min(bestIndex + 1, route.stopsData.length - 1)];
        const eta = speedMps > 0.5
            ? `${Math.max(1, Math.round(bestDistance / speedMps / 60))} min`
            : (nextStop.arrival_time ? formatGtfsClock(nextStop.arrival_time) : 'Indisponível');

        return {
            name: nextStop.stop_name || nextStop.stop_id || 'Indisponível',
            eta
        };
    }

    function createVehiclePopupHtml(vehicle) {
        const nextStop = getNextStopInfoForVehicle(vehicle);
        const speedKmh = Math.round(Number(vehicle.speed || 0) * 3.6);
        return `
            <div class="card border-0 vehicle-popup" style="min-width: 245px;">
                <div class="card-body p-3">
                    <div class="text-center mb-2">
                        ${createBusSvg(getVehicleColor(vehicle), 32)}
                    </div>
                    <h6 class="mb-2 text-center">Linha ${escapeHtml(vehicle.route_name || vehicle.route_id || 'N/A')}</h6>
                    <div class="small"><strong>Destino:</strong> ${escapeHtml(getVehicleHeadsign(vehicle))}</div>
                    <div class="small"><strong>Velocidade:</strong> ${speedKmh} km/h</div>
                    <div class="small"><strong>Lotação:</strong> ${escapeHtml(formatOccupancy(vehicle.occupancy))}</div>
                    <div class="small"><strong>Próxima paragem:</strong> ${escapeHtml(nextStop.name)}</div>
                    <div class="small"><strong>ETA:</strong> ${escapeHtml(nextStop.eta)}</div>
                    <div class="small"><strong>Estado:</strong> ${escapeHtml(vehicleStatusText(vehicle))}</div>
                    <div class="small text-muted mt-1">Atualizado: ${escapeHtml(formatVehicleTimestamp(vehicle.timestamp))}</div>
                </div>
            </div>
        `;
    }

    function formatGtfsClock(time) {
        if (!time) return '--:--';
        const parts = String(time).split(':').map(Number);
        if (parts.length < 2 || Number.isNaN(parts[0]) || Number.isNaN(parts[1])) return '--:--';
        return `${String(parts[0] % 24).padStart(2, '0')}:${String(parts[1]).padStart(2, '0')}`;
    }

    function createUserMarkerElement() {
        const el = document.createElement('div');
        el.className = 'marker-dot';
        el.style.background = '#4CAF50';
        el.style.width = '20px';
        el.style.height = '20px';
        return el;
    }

    function redrawVehicleMarkers() {
        Object.values(vehicleMarkers).forEach(record => {
            if (record.animationFrame) cancelAnimationFrame(record.animationFrame);
            record.marker.remove();
        });
        vehicleMarkers = {};
        if (SHOW_ALL_VEHICLES && window._latestVehicles) {
            drawVehicleMarkers(window._latestVehicles);
        }
        if (selectedRouteBusMarker && window._selectedBusData) {
            selectedRouteBusMarker.remove();
            selectedRouteBusMarker = null;
            selectedRouteMarkerRecord = null;
            updateSelectedBusMarker(window._selectedBusData);
        }
        if (currentBusMarker && window._currentBusData) {
            currentBusMarker.remove();
            currentBusMarker = null;
            currentBusMarkerRecord = null;
            updateBusPosition(window._currentBusData, false);
        }
        if (userMarker && userLocation) {
            userMarker.remove();
            userMarker = new mapboxgl.Marker({ element: createUserMarkerElement() })
                .setLngLat([userLocation.lon, userLocation.lat])
                .addTo(map);
        }
    }

    function setMarkerPopup(marker, vehicle) {
        marker.setPopup(new mapboxgl.Popup({ offset: 18 }).setHTML(createVehiclePopupHtml(vehicle)));
    }

    function resetSelectedBusAlertState(routeInfo = null) {
        selectedRouteBusAlertState = {
            routeKey: routeInfo
                ? (routeInfo.primary_trip_id || routeInfo.trip_id || routeInfo.route_id || routeInfo.route_name || routeInfo.line || null)
                : null,
            approachingNotified: false,
            arrivedNotified: false
        };
    }

    function syncRealtimeStatus(routeInfo, etaData = null) {
        if (!routeInfo) return;

        const routeIndex = Number(window.activeRouteIndex || 0);
        const routeDelayElement = document.querySelector(`[data-route-delay="${routeIndex}"]`);
        const routeNextElement = document.querySelector(`[data-route-next="${routeIndex}"]`);
        const hasRealtime = Boolean(etaData && (etaData.is_realtime || etaData.eta_text));
        const delayText = etaData?.delay_text || routeInfo.delay_text || (hasRealtime ? 'Ao vivo' : 'Sem realtime');
        const nextText = etaData?.eta_text || routeInfo.nextBus || 'Indisponível';
        const delaySeconds = Number(etaData?.delay || routeInfo.delay || 0) || 0;

        if (routeNextElement) {
            routeNextElement.textContent = nextText;
        }

        if (routeDelayElement) {
            routeDelayElement.classList.remove('text-success', 'text-warning', 'text-danger', 'text-muted');
            routeDelayElement.classList.add(
                !hasRealtime ? 'text-muted' : (delaySeconds > 180 ? 'text-warning' : 'text-success')
            );
            routeDelayElement.innerHTML = `<i class="fas fa-signal me-1"></i>${escapeHtml(delayText)}`;
        }

        routeInfo.nextBus = nextText;
        routeInfo.delay = delaySeconds;
        routeInfo.delay_text = delayText;
    }

    function maybeNotifyBusApproach(routeInfo, vehicle) {
        if (!routeInfo || !vehicle || !Array.isArray(routeInfo.stopsData) || routeInfo.stopsData.length === 0) return;

        const routeKey = routeInfo.primary_trip_id || routeInfo.trip_id || routeInfo.route_id || routeInfo.route_name || routeInfo.line || null;
        if (selectedRouteBusAlertState.routeKey !== routeKey) {
            resetSelectedBusAlertState(routeInfo);
        }

        const firstStop = routeInfo.stopsData[0];
        const distanceToBoardingStop = calculateDistance(
            Number(vehicle.latitude),
            Number(vehicle.longitude),
            Number(firstStop.stop_lat),
            Number(firstStop.stop_lon)
        );

        if (!selectedRouteBusAlertState.approachingNotified && distanceToBoardingStop <= 450) {
            selectedRouteBusAlertState.approachingNotified = true;
            showNotification(`O autocarro da linha ${routeInfo.line} está a aproximar-se da paragem.`, 'info');
        }

        if (!selectedRouteBusAlertState.arrivedNotified && distanceToBoardingStop <= 120) {
            selectedRouteBusAlertState.arrivedNotified = true;
            showNotification(`O autocarro da linha ${routeInfo.line} está a chegar à paragem.`, 'success');
        }
    }

    function animateMarkerTo(record, vehicle, target, duration = 10000) {
        if (!record || !record.marker) return;

        if (record.animationFrame) {
            cancelAnimationFrame(record.animationFrame);
            record.animationFrame = null;
        }

        const speed = Number(vehicle.speed || 0);
        const status = String(vehicle.current_status || '').toUpperCase();
        if (isReducedMotionEnabled() || speed <= 0.5 || status === 'STOPPED_AT') {
            record.marker.setLngLat(target);
            record.lngLat = target;
            return;
        }

        const current = record.marker.getLngLat();
        const start = [current.lng, current.lat];
        const startedAt = performance.now();

        const step = (now) => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = progress < 0.5
                ? 2 * progress * progress
                : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            const lng = start[0] + (target[0] - start[0]) * eased;
            const lat = start[1] + (target[1] - start[1]) * eased;
            record.marker.setLngLat([lng, lat]);

            if (progress < 1) {
                record.animationFrame = requestAnimationFrame(step);
            } else {
                record.animationFrame = null;
                record.lngLat = target;
            }
        };

        record.animationFrame = requestAnimationFrame(step);
    }

    function drawVehicleMarkers(vehicles) {
        if (!map) return;
        const seen = new Set();

        vehicles.forEach((vehicle, index) => {
            const lat = parseFloat(vehicle.latitude);
            const lon = parseFloat(vehicle.longitude);

            if (!isNaN(lat) && !isNaN(lon)) {
                const id = vehicle.vehicle_id || `${vehicle.route_id || 'bus'}-${index}`;
                const target = [lon, lat];
                seen.add(id);

                if (vehicleMarkers[id]) {
                    vehicleMarkers[id].vehicle = vehicle;
                    setMarkerPopup(vehicleMarkers[id].marker, vehicle);
                    animateMarkerTo(vehicleMarkers[id], vehicle, target, 10000);
                    return;
                }

                const marker = new mapboxgl.Marker({
                    element: createMapboxMarker(getVehicleColor(vehicle), 24)
                })
                    .setLngLat(target)
                    .addTo(map);
                setMarkerPopup(marker, vehicle);

                vehicleMarkers[id] = {
                    marker,
                    vehicle,
                    lngLat: target,
                    animationFrame: null
                };
            }
        });

        Object.keys(vehicleMarkers).forEach(id => {
            if (seen.has(id)) return;
            if (vehicleMarkers[id].animationFrame) cancelAnimationFrame(vehicleMarkers[id].animationFrame);
            vehicleMarkers[id].marker.remove();
            delete vehicleMarkers[id];
        });
    }

    function updateVehicles() {
        if (!map) return;

        fetch('/urban/public/api/realtime?action=vehicles')
            .then(res => res.json())
            .then(data => {
                if (data.data && Array.isArray(data.data)) {
                    window._latestVehicles = data.data;
                    if (SHOW_ALL_VEHICLES) {
                        drawVehicleMarkers(data.data);
                    } else {
                        Object.keys(vehicleMarkers).forEach(id => {
                            if (vehicleMarkers[id].animationFrame) cancelAnimationFrame(vehicleMarkers[id].animationFrame);
                            vehicleMarkers[id].marker.remove();
                            delete vehicleMarkers[id];
                        });
                    }
                    $('#lastUpdate').text(new Date().toLocaleTimeString('pt-PT'));

                    const source = data.source || data.data_source || 'fallback';
                    const sourceLabel = source === 'realtime'
                        ? 'AO VIVO'
                        : (source === 'estimated' ? 'ESTIMADO' : (source === 'simulated' ? 'SIMULADO' : 'FALLBACK'));

                    $('.card-header .badge').first()
                        .text(sourceLabel)
                        .removeClass('bg-success bg-warning realtime-badge-live realtime-badge-estimated realtime-badge-fallback realtime-badge-simulated')
                        .addClass(source === 'realtime' ? 'realtime-badge-live' : `realtime-badge-${source}`);

                    const selectedVisibleCount = selectedRouteMarkerRecord || currentBusMarkerRecord ? 1 : 0;
                    $('#activeBuses').text(SHOW_ALL_VEHICLES ? `${Object.keys(vehicleMarkers).length}/${data.data.length}` : `${selectedVisibleCount}/${data.data.length}`);
                    $('#realtimeSourceLabel').text(sourceLabel);
                }
            })
            .catch(err => console.warn('Erro ao buscar veículos:', err));
    }

    function getSegmentCoordinates(segment) {
        const coordinates = segment?.geometry?.coordinates;
        if (!Array.isArray(coordinates)) {
            return [];
        }
        return coordinates
            .filter(coord => Array.isArray(coord) && coord.length >= 2)
            .map(coord => [Number(coord[0]), Number(coord[1])])
            .filter(coord => Number.isFinite(coord[0]) && Number.isFinite(coord[1]));
    }

    function buildSegmentGeoJson(route, debugEnabled = false) {
        const features = [];
        const segments = Array.isArray(route.segments) ? route.segments : [];

        segments.forEach((segment, index) => {
            const coordinates = getSegmentCoordinates(segment);
            if (coordinates.length < 2) return;

            features.push({
                type: 'Feature',
                geometry: {
                    type: 'LineString',
                    coordinates
                },
                properties: {
                    segment_index: index,
                    segment_type: segment.type || 'bus',
                    line: segment.line || '',
                    trip_id: segment.trip_id || '',
                    shape_id: segment.shape_id || '',
                    from_name: segment.from_name || '',
                    to_name: segment.to_name || '',
                    duration_minutes: segment.duration_minutes || segment.time || 0,
                    stop_count: segment.stop_count || segment.stops || 0,
                    points_count: coordinates.length,
                    geometry_quality: segment.geometry_quality || '',
                    geometry_source: segment.geometry_source || '',
                    debug: debugEnabled && segment.debug ? JSON.stringify(segment.debug) : ''
                }
            });
        });

        return {
            type: 'FeatureCollection',
            features
        };
    }

    function buildRoutePointFeatures(route) {
        const features = [];
        const segments = Array.isArray(route.segments) ? route.segments : [];
        const debugEnabled = new URLSearchParams(window.location.search).get('debug') === '1';
        const seenStops = new Set();

        const pushPoint = (segmentIndex, kind, name, lon, lat, action, line = '', debug = '', extra = {}) => {
            if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
            features.push({
                type: 'Feature',
                geometry: {
                    type: 'Point',
                    coordinates: [lon, lat]
                },
                properties: {
                    segment_index: segmentIndex,
                    kind,
                    type: extra.type || kind,
                    name: name || 'Ponto',
                    action: action || '',
                    line: line || '',
                    debug: debugEnabled ? debug : '',
                    stop_id: extra.stop_id || '',
                    stop_name: extra.stop_name || name || 'Ponto',
                    arrival_time: extra.arrival_time || '',
                    departure_time: extra.departure_time || '',
                    wait_time: extra.wait_time ?? '',
                    from_line: extra.from_line || '',
                    to_line: extra.to_line || '',
                    from_trip_id: extra.from_trip_id || '',
                    to_trip_id: extra.to_trip_id || '',
                    instruction: extra.instruction || action || ''
                }
            });
        };

        segments.forEach((segment, index) => {
            const debugText = debugEnabled && segment.debug ? JSON.stringify(segment.debug) : '';
            (segment.markers || []).forEach((marker) => {
                const coordinates = marker.coordinates || [];
                const lon = Number(coordinates[0]);
                const lat = Number(coordinates[1]);
                pushPoint(
                    index,
                    marker.kind || 'point',
                    marker.name || segment.from_name || 'Ponto',
                    lon,
                    lat,
                    marker.action || '',
                    marker.line || segment.line || '',
                    debugText,
                    marker
                );
            });
        });

        (Array.isArray(route.stopsData) ? route.stopsData : []).forEach((stop, index) => {
            const lon = Number(stop.stop_lon);
            const lat = Number(stop.stop_lat);
            const stopId = String(stop.stop_id || `${lat},${lon}`);
            if (!Number.isFinite(lat) || !Number.isFinite(lon) || seenStops.has(stopId)) {
                return;
            }

            seenStops.add(stopId);
            pushPoint(
                -1,
                'stop',
                stop.stop_name || `Paragem ${index + 1}`,
                lon,
                lat,
                `Paragem ${index + 1} da rota`,
                stop.route_short_name || '',
                debugEnabled ? `stop_id=${stopId}` : '',
                {
                    type: 'stop',
                    stop_id: stopId,
                    stop_name: stop.stop_name || `Paragem ${index + 1}`
                }
            );
        });

        return {
            type: 'FeatureCollection',
            features
        };
    }

    function focusRouteSegment(segmentIndex, fitOnly = false) {
        const route = window.currentRouteForRedraw;
        if (!route || !Array.isArray(route.segments) || !route.segments[segmentIndex] || !map) return;
        const segment = route.segments[segmentIndex];
        const coordinates = getSegmentCoordinates(segment);
        if (coordinates.length < 2 || !ensureRouteSource()) return;

        map.getSource('route-segment-focus').setData({
            type: 'FeatureCollection',
            features: [{
                type: 'Feature',
                geometry: {
                    type: 'LineString',
                    coordinates
                },
                properties: {
                    segment_type: segment.type || 'bus'
                }
            }]
        });

        document.querySelectorAll('.segment-item').forEach((element) => {
            element.classList.toggle('active', Number(element.dataset.segmentIndex) === Number(segmentIndex));
        });

        if (fitOnly) {
            const bounds = new mapboxgl.LngLatBounds(coordinates[0], coordinates[0]);
            coordinates.forEach(coord => bounds.extend(coord));
            requestMapResize(30);
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            map.fitBounds(bounds, {
                padding: isMobile
                    ? { top: 48, right: 28, bottom: 180, left: 28 }
                    : { top: 70, right: 90, bottom: 90, left: 420 },
                maxZoom: isMobile ? 15.2 : 14.8
            });
        }
    }

    function drawRouteSegments(route, fitMap = true) {
        if (!map || !route) return;
        window.currentRouteForRedraw = route;

        if (!ensureRouteSource()) {
            runWhenStyleReady(() => drawRouteSegments(route, fitMap));
            return;
        }

        const debugEnabled = new URLSearchParams(window.location.search).get('debug') === '1';
        const routeSegmentsGeoJson = buildSegmentGeoJson(route, debugEnabled);
        const routePointsGeoJson = buildRoutePointFeatures(route);

        if (debugEnabled) {
            console.log('[UrbanTraffic] segmentos da rota:', (route.segments || []).map((segment, index) => ({
                index,
                type: segment.type,
                line: segment.line || '',
                trip_id: segment.trip_id || '',
                shape_id: segment.shape_id || '',
                points: getSegmentCoordinates(segment).length
            })));
        }

        map.getSource('selected-route').setData({
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: []
            }
        });

        map.getSource('route-segments').setData(routeSegmentsGeoJson);
        map.getSource('route-points').setData(routePointsGeoJson);
        map.getSource('route-segment-focus').setData({
            type: 'FeatureCollection',
            features: []
        });

        const routeBounds = Array.isArray(route.bounds) && route.bounds.length === 2 ? route.bounds : null;
        if (fitMap) {
            let bounds = null;
            if (routeBounds) {
                bounds = new mapboxgl.LngLatBounds(routeBounds[0], routeBounds[1]);
            } else if (routeSegmentsGeoJson.features[0]?.geometry?.coordinates?.[0]) {
                const firstCoord = routeSegmentsGeoJson.features[0].geometry.coordinates[0];
                bounds = new mapboxgl.LngLatBounds(firstCoord, firstCoord);
                routeSegmentsGeoJson.features.forEach(feature => feature.geometry.coordinates.forEach(coord => bounds.extend(coord)));
                routePointsGeoJson.features.forEach(feature => bounds.extend(feature.geometry.coordinates));
            }

            if (bounds) {
                requestMapResize(40);
                // Abrir mais o enquadramento para incluir painel, origem, destino, paragens e contexto visual.
                const isMobile = window.matchMedia('(max-width: 768px)').matches;
                map.fitBounds(bounds, {
                    padding: isMobile
                        ? { top: 28, right: 20, bottom: 220, left: 20 }
                        : { top: 72, right: 92, bottom: 92, left: 430 },
                    maxZoom: isMobile ? 15 : 14.6
                });
            }
        }
        requestMapResize(50);
        requestMapResize(220);
    }

    function clearWalkingRoute() {
        if (map && map.getSource('walking-route')) {
            map.getSource('walking-route').setData({
                type: 'Feature',
                geometry: {
                    type: 'LineString',
                    coordinates: []
                }
            });
        }
    }

    function buildWalkingLineFeature(fromLon, fromLat, toLon, toLat) {
        return {
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: [
                    [Number(fromLon), Number(fromLat)],
                    [Number(toLon), Number(toLat)]
                ]
            }
        };
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function calculateTotalTime() {
        return currentRoute ? currentRoute.duration : 0;
    }

    function showTripSummary() {
        if (!window.globalRoutes || globalRoutes.length === 0) {
            if (typeof window.showToast === 'function') {
                window.showToast('Selecione uma rota primeiro.', 'warning');
            }
            return;
        }

        const activeIndex = $('.route-card.active').data('route-index');
        const selectedRoute = globalRoutes[activeIndex] || globalRoutes[0];
        currentRoute = selectedRoute;
        window.inlineJourneyState.routeIndex = Number.isFinite(Number(activeIndex)) ? Number(activeIndex) : 0;

        const totalTime = calculateTotalTime();
        const walkDistance = currentRoute.walk_from?.distance || 0;
        const walkTime = currentRoute.walk_from?.time || 0;
        const busTime = Math.max(0, totalTime - walkTime);

        const summaryHtml = `
            <div class="route-inline-state">
                <div class="route-inline-state__header">
                    <h6 class="mb-1"><i class="fas fa-route me-2"></i>Trajeto pronto a iniciar</h6>
                    <small>${currentRoute.from} → ${currentRoute.to}</small>
                </div>
                <div class="route-inline-state__body">
                    <div class="route-inline-state__grid">
                        <div class="route-inline-state__metric"><small>Caminhada</small><strong>${walkDistance} m · ${walkTime} min</strong></div>
                        <div class="route-inline-state__metric"><small>Autocarro</small><strong>${busTime} min</strong></div>
                        <div class="route-inline-state__metric"><small>Total</small><strong>${totalTime} min</strong></div>
                    </div>
                    <div class="progress-bar-container mt-3">
                        <div class="progress-bar-fill" style="width: ${totalTime > 0 ? (walkTime / totalTime) * 100 : 0}%; background: #66BB6A;"></div>
                    </div>
                    <div class="route-inline-state__actions">
                        <button class="btn-nav btn-secondary-nav" onclick="exitNavigation()">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button class="btn-nav btn-primary-nav" onclick="startWalkingPhase()">
                            <i class="fas fa-play me-2"></i>Iniciar viagem
                        </button>
                    </div>
                </div>
            </div>
        `;

        setInlineJourneyState(summaryHtml, 'summary');
        currentPhase = 'summary';
    }

    async function getUserLocation() {
        return new Promise((resolve, reject) => {
            if (!canUseLocationSetting()) {
                reject('Localização desativada nas definições.');
                return;
            }

            if (!navigator.geolocation) {
                reject('Geolocalização não suportada');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lon: position.coords.longitude,
                        heading: position.coords.heading || 0
                    };
                    resolve(userLocation);
                },
                (error) => reject(error.message),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    async function drawWalkingRoute() {
        if (!currentRoute || !currentRoute.stopsData || !currentRoute.stopsData[0]) return null;
        if (!ensureRouteSource()) {
            runWhenStyleReady(() => drawWalkingRoute());
            return null;
        }

        const firstStop = currentRoute.stopsData[0];
        const stopLon = Number(firstStop.stop_lon);
        const stopLat = Number(firstStop.stop_lat);
        if (!Number.isFinite(stopLon) || !Number.isFinite(stopLat)) {
            return null;
        }

        map.getSource('walking-route').setData(
            buildWalkingLineFeature(userLocation.lon, userLocation.lat, stopLon, stopLat)
        );

        remainingWalkDistance = calculateDistance(userLocation.lat, userLocation.lon, stopLat, stopLon);
        return {
            distance: remainingWalkDistance,
            duration: Math.ceil(remainingWalkDistance / 80)
        };
    }

    async function startWalkingPhase() {
        showNotification('📍 A obter localização...', 'info');

        try {
            await getUserLocation();

            if (userMarker) userMarker.remove();

            userMarker = new mapboxgl.Marker({ element: createUserMarkerElement() })
                .setLngLat([userLocation.lon, userLocation.lat])
                .setPopup(new mapboxgl.Popup({ offset: 18 }).setText('📍 A sua localização'))
                .addTo(map);

            map.flyTo({
                center: [userLocation.lon, userLocation.lat],
                zoom: 17
            });

            await drawWalkingRoute();
            startLocationTracking();
            showWalkingPanel();

            currentPhase = 'walking';
        } catch (error) {
            console.warn('Erro:', error);
            showNotification('Erro ao obter localização. Verifique as permissões.', 'error');
        }
    }

    function startLocationTracking() {
        if (!canUseLocationSetting()) {
            return;
        }

        if (watchPositionId) navigator.geolocation.clearWatch(watchPositionId);

        watchPositionId = navigator.geolocation.watchPosition(
            (position) => {
                userLocation = {
                    lat: position.coords.latitude,
                    lon: position.coords.longitude,
                    heading: position.coords.heading || 0
                };

                if (userMarker) {
                    userMarker.setLngLat([userLocation.lon, userLocation.lat]);
                }

                map.easeTo({
                    center: [userLocation.lon, userLocation.lat],
                    zoom: 17,
                    duration: 800
                });

                updateRemainingWalkDistance();
                checkIfArrivedAtStop();
            },
            null,
            { enableHighAccuracy: true }
        );
    }

    function updateRemainingWalkDistance() {
        if (!currentRoute || !currentRoute.stopsData || !currentRoute.stopsData[0] || !userLocation) return;

        const firstStop = currentRoute.stopsData[0];
        const newDistance = calculateDistance(userLocation.lat, userLocation.lon, firstStop.stop_lat, firstStop.stop_lon);
        remainingWalkDistance = newDistance;

        $('#walkDistanceRemaining').text(`${Math.round(remainingWalkDistance)} m`);

        const totalWalkDistance = currentRoute.walk_from?.distance || remainingWalkDistance || 1;
        const progress = ((totalWalkDistance - remainingWalkDistance) / totalWalkDistance) * 100;
        $('.progress-bar-fill').css('width', `${Math.max(0, progress)}%`);
    }

    function checkIfArrivedAtStop() {
        if (!currentRoute || !currentRoute.stopsData || !currentRoute.stopsData[0] || !userLocation) return;

        const firstStop = currentRoute.stopsData[0];
        const distanceToStop = calculateDistance(userLocation.lat, userLocation.lon, firstStop.stop_lat, firstStop.stop_lon);

        if (distanceToStop < 30) {
            showNotification('Chegou à paragem! O autocarro está a chegar.', 'success');
            stopLocationTracking();
            startBusPhase();
        }
    }

    function showWalkingPanel() {
        const firstStop = currentRoute.stopsData[0];

        const walkingHtml = `
            <div class="route-inline-state">
                <div class="route-inline-state__header">
                    <h6 class="mb-1"><i class="fas fa-walking me-2"></i>A caminho da paragem</h6>
                    <small>${firstStop.stop_name}</small>
                </div>
                <div class="route-inline-state__body">
                    <div class="instruction-step">
                        <div class="instruction-icon"><i class="fas fa-arrow-right"></i></div>
                        <div class="instruction-text">
                            <strong>Siga em frente</strong>
                            <div class="instruction-distance">Direção à paragem</div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 0%;"></div>
                    </div>
                    <div class="text-center my-3">
                        <div class="eta-countdown" id="walkDistanceRemaining">${Math.round(remainingWalkDistance)} m</div>
                        <small>restantes</small>
                    </div>
                    <div class="route-inline-state__actions">
                        <button class="btn-nav btn-secondary-nav" onclick="exitNavigation()">
                            <i class="fas fa-times me-2"></i>Sair
                        </button>
                    </div>
                </div>
            </div>
        `;

        setInlineJourneyState(walkingHtml, 'walking');
    }

    function stopLocationTracking() {
        if (watchPositionId) {
            navigator.geolocation.clearWatch(watchPositionId);
            watchPositionId = null;
        }
    }

    function startBusPhase() {
        showNotification('A entrar no autocarro...', 'info');
        currentTripId = currentRoute.primary_trip_id || currentRoute._primary_trip_id || currentRoute.trip_id;
        startBusTracking();
        showBusPanel();
        currentPhase = 'bus';
    }

    function showBusPanel() {
        const busHtml = `
            <div class="route-inline-state">
                <div class="route-inline-state__header">
                    <h6 class="mb-1"><i class="fas fa-bus me-2"></i>Linha ${currentRoute.line}</h6>
                    <small>${currentRoute.from} → ${currentRoute.to}</small>
                </div>
                <div class="route-inline-state__body">
                    <div class="text-center mb-3">
                        <div class="eta-countdown" id="busEta">A calcular...</div>
                        <small>para a próxima paragem</small>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="busProgress" style="width: 0%;"></div>
                    </div>
                    <strong><i class="fas fa-map-marker-alt me-2"></i>Próximas paragens:</strong>
                    <ul class="next-stops-list" id="nextStopsList"></ul>
                    <div class="route-inline-state__actions">
                        <button class="btn-nav btn-secondary-nav" onclick="exitNavigation()">
                            <i class="fas fa-times me-2"></i>Sair
                        </button>
                    </div>
                </div>
            </div>
        `;

        setInlineJourneyState(busHtml, 'bus');
        updateNextStops();
    }

    function updateNextStops() {
        if (!currentRoute || !currentRoute.stopsData) return;

        const stops = currentRoute.stopsData.slice(1, 6);
        let html = '';

        stops.forEach((stop, idx) => {
            const eta = idx === 0 ? 'Próxima' : `${idx * 3} min`;
            html += `
                <li>
                    <span><i class="fas fa-circle me-2" style="font-size: 8px; color: #4CAF50;"></i>${stop.stop_name}</span>
                    <span class="stop-eta">${eta}</span>
                </li>
            `;
        });

        $('#nextStopsList').html(html);
    }

    function startBusTracking() {
        if (!currentTripId) return;

        fetchBusPosition();

        if (busTrackingInterval) clearInterval(busTrackingInterval);
        busTrackingInterval = setInterval(fetchBusPosition, getRealtimeRefreshMs());

        if (countdownInterval) clearInterval(countdownInterval);

        let countdown = 120;
        countdownInterval = setInterval(() => {
            if (currentPhase === 'bus' && countdown > 0) {
                countdown--;
                const minutes = Math.floor(countdown / 60);
                const seconds = countdown % 60;
                $('#busEta').text(`${minutes}:${seconds.toString().padStart(2, '0')}`);
            }
        }, 1000);
    }

    async function fetchBusPosition() {
        try {
            const response = await fetch(`/urban/public/api/realtime?action=vehicle&trip_id=${currentTripId}`);
            const data = await response.json();

            if (data.status === 'success' && data.available === false) {
                showNoBusMessage(data.message || 'Sem veículo realtime disponível para esta viagem');
                return;
            }

            if (data.status === 'success' && data.data) {
                updateBusPosition(data.data);
                updateBusProgress(data.data);
            }
        } catch (error) {
            console.warn('Erro ao buscar autocarro:', error);
        }
    }

    function updateBusPosition(vehicle, moveCamera = true) {
        if (!vehicle.latitude || !vehicle.longitude) return;

        window._currentBusData = vehicle;

        const selectedVehicle = { ...vehicle, __selected: true };
        const target = [Number(vehicle.longitude), Number(vehicle.latitude)];

        if (currentBusMarkerRecord) {
            updateMarkerRecord(currentBusMarkerRecord, selectedVehicle, target, 10000);
            currentBusMarker = currentBusMarkerRecord.marker;
        } else {
            currentBusMarker = new mapboxgl.Marker({
                element: createMapboxMarker('#2E7D32', 32)
            })
            .setLngLat(target)
            .addTo(map);

            setMarkerPopup(currentBusMarker, selectedVehicle);
            currentBusMarkerRecord = {
                marker: currentBusMarker,
                vehicle: selectedVehicle,
                lngLat: target,
                animationFrame: null
            };
        }

        if (moveCamera) {
            map.easeTo({
                center: target,
                zoom: 15,
                duration: 1000
            });
        }

        const lastStop = currentRoute.stopsData[currentRoute.stopsData.length - 1];
        const distanceToDest = calculateDistance(vehicle.latitude, vehicle.longitude, lastStop.stop_lat, lastStop.stop_lon);

        if (distanceToDest < 100) {
            showNotification('🎉 A chegar ao destino!', 'success');
            setTimeout(() => completeNavigation(), 10000);
        }
    }

    function updateBusProgress(vehicle) {
        if (!currentRoute || !currentRoute.stopsData) return;

        let minDistance = Infinity;
        let closestStopIndex = 0;

        currentRoute.stopsData.forEach((stop, idx) => {
            const dist = calculateDistance(vehicle.latitude, vehicle.longitude, stop.stop_lat, stop.stop_lon);
            if (dist < minDistance) {
                minDistance = dist;
                closestStopIndex = idx;
            }
        });

        const progress = (closestStopIndex / (currentRoute.stopsData.length - 1)) * 100;
        $('#busProgress').css('width', `${progress}%`);

        if (closestStopIndex + 1 < currentRoute.stopsData.length) {
            const nextStop = currentRoute.stopsData[closestStopIndex + 1];
            const distToNext = calculateDistance(vehicle.latitude, vehicle.longitude, nextStop.stop_lat, nextStop.stop_lon);
            const etaMinutes = Math.ceil(distToNext / 500);
            $('#busEta').text(`${etaMinutes} min`);
        }
    }

    function stopBusTracking() {
        if (busTrackingInterval) {
            clearInterval(busTrackingInterval);
            busTrackingInterval = null;
        }

        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }

        if (currentBusMarker) {
            if (currentBusMarkerRecord?.animationFrame) {
                cancelAnimationFrame(currentBusMarkerRecord.animationFrame);
            }
            currentBusMarker.remove();
            currentBusMarker = null;
        }
        currentBusMarkerRecord = null;
    }

    function updateMarkerRecord(record, vehicle, target, duration = 10000) {
        record.vehicle = vehicle;
        setMarkerPopup(record.marker, vehicle);
        animateMarkerTo(record, vehicle, target, duration);
    }

    async function fetchBestVehicleForRoute(routeInfo) {
        const routeId = routeInfo?.route_id || routeInfo?.route_name || routeInfo?.line || '';
        const routeName = routeInfo?.route_name || routeInfo?.line || '';
        const tripId = routeInfo?.primary_trip_id || routeInfo?._primary_trip_id || routeInfo?.trip_id || '';
        const anchorStop = Array.isArray(routeInfo?.stopsData) && routeInfo.stopsData.length > 0 ? routeInfo.stopsData[0] : null;

        if (tripId) {
            try {
                const vehicleResponse = await fetch(`/urban/public/api/realtime?action=vehicle&trip_id=${encodeURIComponent(tripId)}`);
                const vehicleData = await vehicleResponse.json();
                if (vehicleData.status === 'success' && vehicleData.data) {
                    return vehicleData.data;
                }
            } catch (error) {
                console.warn('Fallback para busca por rota após falha por trip_id:', error);
            }
        }

        const query = new URLSearchParams({
            action: 'route',
            route_id: routeId,
            route_name: routeName,
            trip_id: tripId
        });
        if (anchorStop?.stop_lat && anchorStop?.stop_lon) {
            query.set('lat', String(anchorStop.stop_lat));
            query.set('lon', String(anchorStop.stop_lon));
        }

        const response = await fetch(`/urban/public/api/realtime?${query.toString()}`);
        const data = await response.json();
        if (data.status === 'success' && data.data) {
            return Array.isArray(data.data) ? (data.data[0] || null) : data.data;
        }

        return null;
    }

    async function showBusForRoute(routeInfo) {
        if (!routeInfo) return;

        const routeId = typeof routeInfo === 'object'
            ? (routeInfo.route_id || routeInfo.route_name || routeInfo.line)
            : routeInfo;
        const routeName = typeof routeInfo === 'object'
            ? (routeInfo.route_name || routeInfo.line)
            : routeInfo;
        const tripId = typeof routeInfo === 'object'
            ? (routeInfo.primary_trip_id || routeInfo._primary_trip_id || routeInfo.trip_id)
            : null;
        const anchorStop = typeof routeInfo === 'object' && Array.isArray(routeInfo.stopsData) && routeInfo.stopsData.length > 0
            ? routeInfo.stopsData[0]
            : null;

        if (!routeId && !routeName && !tripId) return;
        selectedRouteId = routeId || routeName || tripId;
        resetSelectedBusAlertState(routeInfo);

        let vehicleFound = null;
        let etaFound = null;

        try {
            [vehicleFound, etaFound] = await Promise.all([
                fetchBestVehicleForRoute(routeInfo),
                (routeInfo.stopsData?.[0]?.stop_id && tripId)
                    ? window.fetchRealtimeETA(tripId, routeInfo.stopsData[0].stop_id)
                    : Promise.resolve(null)
            ]);
        } catch (error) {
            console.warn('Erro ao buscar veículo para a rota selecionada:', error);
        }

        syncRealtimeStatus(routeInfo, etaFound);

        if (vehicleFound) {
            updateSelectedBusMarker(vehicleFound);
            maybeNotifyBusApproach(routeInfo, vehicleFound);

            if (selectedRouteBusInterval) clearInterval(selectedRouteBusInterval);
            selectedRouteBusInterval = setInterval(() => {
                fetchUpdatedBusPosition(routeInfo);
            }, Math.max(5000, getRealtimeRefreshMs()));
        } else {
            showNoBusMessage();
        }
    }

    async function fetchUpdatedBusPosition(routeInfo) {
        try {
            const tripId = routeInfo?.primary_trip_id || routeInfo?._primary_trip_id || routeInfo?.trip_id || '';
            const [vehicle, eta] = await Promise.all([
                fetchBestVehicleForRoute(routeInfo),
                (routeInfo?.stopsData?.[0]?.stop_id && tripId)
                    ? window.fetchRealtimeETA(tripId, routeInfo.stopsData[0].stop_id)
                    : Promise.resolve(null)
            ]);

            syncRealtimeStatus(routeInfo, eta);

            if (vehicle) {
                updateSelectedBusMarker(vehicle);
                maybeNotifyBusApproach(routeInfo, vehicle);

                if (userLocation) {
                    const distance = calculateDistance(
                        userLocation.lat, userLocation.lon,
                        vehicle.latitude, vehicle.longitude
                    );
                    showBusDistance(distance);
                }
            } else {
                showNoBusMessage();
            }
        } catch (error) {
            console.warn('Erro ao atualizar autocarro:', error);
        }
    }

    function updateSelectedBusMarker(vehicle) {
        if (!map || !vehicle.latitude || !vehicle.longitude) return;

        const selectedVehicle = { ...vehicle, __selected: true };
        window._selectedBusData = selectedVehicle;
        const target = [Number(vehicle.longitude), Number(vehicle.latitude)];

        if (selectedRouteMarkerRecord) {
            updateMarkerRecord(selectedRouteMarkerRecord, selectedVehicle, target, 4500);
            selectedRouteBusMarker = selectedRouteMarkerRecord.marker;
            if (currentPhase === 'bus') {
                map.easeTo({
                    center: target,
                    zoom: Math.max(14.5, map.getZoom()),
                    duration: 1200
                });
            }
            return;
        }

        selectedRouteBusMarker = new mapboxgl.Marker({
            element: createMapboxMarker('#2E7D32', 32)
        })
        .setLngLat(target)
        .addTo(map);

        setMarkerPopup(selectedRouteBusMarker, selectedVehicle);
        selectedRouteMarkerRecord = {
            marker: selectedRouteBusMarker,
            vehicle: selectedVehicle,
            lngLat: target,
            animationFrame: null
        };

        map.easeTo({
            center: target,
            zoom: Math.max(14.5, map.getZoom()),
            duration: 1200
        });
    }

    function showBusDistance(distance) {
        const distanceText = distance < 1000 ? `${Math.round(distance)} m` : `${(distance / 1000).toFixed(1)} km`;
        $('#nextBus').text(distanceText);
        const activeRouteIndex = Number(window.activeRouteIndex || 0);
        $(`[data-route-next="${activeRouteIndex}"]`).text(distanceText);
    }

    function showNoBusMessage(message = 'Sem veículo realtime disponível para esta viagem') {
        $('#nextBus').text(message);
        const activeRouteIndex = Number(window.activeRouteIndex || 0);
        $(`[data-route-next="${activeRouteIndex}"]`).text(message);
    }

    function stopSelectedBusTracking() {
        if (selectedRouteBusInterval) {
            clearInterval(selectedRouteBusInterval);
            selectedRouteBusInterval = null;
        }

        if (selectedRouteBusMarker) {
            if (selectedRouteMarkerRecord?.animationFrame) {
                cancelAnimationFrame(selectedRouteMarkerRecord.animationFrame);
            }
            selectedRouteBusMarker.remove();
            selectedRouteBusMarker = null;
        }
        selectedRouteMarkerRecord = null;
    }

    async function getUserLocationForBus() {
        return new Promise((resolve, reject) => {
            if (userLocation) {
                resolve(userLocation);
                return;
            }

            if (!canUseLocationSetting()) {
                reject('Localização desativada nas definições.');
                return;
            }

            if (!navigator.geolocation) {
                reject('Geolocalização não suportada');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lon: position.coords.longitude,
                        heading: position.coords.heading || 0
                    };

                    if (!userMarker) {
                        userMarker = new mapboxgl.Marker({ element: createUserMarkerElement() })
                            .setLngLat([userLocation.lon, userLocation.lat])
                            .setPopup(new mapboxgl.Popup({ offset: 18 }).setText('📍 A sua localização'))
                            .addTo(map);
                    } else {
                        userMarker.setLngLat([userLocation.lon, userLocation.lat]);
                    }

                    resolve(userLocation);
                },
                (error) => reject(error.message),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    const originalSelectRoute = window.selectRoute;
    window.selectRoute = function(index) {
        if (originalSelectRoute) originalSelectRoute(index);

        const route = window.globalRoutes ? globalRoutes[index] : null;
        if (route) {
            try {
                drawRouteSegments(route);
            } catch (error) {
                console.warn('Erro ao redesenhar rota selecionada:', error);
                window.pendingRouteIndex = index;
            }
        }
        requestMapResize(30);
        requestMapResize(180);
        if (route && (route.route_id || route.route_name || route.line || route.primary_trip_id)) {
            showBusForRoute(route);
            getUserLocationForBus().catch(err => console.log('Localização não disponível:', err));
        }
    };

    $(window).on('beforeunload', function() {
        stopSelectedBusTracking();
    });

    function showNotification(message, type = 'info') {
        if (!canUseNotificationsSetting()) {
            if (type === 'error') {
                console.warn(message);
            }
            return;
        }

        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
        const notification = $(`
            <div class="notification-toast">
                <span>${icon}</span>
                <span>${message}</span>
            </div>
        `).appendTo('body');

        setTimeout(() => notification.fadeOut(300, () => notification.remove()), 4000);
    }

    function exitNavigation(silent = false) {
        currentPhase = 'summary';
        clearInlineJourneyState();

        stopLocationTracking();
        stopBusTracking();
        stopSelectedBusTracking();

        if (userMarker) {
            userMarker.remove();
            userMarker = null;
        }

        clearWalkingRoute();
        currentTripId = null;

        if (!silent && typeof window.showToast === 'function') {
            window.showToast('Trajeto parado.', 'info');
        }
    }

    function completeNavigation() {
        showNotification('🎉 Chegou ao destino! Obrigado por utilizar o UrbanTraffic.', 'success');
        exitNavigation();
    }

    function startRealtimeUpdates() {
        if (window.vehicleUpdateInterval) clearInterval(window.vehicleUpdateInterval);
        $('#lastUpdate').text(new Date().toLocaleTimeString('pt-PT'));
        updateVehicles();
        window.vehicleUpdateInterval = setInterval(() => {
            $('#lastUpdate').text(new Date().toLocaleTimeString('pt-PT'));
            updateVehicles();
        }, getRealtimeRefreshMs());
    }

    $('#mapViewDefault').on('click', function() {
        if (currentLayerType !== 'mapbox') {
            toggleMapLayer('mapbox');
        }
    });

    $('#mapViewSatellite').on('click', function() {
        if (currentLayerType !== 'satellite') {
            toggleMapLayer('satellite');
        }
    });

    $(document).ready(function() {
        const params = new URLSearchParams(window.location.search);
        const fromLat = params.get('fromLat');
        const fromLon = params.get('fromLon');
        const toLat = params.get('toLat');
        const toLon = params.get('toLon');
        const origin = params.get('origin');
        const dest = params.get('dest');
        const travelDate = params.get('travelDate');
        const departureTime = params.get('departureTime');

        $('#journeyMetaDisplay').text(formatJourneyMeta(travelDate, departureTime));

        if (fromLat && fromLon && toLat && toLon) {
            $('#originDisplay').text(`${fromLat}, ${fromLon}`);
            $('#destDisplay').text(`${toLat}, ${toLon}`);
        } else if (origin && dest) {
            $('#originDisplay').text(decodeURIComponent(origin));
            $('#destDisplay').text(decodeURIComponent(dest));
        } else {
            $('#routesList').html(`<div class="alert alert-warning">Nenhuma rota especificada.</div>`);
        }

        initMap();
    });

    window.mapboxDrawRoute = drawRouteSegments;
    window.focusRouteSegment = focusRouteSegment;
    window.addEventListener('load', () => requestMapResize(0));
    window.addEventListener('resize', () => requestMapResize(0));

    window.showTripSummary = showTripSummary;
    window.startWalkingPhase = startWalkingPhase;
    window.exitNavigation = exitNavigation;
    </script>
</body>
</html>
