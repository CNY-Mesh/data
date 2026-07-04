<?php 
$title = "Search Results for: " . implode(', ', $searchTerms);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">🔍 Search Database</h4>
                </div>
                <div class="card-body">
                    <form method="post" action="/?r=search" class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="search_terms" class="form-label">
                                    <strong>Search Terms</strong> 
                                    <small class="text-muted">(comma-separated)</small>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="search_terms" 
                                       name="search_terms" 
                                       value="<?= htmlspecialchars($customSearchTerms ?: implode(', ', $defaultSearchTerms)) ?>"
                                       placeholder="Enter search terms separated by commas">
                                <div class="form-text">
                                    Search across selected data type for node names, numbers, hardware, messages, etc.
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="search_type" class="form-label">
                                    <strong>Search In</strong>
                                </label>
                                <select class="form-select" id="search_type" name="search_type">
                                    <option value="nodes" <?= $searchType === 'nodes' ? 'selected' : '' ?>>
                                        📡 Nodes (Names, Hardware)
                                    </option>
                                    <option value="text_messages" <?= $searchType === 'text_messages' ? 'selected' : '' ?>>
                                        💬 Text Messages
                                    </option>
                                    <option value="positions" <?= $searchType === 'positions' ? 'selected' : '' ?>>
                                        📍 Position Data
                                    </option>
                                    <option value="telemetry" <?= $searchType === 'telemetry' ? 'selected' : '' ?>>
                                        📊 Telemetry Data
                                    </option>
                                    <option value="raw_messages" <?= $searchType === 'raw_messages' ? 'selected' : '' ?>>
                                        🔧 Raw Messages
                                    </option>
                                </select>
                                <div class="form-text">
                                    Select data type to search
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="w-100">
                                    <button type="submit" class="btn btn-primary w-100 mb-1">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <?php if ($isCustomSearch): ?>
                                        <a href="/?r=search" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-undo"></i> Reset to Defaults
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-8">
                                <strong>Currently searching for:</strong> 
                                <?php foreach ($searchTerms as $term): ?>
                                    <span class="badge bg-primary me-1"><?= htmlspecialchars($term) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <strong>Total matches:</strong> 
                                <span class="badge bg-success"><?= $totalMatches ?></span>
                            </div>
                        </div>
                        <?php if ($isCustomSearch): ?>
                            <hr class="my-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Using custom search terms. 
                                <a href="/?r=search" class="text-decoration-none">Click here</a> to return to default search.
                            </small>
                        <?php else: ?>
                            <hr class="my-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Using default search terms from configuration. Enter custom terms above to override.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">

<?php if (empty($results)): ?>
    <div class="alert alert-warning">
        <strong>No matches found</strong> for the search terms in any database tables.
        <?php if (!$isCustomSearch): ?>
            <br><small>Try entering custom search terms above to search for specific content.</small>
        <?php endif; ?>
    </div>
