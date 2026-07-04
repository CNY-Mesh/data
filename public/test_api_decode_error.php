<?php
/**
 * Test API endpoint to see if it's working for decode errors
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Support\Env;

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($envFile));
    $dotenv->load();
}

// Test message structure
$testMessage = [
    'topic' => 'test/debug',
    'timestamp' => time(),
    'json_data' => [
        'decode_error' => true,
        'error_message' => 'Test decode error from PHP',
        'likely_encrypted' => true,
        'size' => 123,
        'hex_preview' => 'deadbeef',
        'type' => 'decode_error',
        'from' => null,
        'to' => null,
        'rssi' => null,
        'snr' => null
    ]
];

$payload = [
    'messages' => [$testMessage]
];

echo "<h1>API Test</h1>";
echo "<h2>Sending test decode error message...</h2>";

// Send to API
$apiUrl = \App\Support\Env::get('API_URL', 'https://data.cnymesh.org/?r=api&a=mesh_data');
echo "<p>API URL: " . htmlspecialchars($apiUrl) . "</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: CNY-Mesh Debug Test/1.0'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h2>Results:</h2>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

if ($error) {
    echo "<p><strong>cURL Error:</strong> " . htmlspecialchars($error) . "</p>";
}

echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Check if the message was stored
$dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);
$pdo = $db->pdo();

echo "<h2>Database Check:</h2>";
$stmt = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE topic = 'test/debug' AND message_type = 'decode_error'");
$count = $stmt->fetchColumn();
echo "<p>Messages with topic 'test/debug' and type 'decode_error': <strong>$count</strong></p>";

if ($count > 0) {
    echo "<p style='color: green;'>✅ Test message was stored successfully!</p>";
    
    // Show the stored message
    $stmt = $pdo->query("SELECT * FROM raw_messages WHERE topic = 'test/debug' AND message_type = 'decode_error' ORDER BY id DESC LIMIT 1");
    $stored = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Stored Message:</h3>";
    echo "<pre>" . htmlspecialchars(print_r($stored, true)) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Test message was NOT stored.</p>";
}
?>
