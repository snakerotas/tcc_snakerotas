<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Snake rotas</title>

  <!-- Mantém o mesmo SDK/API TomTom do seu projeto -->
  <script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.15.0/maps/maps-web.min.js"></script>
  <link rel="stylesheet" href="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.15.0/maps/maps.css" />

<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="topbar">
    <div class="topbar-inner">
      <div class="logo"><span class="dot"></span> Snake<span style="color:var(--brand)">Rotas</span></div>
      <div class="searchstack">
        <!-- Autocomplete para adicionar Waypoint (já existia) -->
        <div class="searchgroup">
          <input id="searchInput" type="text" placeholder="Buscar endereço, cidade ou POI (Enter para adicionar waypoint)" autocomplete="off" />
          <div id="suggestions" class="suggestions"></div>
        </div>
        <!-- NOVO: Autocomplete para DESTINO (com histórico e teclas) -->
        <div class="row">
          <div class="searchgroup" style="width:100%">
            <input id="destInput" type="text" placeholder="Destino (digite para ver sugestões)" autocomplete="off" />
            <div id="destSuggestions" class="suggestions"></div>
          </div>
          <button class="btn" id="btnSetDest">Destino</button>
        </div>
      </div>
      <div class="top-actions">
        <button class="btn" id="btnLocate">Minha posição</button>
        <button class="btn" id="btnClear">Limpar</button>
        <button class="btn primary" id="btnRoute">Traçar rota</button>
      </div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <div class="panel">
        <div class="hd"><strong>Explorar por categoria</strong></div>
        <div class="bd">
          <div class="row" style="margin-bottom:.5rem">
            <select id="category">
              <option value="supermarket">Mercado</option>
              <option value="pharmacy">Farmácia</option>
              <option value="restaurant">Restaurante</option>
              <option value="hospital">Hospital</option>
              <option value="bank">Banco</option>
              <option value="fuel">Posto</option>
              <option value="parking">Estacionamento</option>
              <option value="shopping">Shopping</option>
            </select>
            <button class="btn" id="btnPOI">Buscar</button>
          </div>

          <!-- NOVO: Input com autocomplete + histórico (categorias) -->
          <div class="row" style="margin-bottom:.5rem">
            <div class="searchgroup" style="width:100%">
              <input id="categoryInput" type="text" placeholder="Categoria (ex: Restaurante, Café, Hospital...)" autocomplete="off" />
              <div id="catSuggestions" class="suggestions"></div>
            </div>
            <button class="btn" id="btnPOIText">Buscar</button>
          </div>

          <!-- NOVO: Raio de busca -->
          <div class="row">
            <span class="small" style="min-width:62px">Raio</span>
            <input id="radiusRange" type="range" min="300" max="30000" step="300" value="3000" style="flex:1">
            <div class="stat" id="radiusLabel">3,0 km</div>
          </div>

          <div class="chips" style="margin-top:.75rem">
            <span class="chip" data-cat="cafe">☕ Café</span>
            <span class="chip" data-cat="bar">🍻 Bar</span>
            <span class="chip" data-cat="fastfood">🍔 Fast-food</span>
            <span class="chip" data-cat="atm">🏧 ATM</span>
            <span class="chip" data-cat="hotel">🏨 Hotel</span>
            <span class="chip" data-cat="park">🌳 Parque</span>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="hd"><strong>Waypoints</strong><span class="muted"> &nbsp; (clique no mapa ou use a busca acima)</span></div>
        <div class="bd"><div id="wpList" class="list"></div></div>
      </div>

      <div class="panel">
        <div class="hd"><strong>Locais encontrados</strong></div>
        <div class="bd"><div id="poiList" class="list"></div></div>
      </div>

      <div class="panel">
        <div class="hd"><strong>Passo-a-passo da rota</strong></div>
        <div class="bd"><div id="stepsList" class="list" style="max-height: 26vh"></div></div>
      </div>
    </aside>

    <div id="map" class="panel"></div>
  </div>

  <div class="footerbar">
    <div class="stat">Distância: <strong id="statDistance">—</strong></div>
    <div class="stat">Tempo: <strong id="statTime">—</strong></div>
    <div class="stat">Waypoints: <strong id="statWps">0</strong></div>
  </div>

  <script>
    // ===================== CONFIG / ESTADO =====================
    const apiKey = "cwodq1B0ax9m9HtDV2iM2yj50Eugb05p"; // mesma key
    let map, routeLayerId = 'route-line', routeSourceId = 'route-src';
    let origin = null;            // {lat, lon}
    let destination = null;       // {lat, lon, marker, label}
    const waypoints = [];         // [{id, lat, lon, marker, label}]
    const poiMarkers = [];

    const wpList = document.getElementById('wpList');
    const poiList = document.getElementById('poiList');
    const stepsList = document.getElementById('stepsList');
    const statDistance = document.getElementById('statDistance');
    const statTime = document.getElementById('statTime');
    const statWps = document.getElementById('statWps');

    // NOVO: elementos categoria + raio
    const categoryInput = document.getElementById('categoryInput');
    const catSuggestions = document.getElementById('catSuggestions');
    const btnPOIText = document.getElementById('btnPOIText');
    const radiusRange = document.getElementById('radiusRange');
    const radiusLabel = document.getElementById('radiusLabel');
    let currentRadius = Number(radiusRange.value); // metros

    // ===================== HISTÓRICO (localStorage) =====================
    const HIST_DEST = 'hist_dest_v1';
    const HIST_CAT  = 'hist_cat_v1';

    function loadHistory(key){ try{ return JSON.parse(localStorage.getItem(key)||'[]'); }catch{ return [] } }
    function saveHistory(key, value){
      if(!value) return;
      let arr = loadHistory(key).filter(v=> v.toLowerCase() !== value.toLowerCase());
      arr.unshift(value);
      if(arr.length>5) arr = arr.slice(0,5);
      localStorage.setItem(key, JSON.stringify(arr));
    }

    // ===================== MAPA =====================
    function initMap(lat, lon){
      origin = { lat, lon };
      map = tt.map({ key: apiKey, container: 'map', center: [lon, lat], zoom: 13 });
      map.addControl(new tt.NavigationControl());

      // Origem bem visível
      stdMarker('#22c55e').setLngLat([lon, lat]).addTo(map);

      // Clique para adicionar waypoint — usando e.lngLat (lng, lat) corretamente
      map.on('click', (e)=>{
        const { lng, lat } = e.lngLat; // ordem correta
        addWaypoint(lat, lng);
      });
    }

    // Inicialização com geolocalização (fallback SP)
    if (navigator.geolocation){
      navigator.geolocation.getCurrentPosition(p=> initMap(p.coords.latitude, p.coords.longitude), ()=> initMap(-23.55052, -46.633309));
    } else { initMap(-23.55052, -46.633309); }

    // ===================== MARCADORES PADRÃO =====================
    function stdMarker(color = '#ff3b6a', draggable = false){
      return new tt.Marker({ color, draggable, anchor: 'bottom' });
    }

    function addWaypoint(lat, lon, label = 'Waypoint'){
      const id = Date.now() + Math.random().toString(36).slice(2,6);
      const marker = stdMarker('#ff3b6a', true).setLngLat([lon, lat]).addTo(map);
      const wp = { id, lat, lon, marker, label };
      marker.on('dragend', ()=>{ const p = marker.getLngLat(); wp.lat = p.lat; wp.lon = p.lng; renderWaypoints(); });
      waypoints.push(wp); renderWaypoints();
    }

    function removeWaypoint(id){ const i = waypoints.findIndex(w=>w.id===id); if(i>-1){ waypoints[i].marker.remove(); waypoints.splice(i,1); renderWaypoints(); } }
    function moveWaypoint(i, d){ const j=i+d; if(j<0||j>=waypoints.length) return; const t=waypoints[i]; waypoints[i]=waypoints[j]; waypoints[j]=t; renderWaypoints(); }

    function renderWaypoints(){
      statWps.textContent = waypoints.length;
      wpList.innerHTML = '';
      waypoints.forEach((w,i)=>{
        const div = document.createElement('div'); div.className='card';
        div.innerHTML = `<div><strong>#${i+1}</strong> ${w.label}</div><div class="small">${w.lat.toFixed(5)}, ${w.lon.toFixed(5)}</div>`;
        const row = document.createElement('div'); row.className='controls';
        row.append(btn('↑',()=>moveWaypoint(i,-1)), btn('↓',()=>moveWaypoint(i,1)), btn('Ir',()=>map.flyTo({center:[w.lon,w.lat], zoom:15})), btn('Remover',()=>removeWaypoint(w.id)));
        div.appendChild(row); wpList.appendChild(div);
      });
    }

    function btn(t, fn){ const b=document.createElement('button'); b.className='btn'; b.textContent=t; b.onclick=fn; return b; }

    // ===================== BUSCAS (Geocode, Autocomplete, POIs) =====================
    async function geocode(query){
      const url = `https://api.tomtom.com/search/2/search/${encodeURIComponent(query)}.json?key=${apiKey}&limit=1`;
      const r = await fetch(url); const data = await r.json();
      if(data.results?.length){ const x=data.results[0]; return { lat:x.position.lat, lon:x.position.lon, label:x.poi?.name||x.address?.freeformAddress||query }; }
      throw new Error('Não encontrado');
    }

    async function autocomplete(q, latBias, lonBias){
      const url = `https://api.tomtom.com/search/2/autocomplete/${encodeURIComponent(q)}.json?key=${apiKey}&lat=${latBias}&lon=${lonBias}&limit=8&language=pt-BR`;
      const r = await fetch(url); return r.json();
    }

    // ---------- Autocomplete de WAYPOINT (Enter para adicionar) ----------
    const searchInput = document.getElementById('searchInput');
    const suggestions = document.getElementById('suggestions');
    let tmr = null;

    searchInput.addEventListener('keydown', async (ev)=>{
      if (ev.key === 'Enter') { ev.preventDefault(); addFromSearch(); }
    });
    searchInput.addEventListener('input', ()=>{
      const q = searchInput.value.trim();
      if (tmr) clearTimeout(tmr);
      if (!q) { suggestions.style.display='none'; return; }
      tmr = setTimeout(async ()=>{
        try{
          const c = map.getCenter();
          const latBias = origin?.lat ?? c.lat; const lonBias = origin?.lon ?? c.lng;
          const data = await autocomplete(q, latBias, lonBias);
          suggestions.innerHTML='';
          (data.results||[]).forEach(item=>{
            const text = item.address?.freeformAddress || item.poi?.name || item.match?.freeformAddress || q;
            const el = document.createElement('div'); el.className='item'; el.textContent = text;
            el.onclick = async ()=>{
              searchInput.value=text; suggestions.style.display='none';
              try{
                if(item.position){
                  addWaypoint(item.position.lat, item.position.lon, text);
                  map.flyTo({center:[item.position.lon,item.position.lat], zoom:14});
                } else {
                  const r = await geocode(text);
                  addWaypoint(r.lat, r.lon, r.label);
                  map.flyTo({center:[r.lon,r.lat], zoom:14});
                }
              }catch(e){ alert('Não encontrado'); }
            };
            suggestions.appendChild(el);
          });
          suggestions.style.display = (data.results||[]).length ? 'block' : 'none';
        }catch{ suggestions.style.display='none'; }
      }, 220);
    });

    async function addFromSearch(){
      const q=searchInput.value.trim(); if(!q) return;
      try{ const r=await geocode(q); addWaypoint(r.lat, r.lon, r.label); map.flyTo({center:[r.lon,r.lat], zoom:14}); }catch(e){ alert('Não encontrado'); }
    }

    // ---------- NOVO: Autocomplete de DESTINO (com histórico + teclas) ----------
    const destInput = document.getElementById('destInput');
    const destSuggestions = document.getElementById('destSuggestions');
    let tmrDest = null;
    let destActiveIndex = -1;
    let destCurrentItems = []; // [{text, position?}]

    function buildDestItems(apiResults){
      const history = loadHistory(HIST_DEST).map(t=>({ text:t })); // sem position -> usa geocode
      const apiItems = (apiResults||[]).map(item=>({
        text: item.address?.freeformAddress || item.poi?.name || item.match?.freeformAddress || '',
        position: item.position // se existir, não precisa geocode
      })).filter(x=>x.text);
      // Merge com dedupe pelo texto (histórico primeiro)
      const mapSeen = new Set();
      const merged = [];
      [...history, ...apiItems].forEach(it=>{
        const key = it.text.toLowerCase();
        if(!mapSeen.has(key)){ mapSeen.add(key); merged.push(it); }
      });
      return merged.slice(0,10);
    }

    function renderDestSuggestions(items){
      destCurrentItems = items;
      destActiveIndex = -1;
      destSuggestions.innerHTML = '';
      items.forEach((it, idx)=>{
        const row = document.createElement('div'); row.className = 'item'; row.textContent = it.text;
        row.onclick = ()=> pickDestination(idx);
        destSuggestions.appendChild(row);
      });
      destSuggestions.style.display = items.length ? 'block' : 'none';
    }

    function pickDestination(index){
      const item = destCurrentItems[index]; if(!item) return;
      destInput.value = item.text; destSuggestions.style.display='none';
      // salva histórico
      // histórico removido
      // define destino
      (async ()=>{
        try{
          if(item.position){ setDestination(item.position.lat, item.position.lon, item.text); map.flyTo({center:[item.position.lon, item.position.lat], zoom:14}); }
          else { const r = await geocode(item.text); setDestination(r.lat, r.lon, r.label); map.flyTo({center:[r.lon, r.lat], zoom:14}); }
        }catch(e){ alert('Destino não encontrado'); }
      })();
    }

    function updateDestActive(){
      [...destSuggestions.children].forEach((el,i)=> el.classList.toggle('active', i===destActiveIndex));
    }

    destInput.addEventListener('keydown', (ev)=>{
      const count = destCurrentItems.length;
      if(ev.key === 'ArrowDown' && count){ ev.preventDefault(); destActiveIndex = (destActiveIndex+1)%count; updateDestActive(); }
      if(ev.key === 'ArrowUp' && count){ ev.preventDefault(); destActiveIndex = (destActiveIndex-1+count)%count; updateDestActive(); }
      if(ev.key === 'Enter'){ ev.preventDefault(); if(destActiveIndex>=0) pickDestination(destActiveIndex); else pickFirstDestOrGeocode(); }
      if(ev.key === 'Escape'){ destSuggestions.style.display='none'; }
    });

    destInput.addEventListener('focus', ()=>{
      const q = destInput.value.trim();
      if(!q){ // mostra somente histórico ao focar vazio
        const items = buildDestItems([]);
        renderDestSuggestions(items);
      }
    });

    destInput.addEventListener('input', ()=>{
      const q = destInput.value.trim();
      if (tmrDest) clearTimeout(tmrDest);
      if (!q) { // apenas histórico
        renderDestSuggestions(buildDestItems([]));
        return;
      }
      tmrDest = setTimeout(async ()=>{
        try{
          const c = map.getCenter();
          const latBias = origin?.lat ?? c.lat; const lonBias = origin?.lon ?? c.lng;
          const data = await autocomplete(q, latBias, lonBias);
          renderDestSuggestions(buildDestItems(data.results||[]));
        }catch{ destSuggestions.style.display='none'; }
      }, 220);
    });

    function pickFirstDestOrGeocode(){
      const first = destSuggestions.querySelector('.item');
      if (first){ first.click(); return; }
      document.getElementById('btnSetDest').click();
    }

    // Botão "Destino" (continua aceitando digitar manual e geocodificar)
    document.getElementById('btnSetDest').onclick = async ()=>{
      const q = destInput.value.trim(); if(!q) return;
      try{
        const r = await geocode(q);
        setDestination(r.lat, r.lon, r.label);
        // histórico removido
        map.flyTo({center:[r.lon,r.lat], zoom:14});
      }catch(e){ alert('Destino não encontrado'); }
    };

    // Fecha sugestões ao clicar fora dos campos
    document.addEventListener('click', (e)=>{
      const inSearch = e.target.closest?.('.searchgroup');
      if (!inSearch){ suggestions.style.display='none'; destSuggestions.style.display='none'; catSuggestions.style.display='none'; }
    });

    function setDestination(lat, lon, label='Destino'){
      if (destination?.marker) destination.marker.remove();
      const marker = stdMarker('#3ab6ff').setLngLat([lon, lat]).addTo(map);
      destination = { lat, lon, label, marker };
    }

    // ========== NOVO: AUTOCOMPLETE DE CATEGORIA + HISTÓRICO + TECLAS ==========
    // Mapeamento (rótulo -> consulta)
    const CATEGORY_PRESETS = [
      { label:'Restaurante', query:'restaurant' },
      { label:'Mercado', query:'supermarket' },
      { label:'Farmácia', query:'pharmacy' },
      { label:'Hospital', query:'hospital' },
      { label:'Banco', query:'bank' },
      { label:'Posto', query:'fuel' },
      { label:'Estacionamento', query:'parking' },
      { label:'Shopping', query:'shopping' },
      { label:'Café', query:'cafe' },
      { label:'Bar', query:'bar' },
      { label:'Fast-food', query:'fastfood' },
      { label:'ATM', query:'atm' },
      { label:'Hotel', query:'hotel' },
      { label:'Parque', query:'park' },
      { label:'Pizzaria', query:'pizza' },
      { label:'Padaria', query:'bakery' },
      { label:'Academia', query:'gym' },
      { label:'Escola', query:'school' },
      { label:'Oficina', query:'car_repair' },
      { label:'Aluguel de carros', query:'car_rental' },
      { label:'Lava-rápido', query:'car_wash' },
      { label:'Estação de recarga', query:'charging_station' },
      { label:'Delegacia', query:'police' },
      { label:'Correios', query:'post_office' }
    ];

    let catActiveIndex = -1;
    let catCurrentItems = []; // [{label, query}]

    function buildCatItems(text){
      const q = (text||'').toLowerCase();
      const hist = loadHistory(HIST_CAT).map(s=>{
        // procura preset para manter o "query" quando possível
        const preset = CATEGORY_PRESETS.find(p=> p.label.toLowerCase() === s.toLowerCase());
        return { label:s, query: preset?.query || s };
      });
      const base = CATEGORY_PRESETS.filter(p=> p.label.toLowerCase().includes(q) || p.query.toLowerCase().includes(q));
      // merge histórico + base, sem duplicar por label
      const seen = new Set();
      const merged = [];
      [...hist, ...base].forEach(it=>{
        const k = it.label.toLowerCase();
        if(!seen.has(k)){ seen.add(k); merged.push(it); }
      });
      return merged.slice(0,10);
    }

    function renderCatSuggestions(items){
      catCurrentItems = items; catActiveIndex = -1;
      catSuggestions.innerHTML='';
      items.forEach((it,i)=>{
        const row = document.createElement('div'); row.className='item'; row.textContent = it.label;
        row.onclick = ()=> pickCategory(i);
        catSuggestions.appendChild(row);
      });
      catSuggestions.style.display = items.length ? 'block' : 'none';
    }

    function updateCatActive(){
      [...catSuggestions.children].forEach((el,i)=> el.classList.toggle('active', i===catActiveIndex));
    }

    function pickCategory(index){
      const it = catCurrentItems[index]; if(!it) return;
      categoryInput.value = it.label;
      catSuggestions.style.display='none';
      // histórico removido
      searchPOIs(it.query || it.label); // se não tiver preset, usa texto livre
    }

    categoryInput.addEventListener('focus', ()=>{
      if(!categoryInput.value.trim()){
        renderCatSuggestions(buildCatItems(''));
      }
    });

    categoryInput.addEventListener('input', ()=>{
      const q = categoryInput.value.trim();
      renderCatSuggestions(buildCatItems(q));
    });

    categoryInput.addEventListener('keydown', (ev)=>{
      const count = catCurrentItems.length;
      if(ev.key==='ArrowDown' && count){ ev.preventDefault(); catActiveIndex = (catActiveIndex+1)%count; updateCatActive(); }
      else if(ev.key==='ArrowUp' && count){ ev.preventDefault(); catActiveIndex = (catActiveIndex-1+count)%count; updateCatActive(); }
      else if(ev.key==='Enter'){ ev.preventDefault(); if(catActiveIndex>=0) pickCategory(catActiveIndex); else btnPOIText.click(); }
      else if(ev.key==='Escape'){ catSuggestions.style.display='none'; }
    });

    btnPOIText.addEventListener('click', ()=>{
      const text = categoryInput.value.trim(); if(!text) return;
      // tenta casar com preset para obter "query"
      const preset = CATEGORY_PRESETS.find(p=> p.label.toLowerCase() === text.toLowerCase());
      const q = preset?.query || text;
      // histórico removido
      searchPOIs(q);
    });

    // POIs por categoria (select e chips mantidos)
    document.getElementById('btnPOI').onclick = ()=> searchPOIs(document.getElementById('category').value);
    document.querySelectorAll('.chip').forEach(ch=> ch.onclick = ()=> searchPOIs(ch.dataset.cat));

    // NOVO: RAIO — atualiza label em tempo real
    function updateRadiusLabel(){
      currentRadius = Number(radiusRange.value);
      const km = (currentRadius/1000).toFixed(1).replace('.', ',');
      radiusLabel.textContent = `${km} km`;
    }
    radiusRange.addEventListener('input', updateRadiusLabel);
    updateRadiusLabel();

    async function searchPOIs(category){
      clearPOIs(); poiList.innerHTML='';
      const c = map.getCenter();
      const url = `https://api.tomtom.com/search/2/categorySearch/${encodeURIComponent(category)}.json?key=${apiKey}&lat=${c.lat}&lon=${c.lng}&radius=${currentRadius}&limit=20`;
      const r = await fetch(url); const data = await r.json();
      (data.results||[]).forEach(p=>{
        const name = p.poi?.name || 'Local'; const addr = p.address?.freeformAddress || '';
        const { lat, lon } = p.position;
        const m = stdMarker('#7df9ff').setLngLat([lon, lat]).addTo(map); poiMarkers.push(m);
        const card = document.createElement('div'); card.className='card';
        card.innerHTML = `<strong>${name}</strong><div class=small>${addr}</div>`;
        const row = document.createElement('div'); row.className='controls';
        row.append(btn('+ Waypoint', ()=> addWaypoint(lat, lon, name)), btn('Ir', ()=> map.flyTo({ center:[lon,lat], zoom:15 })));
        card.appendChild(row); poiList.appendChild(card);
      });
      fitBoundsToPOIs();
    }

    function clearPOIs(){ poiMarkers.forEach(m=>m.remove()); poiMarkers.length=0; }
    function fitBoundsToPOIs(){ const b=new tt.LngLatBounds(); poiMarkers.forEach(m=>b.extend(m.getLngLat().toArray())); if(!b.isEmpty()) map.fitBounds(b,{padding:70}); }

    // ===================== ROTAS =====================
    document.getElementById('btnRoute').onclick = calculateRoute;

    async function calculateRoute(){
      if (!origin) return alert('Sem origem.');
      if (!destination) return alert('Defina um destino.');
      const locs = [ `${origin.lat},${origin.lon}`,
                     ...waypoints.map(w=>`${w.lat},${w.lon}`),
                     `${destination.lat},${destination.lon}`].join(':');
      const url = `https://api.tomtom.com/routing/1/calculateRoute/${locs}/json?key=${apiKey}&traffic=true&instructionsType=text`;
      const r = await fetch(url); const data = await r.json();
      if (!data.routes?.length) return alert('Rota não encontrada');

      const route = data.routes[0];
      const line = route.legs.flatMap(l=> l.points).map(p=>[p.longitude,p.latitude]);
      drawRoute(line);

      const s = route.summary; if (s){ statDistance.textContent = fmtKm(s.lengthInMeters); statTime.textContent = fmtTime(s.travelTimeInSeconds); }
      renderSteps(route.guidance?.instructions || []);

      const b = new tt.LngLatBounds(); line.forEach(c=>b.extend(c)); map.fitBounds(b, { padding: 80 });
    }

    function drawRoute(coords){
      clearRoute();
      map.addSource(routeSourceId, { type:'geojson', data:{ type:'Feature', geometry:{ type:'LineString', coordinates: coords }}});
      map.addLayer({ id: routeLayerId, type:'line', source: routeSourceId, paint: { 'line-color': '#7df9ff', 'line-width': 6, 'line-opacity': .95 } });
    }

    function clearRoute(){ if(map?.getLayer(routeLayerId)) map.removeLayer(routeLayerId); if(map?.getSource(routeSourceId)) map.removeSource(routeSourceId); stepsList.innerHTML=''; }

    function renderSteps(steps){
      stepsList.innerHTML = '';
      steps.forEach((st, i)=>{
        const div = document.createElement('div'); div.className='card';
        const lat = st.point?.latitude, lon = st.point?.longitude;
        div.innerHTML = `<strong>${i+1}. ${sanitize(st.message)}</strong><div class="small">${lat?.toFixed?.(5)||''}, ${lon?.toFixed?.(5)||''}</div>`;
        const row = document.createElement('div'); row.className='controls';
        row.append(btn('Ir ao passo', ()=> (lat!=null&&lon!=null) && map.flyTo({ center:[lon, lat], zoom:16 })));
        div.appendChild(row); stepsList.appendChild(div);
      });
    }

    // ===================== AÇÕES =====================
    document.getElementById('btnClear').onclick = ()=>{
      waypoints.forEach(w=> w.marker?.remove()); waypoints.length=0; renderWaypoints();
      if(destination?.marker) destination.marker.remove(); destination=null;
      clearPOIs(); clearRoute();
      statDistance.textContent='—'; statTime.textContent='—'; statWps.textContent='0';
      document.getElementById('destInput').value='';
      document.getElementById('searchInput').value='';
      document.getElementById('suggestions').style.display='none';
      document.getElementById('destSuggestions').style.display='none';
      categoryInput.value=''; catSuggestions.style.display='none';
    };

    document.getElementById('btnLocate').onclick = ()=>{
      if(!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(p=>{
        map.flyTo({ center:[p.coords.longitude, p.coords.latitude], zoom: 14 });
      });
    };

    // ===================== UTILS =====================
    function fmtKm(m){ return (m/1000).toFixed(1).replace('.',',') + ' km'; }
    function fmtTime(s){ const h=Math.floor(s/3600), m=Math.floor((s%3600)/60); return (h? h+' h ': '') + (m? m+' min':''); }
    function sanitize(t){ return String(t).replace(/[<>]/g, ''); }
  
    
// ===================== BUSCA INTELIGENTE (FUZZY SEARCH) =====================
const suggestionsDiv = document.getElementById('suggestions');
let searchTimeout;

function performSearch(query) {
  if (!query) {
    suggestionsDiv.style.display = 'none';
    return;
  }

  // Chame o endpoint Fuzzy Search da API do TomTom
  const url = `https://api.tomtom.com/search/2/search/${encodeURIComponent(query)}.json?key=${apiKey}&limit=5&typeahead=true`;

  fetch(url)
    .then(response => response.json())
    .then(data => {
      displaySuggestions(data.results);
    })
    .catch(error => console.error('Erro na busca:', error));
}

function displaySuggestions(results) {
  suggestionsDiv.innerHTML = '';
  if (results && results.length > 0) {
    results.forEach(result => {
      const suggestionItem = document.createElement('div');
      suggestionItem.classList.add('suggestion-item');
      suggestionItem.textContent = result.address.freeformAddress;
      suggestionItem.addEventListener('click', () => {
        // Use a sugestão clicada para adicionar um waypoint
        const { lat, lon } = result.position;
        addWaypoint(lat, lon, result.address.freeformAddress);
        suggestionsDiv.style.display = 'none';
        searchInput.value = result.address.freeformAddress;
      });
      suggestionsDiv.appendChild(suggestionItem);
    });
    suggestionsDiv.style.display = 'block';
  } else {
    suggestionsDiv.style.display = 'none';
  }
}

// Ouve as digitações do usuário no campo de busca
searchInput.addEventListener('input', (e) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    performSearch(e.target.value);
  }, 300); // Atraso para evitar muitas chamadas à API
});

