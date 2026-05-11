let carrisLines = [];

let currentFilter = 'all';
let searchTerm = '';

$(document).ready(function() {
    loadLines();
    setupSearchListeners();
});

// ==================== FETCH REAL ====================
async function loadLines() {

    $('#linesContainer').html(`
        <div class="text-center p-4">
            <div class="spinner-border text-success"></div>
            <p>A carregar linhas...</p>
        </div>
    `);

    try {

        const res = await fetch('/urban/public/api/lines');
        const data = await res.json();

        if (data.status !== "success") {
            throw new Error("Erro ao buscar linhas");
        }

        carrisLines = data.lines;

        displayLines(carrisLines);

    } catch (e) {
        console.warn(e);
        $('#linesContainer').html("Erro ao carregar linhas");
    }
}

// ==================== DISPLAY ====================
function displayLines(lines) {

    const container = $('#linesContainer');
    container.empty();

    if (lines.length === 0) {
        container.html(`<p class="text-center">Nenhuma linha encontrada</p>`);
        return;
    }

    lines.forEach(line => {
        const lineId = line.id || line.line_id || line.short_name || '';
        const lineName = line.name || line.long_name || line.display_name || line.short_name || 'Linha sem nome';
        const firstPattern = Array.isArray(line.patterns) ? line.patterns[0] : null;
        const patternId = line.pattern_id || firstPattern?.id || firstPattern?.pattern_id || firstPattern || lineId;
        const color = line.color ? `#${line.color.toString().replace('#', '')}` : '#4CAF50';
        const textColor = line.text_color ? `#${line.text_color.toString().replace('#', '')}` : '#FFFFFF';

        const card = `
        <div class="col-md-6 col-lg-4">
            <div class="card line-card h-100" onclick="viewLineDetails('${patternId}', '${lineId}')">

                <div class="card-body">

                    <div class="d-flex justify-content-between">
                        <span class="badge" style="background: ${color}; color: ${textColor};">${lineId}</span>
                        <strong>${lineName}</strong>
                    </div>

                    <div class="mt-2">
                        Linha Carris Metropolitana
                    </div>

                </div>

            </div>
        </div>
        `;

        container.append(card);
    });
}

// ==================== FILTROS ====================
function applyFilters() {

    let filtered = [...carrisLines];

    if (currentFilter !== 'all') {
        filtered = filtered.filter(l => l.area === currentFilter);
    }

    if (searchTerm) {
        filtered = filtered.filter(l =>
            l.name?.toLowerCase().includes(searchTerm) ||
            l.id?.toLowerCase().includes(searchTerm)
        );
    }

    displayLines(filtered);
}

window.filterLines = applyFilters;

window.filterByArea = function(area) {
    currentFilter = area;
    $('.btn-outline-verde').removeClass('active');
    $(`.btn-outline-verde[onclick="filterByArea('${area}')"]`).addClass('active');
    applyFilters();
};

function setupSearchListeners() {

    let debounce;

    $('#searchLine').on('input', function() {

        clearTimeout(debounce);

        debounce = setTimeout(() => {
            searchTerm = $(this).val().toLowerCase();
            applyFilters();
        }, 300);
    });
}

// ==================== MODAL ====================
window.viewLineDetails = async function(patternId, lineId = patternId) {

    try {

        const res = await fetch(`/urban/public/api/line/stops?pattern_id=${encodeURIComponent(patternId)}`);
        const data = await res.json();

        if (data.status !== "success") throw new Error();

        const stops = data.stops;

        let stopsHtml = stops.map(s => `
            <li>${s.stop?.name || 'Paragem'}</li>
        `).join('');

        const modal = `
        <div class="modal fade" id="lineModal">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Linha ${lineId}</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <ul>${stopsHtml}</ul>
                    </div>

                </div>
            </div>
        </div>
        `;

        $('body').append(modal);
        $('#lineModal').modal('show');

        $('#lineModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });

    } catch (e) {
        if (typeof window.showToast === 'function') {
            window.showToast('Erro ao carregar paragens.', 'error');
        } else if (window.App && typeof window.App.showNotification === 'function') {
            window.App.showNotification('Erro ao carregar paragens.', 'error');
        } else {
            console.warn('Erro ao carregar paragens.');
        }
    }
};
