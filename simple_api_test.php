<?php
// Simple test script for production API using file_get_contents
echo "Testing production API at https://data.cnymesh.org/\n\n";

// Test data
$test_data = [
    'topic' => 'msh/US/2/json/LongFast/!test123',
    'timestamp' => time(),
    'json_data' => [
        'channel' => 0,
        'from' => 1128011160,
        'to' => 4294967295,
        'type' => 'position',
        'payload' => [
            'latitude_i' => 387809280,  // Syracuse area coordinates
            'longitude_i' => -905936896,
            'altitude' => 134,
            'time' => time()
        ],
        'rssi' => -95,
        'snr' => 7.25
    ]
];

function testWithFileGetContents($url, $data, $description) {
    echo "Testing: $description\n";
    echo "URL: $url\n";
    
    $json_data = json_encode($data);
    echo "Payload size: " . strlen($json_data) . " bytes\n";
    
    // Create context for POST request
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
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Request failed\n";
        $error = error_get_last();
        if ($error) {
            echo "Error: " . $error['message'] . "\n";
        }
        return false;
    }
    
    echo "Response: $response\n";
    
    $json_response = json_decode($response, true);
    if ($json_response && isset($json_response['success']) && $json_response['success']) {
        echo "✅ Success! Saved {$json_response['saved_count']} messages\n";
        if (!empty($json_response['errors'])) {
            echo "⚠️  Errors: " . implode(', ', $json_response['errors']) . "\n";
        }
        return true;
    } else {
        echo "❌ API returned error or unexpected response\n";
        return false;
    }
}

// Test the production API
$api_url = 'https://data.cnymesh.org/api?a=mesh_data';

echo "=" . str_repeat("=", 60) . "\n";
echo "Test: Single message to production API\n";
echo "=" . str_repeat("=", 60) . "\n";

$result = testWithFileGetContents($api_url, $test_data, "Position message test");

echo "\n" . str_repeat("=", 60) . "\n";
echo "RESULT: " . ($result ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "=" . str_repeat("=", 60) . "\n";

if ($result) {
    echo "\n🎉 API test passed! The production endpoint is working.\n";
    echo "Python MQTT monitor can send data to: $api_url\n";
} else {
    echo "\n⚠️  API test failed. Check the endpoint implementation.\n";
}

echo "\nTest completed at " . date('Y-m-d H:i:s') . "\n";
?>
