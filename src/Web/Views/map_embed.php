<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'CNYmesh Map Embed') ?></title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    html, body {
      width: 100%;
      height: 100%;
      margin: 0;
      overflow: hidden;
      background: #1a1a1a;
    }

    #map {
      width: 100%;
      height: 100vh;
    }

    .leaflet-control-container .legend {
      max-width: 220px;
    }

    .county-label {
      color: #e5e7eb;
      font-size: 11px;
      font-weight: 600;
      text-shadow: 0 0 3px #111827, 0 0 6px #111827;
      white-space: nowrap;
      pointer-events: none;
    }
  </style>
</head>
<body>
  <div id="map"></div>

  <script>
  window.addEventListener('DOMContentLoaded', async () => {
    const map = L.map('map', {
      zoomControl: true,
      preferCanvas: true
    }).setView([43.5, -76.0], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const ourNodesList = <?= json_encode(array_values($ourNodesList), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const positionRows = <?= json_encode($positions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const mapReportRows = <?= json_encode($mapRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const countyOverlayPaneName = 'countyOverlayPane';
    const countyLabelPaneName = 'countyLabelPane';

    map.createPane(countyOverlayPaneName);
    map.getPane(countyOverlayPaneName).style.zIndex = '390';

    map.createPane(countyLabelPaneName);
    map.getPane(countyLabelPaneName).style.zIndex = '440';

    const countyLabelsLayer = L.layerGroup().addTo(map);
    const countyLabelMinZoom = 9;

    const ourNodeIcon = L.icon({
      iconUrl: 'data:image/svg+xml;base64,' + btoa(`
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" width="30" height="30">
          <defs>
            <filter id="glow">
              <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
              <feMerge>
                <feMergeNode in="coloredBlur"/>
                <feMergeNode in="SourceGraphic"/>
              </feMerge>
            </filter>
          </defs>
          <path fill="#FFD700" stroke="#FFA500" stroke-width="1" filter="url(#glow)"
                d="M15 2l3.5 10.5 11 0.5-9 7 3.5 10.5-9-7-9 7 3.5-10.5-9-7 11-0.5z"/>
        </svg>`),
      iconSize: [30, 30],
      iconAnchor: [15, 15],
      popupAnchor: [0, -15]
    });

    const knownNodeIcon = L.icon({
      iconUrl: 'data:image/svg+xml;base64,' + btoa(`
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 41" width="25" height="41">
          <path fill="#10B981" stroke="#065F46" stroke-width="2" d="M12.5 0C5.6 0 0 5.6 0 12.5c0 8.2 12.5 28.5 12.5 28.5s12.5-20.3 12.5-28.5C25 5.6 19.4 0 12.5 0z"/>
          <circle fill="white" cx="12.5" cy="12.5" r="6"/>
        </svg>`),
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34]
    });

    const unknownNodeIcon = L.icon({
      iconUrl: 'data:image/svg+xml;base64,' + btoa(`
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 41" width="25" height="41">
          <path fill="#EF4444" stroke="#991B1B" stroke-width="2" d="M12.5 0C5.6 0 0 5.6 0 12.5c0 8.2 12.5 28.5 12.5 28.5s12.5-20.3 12.5-28.5C25 5.6 19.4 0 12.5 0z"/>
          <circle fill="white" cx="12.5" cy="12.5" r="6"/>
          <text x="12.5" y="17" text-anchor="middle" font-family="Arial" font-size="10" fill="#991B1B">?</text>
        </svg>`),
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34]
    });

    const mapReportIcon = L.icon({
      iconUrl: 'data:image/svg+xml;base64,' + btoa(`
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
          <path fill="#3B82F6" stroke="#1E3A8A" stroke-width="1.5" d="M12 1l4.5 6.5L23 12l-6.5 4.5L12 23l-4.5-6.5L1 12l6.5-4.5z"/>
          <circle fill="white" cx="12" cy="12" r="3.2"/>
        </svg>`),
      iconSize: [24, 24],
      iconAnchor: [12, 12],
      popupAnchor: [0, -12]
    });

    function isOurNode(nodeNum) {
      return ourNodesList.includes(nodeNum.toString()) ||
             ourNodesList.includes(nodeNum) ||
             ourNodesList.includes('!' + nodeNum.toString(16).padStart(8, '0'));
    }

    function normalizeCountyName(name) {
      return String(name || '').trim().toLowerCase();
    }

    function updateCountyLabelVisibility() {
      if (map.getZoom() >= countyLabelMinZoom) {
        if (!map.hasLayer(countyLabelsLayer)) {
          countyLabelsLayer.addTo(map);
        }
      } else if (map.hasLayer(countyLabelsLayer)) {
        map.removeLayer(countyLabelsLayer);
      }
    }

    const bounds = [];
    let focusCountyBounds = null;

    try {
      const [countiesResponse, focusCountiesResponse] = await Promise.all([
        fetch('/assets/ny-counties.geojson'),
        fetch('/assets/focus-counties.json')
      ]);

      if (countiesResponse.ok && focusCountiesResponse.ok) {
        const countiesGeoJson = await countiesResponse.json();
        const focusCounties = await focusCountiesResponse.json();
        const focusCountySet = new Set((Array.isArray(focusCounties) ? focusCounties : []).map(normalizeCountyName));

        const countyLayer = L.geoJSON(countiesGeoJson, {
          pane: countyOverlayPaneName,
          style: function(feature) {
            const countyName = normalizeCountyName(feature?.properties?.NAME);
            const isFocusCounty = focusCountySet.has(countyName);
            return {
              color: isFocusCounty ? '#F59E0B' : '#64748B',
              weight: isFocusCounty ? 2.2 : 1.2,
              fillColor: isFocusCounty ? '#F59E0B' : '#94A3B8',
              fillOpacity: isFocusCounty ? 0.18 : 0.05
            };
          },
          onEachFeature: function(feature, layer) {
            const countyName = feature?.properties?.NAME || 'Unknown County';
            const isFocusCounty = focusCountySet.has(normalizeCountyName(countyName));

            layer.bindPopup(`<b>${countyName} County</b>${isFocusCounty ? '<br/><span style="color:#F59E0B;font-weight:600;">Focus County</span>' : ''}`);

            if (typeof layer.getBounds === 'function') {
              const center = layer.getBounds().getCenter();
              L.marker(center, {
                pane: countyLabelPaneName,
                interactive: false,
                icon: L.divIcon({
                  className: 'county-label',
                  html: countyName,
                  iconSize: [0, 0]
                })
              }).addTo(countyLabelsLayer);
            }
          }
        }).addTo(map);

        countyLayer.eachLayer((layer) => {
          const featureCountyName = normalizeCountyName(layer.feature?.properties?.NAME);
          if (focusCountySet.has(featureCountyName) && typeof layer.getBounds === 'function') {
            const layerBounds = layer.getBounds();
            if (layerBounds && layerBounds.isValid()) {
              if (focusCountyBounds === null) {
                focusCountyBounds = layerBounds;
              } else {
                focusCountyBounds.extend(layerBounds);
              }
            }
          }
        });
      }
    } catch (error) {
      console.error('Unable to load county overlays:', error);
    }

    positionRows.forEach((position) => {
      const lat = Number(position.lat);
      const lon = Number(position.lon);
      if (Number.isNaN(lat) || Number.isNaN(lon)) {
        return;
      }

      let nodeType = '';
      let markerIcon = null;

      if (isOurNode(position.node_num)) {
        nodeType = 'Our Node';
        markerIcon = ourNodeIcon;
      } else if (Number(position.is_known_node) === 1) {
        nodeType = 'Known Node';
        markerIcon = knownNodeIcon;
      } else {
        nodeType = 'Unknown';
        markerIcon = unknownNodeIcon;
      }

      let label = position.long_name || position.short_name || `!${Number(position.node_num).toString(16).padStart(8, '0')}`;
      let popup = `<b>${label}</b> <span style="color: ${isOurNode(position.node_num) ? '#2563EB' : (Number(position.is_known_node) === 1 ? '#10B981' : '#EF4444')}; font-weight: bold;">(${nodeType})</span><br/>`;
      popup += `<b>Position:</b> ${lat.toFixed(5)}, ${lon.toFixed(5)}<br/>`;
      if (position.altitude) popup += `<b>Altitude:</b> ${position.altitude}m<br/>`;
      if (position.rx_rssi) popup += `<b>RSSI:</b> ${Number(position.rx_rssi).toFixed(1)}dBm<br/>`;
      if (position.rx_snr) popup += `<b>SNR:</b> ${Number(position.rx_snr).toFixed(1)}dB<br/>`;
      popup += `<b>Time:</b> ${new Date(Number(position.time) * 1000).toLocaleString()}`;

      L.marker([lat, lon], { icon: markerIcon }).addTo(map).bindPopup(popup);
      bounds.push([lat, lon]);
    });

    const latestMapReportPerNode = new Map();
    mapReportRows.forEach((report) => {
      if (!latestMapReportPerNode.has(report.node_num)) {
        latestMapReportPerNode.set(report.node_num, report);
      }
    });

    latestMapReportPerNode.forEach((report) => {
      const lat = report.lat !== null ? Number(report.lat) : null;
      const lon = report.lon !== null ? Number(report.lon) : null;
      if (lat === null || lon === null || Number.isNaN(lat) || Number.isNaN(lon)) {
        return;
      }

      const label = report.long_name || report.short_name || `!${Number(report.node_num).toString(16).padStart(8, '0')}`;
      const reportTime = Number(report.saved_at || 0);
      const positionTime = Number(report.position_time || 0);

      let popup = `<b>${label}</b> <span style="color: #3B82F6; font-weight: bold;">(Map Report)</span><br/>`;
      popup += `<b>Position:</b> ${lat.toFixed(5)}, ${lon.toFixed(5)}<br/>`;
      if (report.channel_id) popup += `<b>Channel:</b> ${report.channel_id}<br/>`;
      popup += `<b>Payload:</b> ${Number(report.bytes || 0)} bytes<br/>`;
      if (reportTime > 0) popup += `<b>Report Time:</b> ${new Date(reportTime * 1000).toLocaleString()}<br/>`;
      if (positionTime > 0) popup += `<b>Position Time:</b> ${new Date(positionTime * 1000).toLocaleString()}`;

      L.marker([lat, lon], { icon: mapReportIcon }).addTo(map).bindPopup(popup);
      bounds.push([lat, lon]);
    });

    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function() {
      const div = L.DomUtil.create('div', 'legend');
      div.innerHTML = `
        <h4>Map Legend</h4>
        <div style="margin: 5px 0;"><span style="color: #FFD700; font-size: 16px;">★</span> Our Nodes</div>
        <div style="margin: 5px 0;"><span style="color: #10B981; font-size: 16px;">●</span> Other Known Nodes</div>
        <div style="margin: 5px 0;"><span style="color: #EF4444; font-size: 16px;">●</span> Unknown Nodes</div>
        <div style="margin: 5px 0;"><span style="color: #3B82F6; font-size: 16px;">◆</span> Map Reports</div>
      `;
      return div;
    };
    legend.addTo(map);

    map.on('zoomend', updateCountyLabelVisibility);
    updateCountyLabelVisibility();

    if (focusCountyBounds && focusCountyBounds.isValid()) {
      map.fitBounds(focusCountyBounds, { padding: [32, 32], maxZoom: 11 });
    } else if (bounds.length > 0) {
      const combinedBounds = bounds[0] instanceof L.LatLngBounds
        ? bounds[0]
        : L.latLngBounds(bounds[0]);

      for (let i = 1; i < bounds.length; i += 1) {
        if (bounds[i] instanceof L.LatLngBounds) {
          combinedBounds.extend(bounds[i]);
        } else {
          combinedBounds.extend(bounds[i]);
        }
      }

      map.fitBounds(combinedBounds, { padding: [24, 24], maxZoom: 15 });
    }
  });
  </script>
</body>
</html>