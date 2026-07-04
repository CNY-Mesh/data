<?php
/**
 * Check Database Schema
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Database Schema Check ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check raw_messages table structure
    echo "raw_messages table structure:\n";
    $columns = $db->pdo()->query("PRAGMA table_info(raw_messages)")->fetchAll();
    foreach ($columns as $col) {
        echo "  {$col['name']} ({$col['type']}) " . ($col['notnull'] ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    // Get latest messages without created_at
    echo "\nLatest 5 messages:\n";
    $latest = $db->pdo()->query("
        SELECT id, channel_id, topic, message_type, is_encrypted
        FROM raw_messages 
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    foreach ($latest as $msg) {
        echo sprintf("#%-5d %-12s %-25s %-15s enc:%d\n", 
            $msg['id'],
            $msg['channel_id'],
            $msg['topic'],
            $msg['message_type'],
            $msg['is_encrypted']
        );
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
