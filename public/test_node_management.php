<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "Testing Node Management functionality...\n";
echo str_repeat("=", 60) . "\n";

// Test current environment values
echo "Current environment values:\n";
echo "OUR_NODES: " . (Env::get('OUR_NODES', '') ?: 'Not set') . "\n";
echo "NODE_HISTORY_IDS: " . (Env::get('NODE_HISTORY_IDS', '') ?: 'Not set') . "\n";
echo "\n";

// Test database connection and node lookup
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

// Test node details lookup
function getNodeDetails($pdo, $nodeIds) {
    if (empty($nodeIds)) {
        return [];
    }

    $nodes = array_map('trim', explode(',', $nodeIds));
    $nodes = array_filter($nodes, function($node) {
        return !empty($node);
    });

    if (empty($nodes)) {
        return [];
    }

    $placeholders = str_repeat('?,', count($nodes) - 1) . '?';
    
    // Convert hex to decimal for database lookup
    $decimalNodes = [];
    foreach ($nodes as $node) {
        if (preg_match('/^[0-9a-fA-F]+$/', $node) && !preg_match('/^[0-9]+$/', $node)) {
            // Looks like hex, convert to decimal
            $decimalNodes[] = hexdec($node);
        } else {
            // Already decimal
            $decimalNodes[] = (int)$node;
        }
    }

    $stmt = $pdo->prepare("
        SELECT node_num, long_name, short_name, 
               DATETIME(last_seen, 'unixepoch') as last_seen_time,
               hardware
        FROM nodes 
        WHERE node_num IN ($placeholders)
        ORDER BY last_seen DESC
    ");
    $stmt->execute($decimalNodes);
    
    return $stmt->fetchAll();
}

// Test OUR_NODES lookup
$ourNodes = Env::get('OUR_NODES', '');
if (!empty($ourNodes)) {
    echo "OUR_NODES details:\n";
    echo str_repeat("-", 40) . "\n";
    $ourNodesDetails = getNodeDetails($pdo, $ourNodes);
    
    if (!empty($ourNodesDetails)) {
        foreach ($ourNodesDetails as $node) {
            printf("%-12s %-20s %-8s %s\n", 
                $node['node_num'] . ' (' . dechex($node['node_num']) . ')',
                $node['long_name'] ?: 'Unknown',
                $node['short_name'] ?: 'N/A',
                $node['last_seen_time'] ?: 'Never'
            );
        }
    } else {
        echo "No nodes found in database.\n";
    }
    echo "\n";
}

// Test NODE_HISTORY_IDS lookup
$historyNodes = Env::get('NODE_HISTORY_IDS', '');
if (!empty($historyNodes)) {
    echo "NODE_HISTORY_IDS details:\n";
    echo str_repeat("-", 40) . "\n";
    $historyNodesDetails = getNodeDetails($pdo, $historyNodes);
    
    if (!empty($historyNodesDetails)) {
        foreach ($historyNodesDetails as $node) {
            printf("%-12s %-20s %-8s %s\n", 
                $node['node_num'] . ' (' . dechex($node['node_num']) . ')',
                $node['long_name'] ?: 'Unknown',
                $node['short_name'] ?: 'N/A',
                $node['last_seen_time'] ?: 'Never'
            );
        }
    } else {
        echo "No nodes found in database.\n";
    }
    echo "\n";
}

// Test .env file access
$envFile = __DIR__ . '/../.env';
echo "Environment file test:\n";
echo str_repeat("-", 40) . "\n";
echo "File path: $envFile\n";
echo "File exists: " . (file_exists($envFile) ? 'Yes' : 'No') . "\n";
echo "File readable: " . (is_readable($envFile) ? 'Yes' : 'No') . "\n";
echo "File writable: " . (is_writable($envFile) ? 'Yes' : 'No') . "\n";

if (file_exists($envFile)) {
    $content = file_get_contents($envFile);
    echo "File size: " . strlen($content) . " bytes\n";
    
    // Check if our target lines exist
    $hasOurNodes = (strpos($content, 'OUR_NODES=') !== false);
    $hasHistoryIds = (strpos($content, 'NODE_HISTORY_IDS=') !== false);
    
    echo "Contains OUR_NODES: " . ($hasOurNodes ? 'Yes' : 'No') . "\n";
    echo "Contains NODE_HISTORY_IDS: " . ($hasHistoryIds ? 'Yes' : 'No') . "\n";
}

echo "\nNode Management page should be accessible at: /?r=node_management\n";
?>
