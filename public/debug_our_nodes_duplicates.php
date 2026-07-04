<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "Debugging OUR_NODES duplicates issue...\n";
echo str_repeat("=", 60) . "\n";

// Get the exact environment variable
$ourNodesStr = Env::get('OUR_NODES', '');
echo "Raw OUR_NODES: '$ourNodesStr'\n";

// Parse it exactly like the controller does
$nodeIds = array_filter(array_map('trim', explode(',', $ourNodesStr)));
echo "Parsed node IDs (" . count($nodeIds) . "): " . implode(', ', $nodeIds) . "\n";

// Check for duplicates in the source
$uniqueNodeIds = array_unique($nodeIds);
if (count($nodeIds) !== count($uniqueNodeIds)) {
    echo "WARNING: Duplicate node IDs in OUR_NODES environment variable!\n";
    $duplicates = array_diff_assoc($nodeIds, $uniqueNodeIds);
    echo "Duplicates: " . implode(', ', $duplicates) . "\n";
}

// Convert to decimal like the controller
$decimalNodeIds = [];
foreach ($nodeIds as $nodeId) {
    $nodeId = trim($nodeId);
    if (empty($nodeId)) continue;
    
    if (preg_match('/^[0-9a-fA-F]+$/', $nodeId) && preg_match('/[a-fA-F]/', $nodeId)) {
        $decimal = hexdec($nodeId);
        $decimalNodeIds[] = $decimal;
        echo "Hex '$nodeId' -> Decimal '$decimal'\n";
    } else {
        $decimal = (int)$nodeId;
        $decimalNodeIds[] = $decimal;
        echo "Decimal '$nodeId' -> '$decimal'\n";
    }
}

echo "Final decimal IDs (" . count($decimalNodeIds) . "): " . implode(', ', $decimalNodeIds) . "\n";

// Check for duplicates after conversion
$uniqueDecimalIds = array_unique($decimalNodeIds);
if (count($decimalNodeIds) !== count($uniqueDecimalIds)) {
    echo "WARNING: Duplicate decimal IDs after conversion!\n";
}

// Test the database query
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

if (!empty($decimalNodeIds)) {
    $placeholders = str_repeat('?,', count($decimalNodeIds) - 1) . '?';
    
    echo "\nSQL Query: SELECT DISTINCT node_num, long_name, short_name FROM nodes WHERE node_num IN ($placeholders)\n";
    echo "Parameters: " . implode(', ', $decimalNodeIds) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT node_num, long_name, short_name, hardware, last_seen
        FROM nodes 
        WHERE node_num IN ($placeholders)
        ORDER BY last_seen DESC
    ");
    
    $stmt->execute($decimalNodeIds);
    $results = $stmt->fetchAll();
    
    echo "\nDatabase results (" . count($results) . " rows):\n";
    echo str_repeat("-", 60) . "\n";
    
    $foundNodeNums = [];
    foreach ($results as $result) {
        echo "Node: {$result['node_num']}, Name: {$result['long_name']}, Short: {$result['short_name']}\n";
        $foundNodeNums[] = $result['node_num'];
    }
    
    // Check for duplicates in results
    $uniqueFoundIds = array_unique($foundNodeNums);
    if (count($foundNodeNums) !== count($uniqueFoundIds)) {
        echo "WARNING: Database returned duplicate node_num values!\n";
        $resultDuplicates = array_diff_assoc($foundNodeNums, $uniqueFoundIds);
        echo "Duplicate node_nums in results: " . implode(', ', $resultDuplicates) . "\n";
    } else {
        echo "✓ No duplicates in database results\n";
    }
}

echo "\nIf this shows no duplicates, the issue might be in the frontend JavaScript or AJAX handling.\n";
?>
