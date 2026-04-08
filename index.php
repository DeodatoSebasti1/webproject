<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>UrbanTraffic — Lisboa</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 960px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
  h1   { font-size: 22px; margin-bottom: 4px; }
  h2   { font-size: 15px; margin: 0 0 12px; border-bottom: 2px solid #ddd; padding-bottom: 6px; }
  p.sub { color: #666; font-size: 13px; margin: 0 0 20px; }
  section { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
  label  { font-size: 13px; display: block; margin-bottom: 4px; font-weight: bold; }
  input  { width: 100%; padding: 7px 10px; font-size: 13px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 10px; }
  button { padding: 8px 18px; background: #1a73e8; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
  button:hover    { background: #1558b0; }
  button:disabled { background: #aaa; cursor: not-allowed; }
  button.sec { background: #fff; color: #1a73e8; border: 1px solid #1a73e8; }
  button.sec:hover { background: #e8f0fe; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { background: #f0f0f0; text-align: left; padding: 6px 10px; font-size: 12px; }
  td { padding: 6px 10px; border-top: 1px solid #eee; vertical-align: top; }
  tr:hover td { background: #fafafa; }
  .route-box { border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-top: 12px; }
  .route-box.primary { border-color: #1a73e8; }
  .route-title { font-weight: bold; font-size: 14px; margin-bottom: 4px; }
  .route-meta  { font-size: 12px; color: #555; margin-bottom: 10px; }
  .segment     { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding: 6px 0; border-top: 1px solid #f0f0f0; font-size: 13px; }
  .seg-stop    { font-weight: 500; }
  .seg-arrow   { color: #aaa; }
  .tag         { font-size: 11px; padding: 2px 7px; border-radius: 3px; white-space: nowrap; }
  .tag-line    { background: #e8f0fe; color: #1a73e8; }
  .tag-time    { background: #fce8e6; color: #c5221f; }
  .tag-eta     { background: #e6f4ea; color: #188038; }
  .tag-sched   { background: #fef7e0; color: #b45309; }
  .msg     { font-size: 13px; color: #666; font-style: italic; margin-top: 8px; }
  .err     { color: #d32f2f; font-size: 13px; }
  .loading { color: #1a73e8; font-size: 13px; }
  .ac-wrap { position: relative; }
  .ac-list { position: absolute; left: 0; right: 0; background: #fff; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; max-height: 200px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
  .ac-item { padding: 8px 10px; cursor: pointer; font-size: 13px; }
  .ac-item:hover, .ac-item.active { background: #e8f0fe; }
  .ac-item small { color: #888; font-size: 11px; display: block; }
  #stops-status { font-size: 12px; color: #888; margin-bottom: 8px; }
</style>
</head>
<body>

<h1> UrbanTraffic</h1>
<p class="sub">Transportes Públicos — dados Carris Metropolitana em tempo real</p>


<section>
  <h2>Calcular Percurso</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div>
      <label> Origem</label>
      <div class="ac-wrap">
        <input id="inp-origin" type="text" placeholder="Pesquisar paragem…" autocomplete="off"/>
        <div class="ac-list" id="ac-origin"></div>
      </div>
    </div>
    <div>
      <label> Destino</label>
      <div class="ac-wrap">
        <input id="inp-dest" type="text" placeholder="Pesquisar paragem…" autocomplete="off"/>
        <div class="ac-list" id="ac-dest"></div>
      </div>
    </div>
  </div>
  <button id="btn-search" disabled>Calcular Rota</button>
  <div id="route-result"></div>
</section>


<section>
  <h2>Próximos Autocarros numa Paragem</h2>
  <div style="display:flex;gap:8px;">
    <div class="ac-wrap" style="flex:1;">
      <input id="inp-eta-stop" type="text" placeholder="Pesquisar paragem…" autocomplete="off" style="margin:0;"/>
      <div class="ac-list" id="ac-eta"></div>
    </div>
    <button id="btn-eta" disabled>Ver chegadas</button>
  </div>
  <div id="eta-result" class="msg" style="margin-top:10px;">Seleciona uma paragem.</div>
</section>


<section>
  <h2>Veículos em Serviço <span style="font-size:11px;color:#888;font-weight:normal;">(tempo real)</span></h2>
  <button id="btn-buses">Atualizar</button>
  <span id="buses-count" style="font-size:12px;color:#888;margin-left:10px;"></span>
  <div id="buses-result" class="msg" style="margin-top:10px;">Clique em "Atualizar".</div>
</section>


<section>
  <h2 style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;margin-bottom:0;" onclick="toggleStops()">
    Paragens
    <span id="stops-toggle" style="font-size:12px;font-weight:normal;color:#1a73e8;">▼ expandir</span>
  </h2>
  <div id="stops-body" style="display:none;margin-top:12px;">
    <div id="stops-status"></div>
    <input id="inp-stops-search" type="text" placeholder="Filtrar por nome…" style="max-width:320px;"/>
    <div id="stops-result"></div>
  </div>
</section>


<section>
  <h2>Linhas</h2>
  <button id="btn-routes">Carregar Linhas</button>
  <div id="routes-result" class="msg" style="margin-top:10px;">Clique para carregar as linhas.</div>
</section>

<script>
const API = 'api';


function fmtTime(mins) {
  if (mins == null) return '—';
  const h = Math.floor(mins / 60), m = Math.round(mins % 60);
  return h > 0 ? `${h}h ${m}min` : `${m} min`;
}
function fmtSpeed(s) { return (s != null && s !== '') ? parseFloat(s).toFixed(0) + ' km/h' : '—'; }
function fmtTs(ts)   { return ts ? new Date(ts).toLocaleTimeString('pt-PT') : '—'; }

async function apiFetch(url) {
  const r    = await fetch(url);
  const data = await r.json();
  if (!r.ok) throw new Error(data.message || `HTTP ${r.status}`);
  return data;
}


let allStops = [];
let stopsLoaded = false;

function toggleStops() {
  const body   = document.getElementById('stops-body');
  const toggle = document.getElementById('stops-toggle');
  const open   = body.style.display === 'none';
  body.style.display = open ? 'block' : 'none';
  toggle.textContent = open ? '▲ minimizar' : '▼ expandir';
  if (open && !stopsLoaded) loadStops();
}

async function loadStops() {
  const status = document.getElementById('stops-status');
  try {
    const d  = await apiFetch(`${API}/stops.php`);
    allStops = d.stops || [];
    stopsLoaded = true;
    status.textContent = `${allStops.length} paragens carregadas.`;
    status.className   = '';
    renderStopsTable(allStops);
  } catch(e) {
    status.innerHTML = `<span class="err">Erro ao carregar paragens: ${e.message}</span>`;
  }
}

function renderStopsTable(stops) {
  const el = document.getElementById('stops-result');
  if (!stops.length) { el.innerHTML = '<span class="msg">Nenhuma paragem encontrada.</span>'; return; }
  const shown = stops.slice(0, 300);
  el.innerHTML = `
    <table>
      <tr><th>ID</th><th>Nome</th><th>Localidade</th><th>Linhas</th></tr>
      ${shown.map(s => `<tr>
        <td style="color:#888;font-size:11px;">${s.stop_id}</td>
        <td>${s.stop_name}</td>
        <td style="color:#666;">${s.locality || '—'}</td>
        <td style="font-size:11px;">${(s.lines||[]).slice(0,6).map(l=>`<span class="tag tag-line">${l}</span>`).join(' ')}</td>
      </tr>`).join('')}
      ${stops.length > 300 ? `<tr><td colspan="4" style="color:#888;font-size:12px;">… mais ${stops.length-300} paragens. Use o filtro.</td></tr>` : ''}
    </table>`;
}

document.getElementById('inp-stops-search').addEventListener('input', function() {
  const q = this.value.trim().toLowerCase();
  renderStopsTable(q ? allStops.filter(s => s.stop_name.toLowerCase().includes(q)) : allStops);
});


function setupAC(inputId, listId, onSelect) {
  const inp  = document.getElementById(inputId);
  const list = document.getElementById(listId);
  let idx = -1;

  inp.addEventListener('input', () => {
    const q = inp.value.trim().toLowerCase();
    list.innerHTML = ''; idx = -1;
    if (q.length === 0) { onSelect(null); list.style.display = 'none'; return; }
    if (q.length < 2)   { list.style.display = 'none'; return; }
    const hits = allStops.filter(s => s.stop_name.toLowerCase().includes(q)).slice(0, 10);
    if (!hits.length)   { list.style.display = 'none'; return; }
    hits.forEach(s => {
      const div = document.createElement('div');
      div.className = 'ac-item';
      div.innerHTML = `${s.stop_name}<small>${s.stop_id}${s.locality ? ' · '+s.locality : ''}</small>`;
      div.onclick = () => {
        inp.value = s.stop_name;
        list.style.display = 'none';
        onSelect(s);
        checkReady();
      };
      list.appendChild(div);
    });
    list.style.display = 'block';
  });

  inp.addEventListener('keydown', e => {
    const items = list.querySelectorAll('.ac-item');
    if      (e.key === 'ArrowDown')            { idx = Math.min(idx+1, items.length-1); hi(items); }
    else if (e.key === 'ArrowUp')              { idx = Math.max(idx-1, 0); hi(items); }
    else if (e.key === 'Enter' && idx >= 0)    { items[idx].click(); }
    else if (e.key === 'Escape')               { list.style.display = 'none'; }
  });

  document.addEventListener('click', e => {
    if (!inp.contains(e.target) && !list.contains(e.target)) list.style.display = 'none';
  });

  function hi(items) {
    items.forEach(i => i.classList.remove('active'));
    if (items[idx]) items[idx].classList.add('active');
  }
}


let selOrigin = null, selDest = null;

setupAC('inp-origin', 'ac-origin', s => { selOrigin = s; });
setupAC('inp-dest',   'ac-dest',   s => { selDest   = s; });

function checkReady() {
  document.getElementById('btn-search').disabled = !(selOrigin && selDest);
}

document.getElementById('btn-search').addEventListener('click', async () => {
  const el = document.getElementById('route-result');
  el.innerHTML = '<span class="loading">A calcular rota… (pode demorar alguns segundos)</span>';
  try {
    const d = await apiFetch(`${API}/searchRoute.php?from=${selOrigin.stop_id}&to=${selDest.stop_id}`);
    if (d.status !== 'success' || !d.routes?.length) {
      el.innerHTML = `<span class="msg">${d.message || 'Nenhuma rota encontrada.'}</span>`; return;
    }
    el.innerHTML = d.routes.map((r, i) => routeBox(r, i === 0)).join('');
  } catch(e) {
    el.innerHTML = `<span class="err">Erro: ${e.message}</span>`;
  }
});

function routeBox(r, primary) {
  const segs = (r.segments || []).map(s => `
    <div class="segment">
      <span class="seg-stop">${s.from.stop_name}</span>
      <span class="seg-arrow">→</span>
      <span class="seg-stop">${s.to.stop_name}</span>
      <span class="tag tag-line">${s.route_name}</span>
      <span class="tag tag-time">⏱ ${s.travel_time} min</span>
      ${s.estimated_arrival ? `<span class="tag tag-eta">🚌 ${s.estimated_arrival}</span>` : ''}
      ${s.scheduled_arrival ? `<span class="tag tag-sched">🕐 ${s.scheduled_arrival}</span>` : ''}
    </div>`).join('');

  return `
    <div class="route-box ${primary ? 'primary' : ''}">
      <div class="route-title">${r.label}</div>
      <div class="route-meta">
        ⏱ <strong>${fmtTime(r.total_travel_time)}</strong> &nbsp;·&nbsp;
        🔄 <strong>${r.transfers}</strong> transbordo(s) &nbsp;·&nbsp;
        🚏 <strong>${r.stop_count}</strong> paragens
      </div>
      ${segs}
    </div>`;
}


let selEtaStop = null;
setupAC('inp-eta-stop', 'ac-eta', s => {
  selEtaStop = s;
  document.getElementById('btn-eta').disabled = !s;
});

document.getElementById('btn-eta').addEventListener('click', async () => {
  const el = document.getElementById('eta-result');
  el.innerHTML = '<span class="loading">A carregar chegadas…</span>';
  try {
    const d        = await apiFetch(`${API}/stopEtas.php?stop_id=${selEtaStop.stop_id}`);
    const arrivals = d.arrivals || [];
    if (!arrivals.length) { el.innerHTML = '<span class="msg">Sem dados de chegada disponíveis.</span>'; return; }
    el.innerHTML = `
      <table>
        <tr><th>Linha</th><th>Destino</th><th>Prevista</th><th>Estimada</th></tr>
        ${arrivals.map(a => `<tr>
          <td><span class="tag tag-line">${a.line_id||'—'}</span></td>
          <td>${a.headsign||'—'}</td>
          <td>${a.scheduled_arrival||'—'}</td>
          <td><strong>${a.estimated_arrival||'—'}</strong></td>
        </tr>`).join('')}
      </table>`;
  } catch(e) {
    el.innerHTML = `<span class="err">Erro: ${e.message}</span>`;
  }
});


document.getElementById('btn-buses').addEventListener('click', async () => {
  const el    = document.getElementById('buses-result');
  const count = document.getElementById('buses-count');
  el.innerHTML = '<span class="loading">A carregar veículos…</span>';
  count.textContent = '';
  try {
    const d     = await apiFetch(`${API}/busPositions.php`);
    const buses = d.buses || [];
    count.textContent = `${buses.length} veículos · ${fmtTs(d.timestamp)}`;
    if (!buses.length) { el.innerHTML = '<span class="msg">Nenhum veículo em serviço.</span>'; return; }
    el.innerHTML = `
      <table>
        <tr><th>ID</th><th>Linha</th><th>Lat</th><th>Lon</th><th>Velocidade</th><th>Direção</th><th>Hora</th></tr>
        ${buses.map(b => `<tr>
          <td style="font-size:11px;color:#888;">${b.bus_id}</td>
          <td><span class="tag tag-line">${b.route_name}</span></td>
          <td>${parseFloat(b.latitude).toFixed(5)}</td>
          <td>${parseFloat(b.longitude).toFixed(5)}</td>
          <td>${fmtSpeed(b.speed)}</td>
          <td>${b.heading != null ? b.heading+'°' : '—'}</td>
          <td style="font-size:11px;color:#888;">${fmtTs(b.recorded_at)}</td>
        </tr>`).join('')}
      </table>`;
  } catch(e) {
    el.innerHTML = `<span class="err">Erro: ${e.message}</span>`;
  }
});


document.getElementById('btn-routes').addEventListener('click', async () => {
  const el = document.getElementById('routes-result');
  el.innerHTML = '<span class="loading">A carregar linhas…</span>';
  try {
    const d      = await apiFetch(`${API}/routes.php`);
    const routes = d.routes || [];
    if (!routes.length) { el.innerHTML = '<span class="msg">Nenhuma linha disponível.</span>'; return; }
    el.innerHTML = `
      <p style="font-size:12px;color:#888;margin-bottom:8px;">${routes.length} linhas</p>
      <table>
        <tr><th>Linha</th><th>Nome</th><th>Municípios</th><th></th></tr>
        ${routes.map(r => `
          <tr id="row-${r.route_id}">
            <td><span class="tag tag-line">${r.short_name||r.route_id}</span></td>
            <td>${r.route_name}</td>
            <td style="font-size:11px;color:#666;">${(r.municipalities||[]).slice(0,3).join(', ')}</td>
            <td><button class="sec" onclick="loadLineStops('${r.route_id}', this)" style="padding:4px 10px;font-size:11px;">Ver paragens</button></td>
          </tr>
          <tr id="ls-${r.route_id}" style="display:none">
            <td colspan="4"><div id="ls-content-${r.route_id}"></div></td>
          </tr>`).join('')}
      </table>`;
  } catch(e) {
    el.innerHTML = `<span class="err">Erro: ${e.message}</span>`;
  }
});

async function loadLineStops(lineId, btn) {
  
  const row     = document.getElementById(`ls-${lineId}`);
  const content = document.getElementById(`ls-content-${lineId}`);
  if (!row) return;
  if (row.style.display === 'table-row') { row.style.display = 'none'; btn.textContent = 'Ver paragens'; return; }
  row.style.display = 'table-row';
  btn.textContent   = 'Fechar';
  content.innerHTML = '<span class="loading">A carregar…</span>';
  try {
    
    const lineData = await apiFetch(`${API}/routes.php`);
    const line     = (lineData.routes||[]).find(r => r.route_id === lineId);
    const patternId = line?.patterns?.[0];
    if (!patternId) { content.innerHTML = '<span class="msg">Sem percurso disponível.</span>'; return; }

    const d     = await apiFetch(`${API}/routes.php?line_id=${patternId}`);
    const stops = d.stops || [];
    content.innerHTML = stops.length
      ? `<table style="margin:6px 0">
          <tr><th>#</th><th>ID</th><th>Nome</th><th>Chegada</th><th>Partida</th></tr>
          ${stops.map(s => `<tr>
            <td>${s.stop_order}</td>
            <td style="color:#888;font-size:11px;">${s.stop_id}</td>
            <td>${s.stop_name}</td>
            <td>${s.arrival_time||'—'}</td>
            <td>${s.departure_time||'—'}</td>
          </tr>`).join('')}
         </table>`
      : '<span class="msg">Sem paragens registadas.</span>';
  } catch(e) {
    content.innerHTML = `<span class="err">Erro: ${e.message}</span>`;
  }
}


loadStops();
</script>
</body>
</html>