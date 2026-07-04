<?php
// Test script for production API endpoint
echo "Testing production API at https://data.cnymesh.org/\n\n";

// Test data simulating what the Python script would send
$test_data = [
    'messages' => [
        [
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
        ],
        [
            'topic' => 'msh/US/2/e/LongFast/!test456',
            'timestamp' => time(),
            'decoded_packet' => [
                'from' => 1146447400,
                'to' => 4294967295,
                'decoded' => [
                    'portnum' => 67,
                    'portnum_name' => 'TELEMETRY_APP',
                    'telemetry' => [
                        'battery_level' => 85,
                        'voltage' => 4.12,
                        'channel_utilization' => 12.5,
                        'air_util_tx' => 0.8
                    ]
                ]
            ]
        ],
        [
            'topic' => 'msh/US/2/json/LongFast/!test789',
            'timestamp' => time(),
            'json_data' => [
                'channel' => 0,
                'from' => 999888777,
                'to' => 4294967295,
                'type' => 'nodeinfo',
                'payload' => [
                    'shortname' => 'TestNode',
                    'longname' => 'Test Node for API',
                    'macaddr' => 'AA:BB:CC:DD:EE:FF',
                    'hw_model' => 'TBEAM'
                ],
                'rssi' => -88,
                'snr' => 9.5
            ]
        ]
    ]
];

// Test single message format too
$single_message_data = [
    'topic' => 'msh/US/2/json/LongFast/!single123',
    'timestamp' => time(),
    'json_data' => [
        'channel' => 0,
        'from' => 555666777,
        'to' => 4294967295,
        'type' => 'text',
        'payload' => [
            'text' => 'Hello from API test!'
        ],
        'rssi' => -92,
        'snr' => 6.0
    ]
];

function testApiEndpoint($url, $data, $description) {
    echo "Testing: $description\n";
    echo "URL: $url\n";
    
    $json_data = json_encode($data);
    echo "Payload size: " . strlen($json_data) . " bytes\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ cURL Error: $error\n";
        return false;
    }
    
    echo "HTTP Status: $http_code\n";
    echo "Response: $response\n";
    
    if ($http_code === 200) {
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
    } else {
        echo "❌ HTTP Error: $http_code\n";
        return false;
    }
}

// Test the production API
$api_url = 'https://data.cnymesh.org/api?a=mesh_data';

echo "=" . str_repeat("=", 60) . "\n";
echo "Test 1: Multiple messages (batch format)\n";
echo "=" . str_repeat("=", 60) . "\n";
$result1 = testApiEndpoint($api_url, $test_data, "Batch message test");

echo "\n" . str_repeat("-", 60) . "\n\n";

echo "=" . str_repeat("=", 60) . "\n";
echo "Test 2: Single message format\n";
echo "=" . str_repeat("=", 60) . "\n";
$result2 = testApiEndpoint($api_url, $single_message_data, "Single message test");

echo "\n" . str_repeat("=", 60) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "Batch test: " . ($result1 ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "Single test: " . ($result2 ? "✅ PASSED" : "❌ FAILED") . "\n";

if ($result1 && $result2) {
    echo "\n🎉 All tests passed! The API is working correctly.\n";
    echo "Your Python MQTT monitor can now send data to:\n";
    echo "  $api_url\n";
} else {
    echo "\n⚠️  Some tests failed. Check the API implementation.\n";
}

echo "\nTest completed at " . date('Y-m-d H:i:s') . "\n";
?>
