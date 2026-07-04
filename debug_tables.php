<?php
// Check table structures
$pdo = new PDO('sqlite:data/meshtastic.sqlite');

echo "=== POSITIONS TABLE ===\n";
$stmt = $pdo->query('PRAGMA table_info(positions)');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['name'] . ' (' . $col['type'] . ')' . "\n";
}

echo "\n=== NODES TABLE ===\n";
$stmt = $pdo->query('PRAGMA table_info(nodes)');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['name'] . ' (' . $col['type'] . ')' . "\n";
}

echo "\n=== SAMPLE POSITION DATA ===\n";
$stmt = $pdo->query('SELECT * FROM positions LIMIT 3');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row) {
    print_r($row);
}
?>
