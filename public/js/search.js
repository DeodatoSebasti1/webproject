// /urban/public/js/search.js - Versão corrigida

// Debounce
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

function notifySearch(message, type = 'info') {
    if (window.UrbanPreferences?.canUseNotifications && !window.UrbanPreferences.canUseNotifications()) {
        if (type === 'error') {
            console.error(message);
        }
        return;
    }

    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
        return;
    }

    if (window.App && typeof window.App.showNotification === 'function') {
        window.App.showNotification(message, type);
        return;
    }

    console[type === 'error' ? 'error' : 'warn'](message);
}

function setSearchFieldState(inputId, isValid, message = '') {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.classList.remove('is-valid', 'is-invalid');
    input.setCustomValidity(message || '');

    if (isValid === true) {
        input.classList.add('is-valid');
    } else if (isValid === false) {
        input.classList.add('is-invalid');
    }

    const feedback = document.getElementById(`${inputId}Feedback`);
    if (feedback && message) {
        feedback.textContent = message;
    }
}

function validateSearchFields() {
    const origin = ($('#origin').val() || '').trim();
    const destination = ($('#destination').val() || '').trim();
    const travelDate = ($('#travelDate').val() || '').trim();
    const departureTime = ($('#departureTime').val() || '').trim();
    const today = new Date().toISOString().slice(0, 10);
    let isValid = true;

    setSearchFieldState('origin', null);
    setSearchFieldState('destination', null);

    if (origin.length < 3) {
        setSearchFieldState('origin', false, 'Indique uma origem válida com pelo menos 3 caracteres.');
        isValid = false;
    } else {
        setSearchFieldState('origin', true);
    }

    if (destination.length < 3) {
        setSearchFieldState('destination', false, 'Indique um destino válido com pelo menos 3 caracteres.');
        isValid = false;
    } else if (origin && origin.toLowerCase() === destination.toLowerCase()) {
        setSearchFieldState('destination', false, 'Origem e destino não podem ser iguais.');
        isValid = false;
    } else {
        setSearchFieldState('destination', true);
    }

    if (travelDate && travelDate < today) {
        notifySearch('A data da viagem não pode estar no passado.', 'warning');
        isValid = false;
    }

    if (departureTime && !/^([01]\d|2[0-3]):[0-5]\d$/.test(departureTime)) {
        notifySearch('Escolha uma hora válida no formato HH:MM.', 'warning');
        isValid = false;
    }

    return isValid;
}

// Buscar sugestões via backend (evita bloqueios de rede)
async function fetchAddressSuggestions(query) {
    if (query.length < 3) return [];
    
    try {
        const apiResponse = await fetch(`/urban/public/api/search?q=${encodeURIComponent(query)}`);
        const data = await apiResponse.json();
        
        if (data.error) {
            console.warn('Erro do proxy:', data.error);
            return [];
        }
        
        return data;
    } catch (error) {
        console.warn('Erro ao buscar sugestões:', error);
        return [];
    }
}

async function resolveAddressToCoords(query) {
    const trimmedQuery = (query || '').trim();
    if (trimmedQuery.length < 3) return null;

    const suggestions = await fetchAddressSuggestions(trimmedQuery);
    if (!Array.isArray(suggestions) || suggestions.length === 0) {
        return null;
    }

    const normalizedQuery = trimmedQuery.toLowerCase();
    const exactMatch = suggestions.find((suggestion) => {
        const display = (suggestion.display || '').toLowerCase();
        const full = (suggestion.full || '').toLowerCase();
        return display === normalizedQuery || full === normalizedQuery;
    });

    const bestMatch = exactMatch || suggestions[0];
    const lat = parseFloat(bestMatch.lat);
    const lon = parseFloat(bestMatch.lon);

    if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
        return null;
    }

    return {
        lat,
        lon,
        display: bestMatch.display || trimmedQuery
    };
}

