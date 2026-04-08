<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Linhas Carris Metropolitana</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        :root {
            --verde-urbano: #4CAF50;
            --cinza-urbano: #5A6B7A;
            --cinza-claro: #E8ECF1;
            --preto-suave: #2C3E50;
            --laranja-carris: #ff8c00;
        }
        
        /* Header com gradiente usando nossas cores */
        .page-header {
            background: linear-gradient(135deg, var(--verde-urbano) 0%, #45a049 100%);
            color: white;
            padding: 40px 0;
        }
        
        /* Logo personalizado */
        .logo-icon {
            position: relative;
            width: 30px;
            height: 40px;
            margin-right: 10px;
        }
        
        .pin-shape {
            position: absolute;
            width: 100%;
            height: 100%;
            background: var(--verde-urbano);
            clip-path: polygon(0% 0%, 100% 0%, 100% 70%, 50% 100%, 0% 70%);
        }
        
        .buildings {
            position: absolute;
            top: 6px;
            left: 6px;
            right: 6px;
            height: 12px;
            background: var(--cinza-urbano);
            clip-path: polygon(0% 0%, 20% 0%, 20% 100%, 40% 100%, 40% 0%, 60% 0%, 60% 100%, 80% 100%, 80% 0%, 100% 0%, 100% 100%, 0% 100%);
        }
        
        .road-line {
            position: absolute;
            top: 22px;
            left: 4px;
            right: 4px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transform: rotate(-5deg);
        }
        
        .traffic-light {
            position: absolute;
            right: -4px;
            top: 8px;
            width: 6px;
            height: 16px;
            background: var(--preto-suave);
            border-radius: 2px;
        }
        
        .green-light {
            position: absolute;
            right: -2px;
            top: 10px;
            width: 3px;
            height: 3px;
            background: var(--verde-urbano);
            border-radius: 50%;
            box-shadow: 0 0 5px var(--verde-urbano);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .signal-waves {
            position: absolute;
            right: -12px;
            top: 0;
            width: 12px;
            height: 12px;
            border: 2px solid var(--verde-urbano);
            border-radius: 50%;
            opacity: 0.5;
        }
        
        .signal-waves:after {
            content: '';
            position: absolute;
            right: -4px;
            top: -4px;
            width: 16px;
            height: 16px;
            border: 2px solid var(--verde-urbano);
            border-radius: 50%;
            opacity: 0.3;
        }
        
        .map-base {
            position: absolute;
            bottom: 4px;
            left: 4px;
            right: 4px;
            height: 6px;
            background: repeating-linear-gradient(45deg, var(--cinza-claro) 0px, var(--cinza-claro) 4px, var(--cinza-urbano) 4px, var(--cinza-urbano) 8px);
            border-radius: 2px;
        }
        
        /* Cards de linhas */
        .line-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--verde-urbano);
            cursor: pointer;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .line-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.2);
        }
        
        .badge-area {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 20px;
        }
        
        .area-1 { background: #4361ee; color: white; }
        .area-2 { background: #06d6a0; color: white; }
        .area-3 { background: #ffd166; color: var(--preto-suave); }
        .area-4 { background: #ef476f; color: white; }
        
        .btn-verde {
            background: var(--verde-urbano);
            color: white;
            border: none;
        }
        
        .btn-verde:hover {
            background: #45a049;
            color: white;
        }
        
        .btn-outline-verde {
            border: 1px solid var(--verde-urbano);
            color: var(--verde-urbano);
            background: transparent;
        }
        
        .btn-outline-verde:hover,
        .btn-outline-verde.active {
            background: var(--verde-urbano);
            color: white;
        }
        
        .search-box {
            border: 2px solid var(--cinza-claro);
            border-radius: 8px;
            padding: 8px 15px;
        }
        
        .search-box:focus {
            border-color: var(--verde-urbano);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }
        
        .footer-custom {
            background: var(--preto-suave);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <?php include 'partials/navbar.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="h2 mb-2">
                <i class="fas fa-bus me-2"></i>
                Linhas Carris Metropolitana
            </h1>
            <p class="mb-0">Consulte todas as linhas de autocarro da Área Metropolitana de Lisboa</p>
        </div>
    </section>

    <!-- Search Section -->
    <section class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search" style="color: var(--verde-urbano);"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" 
                                   id="searchLine" placeholder="Ex: 3702, 4708, 1508..."
                                   style="border-left: none;">
                            <button class="btn" style="background: var(--verde-urbano); color: white;" 
                                    type="button" onclick="filterLines()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div id="searchSuggestions" class="mt-2" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Áreas Filter -->
    <section class="container mb-4">
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <button class="btn btn-outline-verde active" onclick="filterByArea('all')">
                Todas as Áreas
            </button>
            <button class="btn btn-outline-verde" onclick="filterByArea('1')">
                Área 1 - Lisboa
            </button>
            <button class="btn btn-outline-verde" onclick="filterByArea('2')">
                Área 2 - Loures/Odivelas
            </button>
            <button class="btn btn-outline-verde" onclick="filterByArea('3')">
                Área 3 - Cascais/Oeiras
            </button>
            <button class="btn btn-outline-verde" onclick="filterByArea('4')">
                Área 4 - Almada/Seixal
            </button>
        </div>
    </section>

    <!-- Info sobre áreas -->
    <section class="container mb-4">
        <div class="alert" style="background: var(--cinza-claro); border-left: 4px solid var(--verde-urbano);">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle me-3 fs-4" style="color: var(--verde-urbano);"></i>
                <div>
                    <strong>Áreas da Carris Metropolitana:</strong> 
                    <span class="badge-area area-1 ms-2">Área 1 - Lisboa</span>
                    <span class="badge-area area-2 ms-2">Área 2 - Loures/Odivelas</span>
                    <span class="badge-area area-3 ms-2">Área 3 - Cascais/Oeiras</span>
                    <span class="badge-area area-4 ms-2">Área 4 - Almada/Seixal</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Lines Results -->
    <section class="container my-4">
        <div class="row g-3" id="linesContainer">
            <!-- Loading spinner -->
            <div class="text-center py-5">
                <div class="spinner-border" style="color: var(--verde-urbano);" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="mt-3">A carregar linhas da Carris Metropolitana...</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-custom">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <div class="logo-icon me-2">
                            <div class="pin-shape"></div>
                            <div class="buildings"></div>
                            <div class="road-line"></div>
                            <div class="traffic-light">
                                <div class="green-light"></div>
                            </div>
                            <div class="signal-waves"></div>
                            <div class="map-base"></div>
                        </div>
                        <span style="color: var(--verde-urbano); font-weight: bold;">Urban</span>
                        <span class="text-white fw-bold">Traffic</span>
                    </div>
                    <p class="text-white-50 small">Dados simulados da Carris Metropolitana - Frontend v1.0</p>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white">Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.html" class="text-white-50">Direções</a></li>
                        <li><a href="lines.html" class="text-white-50">Linhas</a></li>
                        <li><a href="configuracoes.html" class="text-white-50">Configurações</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white">Informação</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">Sobre</a></li>
                        <li><a href="#" class="text-white-50">Contactos</a></li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-white-50 small">
                <i class="fas fa-bus me-2" style="color: var(--verde-urbano);"></i>
                Dados simulados da Carris Metropolitana
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/lines.js"></script>
</body>
</html>