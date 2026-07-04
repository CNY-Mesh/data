<?php
/**
 * Debug ServiceEnvelope Structure
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== ServiceEnvelope Structure Debug ===\n";

$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);

// Get a recent LongFast message
$messages = $db->pdo()->query("
    SELECT id, raw_message, LENGTH(raw_message) as size
    FROM raw_messages 
    WHERE channel_id = 'LongFast' 
    ORDER BY id DESC 
    LIMIT 5
")->fetchAll();

foreach ($messages as $msg) {
    echo "\n--- Message #{$msg['id']} ({$msg['size']} bytes) ---\n";
    
    try {
        $env = new \Meshtastic\ServiceEnvelope();
        $env->mergeFromString($msg['raw_message']);
        
        echo "Channel ID: " . $env->getChannelId() . "\n";
        echo "Gateway ID: " . $env->getGatewayId() . "\n";
        
        $pkt = $env->getPacket();
        if ($pkt) {
            echo "Packet from: " . $pkt->getFrom() . "\n";
            echo "Packet to: " . $pkt->getTo() . "\n";
            echo "Packet ID: " . $pkt->getId() . "\n";
            
            // Check what kind of payload we have
            $encrypted = $pkt->getEncrypted();
            $decoded = $pkt->getDecoded();
            
            echo "Encrypted length: " . strlen($encrypted) . "\n";
            if (strlen($encrypted) > 0) {
                echo "Encrypted hex (first 32): " . bin2hex(substr($encrypted, 0, 32)) . "\n";
            }
            
            if ($decoded) {
                echo "Has decoded payload!\n";
                echo "Port: " . $decoded->getPortnum() . "\n";
                echo "Payload length: " . strlen($decoded->getPayload()) . "\n";
                if (strlen($decoded->getPayload()) > 0) {
                    echo "Payload hex (first 32): " . bin2hex(substr($decoded->getPayload(), 0, 32)) . "\n";
                }
            } else {
                echo "No decoded payload\n";
            }
        } else {
            echo "No packet found in envelope!\n";
        }
        
    } catch (\Throwable $e) {
        echo "Error parsing: " . $e->getMessage() . "\n";
    }
}
?>
