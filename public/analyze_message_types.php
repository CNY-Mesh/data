<?php
/**
 * Check JSON vs Binary Message Distribution
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== JSON vs Binary Message Analysis ===\n";

$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);

// Check message distribution by topic and channel
$stats = $db->pdo()->query("
    SELECT 
        channel_id,
        topic,
        is_encrypted,
        message_type,
        COUNT(*) as count,
        AVG(LENGTH(raw_message)) as avg_size
    FROM raw_messages 
    WHERE created_at > datetime('now', '-1 hour')
    GROUP BY channel_id, topic, is_encrypted, message_type
    ORDER BY channel_id, topic, is_encrypted
")->fetchAll();

echo "Recent message distribution (last hour):\n";
foreach ($stats as $stat) {
    echo sprintf("%-12s %-15s encrypted:%-1d %-20s count:%-4d avg_size:%.1f\n", 
        $stat['channel_id'], 
        $stat['topic'], 
        $stat['is_encrypted'],
        $stat['message_type'],
        $stat['count'],
        $stat['avg_size']
    );
}

// Check if we have any LongFast messages with actual encrypted data
echo "\n=== LongFast Messages with Encrypted Data ===\n";
$encrypted = $db->pdo()->query("
    SELECT id, topic, LENGTH(raw_message) as size
    FROM raw_messages 
    WHERE channel_id = 'LongFast' 
    AND is_encrypted = 1
    ORDER BY id DESC 
    LIMIT 10
")->fetchAll();

foreach ($encrypted as $msg) {
    echo "Message #{$msg['id']} from {$msg['topic']}: {$msg['size']} bytes\n";
    
    // Parse and check encrypted content
    try {
        $rawData = $db->pdo()->query("SELECT raw_message FROM raw_messages WHERE id = {$msg['id']}")->fetchColumn();
        $env = new \Meshtastic\ServiceEnvelope();
        $env->mergeFromString($rawData);
        
        $pkt = $env->getPacket();
        if ($pkt) {
            $encrypted = $pkt->getEncrypted();
            echo "  Actual encrypted length: " . strlen($encrypted) . "\n";
            if (strlen($encrypted) > 0) {
                echo "  First 16 bytes: " . bin2hex(substr($encrypted, 0, 16)) . "\n";
            }
        }
    } catch (\Throwable $e) {
        echo "  Parse error: " . $e->getMessage() . "\n";
    }
}

// Check what topics we're seeing for LongFast
echo "\n=== LongFast Topic Distribution ===\n";
$topics = $db->pdo()->query("
    SELECT topic, COUNT(*) as count
    FROM raw_messages 
    WHERE channel_id = 'LongFast'
    AND created_at > datetime('now', '-2 hours')
    GROUP BY topic
    ORDER BY count DESC
")->fetchAll();

foreach ($topics as $topic) {
    echo "{$topic['topic']}: {$topic['count']} messages\n";
}
?>
