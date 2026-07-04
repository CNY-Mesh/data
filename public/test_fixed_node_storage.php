<?php
/**
 * Check Fixed Node Storage
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Testing Fixed Node Storage ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check current nodes
    echo "Nodes before fix: " . $db->pdo()->query("SELECT COUNT(*) FROM nodes")->fetchColumn() . "\n";
    
    // Check recent position data with correct column names
    echo "\nRecent positions (using correct schema):\n";
    $positions = $db->pdo()->query("
        SELECT node_num, lat, lon, time
        FROM positions 
        ORDER BY time DESC 
        LIMIT 5
    ")->fetchAll();
    
    foreach ($positions as $pos) {
        $time = $pos['time'] ? date('Y-m-d H:i:s', $pos['time']) : 'unknown';
        echo sprintf("Node %d: %.6f, %.6f at %s\n", 
            $pos['node_num'],
            $pos['lat'],
            $pos['lon'],
            $time
        );
    }
    
    // Wait a moment for new messages to process
    echo "\nWaiting for new nodeinfo messages to process...\n";
    sleep(2);
    
    // Check if any new nodes appeared
    $newNodes = $db->pdo()->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
    echo "Nodes after waiting: $newNodes\n";
    
    if ($newNodes > 0) {
        echo "\nNew nodes found:\n";
        $nodes = $db->pdo()->query("
            SELECT node_num, node_id, short_name, long_name, hardware, last_seen
            FROM nodes 
            ORDER BY last_seen DESC
        ")->fetchAll();
        
        foreach ($nodes as $node) {
            $time = $node['last_seen'] ? date('H:i:s', $node['last_seen']) : 'unknown';
            echo sprintf("Node %d (%s): %s (%s) - HW:%s - %s\n", 
                $node['node_num'],
                $node['node_id'],
                $node['short_name'],
                $node['long_name'],
                $node['hardware'],
                $time
            );
        }
    }
    
    // Check recent nodeinfo messages to see if they're being processed now
    echo "\nRecent nodeinfo activity:\n";
    $recent = $db->pdo()->query("
        SELECT COUNT(*) as count, MAX(processed_at) as latest
        FROM raw_messages 
        WHERE message_type = 'nodeinfo'
        AND processed_at > strftime('%s', 'now', '-5 minutes')
    ")->fetch();
    
    if ($recent['count'] > 0) {
        $latest = $recent['latest'] ? date('H:i:s', $recent['latest']) : 'unknown';
        echo "Found {$recent['count']} nodeinfo messages in last 5 minutes, latest at $latest\n";
    } else {
        echo "No recent nodeinfo messages found\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
