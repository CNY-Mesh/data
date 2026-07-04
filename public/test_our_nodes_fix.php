<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "Testing OurNodesController node ID logic...\n";
echo str_repeat("=", 60) . "\n";

// Test the environment variable parsing
$ourNodesStr = Env::get('OUR_NODES', '');
echo "OUR_NODES from environment: '$ourNodesStr'\n";

$nodeIds = array_filter(array_map('trim', explode(',', $ourNodesStr)));
echo "Parsed node IDs: " . implode(', ', $nodeIds) . "\n";

// Convert hex to decimal like the controller does
$decimalNodeIds = [];
foreach ($nodeIds as $nodeId) {
    $nodeId = trim($nodeId);
    if (empty($nodeId)) continue;
    
    // Check if it's hex (contains letters) or already decimal
    if (preg_match('/^[0-9a-fA-F]+$/', $nodeId) && preg_match('/[a-fA-F]/', $nodeId)) {
        // It's hex, convert to decimal
        $decimal = hexdec($nodeId);
        $decimalNodeIds[] = $decimal;
        echo "Converted hex '$nodeId' to decimal '$decimal'\n";
    } else {
        // It's already decimal
        $decimal = (int)$nodeId;
        $decimalNodeIds[] = $decimal;
        echo "Using decimal '$nodeId' as '$decimal'\n";
    }
}

echo "\nFinal decimal node IDs: " . implode(', ', $decimalNodeIds) . "\n";

// Test database lookup
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

if (!empty($decimalNodeIds)) {
    $placeholders = str_repeat('?,', count($decimalNodeIds) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT node_num, long_name, short_name, hardware, last_seen
        FROM nodes 
        WHERE node_num IN ($placeholders)
        ORDER BY last_seen DESC
    ");
    
    $stmt->execute($decimalNodeIds);
    $results = $stmt->fetchAll();
    
    echo "\nDatabase query results:\n";
    echo str_repeat("-", 60) . "\n";
    
    if (!empty($results)) {
        printf("%-12s %-20s %-12s %-20s\n", "Node ID", "Long Name", "Short Name", "Last Seen");
        echo str_repeat("-", 60) . "\n";
        
        foreach ($results as $node) {
            printf("%-12s %-20s %-12s %-20s\n", 
                $node['node_num'],
                substr($node['long_name'] ?: 'N/A', 0, 19),
                $node['short_name'] ?: 'N/A',
                $node['last_seen'] ? date('Y-m-d H:i:s', $node['last_seen']) : 'Never'
            );
        }
        
        echo "\nTotal unique nodes found: " . count($results) . "\n";
    } else {
        echo "No nodes found in database matching the IDs.\n";
    }
} else {
    echo "\nNo valid node IDs to search for.\n";
}

echo "\nThis should eliminate duplicate results on AJAX refresh.\n";
?>
