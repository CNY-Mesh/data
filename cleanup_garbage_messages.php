<?php
// Clean up garbage text messages
// Run this script to remove unwanted test messages from the database

require_once __DIR__ . '/bootstrap.php';

echo "Cleaning up garbage text messages...\n";

try {
    $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
    $db = new \App\Database($dsn);
    $pdo = $db->pdo();
    
    // Define garbage messages to remove
    $garbage_messages = [
        [
            'node_from' => 2224786404,
            'message' => 'TEST 2',
            'description' => 'Test messages from node 2224786404'
        ],
        [
            'node_from' => 142224248,
            'message' => 'Fake from MQTTool',
            'description' => 'Fake messages from MQTTool'
        ]
    ];
    
    $total_deleted = 0;
    
    // Prepare delete statement
    $stmt = $pdo->prepare('DELETE FROM text_messages WHERE node_from = ? AND message = ?');
    
    foreach ($garbage_messages as $garbage) {
        echo "Removing: {$garbage['description']}\n";
        echo "  Node: {$garbage['node_from']}, Message: '{$garbage['message']}'\n";
        
        // Execute deletion
        $stmt->execute([$garbage['node_from'], $garbage['message']]);
        $deleted_count = $stmt->rowCount();
        
        echo "  Deleted: $deleted_count messages\n";
        $total_deleted += $deleted_count;
    }
    
    echo "\nTotal messages deleted: $total_deleted\n";
    
    // Show remaining message counts by node
    echo "\nRemaining message counts by top nodes:\n";
    $stmt = $pdo->query("
        SELECT 
            node_from,
            COUNT(*) as message_count,
            n.long_name,
            n.short_name
        FROM text_messages tm
        LEFT JOIN nodes n ON tm.node_from = n.node_num
        GROUP BY node_from
        ORDER BY message_count DESC
        LIMIT 10
    ");
    
    $results = $stmt->fetchAll();
    foreach ($results as $row) {
        $node_name = $row['long_name'] ?: $row['short_name'] ?: 'Unknown';
        echo "  Node {$row['node_from']} ({$node_name}): {$row['message_count']} messages\n";
    }
    
    // Show total message count
    $total_messages = $pdo->query('SELECT COUNT(*) FROM text_messages')->fetchColumn();
    echo "\nTotal text messages remaining: $total_messages\n";
    
    echo "\nCleanup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
?>
