<?php
require_once __DIR__ . '/../bootstrap.php';

// Test data simulating what the Python script would send
$test_data = [
    'messages' => [
        [
            'topic' => 'msh/US/2/json/LongFast/!433c1598',
            'timestamp' => time(),
            'json_data' => [
                'channel' => 0,
                'from' => 1128011160,
                'to' => 4294967295,
                'type' => 'position',
                'payload' => [
                    'latitude_i' => 387809280,
                    'longitude_i' => -905936896,
                    'altitude' => 134,
                    'time' => time()
                ],
                'rssi' => -95,
                'snr' => 7.25
            ]
        ],
        [
            'topic' => 'msh/US/2/e/LongFast/!43b585fc',
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

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['a'] = 'mesh_data';

// Capture the raw POST data
$input = json_encode($test_data);
file_put_contents('php://input', $input);

echo "Testing API endpoint with sample data...\n\n";

// Create API controller and test
try {
    $controller = new \CNYMesh\Web\Controllers\ApiController();
    
    // Manually set the input for testing
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('mesh_data');
    $method->setAccessible(true);
    
    // Create test request simulation
    ob_start();
    
    // Test with the prepared data
    $json_input = json_encode($test_data);
    
    // Call the processMessage method directly for testing
    $process_method = $reflection->getMethod('processMessage');
    $process_method->setAccessible(true);
    
    $db = new \CNYMesh\Database();
    
    foreach ($test_data['messages'] as $message) {
        $result = $process_method->invoke($controller, $message, $db);
        echo "Processed message from topic: {$message['topic']}\n";
        if ($result) {
            echo "  ✓ Successfully processed\n";
        } else {
            echo "  ✗ Processing failed\n";
        }
    }
    
    echo "\nAPI test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error testing API: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
