<?php
// Test the deduplication functionality
require_once 'bootstrap.php';

$db = new \MeshTracker\Database();

echo "<h2>Testing Text Message Deduplication</h2>\n\n";

// Count current text messages
$count_stmt = $db->pdo()->query("SELECT COUNT(*) as count FROM text_messages");
$initial_count = $count_stmt->fetch()['count'];
echo "Initial text message count: $initial_count<br><br>\n";

// Test data - sending the same message multiple times
$test_data = [
    'node_from' => 999999999,
    'node_to' => null,
    'message' => 'Test deduplication message',
    'timestamp' => time(),
    'topic' => 'msh/CNY/2/t/!4358/1234567890'
];

echo "Attempting to send the same message 5 times...<br>\n";

// Try to insert the same message 5 times via API
for ($i = 1; $i <= 5; $i++) {
    $api_data = [
        'node_from' => $test_data['node_from'],
        'node_to' => $test_data['node_to'],
        'payload' => ['message' => $test_data['message']],
        'timestamp' => $test_data['timestamp'],
        'topic' => $test_data['topic']
    ];
    
    // Create an instance of ApiController and test
    $controller = new \MeshTracker\Web\Controllers\ApiController();
    
    // Use reflection to call the private method for testing
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('processTextMessage');
    $method->setAccessible(true);
    
    try {
        $method->invoke(
            $controller, 
            $api_data['node_from'], 
            $api_data['node_to'], 
            $api_data['payload'], 
            $api_data['timestamp'], 
            $api_data['topic']
        );
        echo "Attempt $i: Method called successfully<br>\n";
    } catch (Exception $e) {
        echo "Attempt $i: Error - " . $e->getMessage() . "<br>\n";
    }
}

// Count messages after attempts
$count_stmt = $db->pdo()->query("SELECT COUNT(*) as count FROM text_messages");
$final_count = $count_stmt->fetch()['count'];
echo "<br>Final text message count: $final_count<br>\n";
echo "Messages added: " . ($final_count - $initial_count) . "<br><br>\n";

// Check if our test message exists
$check_stmt = $db->pdo()->prepare("SELECT * FROM text_messages WHERE node_from = ? AND message = ?");
$check_stmt->execute([$test_data['node_from'], $test_data['message']]);
$messages = $check_stmt->fetchAll();

echo "Test messages found in database: " . count($messages) . "<br><br>\n";

if (count($messages) > 0) {
    echo "<h3>Test Message Details:</h3>\n";
    foreach ($messages as $msg) {
        echo "ID: {$msg['id']}, Hash: " . substr($msg['message_hash'], 0, 16) . "..., Topic: {$msg['topic']}<br>\n";
    }
}

echo "<br><strong>Result: </strong>";
if (($final_count - $initial_count) == 1 && count($messages) == 1) {
    echo "<span style='color: green;'>✅ PASS - Deduplication working correctly! Only 1 message stored despite 5 attempts.</span><br>\n";
} else {
    echo "<span style='color: red;'>❌ FAIL - Expected 1 new message, got " . ($final_count - $initial_count) . "</span><br>\n";
}

// Clean up test data
echo "<br>Cleaning up test data...<br>\n";
$cleanup_stmt = $db->pdo()->prepare("DELETE FROM text_messages WHERE node_from = ?");
$cleanup_stmt->execute([$test_data['node_from']]);
echo "Test data cleaned up.<br>\n";
?>
