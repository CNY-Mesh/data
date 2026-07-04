<?php
/**
 * Raw Packet Analysis Tool - Updated at <?= date('Y-m-d H:i:s') ?>

 * Analyzes stored raw_messages to debug decoding failures
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Decoder;
use App\Support\Env;
use Meshtastic\ServiceEnvelope;

$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);
$decoder = new Decoder();

// Get analysis parameters
$limit = (int)($_GET['limit'] ?? 20);
$channel = $_GET['channel'] ?? '';
$topic_filter = $_GET['topic'] ?? '';
$encrypted_only = isset($_GET['encrypted']);
$failed_only = isset($_GET['failed']);
$analyze_id = (int)($_GET['analyze'] ?? 0);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Raw Packet Analysis Tool</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filters { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .packet { background: white; margin: 10px 0; padding: 15px; border-radius: 8px; border-left: 4px solid #007acc; }
        .packet.failed { border-left-color: #dc3545; }
        .packet.encrypted { border-left-color: #ffc107; }
        .packet.success { border-left-color: #28a745; }
        .packet-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .packet-id { font-weight: bold; color: #007acc; }
        .packet-topic { color: #666; font-size: 12px; }
        .packet-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px; }
        .info-item { background: #f8f9fa; padding: 8px; border-radius: 4px; }
        .info-label { font-weight: bold; color: #555; font-size: 11px; }
        .info-value { color: #333; }
        .hex-dump { background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 11px; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
        .analysis { background: #e3f2fd; padding: 10px; border-radius: 4px; margin-top: 10px; }
        .error { background: #ffebee; padding: 10px; border-radius: 4px; margin-top: 10px; color: #c62828; }
        .success { background: #e8f5e8; padding: 10px; border-radius: 4px; margin-top: 10px; color: #2e7d32; }
        .btn { padding: 8px 16px; background: #007acc; color: white; text-decoration: none; border-radius: 4px; margin: 2px; display: inline-block; }
        .btn:hover { background: #005fa3; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007acc; }
        .stat-label { color: #666; font-size: 12px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: inline-block; width: 100px; font-weight: bold; }
        .form-group input, .form-group select { padding: 5px; margin-left: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔍 Raw Packet Analysis Tool</h1>
        <p>Analyze stored raw_messages to debug decoding failures and encryption issues</p>
    </div>

    <?php
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_encrypted = 1 THEN 1 ELSE 0 END) as encrypted_count,
            SUM(CASE WHEN message_type = 'DECODE_FAILED' THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN is_json = 1 THEN 1 ELSE 0 END) as json_count,
            COUNT(DISTINCT channel_id) as unique_channels,
            COUNT(DISTINCT gateway_id) as unique_gateways
        FROM raw_messages
    ";
    
    $stats = $db->pdo()->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total']) ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['failed_count']) ?></div>
            <div class="stat-label">Decode Failed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['encrypted_count']) ?></div>
            <div class="stat-label">Encrypted</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['json_count']) ?></div>
            <div class="stat-label">JSON Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['unique_channels'] ?></div>
            <div class="stat-label">Unique Channels</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['unique_gateways'] ?></div>
            <div class="stat-label">Unique Gateways</div>
        </div>
    </div>

    <div class="filters">
        <form method="GET">
            <div class="form-group">
                <label>Limit:</label>
                <select name="limit">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>
            <div class="form-group">
                <label>Channel:</label>
                <input type="text" name="channel" value="<?= htmlspecialchars($channel) ?>" placeholder="e.g. LongFast">
            </div>
            <div class="form-group">
                <label>Topic:</label>
                <input type="text" name="topic" value="<?= htmlspecialchars($topic_filter) ?>" placeholder="e.g. msh/US/NY">
            </div>
            <div class="form-group">
                <label>Filters:</label>
                <input type="checkbox" name="encrypted" <?= $encrypted_only ? 'checked' : '' ?>> Encrypted only
                <input type="checkbox" name="failed" <?= $failed_only ? 'checked' : '' ?>> Failed only
            </div>
            <button type="submit" class="btn">Apply Filters</button>
            <a href="?" class="btn">Clear All</a>
        </form>
    </div>

    <?php
    // Build query with filters
    $where_conditions = [];
    $params = [];
    
    if ($channel) {
        $where_conditions[] = "channel_id LIKE ?";
        $params[] = "%$channel%";
    }
    
    if ($topic_filter) {
        $where_conditions[] = "topic LIKE ?";
        $params[] = "%$topic_filter%";
    }
    
    if ($encrypted_only) {
        $where_conditions[] = "is_encrypted = 1";
    }
    
    if ($failed_only) {
        $where_conditions[] = "message_type = 'DECODE_FAILED'";
    }

    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "
        SELECT id, topic, channel_id, gateway_id, node_from, node_to, port_num, 
               is_encrypted, is_json, message_type, rx_time, rx_rssi, rx_snr, raw_message
        FROM raw_messages 
        $where_clause
        ORDER BY id DESC 
        LIMIT $limit
    ";
    
    $stmt = $db->pdo()->prepare($query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($messages) . " messages matching criteria:</p>";
    
    foreach ($messages as $msg) {
        $packet_class = 'packet';
        if ($msg['message_type'] == 'DECODE_FAILED') $packet_class .= ' failed';
        elseif ($msg['is_encrypted']) $packet_class .= ' encrypted';
        else $packet_class .= ' success';
        
        echo "<div class='$packet_class'>";
        echo "<div class='packet-header'>";
        echo "<span class='packet-id'>Message #{$msg['id']}</span>";
        echo "<span class='packet-topic'>{$msg['topic']}</span>";
        echo "</div>";
        
        echo "<div class='packet-info'>";
        echo "<div class='info-item'><div class='info-label'>CHANNEL</div><div class='info-value'>{$msg['channel_id']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>GATEWAY</div><div class='info-value'>{$msg['gateway_id']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>FROM</div><div class='info-value'>{$msg['node_from']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>TO</div><div class='info-value'>{$msg['node_to']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>PORT</div><div class='info-value'>{$msg['port_num']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>TYPE</div><div class='info-value'>{$msg['message_type']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>ENCRYPTED</div><div class='info-value'>" . ($msg['is_encrypted'] ? 'Yes' : 'No') . "</div></div>";
        echo "<div class='info-item'><div class='info-label'>JSON</div><div class='info-value'>" . ($msg['is_json'] ? 'Yes' : 'No') . "</div></div>";
        echo "<div class='info-item'><div class='info-label'>RSSI</div><div class='info-value'>{$msg['rx_rssi']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>SNR</div><div class='info-value'>{$msg['rx_snr']}</div></div>";
        echo "<div class='info-item'><div class='info-label'>SIZE</div><div class='info-value'>" . strlen($msg['raw_message']) . " bytes</div></div>";
        echo "<div class='info-item'><div class='info-label'>TIME</div><div class='info-value'>" . date('Y-m-d H:i:s', $msg['rx_time']) . "</div></div>";
        echo "</div>";
        
        // Show hex dump of first 100 bytes
        $hex_sample = bin2hex(substr($msg['raw_message'], 0, 100));
        $hex_formatted = chunk_split($hex_sample, 32, "\n");
        echo "<div class='hex-dump'>Hex dump (first 100 bytes):\n$hex_formatted";
        if (strlen($msg['raw_message']) > 100) {
            echo "... (" . (strlen($msg['raw_message']) - 100) . " more bytes)";
        }
        echo "</div>";
        
        // Attempt live decoding if requested
        if ($analyze_id == $msg['id']) {
            echo "<div class='analysis'>";
            echo "<strong>🔬 Live Analysis:</strong><br>";
            
            try {
                // Try to parse as ServiceEnvelope
                $envelope = $decoder->parseEnvelope($msg['raw_message']);
                if ($envelope) {
                    echo "✅ ServiceEnvelope parsed successfully<br>";
                    echo "- Channel ID: " . $envelope->getChannelId() . "<br>";
                    echo "- Gateway ID: " . $envelope->getGatewayId() . "<br>";
                    echo "- Has Packet: " . ($envelope->hasPacket() ? 'Yes' : 'No') . "<br>";
                    
                    if ($envelope->hasPacket()) {
                        $packet = $envelope->getPacket();
                        echo "- Packet From: " . $packet->getFrom() . "<br>";
                        echo "- Packet To: " . $packet->getTo() . "<br>";
                        echo "- Has Decoded: " . ($packet->hasDecoded() ? 'Yes' : 'No') . "<br>";
                        echo "- Has Encrypted: " . (strlen($packet->getEncrypted()) > 0 ? 'Yes' : 'No') . "<br>";
                        
                        if ($packet->hasDecoded()) {
                            $decoded = $packet->getDecoded();
                            echo "- Port Number: " . $decoded->getPortnum() . "<br>";
                            echo "- Payload Length: " . strlen($decoded->getPayload()) . " bytes<br>";
                        }
                        
                        // Try to get decoded data
                        $decoded_data = $decoder->getDecodedData($envelope);
                        if ($decoded_data) {
                            echo "✅ Successfully decoded data!<br>";
                            echo "<pre>" . print_r($decoded_data, true) . "</pre>";
                        } else {
                            echo "❌ Failed to decode data<br>";
                        }
                    }
                } else {
                    echo "❌ Failed to parse as ServiceEnvelope<br>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Analysis failed: " . $e->getMessage() . "</div>";
            }
            
            echo "</div>";
        }
        
        echo "<div style='margin-top: 10px;'>";
        echo "<a href='?analyze={$msg['id']}&limit=$limit&channel=" . urlencode($channel) . "&topic=" . urlencode($topic_filter) . ($encrypted_only ? '&encrypted=1' : '') . ($failed_only ? '&failed=1' : '') . "' class='btn'>🔬 Analyze</a>";
        echo "</div>";
        
        echo "</div>";
    }
    ?>
    
    <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 8px;">
        <h3>🛠️ Analysis Tools</h3>
        <a href="debug_logs.php" class="btn">📊 Debug Logs</a>
        <a href="decrypt_test.php" class="btn">🔐 Decryption Test</a>
        <a href="debug_protobuf.php" class="btn">📦 Protobuf Debug</a>
        <a href="debug_index.php" class="btn">🏠 Tools Index</a>
    </div>
</div>

</body>
</html>
