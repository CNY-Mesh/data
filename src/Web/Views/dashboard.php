<section class="stats">
  <div class="card"><div class="metric"><?=htmlspecialchars((string)$nodes)?></div><div class="label">Nodes</div></div>
  <div class="card"><div class="metric"><?=htmlspecialchars((string)$pos)?></div><div class="label">Positions</div></div>
  <div class="card"><div class="metric"><?=htmlspecialchars((string)$nei)?></div><div class="label">Neighbors</div></div>
  <div class="card"><div class="metric"><?=htmlspecialchars((string)$tel)?></div><div class="label">Telemetry</div></div>
  <div class="card"><div class="metric"><?=htmlspecialchars((string)$trc)?></div><div class="label">Traceroutes</div></div>
  <div class="card"><div class="metric"><?=htmlspecialchars((string)$map)?></div><div class="label">Map Reports</div></div>
</section>

<section class="map-wrap">
  <h2>Last Known Positions</h2>
  <div style="margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 15px;">
                <div class="form-check">
              <input class="form-check-input" type="checkbox" id="showAllKnownNodes" checked>
              <label class="form-check-label" for="showAllKnownNodes">
                Show all known nodes
              </label>
            </div>
    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
      <input type="checkbox" id="showAllRecentPositions" style="cursor: pointer;">
      <span>Show all recent position data</span>
    </label>
    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
      <input type="checkbox" id="showMapReports" checked style="cursor: pointer;">
      <span>Show map report nodes</span>
    </label>
  </div>
  <div id="map" style="height:400px;"></div>
</section>

<style>
  .unknown-node {
    display: none;
  }
</style>

