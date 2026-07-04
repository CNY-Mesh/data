<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Database;

// Test the position history filtering
$db = new Database('sqlite:' . __DIR__ . '/../data/meshtastic.sqlite');
$pdo = $db->pdo();

// Test with a known node that has position history
$testNodeNum = 3126879184; // One of our tracked nodes

echo "Testing position history duplicate filtering for node: $testNodeNum\n";
echo str_repeat("=", 60) . "\n";

// First, let's see all position history for this node
$stmt = $pdo->prepare("
    SELECT ph.*, 
        DATETIME(ph.time, 'unixepoch') as position_time,
        DATETIME(ph.recorded_at, 'unixepoch') as recorded_time
    FROM position_history ph 
    WHERE ph.node_num = ? 
    ORDER BY ph.time DESC 
    LIMIT 20
");
$stmt->execute([$testNodeNum]);
$allPositions = $stmt->fetchAll();

echo "Total positions found: " . count($allPositions) . "\n\n";

if (count($allPositions) > 0) {
    echo "Last 10 positions (unfiltered):\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-12s %-12s %-8s\n", "Time", "Latitude", "Longitude", "Altitude");
    echo str_repeat("-", 80) . "\n";
    
    foreach (array_slice($allPositions, 0, 10) as $pos) {
        printf("%-20s %-12s %-12s %-8s\n", 
            substr($pos['position_time'], 11, 8), // Just time part
            $pos['lat'] ? number_format($pos['lat'], 6) : 'NULL',
            $pos['lon'] ? number_format($pos['lon'], 6) : 'NULL',
            $pos['altitude'] ?? 'NULL'
        );
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

// Now test the filtered query
$stmt = $pdo->prepare("
    WITH filtered_positions AS (
        SELECT ph.*, 
            DATETIME(ph.time, 'unixepoch') as position_time,
            DATETIME(ph.recorded_at, 'unixepoch') as recorded_time,
            LAG(ph.lat) OVER (ORDER BY ph.time) as prev_lat,
            LAG(ph.lon) OVER (ORDER BY ph.time) as prev_lon,
            LAG(ph.altitude) OVER (ORDER BY ph.time) as prev_alt
        FROM position_history ph 
        WHERE ph.node_num = ?
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
    LIMIT 20
");
$stmt->execute([$testNodeNum]);
$filteredPositions = $stmt->fetchAll();

echo "Filtered positions found: " . count($filteredPositions) . "\n\n";

if (count($filteredPositions) > 0) {
    echo "Filtered positions (duplicates removed):\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-12s %-12s %-8s\n", "Time", "Latitude", "Longitude", "Altitude");
    echo str_repeat("-", 80) . "\n";
    
    foreach (array_slice($filteredPositions, 0, 10) as $pos) {
        printf("%-20s %-12s %-12s %-8s\n", 
            substr($pos['position_time'], 11, 8), // Just time part
            $pos['lat'] ? number_format($pos['lat'], 6) : 'NULL',
            $pos['lon'] ? number_format($pos['lon'], 6) : 'NULL',
            $pos['altitude'] ?? 'NULL'
        );
    }
}

echo "\nFiltering removed " . (count($allPositions) - count($filteredPositions)) . " duplicate positions.\n";
