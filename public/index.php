<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Dados Carris Metropolitana</title>
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
        
        /* Botões */
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
        
        .card-urbano {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            background: white;
        }
        
        .card-urbano:hover {
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.1);
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
    </style>
</head>
<body>

    <!-- Navbar -->
    <?php include 'partials/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section py-5">
        <div class="container text-center py-4">
            <h1 class="display-4 fw-bold mb-3">Seu novo App de transportes</h1>
            <p class="lead mb-4">Informação em tempo real para uma mobilidade mais eficiente</p>
            <div class="d-flex justify-content-center gap-3">
                <span class="data-badge">
                    <i class="fas fa-database me-2"></i>Dados em Tempo Real
                </span>
                <span class="data-badge">
                    <i class="fas fa-map-marked-alt me-2"></i>15 Municípios
                </span>
                <span class="data-badge">
                    <i class="fas fa-clock me-2"></i>Atualização 30s
                </span>
            </div>
        </div>
    </section>

    <!--Paneil de pesquisa -->
    <section class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-urbano">
                    <div class="card-header bg-white border-0 pt-4">
                        <h4 class="mb-0">
                            
                            Planeie a sua viagem
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <!-- Origem -->
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-semibold">
                                Origem
                            </label>
                            <input class="form-control form-control-lg" id="origin" type="text" 
                                placeholder="Ex: Parque das Nações, Cacém, Almada..."
                                autocomplete="off">
                            <div id="originSuggestions" class="suggestions-box"></div>
                        </div>

                        <!-- Destino -->
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-semibold">
                                Destino
                            </label>
                            <input class="form-control form-control-lg" id="destination" type="text" 
                                placeholder="Ex: Lisboa, Amadora, Montijo..."
                                autocomplete="off">
                            <div id="destinationSuggestions" class="suggestions-box"></div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Data
                                </label>
                                <input type="date" class="form-control" id="travelDate" 
                                       value="2026-03-13">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-clock me-2"></i>Horário
                                </label>
                                <select class="form-select" id="time">
                                    <option value="now">Partir agora</option>
                                    <option value="later">Partir mais tarde</option>
                                </select>
                            </div>
                        </div>

                        <button class="btn btn-urbano w-100" onclick="searchRoutes()">
                            <i class="fas fa-search me-2"></i>Pesquisar Rotas
                        </button>
                    </div>
                    <div class="card-footer bg-white border-0 pb-3 text-center">
                       
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dados da Carris Metropolitana -->
    <section class="container my-5">
        <h2 class="text-center mb-4">
            <i class="fas fa-chart-bar me-2" style="color: var(--verde-urbano);"></i>
            Dados Atuais da Rede
        </h2>
        
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card card-urbano text-center p-4">
                    <i class="fas fa-bus fa-3x mb-3" style="color: var(--verde-urbano);"></i>
                    <h3 class="fw-bold" id="totalBuses">247</h3>
                    <p class="text-muted">Autocarros em circulação</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-urbano text-center p-4">
                    <i class="fas fa-route fa-3x mb-3" style="color: var(--verde-urbano);"></i>
                    <h3 class="fw-bold" id="totalLines">168</h3>
                    <p class="text-muted">Linhas ativas</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-urbano text-center p-4">
                    <i class="fas fa-map-pin fa-3x mb-3" style="color: var(--verde-urbano);"></i>
                    <h3 class="fw-bold" id="totalStops">4523</h3>
                    <p class="text-muted">Paragens</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-urbano text-center p-4">
                    <i class="fas fa-clock fa-3x mb-3" style="color: var(--verde-urbano);"></i>
                    <h3 class="fw-bold" id="updateTime">-</h3>
                    <p class="text-muted">Última atualização</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Como acedemos aos dados -->
    <section class="py-5" style="background: var(--cinza-claro);">
        <div class="container">
            <h2 class="text-center mb-5">Dados Abertos da Carris Metropolitana</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-urbano h-100 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-file-alt fa-2x me-3" style="color: var(--verde-urbano);"></i>
                            <h5 class="mb-0">GTFS Estático</h5>
                        </div>
                        <p class="text-muted small">Horários, paragens e rotas das linhas</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Horários programados</li>
                            <li><i class="fas fa-check text-success me-2"></i>Sequência de paragens</li>
                            <li><i class="fas fa-check text-success me-2"></i>Georreferenciação</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-urbano h-100 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-satellite fa-2x me-3" style="color: var(--verde-urbano);"></i>
                            <h5 class="mb-0">GTFS Realtime</h5>
                        </div>
                        <p class="text-muted small">Posição dos veículos em tempo real</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Localização dos autocarros</li>
                            <li><i class="fas fa-check text-success me-2"></i>Tempos de espera</li>
                            <li><i class="fas fa-check text-success me-2"></i>Alertas de serviço</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-urbano h-100 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-chart-line fa-2x me-3" style="color: var(--verde-urbano);"></i>
                            <h5 class="mb-0">Informação ao Utilizador</h5>
                        </div>
                        <p class="text-muted small">Interface intuitiva para planeamento</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Cálculo de rotas</li>
                            <li><i class="fas fa-check text-success me-2"></i>Alternativas de percurso</li>
                            <li><i class="fas fa-check text-success me-2"></i>Tempo de viagem</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sobre os dados -->
    <section class="container my-5">
        <div class="alert" style="background: rgba(76, 175, 80, 0.1); border-left: 4px solid var(--verde-urbano);">
            <div class="d-flex">
                <i class="fas fa-info-circle fa-2x me-3" style="color: var(--verde-urbano);"></i>
                <div>
                    <h5>Sobre os dados apresentados</h5>
                    <p class="mb-0">Toda a informação é fornecida diretamente pela Carris Metropolitana através dos seus feeds oficiais de dados abertos (GTFS e GTFS-Realtime). Atualizamos a informação a cada 30 segundos para garantir a máxima precisão.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'partials/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/search.js"></script>
    
    <script>
        // Simular dados em tempo real da Carris Metropolitana
        function updateCarrisData() {
            const buses = Math.floor(Math.random() * 50) + 200;
            const time = new Date().toLocaleTimeString();
            
            $('#totalBuses').text(buses);
            $('#totalLines').text('168');
            $('#totalStops').text('4523');
            $('#updateTime').text(time);
        }
        
        setInterval(updateCarrisData, 30000);
        updateCarrisData();
    </script>
</body>
</html>