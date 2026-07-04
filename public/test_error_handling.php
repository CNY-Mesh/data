<?php
/**
 * Test API error handling with malformed data
 * Run from: https://data.cnymesh.org/test_error_handling.php
 */

// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

echo "<h1>Test API Error Handling</h1>\n";
echo "<style>
.error { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.json { font-family: monospace; font-size: 0.9em; background: #f8f8f8; padding: 10px; margin: 10px 0; white-space: pre-wrap; border: 1px solid #ccc; }
</style>\n";

// Test 1: Valid message (should work)
echo "<h2>Test 1: Valid Message</h2>\n";
$valid_payload = [
    'messages' => [
        [
            'topic' => 'test/valid',
            'timestamp' => time(),
            'json_data' => [
                'from' => 999999991,
                'to' => 999999992,
                'type' => 'text',
                'payload' => [
                    'text' => 'Valid test message'
                ]
            ]
        ]
    ]
];

$response = testApiCall($valid_payload);
echo "<p>Response: <span class='" . ($response['success'] ? 'success' : 'error') . "'>" . 
     ($response['success'] ? 'SUCCESS' : 'FAILED') . "</span></p>\n";
echo "<div class='json'>" . htmlspecialchars($response['body']) . "</div>\n";

// Test 2: Message with invalid json_data (should be handled gracefully)
echo "<h2>Test 2: Invalid json_data Type</h2>\n";
$invalid_payload = [
    'messages' => [
        [
            'topic' => 'test/invalid',
            'timestamp' => time(),
            'json_data' => 12.34  // This should be an array, not a float
        ]
    ]
];

$response = testApiCall($invalid_payload);
echo "<p>Response: <span class='" . ($response['success'] ? 'success' : 'warning') . "'>" . 
     ($response['success'] ? 'HANDLED' : 'FAILED') . "</span></p>\n";
echo "<div class='json'>" . htmlspecialchars($response['body']) . "</div>\n";

// Test 3: Message with invalid payload (should be handled gracefully)
echo "<h2>Test 3: Invalid Payload Type</h2>\n";
$invalid_payload2 = [
    'messages' => [
        [
            'topic' => 'test/invalid2',
            'timestamp' => time(),
            'json_data' => [
                'from' => 999999993,
                'to' => 999999994,
                'type' => 'text',
                'payload' => 'this should be an array'  // Invalid payload type
            ]
        ]
    ]
];

$response = testApiCall($invalid_payload2);
echo "<p>Response: <span class='" . ($response['success'] ? 'success' : 'warning') . "'>" . 
     ($response['success'] ? 'HANDLED' : 'FAILED') . "</span></p>\n";
echo "<div class='json'>" . htmlspecialchars($response['body']) . "</div>\n";

// Test 4: Non-array message
echo "<h2>Test 4: Non-Array Message</h2>\n";
$invalid_payload3 = [
    'messages' => [
        'this should be an array'  // Invalid message type
    ]
];

$response = testApiCall($invalid_payload3);
echo "<p>Response: <span class='" . ($response['success'] ? 'success' : 'warning') . "'>" . 
     ($response['success'] ? 'HANDLED' : 'FAILED') . "</span></p>\n";
echo "<div class='json'>" . htmlspecialchars($response['body']) . "</div>\n";

function testApiCall($payload) {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload)
        ]
    ]);
    
    $response = file_get_contents('https://data.cnymesh.org/?r=api&a=mesh_data', false, $context);
    
    if ($response === false) {
        return ['success' => false, 'body' => 'API call failed'];
    }
    
    $data = json_decode($response, true);
    $success = $data && isset($data['success']) && $data['success'];
    
    return ['success' => $success, 'body' => $response];
}

echo "<p><em>Error handling test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
