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

        const res = await fetch('/urban-traffic/public/lines');
        const data = await res.json();

        if (data.status !== "success") {
            throw new Error("Erro ao buscar linhas");
        }

        carrisLines = data.lines;

        displayLines(carrisLines);

    } catch (e) {
        console.error(e);
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

        const card = `
        <div class="col-md-6 col-lg-4">
            <div class="card line-card h-100" onclick="viewLineDetails('${line.id}')">

                <div class="card-body">

                    <div class="d-flex justify-content-between">
                        <span class="badge bg-success">${line.id}</span>
                        <strong>${line.name}</strong>
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
window.viewLineDetails = async function(lineId) {

    try {

        const res = await fetch(`/urban-traffic/public/line/stops?pattern_id=${lineId}`);
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
        alert("Erro ao carregar paragens");
    }
};