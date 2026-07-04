<?php
/**
 * Check Actual Node Data Storage
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Node Data Storage Investigation ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check what's actually in the nodes table
    echo "=== Nodes Table Contents ===\n";
    $nodes = $db->pdo()->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
    echo "Total nodes in database: $nodes\n";
    
    if ($nodes > 0) {
        $recent_nodes = $db->pdo()->query("
            SELECT id, short_name, long_name, hw_model, last_heard
            FROM nodes 
            ORDER BY last_heard DESC 
            LIMIT 10
        ")->fetchAll();
        
        echo "\nRecent nodes:\n";
        foreach ($recent_nodes as $node) {
            echo sprintf("Node %d: %s (%s) - %s - %s\n", 
                $node['id'],
                $node['short_name'],
                $node['long_name'],
                $node['hw_model'],
                $node['last_heard']
            );
        }
    }
    
    // Check what nodeinfo messages we actually captured
    echo "\n=== Raw Nodeinfo Message Analysis ===\n";
    $nodeinfo_messages = $db->pdo()->query("
        SELECT id, channel_id, topic, node_from, processed_at
        FROM raw_messages 
        WHERE message_type = 'nodeinfo'
        ORDER BY id DESC 
        LIMIT 10
    ")->fetchAll();
    
    echo "Recent nodeinfo messages in raw_messages:\n";
    foreach ($nodeinfo_messages as $msg) {
        $time = $msg['processed_at'] ? date('H:i:s', $msg['processed_at']) : 'unknown';
        echo sprintf("#%-5d %-12s from:%-10s %s %s\n", 
            $msg['id'],
            $msg['channel_id'],
            $msg['node_from'],
            $msg['topic'],
            $time
        );
    }
    
    // Check if the NodeInfoHandler is being called
    echo "\n=== Check NodeInfoHandler Processing ===\n";
    
    // Get a recent nodeinfo message and see what should happen
    if (!empty($nodeinfo_messages)) {
        $msg = $nodeinfo_messages[0];
        echo "Examining message #{$msg['id']}:\n";
        
        // Get the raw data
        $rawData = $db->pdo()->query("SELECT raw_message FROM raw_messages WHERE id = {$msg['id']}")->fetchColumn();
        
        if ($rawData && str_contains($msg['topic'], '/json/')) {
            echo "This is a JSON message, checking payload...\n";
            
            try {
                $data = json_decode($rawData, true);
                if (isset($data['payload'])) {
                    $payload = base64_decode($data['payload']);
                    echo "Payload length: " . strlen($payload) . " bytes\n";
                    echo "Payload hex: " . bin2hex($payload) . "\n";
                    
                    // Try to parse as nodeinfo
                    try {
                        $nodeInfo = new \Meshtastic\User();
                        $nodeInfo->mergeFromString($payload);
                        echo "Successfully parsed as User protobuf!\n";
                        echo "ID: " . $nodeInfo->getId() . "\n";
                        echo "Short name: " . $nodeInfo->getShortName() . "\n";
                        echo "Long name: " . $nodeInfo->getLongName() . "\n";
                        echo "HW Model: " . $nodeInfo->getHwModel() . "\n";
                    } catch (\Throwable $e) {
                        echo "Failed to parse as User: " . $e->getMessage() . "\n";
                    }
                }
            } catch (\Throwable $e) {
                echo "JSON parse error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Check if we have any entries in related tables
    echo "\n=== Related Tables Check ===\n";
    $positions = $db->pdo()->query("SELECT COUNT(*) FROM positions")->fetchColumn();
    $telemetry = $db->pdo()->query("SELECT COUNT(*) FROM telemetry")->fetchColumn();
    echo "Positions: $positions\n";
    echo "Telemetry: $telemetry\n";
    
    // Check recent positions to see if nodes are being linked
    if ($positions > 0) {
        echo "\nRecent positions:\n";
        $recent_pos = $db->pdo()->query("
            SELECT node_id, latitude, longitude, timestamp
            FROM positions 
            ORDER BY timestamp DESC 
            LIMIT 5
        ")->fetchAll();
        
        foreach ($recent_pos as $pos) {
            echo sprintf("Node %d: %.6f, %.6f at %s\n", 
                $pos['node_id'],
                $pos['latitude'],
                $pos['longitude'],
                $pos['timestamp']
            );
        }
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
