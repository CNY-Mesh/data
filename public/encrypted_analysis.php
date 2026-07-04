<?php
require_once '../bootstrap.php';

use App\Database;

$db = new Database('sqlite:../data/meshtastic.sqlite');
$pdo = $db->pdo();

// Get parameters
$limit = (int)($_GET['limit'] ?? 50);
$showPayload = isset($_GET['show_payload']);

// Add detailed logging for decryption failures
$logDecryptionFailures = true;
if ($logDecryptionFailures) {
    $stmt = $pdo->prepare(
        "SELECT id, topic, port, length(payload_binary) as payload_size, notes FROM raw_messages WHERE topic LIKE '%/e/%' ORDER BY id DESC LIMIT ?"
    );
    $stmt->execute([$limit]);
    $messages = $stmt->fetchAll();

    foreach ($messages as $msg) {
        $logFile = '../logs/decryption_failures.log';
        $logEntry = sprintf(
            "[ID: %d] Topic: %s, Port: %d, Payload Size: %d bytes\n",
            $msg['id'],
            $msg['topic'],
            $msg['port'],
            $msg['payload_size']
        );
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Encrypted Message Analysis - Meshtastic Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: #e8f4f8; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
        .stat-label { color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .encrypted { color: #d32f2f; font-weight: bold; }
        .port-unknown { color: #ff9800; }
        .port-nodeinfo { color: #4caf50; font-weight: bold; }
        .port-position { color: #2196f3; }
        .port-telemetry { color: #9c27b0; }
        .payload-preview { font-family: monospace; font-size: 12px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
        .controls { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 6px; }
        .controls a { margin-right: 15px; }
        .topic-encrypted { background: #ffe6e6; }
        .mystery-port { background: #fff3e0; }
        .nodeinfo-candidate { background: #e8f5e8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Encrypted Message Analysis</h1>
        
        <div class="controls">
            <strong>Controls:</strong>
            <a href="?limit=25">Show 25</a>
            <a href="?limit=50">Show 50</a>
            <a href="?limit=100">Show 100</a>
            <a href="?show_payload=1&limit=<?php echo $limit; ?>">Show Payload Preview</a>
            <a href="?limit=<?php echo $limit; ?>">Hide Payload</a>
            <a href="debug_logs.php">Debug Logs</a>
            <a href="restart_worker.php">Worker Control</a>
        </div>

        <?php
        // Get statistics
        $totalEncrypted = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE topic LIKE '%/e/%'")->fetchColumn();
        $recentEncrypted = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE topic LIKE '%/e/%' AND created_at > datetime('now', '-1 hour')")->fetchColumn();
        $uniqueChannels = $pdo->query("SELECT COUNT(DISTINCT channel) FROM raw_messages WHERE topic LIKE '%/e/%'")->fetchColumn();
        $portDistribution = $pdo->query("SELECT port, COUNT(*) as count FROM raw_messages WHERE topic LIKE '%/e/%' GROUP BY port ORDER BY count DESC LIMIT 10")->fetchAll();
        ?>

        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($totalEncrypted); ?></div>
                <div class="stat-label">Total Encrypted Messages</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($recentEncrypted); ?></div>
                <div class="stat-label">Last Hour</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $uniqueChannels; ?></div>
                <div class="stat-label">Unique Channels</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count($portDistribution); ?></div>
                <div class="stat-label">Different Ports</div>
            </div>
        </div>

        <h2>🔢 Port Distribution in Encrypted Messages</h2>
        <table>
            <tr>
                <th>Port</th>
                <th>Known As</th>
                <th>Count</th>
                <th>Percentage</th>
                <th>Likely Contains</th>
            </tr>
            <?php foreach ($portDistribution as $port): 
                $portName = match($port['port']) {
                    1 => 'TEXT_MESSAGE_APP',
                    3 => 'POSITION_APP',
                    4 => 'NODEINFO_APP',
                    67 => 'TELEMETRY_APP',
                    70 => 'TRACEROUTE_APP',
                    71 => 'NEIGHBORINFO_APP',
                    73 => 'MAP_REPORT_APP',
                    default => 'UNKNOWN'
                };
                
                $contains = match($port['port']) {
                    1 => 'Text messages, chat',
                    3 => 'GPS coordinates',
                    4 => 'Node names, hardware info',
                    67 => 'Battery, voltage, temperature',
                    70 => 'Network routing paths',
                    71 => 'Neighbor node lists',
                    73 => 'Map position reports',
                    default => 'Unknown data type'
                };
                
                $percentage = round(($port['count'] / $totalEncrypted) * 100, 1);
                $cssClass = $port['port'] == 4 ? 'nodeinfo-candidate' : ($portName == 'UNKNOWN' ? 'mystery-port' : '');
            ?>
            <tr class="<?php echo $cssClass; ?>">
                <td class="<?php echo $port['port'] == 4 ? 'port-nodeinfo' : ($portName == 'UNKNOWN' ? 'port-unknown' : ''); ?>">
                    <?php echo $port['port'] ?? 'NULL'; ?>
                </td>
                <td><?php echo $portName; ?></td>
                <td><?php echo number_format($port['count']); ?></td>
                <td><?php echo $percentage; ?>%</td>
                <td><?php echo $contains; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>📊 Recent Encrypted Messages (Last <?php echo $limit; ?>)</h2>
        <?php
        $sql = "SELECT id, topic, channel, node_from, node_to, port, 
                       datetime(rx_time, 'unixepoch') as rx_time_formatted,
                       datetime(created_at) as created_at_formatted,
                       rx_rssi, rx_snr, notes,
                       length(payload_binary) as payload_size
                       " . ($showPayload ? ", substr(hex(payload_binary), 1, 40) as payload_hex" : "") . "
                FROM raw_messages 
                WHERE topic LIKE '%/e/%' 
                ORDER BY id DESC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $messages = $stmt->fetchAll();
        ?>

        <table>
            <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Topic</th>
                <th>Channel</th>
                <th>From</th>
                <th>To</th>
                <th>Port</th>
                <th>Type</th>
                <th>Size</th>
                <th>RSSI</th>
                <th>SNR</th>
                <?php if ($showPayload): ?>
                <th>Payload Preview</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($messages as $msg): 
                $portName = match($msg['port']) {
                    1 => 'TEXT',
                    3 => 'POS',
                    4 => 'NODE',
                    67 => 'TELEM',
                    70 => 'TRACE',
                    71 => 'NEIGH',
                    73 => 'MAP',
                    default => 'UNK'
                };
                
                $isNodeInfo = $msg['port'] == 4;
                $isEncrypted = strpos($msg['topic'], '/e/') !== false;
            ?>
            <tr class="<?php echo $isEncrypted ? 'topic-encrypted' : ''; ?> <?php echo $isNodeInfo ? 'nodeinfo-candidate' : ''; ?>">
                <td><?php echo $msg['id']; ?></td>
                <td><?php echo substr($msg['rx_time_formatted'] ?? $msg['created_at_formatted'], 11, 8); ?></td>
                <td style="font-size: 11px; max-width: 200px; overflow: hidden;">
                    <?php echo htmlspecialchars($msg['topic']); ?>
                </td>
                <td><?php echo htmlspecialchars($msg['channel']); ?></td>
                <td><?php echo htmlspecialchars($msg['node_from']); ?></td>
                <td><?php echo htmlspecialchars($msg['node_to']); ?></td>
                <td class="<?php echo $isNodeInfo ? 'port-nodeinfo' : ($msg['port'] ? '' : 'port-unknown'); ?>">
                    <?php echo $msg['port'] ?? 'NULL'; ?>
                </td>
                <td class="<?php echo $isNodeInfo ? 'port-nodeinfo' : ''; ?>">
                    <?php echo $portName; ?>
                </td>
                <td><?php echo $msg['payload_size']; ?>b</td>
                <td><?php echo $msg['rx_rssi'] ?? '-'; ?></td>
                <td><?php echo $msg['rx_snr'] ?? '-'; ?></td>
                <?php if ($showPayload): ?>
                <td class="payload-preview"><?php echo $msg['payload_hex'] ?? ''; ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>🎯 Key Findings</h2>
        <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <?php
            $nodeInfoCount = 0;
            foreach ($portDistribution as $port) {
                if ($port['port'] == 4) {
                    $nodeInfoCount = $port['count'];
                    break;
                }
            }
            ?>
            
            <h3>Analysis Results:</h3>
            <ul>
                <li><strong>🔐 Total Encrypted Messages:</strong> <?php echo number_format($totalEncrypted); ?> (these are being skipped)</li>
                <li><strong>📝 NodeInfo Messages (Port 4):</strong> <?php echo $nodeInfoCount ? number_format($nodeInfoCount) : 'None found'; ?></li>
                <?php if ($nodeInfoCount > 0): ?>
                <li><strong>🎯 CRITICAL:</strong> We found <?php echo number_format($nodeInfoCount); ?> NodeInfo messages in encrypted data!</li>
                <li><strong>💡 This explains why we have 0 nodes:</strong> Node names/info is in encrypted messages we're skipping</li>
                <?php else: ?>
                <li><strong>🤔 Interesting:</strong> No Port 4 (NodeInfo) messages found in encrypted data</li>
                <li><strong>💭 This suggests:</strong> Either NodeInfo comes through a different channel, or timing issue</li>
                <?php endif; ?>
                <li><strong>📍 Position Data:</strong> Successfully coming through unencrypted /map/ messages (36 entries)</li>
                <li><strong>📊 Telemetry Data:</strong> Successfully coming through unencrypted messages (17 entries)</li>
            </ul>
            
            <?php if ($nodeInfoCount > 0): ?>
            <div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 15px;">
                <strong>🚀 Next Steps:</strong> We need to fix the encrypted message parsing to get node names and information!
            </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
            <h3>🔗 Navigation</h3>
            <a href="debug_logs.php">View Debug Logs</a> | 
            <a href="restart_worker.php">Worker Management</a> | 
            <a href="rawdata.php">Raw Database Data</a> | 
            <a href="?r=dashboard">Dashboard</a> |
            <a href="cleanup.php">Database Cleanup</a>
        </div>
    </div>
</body>
</html>
