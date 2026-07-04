<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "=== DEEP PROTOBUF INVESTIGATION ===\n\n";
    
    // Get multiple samples to see patterns
    $stmt = $pdo->prepare("
        SELECT payload_hex, topic, payload_length
        FROM raw_messages 
        WHERE payload_length > 5 AND is_json = 0
        ORDER BY id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $samples = $stmt->fetchAll();
    
    foreach ($samples as $i => $sample) {
        echo "=== SAMPLE " . ($i + 1) . " ===\n";
        echo "Topic: {$sample['topic']}\n";
        echo "Length: {$sample['payload_length']} bytes\n";
        
        $payloadBinary = hex2bin($sample['payload_hex']);
        echo "Raw hex: " . bin2hex($payloadBinary) . "\n";
        
        // Manual protobuf wire format analysis
        echo "Protobuf wire format analysis:\n";
        $pos = 0;
        $fieldsFound = 0;
        
        while ($pos < strlen($payloadBinary) && $fieldsFound < 10) {
            if ($pos >= strlen($payloadBinary)) break;
            
            $byte = ord($payloadBinary[$pos]);
            $fieldNum = $byte >> 3;
            $wireType = $byte & 0x07;
            
            echo "  Pos $pos: byte=0x" . sprintf('%02x', $byte) . " field=$fieldNum wireType=$wireType";
            
            if ($fieldNum >= 1 && $fieldNum <= 20 && $wireType <= 5) {
                echo " (valid)";
                $fieldsFound++;
                
                // Try to read the value based on wire type
                $pos++;
                if ($pos < strlen($payloadBinary)) {
                    switch ($wireType) {
                        case 0: // Varint
                            $value = 0;
                            $shift = 0;
                            $bytes = 0;
                            while ($pos < strlen($payloadBinary) && $bytes < 10) {
                                $b = ord($payloadBinary[$pos]);
                                $value |= (($b & 0x7F) << $shift);
                                $pos++;
                                $bytes++;
                                if (($b & 0x80) === 0) break;
                                $shift += 7;
                            }
                            echo " value=$value";
                            break;
                            
                        case 1: // 64-bit
                            if ($pos + 7 < strlen($payloadBinary)) {
                                $pos += 8;
                                echo " (64-bit data)";
                            }
                            break;
                            
                        case 2: // Length-delimited
                            if ($pos < strlen($payloadBinary)) {
                                $len = ord($payloadBinary[$pos]);
                                $pos++;
                                if ($pos + $len <= strlen($payloadBinary)) {
                                    $data = substr($payloadBinary, $pos, $len);
                                    echo " len=$len data=" . bin2hex(substr($data, 0, 8)) . "...";
                                    $pos += $len;
                                }
                            }
                            break;
                            
                        case 5: // 32-bit
                            if ($pos + 3 < strlen($payloadBinary)) {
                                $pos += 4;
                                echo " (32-bit data)";
                            }
                            break;
                    }
                }
            } else {
                echo " (invalid/noise)";
                $pos++;
            }
            echo "\n";
        }
        
        // Check if this could be a direct encrypted payload
        echo "Direct encryption test:\n";
        if (strlen($payloadBinary) >= 16) {
            echo "  Payload long enough for encryption\n";
            
            // Try to decrypt assuming it's raw encrypted data with known patterns
            $testKeys = [
                base64_decode('AQ=='), // Default LongFast
                base64_decode('1PG9xOBG0OfLaFiRCsyOhKg/e7o='), // Another common key
            ];
            
            foreach ($testKeys as $ki => $key) {
                echo "  Testing key " . ($ki + 1) . " (length: " . strlen($key) . "):\n";
                
                // Try different IV patterns
                $ivPatterns = [
                    str_repeat("\0", 16), // All zeros
                    substr(md5('test'), 0, 16), // MD5 based
                    substr($payloadBinary, 0, 16), // First 16 bytes as IV
                ];
                
                foreach ($ivPatterns as $ivi => $iv) {
                    $ciphers = strlen($key) === 16 ? ['aes-128-ctr', 'aes-128-cbc'] : ['aes-256-ctr', 'aes-256-cbc'];
                    
                    foreach ($ciphers as $cipher) {
                        $result = @openssl_decrypt($payloadBinary, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                        if ($result !== false && strlen($result) > 0) {
                            echo "    ✓ Cipher $cipher with IV pattern $ivi: " . strlen($result) . " bytes\n";
                            echo "      First 16 bytes: " . bin2hex(substr($result, 0, 16)) . "\n";
                            
                            // Try to parse as protobuf
                            try {
                                $data = new \Meshtastic\Data();
                                $data->mergeFromString($result);
                                $port = $data->getPortnum();
                                if ($port > 0 && $port < 1000) {
                                    echo "      🎉 VALID DATA OBJECT! Port: $port\n";
                                }
                            } catch (\Throwable $e) {
                                // Silent fail
                            }
                        }
                    }
                }
            }
        }
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
