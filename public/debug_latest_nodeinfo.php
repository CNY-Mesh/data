<?php
/**
 * Debug Latest Nodeinfo Processing
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Latest Nodeinfo Processing Debug ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check the absolute latest nodeinfo messages
    echo "=== Latest Nodeinfo Messages ===\n";
    $latest = $db->pdo()->query("
        SELECT id, node_from, processed_at, raw_message
        FROM raw_messages 
        WHERE message_type = 'nodeinfo'
        AND topic LIKE '%/json/%'
        ORDER BY id DESC 
        LIMIT 3
    ")->fetchAll();
    
    foreach ($latest as $msg) {
        $time = $msg['processed_at'] ? date('H:i:s', $msg['processed_at']) : 'unknown';
        echo "\n--- Message #{$msg['id']} from node {$msg['node_from']} at $time ---\n";
        
        // Parse the JSON to see the structure
        $data = json_decode($msg['raw_message'], true);
        if ($data && isset($data['payload'])) {
            echo "Payload structure: " . json_encode($data['payload'], JSON_PRETTY_PRINT) . "\n";
            
            // Show what we would extract with our fixed code
            $payload = $data['payload'];
            $nodeId = $payload['id'] ?? 'missing';
            $shortName = $payload['shortname'] ?? 'missing';
            $longName = $payload['longname'] ?? 'missing';
            $hardware = $payload['hardware'] ?? 'missing';
            
            echo "Extracted values:\n";
            echo "  node_id: $nodeId\n";
            echo "  shortname: $shortName\n";
            echo "  longname: $longName\n";
            echo "  hardware: $hardware\n";
        }
    }
    
    // Check current node count
    echo "\n=== Current Node Count ===\n";
    $nodeCount = $db->pdo()->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
    echo "Total nodes in database: $nodeCount\n";
    
    if ($nodeCount > 0) {
        echo "\nNodes in database:\n";
        $nodes = $db->pdo()->query("
            SELECT node_num, node_id, short_name, long_name, hardware, last_seen
            FROM nodes 
            ORDER BY last_seen DESC
        ")->fetchAll();
        
        foreach ($nodes as $node) {
            $time = $node['last_seen'] ? date('H:i:s', $node['last_seen']) : 'unknown';
            echo sprintf("  Node %d (%s): %s (%s) - HW:%s - %s\n", 
                $node['node_num'],
                $node['node_id'],
                $node['short_name'],
                $node['long_name'],
                $node['hardware'],
                $time
            );
        }
    }
    
    // Check for very recent activity (last 2 minutes)
    echo "\n=== Very Recent Activity ===\n";
    $recentCount = $db->pdo()->query("
        SELECT COUNT(*) 
        FROM raw_messages 
        WHERE message_type = 'nodeinfo'
        AND processed_at > strftime('%s', 'now', '-2 minutes')
    ")->fetchColumn();
    
    echo "Nodeinfo messages in last 2 minutes: $recentCount\n";
    
    // Check latest message timestamp
    $latestTimestamp = $db->pdo()->query("
        SELECT MAX(processed_at) 
        FROM raw_messages
    ")->fetchColumn();
    
    if ($latestTimestamp) {
        $timeDiff = time() - $latestTimestamp;
        echo "Latest message was $timeDiff seconds ago\n";
        if ($timeDiff < 60) {
            echo "Worker is active!\n";
        } else {
            echo "Worker might be inactive (no recent messages)\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
