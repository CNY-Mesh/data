<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "=== PROTOBUF PARSING DIAGNOSTIC ===\n\n";
    
    // Get a sample with one of the suspicious port numbers
    $stmt = $pdo->prepare("
        SELECT payload_hex, port_num, topic
        FROM raw_messages 
        WHERE port_num = 1223526 AND payload_length > 0
        LIMIT 1
    ");
    $stmt->execute();
    $sample = $stmt->fetch();
    
    if (!$sample) {
        echo "No sample with port 1223526 found, trying other suspicious ports...\n";
        $stmt = $pdo->prepare("
            SELECT payload_hex, port_num, topic
            FROM raw_messages 
            WHERE port_num > 1000 AND payload_length > 0
            LIMIT 1
        ");
        $stmt->execute();
        $sample = $stmt->fetch();
    }
    
    if (!$sample) {
        echo "No suspicious port samples found\n";
        exit;
    }
    
    echo "Sample with port {$sample['port_num']} from topic: {$sample['topic']}\n";
    echo "Payload hex: " . substr($sample['payload_hex'], 0, 64) . "...\n\n";
    
    $payloadBinary = hex2bin($sample['payload_hex']);
    
    echo "=== STEP-BY-STEP PROTOBUF PARSING ===\n\n";
    
    // Step 1: Try ServiceEnvelope
    echo "Step 1: Parsing as ServiceEnvelope\n";
    try {
        $env = new \Meshtastic\ServiceEnvelope();
        $env->mergeFromString($payloadBinary);
        echo "✓ ServiceEnvelope parsing successful\n";
        echo "  Channel ID: '" . $env->getChannelId() . "'\n";
        echo "  Gateway ID: '" . $env->getGatewayId() . "'\n";
        echo "  Has packet: " . ($env->hasPacket() ? 'YES' : 'NO') . "\n";
        
        if ($env->hasPacket()) {
            $packet = $env->getPacket();
            echo "  Packet type: " . get_class($packet) . "\n";
            
            // Step 2: Examine MeshPacket
            echo "\nStep 2: Examining MeshPacket\n";
            echo "  From: " . $packet->getFrom() . "\n";
            echo "  To: " . $packet->getTo() . "\n";
            echo "  ID: " . $packet->getId() . "\n";
            echo "  Has decoded: " . ($packet->hasDecoded() ? 'YES' : 'NO') . "\n";
            echo "  Has encrypted: " . ($packet->hasEncrypted() ? 'YES' : 'NO') . "\n";
            
            if ($packet->hasDecoded()) {
                // Step 3: Examine Data
                echo "\nStep 3: Examining decoded Data\n";
                $data = $packet->getDecoded();
                echo "  Data type: " . get_class($data) . "\n";
                echo "  Port number: " . $data->getPortnum() . "\n";
                echo "  Payload length: " . strlen($data->getPayload()) . "\n";
                
                // Check if port number makes sense
                if ($data->getPortnum() != $sample['port_num']) {
                    echo "  *** PORT MISMATCH! Expected: {$sample['port_num']}, Got: " . $data->getPortnum() . " ***\n";
                }
            } else {
                echo "  No decoded data available\n";
            }
        } else {
            echo "\nStep 2: No MeshPacket in ServiceEnvelope\n";
            echo "Trying direct MeshPacket parsing...\n";
            
            $directPacket = new \Meshtastic\MeshPacket();
            $directPacket->mergeFromString($payloadBinary);
            echo "✓ Direct MeshPacket parsing successful\n";
            echo "  From: " . $directPacket->getFrom() . "\n";
            echo "  To: " . $directPacket->getTo() . "\n";
            echo "  Has decoded: " . ($directPacket->hasDecoded() ? 'YES' : 'NO') . "\n";
        }
        
    } catch (\Throwable $e) {
        echo "✗ ServiceEnvelope parsing failed: " . $e->getMessage() . "\n";
        
        echo "\nTrying direct MeshPacket parsing...\n";
        try {
            $packet = new \Meshtastic\MeshPacket();
            $packet->mergeFromString($payloadBinary);
            echo "✓ Direct MeshPacket parsing successful\n";
        } catch (\Throwable $e2) {
            echo "✗ Direct MeshPacket parsing failed: " . $e2->getMessage() . "\n";
            
            echo "\nTrying direct Data parsing...\n";
            try {
                $data = new \Meshtastic\Data();
                $data->mergeFromString($payloadBinary);
                echo "✓ Direct Data parsing successful\n";
                echo "  Port: " . $data->getPortnum() . "\n";
            } catch (\Throwable $e3) {
                echo "✗ Direct Data parsing failed: " . $e3->getMessage() . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
