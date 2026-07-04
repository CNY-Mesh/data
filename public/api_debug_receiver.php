<?php
/**
 * Test what the API actually receives
 * Run from: https://data.cnymesh.org/api_debug_receiver.php
 */

// Include required files to use the database
require_once '../bootstrap.php';

echo "<h1>API Debug Receiver</h1>\n";
echo "<style>
.error { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
.info { color: blue; font-weight: bold; }
.json { font-family: monospace; font-size: 0.9em; background: #f8f8f8; padding: 10px; margin: 10px 0; white-space: pre-wrap; border: 1px solid #ccc; }
</style>\n";

// Show what this request looks like
echo "<h2>This Request (to api_debug_receiver.php)</h2>\n";
echo "<p class='info'>REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'undefined') . "</p>\n";
echo "<p class='info'>QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'undefined') . "</p>\n";
echo "<p class='info'>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'undefined') . "</p>\n";

// Now test what happens when we manually invoke the API controller
echo "<h2>Manual API Controller Test</h2>\n";

try {
    // Create an API controller instance
    $api = new App\Web\Controllers\ApiController();
    
    // Test with simulated POST
    echo "<h3>Simulating POST Request</h3>\n";
    
    // Temporarily override REQUEST_METHOD
    $original_method = $_SERVER['REQUEST_METHOD'] ?? null;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['a'] = 'mesh_data';
    
    // Create test input data
    $test_data = [
        'messages' => [
            [
                'topic' => 'test/manual',
                'timestamp' => time(),
                'json_data' => [
                    'from' => 999999996,
                    'to' => 999999995,
                    'type' => 'text',
                    'payload' => [
                        'text' => 'Manual API test - ' . date('H:i:s')
                    ]
                ]
            ]
        ]
    ];
    
    // Simulate the POST input by writing to a temporary stream
    $temp_input = tmpfile();
    fwrite($temp_input, json_encode($test_data));
    rewind($temp_input);
    
    // We can't override php://input easily, so let's just test the structure
    echo "<p>Test data prepared:</p>\n";
    echo "<div class='json'>" . htmlspecialchars(json_encode($test_data, JSON_PRETTY_PRINT)) . "</div>\n";
    
    // Restore original method
    if ($original_method !== null) {
        $_SERVER['REQUEST_METHOD'] = $original_method;
    } else {
        unset($_SERVER['REQUEST_METHOD']);
    }
    
    echo "<p class='info'>Cannot easily test POST input without modifying core functions</p>\n";
    
} catch (Exception $e) {
    echo "<p class='error'>Error testing API: {$e->getMessage()}</p>\n";
}

// Test direct database operation to ensure our text fix works
echo "<h2>Test Direct Database Insert</h2>\n";

try {
    $db = new App\Database('sqlite:../data/meshtastic.sqlite');
    $pdo = $db->pdo();
    
    // Test inserting a text message directly
    $stmt = $pdo->prepare("
        INSERT INTO text_messages (node_from, node_to, message, rx_time) 
        VALUES (?, ?, ?, ?)
    ");
    
    $test_node_from = 999999994;
    $test_node_to = 999999993;
    $test_message = 'Direct database test - ' . date('H:i:s');
    $test_time = time();
    
    $stmt->execute([$test_node_from, $test_node_to, $test_message, $test_time]);
    
    echo "<p class='success'>Direct database insert successful!</p>\n";
    echo "<p>Inserted message: '{$test_message}' from {$test_node_from} to {$test_node_to}</p>\n";
    
    // Check if it was inserted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM text_messages WHERE node_from = ? AND message = ?");
    $stmt->execute([$test_node_from, $test_message]);
    $count = $stmt->fetchColumn();
    
    echo "<p>Records matching our test: {$count}</p>\n";
    
} catch (Exception $e) {
    echo "<p class='error'>Database test error: {$e->getMessage()}</p>\n";
}

echo "<p><em>Debug completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
