<?php
// Test the production API endpoint at https://data.cnymesh.org/

echo "Testing production API at https://data.cnymesh.org/\n\n";

// Test data simulating what the Python script would send
$test_data = [
    'messages' => [
        [
            'topic' => 'msh/US/2/json/LongFast/!test001',
            'timestamp' => time(),
            'json_data' => [
                'channel' => 0,
                'from' => 1128011160,
                'to' => 4294967295,
                'type' => 'position',
                'payload' => [
                    'latitude_i' => 387809280,  // ~38.7809 degrees (Syracuse area)
                    'longitude_i' => -905936896, // ~-90.5937 degrees
                    'altitude' => 134,
                    'time' => time()
                ],
                'rssi' => -95,
                'snr' => 7.25
            ]
        ],
        [
            'topic' => 'msh/US/2/e/LongFast/!test002',
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
        ]
    ]
];

$json_data = json_encode($test_data);

// Create HTTP context for POST request
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data),
            'User-Agent: CNY-Mesh Test Script/1.0'
        ],
        'content' => $json_data,
        'timeout' => 30
    ]
]);

echo "Sending test data to production API...\n";
echo "URL: https://data.cnymesh.org/api?a=mesh_data\n";
echo "Data size: " . strlen($json_data) . " bytes\n";
echo "Messages: " . count($test_data['messages']) . "\n\n";

// Send the request
$response = @file_get_contents('https://data.cnymesh.org/api?a=mesh_data', false, $context);

if ($response === false) {
    $error = error_get_last();
    echo "❌ Request failed!\n";
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    
    // Check if it's a network connectivity issue
    echo "\nTrying basic connectivity test...\n";
    $ping_response = @file_get_contents('https://data.cnymesh.org/', false, stream_context_create([
        'http' => ['timeout' => 10]
    ]));
    
    if ($ping_response !== false) {
        echo "✓ Base URL is accessible\n";
        echo "❌ API endpoint may not exist or have issues\n";
    } else {
        echo "❌ Cannot reach server - check internet connection\n";
    }
} else {
    echo "✓ Request successful!\n";
    echo "Response:\n";
    echo $response . "\n\n";
    
    // Try to decode JSON response
    $decoded = json_decode($response, true);
    if ($decoded !== null) {
        echo "Decoded response:\n";
        if (isset($decoded['success']) && $decoded['success']) {
            echo "✓ API returned success\n";
            echo "✓ Saved " . ($decoded['saved_count'] ?? 'unknown') . " messages\n";
            if (isset($decoded['error_count']) && $decoded['error_count'] > 0) {
                echo "⚠ " . $decoded['error_count'] . " errors occurred\n";
                if (isset($decoded['errors'])) {
                    foreach ($decoded['errors'] as $error) {
                        echo "  - $error\n";
                    }
                }
            }
        } else {
            echo "❌ API returned error: " . ($decoded['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "⚠ Response is not valid JSON\n";
        echo "Raw response: $response\n";
    }
}

echo "\nTest completed.\n";
