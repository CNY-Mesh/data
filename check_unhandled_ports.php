<?php

require_once __DIR__ . '/bootstrap.php';

use App\Database;

try {
    $db = new Database();
    $pdo = $db->pdo();
    
    echo "=== UNHANDLED PORTS ANALYSIS ===\n\n";
    
    // Known handled ports
    $handledPorts = [3, 4, 67, 70, 71, 73]; // Current handlers
    $newPorts = [1]; // Newly added handlers
    
    $placeholders = str_repeat('?,', count($handledPorts) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT port_num, COUNT(*) as count,
               SUM(CASE WHEN payload_length > 0 THEN 1 ELSE 0 END) as with_payload,
               AVG(payload_length) as avg_length,
               MAX(processed_at) as last_seen
        FROM raw_messages 
        WHERE port_num NOT IN ($placeholders) 
          AND port_num IS NOT NULL
          AND is_json = 0
        GROUP BY port_num 
        ORDER BY count DESC
    ");
    $stmt->execute($handledPorts);
    
    $unhandledPorts = $stmt->fetchAll();
    
    if (empty($unhandledPorts)) {
        echo "✓ All ports with data are being handled!\n";
    } else {
        echo "Found " . count($unhandledPorts) . " unhandled port numbers:\n\n";
        
        foreach ($unhandledPorts as $row) {
            $port = $row['port_num'];
            $count = $row['count'];
            $withPayload = $row['with_payload'];
            $avgLen = round($row['avg_length'], 1);
            $lastSeen = date('Y-m-d H:i:s', $row['last_seen']);
            
            echo sprintf(
                "Port %3d: %5d messages (%d with payload, avg: %s bytes) - Last: %s\n",
                $port,
                $count,
                $withPayload,
                $avgLen,
                $lastSeen
            );
            
            // Show sample payload for ports with data
            if ($withPayload > 0) {
                $sampleStmt = $pdo->prepare("
                    SELECT payload_hex 
                    FROM raw_messages 
                    WHERE port_num = ? AND payload_length > 0 
                    LIMIT 1
                ");
                $sampleStmt->execute([$port]);
                $sample = $sampleStmt->fetch();
                if ($sample) {
                    $hex = substr($sample['payload_hex'], 0, 40);
                    echo "         Sample payload: $hex...\n";
                }
            }
            echo "\n";
        }
        
        echo "\nRECOMMENDATIONS:\n";
        echo "- Add handlers for ports with high message counts\n";
        echo "- Research Meshtastic protocol documentation for unknown ports\n";
        echo "- Create specific tables for structured data if needed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
