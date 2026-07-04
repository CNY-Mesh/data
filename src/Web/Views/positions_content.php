<?php
/**
 * Positions Content - AJAX-only view
 * Contains only the refreshable content without page chrome
 */
?>

<!-- Statistics Section -->
<div class="stats-section">
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-number"><?= number_format($stats['total_positions']) ?></div>
            <div class="stat-label">Total Positions</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= number_format($stats['unique_nodes']) ?></div>
            <div class="stat-label">Unique Nodes</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= number_format($stats['known_nodes']) ?></div>
            <div class="stat-label">Known Nodes</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= number_format($stats['with_altitude']) ?></div>
            <div class="stat-label">With Altitude</div>
        </div>
    </div>
    
    <?php if ($stats['earliest']): ?>
    <div class="coverage-info">
        <small>
            Time Range: <?= date('M j, Y', $stats['earliest']) ?> to <?= date('M j, Y', $stats['latest']) ?><br>
            Coverage: <?= round($stats['min_lat'], 4) ?>° to <?= round($stats['max_lat'], 4) ?>° N, 
            <?= round($stats['min_lon'], 4) ?>° to <?= round($stats['max_lon'], 4) ?>° W
        </small>
    </div>
    <?php endif; ?>
</div>

<!-- Map Toggle -->
<div class="map-controls">
    <button type="button" onclick="toggleMap()">
        <i class="fas fa-map"></i> <span id="mapToggleText">Show Map</span>
    </button>
</div>

<!-- Map Container -->
<div id="mapContainer" class="map-container" style="display: none;">
    <div id="positionsMap" style="height: 400px;"></div>
</div>

<!-- Results Table -->
<div class="results-section">
    <h3>Position Records (<?= count($positions) ?> of <?= number_format($stats['total_positions']) ?>)</h3>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Node</th>
                    <th>Name</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Altitude</th>
                    <th>Signal</th>
                    <th>Topic</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($positions as $pos): ?>
                <tr class="<?= $pos['is_known_node'] ? 'known-node' : 'unknown-node' ?>">
                    <td>
                        <code><?= htmlspecialchars($pos['node_num']) ?></code>
                        <?php if ($pos['is_known_node']): ?>
                            <i class="fas fa-check-circle" title="Known Node"></i>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pos['long_name']): ?>
                            <strong><?= htmlspecialchars($pos['long_name']) ?></strong>
                            <?php if ($pos['short_name']): ?>
                                <br><small><?= htmlspecialchars($pos['short_name']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="unknown">Unknown Node</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= $pos['lat_rounded'] ?></code>
                    </td>
                    <td>
                        <code><?= $pos['lon_rounded'] ?></code>
                    </td>
                    <td>
                        <?php if ($pos['altitude_m']): ?>
                            <?= $pos['altitude_m'] ?>m
                        <?php else: ?>
                            <span class="unknown">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pos['rx_rssi']): ?>
                            <small>RSSI: <?= round($pos['rx_rssi']) ?>dBm</small>
                        <?php endif; ?>
                        <?php if ($pos['rx_snr']): ?>
                            <br><small>SNR: <?= round($pos['rx_snr'], 1) ?>dB</small>
                        <?php endif; ?>
                        <?php if (!$pos['rx_rssi'] && !$pos['rx_snr']): ?>
                            <span class="unknown">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($pos['topic'])): ?>
                            <div class="topic-info">
                                <code class="small bg-light p-1 rounded" style="font-size: 0.7rem;"><?= htmlspecialchars($pos['topic']) ?></code>
                                <br>
                                <small class="text-muted">
                                    <?php 
                                    $topic_parts = explode('/', $pos['topic']);
                                    if (count($topic_parts) >= 2) {
                                        echo '<i class="fas fa-globe"></i> ' . htmlspecialchars($topic_parts[1]);
                                        if (count($topic_parts) >= 5) {
                                            echo ' <i class="fas fa-key"></i> ' . (strpos($pos['topic'], '/c/') !== false ? 'Encrypted' : 'Public');
                                        }
                                    }
                                    ?>
                                </small>
                            </div>
                        <?php else: ?>
                            <span class="text-muted unknown">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small><?= date('M j, Y H:i:s', $pos['time']) ?></small>
                        <br><small class="time-ago"><?= $this->timeAgo($pos['time']) ?></small>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button onclick="showOnMap(<?= $pos['lat'] ?>, <?= $pos['lon'] ?>, '<?= htmlspecialchars($pos['long_name'] ?: 'Node ' . $pos['node_num']) ?>')" title="Show on Map">
                                <i class="fas fa-map-marker-alt"></i>
                            </button>
                            <a href="/?r=node&id=<?= $pos['node_num'] ?>" title="Node Details">
                                <i class="fas fa-info-circle"></i>
                            </a>
                            <button onclick="copyCoordinates(<?= $pos['lat'] ?>, <?= $pos['lon'] ?>)" title="Copy Coordinates">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (empty($positions)): ?>
    <div class="no-results">
        <i class="fas fa-info-circle"></i> No positions found matching your criteria.
    </div>
    <?php endif; ?>
</div>
