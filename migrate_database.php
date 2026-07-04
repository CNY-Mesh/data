<?php

require_once __DIR__ . '/bootstrap.php';

use App\Database;
use App\Support\Env;

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "Checking database tables...\n";
    
    // Get existing tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $existingTables = array_column($stmt->fetchAll(), 'name');
    
    echo "Existing tables: " . implode(', ', $existingTables) . "\n\n";
    
    // Check if new tables exist
    $newTables = ['raw_messages', 'text_messages'];
    $tablesToCreate = [];
    
    foreach ($newTables as $table) {
        if (!in_array($table, $existingTables)) {
            $tablesToCreate[] = $table;
        }
    }
    
    if (empty($tablesToCreate)) {
        echo "All tables exist. No migration needed.\n";
    } else {
        echo "Creating missing tables: " . implode(', ', $tablesToCreate) . "\n";
        
        // Create raw_messages table
        if (in_array('raw_messages', $tablesToCreate)) {
            $sql = "
                CREATE TABLE raw_messages (
                    id              INTEGER PRIMARY KEY AUTOINCREMENT,
                    topic           TEXT,
                    channel_id      TEXT,
                    gateway_id      TEXT,
                    node_from       INTEGER,
                    node_to         INTEGER,
                    port_num        INTEGER,
                    payload_hex     TEXT,
                    payload_length  INTEGER,
                    is_encrypted    BOOLEAN,
                    is_json         BOOLEAN,
                    message_type    TEXT,
                    rx_time         INTEGER,
                    rx_rssi         REAL,
                    rx_snr          REAL,
                    raw_message     BLOB,
                    processed_at    INTEGER DEFAULT (strftime('%s', 'now'))
                );
            ";
            $pdo->exec($sql);
            echo "✓ Created raw_messages table\n";
            
            // Create indexes
            $indexes = [
                "CREATE INDEX idx_raw_messages_port ON raw_messages(port_num);",
                "CREATE INDEX idx_raw_messages_node_from ON raw_messages(node_from);", 
                "CREATE INDEX idx_raw_messages_channel ON raw_messages(channel_id);",
                "CREATE INDEX idx_raw_messages_time ON raw_messages(rx_time);"
            ];
            
            foreach ($indexes as $index) {
                try {
                    $pdo->exec($index);
                    echo "✓ Created index\n";
                } catch (Exception $e) {
                    echo "⚠ Index already exists or error: " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Create text_messages table
        if (in_array('text_messages', $tablesToCreate)) {
            $sql = "
                CREATE TABLE text_messages (
                    id           INTEGER PRIMARY KEY AUTOINCREMENT,
                    node_from    INTEGER,
                    node_to      INTEGER,
                    message      TEXT,
                    rx_time      INTEGER
                );
            ";
            $pdo->exec($sql);
            echo "✓ Created text_messages table\n";
            
            // Create indexes
            $indexes = [
                "CREATE INDEX idx_text_messages_node_from ON text_messages(node_from);",
                "CREATE INDEX idx_text_messages_time ON text_messages(rx_time);"
            ];
            
            foreach ($indexes as $index) {
                try {
                    $pdo->exec($index);
                    echo "✓ Created index\n";
                } catch (Exception $e) {
                    echo "⚠ Index already exists or error: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "\nMigration completed!\n";
    }
    
    // Show final table list
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $finalTables = array_column($stmt->fetchAll(), 'name');
    echo "\nFinal tables: " . implode(', ', $finalTables) . "\n";
    
    // Show record counts
    echo "\nRecord counts:\n";
    foreach ($finalTables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "  $table: $count records\n";
        } catch (Exception $e) {
            echo "  $table: Error reading table\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
