<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

header('Content-Type: text/html; charset=utf-8');

try {
    echo "<!DOCTYPE html><html><head><title>ServiceEnvelope Analysis</title></head><body>\n";
    echo "<h1>SERVICE ENVELOPE STRUCTURE ANALYSIS</h1>\n\n";
    
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    // Get a recent sample with payload
    $stmt = $pdo->prepare("
        SELECT id, payload_hex, payload_length, topic
        FROM raw_messages 
        WHERE payload_length > 5 AND is_json = 0
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $sample = $stmt->fetch();
    
    if (!$sample) {
        echo "<p>No sample data found</p>\n";
        exit;
    }
    
    echo "<h2>Sample Message Analysis</h2>\n";
    echo "<p><strong>Message ID:</strong> {$sample['id']}</p>\n";
    echo "<p><strong>Topic:</strong> {$sample['topic']}</p>\n";
    echo "<p><strong>Payload Length:</strong> {$sample['payload_length']} bytes</p>\n";
    
    $payloadBinary = hex2bin($sample['payload_hex']);
    echo "<p><strong>Payload Hex:</strong> " . substr($sample['payload_hex'], 0, 100) . "...</p>\n";
    
    if ($payloadBinary === false) {
        echo "<p style='color: red;'>Failed to decode hex payload</p>\n";
        exit;
    }
    
    // Manually examine ServiceEnvelope structure
    echo "<h3>ServiceEnvelope Detailed Analysis</h3>\n";
    
    try {
        $env = new \Meshtastic\ServiceEnvelope();
        $env->mergeFromString($payloadBinary);
        
        echo "<p>✅ ServiceEnvelope parsed successfully</p>\n";
        
        // Check all available methods
        $methods = get_class_methods($env);
        echo "<p><strong>Available methods:</strong> " . implode(', ', $methods) . "</p>\n";
        
        // Test each getter method
        echo "<h4>ServiceEnvelope Field Values:</h4>\n";
        echo "<ul>\n";
        
        // Try common protobuf methods
        if (method_exists($env, 'getChannelId')) {
            $channelId = $env->getChannelId();
            echo "<li><strong>Channel ID:</strong> '" . $channelId . "' (type: " . gettype($channelId) . ")</li>\n";
        }
        
        if (method_exists($env, 'getGatewayId')) {
            $gatewayId = $env->getGatewayId();
            echo "<li><strong>Gateway ID:</strong> '" . $gatewayId . "' (type: " . gettype($gatewayId) . ")</li>\n";
        }
        
        if (method_exists($env, 'hasPacket')) {
            $hasPacket = $env->hasPacket();
            echo "<li><strong>Has Packet:</strong> " . ($hasPacket ? 'true' : 'false') . "</li>\n";
        }
        
        if (method_exists($env, 'getPacket')) {
            $packet = $env->getPacket();
            echo "<li><strong>Packet:</strong> " . (is_null($packet) ? 'null' : get_class($packet)) . "</li>\n";
        }
        
        // Check for alternative field names
        foreach ($methods as $method) {
            if (strpos($method, 'get') === 0 && $method !== 'getPacket' && $method !== 'getChannelId' && $method !== 'getGatewayId') {
                try {
                    $value = $env->$method();
                    $valueStr = is_object($value) ? get_class($value) : var_export($value, true);
                    echo "<li><strong>$method():</strong> $valueStr</li>\n";
                } catch (\Throwable $e) {
                    echo "<li><strong>$method():</strong> Error - " . $e->getMessage() . "</li>\n";
                }
            }
        }
        
        echo "</ul>\n";
        
        // If no packet, try to parse the payload directly as MeshPacket
        if (!$env->hasPacket() || is_null($env->getPacket())) {
            echo "<h4>Alternative Parsing Attempt</h4>\n";
            echo "<p>Since no MeshPacket found in ServiceEnvelope, trying direct MeshPacket parsing...</p>\n";
            
            try {
                $directPacket = new \Meshtastic\MeshPacket();
                $directPacket->mergeFromString($payloadBinary);
                
                echo "<p>✅ Direct MeshPacket parsing successful!</p>\n";
                echo "<p><strong>From:</strong> " . $directPacket->getFrom() . "</p>\n";
                echo "<p><strong>To:</strong> " . $directPacket->getTo() . "</p>\n";
                echo "<p><strong>Has Decoded:</strong> " . ($directPacket->hasDecoded() ? 'yes' : 'no') . "</p>\n";
                echo "<p><strong>Has Encrypted:</strong> " . ($directPacket->hasEncrypted() ? 'yes' : 'no') . "</p>\n";
                
                if ($directPacket->hasDecoded()) {
                    $data = $directPacket->getDecoded();
                    echo "<p><strong>Port:</strong> " . $data->getPortnum() . "</p>\n";
                    echo "<p><strong>Payload Length:</strong> " . strlen($data->getPayload()) . " bytes</p>\n";
                }
                
            } catch (\Throwable $e) {
                echo "<p style='color: red;'>❌ Direct MeshPacket parsing failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            }
        }
        
    } catch (\Throwable $e) {
        echo "<p style='color: red;'>❌ ServiceEnvelope parsing failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        
        // Try parsing as raw bytes
        echo "<h4>Raw Byte Analysis</h4>\n";
        echo "<p>First 32 bytes: " . bin2hex(substr($payloadBinary, 0, 32)) . "</p>\n";
        echo "<p>Bytes as decimal: ";
        for ($i = 0; $i < min(16, strlen($payloadBinary)); $i++) {
            echo ord($payloadBinary[$i]) . " ";
        }
        echo "</p>\n";
    }
    
    echo "</body></html>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
