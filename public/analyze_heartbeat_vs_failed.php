<?php
/**
 * Analyze HEARTBEAT vs DECODE_FAILED Patterns
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== HEARTBEAT vs DECODE_FAILED Analysis ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Get some successful HEARTBEAT messages
    echo "Recent HEARTBEAT successes:\n";
    $heartbeats = $db->pdo()->query("
        SELECT id, topic, LENGTH(raw_message) as size, node_from, node_to
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND message_type = 'HEARTBEAT'
        AND topic NOT LIKE '%/json/%'
        ORDER BY id DESC 
        LIMIT 3
    ")->fetchAll();
    
    foreach ($heartbeats as $msg) {
        echo "\n--- HEARTBEAT #{$msg['id']} ({$msg['size']} bytes) ---\n";
        echo "Topic: {$msg['topic']}\n";
        echo "From: {$msg['node_from']}, To: {$msg['node_to']}\n";
        
        $rawData = $db->pdo()->query("SELECT raw_message FROM raw_messages WHERE id = {$msg['id']}")->fetchColumn();
        
        try {
            $env = new \Meshtastic\ServiceEnvelope();
            $env->mergeFromString($rawData);
            
            $pkt = $env->getPacket();
            if ($pkt) {
                echo "Packet from: " . $pkt->getFrom() . ", to: " . $pkt->getTo() . ", ID: " . $pkt->getId() . "\n";
                echo "Has decoded: " . ($pkt->getDecoded() ? 'yes' : 'no') . "\n";
                echo "Encrypted length: " . strlen($pkt->getEncrypted()) . "\n";
                
                if (strlen($pkt->getEncrypted()) > 0) {
                    echo "Encrypted hex: " . bin2hex($pkt->getEncrypted()) . "\n";
                    
                    // Try to decrypt with our key
                    $key = str_repeat(base64_decode("AQ=="), 16);
                    $iv = pack('N', $pkt->getFrom()) . pack('N', $pkt->getId()) . str_repeat("\0", 8);
                    
                    $pt = @openssl_decrypt($pkt->getEncrypted(), 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $iv);
                    if ($pt !== false && strlen($pt) > 0) {
                        echo "Manual decryption SUCCESS: " . bin2hex($pt) . "\n";
                        
                        // Try to parse as Data protobuf
                        try {
                            $data = new \Meshtastic\Data();
                            $data->mergeFromString($pt);
                            echo "Data port: " . $data->getPortnum() . "\n";
                            echo "Payload length: " . strlen($data->getPayload()) . "\n";
                        } catch (\Throwable $e) {
                            echo "Not Data protobuf: " . $e->getMessage() . "\n";
                        }
                    }
                }
                
                if ($pkt->getDecoded()) {
                    $decoded = $pkt->getDecoded();
                    echo "Decoded port: " . $decoded->getPortnum() . "\n";
                    echo "Decoded payload: " . bin2hex($decoded->getPayload()) . "\n";
                }
            }
        } catch (\Throwable $e) {
            echo "Parse error: " . $e->getMessage() . "\n";
        }
    }
    
    // Compare with similar-sized DECODE_FAILED messages
    echo "\n\n=== DECODE_FAILED for comparison ===\n";
    $failed = $db->pdo()->query("
        SELECT id, topic, LENGTH(raw_message) as size
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND message_type = 'DECODE_FAILED'
        AND topic NOT LIKE '%/json/%'
        AND is_encrypted = 1
        AND LENGTH(raw_message) BETWEEN 19 AND 25
        ORDER BY id DESC 
        LIMIT 2
    ")->fetchAll();
    
    foreach ($failed as $msg) {
        echo "\n--- FAILED #{$msg['id']} ({$msg['size']} bytes) ---\n";
        
        $rawData = $db->pdo()->query("SELECT raw_message FROM raw_messages WHERE id = {$msg['id']}")->fetchColumn();
        
        try {
            $env = new \Meshtastic\ServiceEnvelope();
            $env->mergeFromString($rawData);
            
            $pkt = $env->getPacket();
            if ($pkt) {
                echo "From: " . $pkt->getFrom() . ", ID: " . $pkt->getId() . "\n";
                echo "Encrypted length: " . strlen($pkt->getEncrypted()) . "\n";
                
                if (strlen($pkt->getEncrypted()) > 0) {
                    echo "First 20 bytes: " . bin2hex(substr($pkt->getEncrypted(), 0, 20)) . "\n";
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
