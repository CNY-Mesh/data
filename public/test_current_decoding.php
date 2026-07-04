<?php
/**
 * Test Current Decoding Setup
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Decoder;
use App\Support\Env;

$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);
$decoder = new Decoder();

echo "=== Meshtastic Decoding Test ===\n";

// Show the environment key
$envKey = Env::get('LONGFAST_B64_KEY', '');
if ($envKey) {
    $keyBytes = base64_decode($envKey);
    echo "LongFast Key (from env): " . bin2hex($keyBytes) . "\n";
    echo "Key Length: " . strlen($keyBytes) . " bytes\n\n";
}

// Test a few recent raw messages
$messages = $db->pdo()->query("
    SELECT id, topic, channel_id, is_encrypted, is_json, message_type, raw_message 
    FROM raw_messages 
    ORDER BY id DESC 
    LIMIT 5
")->fetchAll();

echo "Recent Messages:\n";
foreach ($messages as $msg) {
    echo "ID #{$msg['id']}: ";
    echo "Topic={$msg['topic']}, ";
    echo "Channel={$msg['channel_id']}, ";
    echo "Encrypted=" . ($msg['is_encrypted'] ? 'Yes' : 'No') . ", ";
    echo "JSON=" . ($msg['is_json'] ? 'Yes' : 'No') . ", ";
    echo "Type={$msg['message_type']}, ";
    echo "Size=" . strlen($msg['raw_message']) . "b\n";
    
    // Try to decode if it's binary and failed
    if (!$msg['is_json'] && $msg['message_type'] == 'DECODE_FAILED') {
        echo "  -> Trying to decode: ";
        $envelope = $decoder->parseEnvelope($msg['raw_message']);
        if ($envelope) {
            echo "ServiceEnvelope OK, ";
            if ($envelope->hasPacket()) {
                echo "Has packet, ";
                $decoded = $decoder->getDecodedData($envelope);
                if ($decoded) {
                    echo "DECODE SUCCESS!\n";
                } else {
                    echo "decode failed\n";
                }
            } else {
                echo "no packet\n";
            }
        } else {
            echo "ServiceEnvelope failed\n";
        }
    }
}
