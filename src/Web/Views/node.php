<h2>
  <?php if (!empty($node['long_name'])): ?>
    <?= htmlspecialchars($node['long_name']) ?>
  <?php else: ?>
    Node <?= htmlspecialchars((string)($node['node_num'] ?? '')) ?>
  <?php endif; ?>
</h2>
<section class="grid2">
  <div>
    <h3>Identity</h3>
    <ul>
      <li>Short Name: <b><?= htmlspecialchars($node['short_name'] ?? 'Unknown') ?></b></li>
      <li>Node Number: <b><?= htmlspecialchars((string)($node['node_num'] ?? '')) ?></b></li>
      <li>Node ID: <b><?= htmlspecialchars($node['node_id'] ?? 'Unknown') ?></b></li>
      <li>Hardware: <b><?= htmlspecialchars((string)($node['hardware'] ?? 'Unknown')) ?></b></li>
      <li>Last Seen: <b><?= $node['last_seen'] ? date('Y-m-d H:i:s', (int)$node['last_seen']) : 'Never' ?></b></li>
    </ul>
  </div>
  <div>
    <h3>Last Position</h3>
    <?php if ($pos && $pos['lat'] && $pos['lon']): ?>
      <div id="map" style="height:300px;"></div>
      <script>
      window.addEventListener('DOMContentLoaded', () => {
        const map = L.map('map').setView([<?= (float)$pos['lat'] ?>, <?= (float)$pos['lon'] ?>], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}).addTo(map);
        L.marker([<?= (float)$pos['lat'] ?>, <?= (float)$pos['lon'] ?>]).addTo(map);
      });
      </script>
      <p><b><?= number_format((float)$pos['lat'],5) ?>, <?= number_format((float)$pos['lon'],5) ?></b> @ <?= date('Y-m-d H:i:s', (int)$pos['time']) ?></p>
      <p>RSSI: <?= htmlspecialchars((string)($pos['rx_rssi'] ?? '')) ?> / SNR: <?= htmlspecialchars((string)($pos['rx_snr'] ?? '')) ?></p>
    <?php else: ?>
      <p>No position on record.</p>
    <?php endif; ?>
  </div>
</section>

