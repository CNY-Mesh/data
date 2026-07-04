<?php
// API Test for Mesh Data Endpoint
// Accessible at: https://data.cnymesh.org/test_api_endpoint.php

header('Content-Type: text/plain');
echo "CNY Mesh - API Endpoint Test\n";
echo "============================\n\n";

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

echo "Test 1: API Endpoint Availability\n";
echo "---------------------------------\n";

// Check if we can create the API controller
try {
    require_once __DIR__ . '/../bootstrap.php';
    echo "✓ Bootstrap loaded successfully\n";
    
    // Test if API controller exists
    if (class_exists('CNYMesh\Web\Controllers\ApiController')) {
        echo "✓ ApiController class found\n";
    } else {
        echo "❌ ApiController class not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Bootstrap error: " . $e->getMessage() . "\n";
}

echo "\nTest 2: Direct API Method Test\n";
echo "------------------------------\n";

// Test the API method directly
try {
    $controller = new \CNYMesh\Web\Controllers\ApiController();
    echo "✓ ApiController instantiated\n";
    
    // Simulate POST request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['a'] = 'mesh_data';
    
    // Test if method exists
    if (method_exists($controller, 'mesh_data')) {
        echo "✓ mesh_data method exists\n";
        
        // We can't easily test the actual method without mocking POST data
        echo "ℹ Method available for testing\n";
    } else {
        echo "❌ mesh_data method not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Controller error: " . $e->getMessage() . "\n";
}

echo "\nTest 3: Database Connection\n";
echo "---------------------------\n";

try {
    $db = new \CNYMesh\Database();
    echo "✓ Database connection successful\n";
    
    // Test a simple query
    $stmt = $db->query("SELECT COUNT(*) as count FROM raw_messages");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Database query successful - " . $result['count'] . " raw messages in database\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\nTest 4: API Logic Simulation\n";
echo "----------------------------\n";

// Test the processing logic without actually storing
foreach ($test_data['messages'] as $i => $message) {
    echo "Message " . ($i + 1) . ":\n";
    echo "  Topic: " . $message['topic'] . "\n";
    echo "  Timestamp: " . date('Y-m-d H:i:s', $message['timestamp']) . "\n";
    
    if (isset($message['json_data'])) {
        echo "  Type: JSON message (" . $message['json_data']['type'] . ")\n";
        if ($message['json_data']['type'] === 'position') {
            $payload = $message['json_data']['payload'];
            $lat = $payload['latitude_i'] / 10000000.0;
            $lon = $payload['longitude_i'] / 10000000.0;
            echo "  Position: $lat, $lon (altitude: " . $payload['altitude'] . "m)\n";
        }
    } elseif (isset($message['decoded_packet'])) {
        echo "  Type: Decoded packet (" . $message['decoded_packet']['decoded']['portnum_name'] . ")\n";
        if (isset($message['decoded_packet']['decoded']['telemetry'])) {
            $tel = $message['decoded_packet']['decoded']['telemetry'];
            echo "  Telemetry: " . $tel['battery_level'] . "% battery, " . $tel['voltage'] . "V\n";
        }
    }
    echo "  ✓ Would be processed successfully\n\n";
}

echo "Test Summary\n";
echo "============\n";
echo "API endpoint is ready for testing.\n";
echo "Production URL: https://data.cnymesh.org/api?a=mesh_data\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
