<?php
/**
 * Check Database Schema and Node Processing
 */

// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Database Schema Investigation ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Check all table schemas
    $tables = ['nodes', 'positions', 'telemetry', 'raw_messages'];
    
    foreach ($tables as $table) {
        echo "\n=== $table table schema ===\n";
        try {
            $columns = $db->pdo()->query("PRAGMA table_info($table)")->fetchAll();
            foreach ($columns as $col) {
                echo "  {$col['name']} ({$col['type']}) " . ($col['notnull'] ? 'NOT NULL' : 'NULL') . "\n";
            }
        } catch (\Throwable $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Check the structure of a recent nodeinfo JSON message
    echo "\n=== JSON Message Structure Analysis ===\n";
    $nodeinfo = $db->pdo()->query("
        SELECT raw_message 
        FROM raw_messages 
        WHERE message_type = 'nodeinfo' 
        AND topic LIKE '%/json/%'
        ORDER BY id DESC 
        LIMIT 1
    ")->fetchColumn();
    
    if ($nodeinfo) {
        echo "Raw JSON structure:\n";
        $data = json_decode($nodeinfo, true);
        if ($data) {
            echo "Keys: " . implode(', ', array_keys($data)) . "\n";
            
            if (isset($data['payload'])) {
                echo "Payload type: " . gettype($data['payload']) . "\n";
                if (is_string($data['payload'])) {
                    $payload = base64_decode($data['payload']);
                    echo "Decoded payload length: " . strlen($payload) . " bytes\n";
                } else {
                    echo "Payload value: " . json_encode($data['payload']) . "\n";
                }
            }
            
            echo "Full JSON sample:\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    // Check if positions table has the right columns
    echo "\n=== Position Table Data Sample ===\n";
    $positions = $db->pdo()->query("
        SELECT * FROM positions 
        ORDER BY id DESC 
        LIMIT 3
    ")->fetchAll();
    
    foreach ($positions as $pos) {
        echo "Position: " . json_encode($pos) . "\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
