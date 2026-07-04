<?php
require_once 'bootstrap.php';
use App\Database;
use App\Support\Env;

$db = new Database(Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite');
$pdo = $db->pdo();

echo "Position table info:\n";
$stmt = $pdo->query('PRAGMA table_info(positions)');
while ($row = $stmt->fetch()) {
    echo $row['name'] . ' (' . $row['type'] . ")\n";
}

echo "\nSample position data:\n";
$stmt = $pdo->query('SELECT * FROM positions LIMIT 3');
while ($row = $stmt->fetch()) {
    print_r($row);
}

echo "\nNodes matching search terms:\n";
$searchTerms = ['CNYmesh', 'AK2X', 'NY/CNY'];
$searchConditions = [];
$params = [];

foreach ($searchTerms as $term) {
    $searchConditions[] = "(long_name LIKE ? OR short_name LIKE ? OR hardware LIKE ? OR node_num LIKE ?)";
    $params[] = "%$term%";
    $params[] = "%$term%"; 
    $params[] = "%$term%";
    $params[] = "%$term%";
}

$whereClause = implode(' OR ', $searchConditions);

$stmt = $pdo->prepare("
    SELECT node_num, long_name, short_name, hardware, last_seen
    FROM nodes 
    WHERE $whereClause
    ORDER BY last_seen DESC
    LIMIT 5
");

$stmt->execute($params);
$nodes = $stmt->fetchAll();

foreach ($nodes as $node) {
    echo "Node: " . $node['node_num'] . " - " . $node['long_name'] . "\n";
    
    // Check position for this node
    $posStmt = $pdo->prepare("SELECT * FROM positions WHERE node_num = ? LIMIT 1");
    $posStmt->execute([$node['node_num']]);
    $position = $posStmt->fetch();
    
    if ($position) {
        echo "  Position found: lat=" . $position['lat'] . ", lon=" . $position['lon'] . "\n";
    } else {
        echo "  No position found\n";
    }
}
?>
