<div class="container">
    <h1>Meshtastic Data Analytics</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php elseif (empty($stats) || $stats['total_messages'] == 0): ?>
        <div class="alert alert-warning">
            <h4>No Data Available</h4>
            <p>The raw_messages table appears to be empty or inaccessible. This could mean:</p>
            <ul>
                <li>The MQTT worker hasn't been restarted with the new handlers yet</li>
                <li>Database path configuration mismatch</li>
                <li>The new table structure hasn't been applied</li>
            </ul>
            <p>Debug info:</p>
            <ul>
                <li>Total messages: <?= $stats['total_messages'] ?? 'N/A' ?></li>
                <li>Unique senders: <?= $stats['unique_senders'] ?? 'N/A' ?></li>
            </ul>
        </div>
    <?php else: ?>
    
    <!-- Overall Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Messages</h5>
                    <h2><?= number_format($stats['total_messages']) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Unique Senders</h5>
                    <h2><?= number_format($stats['unique_senders']) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Recent Activity</h5>
                    <h2><?= number_format($recentActivity['count']) ?></h2>
                    <small>Last 10 minutes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Nodes</h5>
                    <h2><?= number_format($recentActivity['unique_nodes']) ?></h2>
                    <small>Last 10 minutes</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Port Breakdown -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Port Number Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Port</th>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>With Data</th>
                                    <th>Avg Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($portBreakdown as $port): ?>
                                <tr>
                                    <td><code><?= $port['port_num'] ?? 'NULL' ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $port['port_name'] === 'UNKNOWN' ? 'warning' : 'secondary' ?>">
                                            <?= htmlspecialchars($port['port_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($port['count']) ?></td>
                                    <td><?= number_format($port['with_payload']) ?></td>
                                    <td><?= $port['avg_payload_len'] ?> bytes</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Channel Breakdown -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Channel Activity</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Channel</th>
                                    <th>Messages</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalChannelMessages = array_sum(array_column($channelBreakdown, 'count'));
                                foreach ($channelBreakdown as $channel): 
                                    $percentage = $totalChannelMessages > 0 ? round($channel['count'] / $totalChannelMessages * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($channel['channel_id']) ?></code></td>
                                    <td><?= number_format($channel['count']) ?></td>
                                    <td><?= $percentage ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- JSON Message Types -->
        <?php if (!empty($jsonTypes)): ?>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>JSON Message Types</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jsonTypes as $type): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($type['message_type'] ?? 'NULL') ?></code></td>
                                    <td><?= number_format($type['count']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Structured Data Tables -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Structured Data Tables</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Table</th>
                                    <th>Description</th>
                                    <th>Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($structuredData as $data): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($data['table']) ?></code></td>
                                    <td><?= htmlspecialchars($data['description']) ?></td>
                                    <td>
                                        <?php if ($data['count'] !== 'N/A'): ?>
                                            <?= number_format($data['count']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timing Information -->
    <?php if ($stats['first_message'] && $stats['last_message']): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Data Collection Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>First Message:</strong> 
                            <?= date('Y-m-d H:i:s', $stats['first_message']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Last Message:</strong> 
                            <?= date('Y-m-d H:i:s', $stats['last_message']) ?>
                        </div>
                    </div>
                    <div class="mt-2">
                        <strong>Collection Duration:</strong> 
                        <?= gmdate('H:i:s', $stats['last_message'] - $stats['first_message']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// Auto-refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);
</script>
