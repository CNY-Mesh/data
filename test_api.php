<?php
/**
 * Test script for the mesh data API endpoint
 * Usage: php test_api.php
 */

// Test data that mimics what the Python script would send
$test_data = [
    'messages' => [
        [
            'topic' => 'msh/US/2/json/LongFast/!433c1598',
            'timestamp' => time(),
            'json_data' => [
                'channel' => 0,
                'from' => 1128011160,
                'hop_start' => 7,
                'hops_away' => 0,
                'id' => 2751965789,
                'payload' => [
                    'PDOP' => 301,
                    'altitude' => 134,
                    'latitude_i' => 387809280,
                    'longitude_i' => -905936896,
                    'precision_bits' => 16,
                    'sats_in_view' => 5,
                    'time' => time()
                ],
                'sender' => '!433c1598',
                'timestamp' => time(),
                'to' => 4294967295,
                'type' => 'position',
                'rssi' => -95,
                'snr' => 7.25
            ]
        ],
        [
            'topic' => 'msh/US/2/e/LongFast/!43b585fc',
            'timestamp' => time(),
            'decoded_packet' => [
                'to' => 4294967295,
                'from' => 3777611546,
                'id' => 123456789,
                'rx_time' => time(),
                'rx_snr' => 5.5,
                'rx_rssi' => -88,
                'hop_limit' => 3,
                'channel' => 8,
                'want_ack' => false,
                'priority' => 0,
                'delayed' => 0,
                'decoded' => [
                    'portnum' => 67,
                    'portnum_name' => 'TELEMETRY_APP',
                    'payload_size' => 29,
                    'payload_hex' => '0de9e0020012160865152b8786401db5816e3f258e97a23f28a1d49102',
                    'telemetry' => [
                        'battery_level' => 85,
                        'voltage' => 4.12,
                        'channel_utilization' => 2.5,
                        'air_util_tx' => 1.2,
                        'uptime_seconds' => 3600
                    ]
                ]
            ]
        ]
    ]
];

// Convert to JSON
$json_data = json_encode($test_data);

// API endpoint URL
$url = 'http://localhost/api?a=mesh_data';

// Make POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

if ($http_code === 200) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        echo "✅ Test successful! Saved {$result['saved_count']} messages\n";
        if ($result['error_count'] > 0) {
            echo "⚠️  {$result['error_count']} errors occurred:\n";
            foreach ($result['errors'] as $error) {
                echo "   - $error\n";
            }
        }
    } else {
        echo "❌ Test failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "❌ HTTP Error: $http_code\n";
}
