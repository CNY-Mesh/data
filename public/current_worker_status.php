<?php
/**
 * Current Worker Status Check
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Current Worker Status ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check very recent activity (last 5 minutes)
    $recent = $db->pdo()->query("
        SELECT 
            message_type,
            channel_id,
            is_encrypted,
            COUNT(*) as count
        FROM raw_messages 
        WHERE id > (SELECT MAX(id) - 100 FROM raw_messages)
        GROUP BY message_type, channel_id, is_encrypted
        ORDER BY count DESC
    ")->fetchAll();
    
    echo "Recent activity (last ~100 messages):\n";
    foreach ($recent as $stat) {
        echo sprintf("%-15s %-12s encrypted:%-1d count:%d\n", 
            $stat['message_type'],
            $stat['channel_id'], 
            $stat['is_encrypted'],
            $stat['count']
        );
    }
    
    // Get the absolute latest messages
    echo "\nLatest 5 messages:\n";
    $latest = $db->pdo()->query("
        SELECT id, channel_id, message_type, is_encrypted, processed_at
        FROM raw_messages 
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    foreach ($latest as $msg) {
        $time = $msg['processed_at'] ? date('H:i:s', $msg['processed_at']) : 'unknown';
        echo sprintf("#%-5d %-12s %-15s encrypted:%-1d %s\n", 
            $msg['id'],
            $msg['channel_id'],
            $msg['message_type'],
            $msg['is_encrypted'],
            $time
        );
    }
    
    // Check total counts
    $total = $db->pdo()->query("SELECT COUNT(*) FROM raw_messages")->fetchColumn();
    echo "\nTotal messages in database: $total\n";
    
    // Check success rates
    echo "\nOverall success rates:\n";
    $success = $db->pdo()->query("
        SELECT 
            CASE 
                WHEN message_type IN ('DECODE_FAILED', 'JSON_PARSE_ERROR') THEN 'Failed'
                ELSE 'Success'
            END as status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM raw_messages), 2) as percentage
        FROM raw_messages
        GROUP BY status
    ")->fetchAll();
    
    foreach ($success as $stat) {
        echo "{$stat['status']}: {$stat['count']} ({$stat['percentage']}%)\n";
    }
    
    // Summary by channel
    echo "\nBy channel:\n";
    $channels = $db->pdo()->query("
        SELECT 
            channel_id,
            CASE 
                WHEN message_type IN ('DECODE_FAILED', 'JSON_PARSE_ERROR') THEN 'Failed'
                ELSE 'Success'
            END as status,
            COUNT(*) as count
        FROM raw_messages
        GROUP BY channel_id, status
        ORDER BY channel_id, status
    ")->fetchAll();
    
    foreach ($channels as $stat) {
        echo sprintf("%-12s %s: %d\n", 
            $stat['channel_id'], 
            $stat['status'],
            $stat['count']
        );
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
