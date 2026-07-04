<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$db = new \App\Database();
$pdo = $db->pdo();

// Get recent statistics
$stats = [];

// Raw messages by topic pattern
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN topic LIKE '%/e/%' THEN 'Encrypted'
            WHEN topic LIKE '%/json/%' THEN 'JSON'
            ELSE 'Other'
        END as topic_type,
        COUNT(*) as count,
        MAX(created_at) as last_seen
    FROM raw_messages 
    GROUP BY topic_type 
    ORDER BY count DESC
");
$stats['topic_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Messages by port
$stmt = $pdo->query("
    SELECT port_num, COUNT(*) as count, MAX(created_at) as last_seen
    FROM raw_messages 
    GROUP BY port_num 
    ORDER BY count DESC 
    LIMIT 20
");
$stats['ports'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent encrypted messages
$stmt = $pdo->query("
    SELECT topic, gateway_id, node_from, node_to, port_num, created_at
    FROM raw_messages 
    WHERE topic LIKE '%/e/%' 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stats['recent_encrypted'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Node activity
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT node_from) as unique_senders,
           COUNT(DISTINCT node_to) as unique_receivers,
           COUNT(*) as total_messages
    FROM raw_messages
");
$stats['node_activity'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Nodes table count
$stmt = $pdo->query("SELECT COUNT(*) as count FROM nodes");
$stats['nodes_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Position records
$stmt = $pdo->query("SELECT COUNT(*) as count FROM positions");
$stats['positions_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>MQTT Processing Stats</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h2 { margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .metric { display: inline-block; margin: 10px 20px 10px 0; }
        .metric-value { font-size: 2em; font-weight: bold; color: #007cba; }
        .metric-label { color: #666; font-size: 0.9em; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .danger { color: #dc3545; }
        .refresh-controls { text-align: right; margin-bottom: 20px; }
        .refresh-controls a { margin-left: 10px; padding: 5px 10px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <div class="container">
        <h1>MQTT Processing Statistics</h1>
        
        <div class="refresh-controls">
            <strong>Auto-refresh: 30s</strong>
            <a href="debug_logs.php">View Logs</a>
        </div>

        <div class="card">
            <h2>Overview</h2>
            <div class="metric">
                <div class="metric-value <?= $stats['nodes_count'] > 0 ? 'success' : 'danger' ?>"><?= $stats['nodes_count'] ?></div>
                <div class="metric-label">Nodes in Database</div>
            </div>
            <div class="metric">
                <div class="metric-value"><?= $stats['positions_count'] ?></div>
                <div class="metric-label">Position Records</div>
            </div>
            <div class="metric">
                <div class="metric-value"><?= $stats['node_activity']['total_messages'] ?></div>
                <div class="metric-label">Total Messages</div>
            </div>
            <div class="metric">
                <div class="metric-value"><?= $stats['node_activity']['unique_senders'] ?></div>
                <div class="metric-label">Unique Senders</div>
            </div>
        </div>

        <div class="card">
            <h2>Message Types</h2>
            <table>
                <thead>
                    <tr>
                        <th>Topic Type</th>
                        <th>Count</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['topic_types'] as $type): ?>
                    <tr>
                        <td><?= htmlspecialchars($type['topic_type']) ?></td>
                        <td><?= number_format($type['count']) ?></td>
                        <td><?= $type['last_seen'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Port Numbers (Top 20)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Port</th>
                        <th>Count</th>
                        <th>Last Seen</th>
                        <th>Likely Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $portTypes = [
                        1 => 'TEXT_MESSAGE_APP',
                        3 => 'POSITION_APP',
                        4 => 'NODEINFO_APP',
                        67 => 'TELEMETRY_APP',
                        71 => 'NEIGHBORINFO_APP',
                        80 => 'TRACEROUTE_APP',
                        256 => 'PRIVATE_APP'
                    ];
                    
                    foreach ($stats['ports'] as $port): 
                        $portNum = (int)$port['port_num'];
                        $portType = $portTypes[$portNum] ?? 'Unknown';
                        $rowClass = $portNum === 0 ? 'danger' : ($portNum > 1000 ? 'warning' : 'success');
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= $portNum ?></td>
                        <td><?= number_format($port['count']) ?></td>
                        <td><?= $port['last_seen'] ?></td>
                        <td><?= $portType ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Recent Encrypted Messages</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Topic</th>
                        <th>From Node</th>
                        <th>Port</th>
                        <th>Gateway</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_encrypted'] as $msg): ?>
                    <tr>
                        <td><?= $msg['created_at'] ?></td>
                        <td><?= htmlspecialchars($msg['topic']) ?></td>
                        <td><?= $msg['node_from'] ?></td>
                        <td class="<?= $msg['port_num'] == 0 ? 'danger' : 'success' ?>"><?= $msg['port_num'] ?></td>
                        <td><?= htmlspecialchars($msg['gateway_id']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
