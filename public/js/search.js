let selectedOrigin = null;
let selectedDestination = null;

// ==================== FETCH PLACES (ESTILO GOOGLE MAPS) ====================
async function fetchPlaces(query) {

    if (!query || query.length < 3) return [];

    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=pt&limit=5&addressdetails=1&namedetails=1`
        );

        if (!res.ok) return [];

        const data = await res.json();

        return data.map(place => {
            // Formatar nome estilo Google Maps
            let displayName = '';
            let secondaryText = '';
            
            // Se for uma paragem de autocarro
            if (place.type === 'bus_stop') {
                displayName = place.display_name.split(',')[0].trim();
                // Procurar a localidade
                if (place.address?.city) secondaryText = place.address.city;
                else if (place.address?.town) secondaryText = place.address.town;
                else if (place.address?.village) secondaryText = place.address.village;
                else if (place.address?.suburb) secondaryText = place.address.suburb;
            }
            // Se for aeroporto
            else if (place.type === 'aerodrome' || place.class === 'aeroway') {
                displayName = place.display_name.split(',')[0].trim();
                secondaryText = 'Aeroporto';
            }
            // Se for estação de comboio/metro
            else if (place.type === 'station' || place.type === 'railway') {
                displayName = place.display_name.split(',')[0].trim();
                if (place.address?.city) secondaryText = place.address.city;
                else secondaryText = 'Estação';
            }
            // Se for uma rua/avenida
            else if (place.address?.road) {
                displayName = place.address.road;
                // Adicionar número se existir
                if (place.address?.house_number) {
                    displayName += `, ${place.address.house_number}`;
                }
                // Localidade
                if (place.address?.city) secondaryText = place.address.city;
                else if (place.address?.town) secondaryText = place.address.town;
                else if (place.address?.suburb) secondaryText = place.address.suburb;
            }
            // Se for uma cidade/bairro
            else if (place.address?.city || place.address?.town || place.address?.village) {
                displayName = place.address.city || place.address.town || place.address.village;
                if (place.address?.district) {
                    secondaryText = place.address.district;
                } else if (place.address?.county) {
                    secondaryText = place.address.county;
                }
            }
            // Se for um local de interesse
            else if (place.type === 'attraction' || place.type === 'tourism') {
                displayName = place.display_name.split(',')[0].trim();
                if (place.address?.city) secondaryText = place.address.city;
            }
            // Fallback: tentar extrair nome limpo
            else {
                let parts = place.display_name.split(',');
                displayName = parts[0].trim();
                if (parts.length > 1) {
                    secondaryText = parts[1].trim();
                }
            }
            
            // Remover caracteres especiais
            displayName = displayName.replace(/[()]/g, '').trim();
            secondaryText = secondaryText.replace(/[()]/g, '').trim();
            
            return {
                name: displayName,
                secondary: secondaryText,
                full_name: place.display_name,
                lat: parseFloat(place.lat),
                lon: parseFloat(place.lon),
                type: place.type,
                address: place.address
            };
        });

    } catch (e) {
        console.error("Erro na API:", e);
        return [];
    }
}

// ==================== MOSTRAR SUGESTÕES (ESTILO GOOGLE MAPS) ====================
function showSuggestions(inputId, suggestions) {

    const box = $(`#${inputId}Suggestions`);
    box.empty();

    if (suggestions.length === 0) {
        box.hide();
        return;
    }

    suggestions.forEach(s => {
        // Escolher ícone baseado no tipo
        let icon = '📍';
        if (s.type === 'bus_stop') icon = '🚏';
        else if (s.type === 'aerodrome') icon = '✈️';
        else if (s.type === 'station' || s.type === 'railway') icon = '🚂';
        else if (s.address?.road) icon = '🛣️';
        else if (s.address?.city) icon = '🏙️';
        
        const item = $(`
            <div class="suggestion-item">
                <div class="suggestion-icon">${icon}</div>
                <div class="suggestion-info">
                    <div class="suggestion-title">${s.name}</div>
                    ${s.secondary ? `<div class="suggestion-subtitle">${s.secondary}</div>` : ''}
                </div>
            </div>
        `);

        item.on("click", function () {
            selectSuggestion(inputId, s);
        });

        box.append(item);
    });

    box.show();
}

// ==================== SELECIONAR ====================
function selectSuggestion(inputId, place) {

    // Mostrar apenas o nome principal no input
    $(`#${inputId}`).val(place.name);
    $(`#${inputId}Suggestions`).hide();

    if (inputId === "origin") {
        selectedOrigin = place;
    } else {
        selectedDestination = place;
    }
}

// ==================== DEBOUNCE ====================
function debounce(fn, delay) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ==================== ESCONDER AO CLICAR FORA ====================
$(document).on('click', function (e) {
    if (!$(e.target).closest('.position-relative').length) {
        $('.suggestions-box').hide();
    }
});

// ==================== LISTENERS ====================
$(document).ready(function () {

    console.log("search.js carregado");

    $('#origin').on('input', debounce(async function () {

        const val = $(this).val();

        const places = await fetchPlaces(val);
        showSuggestions("origin", places);

    }, 300));

    $('#destination').on('input', debounce(async function () {

        const val = $(this).val();

        const places = await fetchPlaces(val);
        showSuggestions("destination", places);

    }, 300));

});

// ==================== SEARCH ====================
async function searchRoutes() {
    if (!selectedOrigin || !selectedDestination) {
        alert("Seleciona origem e destino válidos");
        return;
    }

    const btn = document.querySelector('.btn-urbano');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>A encontrar paragens...';
    }

    try {
        const originStop = await findNearestStop(selectedOrigin.lat, selectedOrigin.lon);
        const destStop = await findNearestStop(selectedDestination.lat, selectedDestination.lon);

        if (!originStop || !destStop) {
            alert("Não foi possível encontrar paragens próximas");
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search me-2"></i>Pesquisar Rotas';
            }
            return;
        }

        console.log("Stop origem:", originStop);
        console.log("Stop destino:", destStop);

        const BASE = "/urban/public";
        window.location.href = `${BASE}/results.php?fromLat=${originStop.stop_lat}&fromLon=${originStop.stop_lon}&toLat=${destStop.stop_lat}&toLon=${destStop.stop_lon}`;

    } catch (error) {
        console.error("Erro:", error);
        alert("Erro ao calcular rota");
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-2"></i>Pesquisar Rotas';
        }
    }
}

// ==================== ENCONTRAR STOP MAIS PRÓXIMO ====================
async function findNearestStop(lat, lon) {
    const res = await fetch(`/urban/app/controllers/RouteController.php?findNearestStop=1&lat=${lat}&lon=${lon}`);
    const data = await res.json();
    
    if (data.status === "success") {
        return {
            stop_id: data.stop.stop_id,
            stop_name: data.stop.stop_name,
            stop_lat: data.stop.stop_lat,
            stop_lon: data.stop.stop_lon,
            walk_distance: data.walk_distance,
            walk_time: data.walk_time
        };
    }
    return null;
}

// ==================== EXPORT ====================
window.searchRoutes = searchRoutes;