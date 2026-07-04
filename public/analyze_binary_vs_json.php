<?php
/**
 * Analyze Binary vs JSON LongFast Messages
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Binary vs JSON LongFast Analysis ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Get recent binary LongFast messages that failed
    echo "Recent binary LongFast failures:\n";
    $binary = $db->pdo()->query("
        SELECT id, topic, node_from, LENGTH(raw_message) as size
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND topic LIKE '%/e/LongFast%'
        AND message_type = 'DECODE_FAILED'
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    foreach ($binary as $msg) {
        echo "\n--- Binary Message #{$msg['id']} ---\n";
        echo "Topic: {$msg['topic']}\n";
        echo "From node: {$msg['node_from']}\n";
        echo "Size: {$msg['size']} bytes\n";
        
        // Parse the ServiceEnvelope
        $rawData = $db->pdo()->query("SELECT raw_message FROM raw_messages WHERE id = {$msg['id']}")->fetchColumn();
        
        try {
            $env = new \Meshtastic\ServiceEnvelope();
            $env->mergeFromString($rawData);
            
            echo "Channel ID: " . $env->getChannelId() . "\n";
            echo "Gateway ID: " . $env->getGatewayId() . "\n";
            
            $pkt = $env->getPacket();
            if ($pkt) {
                echo "Packet from: " . $pkt->getFrom() . "\n";
                echo "Packet to: " . $pkt->getTo() . "\n";
                echo "Packet ID: " . $pkt->getId() . "\n";
                
                $encrypted = $pkt->getEncrypted();
                echo "Encrypted length: " . strlen($encrypted) . "\n";
                
                if (strlen($encrypted) > 0) {
                    echo "Encrypted hex (first 32): " . bin2hex(substr($encrypted, 0, 32)) . "\n";
                    
                    // Try our LongFast key
                    $key = str_repeat(base64_decode("AQ=="), 16); // 0x01 repeated
                    $iv = pack('N', $pkt->getFrom()) . pack('N', $pkt->getId()) . str_repeat("\0", 8);
                    
                    $pt = @openssl_decrypt($encrypted, 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $iv);
                    if ($pt !== false && strlen($pt) > 0) {
                        echo "Decryption SUCCESS! Length: " . strlen($pt) . "\n";
                        echo "Plaintext hex (first 32): " . bin2hex(substr($pt, 0, 32)) . "\n";
                    } else {
                        echo "Decryption failed with standard LongFast key\n";
                    }
                } else {
                    echo "NO ENCRYPTED DATA FOUND!\n";
                }
            }
        } catch (\Throwable $e) {
            echo "Parse error: " . $e->getMessage() . "\n";
        }
    }
    
    // Compare with successful JSON messages
    echo "\n\n=== Recent JSON LongFast successes ===\n";
    $json = $db->pdo()->query("
        SELECT id, topic, node_from, message_type
        FROM raw_messages 
        WHERE channel_id = 'LongFast' 
        AND topic LIKE '%/json/LongFast%'
        AND message_type != 'DECODE_FAILED'
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    foreach ($json as $msg) {
        echo "#{$msg['id']}: {$msg['topic']} -> {$msg['message_type']}\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
