<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;
use App\Decoder;

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "=== MQTT-SPECIFIC DECRYPTION TEST ===\n\n";
    
    // Get an encrypted message sample
    $stmt = $pdo->prepare("
        SELECT payload_hex, topic, payload_length
        FROM raw_messages 
        WHERE topic LIKE '%/e/%' AND payload_length > 10
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $sample = $stmt->fetch();
    
    if (!$sample) {
        echo "No encrypted message samples found\n";
        exit;
    }
    
    echo "Testing encrypted message from: {$sample['topic']}\n";
    echo "Payload length: {$sample['payload_length']} bytes\n\n";
    
    $payloadBinary = hex2bin($sample['payload_hex']);
    
    // Parse topic to extract channel and node info
    $topicParts = explode('/', $sample['topic']);
    $channelName = null;
    $nodeId = null;
    
    foreach ($topicParts as $part) {
        if (in_array($part, ['LongFast', 'LongSlow', 'MediumFast', 'MediumSlow', 'private'])) {
            $channelName = $part;
        }
        if (strpos($part, '!') === 0) {
            $nodeId = $part;
        }
    }
    
    echo "Extracted from topic:\n";
    echo "  Channel: $channelName\n";
    echo "  Node ID: $nodeId\n\n";
    
    // Step 1: Parse as direct MeshPacket
    echo "=== STEP 1: Parse MeshPacket ===\n";
    try {
        $packet = new \Meshtastic\MeshPacket();
        $packet->mergeFromString($payloadBinary);
        
        echo "✓ MeshPacket parsed\n";
        echo "  From: " . $packet->getFrom() . "\n";
        echo "  To: " . $packet->getTo() . "\n";
        echo "  ID: " . $packet->getId() . "\n";
        echo "  Has encrypted: " . ($packet->hasEncrypted() ? 'YES' : 'NO') . "\n";
        echo "  Has decoded: " . ($packet->hasDecoded() ? 'YES' : 'NO') . "\n";
        
        if (!$packet->hasEncrypted() && !$packet->hasDecoded()) {
            echo "  ⚠️  No encrypted or decoded data - this might not be a MeshPacket\n";
        }
        
        // Step 2: Try decryption if encrypted
        if ($packet->hasEncrypted() && $channelName === 'LongFast') {
            echo "\n=== STEP 2: Attempt Decryption ===\n";
            
            $ciphertext = $packet->getEncrypted();
            echo "  Ciphertext length: " . strlen($ciphertext) . " bytes\n";
            
            // Get LongFast key
            $b64Key = Env::get('LONGFAST_B64_KEY', 'AQ=='); // Default LongFast PSK
            $key = base64_decode($b64Key);
            if (strlen($key) !== 16 && strlen($key) !== 32) {
                $key = "AQ=="; // Fallback to default
                $key = base64_decode($key);
            }
            
            echo "  Key length: " . strlen($key) . " bytes\n";
            
            // Create IV: nodeFrom (4 bytes) + packetId (4 bytes) + 8 zero bytes
            $iv = pack('N', $packet->getFrom()) . pack('N', $packet->getId()) . str_repeat("\0", 8);
            echo "  IV length: " . strlen($iv) . " bytes\n";
            
            // Try decryption with different ciphers
            $ciphers = ['aes-128-ctr', 'aes-256-ctr'];
            foreach ($ciphers as $cipher) {
                if (($cipher === 'aes-128-ctr' && strlen($key) === 16) || 
                    ($cipher === 'aes-256-ctr' && strlen($key) === 32)) {
                    
                    echo "  Trying cipher: $cipher\n";
                    $plaintext = @openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                    
                    if ($plaintext !== false) {
                        echo "  ✓ Decryption successful! Plaintext length: " . strlen($plaintext) . " bytes\n";
                        
                        // Parse decrypted data as Data object
                        try {
                            $data = new \Meshtastic\Data();
                            $data->mergeFromString($plaintext);
                            
                            echo "  ✓ Data object parsed successfully!\n";
                            echo "    Port: " . $data->getPortnum() . "\n";
                            echo "    Payload length: " . strlen($data->getPayload()) . " bytes\n";
                            echo "    Want response: " . ($data->getWantResponse() ? 'YES' : 'NO') . "\n";
                            
                            // Identify port type
                            $portNames = [
                                1 => 'TEXT_MESSAGE_APP',
                                3 => 'POSITION_APP',
                                4 => 'NODEINFO_APP',
                                67 => 'TELEMETRY_APP',
                                70 => 'TRACEROUTE_APP',
                                71 => 'NEIGHBORINFO_APP',
                                73 => 'MAP_REPORT_APP'
                            ];
                            
                            $port = $data->getPortnum();
                            $portName = $portNames[$port] ?? 'UNKNOWN';
                            echo "    Port type: $portName\n";
                            
                            if ($port === 4) {
                                echo "    🎉 NODEINFO MESSAGE FOUND!\n";
                            } else if ($port === 3) {
                                echo "    📍 POSITION MESSAGE FOUND!\n";
                            }
                            
                        } catch (\Throwable $e) {
                            echo "  ✗ Failed to parse decrypted data: " . $e->getMessage() . "\n";
                        }
                    } else {
                        echo "  ✗ Decryption failed with $cipher\n";
                    }
                }
            }
        } else if ($packet->hasDecoded()) {
            echo "\n=== STEP 2: Use Pre-decoded Data ===\n";
            $data = $packet->getDecoded();
            echo "  Port: " . $data->getPortnum() . "\n";
            echo "  Payload length: " . strlen($data->getPayload()) . " bytes\n";
        }
        
    } catch (\Throwable $e) {
        echo "✗ MeshPacket parsing failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
