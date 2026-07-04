<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;
use App\Decoder;

header('Content-Type: text/html; charset=utf-8');

try {
    echo "<!DOCTYPE html><html><head><title>Protobuf Library Test</title></head><body>\n";
    echo "<h1>PROTOBUF LIBRARY DIAGNOSTIC</h1>\n\n";
    
    // Test 1: Check protobuf library availability
    echo "<h2>Test 1: Protobuf Library Availability</h2>\n";
    
    try {
        $testData = new \Meshtastic\Data();
        echo "<p>✅ Meshtastic\\Data class available</p>\n";
        echo "<p>Available methods: " . implode(', ', get_class_methods($testData)) . "</p>\n";
        
    } catch (\Throwable $e) {
        echo "<p style='color: red;'>❌ Protobuf class test failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Test 2: Get a real sample from the database and test parsing
    echo "<h2>Test 2: Real Data Parsing Test</h2>\n";
    
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    $stmt = $pdo->prepare("
        SELECT id, payload_hex, port_num, payload_length, topic
        FROM raw_messages 
        WHERE payload_length > 10 AND is_json = 0
        ORDER BY id DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $samples = $stmt->fetchAll();
    
    $decoder = new Decoder();
    
    foreach ($samples as $sample) {
        echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>\n";
        echo "<h3>Message ID: {$sample['id']}</h3>\n";
        echo "<p><strong>Stored Port:</strong> {$sample['port_num']}</p>\n";
        echo "<p><strong>Topic:</strong> " . substr($sample['topic'], 0, 60) . "...</p>\n";
        echo "<p><strong>Payload Length:</strong> {$sample['payload_length']} bytes</p>\n";
        
        $payloadBinary = hex2bin($sample['payload_hex']);
        
        if ($payloadBinary !== false) {
            echo "<h4>Re-parsing with Decoder:</h4>\n";
            
            // Try to parse as ServiceEnvelope
            $env = $decoder->parseEnvelope($payloadBinary);
            if ($env !== null) {
                echo "<p>✅ ServiceEnvelope parsed successfully</p>\n";
                echo "<p>Channel ID: " . $env->getChannelId() . "</p>\n";
                echo "<p>Gateway ID: " . $env->getGatewayId() . "</p>\n";
                
                $decoded = $decoder->getDecodedData($env);
                if ($decoded !== null) {
                    [$data, $packet] = $decoded;
                    echo "<p>✅ Data decoded successfully</p>\n";
                    echo "<p><strong>Real Port Number:</strong> " . $data->getPortnum() . "</p>\n";
                    echo "<p><strong>Payload Length:</strong> " . strlen($data->getPayload()) . " bytes</p>\n";
                    echo "<p><strong>From:</strong> " . $packet->getFrom() . "</p>\n";
                    echo "<p><strong>To:</strong> " . $packet->getTo() . "</p>\n";
                    
                    // Compare with stored values
                    if ($data->getPortnum() != $sample['port_num']) {
                        echo "<p style='color: red;'>❌ PORT MISMATCH! Real: " . $data->getPortnum() . ", Stored: {$sample['port_num']}</p>\n";
                    } else {
                        echo "<p style='color: green;'>✅ Port numbers match</p>\n";
                    }
                } else {
                    echo "<p style='color: red;'>❌ Failed to decode data</p>\n";
                }
            } else {
                echo "<p style='color: red;'>❌ Failed to parse ServiceEnvelope</p>\n";
            }
        } else {
            echo "<p style='color: red;'>❌ Failed to convert hex to binary</p>\n";
        }
        
        echo "</div>\n";
    }
    
    echo "</body></html>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
