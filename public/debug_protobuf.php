<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

header('Content-Type: text/html; charset=utf-8');

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "<!DOCTYPE html><html><head><title>Protobuf Parse Debug</title></head><body>\n";
    echo "<h1>PROTOBUF PARSING DEBUG</h1>\n\n";
    
    // Get a sample raw message
    $stmt = $pdo->prepare("
        SELECT id, topic, payload_hex, payload_length, port_num, node_from, node_to
        FROM raw_messages 
        WHERE payload_length > 0 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $samples = $stmt->fetchAll();
    
    echo "<h2>Sample Raw Messages with Payloads</h2>\n";
    
    foreach ($samples as $sample) {
        echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>\n";
        echo "<h3>Message ID: {$sample['id']}</h3>\n";
        echo "<p><strong>Topic:</strong> {$sample['topic']}</p>\n";
        echo "<p><strong>Current Port:</strong> {$sample['port_num']}</p>\n";
        echo "<p><strong>Node From:</strong> {$sample['node_from']}</p>\n";
        echo "<p><strong>Payload Length:</strong> {$sample['payload_length']} bytes</p>\n";
        
        $payloadHex = $sample['payload_hex'];
        echo "<p><strong>Payload Hex (first 100 chars):</strong> " . substr($payloadHex, 0, 100) . "...</p>\n";
        
        // Try to manually parse the protobuf
        $binaryData = hex2bin($payloadHex);
        if ($binaryData !== false) {
            echo "<h4>Manual Protobuf Analysis:</h4>\n";
            echo "<pre>\n";
            
            // Show first 32 bytes as hex and binary
            $first32 = substr($binaryData, 0, 32);
            echo "First 32 bytes (hex): " . bin2hex($first32) . "\n";
            echo "First 32 bytes (as decimal): ";
            for ($i = 0; $i < min(32, strlen($first32)); $i++) {
                echo ord($first32[$i]) . " ";
            }
            echo "\n\n";
            
            // Try to find protobuf patterns
            // Look for field numbers 1-10 (common in MeshPacket)
            echo "Looking for protobuf field patterns:\n";
            for ($i = 0; $i < min(20, strlen($binaryData)); $i++) {
                $byte = ord($binaryData[$i]);
                $fieldNum = $byte >> 3;
                $wireType = $byte & 0x07;
                
                if ($fieldNum > 0 && $fieldNum <= 20 && $wireType <= 5) {
                    echo "Byte $i: Field $fieldNum, Wire Type $wireType (0x" . sprintf('%02x', $byte) . ")\n";
                }
            }
            
            echo "</pre>\n";
        }
        echo "</div>\n";
    }
    
    // Check if we can find any messages that should have been position/telemetry
    echo "<h2>Position/Telemetry Cross-Reference</h2>\n";
    
    $stmt = $pdo->query("
        SELECT p.node_num, p.latitude, p.longitude, p.created_at,
               r.id as raw_id, r.topic, r.port_num
        FROM positions p
        LEFT JOIN raw_messages r ON p.node_num = r.node_from 
        AND ABS(strftime('%s', p.created_at) - strftime('%s', r.created_at)) < 5
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    
    $matches = $stmt->fetchAll();
    if (!empty($matches)) {
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Node</th><th>Position Time</th><th>Raw Message ID</th><th>Raw Topic</th><th>Raw Port</th></tr>\n";
        foreach ($matches as $match) {
            $nodeHex = "!" . base_convert($match['node_num'], 10, 16);
            echo "<tr>\n";
            echo "<td>{$nodeHex}</td>\n";
            echo "<td>{$match['created_at']}</td>\n";
            echo "<td>" . ($match['raw_id'] ?: 'No match') . "</td>\n";
            echo "<td>" . ($match['topic'] ?: 'N/A') . "</td>\n";
            echo "<td>" . ($match['port_num'] ?: 'N/A') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No time-matched correlations found between positions and raw messages.</p>\n";
    }
    
    echo "</body></html>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
