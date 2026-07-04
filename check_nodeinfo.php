<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "=== NODE INFO PROBLEM ANALYSIS ===\n\n";
    
    // Show port breakdown
    $stmt = $pdo->query("
        SELECT port_num, COUNT(*) as count, 
               SUM(CASE WHEN payload_length > 0 THEN 1 ELSE 0 END) as with_payload,
               AVG(payload_length) as avg_payload_len
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
    
    echo "Port breakdown:\n";
    while ($row = $stmt->fetch()) {
        $port = $row['port_num'];
        $portName = $knownPorts[$port] ?? 'UNKNOWN';
        $hasHandler = in_array($port, [1, 3, 4, 67, 70, 71, 73]) ? 'YES' : 'NO';
        
        printf(
            "Port %3s: %-20s | %5d total, %5d with data | Handler: %s\n",
            $port ?? 'NULL',
            $portName,
            $row['count'],
            $row['with_payload'],
            $hasHandler
        );
    }
    
    echo "\n=== SPECIFIC PORT 4 (NODEINFO) ANALYSIS ===\n";
    
    // Check port 4 specifically
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, 
               SUM(CASE WHEN payload_length > 0 THEN 1 ELSE 0 END) as with_payload,
               MIN(payload_length) as min_len,
               MAX(payload_length) as max_len,
               AVG(payload_length) as avg_len
        FROM raw_messages 
        WHERE port_num = 4
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result && $result['count'] > 0) {
        echo "Port 4 (NODEINFO_APP) messages: {$result['count']}\n";
        echo "With payload: {$result['with_payload']}\n";
        echo "Payload lengths: min={$result['min_len']}, max={$result['max_len']}, avg=" . round($result['avg_len'], 1) . "\n";
        
        // Show a sample port 4 message
        $stmt = $pdo->prepare("
            SELECT node_from, payload_hex, payload_length 
            FROM raw_messages 
            WHERE port_num = 4 AND payload_length > 0 
            LIMIT 1
        ");
        $stmt->execute();
        $sample = $stmt->fetch();
        
        if ($sample) {
            echo "\nSample port 4 message:\n";
            echo "  Node from: {$sample['node_from']} (!" . base_convert($sample['node_from'], 10, 16) . ")\n";
            echo "  Payload length: {$sample['payload_length']} bytes\n";
            echo "  Payload hex: " . substr($sample['payload_hex'], 0, 64) . "...\n";
        }
    } else {
        echo "No port 4 (NODEINFO_APP) messages found!\n";
        echo "This explains why no nodes are being recorded.\n";
        echo "Node info messages are sent less frequently than positions.\n";
    }
    
    echo "\n=== JSON MESSAGE ANALYSIS ===\n";
    
    // Check JSON messages for nodeinfo
    $stmt = $pdo->query("
        SELECT message_type, COUNT(*) as count 
        FROM raw_messages 
        WHERE is_json = 1 
        GROUP BY message_type 
        ORDER BY count DESC
    ");
    
    $jsonResults = $stmt->fetchAll();
    if (!empty($jsonResults)) {
        echo "JSON message types found:\n";
        foreach ($jsonResults as $row) {
            echo "  {$row['message_type']}: {$row['count']} messages\n";
        }
        
        // Check for nodeinfo in JSON
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM raw_messages 
            WHERE is_json = 1 AND message_type = 'nodeinfo'
        ");
        $stmt->execute();
        $nodeInfoJson = $stmt->fetch()['count'];
        
        if ($nodeInfoJson > 0) {
            echo "\nFound {$nodeInfoJson} JSON nodeinfo messages!\n";
            echo "These should be processed by the JSON handler.\n";
        }
    } else {
        echo "No JSON messages found.\n";
    }
    
    echo "\n=== POSITION TO NODE MAPPING ===\n";
    
    // Show which nodes we have positions for but no node info
    $stmt = $pdo->query("
        SELECT p.node_num, COUNT(*) as position_count
        FROM positions p
        LEFT JOIN nodes n ON p.node_num = n.node_num
        WHERE n.node_num IS NULL
        GROUP BY p.node_num
        ORDER BY position_count DESC
        LIMIT 10
    ");
    
    $orphanedPositions = $stmt->fetchAll();
    if (!empty($orphanedPositions)) {
        echo "Nodes with positions but no node info:\n";
        foreach ($orphanedPositions as $row) {
            $nodeHex = base_convert($row['node_num'], 10, 16);
            echo "  !{$nodeHex}: {$row['position_count']} position updates\n";
        }
        echo "\nThese positions won't show on the map without node info.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
