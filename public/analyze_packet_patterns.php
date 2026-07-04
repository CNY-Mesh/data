<?php
/**
 * Analyze Packet Patterns
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Packet Pattern Analysis ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check what happens with packets that have no encrypted data
    echo "Checking LongFast packets by encrypted data presence:\n";
    
    $stats = $db->pdo()->query("
        SELECT 
            CASE 
                WHEN topic LIKE '%/json/%' THEN 'JSON'
                ELSE 'Binary'
            END as message_format,
            CASE 
                WHEN is_encrypted = 1 THEN 'Encrypted'
                ELSE 'Not Encrypted'
            END as encryption_status,
            message_type,
            COUNT(*) as count
        FROM raw_messages 
        WHERE channel_id = 'LongFast'
        GROUP BY message_format, encryption_status, message_type
        ORDER BY message_format, encryption_status, count DESC
    ")->fetchAll();
    
    foreach ($stats as $stat) {
        echo sprintf("%-7s %-13s %-20s: %d\n", 
            $stat['message_format'],
            $stat['encryption_status'], 
            $stat['message_type'],
            $stat['count']
        );
    }
    
    // Look at packet sizes for binary messages
    echo "\nBinary LongFast packet size distribution:\n";
    $sizes = $db->pdo()->query("
        SELECT 
            LENGTH(raw_message) as size,
            is_encrypted,
            message_type,
            COUNT(*) as count
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND topic NOT LIKE '%/json/%'
        GROUP BY size, is_encrypted, message_type
        ORDER BY size, is_encrypted
    ")->fetchAll();
    
    foreach ($sizes as $size) {
        echo sprintf("%2d bytes, encrypted:%d, %s: %d packets\n",
            $size['size'],
            $size['is_encrypted'],
            $size['message_type'],
            $size['count']
        );
    }
    
    // Check what's in the small packets
    echo "\nExamining small binary packets (likely control messages):\n";
    $small = $db->pdo()->query("
        SELECT id, topic, LENGTH(raw_message) as size, message_type
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND topic NOT LIKE '%/json/%'
        AND LENGTH(raw_message) < 30
        ORDER BY id DESC
        LIMIT 3
    ")->fetchAll();
    
    foreach ($small as $msg) {
        echo "\n--- Packet #{$msg['id']} ({$msg['size']} bytes) ---\n";
        echo "Topic: {$msg['topic']}\n";
        
        $rawData = $db->pdo()->query("SELECT raw_message FROM raw_messages WHERE id = {$msg['id']}")->fetchColumn();
        
        try {
            $env = new \Meshtastic\ServiceEnvelope();
            $env->mergeFromString($rawData);
            
            $pkt = $env->getPacket();
            if ($pkt) {
                echo "From: " . $pkt->getFrom() . ", To: " . $pkt->getTo() . "\n";
                echo "Has decoded: " . ($pkt->getDecoded() ? 'yes' : 'no') . "\n";
                echo "Encrypted length: " . strlen($pkt->getEncrypted()) . "\n";
                
                if ($pkt->getDecoded()) {
                    $decoded = $pkt->getDecoded();
                    echo "Port: " . $decoded->getPortnum() . "\n";
                    echo "Payload length: " . strlen($decoded->getPayload()) . "\n";
                }
            }
        } catch (\Throwable $e) {
            echo "Parse error: " . $e->getMessage() . "\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
