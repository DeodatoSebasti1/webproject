// settings.js - Configurações da aplicação

function safeSettingsNotify(message, type = 'info') {
    if (window.App && typeof window.App.showNotification === 'function') {
        window.App.showNotification(message, type);
        return;
    }

    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
        return;
    }

    console.log(`[${type}] ${message}`);
}

const SETTINGS_STORAGE_KEY = 'userSettings';
const DARK_MODE_STORAGE_KEY = 'urban_dark_mode';
const LANGUAGE_STORAGE_KEY = 'urban_language';

function readStoredSettings() {
    let legacySettings = {};

    try {
        legacySettings = JSON.parse(localStorage.getItem(SETTINGS_STORAGE_KEY) || '{}') || {};
    } catch (error) {
        console.warn('Could not parse stored settings:', error);
    }

    const storedDarkMode = localStorage.getItem(DARK_MODE_STORAGE_KEY);
    const storedLanguage = localStorage.getItem(LANGUAGE_STORAGE_KEY);

    if (storedDarkMode !== null) {
        legacySettings.darkMode = storedDarkMode === 'true';
    }

    if (storedLanguage) {
        legacySettings.language = storedLanguage;
    }

    return {
        language: legacySettings.language || 'pt',
        notifications: legacySettings.notifications !== false,
        darkMode: !!legacySettings.darkMode,
        location: legacySettings.location !== false,
        dataSaver: !!legacySettings.dataSaver
    };
}

function persistSettings(settings) {
    localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(settings));
    localStorage.setItem(DARK_MODE_STORAGE_KEY, settings.darkMode ? 'true' : 'false');
    localStorage.setItem(LANGUAGE_STORAGE_KEY, settings.language || 'pt');
}

function getCurrentSettingsFromForm() {
    return {
        language: document.getElementById('language')?.value || 'pt',
        notifications: document.getElementById('notifications')?.checked !== false,
        darkMode: !!document.getElementById('darkMode')?.checked,
        location: document.getElementById('location')?.checked !== false,
        dataSaver: !!document.getElementById('dataSaver')?.checked
    };
}

function hydrateSettingsForm(settings) {
    if (document.getElementById('language')) {
        document.getElementById('language').value = settings.language || 'pt';
    }
    if (document.getElementById('notifications')) {
        document.getElementById('notifications').checked = settings.notifications !== false;
    }
    if (document.getElementById('darkMode')) {
        document.getElementById('darkMode').checked = !!settings.darkMode;
    }
    if (document.getElementById('location')) {
        document.getElementById('location').checked = settings.location !== false;
    }
    if (document.getElementById('dataSaver')) {
        document.getElementById('dataSaver').checked = !!settings.dataSaver;
    }
}

// ==================== FUNÇÕES PRINCIPAIS ====================

