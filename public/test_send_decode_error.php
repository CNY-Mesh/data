<?php
/**
 * Test script to manually send a decode error message to the API
 * Access at: https://data.cnymesh.org/test_send_decode_error.php
 */

// Test decode error data structure
$test_decode_error = [
    'messages' => [
        [
            'topic' => 'test/manual_decode_error',
            'timestamp' => time(),
            'json_data' => [
                'decode_error' => true,
                'error_message' => 'Manual test decode error from PHP - Python fix verification',
                'likely_encrypted' => false,
                'size' => 123,
                'hex_preview' => 'deadbeefcafebabe1234567890abcdef',
                'type' => 'decode_error',
                'from' => null,
                'to' => null,
                'rssi' => null,
                'snr' => null
            ]
        ]
    ]
];

// Send to API endpoint
$api_url = 'http://localhost/?r=api&a=mesh_data';  // Match the format used by main.py
$json_data = json_encode($test_decode_error);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Set response header
header('Content-Type: application/json');

$result = [
    'test_name' => 'Manual Decode Error API Test',
    'timestamp' => date('Y-m-d H:i:s'),
    'api_url' => $api_url,
    'http_code' => $http_code,
    'curl_error' => $curl_error ?: null,
    'api_response' => $response ? json_decode($response, true) : null,
    'raw_response' => $response,
    'test_data_sent' => $test_decode_error,
    'success' => ($http_code === 200 && !$curl_error)
];

echo json_encode($result, JSON_PRETTY_PRINT);
?>
