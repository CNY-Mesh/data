<?php
/**
 * Simple position history viewer
 * Upload this to the server and access at: https://data.cnymesh.org/position_history_viewer.php
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Support/Env.php';

use App\Database;
use App\Support\Env;

// Load environment
Env::load(__DIR__);

// Create database connection
$dbPath = __DIR__ . '/data/meshtastic.sqlite';
$dsn = 'sqlite:' . $dbPath;
$db = new Database($dsn);
$pdo = $db->pdo();

// Get OUR_NODES configuration
$ourNodes = Env::get('OUR_NODES', '');
$ourNodesArray = array_map('trim', explode(',', $ourNodes));

$selectedNode = $_GET['node'] ?? '';
$limit = min((int)($_GET['limit'] ?? 50), 500);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Position History - CNYmesh Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-map-marker-alt"></i> Position History Viewer</h4>
                        <small class="text-muted">Track position changes for configured nodes</small>
                    </div>
                    <div class="card-body">
                        <!-- Node Selection -->
                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="node" class="form-label">Select Node:</label>
                                    <select name="node" id="node" class="form-select">
                                        <option value="">All Tracked Nodes</option>
                                        <?php foreach ($ourNodesArray as $nodeId): 
                                            if (empty($nodeId)) continue;
                                            $nodeNum = is_numeric($nodeId) ? (int)$nodeId : hexdec($nodeId);
                                            $selected = ($selectedNode == $nodeId) ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($nodeId) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($nodeId) ?> (<?= $nodeNum ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="limit" class="form-label">Limit:</label>
                                    <select name="limit" id="limit" class="form-select">
                                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                        <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block">
                                        <i class="fas fa-search"></i> View History
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php
                        // Check if position_history table exists
                        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='position_history'");
                        $tableExists = $stmt->fetch() !== false;
                        
                        if (!$tableExists): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Position History Not Available</strong><br>
                                The position_history table doesn't exist yet. It will be created automatically when the first position update for a tracked node is received.
                            </div>
                        <?php else:
                            // Get position history
                            if ($selectedNode) {
                                $nodeNum = is_numeric($selectedNode) ? (int)$selectedNode : hexdec($selectedNode);
                                $stmt = $pdo->prepare("
                                    SELECT ph.*, 
                                        COALESCE(n.long_name, 'Unknown Node') as long_name, 
                                        COALESCE(n.short_name, SUBSTR(printf('!%08x', ph.node_num), -4)) as short_name,
                                        DATETIME(ph.time, 'unixepoch') as position_time,
                                        DATETIME(ph.recorded_at, 'unixepoch') as recorded_time
                                    FROM position_history ph 
                                    LEFT JOIN nodes n ON ph.node_num = n.node_num
                                    WHERE ph.node_num = ? 
                                    ORDER BY ph.time DESC 
                                    LIMIT ?
                                ");
                                $stmt->execute([$nodeNum, $limit]);
                            } else {
                                $stmt = $pdo->prepare("
                                    SELECT ph.*, 
                                        COALESCE(n.long_name, 'Unknown Node') as long_name, 
                                        COALESCE(n.short_name, SUBSTR(printf('!%08x', ph.node_num), -4)) as short_name,
                                        DATETIME(ph.time, 'unixepoch') as position_time,
                                        DATETIME(ph.recorded_at, 'unixepoch') as recorded_time
                                    FROM position_history ph 
                                    LEFT JOIN nodes n ON ph.node_num = n.node_num
                                    ORDER BY ph.time DESC 
                                    LIMIT ?
                                ");
                                $stmt->execute([$limit]);
                            }
                            
                            $history = $stmt->fetchAll();
                            
                            if (empty($history)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>No Position History Found</strong><br>
                                    <?= $selectedNode ? "No position history for node $selectedNode" : "No position history for any tracked nodes" ?> yet.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Node</th>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Altitude</th>
                                                <th>Position Time</th>
                                                <th>Recorded</th>
                                                <th>Signal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($history as $record): ?>
                                            <tr>
                                                <td>
                                                    <code><?= sprintf('!%08x', $record['node_num']) ?></code><br>
                                                    <small class="text-muted"><?= $record['node_num'] ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($record['long_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($record['short_name']) ?></small>
                                                </td>
                                                <td>
                                                    <a href="https://www.google.com/maps?q=<?= $record['lat'] ?>,<?= $record['lon'] ?>" target="_blank">
                                                        <?= number_format($record['lat'], 6) ?>,<br>
                                                        <?= number_format($record['lon'], 6) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?= $record['altitude'] ? $record['altitude'] . 'm' : '-' ?>
                                                </td>
                                                <td>
                                                    <?= $record['position_time'] ?>
                                                </td>
                                                <td>
                                                    <?= $record['recorded_time'] ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['rx_rssi'] || $record['rx_snr']): ?>
                                                        RSSI: <?= $record['rx_rssi'] ?? '-' ?>dBm<br>
                                                        SNR: <?= $record['rx_snr'] ?? '-' ?>dB
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Showing <?= count($history) ?> position history records.
                                        <a href="/?r=api&a=position_history<?= $selectedNode ? '&node_num=' . (is_numeric($selectedNode) ? (int)$selectedNode : hexdec($selectedNode)) : '' ?>&limit=<?= $limit ?>" target="_blank">
                                            View JSON API
                                        </a>
                                    </small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
