<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

// Check the schema of position_history table
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

echo "Checking position_history table schema...\n";
echo str_repeat("=", 60) . "\n";

try {
    $stmt = $pdo->prepare("PRAGMA table_info(position_history)");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    if (count($columns) > 0) {
        echo "Columns in position_history table:\n";
        printf("%-15s %-15s %-10s %-10s\n", "Column", "Type", "NotNull", "Default");
        echo str_repeat("-", 60) . "\n";
        
        foreach ($columns as $col) {
            printf("%-15s %-15s %-10s %-10s\n", 
                $col['name'], 
                $col['type'], 
                $col['notnull'] ? 'YES' : 'NO',
                $col['dflt_value'] ?? 'NULL'
            );
        }
    } else {
        echo "Table position_history not found or has no columns.\n";
    }
    
    // Also check if table exists
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='position_history'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "\nTable position_history does not exist!\n";
    }
    
    // Show some sample data
    echo "\nSample data from position_history:\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->prepare("SELECT * FROM position_history LIMIT 3");
    $stmt->execute();
    $sample = $stmt->fetchAll();
    
    if (count($sample) > 0) {
        $first = $sample[0];
        echo "Sample row columns: " . implode(', ', array_keys($first)) . "\n";
        
        foreach ($sample as $i => $row) {
            echo "\nRow " . ($i + 1) . ":\n";
            foreach ($row as $key => $value) {
                if (!is_numeric($key)) { // Skip numeric indices
                    echo "  $key: " . ($value ?? 'NULL') . "\n";
                }
            }
        }
    } else {
        echo "No data in position_history table.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
