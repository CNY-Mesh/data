<?php
// Test production server API availability
echo "Testing production server API endpoints...\n\n";

// First, let's try to access the main site
echo "1. Testing main site access...\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'user_agent' => 'CNY-Mesh Test Script/1.0'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$main_response = @file_get_contents('https://data.cnymesh.org/', false, $context);
if ($main_response !== false) {
    echo "✓ Main site accessible\n";
    echo "Response length: " . strlen($main_response) . " bytes\n";
} else {
    echo "❌ Cannot access main site\n";
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
}

echo "\n2. Testing API endpoint structure...\n";

// Test different API paths that might exist
$api_paths = [
    '/api',
    '/api/',
    '/api?a=nodes',
    '/public/index.php?a=api',
    '/index.php?a=api'
];

foreach ($api_paths as $path) {
    echo "Testing: https://data.cnymesh.org$path\n";
    $response = @file_get_contents('https://data.cnymesh.org' . $path, false, $context);
    if ($response !== false) {
        echo "  ✓ Accessible (length: " . strlen($response) . ")\n";
        // Show first 200 chars to see what we get
        $preview = substr($response, 0, 200);
        echo "  Preview: " . str_replace(["\n", "\r"], [" ", ""], $preview) . "\n";
    } else {
        echo "  ❌ Not accessible\n";
    }
}

echo "\n3. Testing mesh_data API endpoint...\n";

// Now test our specific endpoint
$test_data = ['test' => 'ping'];
$json_data = json_encode($test_data);

$post_context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ],
        'content' => $json_data,
        'timeout' => 15
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$mesh_paths = [
    '/api?a=mesh_data',
    '/public/index.php?a=mesh_data',
    '/index.php?a=mesh_data'
];

foreach ($mesh_paths as $path) {
    echo "Testing POST to: https://data.cnymesh.org$path\n";
    $response = @file_get_contents('https://data.cnymesh.org' . $path, false, $post_context);
    if ($response !== false) {
        echo "  ✓ POST successful\n";
        echo "  Response: $response\n";
    } else {
        echo "  ❌ POST failed\n";
        $error = error_get_last();
        echo "  Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    }
}

echo "\nTest completed.\n";
