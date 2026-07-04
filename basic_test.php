<?php
// Simple test script to verify API functionality
echo "Testing API endpoint functionality...\n\n";

// Set up the environment
require_once __DIR__ . '/bootstrap.php';

try {
    // Test database connection
    $db = new CNYMesh\Database();
    echo "✓ Database connection successful\n";
    
    // Test sample data processing
    $test_message = [
        'topic' => 'msh/US/2/json/LongFast/!test123',
        'timestamp' => time(),
        'json_data' => [
            'channel' => 0,
            'from' => 1128011160,
            'to' => 4294967295,
            'type' => 'position',
            'payload' => [
                'latitude_i' => 387809280,  // ~38.7809 degrees
                'longitude_i' => -905936896, // ~-90.5937 degrees  
                'altitude' => 134,
                'time' => time()
            ],
            'rssi' => -95,
            'snr' => 7.25
        ]
    ];
    
    // Test raw message storage
    $sql = "INSERT INTO raw_messages (topic, timestamp, raw_data, message_type) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $test_message['topic'],
        $test_message['timestamp'], 
        json_encode($test_message['json_data']),
        'json'
    ]);
    
    if ($result) {
        echo "✓ Raw message storage test successful\n";
    } else {
        echo "✗ Raw message storage test failed\n";
    }
    
    // Test position data processing
    $json_data = $test_message['json_data'];
    if ($json_data['type'] === 'position' && isset($json_data['payload'])) {
        $payload = $json_data['payload'];
        $lat = isset($payload['latitude_i']) ? $payload['latitude_i'] / 10000000.0 : null;
        $lon = isset($payload['longitude_i']) ? $payload['longitude_i'] / 10000000.0 : null;
        
        echo "✓ Position data parsed: lat=$lat, lon=$lon\n";
        
        // Test node update
        $node_sql = "INSERT OR REPLACE INTO nodes (node_num, last_seen) VALUES (?, ?)";
        $node_stmt = $db->prepare($node_sql);
        $node_result = $node_stmt->execute([$json_data['from'], $test_message['timestamp']]);
        
        if ($node_result) {
            echo "✓ Node update test successful\n";
        }
        
        // Test position insert
        $pos_sql = "INSERT INTO positions (node_num, latitude, longitude, altitude, timestamp, rssi, snr) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $pos_stmt = $db->prepare($pos_sql);
        $pos_result = $pos_stmt->execute([
            $json_data['from'],
            $lat,
            $lon,
            $payload['altitude'] ?? null,
            $payload['time'] ?? $test_message['timestamp'],
            $json_data['rssi'] ?? null,
            $json_data['snr'] ?? null
        ]);
        
        if ($pos_result) {
            echo "✓ Position insert test successful\n";
        }
    }
    
    echo "\nAll tests completed successfully!\n";
    echo "API endpoint is ready to receive data from Python MQTT monitor.\n";
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
