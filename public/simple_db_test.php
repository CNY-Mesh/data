<?php
/**
 * Simple Database Query Test
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Simple Database Test ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Just count messages
    $count = $db->pdo()->query("SELECT COUNT(*) FROM raw_messages")->fetchColumn();
    echo "Total messages in database: $count\n";
    
    // Get the latest message
    $latest = $db->pdo()->query("
        SELECT id, channel_id, topic, created_at 
        FROM raw_messages 
        ORDER BY id DESC 
        LIMIT 1
    ")->fetch();
    
    if ($latest) {
        echo "Latest message: #{$latest['id']} from {$latest['channel_id']} on {$latest['topic']} at {$latest['created_at']}\n";
    }
    
    // Check if worker is running
    echo "\nChecking for recent activity (last 5 minutes):\n";
    $recent = $db->pdo()->query("
        SELECT COUNT(*) 
        FROM raw_messages 
        WHERE created_at > datetime('now', '-5 minutes')
    ")->fetchColumn();
    
    echo "Messages in last 5 minutes: $recent\n";
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Class: " . get_class($e) . "\n";
}
?>
