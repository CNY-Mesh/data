<?php

require_once __DIR__ . '/bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== DATABASE CONNECTION TEST ===\n\n";

try {
    // Test the same path as RawDataController
    $path = Env::get('SQLITE_PATH', __DIR__ . '/data/meshtastic.sqlite');
    echo "Database path: $path\n";
    echo "File exists: " . (file_exists($path) ? "Yes" : "No") . "\n";
    echo "File readable: " . (is_readable($path) ? "Yes" : "No") . "\n\n";
    
    $dsn = 'sqlite:' . $path;
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    // Test table existence and data
    $tables = ['positions', 'telemetry', 'raw_messages', 'text_messages'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "$table: $count records\n";
            
            if ($table === 'raw_messages' && $count > 0) {
                // Show a sample record
                $stmt = $pdo->query("SELECT * FROM $table LIMIT 1");
                $sample = $stmt->fetch();
                echo "  Sample record columns: " . implode(', ', array_keys($sample)) . "\n";
            }
        } catch (Exception $e) {
            echo "$table: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== TEST ANALYTICS QUERIES ===\n";
    
    // Test the analytics queries directly
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM raw_messages");
        $result = $stmt->fetch();
        echo "Raw messages count query: " . $result['count'] . "\n";
        
        $stmt = $pdo->query("
            SELECT 
                COUNT(DISTINCT node_from) as unique_senders,
                MIN(processed_at) as first_message,
                MAX(processed_at) as last_message
            FROM raw_messages 
            WHERE node_from IS NOT NULL
        ");
        $stats = $stmt->fetch();
        echo "Unique senders: " . $stats['unique_senders'] . "\n";
        echo "First message: " . ($stats['first_message'] ? date('Y-m-d H:i:s', $stats['first_message']) : 'NULL') . "\n";
        echo "Last message: " . ($stats['last_message'] ? date('Y-m-d H:i:s', $stats['last_message']) : 'NULL') . "\n";
        
    } catch (Exception $e) {
        echo "Analytics query error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}

echo "\n=== ENVIRONMENT INFO ===\n";
echo "SQLITE_PATH env: " . (Env::get('SQLITE_PATH') ?: 'Not set') . "\n";
echo "DB_DSN env: " . (Env::get('DB_DSN') ?: 'Not set') . "\n";
echo "Current working directory: " . getcwd() . "\n";
echo "Script directory: " . __DIR__ . "\n";
