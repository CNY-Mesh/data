<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Support\Env;
use App\Database;

// Simple test to replicate the OurNodesController logic
echo "Testing Our Nodes AJAX logic...\n";
echo "Content-Type: application/json\n\n";

// Simulate the exact same logic as OurNodesController
$ourNodesStr = Env::get('OUR_NODES', '');
$nodeIds = array_filter(array_map('trim', explode(',', $ourNodesStr)));

echo "Node IDs from env: " . json_encode($nodeIds) . "\n";

// Convert to decimal
$decimalNodeIds = [];
foreach ($nodeIds as $nodeId) {
    $nodeId = trim($nodeId);
    if (empty($nodeId)) continue;
    
    if (preg_match('/^[0-9a-fA-F]+$/', $nodeId) && preg_match('/[a-fA-F]/', $nodeId)) {
        $decimalNodeIds[] = hexdec($nodeId);
    } else {
        $decimalNodeIds[] = (int)$nodeId;
    }
}

echo "Decimal node IDs: " . json_encode($decimalNodeIds) . "\n";

// Query database
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
    $ourNodes = $stmt->fetchAll();
    
    echo "Database results: " . json_encode(array_column($ourNodes, 'node_num')) . "\n";
    
    // Test the enhancement loop
    $enhancedNodes = [];
    foreach ($ourNodes as $index => $node) {
        echo "Processing index $index: node_num=" . $node['node_num'] . "\n";
        
        $enhancedNode = $node;
        $enhancedNode['position'] = null; // Skip actual position lookup for this test
        $enhancedNode['telemetry'] = null;
        $enhancedNode['recent_messages'] = [];
        $enhancedNode['last_seen_ago'] = 'test';
        
        $enhancedNodes[] = $enhancedNode;
    }
    
    echo "Enhanced results: " . json_encode(array_column($enhancedNodes, 'node_num')) . "\n";
    echo "Final count: " . count($enhancedNodes) . "\n";
    
    // Check for duplicates at each step
    $originalIds = array_column($ourNodes, 'node_num');
    $enhancedIds = array_column($enhancedNodes, 'node_num');
    
    echo "Original unique: " . (count($originalIds) === count(array_unique($originalIds)) ? 'YES' : 'NO') . "\n";
    echo "Enhanced unique: " . (count($enhancedIds) === count(array_unique($enhancedIds)) ? 'YES' : 'NO') . "\n";
}
?>