<section class="grid2">
  <div>
    <h3>Telemetry</h3>
    <?php if ($tele): ?>
      <!-- Individual metric charts -->
      <div class="telemetry-charts">
        <div class="chart-row">
          <div class="chart-item">
            <h5>Battery Level</h5>
            <div class="chart-container">
              <canvas id="batteryChart"></canvas>
            </div>
            <small><?= number_format((float)($tele['battery_level'] ?? 0), 1) ?>%</small>
          </div>
          <div class="chart-item">
            <h5>Voltage</h5>
            <div class="chart-container">
              <canvas id="voltageChart"></canvas>
            </div>
            <small><?= number_format((float)($tele['voltage'] ?? 0), 2) ?>V</small>
          </div>
        </div>
        <div class="chart-row">
          <div class="chart-item">
            <h5>Channel Utilization</h5>
            <div class="chart-container">
              <canvas id="chanUtilChart"></canvas>
            </div>
            <small><?= number_format((float)($tele['channel_utilization'] ?? 0), 1) ?>%</small>
          </div>
          <div class="chart-item">
            <h5>Air Util TX</h5>
            <div class="chart-container">
              <canvas id="airUtilChart"></canvas>
            </div>
            <small><?= number_format((float)($tele['air_util_tx'] ?? 0), 1) ?>%</small>
          </div>
        </div>
      </div>
      
      <!-- Uptime display -->
      <div class="uptime-display">
        <h5>Uptime</h5>
        <div class="uptime-value">
          <?php
          $uptime = (int)($tele['uptime_seconds'] ?? 0);
          if ($uptime > 0) {
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            $seconds = $uptime % 60;
            
            $uptimeStr = [];
            if ($days > 0) $uptimeStr[] = $days . 'd';
            if ($hours > 0) $uptimeStr[] = $hours . 'h';
            if ($minutes > 0) $uptimeStr[] = $minutes . 'm';
            if ($seconds > 0 || empty($uptimeStr)) $uptimeStr[] = $seconds . 's';
            
            echo implode(' ', $uptimeStr);
          } else {
            echo 'Unknown';
          }
          ?>
        </div>
      </div>
      
      <script>
      window.addEventListener('DOMContentLoaded', () => {
        const chartOptions = {
          responsive: false,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: '#444' },
              ticks: { color: '#ccc' }
            },
            x: {
              grid: { display: false },
              ticks: { display: false }
            }
          }
        };
        
        // Battery Chart (0-101%)
        const batteryValue = <?= $tele['battery_level'] ?? 'null' ?>;
        if (batteryValue !== null) {
          new Chart(document.getElementById('batteryChart'), {
            type: 'bar',
            data: {
              labels: [''],
              datasets: [{
                data: [batteryValue],
                backgroundColor: batteryValue > 20 ? '#28a745' : batteryValue > 10 ? '#ffc107' : '#dc3545',
                borderWidth: 0
              }]
            },
            options: {
              ...chartOptions,
              scales: {
                ...chartOptions.scales,
                y: { ...chartOptions.scales.y, max: 101 }
              }
            }
          });
        }
        
        // Voltage Chart (0-12V)
        const voltageValue = <?= $tele['voltage'] ?? 'null' ?>;
        if (voltageValue !== null) {
          new Chart(document.getElementById('voltageChart'), {
            type: 'bar',
            data: {
              labels: [''],
              datasets: [{
                data: [voltageValue],
                backgroundColor: voltageValue > 3.5 ? '#28a745' : voltageValue > 3.0 ? '#ffc107' : '#dc3545',
                borderWidth: 0
              }]
            },
            options: {
              ...chartOptions,
              scales: {
                ...chartOptions.scales,
                y: { ...chartOptions.scales.y, max: 12 }
              }
            }
          });
        }
        
        // Channel Utilization Chart (0-100%)
        const chanUtilValue = <?= $tele['channel_utilization'] ?? 'null' ?>;
        if (chanUtilValue !== null) {
          new Chart(document.getElementById('chanUtilChart'), {
            type: 'bar',
            data: {
              labels: [''],
              datasets: [{
                data: [chanUtilValue],
                backgroundColor: chanUtilValue < 50 ? '#28a745' : chanUtilValue < 80 ? '#ffc107' : '#dc3545',
                borderWidth: 0
              }]
            },
            options: {
              ...chartOptions,
              scales: {
                ...chartOptions.scales,
                y: { ...chartOptions.scales.y, max: 100 }
              }
            }
          });
        }
        
        // Air Util TX Chart (0-100%)
        const airUtilValue = <?= $tele['air_util_tx'] ?? 'null' ?>;
        if (airUtilValue !== null) {
          new Chart(document.getElementById('airUtilChart'), {
            type: 'bar',
            data: {
              labels: [''],
              datasets: [{
                data: [airUtilValue],
                backgroundColor: airUtilValue < 50 ? '#28a745' : airUtilValue < 80 ? '#ffc107' : '#dc3545',
                borderWidth: 0
              }]
            },
            options: {
              ...chartOptions,
              scales: {
                ...chartOptions.scales,
                y: { ...chartOptions.scales.y, max: 100 }
              }
            }
          });
        }
      });
      </script>
      <p>Updated: <?= date('Y-m-d H:i:s', (int)$tele['updated_at']) ?></p>
    <?php else: ?>
      <p>No telemetry on record.</p>
    <?php endif; ?>
  </div>
  <div>
    <h3>Recent Neighbors</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Neighbor</th><th>SNR</th><th>Heard At</th></tr></thead>
        <tbody>
        <?php foreach ($neighbors as $n): ?>
          <tr>
            <td>
              <a href="/?r=node&id=<?= (int)$n['neighbor_node_num'] ?>" class="node-link">
                <?php if (!empty($n['neighbor_long_name'])): ?>
                  <strong><?= htmlspecialchars($n['neighbor_long_name']) ?></strong>
                  <br><small class="text-muted"><?= (int)$n['neighbor_node_num'] ?></small>
                <?php elseif (!empty($n['neighbor_short_name'])): ?>
                  <strong><?= htmlspecialchars($n['neighbor_short_name']) ?></strong>
                  <br><small class="text-muted"><?= (int)$n['neighbor_node_num'] ?></small>
                <?php else: ?>
                  <strong>Node <?= (int)$n['neighbor_node_num'] ?></strong>
                  <br><small class="text-muted">Unknown</small>
                <?php endif; ?>
              </a>
            </td>
            <td><?= htmlspecialchars((string)$n['snr']) ?></td>
            <td><?= date('Y-m-d H:i:s', (int)$n['heard_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="grid2">
  <div>
    <h3>Recent Text Messages</h3>
    <?php if (!empty($text_messages)): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><i class="fas fa-user-check"></i> To</th>
              <th><i class="fas fa-comment"></i> Message</th>
              <th><i class="fas fa-satellite-dish"></i> Topic</th>
              <th><i class="fas fa-clock"></i> Sent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($text_messages, 0, 10) as $msg): ?>
              <tr>
                <td>
                  <?php if ($msg['node_to'] && $msg['node_to'] != 4294967295): ?>
                    <div class="node-info">
                      <a href="/?r=node&id=<?= (int)$msg['node_to'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($msg['to_name'] ?: $msg['to_short'] ?: 'Node #' . $msg['node_to']) ?>
                      </a>
                      <br>
                      <small class="text-muted">#<?= (int)$msg['node_to'] ?></small>
                    </div>
                  <?php else: ?>
                    <span class="badge bg-secondary">
                      <i class="fas fa-broadcast-tower"></i> Broadcast
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="message-content" style="max-width: 300px; word-break: break-word;">
                    <?= nl2br(htmlspecialchars($msg['message'] ?? '')) ?>
                  </div>
                </td>
                <td>
                  <?php if (!empty($msg['topic'])): ?>
                    <div class="topic-info">
                      <code class="small bg-light p-1 rounded" style="font-size: 0.75rem;"><?= htmlspecialchars($msg['topic']) ?></code>
                      <br>
                      <small class="text-muted">
                        <?php 
                        $topic_parts = explode('/', $msg['topic']);
                        if (count($topic_parts) >= 2) {
                          echo '<i class="fas fa-globe"></i> ' . htmlspecialchars($topic_parts[1]);
                          if (count($topic_parts) >= 5) {
                            echo ' <i class="fas fa-key"></i> ' . (strpos($msg['topic'], '/c/') !== false ? 'Encrypted' : 'Public');
                          }
                        }
                        ?>
                      </small>
                    </div>
                  <?php else: ?>
                    <span class="text-muted">
                      <i class="fas fa-question-circle"></i> Unknown
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="time-info">
                    <small><?= date('M j, Y H:i', (int)$msg['rx_time']) ?></small>
                    <br>
                    <small class="text-info"><?= $this->timeAgo((int)$msg['rx_time']) ?></small>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($text_messages) > 10): ?>
        <div class="mt-2">
          <small class="text-muted">
            Showing 10 of <?= count($text_messages) ?> recent messages. 
            <a href="/?r=text_messages&filter_from=<?= (int)($node['node_num'] ?? 0) ?>" class="text-decoration-none">
              View all messages from this node
            </a>
          </small>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="text-muted">
        <i class="fas fa-inbox"></i> No text messages sent from this node.
      </p>
    <?php endif; ?>
  </div>
  <div>
    <h3>Message Statistics</h3>
    <?php 
    $total_messages = count($text_messages);
    $broadcast_messages = array_filter($text_messages, fn($msg) => !$msg['node_to'] || $msg['node_to'] == 4294967295);
    $direct_messages = array_filter($text_messages, fn($msg) => $msg['node_to'] && $msg['node_to'] != 4294967295);
    $recent_messages = array_filter($text_messages, fn($msg) => $msg['rx_time'] > (time() - 86400)); // Last 24 hours
    ?>
    <ul class="list-unstyled">
      <li><i class="fas fa-comments text-primary"></i> <strong><?= $total_messages ?></strong> total messages</li>
      <li><i class="fas fa-broadcast-tower text-secondary"></i> <strong><?= count($broadcast_messages) ?></strong> broadcast messages</li>
      <li><i class="fas fa-user-friends text-success"></i> <strong><?= count($direct_messages) ?></strong> direct messages</li>
      <li><i class="fas fa-clock text-info"></i> <strong><?= count($recent_messages) ?></strong> in last 24 hours</li>
    </ul>
    
    <?php if ($total_messages > 0): ?>
      <div class="mt-3">
        <h5>Activity Timeline</h5>
        <canvas id="messageChart" width="300" height="200"></canvas>
        <script>
        window.addEventListener('DOMContentLoaded', () => {
          // Group messages by hour for the last 24 hours
          const hours = Array.from({length: 24}, (_, i) => {
            const hour = new Date();
            hour.setHours(hour.getHours() - (23 - i), 0, 0, 0);
            return hour;
          });
          
          const messageCounts = hours.map(hour => {
            const hourStart = Math.floor(hour.getTime() / 1000);
            const hourEnd = hourStart + 3600;
            return <?= json_encode($text_messages) ?>.filter(msg => 
              msg.rx_time >= hourStart && msg.rx_time < hourEnd
            ).length;
          });
          
          const ctx = document.getElementById('messageChart');
          new Chart(ctx, {
            type: 'line',
            data: {
              labels: hours.map(h => h.getHours() + ':00'),
              datasets: [{
                label: 'Messages per Hour',
                data: messageCounts,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
              }]
            },
            options: {
              responsive: true,
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    stepSize: 1
                  }
                }
              },
              plugins: {
                legend: {
                  display: false
                }
              }
            }
          });
        });
        </script>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($isAuthenticated && !empty($positionHistory)): ?>
