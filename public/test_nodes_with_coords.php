<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

// Find nodes with actual position data
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

echo "Finding nodes with position history and real coordinates...\n";
echo str_repeat("=", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT node_num, COUNT(*) as total_positions,
           COUNT(CASE WHEN lat IS NOT NULL AND lon IS NOT NULL THEN 1 END) as real_positions,
           MAX(DATETIME(time, 'unixepoch')) as latest_time
    FROM position_history 
    GROUP BY node_num 
    HAVING real_positions > 0
    ORDER BY real_positions DESC, latest_time DESC
    LIMIT 10
");
$stmt->execute();
$nodes = $stmt->fetchAll();

printf("%-12s %-8s %-8s %-20s\n", "Node", "Total", "Real", "Latest Time");
echo str_repeat("-", 60) . "\n";

foreach ($nodes as $node) {
    printf("%-12s %-8s %-8s %-20s\n", 
        $node['node_num'], 
        $node['total_positions'], 
        $node['real_positions'],
        $node['latest_time']
    );
}

// Test filtering with the first node that has real coordinates
if (count($nodes) > 0) {
    $testNode = $nodes[0]['node_num'];
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Testing filtering with node $testNode...\n\n";
    
    // Get unfiltered positions
    $stmt = $pdo->prepare("
        SELECT ph.*, 
            DATETIME(ph.time, 'unixepoch') as position_time
        FROM position_history ph 
        WHERE ph.node_num = ? AND lat IS NOT NULL AND lon IS NOT NULL
        ORDER BY ph.time DESC 
        LIMIT 10
    ");
    $stmt->execute([$testNode]);
    $unfiltered = $stmt->fetchAll();
    
    echo "Unfiltered positions with real coordinates:\n";
    printf("%-15s %-12s %-12s %-8s\n", "Time", "Latitude", "Longitude", "Altitude");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($unfiltered as $pos) {
        printf("%-15s %-12.6f %-12.6f %-8s\n", 
            substr($pos['position_time'], 11, 8),
            $pos['lat'],
            $pos['lon'],
            $pos['altitude'] ?? 'NULL'
        );
    }
    
    // Get filtered positions
    $stmt = $pdo->prepare("
        WITH filtered_positions AS (
            SELECT ph.*, 
                DATETIME(ph.time, 'unixepoch') as position_time,
                LAG(ph.lat) OVER (ORDER BY ph.time) as prev_lat,
                LAG(ph.lon) OVER (ORDER BY ph.time) as prev_lon,
                LAG(ph.altitude) OVER (ORDER BY ph.time) as prev_alt
            FROM position_history ph 
            WHERE ph.node_num = ? AND lat IS NOT NULL AND lon IS NOT NULL
            ORDER BY ph.time
        )
        SELECT * FROM filtered_positions
        WHERE (
            prev_lat IS NULL OR 
            COALESCE(lat, 0) != COALESCE(prev_lat, 0) OR 
            COALESCE(lon, 0) != COALESCE(prev_lon, 0) OR 
            COALESCE(altitude, 0) != COALESCE(prev_alt, 0)
        )
        ORDER BY time DESC 
        LIMIT 10
    ");
    $stmt->execute([$testNode]);
    $filtered = $stmt->fetchAll();
    
    echo "\nFiltered positions (duplicates removed):\n";
    printf("%-15s %-12s %-12s %-8s\n", "Time", "Latitude", "Longitude", "Altitude");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($filtered as $pos) {
        printf("%-15s %-12.6f %-12.6f %-8s\n", 
            substr($pos['position_time'], 11, 8),
            $pos['lat'],
            $pos['lon'],
            $pos['altitude'] ?? 'NULL'
        );
    }
    
    echo "\nFiltering removed " . (count($unfiltered) - count($filtered)) . " duplicate positions.\n";
}
?>