<?php else: ?>

    <?php if (isset($results['nodes'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-router"></i> Nodes (<?= count($results['nodes']) ?> matches)</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Node #</th>
                                <th>Long Name</th>
                                <th>Short Name</th>
                                <th>Hardware</th>
                                <th>Last Seen</th>
                                <th>Match Field</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['nodes'] as $node): ?>
                                <tr>
                                    <td>
                                        <a href="/?r=node&id=<?= (int)$node['node_num'] ?>">
                                            <?= (int)$node['node_num'] ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($node['long_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($node['short_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($node['hardware'] ?? '') ?></td>
                                    <td>
                                        <?= $node['last_seen'] ? date('Y-m-d H:i:s', (int)$node['last_seen']) : 'Never' ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($node['match_field']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($results['text_messages'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-chat-text"></i> Text Messages (<?= count($results['text_messages']) ?> matches)</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Time</th>
                                <th>From→To</th>
                                <th>From Name</th>
                                <th>Message</th>
                                <th>Topic</th>
                                <th>Match Field</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['text_messages'] as $msg): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= (int)$msg['id'] ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('m/d H:i:s', (int)$msg['rx_time']) ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <a href="/?r=node&id=<?= (int)$msg['node_from'] ?>" class="text-decoration-none">
                                                <?= (int)$msg['node_from'] ?>
                                            </a>
                                            →
                                            <?php if ($msg['node_to']): ?>
                                                <a href="/?r=node&id=<?= (int)$msg['node_to'] ?>" class="text-decoration-none">
                                                    <?= (int)$msg['node_to'] ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">broadcast</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= htmlspecialchars($msg['long_name'] ?? $msg['short_name'] ?? 'Unknown') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="message-content">
                                            <?= htmlspecialchars($msg['message']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($msg['topic']): ?>
                                            <small class="text-muted">
                                                <?php
                                                $topicParts = explode('/', $msg['topic']);
                                                if (count($topicParts) >= 3) {
                                                    echo '<i class="bi bi-globe"></i> ' . htmlspecialchars($topicParts[2]);
                                                    if (strpos($msg['topic'], '/c/') !== false) {
                                                        echo ' <i class="bi bi-key text-warning" title="Encrypted"></i>';
                                                    }
                                                } else {
                                                    echo htmlspecialchars($msg['topic']);
                                                }
                                                ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($msg['match_field']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($results['positions'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-geo-alt"></i> Positions (<?= count($results['positions']) ?> matches)</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Node</th>
                                <th>Name</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Topic</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['positions'] as $pos): ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i:s', (int)$pos['time']) ?></td>
                                    <td>
                                        <a href="/?r=node&id=<?= (int)$pos['node_num'] ?>">
                                            <?= (int)$pos['node_num'] ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($pos['long_name'] ?? $pos['short_name'] ?? 'Unknown') ?></td>
                                    <td><?= number_format((float)$pos['latitude'], 6) ?></td>
                                    <td><?= number_format((float)$pos['longitude'], 6) ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php
                                            $topicParts = explode('/', $pos['topic']);
                                            if (count($topicParts) >= 3) {
                                                echo '<i class="bi bi-globe"></i> ' . htmlspecialchars($topicParts[2]);
                                            } else {
                                                echo htmlspecialchars($pos['topic']);
                                            }
                                            ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($results['telemetry'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-graph-up"></i> Telemetry (<?= count($results['telemetry']) ?> matches)</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Node</th>
                                <th>Name</th>
                                <th>Battery %</th>
                                <th>Voltage</th>
                                <th>Topic</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['telemetry'] as $tel): ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i:s', (int)$tel['updated_at']) ?></td>
                                    <td>
                                        <a href="/?r=node&id=<?= (int)$tel['node_num'] ?>">
                                            <?= (int)$tel['node_num'] ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($tel['long_name'] ?? $tel['short_name'] ?? 'Unknown') ?></td>
                                    <td><?= $tel['battery_level'] ? (int)$tel['battery_level'] . '%' : 'N/A' ?></td>
                                    <td><?= $tel['voltage'] ? number_format((float)$tel['voltage'], 2) . 'V' : 'N/A' ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php
                                            $topicParts = explode('/', $tel['topic']);
                                            if (count($topicParts) >= 3) {
                                                echo '<i class="bi bi-globe"></i> ' . htmlspecialchars($topicParts[2]);
                                            } else {
                                                echo htmlspecialchars($tel['topic']);
                                            }
                                            ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($results['raw_messages'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-code"></i> Raw Messages (<?= count($results['raw_messages']) ?> matches)</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Time</th>
                                <th>Topic</th>
                                <th>From→To</th>
                                <th>Port</th>
                                <th>Type</th>
                                <th>Channel</th>
                                <th>Gateway</th>
                                <th>Encrypted</th>
                                <th>RSSI/SNR</th>
                                <th>Payload</th>
                                <th>Match Field</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['raw_messages'] as $raw): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= (int)$raw['id'] ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= $raw['rx_time'] ? date('m/d H:i:s', (int)$raw['rx_time']) : 'N/A' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="font-monospace text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($raw['topic']) ?>">
                                            <?= htmlspecialchars($raw['topic']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($raw['node_from']): ?>
                                                <a href="/?r=node&id=<?= (int)$raw['node_from'] ?>" class="text-decoration-none">
                                                    <?= (int)$raw['node_from'] ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                            →
                                            <?php if ($raw['node_to']): ?>
                                                <a href="/?r=node&id=<?= (int)$raw['node_to'] ?>" class="text-decoration-none">
                                                    <?= (int)$raw['node_to'] ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($raw['port_num']): ?>
                                                <span class="badge bg-secondary"><?= (int)$raw['port_num'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($raw['message_type']): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($raw['message_type']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($raw['channel_id'] ?: '-') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($raw['gateway_id'] ?: '-') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($raw['is_encrypted']): ?>
                                            <span class="badge bg-warning">🔒</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">📖</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($raw['rx_rssi'] || $raw['rx_snr']): ?>
                                                <?= $raw['rx_rssi'] ? (int)$raw['rx_rssi'] . 'dBm' : '' ?>
                                                <?= ($raw['rx_rssi'] && $raw['rx_snr']) ? '<br>' : '' ?>
                                                <?= $raw['rx_snr'] ? number_format($raw['rx_snr'], 1) . 'dB' : '' ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($raw['payload_hex']): ?>
                                                <span class="font-monospace" title="<?= htmlspecialchars($raw['payload_hex']) ?>">
                                                    <?= htmlspecialchars(substr($raw['payload_hex'], 0, 20)) ?><?= strlen($raw['payload_hex']) > 20 ? '...' : '' ?>
                                                </span>
                                                <br><small class="text-info"><?= (int)$raw['payload_length'] ?> bytes</small>
                                            <?php else: ?>
                                                <span class="text-muted">No payload</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($raw['match_field']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<style>
.message-content {
    max-width: 300px;
    word-wrap: break-word;
}
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}
.table-responsive {
    max-height: 500px;
    overflow-y: auto;
}
</style>

        </div>
    </div>
</div>
