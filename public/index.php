<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Planear viagem</title>
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
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        :root {
            --verde-urbano: #4CAF50;
            --cinza-urbano: #5A6B7A;
            --cinza-claro: #E8ECF1;
            --preto-suave: #2C3E50;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            color: var(--preto-suave);
            background: var(--cinza-claro);
        }
        
        .btn-urbano {
            background: var(--verde-urbano);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-urbano:hover {
            background: #3d8b40;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-outline-urbano {
            background: transparent;
            color: var(--verde-urbano);
            border: 2px solid var(--verde-urbano);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-outline-urbano:hover {
            background: var(--verde-urbano);
            color: white;
        }
        
        .card-urbano {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            background: white;
        }
        
        .suggestions-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--cinza-claro);
            border-radius: 0 0 10px 10px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .suggestion-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--cinza-claro);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .suggestion-item:hover {
            background: var(--verde-urbano);
            color: white;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--verde-urbano) 0%, #2E7D32 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .data-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
        }
        
        .footer-urbano {
            background: var(--preto-suave);
            color: white;
        }
        
        .location-badge {
            background: rgba(76, 175, 80, 0.1);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        .time-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .time-input-group input {
            flex: 1;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="ut-page ut-page-home">

    <!-- Navbar -->
    <?php include 'partials/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section py-5">
        <div class="container text-center py-3">
            <h1 class="display-4 fw-bold mb-3" data-i18n="indexHeroTitle">Planeie a sua viagem</h1>
            <p class="lead mb-4" data-i18n="indexHeroSubtitle">Origem, destino e horários numa experiência rápida, clara e pronta para o dia a dia.</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <span class="data-badge">
                    <i class="fas fa-signal me-2"></i><span data-i18n="indexRealtimeData">Tempo real</span>
                </span>
                <span class="data-badge">
                    <i class="fas fa-database me-2"></i>Dados oficiais
                </span>
                <span class="data-badge">
                    <i class="fas fa-mobile-screen me-2"></i>Mobile
                </span>
            </div>
        </div>
    </section>

    <!-- Painel de pesquisa -->
    <section class="container my-5 ut-search-shell">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="card card-urbano ut-card">
                    <div class="card-header bg-white border-0 pt-4">
                        <h4 class="mb-0">
                            <i class="fas fa-route me-2" style="color: var(--verde-urbano);"></i>
                            <span data-i18n="indexPlanTrip">Planeie a sua viagem</span>
                        </h4>
                        <div class="ut-search-meta">
                            <span class="ut-badge ut-badge-primary"><i class="fas fa-bolt"></i> Pesquisa inteligente</span>
                            <span class="ut-badge ut-badge-live"><i class="fas fa-signal"></i> Dados em tempo real</span>
                            <span class="ut-badge ut-badge-warning"><i class="fas fa-mobile-screen"></i> Preparado para mobile</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <!-- Origem com localização atual -->
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-map-marker-alt me-2" style="color: var(--verde-urbano);"></i>
                                <span data-i18n="indexOrigin">Origem</span>
                            </label>
                            <div class="input-group">
                                <div class="ut-input-shell flex-grow-1">
                                    <i class="fas fa-location-dot ut-input-icon"></i>
                                    <input class="form-control form-control-lg ut-input" id="origin" type="text" 
                                    placeholder="Ex: Parque das Nações, Cacém, Almada..."
                                    autocomplete="off" required minlength="3" maxlength="120">
                                </div>
                                <button class="btn btn-outline-urbano ut-btn ut-btn-secondary" type="button" id="useMyLocationBtn" onclick="getMyLocation()">
                                    <i class="fas fa-location-dot me-1"></i> Minha Localização
                                </button>
                            </div>
                            <div class="invalid-feedback" id="originFeedback">Indique uma origem válida com pelo menos 3 caracteres.</div>
                            <div id="originSuggestions" class="suggestions-box"></div>
                            <div id="locationStatus" class="location-badge ut-alert ut-alert-inline ut-alert-info" style="display: none;">
                                <i class="fas fa-circle-notch fa-spin me-1"></i> A obter localização...
                            </div>
                        </div>

                        <!-- Destino -->
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-flag-checkered me-2" style="color: var(--verde-urbano);"></i>
                                <span data-i18n="indexDestination">Destino</span>
                            </label>
                            <div class="ut-input-shell">
                                <i class="fas fa-flag-checkered ut-input-icon"></i>
                                <input class="form-control form-control-lg ut-input" id="destination" type="text" 
                                    placeholder="Ex: Lisboa, Amadora, Montijo..."
                                    autocomplete="off" required minlength="3" maxlength="120">
                            </div>
                            <div class="invalid-feedback" id="destinationFeedback">Indique um destino válido com pelo menos 3 caracteres.</div>
                            <div id="destinationSuggestions" class="suggestions-box"></div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-calendar-alt me-2"></i><span data-i18n="indexDate">Data</span>
                                </label>
                                <input type="date" class="form-control ut-input" id="travelDate" 
                                       value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-clock me-2"></i>Horário de Partida
                                </label>
                                <div class="time-input-group">
                                    <input type="time" class="form-control ut-input" id="departureTime" 
                                           value="<?php echo date('H:i'); ?>" step="60" pattern="^([01]\\d|2[0-3]):[0-5]\\d$">
                                    <button class="btn btn-outline-urbano ut-btn ut-btn-secondary" type="button" onclick="setNowTime()" style="white-space: nowrap;">
                                        <i class="fas fa-clock me-1"></i> Agora
                                    </button>
                                </div>
                                <small class="text-muted">As rotas serão filtradas pelo horário selecionado</small>
                            </div>
                        </div>

                        <button class="btn btn-urbano ut-btn ut-btn-primary ut-btn-lg w-100" onclick="searchRoutes()" id="searchBtn">
                            <i class="fas fa-search me-2"></i><span data-i18n="indexSearch">Pesquisar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal de Autenticação -->
<div class="modal fade ut-auth-modal" id="authModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Autenticação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="ut-auth-intro">
                    <h6>Aceda à sua mobilidade</h6>
                    <p class="mb-0 small text-muted">Guarde favoritos, recupere histórico e personalize a experiência UrbanTraffic.</p>
                </div>
                <ul class="nav nav-tabs" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="loginTab" data-bs-toggle="tab" data-bs-target="#login" type="button">Login</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="registerTab" data-bs-toggle="tab" data-bs-target="#register" type="button">Criar conta</button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3">
                    <!-- Login Tab -->
                    <div class="tab-pane fade show active" id="login">
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="loginEmail" class="form-label">Email</label>
                                <input type="email" class="form-control ut-input" id="loginEmail" required maxlength="120" autocomplete="email">
                                <div class="invalid-feedback">Introduza um email válido.</div>
                            </div>
                            <div class="mb-3">
                                <label for="loginPassword" class="form-label">Password</label>
                                <input type="password" class="form-control ut-input" id="loginPassword" required minlength="6" maxlength="72" autocomplete="current-password">
                                <div class="invalid-feedback">Introduza a sua password.</div>
                            </div>
                            <button type="submit" class="btn btn-urbano ut-btn ut-btn-primary w-100">Entrar</button>
                        </form>
                        <p class="ut-auth-switch text-center mt-3 mb-0">
                            Ainda não tens conta?
                            <a href="#" data-auth-switch="register">Criar conta</a>
                        </p>
                    </div>
                    
                    <!-- Register Tab -->
                    <div class="tab-pane fade" id="register">
                        <form id="registerForm">
                            <div class="mb-3">
                                <label for="registerName" class="form-label">Nome</label>
                                <input type="text" class="form-control ut-input" id="registerName" required minlength="2" maxlength="80" autocomplete="name">
                                <div class="invalid-feedback">O nome deve ter entre 2 e 80 caracteres.</div>
                            </div>
                            <div class="mb-3">
                                <label for="registerEmail" class="form-label">Email</label>
                                <input type="email" class="form-control ut-input" id="registerEmail" required maxlength="120" autocomplete="email">
                                <div class="invalid-feedback">Introduza um email válido.</div>
                            </div>
                            <div class="mb-3">
                                <label for="registerPassword" class="form-label">Password</label>
                                <input type="password" class="form-control ut-input" id="registerPassword" required minlength="6" maxlength="72" autocomplete="new-password">
                                <div class="invalid-feedback">A password deve ter entre 6 e 72 caracteres.</div>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            <div class="mb-3">
                                <label for="registerConfirmPassword" class="form-label">Confirmar Password</label>
                                <input type="password" class="form-control ut-input" id="registerConfirmPassword" required minlength="6" maxlength="72" autocomplete="new-password">
                                <div class="invalid-feedback">A confirmação tem de coincidir com a password.</div>
                            </div>
                            <button type="submit" class="btn btn-urbano ut-btn ut-btn-primary w-100">Criar conta</button>
                        </form>
                        <p class="ut-auth-switch text-center mt-3 mb-0">
                            Já tens conta?
                            <a href="#" data-auth-switch="login">Entrar</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secção de Favoritos e Histórico -->
<section class="container my-5" id="userDataSection" style="display: none;">
    <div class="row">
        <div class="col-md-6">
            <div class="card card-urbano ut-card">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">
                        <i class="fas fa-heart me-2" style="color: var(--verde-urbano);"></i>
                        Rotas Favoritas
                    </h5>
                </div>
                <div class="card-body">
                    <div id="favoritesPageList">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                            <p>A carregar favoritos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-urbano ut-card">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2" style="color: var(--verde-urbano);"></i>
                        Histórico de Pesquisas
                    </h5>
                </div>
                <div class="card-body">
                    <div id="historyPageList">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                            <p>A carregar histórico...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container mt-4 mb-2">
    <div class="ut-panel text-center">
        <small class="text-muted">
            Dados oficiais Carris Metropolitana · GTFS + Realtime · atualização a cada 30s
        </small>
    </div>
</section>

<!-- Footer -->
<?php include 'partials/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/preferences.js?v=20260427b"></script>
    <script src="js/search.js?v=20260427d"></script>
    <script src="js/auth.js?v=20260427h"></script>
    
    <script>
        let userLocation = null;
        
        // Obter localização do utilizador
        function getMyLocation() {
            const statusDiv = document.getElementById('locationStatus');
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i> A obter localização...';

            if (window.UrbanPreferences?.canUseLocation?.() === false) {
                statusDiv.innerHTML = '<i class="fas fa-location-slash me-1"></i> Localização desativada nas definições.';
                setTimeout(() => statusDiv.style.display = 'none', 3000);
                return;
            }
            
            if (!navigator.geolocation) {
                statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Geolocalização não suportada';
                setTimeout(() => statusDiv.style.display = 'none', 3000);
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    userLocation = {
                        lat: position.coords.latitude,
                        lon: position.coords.longitude
                    };
                    
                    reverseGeocode(userLocation.lat, userLocation.lon);
                },
                function(error) {
                    let errorMsg = '';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Permissão negada. Ative a localização nas definições.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Localização indisponível.';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Tempo esgotado. Tente novamente.';
                            break;
                        default:
                            errorMsg = 'Erro ao obter localização.';
                    }
                    statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + errorMsg;
                    setTimeout(() => statusDiv.style.display = 'none', 3000);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
        
        // Converter coordenadas para endereço
        async function reverseGeocode(lat, lon) {
            const statusDiv = document.getElementById('locationStatus');
            
            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`
                );
                const data = await response.json();
                
                let locationName = '';
                if (data.address) {
                    locationName = data.address.road || 
                                   data.address.suburb || 
                                   data.address.city_district ||
                                   data.address.city ||
                                   data.address.town ||
                                   'Localização atual';
                    
                    if (data.address.house_number) {
                        locationName += `, ${data.address.house_number}`;
                    }
                } else {
                    locationName = `Localização (${lat.toFixed(4)}, ${lon.toFixed(4)})`;
                }
                
                document.getElementById('origin').value = locationName;
                
                window.selectedOriginCoords = { lat: lat, lon: lon };
                
                statusDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Localização obtida: ' + locationName;
                setTimeout(() => statusDiv.style.display = 'none', 2000);
                
            } catch (error) {
                console.warn('Erro no reverse geocoding:', error);
                document.getElementById('origin').value = `${lat.toFixed(4)}, ${lon.toFixed(4)}`;
                window.selectedOriginCoords = { lat: lat, lon: lon };
                statusDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Coordenadas obtidas';
                setTimeout(() => statusDiv.style.display = 'none', 2000);
            }
        }
        
        // Definir horário para "agora"
        function setNowTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('departureTime').value = `${hours}:${minutes}`;
        }
        
        // Inicializar
        $(document).ready(function() {
            setNowTime();
        });
        
        window.getMyLocation = getMyLocation;
        window.setNowTime = setNowTime;
        window.searchRoutes = searchRoutes;
    </script>
</body>
</html>
