<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Configurações</title>
    
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cinza-claro);
            color: var(--preto-suave);
        }
        
        /* Navbar */
        .navbar-custom {
            background: var(--preto-suave);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        /* Logo */
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--verde-urbano) 0%, #45a049 100%);
            color: white;
            padding: 40px 0;
        }
        
        /* Cards de configuração */
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .settings-card:hover {
            box-shadow: 0 5px 20px rgba(76, 175, 80, 0.1);
            border-left-color: var(--verde-urbano);
        }
        
        .settings-icon {
            width: 50px;
            height: 50px;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--verde-urbano);
            font-size: 1.3rem;
        }
        
        .btn-verde {
            background: var(--verde-urbano);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-verde:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-outline-verde {
            border: 1px solid var(--verde-urbano);
            color: var(--verde-urbano);
            background: transparent;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-verde:hover {
            background: var(--verde-urbano);
            color: white;
        }
        
        .form-check-input:checked {
            background-color: var(--verde-urbano);
            border-color: var(--verde-urbano);
        }
        
        .form-select, .form-control {
            border: 2px solid var(--cinza-claro);
            border-radius: 8px;
            padding: 8px 12px;
        }
        
        .form-select:focus, .form-control:focus {
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
                <i class="fas fa-cog me-2"></i>
                Configurações
            </h1>
            <p class="mb-0">Personalize a sua experiência na aplicação</p>
        </div>
    </section>

    <!-- Settings Content -->
    <section class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- Idioma (apenas PT/EN) -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Idioma / Language</h5>
                        <p class="text-muted small mb-0">Selecione o seu idioma preferido</p>
                    </div>
                    <select class="form-select w-auto" id="language">
                        <option value="pt" selected>Português</option>
                        <option value="en">English</option>
                    </select>
                </div>

                <!-- Notificações -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Notificações</h5>
                        <p class="text-muted small mb-0">Receba alertas sobre atrasos e novidades</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notifications" checked>
                    </div>
                </div>

                <!-- Tema Escuro -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Modo Escuro</h5>
                        <p class="text-muted small mb-0">Ative o tema noturno</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="darkMode">
                    </div>
                </div>

                <!-- Localização -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Localização</h5>
                        <p class="text-muted small mb-0">Permitir acesso à localização para melhores rotas</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="location" checked>
                    </div>
                </div>

                <!-- Economia de Dados -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Modo Economia de Dados</h5>
                        <p class="text-muted small mb-0">Reduzir atualizações em tempo real</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dataSaver">
                    </div>
                </div>

                <!-- Sobre -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Sobre a aplicação</h5>
                        <p class="text-muted small mb-0">Versão 1.0.0</p>
                    </div>
                    <button class="btn-outline-verde" onclick="showAbout()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Ajuda -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Ajuda & FAQ</h5>
                        <p class="text-muted small mb-0">Tire as suas dúvidas</p>
                    </div>
                    <button class="btn-outline-verde" onclick="showHelp()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Termos e Condições -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Termos e Condições</h5>
                        <p class="text-muted small mb-0">Leia os nossos termos de uso</p>
                    </div>
                    <button class="btn-outline-verde" onclick="showTerms()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Privacidade -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">Política de Privacidade</h5>
                        <p class="text-muted small mb-0">Como protegemos os seus dados</p>
                    </div>
                    <button class="btn-outline-verde" onclick="showPrivacy()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Separador -->
                <hr class="my-4" style="border-color: var(--cinza-claro);">

                <!-- Apagar Dados -->
                <div class="settings-card d-flex align-items-center border-danger">
                    <div class="settings-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1 text-danger">Apagar Dados</h5>
                        <p class="text-muted small mb-0">Remover todo o histórico e configurações</p>
                    </div>
                    <button class="btn btn-outline-danger" onclick="clearAllData()">
                        <i class="fas fa-trash me-2"></i>Apagar
                    </button>
                </div>

                <!-- Botões de ação -->
                <div class="d-grid gap-2 mt-4">
                    <button class="btn-verde btn-lg" onclick="saveSettings()">
                        <i class="fas fa-save me-2"></i>Guardar Configurações
                    </button>
                </div>

                <div class="text-center mt-3">
                    <button class="btn btn-link text-muted" onclick="resetToDefault()">
                        <i class="fas fa-undo me-2"></i>Restaurar configurações padrão
                    </button>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-custom">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <a class="navbar-brand d-flex align-items-center" href="index.html">
                            <div class="logo-urbantraffic">
                            <img src="img/logo.png" height="80">
                            <span class="fw-bold" style="color: var(--verde-urbano);">Urban</span>
                            <span class="fw-bold text-white">Traffic</span>
                            </div>
                        </a>
                    </div>
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
                        <li><a href="#" class="text-white-50" onclick="showAbout()">Sobre</a></li>
                        <li><a href="#" class="text-white-50" onclick="showHelp()">Ajuda</a></li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-white-50 small">
                <i class="fas fa-bus me-2" style="color: var(--verde-urbano);"></i>
                Dados simulados da Carris Metropolitana • Frontend v1.0
            </div>
        </div>
    </footer>

    <!-- Modal Sobre -->
    <div class="modal fade" id="aboutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--verde-urbano); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Sobre a UrbanTraffic
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="logo-icon mx-auto mb-3" style="width: 60px; height: 80px;">
                            <div class="pin-shape"></div>
                            <div class="buildings"></div>
                            <div class="road-line"></div>
                            <div class="traffic-light">
                                <div class="green-light"></div>
                            </div>
                            <div class="signal-waves"></div>
                            <div class="map-base"></div>
                        </div>
                        <h4>UrbanTraffic v1.0.0</h4>
                        <p class="text-muted">Dados da Carris Metropolitana</p>
                    </div>
                    
                    <h6>Desenvolvedores</h6>
                    <p>Equipa UrbanTraffic</p>
                    
                    <h6>Contacto</h6>
                    <p>suporte@urbantraffic.pt</p>
                    
                    <h6>Fonte de dados</h6>
                    <p>Carris Metropolitana (GTFS e GTFS-Realtime)</p>
                    
                    <hr>
                    
                    <p class="small text-muted">
                        <i class="fas fa-copyright me-1"></i>
                        2026 UrbanTraffic. Todos os direitos reservados.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajuda -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--verde-urbano); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-question-circle me-2"></i>
                        Ajuda & FAQ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Como pesquisar rotas?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Insira a origem e destino nos campos apropriados na página inicial e clique em "Pesquisar". 
                                    O sistema mostrará as melhores rotas disponíveis da Carris Metropolitana.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Os dados são em tempo real?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sim, os dados são atualizados a cada 30 segundos através do feed GTFS-Realtime da Carris Metropolitana.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Como funciona o mapa?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    O mapa mostra todas as rotas disponíveis e a localização em tempo real dos autocarros. Pode clicar nas paragens para ver horários estimados.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    O que é a Carris Metropolitana?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    A Carris Metropolitana é o operador de transportes públicos que serve a Área Metropolitana de Lisboa, abrangendo 15 municípios.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/settings.js"></script>
    
    <script>
        // Funções para configurações
        function showAbout() {
            new bootstrap.Modal(document.getElementById('aboutModal')).show();
        }
        
        function showHelp() {
            new bootstrap.Modal(document.getElementById('helpModal')).show();
        }
        
        function showTerms() {
            // Simular abertura de termos
            alert('Termos e Condições da UrbanTraffic\n\nEsta aplicação utiliza dados fornecidos pela Carris Metropolitana. O uso da aplicação implica a aceitação dos termos de serviço.');
        }
        
        function showPrivacy() {
            // Simular abertura de política de privacidade
            alert('Política de Privacidade\n\nOs seus dados são armazenados localmente no seu dispositivo. Não recolhemos informações pessoais sem o seu consentimento.');
        }
        
        function saveSettings() {
            const settings = {
                language: document.getElementById('language').value,
                notifications: document.getElementById('notifications').checked,
                darkMode: document.getElementById('darkMode').checked,
                location: document.getElementById('location').checked,
                dataSaver: document.getElementById('dataSaver').checked
            };
            
            localStorage.setItem('userSettings', JSON.stringify(settings));
            
            // Aplicar tema escuro se selecionado
            if (settings.darkMode) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
            
            // Feedback visual
            alert('✅ Configurações guardadas com sucesso!');
        }
        
        function clearAllData() {
            if (confirm('⚠️ Tem a certeza que deseja apagar todos os dados? Esta ação não pode ser desfeita.')) {
                localStorage.clear();
                sessionStorage.clear();
                alert('🗑️ Dados apagados com sucesso!');
                location.reload();
            }
        }
        
        function resetToDefault() {
            if (confirm('Restaurar configurações padrão?')) {
                document.getElementById('language').value = 'pt';
                document.getElementById('notifications').checked = true;
                document.getElementById('darkMode').checked = false;
                document.getElementById('location').checked = true;
                document.getElementById('dataSaver').checked = false;
                
                saveSettings();
            }
        }
        
        // Carregar configurações guardadas
        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('userSettings');
            if (saved) {
                const settings = JSON.parse(saved);
                document.getElementById('language').value = settings.language || 'pt';
                document.getElementById('notifications').checked = settings.notifications !== false;
                document.getElementById('darkMode').checked = settings.darkMode || false;
                document.getElementById('location').checked = settings.location !== false;
                document.getElementById('dataSaver').checked = settings.dataSaver || false;
            }
        });
    </script>
</body>
</html>