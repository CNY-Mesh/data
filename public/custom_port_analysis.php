<?php
/**
 * Custom Port Analysis
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;
use App\Handlers\CustomPortHandler;

echo "=== Custom Port Analysis ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Create table if it doesn't exist
    $db->pdo()->exec("
        CREATE TABLE IF NOT EXISTS custom_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            port_num INTEGER NOT NULL,
            node_from INTEGER NOT NULL,
            payload_length INTEGER NOT NULL,
            payload_type TEXT NOT NULL,
            has_text BOOLEAN DEFAULT 0,
            has_binary BOOLEAN DEFAULT 0,
            created_at INTEGER NOT NULL
        )
    ");
    
    $customHandler = new CustomPortHandler($db);
    
    // Get current statistics
    echo "=== Custom Port Statistics (Last 7 Days) ===\n";
    $stats = $customHandler->getCustomPortStats();
    
    if (empty($stats)) {
        echo "No custom port data collected yet. Data will appear as unknown ports are encountered.\n\n";
    } else {
        printf("%-5s %-15s %-8s %-6s %-10s %-15s %-6s %-8s\n", 
            "Port", "Type", "Count", "Nodes", "Avg Size", "Latest", "Text", "Binary");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($stats as $stat) {
            $latest = date('m-d H:i', $stat['latest']);
            printf("%-5d %-15s %-8d %-6d %-10.1f %-15s %-6d %-8d\n",
                $stat['port_num'],
                substr($stat['payload_type'], 0, 15),
                $stat['count'],
                $stat['unique_nodes'],
                $stat['avg_length'],
                $latest,
                $stat['text_count'],
                $stat['binary_count']
            );
        }
    }
    
    // Show unknown ports from raw_messages for comparison
    echo "\n=== Recent Unknown Ports (from raw_messages) ===\n";
    $unknownPorts = $db->pdo()->query("
        SELECT 
            port_num,
            COUNT(*) as count,
            COUNT(DISTINCT CASE 
                WHEN topic LIKE '%json%' THEN json_extract(raw_data, '$.from')
                ELSE 'binary'
            END) as sources,
            MAX(processed_at) as latest,
            AVG(LENGTH(raw_data)) as avg_size
        FROM raw_messages 
        WHERE port_num NOT IN (0, 1, 3, 4, 67, 70, 71, 73)  -- Known handled ports
        AND port_num IS NOT NULL
        AND processed_at > strftime('%s', 'now', '-24 hours')
        GROUP BY port_num
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();
    
    if (!empty($unknownPorts)) {
        printf("%-5s %-8s %-8s %-15s %-10s\n", "Port", "Count", "Sources", "Latest", "Avg Size");
        echo str_repeat("-", 50) . "\n";
        
        foreach ($unknownPorts as $port) {
            $latest = date('H:i:s', $port['latest']);
            printf("%-5d %-8d %-8d %-15s %-10.1f\n",
                $port['port_num'],
                $port['count'],
                $port['sources'],
                $latest,
                $port['avg_size']
            );
        }
    }
    
    // Detailed payload analysis for high-traffic unknown ports
    echo "\n=== Payload Analysis for Unknown Ports ===\n";
    $detailedAnalysis = $db->pdo()->query("
        SELECT 
            port_num,
            SUBSTR(raw_data, 1, 100) as sample_data,
            LENGTH(raw_data) as size,
            processed_at
        FROM raw_messages 
        WHERE port_num IN (31, 36, 46, 52, 65, 940876)
        AND processed_at > strftime('%s', 'now', '-24 hours')
        ORDER BY port_num, processed_at DESC
        LIMIT 5
    ")->fetchAll();
    
    foreach ($detailedAnalysis as $analysis) {
        echo "\nPort {$analysis['port_num']} (Size: {$analysis['size']} bytes):\n";
        
        // Try to determine if it's JSON
        $decoded = json_decode($analysis['sample_data'], true);
        if ($decoded !== null) {
            echo "  Type: JSON\n";
            echo "  Keys: " . implode(', ', array_keys($decoded)) . "\n";
            if (isset($decoded['decoded']['payload'])) {
                echo "  Has payload: Yes\n";
            }
        } else {
            echo "  Type: Binary/Raw\n";
            echo "  Hex: " . bin2hex(substr($analysis['sample_data'], 0, 32)) . "\n";
            echo "  Printable: " . (ctype_print($analysis['sample_data']) ? 'Yes' : 'No') . "\n";
        }
        
        echo "  Time: " . date('Y-m-d H:i:s', $analysis['processed_at']) . "\n";
    }
    
    // Port recommendations
    echo "\n=== Recommendations ===\n";
    $recommendations = [];
    
    foreach ($unknownPorts as $port) {
        $portNum = $port['port_num'];
        
        if ($portNum == 31) {
            $recommendations[] = "Port 31: Could be REPLY_APP (32 -1). Check if it's a custom reply mechanism.";
        } elseif ($portNum == 36) {
            $recommendations[] = "Port 36: Close to PAXCOUNTER_APP (34). Might be a custom sensor application.";
        } elseif ($portNum == 65) {
            $recommendations[] = "Port 65: STORE_FORWARD_APP - This is an official port that should be handled.";
        } elseif ($portNum > 256) {
            $recommendations[] = "Port $portNum: Very high port number suggests private/custom application.";
        } else {
            $recommendations[] = "Port $portNum: Unknown application, analyze payload patterns.";
        }
    }
    
    foreach ($recommendations as $rec) {
        echo "- $rec\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
