<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

// Check the schema of nodes table
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

echo "Checking nodes table schema...\n";
echo str_repeat("=", 60) . "\n";

try {
    $stmt = $pdo->prepare("PRAGMA table_info(nodes)");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    if (count($columns) > 0) {
        echo "Columns in nodes table:\n";
        printf("%-20s %-15s %-10s %-10s\n", "Column", "Type", "NotNull", "Default");
        echo str_repeat("-", 60) . "\n";
        
        foreach ($columns as $col) {
            printf("%-20s %-15s %-10s %-10s\n", 
                $col['name'], 
                $col['type'], 
                $col['notnull'] ? 'YES' : 'NO',
                $col['dflt_value'] ?? 'NULL'
            );
        }
    } else {
        echo "Table nodes not found or has no columns.\n";
    }
    
    // Show some sample data
    echo "\nSample data from nodes:\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->prepare("SELECT * FROM nodes LIMIT 2");
    $stmt->execute();
    $sample = $stmt->fetchAll();
    
    if (count($sample) > 0) {
        $first = $sample[0];
        echo "Sample row columns: " . implode(', ', array_keys($first)) . "\n";
    } else {
        echo "No data in nodes table.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
