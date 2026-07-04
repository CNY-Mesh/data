<?php
/**
 * Success Analysis - What's Actually Working
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== What's Actually Working - Success Analysis ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // LongFast successes in detail
    echo "=== LongFast Success Breakdown ===\n";
    $longfast_success = $db->pdo()->query("
        SELECT 
            message_type,
            CASE 
                WHEN topic LIKE '%/json/%' THEN 'JSON'
                ELSE 'Binary'
            END as format,
            is_encrypted,
            COUNT(*) as count
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND message_type NOT IN ('DECODE_FAILED', 'JSON_PARSE_ERROR')
        GROUP BY message_type, format, is_encrypted
        ORDER BY count DESC
    ")->fetchAll();
    
    $total_longfast_success = 0;
    foreach ($longfast_success as $success) {
        $total_longfast_success += $success['count'];
        echo sprintf("%-15s %-6s encrypted:%-1d count:%d\n", 
            $success['message_type'],
            $success['format'],
            $success['is_encrypted'],
            $success['count']
        );
    }
    
    echo "\nTotal LongFast successes: $total_longfast_success\n";
    
    // Recent successful LongFast messages
    echo "\n=== Recent LongFast Successes ===\n";
    $recent_success = $db->pdo()->query("
        SELECT id, message_type, topic, node_from, processed_at
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND message_type NOT IN ('DECODE_FAILED', 'JSON_PARSE_ERROR')
        ORDER BY id DESC 
        LIMIT 10
    ")->fetchAll();
    
    foreach ($recent_success as $msg) {
        $time = $msg['processed_at'] ? date('H:i:s', $msg['processed_at']) : 'unknown';
        $format = str_contains($msg['topic'], '/json/') ? 'JSON' : 'Binary';
        echo sprintf("#%-5d %-15s %-6s from:%-10s %s\n", 
            $msg['id'],
            $msg['message_type'],
            $format,
            $msg['node_from'],
            $time
        );
    }
    
    // All channels with successes
    echo "\n=== All Successful Channels ===\n";
    $all_success = $db->pdo()->query("
        SELECT 
            channel_id,
            COUNT(*) as success_count,
            COUNT(DISTINCT message_type) as message_types
        FROM raw_messages 
        WHERE message_type NOT IN ('DECODE_FAILED', 'JSON_PARSE_ERROR')
        GROUP BY channel_id
        HAVING success_count > 10
        ORDER BY success_count DESC
        LIMIT 15
    ")->fetchAll();
    
    foreach ($all_success as $channel) {
        echo sprintf("%-15s: %d successes, %d message types\n",
            $channel['channel_id'],
            $channel['success_count'],
            $channel['message_types']
        );
    }
    
    // Success by message type
    echo "\n=== Success by Message Type ===\n";
    $by_type = $db->pdo()->query("
        SELECT 
            message_type,
            COUNT(*) as count,
            COUNT(DISTINCT channel_id) as channels
        FROM raw_messages 
        WHERE message_type NOT IN ('DECODE_FAILED', 'JSON_PARSE_ERROR')
        GROUP BY message_type
        ORDER BY count DESC
    ")->fetchAll();
    
    foreach ($by_type as $type) {
        echo sprintf("%-15s: %d messages across %d channels\n",
            $type['message_type'],
            $type['count'],
            $type['channels']
        );
    }
    
    // Check if our recent decryption improvements are working
    echo "\n=== Recent Binary LongFast Activity ===\n";
    $recent_binary = $db->pdo()->query("
        SELECT 
            message_type,
            is_encrypted,
            COUNT(*) as count
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND topic NOT LIKE '%/json/%'
        AND id > (SELECT MAX(id) - 200 FROM raw_messages)
        GROUP BY message_type, is_encrypted
        ORDER BY count DESC
    ")->fetchAll();
    
    foreach ($recent_binary as $stat) {
        echo sprintf("%-15s encrypted:%-1d count:%d\n", 
            $stat['message_type'],
            $stat['is_encrypted'],
            $stat['count']
        );
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
