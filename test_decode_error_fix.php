<?php
/**
 * Test script to verify decode error messages are being stored in database
 * Upload this to the server and run at: https://data.cnymesh.org/test_decode_error_fix.php
 */

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Support/Env.php';

use App\Database;
use App\Support\Env;

// Set content type for JSON response
header('Content-Type: application/json');

try {
    // Load environment
    Env::load(__DIR__);
    
    // Create database connection with proper DSN
    $dbPath = __DIR__ . '/data/meshtastic.sqlite';
    $dsn = 'sqlite:' . $dbPath;
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    // Check recent decode errors in the database
    $stmt = $pdo->prepare("
        SELECT 
            id,
            topic,
            timestamp,
            json_data,
            created_at,
            DATETIME(timestamp, 'unixepoch') as message_time
        FROM raw_messages 
        WHERE json_data LIKE '%decode_error%' 
           OR json_data LIKE '%Complete decode failure%'
           OR type = 'decode_error'
        ORDER BY timestamp DESC 
        LIMIT 20
    ");
    
    $stmt->execute();
    $decode_errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get total message count and recent messages for context
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM raw_messages");
    $stmt->execute();
    $total_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get most recent messages to see what's being stored
    $stmt = $pdo->prepare("
        SELECT 
            id,
            topic,
            timestamp,
            type,
            json_data,
            DATETIME(timestamp, 'unixepoch') as message_time
        FROM raw_messages 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    
    $stmt->execute();
    $recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for any messages with 'error' in various fields
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as error_count
        FROM raw_messages 
        WHERE json_data LIKE '%error%' 
           OR decoded_packet LIKE '%error%'
           OR type LIKE '%error%'
    ");
    $stmt->execute();
    $error_count = $stmt->fetch(PDO::FETCH_ASSOC)['error_count'];
    
    $result = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_messages' => $total_messages,
        'decode_errors_found' => count($decode_errors),
        'messages_with_error_text' => $error_count,
        'decode_errors' => $decode_errors,
        'recent_messages' => $recent_messages,
        'analysis' => [
            'database_working' => $total_messages > 0,
            'decode_errors_being_stored' => count($decode_errors) > 0,
            'has_any_error_messages' => $error_count > 0
        ]
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
