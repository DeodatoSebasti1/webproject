// search.js - Versão corrigida com nomes consistentes

// Debounce para evitar muitas requisições
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Buscar sugestões da API do OpenStreetMap (Nominatim)
async function fetchAddressSuggestions(query) {
    if (query.length < 3) return [];
    
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&addressdetails=1&limit=10&countrycodes=pt`,
            {
                headers: {
                    'User-Agent': 'UrbanTraffic-App'
                }
            }
        );
        
        const data = await response.json();
        return data.map(item => ({
            display: item.display_name.split(',').slice(0, 3).join(','),
            full: item.display_name,
            type: item.type,
            class: item.class,
            lat: item.lat,
            lon: item.lon
        }));
    } catch (error) {
        console.error('Erro ao buscar sugestões:', error);
        return [];
    }
}

// Mostrar sugestões no dropdown
function showSuggestions(inputId, suggestions) {
    // inputId pode ser 'origin' ou 'destination'
    const suggestionsDiv = $(`#${inputId}Suggestions`); // '#originSuggestions' ou '#destinationSuggestions'
    suggestionsDiv.empty();
    
    if (suggestions.length > 0) {
        suggestions.forEach(suggestion => {
            const icon = getIconForType(suggestion.type || suggestion.class);
            const typeLabel = getTypeLabel(suggestion.type || suggestion.class);
            
            const item = $(`
                <div class="suggestion-item" onclick="selectSuggestion('${inputId}', '${suggestion.display.replace(/'/g, "\\'")}')">
                    <i class="fas ${icon}"></i>
                    <span>${suggestion.display}</span>
                    <span class="suggestion-type">${typeLabel}</span>
                </div>
            `);
            
            suggestionsDiv.append(item);
        });
        
        suggestionsDiv.show();
    } else {
        suggestionsDiv.hide();
    }
}

// Ícone baseado no tipo de lugar
function getIconForType(type) {
    const icons = {
        road: 'fa-road',
        residential: 'fa-home',
        commercial: 'fa-building',
        retail: 'fa-store',
        pedestrian: 'fa-walking',
        city: 'fa-city',
        town: 'fa-city',
        village: 'fa-tree',
        suburb: 'fa-map-pin',
        neighbourhood: 'fa-map-pin',
        airport: 'fa-plane',
        station: 'fa-train',
        bus_stop: 'fa-bus',
        tram_stop: 'fa-tram',
        metro: 'fa-subway',
        attraction: 'fa-camera'
    };
    return icons[type] || 'fa-map-marker-alt';
}

// Label legível para o tipo
function getTypeLabel(type) {
    const labels = {
        road: 'Rua',
        residential: 'Residencial',
        commercial: 'Comercial',
        retail: 'Loja',
        pedestrian: 'Rua Pedonal',
        city: 'Cidade',
        town: 'Vila',
        village: 'Aldeia',
        suburb: 'Bairro',
        neighbourhood: 'Vizinhança',
        airport: 'Aeroporto',
        station: 'Estação',
        bus_stop: 'Paragem',
        tram_stop: 'Elétrico',
        metro: 'Metro',
        attraction: 'Atração'
    };
    return labels[type] || 'Local';
}

// Selecionar sugestão
function selectSuggestion(inputId, value) {
    $(`#${inputId}`).val(value);
    $(`#${inputId}Suggestions`).hide(); // Usa o mesmo padrão
}

// Configurar autocomplete
function setupAutocomplete() {
    // Função debounced para buscar sugestões
    const debouncedSearch = debounce(async function(inputId, query) {
        if (query.length >= 3) {
            // Mostrar loading
            const input = $(`#${inputId}`);
            if (!input.next('.search-loading').length) {
                input.after('<i class="fas fa-spinner fa-spin search-loading"></i>');
            }
            
            const suggestions = await fetchAddressSuggestions(query);
            
            // Remover loading
            $(`#${inputId} + .search-loading`).remove();
            
            showSuggestions(inputId, suggestions);
        } else {
            $(`#${inputId}Suggestions`).hide();
        }
    }, 500);
    
    // Event listeners para origem
    $('#origin').on('input', function() {
        const query = $(this).val();
        debouncedSearch('origin', query);
    });
    
    // Event listeners para destino - CORRIGIDO: usa 'destination' como inputId
    $('#destination').on('input', function() {
        const query = $(this).val();
        debouncedSearch('destination', query); // 'destination' em vez de 'dest'
    });
    
    // Esconder sugestões ao clicar fora - CORRIGIDO: consistência nos nomes
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#origin, #originSuggestions, #destination, #destinationSuggestions').length) {
            $('#originSuggestions, #destinationSuggestions').hide(); // Ambos com o mesmo padrão
        }
    });
    
    // Mostrar sugestões quando o campo ganha foco (se tiver texto)
    $('#origin, #destination').on('focus', function() {
        const inputId = $(this).attr('id');
        if ($(this).val().length >= 3) {
            $(`#${inputId}Suggestions`).show();
        }
    });
}

// Função de busca
async function searchRoutes() {
    const origin = $('#origin').val().trim();
    const destination = $('#destination').val().trim();
    
    // Validação
    if (!origin || !destination) {
        alert('Por favor, preencha origem e destino');
        return;
    }
    
    if (origin.toLowerCase() === destination.toLowerCase()) {
        alert('Origem e destino não podem ser iguais');
        return;
    }
    
    // Mostrar loading
    showLoading();
    
    // Simular busca
    setTimeout(() => {
        hideLoading();
        // Guardar no histórico
        saveSearchHistory(origin, destination);
        // Redirecionar para resultados
        window.location.href = `results.html?origin=${encodeURIComponent(origin)}&dest=${encodeURIComponent(destination)}`;
    }, 1500);
}

// Mostrar loading
function showLoading() {
    const loadingHtml = `
        <div id="loadingOverlay" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        ">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-primary fw-bold">A buscar as melhores rotas...</p>
        </div>
    `;
    
    $('body').append(loadingHtml);
}

// Esconder loading
function hideLoading() {
    $('#loadingOverlay').fadeOut('slow', function() {
        $(this).remove();
    });
}

// Guardar histórico de busca
function saveSearchHistory(origin, destination) {
    let history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
    
    history.unshift({
        origin: origin,
        destination: destination,
        timestamp: new Date().toISOString()
    });
    
    history = history.slice(0, 20);
    localStorage.setItem('searchHistory', JSON.stringify(history));
}

// Inicializar quando a página carrega
$(document).ready(function() {
    console.log('Search module initialized');
    setupAutocomplete();
    
    // Pesquisar com Enter
    $('#origin, #destination').on('keypress', function(e) {
        if (e.which === 13) {
            searchRoutes();
        }
    });
});

// Exportar funções globais
window.searchRoutes = searchRoutes;
window.selectSuggestion = selectSuggestion;