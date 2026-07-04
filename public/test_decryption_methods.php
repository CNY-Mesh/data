<?php
/**
 * Test Different Key Derivation Methods on Real Data
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;
use Meshtastic\Data;

echo "=== Key Derivation Test on Real Encrypted Data ===\n";

$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);

// Get a recent encrypted LongFast message
$encrypted = $db->pdo()->query("
    SELECT id, raw_message 
    FROM raw_messages 
    WHERE channel_id = 'LongFast' 
    AND is_encrypted = 1 
    AND message_type = 'DECODE_FAILED'
    ORDER BY id DESC 
    LIMIT 1
")->fetch();

if (!$encrypted) {
    echo "No encrypted LongFast messages found\n";
    exit;
}

echo "Testing with message #{$encrypted['id']}\n";

// Parse the ServiceEnvelope
try {
    $env = new \Meshtastic\ServiceEnvelope();
    $env->mergeFromString($encrypted['raw_message']);
    
    $pkt = $env->getPacket();
    $ciphertext = $pkt->getEncrypted();
    
    echo "Ciphertext length: " . strlen($ciphertext) . " bytes\n";
    echo "Packet from: " . $pkt->getFrom() . ", ID: " . $pkt->getId() . "\n";
    
    // Test different key derivations
    $rawKey = base64_decode("AQ=="); // 0x01
    $keys = [
        'repeat_16' => str_repeat($rawKey, 16),
        'zero_pad' => $rawKey . str_repeat("\0", 15),
        'all_zeros' => str_repeat("\0", 16),
        'all_ones' => str_repeat("\x01", 16),
    ];
    
    // Test different IV patterns
    $ivs = [
        'from_id_be' => pack('N', $pkt->getFrom()) . pack('N', $pkt->getId()) . str_repeat("\0", 8),
        'from_id_le' => pack('V', $pkt->getFrom()) . pack('V', $pkt->getId()) . str_repeat("\0", 8),
        'all_zeros' => str_repeat("\0", 16),
        'id_only_be' => pack('N', $pkt->getId()) . str_repeat("\0", 12),
        'id_only_le' => pack('V', $pkt->getId()) . str_repeat("\0", 12),
    ];
    
    foreach ($keys as $keyName => $key) {
        echo "\nTrying key method: $keyName (" . bin2hex($key) . ")\n";
        
        foreach ($ivs as $ivName => $iv) {
            foreach (['aes-128-ctr', 'aes-256-ctr'] as $cipher) {
                if (str_contains($cipher, '256') && strlen($key) != 32) continue;
                if (str_contains($cipher, '128') && strlen($key) != 16) continue;
                
                $pt = @openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                if ($pt !== false && strlen($pt) > 0) {
                    echo "  $cipher + $ivName: Got " . strlen($pt) . " bytes\n";
                    echo "    First 20 bytes: " . bin2hex(substr($pt, 0, 20)) . "\n";
                    
                    // Try to parse as protobuf
                    try {
                        $data = new Data();
                        $data->mergeFromString($pt);
                        echo "    SUCCESS! Port: " . $data->getPortnum() . ", Payload: " . strlen($data->getPayload()) . " bytes\n";
                    } catch (\Throwable $e) {
                        echo "    Not valid protobuf\n";
                    }
                }
            }
        }
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
