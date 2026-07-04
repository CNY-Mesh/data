<?php
/**
 * Debug script to check raw messages and API status
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Support\Env;

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($envFile));
    $dotenv->load();
}

$dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);
$pdo = $db->pdo();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Raw Messages Debug</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #fff; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #555; padding: 8px; text-align: left; }
        th { background: #333; }
        .error { color: #ff6b6b; }
        .success { color: #51cf66; }
        .info { color: #74c0fc; }
        .small { font-size: 0.8em; color: #aaa; }
    </style>
</head>
<body>
    <h1>Raw Messages Debug - <?= date('Y-m-d H:i:s') ?></h1>
    
    <h2>Database Statistics</h2>
    <?php
    try {
        // Basic counts
        $totalCount = $pdo->query("SELECT COUNT(*) FROM raw_messages")->fetchColumn();
        $errorCount = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE message_type = 'decode_error'")->fetchColumn();
        $recentCount = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE rx_time > strftime('%s', 'now', '-1 hour')")->fetchColumn();
        $veryRecentCount = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE rx_time > strftime('%s', 'now', '-10 minutes')")->fetchColumn();
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Count</th></tr>";
        echo "<tr><td>Total Raw Messages</td><td class='info'>$totalCount</td></tr>";
        echo "<tr><td>Decode Error Messages</td><td class='error'>$errorCount</td></tr>";
        echo "<tr><td>Messages (Last Hour)</td><td class='info'>$recentCount</td></tr>";
        echo "<tr><td>Messages (Last 10 Min)</td><td class='success'>$veryRecentCount</td></tr>";
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<div class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    
    <h2>Recent Messages (Last 20)</h2>
    <?php
    try {
        $stmt = $pdo->query("
            SELECT id, topic, message_type, node_from, is_encrypted, payload_length, 
                   datetime(rx_time, 'unixepoch') as rx_time_formatted, rx_time,
                   substr(payload_hex, 1, 32) as hex_preview
            FROM raw_messages 
            ORDER BY rx_time DESC 
            LIMIT 20
        ");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($messages)) {
            echo "<div class='error'>No messages found in database!</div>";
        } else {
            echo "<table>";
            echo "<tr><th>ID</th><th>Time</th><th>Topic</th><th>Type</th><th>From</th><th>Encrypted</th><th>Size</th><th>Hex Preview</th></tr>";
            foreach ($messages as $msg) {
                $rowClass = $msg['message_type'] === 'decode_error' ? 'error' : '';
                echo "<tr class='$rowClass'>";
                echo "<td>{$msg['id']}</td>";
                echo "<td class='small'>{$msg['rx_time_formatted']}</td>";
                echo "<td class='small'>" . htmlspecialchars(substr($msg['topic'], -20)) . "</td>";
                echo "<td>{$msg['message_type']}</td>";
                echo "<td>{$msg['node_from']}</td>";
                echo "<td>" . ($msg['is_encrypted'] ? 'Yes' : 'No') . "</td>";
                echo "<td>{$msg['payload_length']}</td>";
                echo "<td class='small'>{$msg['hex_preview']}...</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>Query Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    
    <h2>Message Types (Last Hour)</h2>
    <?php
    try {
        $stmt = $pdo->query("
            SELECT message_type, COUNT(*) as count
            FROM raw_messages 
            WHERE rx_time > strftime('%s', 'now', '-1 hour')
            GROUP BY message_type 
            ORDER BY count DESC
        ");
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($types)) {
            echo "<div class='error'>No message types found in last hour!</div>";
        } else {
            echo "<table>";
            echo "<tr><th>Message Type</th><th>Count (Last Hour)</th></tr>";
            foreach ($types as $type) {
                echo "<tr>";
                echo "<td>{$type['message_type']}</td>";
                echo "<td>{$type['count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>Query Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    
    <h2>Configuration</h2>
    <?php
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    echo "<tr><td>Cleanup Hours</td><td>" . \App\Support\Env::get('RAW_MESSAGE_CLEANUP_HOURS', '1') . "</td></tr>";
    echo "<tr><td>Database DSN</td><td>" . htmlspecialchars(\App\Support\Env::get('DB_DSN', 'Not set')) . "</td></tr>";
    echo "<tr><td>API URL</td><td>" . htmlspecialchars(\App\Support\Env::get('API_URL', 'Not set')) . "</td></tr>";
    echo "</table>";
    ?>
    
    <h2>Last 5 Decode Errors (if any)</h2>
    <?php
    try {
        $stmt = $pdo->query("
            SELECT id, topic, raw_message, datetime(rx_time, 'unixepoch') as rx_time_formatted
            FROM raw_messages 
            WHERE message_type = 'decode_error'
            ORDER BY rx_time DESC 
            LIMIT 5
        ");
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($errors)) {
            echo "<div class='info'>No decode errors found in database.</div>";
        } else {
            echo "<table>";
            echo "<tr><th>ID</th><th>Time</th><th>Topic</th><th>Raw Message Data</th></tr>";
            foreach ($errors as $error) {
                echo "<tr>";
                echo "<td>{$error['id']}</td>";
                echo "<td class='small'>{$error['rx_time_formatted']}</td>";
                echo "<td class='small'>" . htmlspecialchars($error['topic']) . "</td>";
                echo "<td class='small'>" . htmlspecialchars(substr($error['raw_message'], 0, 200)) . "...</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>Query Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    
    <div class='small' style='margin-top: 40px; color: #666;'>
        Debug script - Delete this file when done debugging
    </div>
</body>
</html>