<!-- Position History Section (only for authenticated users and nodes with history data) -->
<section class="grid1">
  <div>
    <h3><i class="fas fa-route"></i> Position History</h3>
    <p class="text-muted mb-3">
      <i class="fas fa-info-circle"></i> 
      <?php if ($isTrackedNode): ?>
        This node is configured for position tracking. Showing <?= count($positionHistory) ?> position records.
      <?php else: ?>
        This node has position history available. Showing <?= count($positionHistory) ?> position records.
      <?php endif ?>
    </p>
    
    <div class="mb-3">
      <small class="text-muted">
        Position history is only visible to logged-in users.
      </small>
    </div>
    
    <!-- Position History Map -->
    <div class="position-history-map mb-4">
      <div id="historyMap" style="height: 400px; border-radius: 8px;"></div>
    </div>
    
    <!-- Position History Table -->
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead class="table-dark">
          <tr>
            <th>Time</th>
            <th>Coordinates</th>
            <th>Altitude</th>
            <th>Signal</th>
            <th>Recorded</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($positionHistory, 0, 20) as $record): ?>
          <tr>
            <td>
              <small><?= htmlspecialchars($record['position_time']) ?></small>
            </td>
            <td>
              <a href="https://www.google.com/maps?q=<?= $record['lat'] ?>,<?= $record['lon'] ?>" 
                 target="_blank" 
                 class="text-decoration-none">
                <?= number_format((float)$record['lat'], 6) ?>,<br>
                <?= number_format((float)$record['lon'], 6) ?>
              </a>
            </td>
            <td>
              <small><?= $record['altitude'] ? $record['altitude'] . 'm' : '-' ?></small>
            </td>
            <td>
              <small>
                <?php if ($record['rx_rssi'] || $record['rx_snr']): ?>
                  <?= $record['rx_rssi'] ? $record['rx_rssi'] . 'dBm' : '' ?>
                  <?= $record['rx_rssi'] && $record['rx_snr'] ? '<br>' : '' ?>
                  <?= $record['rx_snr'] ? $record['rx_snr'] . 'dB' : '' ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </small>
            </td>
            <td>
              <small class="text-muted"><?= htmlspecialchars($record['recorded_time']) ?></small>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <?php if (count($positionHistory) > 20): ?>
      <div class="mt-2">
        <small class="text-muted">
          Showing first 20 of <?= count($positionHistory) ?> total position records.
          <a href="/?r=api&a=position_history&node_num=<?= $node['node_num'] ?>&limit=100" target="_blank">
            View all via API
          </a>
        </small>
      </div>
    <?php endif; ?>
    
    <!-- Leaflet Map JavaScript -->
    <script>
    window.addEventListener('DOMContentLoaded', () => {
      // Position history data
      const positionHistory = <?= json_encode($positionHistory) ?>;
      
      if (positionHistory.length > 0) {
        // Create map centered on the most recent position
        const mostRecent = positionHistory[0];
        const historyMap = L.map('historyMap').setView([mostRecent.lat, mostRecent.lon], 13);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '© OpenStreetMap contributors'
        }).addTo(historyMap);
        
        // Create polyline path from position history (oldest to newest)
        const pathCoords = positionHistory.slice().reverse().map(pos => [pos.lat, pos.lon]);
        
        // Add dotted path line connecting points in chronological order
        const pathLine = L.polyline(pathCoords, {
          color: '#007bff',
          weight: 2,
          opacity: 0.7,
          dashArray: '5, 10', // Creates dotted line: 5px dash, 10px gap
          lineCap: 'round',
          lineJoin: 'round'
        }).addTo(historyMap);
        
        // Add markers for each position
        positionHistory.forEach((pos, index) => {
          const isLatest = index === 0;
          const markerColor = isLatest ? 'red' : 'blue';
          const markerIcon = L.divIcon({
            html: `<div style="background-color: ${markerColor}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>`,
            className: 'custom-marker',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
          });
          
          const marker = L.marker([pos.lat, pos.lon], { icon: markerIcon }).addTo(historyMap);
          
          // Create popup content
          const popupContent = `
            <div style="font-size: 12px;">
              <strong>${isLatest ? 'Latest Position' : 'Position'}</strong><br>
              <strong>Time:</strong> ${pos.position_time}<br>
              <strong>Coords:</strong> ${Number(pos.lat).toFixed(6)}, ${Number(pos.lon).toFixed(6)}<br>
              ${pos.altitude ? `<strong>Altitude:</strong> ${pos.altitude}m<br>` : ''}
              ${pos.rx_rssi ? `<strong>RSSI:</strong> ${pos.rx_rssi}dBm<br>` : ''}
              ${pos.rx_snr ? `<strong>SNR:</strong> ${pos.rx_snr}dB` : ''}
            </div>
          `;
          
          marker.bindPopup(popupContent);
          
          // Open popup for the latest position
          if (isLatest) {
            marker.openPopup();
          }
        });
        
        // Fit map to show all positions
        if (pathCoords.length > 1) {
          historyMap.fitBounds(pathLine.getBounds(), { padding: [20, 20] });
        }
      }
    });
    </script>
  </div>
</section>
<?php elseif ($isAuthenticated): ?>
<!-- Message for authenticated users when no position history exists -->
<section class="grid1">
  <div>
    <h3><i class="fas fa-route"></i> Position History</h3>
    <div class="alert alert-info">
      <i class="fas fa-map-marker-alt"></i>
      <strong>No Position History Available</strong><br>
      <?php if ($isTrackedNode): ?>
        This node is configured for position tracking, but no position history has been recorded yet.
      <?php else: ?>
        This node does not have position history data available.
      <?php endif ?>
    </div>
  </div>
</section>
<?php endif; ?>
