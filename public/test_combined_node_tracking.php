<?php
/**
 * Test script to verify combined node tracking (OUR_NODES + NODE_HISTORY_IDS)
 * Access at: https://data.cnymesh.org/test_combined_node_tracking.php
 */

require_once __DIR__ . '/../src/Support/Env.php';

use App\Support\Env;

header('Content-Type: application/json');

try {
    // Load environment
    Env::load(__DIR__ . '/..');
    
    // Get both node lists
    $ourNodes = Env::get('OUR_NODES', '');
    $historyNodes = Env::get('NODE_HISTORY_IDS', '');
    
    // Parse OUR_NODES
    $ourNodesArray = [];
    if (!empty($ourNodes)) {
        $ourNodesArray = array_map('trim', explode(',', $ourNodes));
    }
    
    // Parse NODE_HISTORY_IDS
    $historyNodesArray = [];
    if (!empty($historyNodes)) {
        $historyNodesArray = array_map('trim', explode(',', $historyNodes));
    }
    
    // Combine and deduplicate (same logic as ApiController)
    $allTrackedNodes = [];
    $allTrackedNodes = array_merge($allTrackedNodes, $ourNodesArray);
    $allTrackedNodes = array_merge($allTrackedNodes, $historyNodesArray);
    $allTrackedNodes = array_unique(array_filter($allTrackedNodes, function($node) {
        return !empty(trim($node));
    }));
    
    // Convert hex IDs to decimal for reference
    $nodeDetails = [];
    foreach ($allTrackedNodes as $nodeId) {
        $nodeDetails[] = [
            'id' => $nodeId,
            'decimal' => is_numeric($nodeId) ? (int)$nodeId : hexdec($nodeId),
            'hex' => is_numeric($nodeId) ? dechex((int)$nodeId) : $nodeId,
            'source' => []
        ];
    }
    
    // Mark sources for each node
    foreach ($nodeDetails as &$detail) {
        if (in_array($detail['id'], $ourNodesArray)) {
            $detail['source'][] = 'OUR_NODES';
        }
        if (in_array($detail['id'], $historyNodesArray)) {
            $detail['source'][] = 'NODE_HISTORY_IDS';
        }
    }
    
    $result = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'configuration' => [
            'our_nodes_env' => $ourNodes,
            'node_history_ids_env' => $historyNodes
        ],
        'parsed_lists' => [
            'our_nodes' => $ourNodesArray,
            'node_history_ids' => $historyNodesArray
        ],
        'combined_tracking' => [
            'total_tracked_nodes' => count($allTrackedNodes),
            'tracked_nodes' => $allTrackedNodes,
            'node_details' => $nodeDetails
        ],
        'verification' => [
            'duplicates_removed' => (count($ourNodesArray) + count($historyNodesArray)) - count($allTrackedNodes),
            'total_before_dedup' => count($ourNodesArray) + count($historyNodesArray),
            'total_after_dedup' => count($allTrackedNodes)
        ],
        'next_steps' => [
            'note' => 'Position history will now be tracked for all nodes in the combined list',
            'api_behavior' => 'ApiController.getOurNodes() now returns this combined list',
            'node_page_behavior' => 'NodeController now uses combined list to determine isTrackedNode status'
        ]
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
