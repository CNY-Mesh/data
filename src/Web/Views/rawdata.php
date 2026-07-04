<div class="container">
    <h1>Raw Database Data</h1>
    <p class="text-muted">Showing up to 100 most recent records per table</p>
    
    <?php foreach ($data as $table => $rows): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= htmlspecialchars($table) ?></h5>
                <span class="badge bg-primary"><?= count($rows) ?> records</span>
            </div>
            <div class="card-body">
                <?php if (empty($rows)): ?>
                    <p class="text-muted">No data found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <?php foreach (array_keys($rows[0]) as $col): ?>
                                        <th><?= htmlspecialchars($col) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $col => $val): ?>
                                            <td>
                                                <?php if ($table === 'raw_messages'): ?>
                                                    <?php if ($col === 'payload_hex' && !empty($val)): ?>
                                                        <code class="small" title="<?= htmlspecialchars($val) ?>">
                                                            <?= htmlspecialchars(substr($val, 0, 32)) ?><?= strlen($val) > 32 ? '...' : '' ?>
                                                        </code>
                                                    <?php elseif ($col === 'raw_message'): ?>
                                                        <span class="text-muted small">[binary data]</span>
                                                    <?php elseif ($col === 'processed_at' || $col === 'rx_time'): ?>
                                                        <?= $val ? date('Y-m-d H:i:s', $val) : '' ?>
                                                    <?php elseif ($col === 'node_from' || $col === 'node_to'): ?>
                                                        <?php if ($val): ?>
                                                            <code>!<?= base_convert($val, 10, 16) ?></code>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($val) ?>
                                                        <?php endif; ?>
                                                    <?php elseif ($col === 'port_num'): ?>
                                                        <span class="badge bg-<?= $val === 0 ? 'secondary' : ($val > 100 ? 'warning' : 'info') ?>">
                                                            <?= $val ?>
                                                        </span>
                                                    <?php elseif ($col === 'is_encrypted' || $col === 'is_json'): ?>
                                                        <span class="badge bg-<?= $val ? 'success' : 'secondary' ?>">
                                                            <?= $val ? 'Yes' : 'No' ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars(is_scalar($val) ? (string)$val : json_encode($val)) ?>
                                                    <?php endif; ?>
                                                <?php elseif ($table === 'text_messages'): ?>
                                                    <?php if ($col === 'rx_time'): ?>
                                                        <?= $val ? date('Y-m-d H:i:s', $val) : '' ?>
                                                    <?php elseif ($col === 'node_from' || $col === 'node_to'): ?>
                                                        <code>!<?= base_convert($val, 10, 16) ?></code>
                                                    <?php elseif ($col === 'message'): ?>
                                                        <div style="max-width: 300px; word-break: break-word;">
                                                            <?= htmlspecialchars($val) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($val) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php 
                                                    // Handle other tables as before
                                                    if (($col === 'last_seen' || $col === 'time' || $col === 'heard_at' || $col === 'logged_at' || $col === 'updated_at' || $col === 'saved_at') && is_numeric($val) && $val > 1000000000): 
                                                        echo date('Y-m-d H:i:s', $val);
                                                    elseif (($col === 'node_num' || $col === 'reporter_node_num' || $col === 'neighbor_node_num' || $col === 'src_node_num' || $col === 'dest_node_num' || $col === 'hop_node_num') && $val):
                                                        echo '<code>!' . base_convert($val, 10, 16) . '</code>';
                                                    else:
                                                        echo htmlspecialchars(is_scalar($val) ? (string)$val : json_encode($val));
                                                    endif;
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Decode Errors Section -->
    <?php if (isset($data['decode_errors'])): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Decode Errors & Encrypted Messages
                </h5>
                <span class="badge bg-dark"><?= count($data['decode_errors']) ?> errors</span>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>About Decode Errors:</strong> These are MQTT messages that could not be decoded, 
                    typically encrypted LongFast messages or corrupted data. The metadata is still stored for analysis.
                </div>
                
                <?php if (empty($data['decode_errors'])): ?>
                    <p class="text-muted">No decode errors found in recent messages.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-warning">
                                <tr>
                                    <th>Time</th>
                                    <th>Topic</th>
                                    <th>Channel</th>
                                    <th>Size</th>
                                    <th>Encrypted</th>
                                    <th>Hex Preview</th>
                                    <th>Error Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['decode_errors'] as $error): ?>
                                    <tr>
                                        <td class="small">
                                            <?= $error['processed_at'] ? date('m/d H:i:s', $error['processed_at']) : 'Unknown' ?>
                                        </td>
                                        <td class="small">
                                            <code><?= htmlspecialchars($error['topic']) ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary small">
                                                <?= htmlspecialchars($error['channel_id'] ?: 'Unknown') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $error['payload_length'] ?? 0 ?> bytes
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $error['is_encrypted'] ? 'danger' : 'secondary' ?>">
                                                <?= $error['is_encrypted'] ? 'Yes' : 'No' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($error['payload_hex'])): ?>
                                                <code class="small" title="<?= htmlspecialchars($error['payload_hex']) ?>">
                                                    <?= htmlspecialchars(substr($error['payload_hex'], 0, 24)) ?><?= strlen($error['payload_hex']) > 24 ? '...' : '' ?>
                                                </code>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php 
                                            $raw_data = json_decode($error['raw_message'], true);
                                            if (isset($raw_data['json_data']['error_message'])) {
                                                echo '<span class="text-danger">' . htmlspecialchars($raw_data['json_data']['error_message']) . '</span>';
                                            } else {
                                                echo '<span class="text-muted">Decode failed</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <h6><i class="fas fa-chart-bar me-2"></i>Error Statistics</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center py-2">
                                        <div class="h5 mb-1">
                                            <?php 
                                            $encrypted_count = count(array_filter($data['decode_errors'], fn($e) => $e['is_encrypted']));
                                            echo $encrypted_count;
                                            ?>
                                        </div>
                                        <small class="text-muted">Encrypted</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center py-2">
                                        <div class="h5 mb-1">
                                            <?php 
                                            $total_errors = count($data['decode_errors']);
                                            echo $total_errors - $encrypted_count;
                                            ?>
                                        </div>
                                        <small class="text-muted">Other Errors</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center py-2">
                                        <div class="h5 mb-1">
                                            <?php 
                                            $avg_size = $total_errors > 0 ? round(array_sum(array_column($data['decode_errors'], 'payload_length')) / $total_errors) : 0;
                                            echo $avg_size;
                                            ?>
                                        </div>
                                        <small class="text-muted">Avg Size (bytes)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center py-2">
                                        <div class="h5 mb-1">
                                            <?php 
                                            $channels = array_unique(array_column($data['decode_errors'], 'channel_id'));
                                            echo count(array_filter($channels));
                                            ?>
                                        </div>
                                        <small class="text-muted">Channels</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
