<?php
// Live API Test - Send actual POST request to mesh_data endpoint
// Accessible at: https://data.cnymesh.org/test_live_api.php

header('Content-Type: text/plain');
echo "CNY Mesh - Live API Test\n";
echo "========================\n\n";

// Test data
$test_data = [
    'messages' => [
        [
            'topic' => 'msh/US/2/json/LongFast/!testapi01',
            'timestamp' => time(),
            'json_data' => [
                'channel' => 0,
                'from' => 1999999999, // Test node ID
                'to' => 4294967295,
                'type' => 'position',
                'payload' => [
                    'latitude_i' => 387809280,  // Syracuse area
                    'longitude_i' => -905936896,
                    'altitude' => 134,
                    'time' => time()
                ],
                'rssi' => -95,
                'snr' => 7.25
            ]
        ]
    ]
];

$json_data = json_encode($test_data);

echo "Sending POST request to API endpoint...\n";
echo "URL: /api?a=mesh_data\n";
echo "Data: " . strlen($json_data) . " bytes\n";
echo "Messages: " . count($test_data['messages']) . "\n\n";

// Create context for internal request
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ],
        'content' => $json_data,
        'timeout' => 30
    ]
]);

// Get the current server info
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$api_url = $protocol . '://' . $host . '/api?a=mesh_data';

echo "Making request to: $api_url\n\n";

// Send the request
$response = @file_get_contents($api_url, false, $context);

if ($response === false) {
    $error = error_get_last();
    echo "❌ Request failed!\n";
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    
    // Try to access the API endpoint with GET to see if it exists
    echo "\nTrying GET request to check endpoint availability...\n";
    $get_response = @file_get_contents($protocol . '://' . $host . '/api', false, stream_context_create([
        'http' => ['timeout' => 10]
    ]));
    
    if ($get_response !== false) {
        echo "✓ API endpoint is accessible via GET\n";
        echo "Response preview: " . substr($get_response, 0, 200) . "\n";
    } else {
        echo "❌ Cannot access API endpoint\n";
    }
    
} else {
    echo "✓ Request successful!\n";
    echo "Response:\n";
    echo $response . "\n\n";
    
    // Try to decode JSON response
    $decoded = json_decode($response, true);
    if ($decoded !== null) {
        echo "Decoded response:\n";
        print_r($decoded);
        
        if (isset($decoded['success']) && $decoded['success']) {
            echo "\n✓ API test PASSED - Data was successfully processed!\n";
            echo "✓ Saved " . ($decoded['saved_count'] ?? 'unknown') . " messages\n";
        } else {
            echo "\n❌ API test FAILED\n";
            echo "Error: " . ($decoded['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "⚠ Response is not valid JSON\n";
        echo "This might indicate an error or unexpected response format\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
