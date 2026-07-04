<?php
// Emergency cleanup script for critical disk space situation
// This script will immediately clear the raw_messages table and vacuum the database

echo "Emergency Database Cleanup Starting...\n";
echo "Warning: This will delete ALL raw messages to free disk space!\n";
echo "Press Ctrl+C in the next 5 seconds to cancel...\n";

// Give user a chance to cancel
for ($i = 5; $i > 0; $i--) {
    echo "$i...\n";
    sleep(1);
}

echo "Starting cleanup...\n";

try {
    // Initialize database connection
    require_once __DIR__ . '/bootstrap.php';
    
    $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
    $db = new \App\Database($dsn);
    $pdo = $db->pdo();
    
    echo "Connected to database.\n";
    
    // Get count before deletion
    $stmt = $pdo->query("SELECT COUNT(*) FROM raw_messages");
    $beforeCount = $stmt->fetchColumn();
    echo "Raw messages before cleanup: " . number_format($beforeCount) . "\n";
    
    // Clear raw_messages table
    echo "Deleting raw messages...\n";
    $stmt = $pdo->exec("DELETE FROM raw_messages");
    echo "Deleted " . number_format($stmt) . " raw messages.\n";
    
    // Reset auto-increment counter
    echo "Resetting auto-increment counter...\n";
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='raw_messages'");
    
    // VACUUM to reclaim space immediately
    echo "Running VACUUM to reclaim disk space (this may take a moment)...\n";
    $pdo->exec("VACUUM");
    
    echo "Emergency cleanup completed successfully!\n";
    echo "Database should now have significantly more free space.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "If this fails, you may need to manually delete the database file and restart.\n";
}

echo "\nCleanup finished.\n";
?>