// Mostrar modal Sobre
window.showAbout = function() {
    if ($('#aboutModal').length) {
        $('#aboutModal').remove();
    }
    
    const modalHtml = `
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
                            <div style="position: relative; width: 60px; height: 80px; margin: 0 auto;" class="mb-3">
                                <div style="position: absolute; width: 100%; height: 100%; background: var(--verde-urbano); clip-path: polygon(0% 0%, 100% 0%, 100% 70%, 50% 100%, 0% 70%);"></div>
                                <div style="position: absolute; top: 8px; left: 8px; right: 8px; height: 15px; background: var(--cinza-urbano); clip-path: polygon(0% 0%, 20% 0%, 20% 100%, 40% 100%, 40% 0%, 60% 0%, 60% 100%, 80% 100%, 80% 0%, 100% 0%, 100% 100%, 0% 100%);"></div>
                                <div style="position: absolute; top: 28px; left: 5px; right: 5px; height: 4px; background: white; border-radius: 2px; transform: rotate(-5deg);"></div>
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
    `;
    
    $('body').append(modalHtml);
    $('#aboutModal').modal('show');
    
    $('#aboutModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
};

// Mostrar modal Ajuda
window.showHelp = function() {
    if ($('#helpModal').length) {
        $('#helpModal').remove();
    }
    
    const modalHtml = `
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
    `;
    
    $('body').append(modalHtml);
    $('#helpModal').modal('show');
    
    $('#helpModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
};

// Mostrar Termos
window.showTerms = function() {
    renderInfoModal(
        'Termos e Condições',
        `
            <p>O UrbanTraffic é uma aplicação académica de apoio à mobilidade urbana.</p>
            <ul class="small ps-3">
                <li>As rotas usam dados GTFS e integrações externas quando disponíveis.</li>
                <li>Os tempos em tempo real podem incluir modos estimados, fallback ou simulação controlada.</li>
                <li>Os utilizadores devem validar informação crítica antes de tomar decisões de viagem.</li>
                <li>O uso da demo implica aceitação destas condições no contexto académico.</li>
            </ul>
        `
    );
};

// Mostrar Privacidade
window.showPrivacy = function() {
    renderInfoModal(
        'Política de Privacidade',
        `
            <p>O UrbanTraffic guarda apenas os dados necessários para autenticação e personalização básica.</p>
            <ul class="small ps-3">
                <li>Preferências visuais e idioma podem ficar guardados no navegador.</li>
                <li>Favoritos e histórico ficam associados à conta autenticada.</li>
                <li>Não são recolhidos dados pessoais além dos estritamente necessários à demo.</li>
                <li>Pode limpar os dados locais na secção de configurações.</li>
            </ul>
        `
    );
};

// Guardar configurações
window.saveSettings = function() {
    const settings = getCurrentSettingsFromForm();

    persistSettings(settings);
    if (window.UrbanPreferences?.setPreferences) {
        window.UrbanPreferences.setPreferences({
            darkMode: settings.darkMode,
            language: settings.language || 'pt'
        });
    } else {
        applySettings(settings);
        applyLanguage(settings.language || 'pt');
        window.dispatchEvent(new Event('urbanPreferencesChanged'));
    }
    
    safeSettingsNotify('Configurações guardadas com sucesso!', 'success');
};

// Apagar todos os dados
window.clearAllData = function() {
    if (confirm('⚠️ Tem a certeza que deseja apagar todos os dados? Esta ação não pode ser desfeita.')) {
        localStorage.clear();
        sessionStorage.clear();
        safeSettingsNotify('Dados apagados com sucesso!', 'info');
        
        // Recarregar após 1 segundo
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
};

// Restaurar padrões
window.resetToDefault = function() {
    if (confirm('Restaurar configurações padrão?')) {
        document.getElementById('language').value = 'pt';
        document.getElementById('notifications').checked = true;
        document.getElementById('darkMode').checked = false;
        document.getElementById('location').checked = true;
        if (document.getElementById('dataSaver')) {
            document.getElementById('dataSaver').checked = false;
        }
        
        saveSettings();
    }
};

// Atualizar tema do mapa
function updateMapTheme(isDark) {
    if (!window.map || !window.L || typeof window.map.eachLayer !== 'function') return;
    
    // Remover camada atual
    window.map.eachLayer(layer => {
        if (layer instanceof L.TileLayer) {
            window.map.removeLayer(layer);
        }
    });
    
    // Adicionar nova camada com tema correto
    const tileUrl = isDark 
        ? "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"
        : "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png";
    
    L.tileLayer(tileUrl, {
        attribution: "© OpenStreetMap © CartoDB"
    }).addTo(window.map);
}

function applySettings(settings) {
    const darkModeEnabled = !!settings.darkMode;
    document.body.classList.toggle('dark-mode', darkModeEnabled);
    document.body.classList.toggle('dark-theme', darkModeEnabled);
    document.documentElement.classList.toggle('dark-mode', darkModeEnabled);
    document.documentElement.classList.toggle('dark-theme', darkModeEnabled);

    if (window.map) {
        updateMapTheme(darkModeEnabled);
    }
}

function renderInfoModal(title, bodyHtml) {
    $('#settingsInfoModal').remove();
    $('body').append(`
        <div class="modal fade" id="settingsInfoModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background: var(--verde-urbano); color: white;">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">${bodyHtml}</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    `);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('settingsInfoModal')).show();
    $('#settingsInfoModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

function applyLanguage(language) {
    const translations = {
        pt: {
            settingsPageTitle: 'Configurações',
            settingsPageSubtitle: 'Personalize a sua experiência na aplicação',
            settingsTitleAccount: 'Conta UrbanTraffic',
            settingsLabelName: 'Nome',
            settingsLabelEmail: 'Email',
            settingsLabelRole: 'Perfil',
            settingsLabelLastLogin: 'Último acesso',
            settingsTitleLanguage: 'Idioma / Language',
            settingsDescLanguage: 'Selecione o seu idioma preferido',
            settingsTitleNotifications: 'Notificações',
            settingsDescNotifications: 'Receba alertas sobre atrasos e novidades',
            settingsTitleDarkMode: 'Modo Escuro',
            settingsDescDarkMode: 'Ative o tema noturno',
            settingsTitleLocation: 'Localização',
            settingsDescLocation: 'Permitir acesso à localização para melhores rotas',
            settingsTitleDataSaver: 'Modo Economia de Dados',
            settingsDescDataSaver: 'Reduzir atualizações em tempo real',
            settingsTitleAbout: 'Sobre a aplicação',
            settingsDescAbout: 'Versão 1.0.0',
            settingsTitleHelp: 'Ajuda & FAQ',
            settingsDescHelp: 'Tire as suas dúvidas',
            settingsTitleTerms: 'Termos e Condições',
            settingsDescTerms: 'Leia os nossos termos de uso',
            settingsTitlePrivacy: 'Política de Privacidade',
            settingsDescPrivacy: 'Como protegemos os seus dados e preferências locais',
            settingsTitleDeleteAccount: 'Eliminar conta',
            settingsDescDeleteAccount: 'Por segurança, esta opção está em desenvolvimento nesta demo.',
            settingsTitleClearData: 'Apagar Dados',
            settingsDescClearData: 'Remover todo o histórico e configurações',
            settingsClearButtonText: 'Apagar',
            settingsSaveButtonText: 'Guardar Configurações',
            settingsResetButtonText: 'Restaurar configurações padrão'
        },
        en: {
            settingsPageTitle: 'Settings',
            settingsPageSubtitle: 'Customize your experience in the app',
            settingsTitleAccount: 'UrbanTraffic Account',
            settingsLabelName: 'Name',
            settingsLabelEmail: 'Email',
            settingsLabelRole: 'Role',
            settingsLabelLastLogin: 'Last access',
            settingsTitleLanguage: 'Language',
            settingsDescLanguage: 'Choose your preferred language',
            settingsTitleNotifications: 'Notifications',
            settingsDescNotifications: 'Receive delay and update alerts',
            settingsTitleDarkMode: 'Dark Mode',
            settingsDescDarkMode: 'Enable the night theme',
            settingsTitleLocation: 'Location',
            settingsDescLocation: 'Allow location access for better routes',
            settingsTitleDataSaver: 'Data Saver Mode',
            settingsDescDataSaver: 'Reduce realtime updates',
            settingsTitleAbout: 'About the app',
            settingsDescAbout: 'Version 1.0.0',
            settingsTitleHelp: 'Help & FAQ',
            settingsDescHelp: 'Find answers to common questions',
            settingsTitleTerms: 'Terms and Conditions',
            settingsDescTerms: 'Read the usage terms',
            settingsTitlePrivacy: 'Privacy Policy',
            settingsDescPrivacy: 'How we protect your data and local preferences',
            settingsTitleDeleteAccount: 'Delete account',
            settingsDescDeleteAccount: 'For safety, this option is still in development in this demo.',
            settingsTitleClearData: 'Clear Data',
            settingsDescClearData: 'Remove all history and settings',
            settingsClearButtonText: 'Clear',
            settingsSaveButtonText: 'Save Settings',
            settingsResetButtonText: 'Restore default settings'
        }
    };

    const selected = translations[language] || translations.pt;
    Object.entries(selected).forEach(([id, text]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = text;
        }
    });
}

function previewSettings() {
    const settings = getCurrentSettingsFromForm();
    if (window.UrbanPreferences?.setPreferences) {
        window.UrbanPreferences.setPreferences({
            darkMode: settings.darkMode,
            language: settings.language || 'pt'
        });
    } else {
        applySettings(settings);
        applyLanguage(settings.language || 'pt');
    }
}

// Carregar configurações guardadas
document.addEventListener('DOMContentLoaded', function() {
    const settings = readStoredSettings();
    hydrateSettingsForm(settings);
    applySettings(settings);
    applyLanguage(settings.language || 'pt');

    document.getElementById('darkMode')?.addEventListener('change', previewSettings);
    document.getElementById('language')?.addEventListener('change', previewSettings);

    syncSettingsAuthState();
});

function formatSettingsDate(value) {
    if (!value) return 'Sem registo';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('pt-PT');
}

async function syncSettingsAuthState() {
    const authState = document.getElementById('settingsAuthState');
    if (!authState) return;

    const user = window.authManager?.getCurrentUser?.();
    const token = window.authManager?.getToken?.();
    const profileFields = document.getElementById('settingsProfileFields');
    const loginBtn = document.getElementById('settingsLoginBtn');
    const registerBtn = document.getElementById('settingsRegisterBtn');
    const dashboardBtn = document.getElementById('settingsDashboardBtn');
    const exportBtn = document.getElementById('settingsExportBtn');

    if (!user) {
        authState.textContent = 'Não autenticado. Entre para gerir os seus dados e exportar informação.';
        if (profileFields) profileFields.style.display = 'none';
        if (loginBtn) loginBtn.style.display = '';
        if (registerBtn) registerBtn.style.display = '';
        if (dashboardBtn) dashboardBtn.style.display = 'none';
        if (exportBtn) exportBtn.style.display = 'none';
        return;
    }

    authState.textContent = `Sessão ativa como ${user.name} (${user.role || 'user'}).`;
    if (loginBtn) loginBtn.style.display = 'none';
    if (registerBtn) registerBtn.style.display = 'none';
    if (dashboardBtn) dashboardBtn.style.display = '';
    if (dashboardBtn && typeof window.authManager?.getDashboardUrl === 'function') {
        dashboardBtn.href = window.authManager.getDashboardUrl(user);
    }
    if (exportBtn) exportBtn.style.display = '';

    try {
        const response = await fetch('/urban/public/api/auth?action=profile', {
            headers: token ? { Authorization: `Bearer ${token}` } : {}
        });
        const data = await response.json();

        if (!response.ok || data.status !== 'success' || !data.user) {
            throw new Error(data.message || 'Não foi possível carregar o perfil.');
        }

        if (profileFields) profileFields.style.display = '';
        if (document.getElementById('settingsUserName')) {
            document.getElementById('settingsUserName').value = data.user.name || user.name || '';
        }
        if (document.getElementById('settingsUserEmail')) {
            document.getElementById('settingsUserEmail').value = data.user.email || user.email || '';
        }
        if (document.getElementById('settingsUserRole')) {
            document.getElementById('settingsUserRole').value = data.user.role || user.role || 'user';
        }
        if (document.getElementById('settingsLastLogin')) {
            document.getElementById('settingsLastLogin').value = formatSettingsDate(data.user.last_login);
        }
    } catch (error) {
        console.warn('Settings profile error:', error);
        authState.textContent = 'Sessão ativa, mas não foi possível carregar os detalhes da conta.';
    }
}
window.syncSettingsAuthState = syncSettingsAuthState;

window.exportUserData = async function() {
    const token = window.authManager?.getToken?.();
    const user = window.authManager?.getCurrentUser?.();

    if (!user) {
        safeSettingsNotify('Faça login para exportar os seus dados.', 'warning');
        return;
    }

    try {
        const [profileResponse, favoritesResponse, historyResponse] = await Promise.all([
            fetch('/urban/public/api/auth?action=profile', { headers: token ? { Authorization: `Bearer ${token}` } : {} }),
            fetch('/urban/public/api/user?action=favorites&limit=10', { headers: token ? { Authorization: `Bearer ${token}` } : {} }),
            fetch('/urban/public/api/user?action=history&limit=10', { headers: token ? { Authorization: `Bearer ${token}` } : {} })
        ]);

        const [profileData, favoritesData, historyData] = await Promise.all([
            profileResponse.json(),
            favoritesResponse.json(),
            historyResponse.json()
        ]);

        const exportPayload = {
            exported_at: new Date().toISOString(),
            user: profileData.user || user,
            settings: readStoredSettings(),
            favorites: favoritesData.favorites || [],
            history: historyData.history || []
        };

        const blob = new Blob([JSON.stringify(exportPayload, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'urbantraffic-dados-utilizador.json';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);

        safeSettingsNotify('Exportação concluída com sucesso.', 'success');
    } catch (error) {
        console.warn('Export user data error:', error);
        safeSettingsNotify('Não foi possível exportar os dados agora.', 'error');
    }
};

window.showDeleteAccountNotice = function() {
    safeSettingsNotify('A eliminação de conta ainda não está disponível nesta demo. Use logout ou contacte o administrador.', 'warning');
};
