<?php

require_once __DIR__ . '/bootstrap.php';

use App\Database;

try {
    $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
    $db = new Database($dsn);
    $pdo = $db->pdo();
    
    echo "Applying database schema changes...\n";
    
    // Read and execute the schema file
    $schema = file_get_contents(__DIR__ . '/schema/sqlite.sql');
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            echo "⚠ Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
        }
    }
    
    echo "\nSchema updates completed!\n";
    
    // Check if tables exist
    $tables = ['raw_messages', 'text_messages'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' not found\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