// Mostrar sugestões no dropdown
function showSuggestions(inputId, suggestions) {
    const suggestionsDiv = $(`#${inputId}Suggestions`);
    suggestionsDiv.empty();
    
    if (suggestions.length > 0) {
        suggestions.forEach(suggestion => {
            const icon = getIconForType(suggestion.type || suggestion.class);
            const typeLabel = getTypeLabel(suggestion.type || suggestion.class);
            
            const escapedDisplay = suggestion.display.replace(/'/g, "\\'");
            
            const item = $(`
                <div class="suggestion-item" data-value="${escapedDisplay}" data-lat="${suggestion.lat}" data-lon="${suggestion.lon}">
                    <i class="fas ${icon}"></i>
                    <div class="suggestion-info">
                        <div class="suggestion-title">${suggestion.display}</div>
                        <div class="suggestion-subtitle">${typeLabel}</div>
                    </div>
                </div>
            `);
            
            item.on('click', function() {
                const value = $(this).data('value');
                const lat = $(this).data('lat');
                const lon = $(this).data('lon');
                selectSuggestion(inputId, value, lat, lon);
            });
            
            suggestionsDiv.append(item);
        });
        
        suggestionsDiv.show();
    } else {
        suggestionsDiv.html(`
            <div class="suggestion-item">
                <i class="fas fa-location-crosshairs"></i>
                <div class="suggestion-info">
                    <div class="suggestion-title">Sem sugestões</div>
                    <div class="suggestion-subtitle">Experimente um nome de rua, zona ou município</div>
                </div>
            </div>
        `).show();
    }
}

// Selecionar sugestão com coordenadas
function selectSuggestion(inputId, value, lat, lon) {
    $(`#${inputId}`).val(value);
    $(`#${inputId}Suggestions`).hide();
    
    // Guardar coordenadas selecionadas globalmente
    if (inputId === 'origin') {
        window.selectedOriginCoords = { lat: parseFloat(lat), lon: parseFloat(lon) };
        console.log('📍 Origem selecionada:', window.selectedOriginCoords);
    } else if (inputId === 'destination') {
        window.selectedDestinationCoords = { lat: parseFloat(lat), lon: parseFloat(lon) };
        console.log('📍 Destino selecionado:', window.selectedDestinationCoords);
    }
}

// Função principal de pesquisa
async function searchRoutes() {
    const originInput = $('#origin').val().trim();
    const destinationInput = $('#destination').val().trim();
    
    if (!validateSearchFields()) {
        notifySearch('Corrija os campos da pesquisa antes de continuar.', 'warning');
        return;
    }
    
    const departureTime = $('#departureTime').val();
    const travelDate = $('#travelDate').val();
    const $searchBtn = $('#searchBtn');
    const originalButtonHtml = $searchBtn.html();

    try {
        $searchBtn.prop('disabled', true).attr('aria-busy', 'true').html('<span class="loading-spinner me-2"></span>A preparar melhor rota...');

        let originCoords = window.selectedOriginCoords;
        let destinationCoords = window.selectedDestinationCoords;

        if (!originCoords || $('#origin').val().trim() !== originInput) {
            originCoords = await resolveAddressToCoords(originInput);
            if (originCoords) {
                window.selectedOriginCoords = { lat: originCoords.lat, lon: originCoords.lon };
                $('#origin').val(originCoords.display);
            }
        }

        if (!destinationCoords || $('#destination').val().trim() !== destinationInput) {
            destinationCoords = await resolveAddressToCoords(destinationInput);
            if (destinationCoords) {
                window.selectedDestinationCoords = { lat: destinationCoords.lat, lon: destinationCoords.lon };
                $('#destination').val(destinationCoords.display);
            }
        }

        if (!originCoords || !destinationCoords) {
            setSearchFieldState('origin', Boolean(originCoords), originCoords ? '' : 'Selecione uma origem sugerida ou reconhecida.');
            setSearchFieldState('destination', Boolean(destinationCoords), destinationCoords ? '' : 'Selecione um destino sugerido ou reconhecido.');
            notifySearch('Não foi possível localizar a origem e o destino. Selecione uma sugestão válida antes de continuar.', 'error');
            return;
        }

        const fromLat = originCoords.lat;
        const fromLon = originCoords.lon;
        const toLat = destinationCoords.lat;
        const toLon = destinationCoords.lon;

        if (![fromLat, fromLon, toLat, toLon].every(Number.isFinite)) {
            setSearchFieldState('origin', false, 'As coordenadas selecionadas para a origem são inválidas.');
            setSearchFieldState('destination', false, 'As coordenadas selecionadas para o destino são inválidas.');
            notifySearch('As coordenadas da pesquisa são inválidas. Tente novamente e selecione uma sugestão.', 'error');
            return;
        }

        console.log('🔍 Pesquisando com coordenadas:', { fromLat, fromLon, toLat, toLon });

        window.location.href = `results.php?fromLat=${fromLat}&fromLon=${fromLon}&toLat=${toLat}&toLon=${toLon}&origin=${encodeURIComponent($('#origin').val().trim())}&dest=${encodeURIComponent($('#destination').val().trim())}&travelDate=${encodeURIComponent(travelDate || '')}&departureTime=${encodeURIComponent(departureTime || '')}`;
    } catch (error) {
        console.warn('Erro ao preparar pesquisa:', error);
        notifySearch('Não foi possível preparar a pesquisa neste momento. Tente novamente.', 'error');
    } finally {
        $searchBtn.prop('disabled', false).removeAttr('aria-busy').html(originalButtonHtml || '<i class="fas fa-search me-2"></i>Pesquisar Rotas');
    }
}

// Ícone baseado no tipo
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

// Label legível
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

// Configurar autocomplete
function setupAutocomplete() {
    const debouncedSearch = debounce(async function(inputId, query) {
        if (query.length >= 3) {
            const suggestions = await fetchAddressSuggestions(query);
            showSuggestions(inputId, suggestions);
        } else {
            $(`#${inputId}Suggestions`).hide();
        }
    }, 500);
    
    $('#origin').on('input', function() {
        window.selectedOriginCoords = null;
        setSearchFieldState('origin', null);
        debouncedSearch('origin', $(this).val());
    });
    
    $('#destination').on('input', function() {
        window.selectedDestinationCoords = null;
        setSearchFieldState('destination', null);
        debouncedSearch('destination', $(this).val());
    });
    
    // Esconder sugestões ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#origin, #originSuggestions, #destination, #destinationSuggestions').length) {
            $('#originSuggestions, #destinationSuggestions').hide();
        }
    });
}

// Inicializar quando o DOM estiver pronto
$(document).ready(function() {
    console.log('Search module initialized');
    setupAutocomplete();
    
    $('#origin, #destination').on('keypress', function(e) {
        if (e.which === 13) {
            searchRoutes();
        }
    });
});

// Exportar funções globais
window.searchRoutes = searchRoutes;
window.selectSuggestion = selectSuggestion;