// Oculta as sugestões se clicar fora
document.addEventListener('click', (e) => {
  if (!suggestionsDiv.contains(e.target) && e.target !== searchInput) {
    suggestionsDiv.style.display = 'none';
  }
});

// ===================== AUTOCOMPLETAR (INPUTS DE DESTINO E WAYPOINT) =====================

// Função genérica para realizar a busca e exibir as sugestões
function setupAutocomplete(inputElementId, suggestionsElementId, historyKey, callback) {
    const input = document.getElementById(inputElementId);
    const suggestionsDiv = document.getElementById(suggestionsElementId);
    let searchTimeout;

    // Função para buscar e exibir sugestões
    function performSearch(query) {
        if (!query) {
            suggestionsDiv.style.display = 'none';
            return;
        }

        // URL da API de busca inteligente (Fuzzy Search) do TomTom
        const url = `https://api.tomtom.com/search/2/search/${encodeURIComponent(query)}.json?key=${apiKey}&limit=5&typeahead=true&language=pt-BR`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                displaySuggestions(data.results);
            })
            .catch(error => console.error(`Erro na busca para ${inputElementId}:`, error));
    }

    // Função para exibir as sugestões recebidas da API
    function displaySuggestions(results) {
        suggestionsDiv.innerHTML = '';
        if (results && results.length > 0) {
            results.forEach(result => {
                const suggestionItem = document.createElement('div');
                suggestionItem.classList.add('suggestion-item');
                suggestionItem.textContent = result.address.freeformAddress;
                suggestionItem.addEventListener('click', () => {
                    callback(result);
                    suggestionsDiv.style.display = 'none';
                    input.value = result.address.freeformAddress;
                    if (historyKey) {
                        saveHistory(historyKey, input.value);
                    }
                });
                suggestionsDiv.appendChild(suggestionItem);
            });
            suggestionsDiv.style.display = 'block';
        } else {
            suggestionsDiv.style.display = 'none';
        }
    }

    // Adiciona o ouvinte para a digitação do usuário
    input.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(e.target.value);
        }, 300);
    });

    // Oculta as sugestões se o usuário clicar fora da área
    document.addEventListener('click', (e) => {
        if (!suggestionsDiv.contains(e.target) && e.target !== input) {
            suggestionsDiv.style.display = 'none';
        }
    });

    // Exibe o histórico de busca ao focar no campo de entrada
    input.addEventListener('focus', () => {
        if (input.value === '' && historyKey) {
            const history = loadHistory(historyKey);
            if (history.length > 0) {
                suggestionsDiv.innerHTML = '';
                history.forEach(item => {
                    const historyItem = document.createElement('div');
                    historyItem.classList.add('suggestion-item', 'history-item');
                    historyItem.innerHTML = `<span class="icon"></span> ${item}`;
                    historyItem.addEventListener('click', () => {
                        input.value = item;
                        performSearch(item); // Realiza a busca com o item do histórico
                    });
                    suggestionsDiv.appendChild(historyItem);
                });
                suggestionsDiv.style.display = 'block';
            }
        }
    });
}

