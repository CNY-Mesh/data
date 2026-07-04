<?php

require_once __DIR__ . '/bootstrap.php';

use App\Database;

try {
    $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "=== MESHTASTIC DATA ANALYSIS ===\n\n";
    
    // Check raw_messages table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM raw_messages");
    $rawCount = $stmt->fetch()['count'];
    echo "Raw messages captured: $rawCount\n";
    
    if ($rawCount > 0) {
        // Port number breakdown
        echo "\n--- PORT NUMBER BREAKDOWN ---\n";
        $stmt = $pdo->query("
            SELECT port_num, COUNT(*) as count, 
                   AVG(payload_length) as avg_payload_len,
                   SUM(CASE WHEN payload_length > 0 THEN 1 ELSE 0 END) as with_payload
            FROM raw_messages 
            WHERE is_json = 0 
            GROUP BY port_num 
            ORDER BY count DESC
        ");
        
        $knownPorts = [
            0 => 'HEARTBEAT',
            1 => 'TEXT_MESSAGE_APP',
            3 => 'POSITION_APP',
            4 => 'NODEINFO_APP',
            32 => 'REPLY_APP',
            33 => 'IP_TUNNEL_APP',
            64 => 'SERIAL_APP',
            65 => 'STORE_FORWARD_APP',
            66 => 'RANGE_TEST_APP',
            67 => 'TELEMETRY_APP',
            70 => 'TRACEROUTE_APP',
            71 => 'NEIGHBORINFO_APP',
            72 => 'ATAK_PLUGIN_APP',
            73 => 'MAP_REPORT_APP',
            256 => 'PRIVATE_APP',
            257 => 'ATAK_FORWARDER_APP'
        ];
        
        while ($row = $stmt->fetch()) {
            $port = $row['port_num'];
            $portName = $knownPorts[$port] ?? 'UNKNOWN';
            $count = $row['count'];
            $withPayload = $row['with_payload'];
            $avgLen = round($row['avg_payload_len'], 1);
            
            echo sprintf(
                "Port %3s: %-20s | %5d total, %5d with data (avg: %s bytes)\n",
                $port ?? 'NULL',
                $portName,
                $count,
                $withPayload,
                $avgLen
            );
        }
        
        // Channel breakdown
        echo "\n--- CHANNEL BREAKDOWN ---\n";
        $stmt = $pdo->query("
            SELECT channel_id, COUNT(*) as count
            FROM raw_messages 
            WHERE channel_id IS NOT NULL AND channel_id != ''
            GROUP BY channel_id 
            ORDER BY count DESC 
            LIMIT 20
        ");
        
        while ($row = $stmt->fetch()) {
            echo sprintf("%-15s: %d messages\n", $row['channel_id'], $row['count']);
        }
        
        // JSON message types
        echo "\n--- JSON MESSAGE TYPES ---\n";
        $stmt = $pdo->query("
            SELECT message_type, COUNT(*) as count
            FROM raw_messages 
            WHERE is_json = 1
            GROUP BY message_type 
            ORDER BY count DESC
        ");
        
        while ($row = $stmt->fetch()) {
            echo sprintf("%-15s: %d messages\n", $row['message_type'] ?? 'NULL', $row['count']);
        }
    }
    
    // Check other tables
    $tables = [
        'positions' => 'position data',
        'telemetry' => 'telemetry data', 
        'text_messages' => 'text messages',
        'nodes' => 'node info',
        'neighbors' => 'neighbor reports',
        'traceroutes' => 'traceroute data',
        'map_reports' => 'map reports'
    ];
    
    echo "\n--- STRUCTURED DATA TABLES ---\n";
    foreach ($tables as $table => $description) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo sprintf("%-15s: %5d %s\n", $table, $count, $description);
        } catch (Exception $e) {
            echo sprintf("%-15s: Table not found\n", $table);
        }
    }
    
    // Recent activity
    echo "\n--- RECENT ACTIVITY (last 10 minutes) ---\n";
    $recentTime = time() - 600; // 10 minutes ago
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, 
               COUNT(DISTINCT node_from) as unique_nodes
        FROM raw_messages 
        WHERE processed_at > ?
    ");
    $stmt->execute([$recentTime]);
    $recent = $stmt->fetch();
    echo "Messages: {$recent['count']}, Unique nodes: {$recent['unique_nodes']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
