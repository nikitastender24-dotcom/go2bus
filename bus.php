<?php
// =========================================================================
// БЭКЕНД (СЕРВЕРНЫЙ ПРОКСИ) - обходит CORS, забирая данные с go2.bus-pvl.kz
// =========================================================================
date_default_timezone_set('Asia/Almaty');

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $api = $_GET['api'];
    $baseUrl = 'http://go2.bus-pvl.kz/';
    $url = '';

    if ($api === 'routes') {
        $url = $baseUrl . 'inforoutes?vt=b&srv=pav';
    } elseif ($api === 'details') {
        $route = isset($_GET['route']) ? urlencode($_GET['route']) : '';
        $date = isset($_GET['date']) ? urlencode($_GET['date']) : date('Y-m-d');
        $url = $baseUrl . "inforoutedetails?route={$route}&vt=b&date={$date}&srv=pav&lang=ru&timezone=Asia/Almaty";
    }

    if ($url) {
        // Используем cURL для надежности
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Маскируемся под обычный браузер, чтобы не забанили
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && $response) {
            echo $response;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch data from API', 'code' => $httpCode]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Павлодар · автобусы</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #fafafa; color: #1a1a1a; line-height: 1.5; padding: 1.5rem 1rem 3rem; }
  .container { max-width: 720px; margin: 0 auto; }
  .selector { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 0.9rem 1.2rem; margin-bottom: 1.2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
  .selector label { font-size: 0.9rem; font-weight: 500; color: #444; }
  .selector select { appearance: none; -webkit-appearance: none; background: #f5f5f5 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E") no-repeat right 1rem center; background-size: 12px; border: 1px solid #ddd; border-radius: 30px; padding: 0.55rem 2.2rem 0.55rem 1.2rem; font-size: 0.95rem; font-weight: 500; cursor: pointer; outline: none; }
  .route-header { margin-bottom: 0.9rem; font-size: 0.9rem; color: #666; display: flex; gap: 1rem; align-items: baseline; flex-wrap: wrap; }
  .route-header .number { font-weight: 600; color: #1a1a1a; font-size: 1.1rem; }
  .dir-toggle { background: none; border: 1px solid #ddd; border-radius: 6px; padding: 0.2rem 0.7rem; font-size: 0.9rem; cursor: pointer; color: #444; margin-left: auto; }
  #map { height: 340px; border-radius: 12px; border: 1px solid #e0e0e0; margin-bottom: 1.2rem; z-index: 0; background: #e5e3df; }
  .stops { list-style: none; background: #fff; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; }
  .stops li { display: flex; align-items: center; gap: 0.9rem; padding: 0.8rem 1.2rem; border-bottom: 1px solid #f0f0f0; font-size: 0.92rem; }
  .stops li:last-child { border-bottom: none; }
  .stops li.terminal .stop-name { font-weight: 500; }
  .stop-icon { flex-shrink: 0; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; color: #888; }
  .stop-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
  .stop-name { flex: 1; }
  .status { padding: 1.6rem 1.2rem; text-align: center; color: #999; font-size: 0.88rem; }
  .footnote { margin-top: 1.6rem; font-size: 0.7rem; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 1rem; line-height: 1.7; }
</style>
</head>
<body>
<div class="container">
  <div class="selector">
    <label for="busSelect">Автобус</label>
    <select id="busSelect"><option>Загрузка…</option></select>
  </div>
  <div class="route-header" id="routeHeader" style="display:none">
    <span class="number" id="routeNumber"></span>
    <span id="routeMeta"></span>
    <button class="dir-toggle" id="toggleDir" style="display:none" title="Сменить направление">⇄</button>
  </div>
  <div id="map"></div>
  <ul class="stops" id="stopList"></ul>
  <div class="status" id="status">Загрузка маршрутов…</div>
  <div class="footnote">
    источник данных · <b>go2.bus-pvl.kz</b> · карта · CARTO / OSM<br>
    серверный прокси (без CORS)
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  'use strict';

  const $ = id => document.getElementById(id);
  const select      = $('busSelect');
  const routeHeader = $('routeHeader');
  const routeNumber = $('routeNumber');
  const routeMeta   = $('routeMeta');
  const toggleDir   = $('toggleDir');
  const stopList    = $('stopList');
  const status      = $('status');

  // Карта
  const map = L.map('map', { zoomControl: true }).setView([52.287, 76.967], 12);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap © CARTO'
  }).addTo(map);

  let routeLayer = null;
  let routes     = [];
  let directions = [];
  let dirIndex   = 0;
  let reqId      = 0;

  function clearRouteLayer() {
    if (routeLayer) {
      map.removeLayer(routeLayer);
      routeLayer = null;
    }
  }

  function cleanName(name) {
    return String(name).replace(/^0+/, '') || '0';
  }

  const STOP_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>';

  // ==========================================
  // ЗАГРУЗКА СПИСКА МАРШРУТОВ (Через наш PHP)
  // ==========================================
  async function loadRoutes() {
    status.textContent = 'Загрузка маршрутов…';
    try {
      // Обращаемся к своему же серверу!
      const res = await fetch('?api=routes');
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      
      routes = Array.isArray(data) ? data.filter(r => r && r.vt === 'b' && r.name) : [];
      routes.sort((a, b) => {
        const na = parseInt(cleanName(a.name), 10);
        const nb = parseInt(cleanName(b.name), 10);
        if (!isNaN(na) && !isNaN(nb) && na !== nb) return na - nb;
        return cleanName(a.name).localeCompare(cleanName(b.name));
      });

      select.innerHTML = '';
      if (!routes.length) {
        select.innerHTML = '<option>Нет маршрутов</option>';
        status.textContent = 'Маршруты не найдены.';
        return;
      }

      routes.forEach((r, i) => {
        const opt = document.createElement('option');
        opt.value = String(i);
        opt.textContent = cleanName(r.name);
        select.appendChild(opt);
      });

      status.textContent = '';
      await showRoute(0);
    } catch (e) {
      console.error(e);
      select.innerHTML = '<option>Ошибка</option>';
      status.textContent = 'Не удалось загрузить список маршрутов.';
    }
  }

  // ==========================================
  // ЗАГРУЗКА ДЕТАЛЕЙ МАРШРУТА (Через наш PHP)
  // ==========================================
  async function showRoute(index) {
    const route = routes[index];
    if (!route) return;

    const myReq = ++reqId;
    routeHeader.style.display = '';
    routeNumber.textContent = cleanName(route.name);
    routeMeta.textContent = 'загрузка…';
    toggleDir.style.display = 'none';
    stopList.innerHTML = '';
    status.textContent = '';
    clearRouteLayer();
    map.setView([52.287, 76.967], 12);

    try {
      const today = new Date().toISOString().split('T')[0];
      // Формируем запрос к нашему же серверу
      const url = `?api=details&route=${encodeURIComponent(route.name)}&date=${today}`;
      const res = await fetch(url);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();

      if (myReq !== reqId) return; // если пользователь уже переключил маршрут

      directions = (data && Array.isArray(data.directions)) ? data.directions : [];
      dirIndex = 0;

      if (!directions.length) {
        routeMeta.textContent = '';
        status.textContent = 'Нет данных об остановках.';
        return;
      }

      renderDirection();
    } catch (e) {
      if (myReq !== reqId) return;
      console.error(e);
      routeMeta.textContent = '';
      status.textContent = 'Не удалось загрузить данные маршрута.';
    }
  }

  // ==========================================
  // ОТРИСОВКА
  // ==========================================
  function renderDirection() {
    const dir = directions[dirIndex];
    if (!dir) return;

    routeMeta.textContent = `${dir.begName || '—'} → ${dir.endName || '—'}`;
    toggleDir.style.display = directions.length > 1 ? 'inline-block' : 'none';

    const stops   = Array.isArray(dir.stops) ? dir.stops : [];
    const lastIdx = stops.length - 1;
    stopList.innerHTML = '';

    stops.forEach((stop, idx) => {
      const zone = stop && stop.zones && stop.zones[0];
      if (!zone) return;

      const li = document.createElement('li');
      if (idx === 0 || idx === lastIdx) li.className = 'terminal';

      const icon = document.createElement('span');
      icon.className = 'stop-icon';
      icon.innerHTML = STOP_ICON;

      const name = document.createElement('span');
      name.className = 'stop-name';
      name.textContent = zone.name;

      li.appendChild(icon);
      li.appendChild(name);
      stopList.appendChild(li);
    });

    if (!stops.length) {
      status.textContent = 'Остановки не найдены.';
      return;
    }

    clearRouteLayer();

    const latlngs = [];
    const markers = [];

    stops.forEach((stop) => {
      const zone = stop && stop.zones && stop.zones[0];
      if (!zone || !Array.isArray(zone.location) || zone.location.length < 2) return;

      const lat = Number(zone.location[0]);
      const lon = Number(zone.location[1]);
      if (!isFinite(lat) || !isFinite(lon)) return;

      const ll = [lat, lon];
      latlngs.push(ll);

      const marker = L.circleMarker(ll, {
        radius: 5,
        color: '#0c6fde',
        fillColor: '#0c6fde',
        fillOpacity: 0.85,
        weight: 1
      }).bindPopup(zone.name);

      markers.push(marker);
    });

    if (!latlngs.length) {
      status.textContent = 'Не удалось получить координаты остановок.';
      return;
    }

    const polyline = L.polyline(latlngs, {
      color: '#0c6fde',
      weight: 4,
      opacity: 0.85,
      lineJoin: 'round'
    });

    routeLayer = L.layerGroup([polyline, ...markers]).addTo(map);
    map.fitBounds(polyline.getBounds(), { padding: [30, 30] });
  }

  // Слушатели
  select.addEventListener('change', () => {
    const idx = parseInt(select.value, 10);
    if (!isNaN(idx)) showRoute(idx);
  });

  toggleDir.addEventListener('click', () => {
    if (directions.length < 2) return;
    dirIndex = (dirIndex + 1) % directions.length;
    renderDirection();
  });

  // Запуск
  loadRoutes();
})();
</script>
</body>
</html>