<section>
  <div class="dashboard-reports-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 16px; align-items: start;">
    <div>
      <h2>Recent Positions</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Node</th><th>Name</th><th>Lat</th><th>Lon</th><th>Alt</th><th>RSSI</th><th>SNR</th><th>Time</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr style="<?= $r['is_known_node'] ? 'background-color: #F0FDF4;' : 'background-color: #FEF2F2;' ?>" class="position-row <?= $r['is_known_node'] ? 'known-node' : 'unknown-node' ?>">
              <td><a href="/?r=node&id=<?= (int)$r['node_num'] ?>"><?= sprintf("!%08x", (int)$r['node_num']) ?></a></td>
              <td>
                <?= htmlspecialchars(($r['long_name'] ?: $r['short_name'] ?: 'Unknown')) ?>
                <?php if ($r['is_known_node']): ?>
                  <span style="color: #10B981; font-size: 12px; font-weight: bold;">●</span>
                <?php else: ?>
                  <span style="color: #EF4444; font-size: 12px; font-weight: bold;">?</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars(number_format((float)$r['lat'], 5)) ?></td>
              <td><?= htmlspecialchars(number_format((float)$r['lon'], 5)) ?></td>
              <td><?= $r['altitude'] ? htmlspecialchars(number_format((int)$r['altitude'])) . 'm' : '-' ?></td>
              <td><?= $r['rx_rssi'] ? htmlspecialchars(number_format((float)$r['rx_rssi'], 1)) . 'dBm' : '-' ?></td>
              <td><?= $r['rx_snr'] ? htmlspecialchars(number_format((float)$r['rx_snr'], 1)) . 'dB' : '-' ?></td>
              <td><?= date('Y-m-d H:i:s', (int)$r['time']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div>
      <h2>Recent Map Reports</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Node</th><th>Name</th><th>Channel</th><th>Bytes</th><th>Saved</th></tr></thead>
          <tbody>
          <?php foreach ($mapRows as $r): ?>
            <tr style="<?= $r['is_known_node'] ? 'background-color: #F0FDF4;' : 'background-color: #FEF2F2;' ?>" class="map-report-row">
              <td><?= (int)$r['id'] ?></td>
              <td><a href="/?r=node&id=<?= (int)$r['node_num'] ?>"><?= sprintf("!%08x", (int)$r['node_num']) ?></a></td>
              <td>
                <?= htmlspecialchars(($r['long_name'] ?: $r['short_name'] ?: 'Unknown')) ?>
                <?php if ($r['is_known_node']): ?>
                  <span style="color: #10B981; font-size: 12px; font-weight: bold;">●</span>
                <?php else: ?>
                  <span style="color: #EF4444; font-size: 12px; font-weight: bold;">?</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)($r['channel_id'] ?? '')) ?></td>
              <td><?= (int)($r['bytes'] ?? 0) ?></td>
              <td><?= date('Y-m-d H:i:s', (int)$r['saved_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', async () => {
  const map = L.map('map').setView([43.5, -76.0], 8);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
  
  // Get our nodes list from PHP
  const ourNodesList = <?= json_encode($ourNodesList) ?>;
  
  // Create separate layer groups
  const ourNodesLayer = L.layerGroup().addTo(map); // Default: our nodes visible
  const otherKnownNodesLayer = L.layerGroup().addTo(map); // Default: known nodes visible
  const unknownNodesLayer = L.layerGroup(); // Hidden initially
  const mapReportsLayer = L.layerGroup().addTo(map); // Default: map reports visible

  // Get dashboard map reports from PHP
  const mapReportRows = <?= json_encode($mapRows) ?>;
  
  // Define custom icons
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
  
  // Fetch and display position data
  const res = await fetch('/?r=api&a=positions');
  const data = await res.json();
  
  // Function to check if node is in our nodes list
  function isOurNode(nodeNum) {
    return ourNodesList.includes(nodeNum.toString()) || 
           ourNodesList.includes(nodeNum) ||
           ourNodesList.includes('!' + nodeNum.toString(16).padStart(8, '0'));
  }
  
  data.forEach(p => {
    if (p.lat && p.lon) {
      let label = (p.long_name || p.short_name || `!${p.node_num.toString(16).padStart(8, '0')}`);
      let nodeType = '';
      let targetLayer = null;
      let icon = null;
      
      if (isOurNode(p.node_num)) {
        nodeType = 'Our Node';
        targetLayer = ourNodesLayer;
        icon = ourNodeIcon;
      } else if (p.is_known_node) {
        nodeType = 'Known Node';
        targetLayer = otherKnownNodesLayer;
        icon = knownNodeIcon;
      } else {
        nodeType = 'Unknown';
        targetLayer = unknownNodesLayer;
        icon = unknownNodeIcon;
      }
      
      let popup = `<b>${label}</b> <span style="color: ${isOurNode(p.node_num) ? '#2563EB' : (p.is_known_node ? '#10B981' : '#EF4444')}; font-weight: bold;">(${nodeType})</span><br/>`;
      popup += `<b>Position:</b> ${p.lat.toFixed(5)}, ${p.lon.toFixed(5)}<br/>`;
      if (p.altitude) popup += `<b>Altitude:</b> ${p.altitude}m<br/>`;
      if (p.rx_rssi) popup += `<b>RSSI:</b> ${p.rx_rssi.toFixed(1)}dBm<br/>`;
      if (p.rx_snr) popup += `<b>SNR:</b> ${p.rx_snr.toFixed(1)}dB<br/>`;
      popup += `<b>Time:</b> ${new Date(p.time*1000).toLocaleString()}`;
      
      const m = L.marker([p.lat, p.lon], { icon: icon }).addTo(targetLayer);
      m.bindPopup(popup);
    }
  });

  // Add one map report marker per node using that node's latest known position
  const latestMapReportPerNode = new Map();
  mapReportRows.forEach(report => {
    if (!latestMapReportPerNode.has(report.node_num)) {
      latestMapReportPerNode.set(report.node_num, report);
    }
  });

  latestMapReportPerNode.forEach(report => {
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

    L.marker([lat, lon], { icon: mapReportIcon }).addTo(mapReportsLayer).bindPopup(popup);
  });
  
  // Handle checkbox changes
  const showAllKnownNodesCheckbox = document.getElementById('showAllKnownNodes');
  const showAllRecentPositionsCheckbox = document.getElementById('showAllRecentPositions');
  const showMapReportsCheckbox = document.getElementById('showMapReports');
  
  showAllKnownNodesCheckbox.addEventListener('change', function() {
    if (this.checked) {
      otherKnownNodesLayer.addTo(map);
    } else {
      map.removeLayer(otherKnownNodesLayer);
    }
    updateLegend();
  });
  
  showAllRecentPositionsCheckbox.addEventListener('change', function() {
    if (this.checked) {
      unknownNodesLayer.addTo(map);
    } else {
      map.removeLayer(unknownNodesLayer);
    }
    updateLegend();
  });

  showMapReportsCheckbox.addEventListener('change', function() {
    if (this.checked) {
      mapReportsLayer.addTo(map);
    } else {
      map.removeLayer(mapReportsLayer);
    }
    updateLegend();
  });
  
  // Function to update legend based on current visibility
  function updateLegend() {
    if (window.legendControl) {
      map.removeControl(window.legendControl);
    }
    
    const legend = L.control({position: 'bottomright'});
    legend.onAdd = function() {
      const div = L.DomUtil.create('div', 'legend');
      
      let legendContent = '<h4>Node Types</h4>';
      legendContent += '<div style="margin: 5px 0;"><span style="color: #FFD700; font-size: 16px;">★</span> Our Nodes</div>';
      
      if (showAllKnownNodesCheckbox.checked) {
        legendContent += '<div style="margin: 5px 0;"><span style="color: #10B981; font-size: 16px;">●</span> Other Known Nodes</div>';
      }
      
      if (showAllRecentPositionsCheckbox.checked) {
        legendContent += '<div style="margin: 5px 0;"><span style="color: #EF4444; font-size: 16px;">●</span> Unknown Nodes</div>';
      }

      if (showMapReportsCheckbox.checked) {
        legendContent += '<div style="margin: 5px 0;"><span style="color: #3B82F6; font-size: 16px;">◆</span> Map Reports</div>';
      }
      
      div.innerHTML = legendContent;
      return div;
    };
    legend.addTo(map);
    window.legendControl = legend;
  }
  
  // Initialize legend
  updateLegend();
});
</script>