// ===================== CHAMADAS PARA OS CAMPOS DE AUTOCOMPLETAR =====================

// Configura o autocompletar para o campo de pesquisa de Waypoints
setupAutocomplete('searchInput', 'suggestions', null, (result) => {
    const { lat, lon } = result.position;
    addWaypoint(lat, lon, result.address.freeformAddress);
});

// Configura o autocompletar para o campo de pesquisa de Destino
setupAutocomplete('destInput', 'destSuggestions', HIST_DEST, (result) => {
    setDestination(result.position.lat, result.position.lon, result.address.freeformAddress);
});

// No botão de destino, adicione um ouvinte de evento para buscar
btnSetDest.addEventListener('click', () => {
    const query = destInput.value;
    if (query) {
        // Chame a API de busca inteligente diretamente para o termo digitado
        const url = `https://api.tomtom.com/search/2/search/${encodeURIComponent(query)}.json?key=${apiKey}&limit=1&language=pt-BR`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const result = data.results[0];
                if (result) {
                    setDestination(result.position.lat, result.position.lon, result.address.freeformAddress);
                    saveHistory(HIST_DEST, result.address.freeformAddress);
                } else {
                    console.error('Nenhum resultado encontrado para o destino.');
                }
            })
            .catch(error => console.error('Erro na busca por destino:', error));
    }
});

  </script>
</body>
</html>