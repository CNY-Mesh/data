<?php
/**
 * Our Nodes Content - AJAX-only view
 * Contains only the refreshable content without page chrome
 */
?>

<?php if (empty($ourNodes)): ?>
    <div class="alert alert-info">
        <h5>No nodes found</h5>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($ourNodes as $node): ?>
            <div class="col-12 col-lg-6 col-xl-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title mb-1">
                                <a href="/?r=node&id=<?= $node['node_num'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($node['long_name'] ?: $node['short_name'] ?: 'Unknown') ?>
                                </a>
                            </h5>
                            <div class="text-muted small">
                                <?php if ($node['short_name'] && $node['long_name'] !== $node['short_name']): ?>
                                    <span class="badge bg-secondary me-2"><?= htmlspecialchars($node['short_name']) ?></span>
                                <?php endif; ?>
                                Node: <?= $node['node_num'] ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                Last seen:<br>
                                <?= $node['last_seen_ago'] ?>
                            </small>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Hardware Information -->
                        <?php if ($node['hardware']): ?>
                            <div class="mb-3">
                                <strong>Hardware:</strong> 
                                <span class="badge bg-info"><?= htmlspecialchars($node['hardware']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Position Information -->
                        <?php if ($node['position']): ?>
                            <div class="mb-3">
                                <h6 class="mb-2">📍 Position</h6>
                                
                                <div class="small">
                                    <div>
                                        <a href="https://www.openstreetmap.org/?mlat=<?= $node['position']['lat'] ?>&mlon=<?= $node['position']['lon'] ?>&zoom=15" 
                                           target="_blank" 
                                           class="text-decoration-none"
                                           title="Open in OpenStreetMap">
                                            Lat: <?= number_format($node['position']['lat'], 6) ?>, 
                                            Lng: <?= number_format($node['position']['lon'], 6) ?>
                                        </a>
                                    </div>
                                    <?php if ($node['position']['altitude']): ?>
                                        <div>Alt: <?= $node['position']['altitude'] ?>m</div>
                                    <?php endif; ?>
                                    <div class="text-muted">
                                        Updated: <?= $this->timeAgo($node['position']['time']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <h6 class="mb-2">📍 Position</h6>
                                <div class="small text-muted">
                                    No position data available
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Telemetry Information -->
                        <?php if ($node['telemetry']): ?>
                            <div class="mb-3">
                                <h6 class="mb-2">📊 Telemetry</h6>
                                <div class="row text-center">
                                    <?php if ($node['telemetry']['battery_level']): ?>
                                        <div class="col-6">
                                            <div class="small text-muted">Battery</div>
                                            <div class="fw-bold"><?= $node['telemetry']['battery_level'] ?>%</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($node['telemetry']['voltage']): ?>
                                        <div class="col-6">
                                            <div class="small text-muted">Voltage</div>
                                            <div class="fw-bold"><?= number_format($node['telemetry']['voltage'], 2) ?>V</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($node['telemetry']['channel_utilization']): ?>
                                        <div class="col-6">
                                            <div class="small text-muted">Ch. Util</div>
                                            <div class="fw-bold"><?= number_format($node['telemetry']['channel_utilization'], 1) ?>%</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($node['telemetry']['air_util_tx']): ?>
                                        <div class="col-6">
                                            <div class="small text-muted">Air Util TX</div>
                                            <div class="fw-bold"><?= number_format($node['telemetry']['air_util_tx'], 1) ?>%</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small mt-1">
                                    Updated: <?= $this->timeAgo($node['telemetry']['updated_at']) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Signal Information -->
                        <?php if ($node['position'] && ($node['position']['rx_snr'] || $node['position']['rx_rssi'])): ?>
                            <div class="mb-3">
                                <h6 class="mb-2">📶 Signal</h6>
                                <div class="row text-center">
                                    <?php if ($node['position']['rx_snr']): ?>
                                        <div class="col-6">
                                            <div class="small text-muted">SNR</div>
                                            <div class="fw-bold"><?= number_format($node['position']['rx_snr'], 1) ?> dB</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($node['position']['rx_rssi']): ?>
                                        <div class="col-6">
                                            <div class="small text-muted">RSSI</div>
                                            <div class="fw-bold"><?= number_format($node['position']['rx_rssi'], 1) ?> dBm</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Messages -->
                        <?php if (!empty($node['recent_messages'])): ?>
                            <div class="mb-0">
                                <h6 class="mb-2">💬 Recent Messages (24h)</h6>
                                <?php foreach (array_slice($node['recent_messages'], 0, 3) as $msg): ?>
                                    <div class="border-start border-primary border-2 ps-2 mb-2 small">
                                        <div class="fw-bold">
                                            To: <?= htmlspecialchars($msg['to_name'] ?: $msg['to_short'] ?: 'Unknown') ?>
                                        </div>
                                        <div class="text-truncate" style="max-width: 100%;">
                                            <?= htmlspecialchars(substr($msg['message'], 0, 100)) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?>
                                        </div>
                                        <div class="text-muted">
                                            <?= $this->timeAgo($msg['rx_time']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($node['recent_messages']) > 3): ?>
                                    <div class="text-muted small">
                                        +<?= count($node['recent_messages']) - 3 ?> more messages
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer text-center">
                        <a href="/?r=node&id=<?= $node['node_num'] ?>" class="btn btn-primary btn-sm">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
