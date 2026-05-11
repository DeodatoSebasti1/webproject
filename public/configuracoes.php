<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanTraffic - Configurações</title>
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

        body.dark-mode,
        body.dark-theme {
            background: #111827;
            color: #E5E7EB;
        }

        body.dark-mode .settings-card,
        body.dark-mode .modal-content,
        body.dark-mode .footer-custom,
        body.dark-mode .navbar-custom,
        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-theme .settings-card,
        body.dark-theme .modal-content,
        body.dark-theme .footer-custom,
        body.dark-theme .navbar-custom,
        body.dark-theme .form-control,
        body.dark-theme .form-select {
            background: #1F2937 !important;
            color: #E5E7EB !important;
            border-color: #374151 !important;
        }

        body.dark-mode .text-muted,
        body.dark-mode .small,
        body.dark-mode .form-label,
        body.dark-mode .btn-link.text-muted,
        body.dark-theme .text-muted,
        body.dark-theme .small,
        body.dark-theme .form-label,
        body.dark-theme .btn-link.text-muted {
            color: #CBD5E1 !important;
        }

        body.dark-mode .settings-icon,
        body.dark-theme .settings-icon {
            background: rgba(76, 175, 80, 0.18);
        }

        body.dark-mode .settings-card:hover,
        body.dark-theme .settings-card:hover {
            border-left-color: var(--verde-urbano);
        }

        body.dark-mode .navbar,
        body.dark-theme .navbar {
            background: #111827 !important;
        }

        body.dark-mode input,
        body.dark-mode select,
        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-theme input,
        body.dark-theme select,
        body.dark-theme .form-control,
        body.dark-theme .form-select {
            background-color: #1F2937 !important;
            color: #E5E7EB !important;
            border-color: #374151 !important;
        }

        body.dark-mode .page-header,
        body.dark-theme .page-header {
            background: linear-gradient(135deg, #1f7a35 0%, #2E7D32 100%);
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
<body class="ut-page ut-page-settings">

    <!-- Navbar -->
    <?php include 'partials/navbar.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="h2 mb-2" id="settingsPageTitle">
                <i class="fas fa-cog me-2"></i>
                Configurações
            </h1>
            <p class="mb-0" id="settingsPageSubtitle">Personalize a sua experiência na aplicação</p>
        </div>
    </section>

    <!-- Settings Content -->
    <section class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="settings-card">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div class="d-flex align-items-center">
                            <div class="settings-icon">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div>
                            <h5 class="mb-1" id="settingsTitleAccount">Conta UrbanTraffic</h5>
                                <p class="text-muted small mb-0" id="settingsAuthState">A verificar sessão...</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn-outline-verde ut-btn ut-btn-secondary" id="settingsLoginBtn" type="button" onclick="window.authManager?.showAuthModal()">
                                <i class="fas fa-sign-in-alt me-1"></i>Entrar
                            </button>
                            <button class="btn-outline-verde ut-btn ut-btn-secondary" id="settingsRegisterBtn" type="button" onclick="window.authManager?.showAuthModal('register')">
                                <i class="fas fa-user-plus me-1"></i>Criar conta
                            </button>
                            <a class="btn-outline-verde ut-btn ut-btn-secondary" id="settingsDashboardBtn" href="dashboard.php" data-dashboard-link="settings" style="display:none;">
                                <i class="fas fa-chart-pie me-1"></i>Dashboard
                            </a>
                            <button class="btn-outline-verde ut-btn ut-btn-secondary" id="settingsExportBtn" type="button" onclick="exportUserData()" style="display:none;">
                                <i class="fas fa-download me-1"></i>Exportar dados
                            </button>
                        </div>
                    </div>

                    <div id="settingsProfileFields" class="row g-3 mt-1" style="display:none;">
                        <div class="col-md-6">
                            <label for="settingsUserName" class="form-label" id="settingsLabelName">Nome</label>
                            <input type="text" class="form-control" id="settingsUserName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="settingsUserEmail" class="form-label" id="settingsLabelEmail">Email</label>
                            <input type="email" class="form-control" id="settingsUserEmail" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="settingsUserRole" class="form-label" id="settingsLabelRole">Perfil</label>
                            <input type="text" class="form-control" id="settingsUserRole" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="settingsLastLogin" class="form-label" id="settingsLabelLastLogin">Último acesso</label>
                            <input type="text" class="form-control" id="settingsLastLogin" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Idioma (apenas PT/EN) -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="settingsTitleLanguage">Idioma / Language</h5>
                        <p class="text-muted small mb-0" id="settingsDescLanguage">Selecione o seu idioma preferido</p>
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
                        <h5 class="mb-1" id="settingsTitleNotifications">Notificações</h5>
                        <p class="text-muted small mb-0" id="settingsDescNotifications">Receba alertas sobre atrasos e novidades</p>
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
                        <h5 class="mb-1" id="settingsTitleDarkMode">Modo Escuro</h5>
                        <p class="text-muted small mb-0" id="settingsDescDarkMode">Ative o tema noturno</p>
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
                        <h5 class="mb-1" id="settingsTitleLocation">Localização</h5>
                        <p class="text-muted small mb-0" id="settingsDescLocation">Permitir acesso à localização para melhores rotas</p>
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
                        <h5 class="mb-1" id="settingsTitleDataSaver">Modo Economia de Dados</h5>
                        <p class="text-muted small mb-0" id="settingsDescDataSaver">Reduzir atualizações em tempo real</p>
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
                        <h5 class="mb-1" id="settingsTitleAbout">Sobre a aplicação</h5>
                        <p class="text-muted small mb-0" id="settingsDescAbout">Versão 1.0.0</p>
                    </div>
                    <button class="btn-outline-verde ut-btn ut-btn-secondary" onclick="showAbout()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Ajuda -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="settingsTitleHelp">Ajuda & FAQ</h5>
                        <p class="text-muted small mb-0" id="settingsDescHelp">Tire as suas dúvidas</p>
                    </div>
                    <button class="btn-outline-verde ut-btn ut-btn-secondary" onclick="showHelp()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Termos e Condições -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="settingsTitleTerms">Termos e Condições</h5>
                        <p class="text-muted small mb-0" id="settingsDescTerms">Leia os nossos termos de uso</p>
                    </div>
                    <button class="btn-outline-verde ut-btn ut-btn-secondary" onclick="showTerms()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Privacidade -->
                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="settingsTitlePrivacy">Política de Privacidade</h5>
                        <p class="text-muted small mb-0" id="settingsDescPrivacy">Como protegemos os seus dados e preferências locais</p>
                    </div>
                    <button class="btn-outline-verde ut-btn ut-btn-secondary" onclick="showPrivacy()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div class="settings-card d-flex align-items-center">
                    <div class="settings-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="settingsTitleDeleteAccount">Eliminar conta</h5>
                        <p class="text-muted small mb-0" id="settingsDescDeleteAccount">Por segurança, esta opção está em desenvolvimento nesta demo.</p>
                    </div>
                    <button class="btn btn-outline-secondary ut-btn ut-btn-secondary" type="button" onclick="showDeleteAccountNotice()">
                        <i class="fas fa-info-circle me-2"></i>Ver estado
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
                        <h5 class="mb-1 text-danger" id="settingsTitleClearData">Apagar Dados</h5>
                        <p class="text-muted small mb-0" id="settingsDescClearData">Remover todo o histórico e configurações</p>
                    </div>
                    <button class="btn btn-outline-danger ut-btn" onclick="clearAllData()" id="settingsClearButton">
                        <i class="fas fa-trash me-2"></i><span id="settingsClearButtonText">Apagar</span>
                    </button>
                </div>

                <!-- Botões de ação -->
                <div class="d-grid gap-2 mt-4">
                    <button class="btn-verde btn-lg ut-btn ut-btn-primary ut-btn-lg" onclick="saveSettings()" id="settingsSaveButton">
                        <i class="fas fa-save me-2"></i><span id="settingsSaveButtonText">Guardar Configurações</span>
                    </button>
                </div>

                <div class="text-center mt-3">
                    <button class="btn btn-link text-muted" onclick="resetToDefault()" id="settingsResetButton">
                        <i class="fas fa-undo me-2"></i><span id="settingsResetButtonText">Restaurar configurações padrão</span>
                    </button>
                </div>

            </div>
        </div>
    </section>

    <?php include 'partials/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/preferences.js?v=20260427a"></script>
    <script src="js/auth.js?v=20260427h"></script>
    <script src="js/settings.js?v=20260427h"></script>
</body>
</html>
