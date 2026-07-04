<?php
// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

require_once '../bootstrap.php';

header('Content-Type: application/json');

try {
    $db = new App\Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
    
    $analysis = [];
    
    // Get topic patterns
    $stmt = $db->pdo()->query("
        SELECT 
            topic,
            COUNT(*) as message_count,
            MIN(processed_at) as first_seen,
            MAX(processed_at) as last_seen,
            message_type,
            COUNT(DISTINCT node_from) as unique_senders
        FROM raw_messages 
        WHERE topic IS NOT NULL 
        GROUP BY topic, message_type 
        ORDER BY message_count DESC
    ");
    
    $analysis['topic_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check which tables are missing topic fields
    $tables = ['positions', 'telemetry', 'text_messages', 'neighbor_info'];
    $analysis['table_schemas'] = [];
    
    foreach ($tables as $table) {
        $stmt = $db->pdo()->query("PRAGMA table_info({$table})");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $has_topic = false;
        $column_names = [];
        foreach ($columns as $col) {
            $column_names[] = $col['name'];
            if (stripos($col['name'], 'topic') !== false) {
                $has_topic = true;
            }
        }
        
        $analysis['table_schemas'][$table] = [
            'has_topic_field' => $has_topic,
            'columns' => $column_names,
            'needs_topic_field' => !$has_topic
        ];
    }
    
    // Sample topic analysis - what channels/regions are we seeing?
    $stmt = $db->pdo()->query("
        SELECT 
            CASE 
                WHEN topic LIKE 'msh/US/%' THEN 'US Region'
                WHEN topic LIKE 'msh/EU_%' THEN 'EU Region'
                WHEN topic LIKE 'msh/2/c/%' THEN 'Encrypted Channel'
                WHEN topic LIKE 'msh/2/e/%' THEN 'Encrypted Emergency'
                ELSE 'Other'
            END as topic_category,
            COUNT(*) as count
        FROM raw_messages 
        WHERE topic IS NOT NULL
        GROUP BY topic_category
        ORDER BY count DESC
    ");
    
    $analysis['topic_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts by table
    $analysis['record_counts'] = [
        'raw_messages' => $db->pdo()->query('SELECT COUNT(*) FROM raw_messages')->fetchColumn(),
        'positions' => $db->pdo()->query('SELECT COUNT(*) FROM positions')->fetchColumn(),
        'text_messages' => $db->pdo()->query('SELECT COUNT(*) FROM text_messages')->fetchColumn(),
        'telemetry' => $db->pdo()->query('SELECT COUNT(*) FROM telemetry')->fetchColumn()
    ];
    
    // Check if any positions/texts have corresponding raw messages with topics
    $stmt = $db->pdo()->query("
        SELECT 
            rm.topic,
            COUNT(*) as positions_with_topic
        FROM positions p
        JOIN raw_messages rm ON p.node_num = rm.node_from 
            AND ABS(p.time - rm.rx_time) < 5  -- within 5 seconds
        WHERE rm.topic IS NOT NULL
        GROUP BY rm.topic
        ORDER BY positions_with_topic DESC
        LIMIT 10
    ");
    $analysis['position_topic_correlation'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($analysis, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
