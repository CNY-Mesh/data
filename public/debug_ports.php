<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

header('Content-Type: text/html; charset=utf-8');

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "<!DOCTYPE html><html><head><title>Port Number Debug</title></head><body>\n";
    echo "<h1>PORT NUMBER ANALYSIS</h1>\n\n";
    
    // Check what the raw data looks like
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               COUNT(CASE WHEN port_num = 0 THEN 1 END) as port_zero,
               COUNT(CASE WHEN port_num != 0 THEN 1 END) as port_nonzero,
               MIN(port_num) as min_port,
               MAX(port_num) as max_port
        FROM raw_messages 
        WHERE is_json = 0
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>\n";
    echo "<h2>Port Statistics (Non-JSON Messages)</h2>\n";
    echo "<p><strong>Total messages:</strong> {$stats['total']}</p>\n";
    echo "<p><strong>Port 0 (HEARTBEAT):</strong> {$stats['port_zero']}</p>\n";
    echo "<p><strong>Non-zero ports:</strong> {$stats['port_nonzero']}</p>\n";
    echo "<p><strong>Port range:</strong> {$stats['min_port']} to {$stats['max_port']}</p>\n";
    echo "</div>\n";
    
    if ($stats['port_nonzero'] > 0) {
        echo "<h2>Non-Zero Port Examples</h2>\n";
        $stmt = $pdo->prepare("
            SELECT port_num, COUNT(*) as count
            FROM raw_messages 
            WHERE is_json = 0 AND port_num != 0
            GROUP BY port_num 
            ORDER BY count DESC
        ");
        $stmt->execute();
        $portCounts = $stmt->fetchAll();
        
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Port</th><th>Count</th><th>App Type</th></tr>\n";
        
        $knownPorts = [
            1 => 'TEXT_MESSAGE_APP',
            3 => 'POSITION_APP', 
            4 => 'NODEINFO_APP',
            67 => 'TELEMETRY_APP',
            70 => 'TRACEROUTE_APP',
            71 => 'NEIGHBORINFO_APP',
            73 => 'MAP_REPORT_APP'
        ];
        
        foreach ($portCounts as $row) {
            $portName = $knownPorts[$row['port_num']] ?? 'UNKNOWN';
            echo "<tr><td>{$row['port_num']}</td><td>{$row['count']}</td><td>$portName</td></tr>\n";
        }
        echo "</table>\n";
    }
    
    // Check if we can correlate positions with raw messages by timestamp
    echo "<h2>Position Data Source Analysis</h2>\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as position_count,
               MIN(time) as earliest_position,
               MAX(time) as latest_position
        FROM positions
    ");
    $positionStats = $stmt->fetch();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as raw_count,
               MIN(created_at) as earliest_raw,
               MAX(created_at) as latest_raw
        FROM raw_messages
    ");
    $rawStats = $stmt->fetch();
    
    echo "<div style='background: #e8f5e8; padding: 10px; margin: 10px 0;'>\n";
    echo "<h3>Data Timeline Comparison</h3>\n";
    echo "<p><strong>Positions:</strong> {$positionStats['position_count']} records</p>\n";
    echo "<p><strong>Position timeframe:</strong> {$positionStats['earliest_position']} to {$positionStats['latest_position']}</p>\n";
    echo "<p><strong>Raw messages:</strong> {$rawStats['raw_count']} records</p>\n";
    echo "<p><strong>Raw timeframe:</strong> {$rawStats['earliest_raw']} to {$rawStats['latest_raw']}</p>\n";
    echo "</div>\n";
    
    // Check for time overlap - if positions are much older than raw messages,
    // it means positions came from a different source (older system)
    $positionTime = strtotime($positionStats['latest_position']);
    $rawTime = strtotime($rawStats['earliest_raw']);
    
    if (abs($positionTime - $rawTime) > 3600) { // More than 1 hour difference
        echo "<div style='background: #ffe8e8; padding: 10px; margin: 10px 0;'>\n";
        echo "<h3>⚠️ TIMING MISMATCH DETECTED</h3>\n";
        echo "<p>The position data and raw message data have significantly different timestamps.</p>\n";
        echo "<p>This suggests the position data came from an older system or different data source.</p>\n";
        echo "<p>The current raw message capture may not be processing position data correctly.</p>\n";
        echo "</div>\n";
    }
    
    // Show recent activity to see what's currently being captured
    echo "<h2>Recent Activity (Last 10 Raw Messages)</h2>\n";
    
    $stmt = $pdo->prepare("
        SELECT id, topic, port_num, payload_length, is_json, created_at
        FROM raw_messages 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>ID</th><th>Topic</th><th>Port</th><th>Payload Size</th><th>Type</th><th>Time</th></tr>\n";
    
    foreach ($recent as $row) {
        $type = $row['is_json'] ? 'JSON' : 'Binary';
        echo "<tr>\n";
        echo "<td>{$row['id']}</td>\n";
        echo "<td>" . substr($row['topic'], 0, 40) . "...</td>\n";
        echo "<td>{$row['port_num']}</td>\n";
        echo "<td>{$row['payload_length']} bytes</td>\n";
        echo "<td>$type</td>\n";
        echo "<td>{$row['created_at']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "</body></html>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
