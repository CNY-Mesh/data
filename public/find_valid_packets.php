<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "=== SEARCHING FOR VALID MESHPACKETS ===\n\n";
    
    // Check recent messages to find ones with actual data
    $stmt = $pdo->prepare("
        SELECT payload_hex, port_num, topic, payload_length, node_from, node_to
        FROM raw_messages 
        WHERE payload_length > 10 AND is_json = 0
        ORDER BY id DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $samples = $stmt->fetchAll();
    
    $validCount = 0;
    $invalidCount = 0;
    
    foreach ($samples as $sample) {
        $payloadBinary = hex2bin($sample['payload_hex']);
        
        try {
            $env = new \Meshtastic\ServiceEnvelope();
            $env->mergeFromString($payloadBinary);
            
            if ($env->hasPacket()) {
                $packet = $env->getPacket();
                $from = $packet->getFrom();
                $to = $packet->getTo();
                $id = $packet->getId();
                
                // Check if this packet has real data (non-zero values)
                if ($from != 0 || $to != 0 || $id != 0) {
                    $validCount++;
                    echo "✓ VALID packet found:\n";
                    echo "  Topic: " . substr($sample['topic'], 0, 50) . "...\n";
                    echo "  From: $from (stored: {$sample['node_from']})\n";
                    echo "  To: $to (stored: {$sample['node_to']})\n";
                    echo "  ID: $id\n";
                    echo "  Has decoded: " . ($packet->hasDecoded() ? 'YES' : 'NO') . "\n";
                    echo "  Has encrypted: " . ($packet->hasEncrypted() ? 'YES' : 'NO') . "\n";
                    
                    if ($packet->hasDecoded()) {
                        $data = $packet->getDecoded();
                        $port = $data->getPortnum();
                        echo "  Port: $port (stored: {$sample['port_num']})\n";
                        echo "  Payload size: " . strlen($data->getPayload()) . " bytes\n";
                        
                        // Check if the stored values match the parsed values
                        if ($from != $sample['node_from'] || $to != $sample['node_to'] || $port != $sample['port_num']) {
                            echo "  *** STORAGE MISMATCH DETECTED! ***\n";
                        }
                    }
                    
                    if ($packet->hasEncrypted()) {
                        echo "  Encrypted data size: " . strlen($packet->getEncrypted()) . " bytes\n";
                        echo "  Channel: '" . $env->getChannelId() . "'\n";
                    }
                    
                    echo "\n";
                } else {
                    $invalidCount++;
                    if ($invalidCount <= 3) { // Only show first few invalid ones
                        echo "✗ Empty packet (From=0, To=0, ID=0) from topic: " . substr($sample['topic'], 0, 40) . "...\n";
                    }
                }
            } else {
                echo "✗ No MeshPacket in ServiceEnvelope from: " . substr($sample['topic'], 0, 40) . "...\n";
            }
            
        } catch (\Throwable $e) {
            echo "✗ Parse error: " . substr($e->getMessage(), 0, 50) . "... from: " . substr($sample['topic'], 0, 30) . "...\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Valid packets with data: $validCount\n";
    echo "Invalid/empty packets: $invalidCount\n";
    echo "Total checked: " . count($samples) . "\n";
    
    if ($validCount == 0) {
        echo "\n*** NO VALID PACKETS FOUND! ***\n";
        echo "This suggests the MQTT stream contains only status/heartbeat messages,\n";
        echo "not actual mesh communication data.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
