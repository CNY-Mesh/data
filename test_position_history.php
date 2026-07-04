<?php
/**
 * Test script for position history functionality
 * Upload this to the server and run at: https://data.cnymesh.org/test_position_history.php
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Support/Env.php';

use App\Database;
use App\Support\Env;

header('Content-Type: application/json');

try {
    // Load environment
    Env::load(__DIR__);
    
    // Create database connection
    $dbPath = __DIR__ . '/data/meshtastic.sqlite';
    $dsn = 'sqlite:' . $dbPath;
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    // Get OUR_NODES configuration
    $ourNodes = Env::get('OUR_NODES', '');
    $ourNodesArray = array_map('trim', explode(',', $ourNodes));
    
    // Check if position_history table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='position_history'");
    $tableExists = $stmt->fetch() !== false;
    
    // Get current positions for our nodes
    $ourNodePositions = [];
    foreach ($ourNodesArray as $nodeId) {
        if (empty($nodeId)) continue;
        
        // Try both hex and decimal formats
        $nodeNum = is_numeric($nodeId) ? (int)$nodeId : hexdec($nodeId);
        
        $stmt = $pdo->prepare("
            SELECT p.*, n.long_name, n.short_name,
                   DATETIME(p.time, 'unixepoch') as position_time
            FROM positions p 
            LEFT JOIN nodes n ON p.node_num = n.node_num 
            WHERE p.node_num = ?
        ");
        $stmt->execute([$nodeNum]);
        $position = $stmt->fetch();
        
        if ($position) {
            $ourNodePositions[] = $position;
        }
    }
    
    // Get position history count if table exists
    $historyCount = 0;
    $recentHistory = [];
    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM position_history");
        $historyCount = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("
            SELECT ph.*, n.long_name, n.short_name,
                   DATETIME(ph.time, 'unixepoch') as position_time,
                   DATETIME(ph.recorded_at, 'unixepoch') as recorded_time
            FROM position_history ph 
            LEFT JOIN nodes n ON ph.node_num = n.node_num 
            ORDER BY ph.recorded_at DESC 
            LIMIT 10
        ");
        $recentHistory = $stmt->fetchAll();
    }
    
    // Test API endpoint
    $apiUrl = "http://localhost/?r=api&a=position_history&limit=5";
    $apiResponse = null;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $apiResponse = json_decode($response, true);
    }
    
    $result = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'configuration' => [
            'our_nodes_env' => $ourNodes,
            'our_nodes_array' => $ourNodesArray,
            'tracked_node_count' => count($ourNodesArray)
        ],
        'database_status' => [
            'position_history_table_exists' => $tableExists,
            'position_history_record_count' => $historyCount
        ],
        'current_positions' => [
            'our_nodes_with_positions' => count($ourNodePositions),
            'positions' => $ourNodePositions
        ],
        'position_history' => [
            'recent_records' => $recentHistory
        ],
        'api_test' => [
            'url' => $apiUrl,
            'http_code' => $httpCode,
            'response_count' => is_array($apiResponse) ? count($apiResponse) : 0,
            'response' => $apiResponse
        ],
        'next_steps' => [
            'note' => 'Send a position update for one of your tracked nodes to test history storage',
            'tracked_nodes' => $ourNodesArray
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
