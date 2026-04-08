// settings.js - Configurações da aplicação

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
    App.showNotification('Termos e Condições em breve...', 'info');
};

// Mostrar Privacidade
window.showPrivacy = function() {
    App.showNotification('Política de Privacidade em breve...', 'info');
};

// Guardar configurações
window.saveSettings = function() {
    const settings = {
        language: document.getElementById('language').value,
        notifications: document.getElementById('notifications').checked,
        darkMode: document.getElementById('darkMode').checked,
        location: document.getElementById('location').checked,
        dataSaver: document.getElementById('dataSaver')?.checked || false
    };
    
    localStorage.setItem('userSettings', JSON.stringify(settings));
    
    // Aplicar tema escuro
    if (settings.darkMode) {
        document.body.classList.add('dark-theme');
        // Atualizar mapa se existir
        if (window.map) {
            updateMapTheme(true);
        }
    } else {
        document.body.classList.remove('dark-theme');
        if (window.map) {
            updateMapTheme(false);
        }
    }
    
    App.showNotification('Configurações guardadas com sucesso!', 'success');
};

// Apagar todos os dados
window.clearAllData = function() {
    if (confirm('⚠️ Tem a certeza que deseja apagar todos os dados? Esta ação não pode ser desfeita.')) {
        localStorage.clear();
        sessionStorage.clear();
        App.showNotification('Dados apagados com sucesso!', 'info');
        
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
    if (!window.map) return;
    
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

// Carregar configurações guardadas
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('userSettings');
    if (saved) {
        const settings = JSON.parse(saved);
        
        if (document.getElementById('language')) {
            document.getElementById('language').value = settings.language || 'pt';
        }
        if (document.getElementById('notifications')) {
            document.getElementById('notifications').checked = settings.notifications !== false;
        }
        if (document.getElementById('darkMode')) {
            document.getElementById('darkMode').checked = settings.darkMode || false;
        }
        if (document.getElementById('location')) {
            document.getElementById('location').checked = settings.location !== false;
        }
        if (document.getElementById('dataSaver')) {
            document.getElementById('dataSaver').checked = settings.dataSaver || false;
        }
    }
});