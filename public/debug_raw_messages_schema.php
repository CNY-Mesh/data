<?php
require_once 'bootstrap.php';

$db = new \App\Database();

echo "<h2>Raw Messages Table Schema</h2>\n";

// Get table schema
$stmt = $db->pdo()->query("PRAGMA table_info(raw_messages)");
$columns = $stmt->fetchAll();

echo "<table border='1'>\n";
echo "<tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th><th>Primary Key</th></tr>\n";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['name']}</td>";
    echo "<td>{$col['type']}</td>";
    echo "<td>" . ($col['notnull'] ? 'YES' : 'NO') . "</td>";
    echo "<td>{$col['dflt_value']}</td>";
    echo "<td>" . ($col['pk'] ? 'YES' : 'NO') . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<h3>Sample Data</h3>\n";
$stmt = $db->pdo()->query("SELECT * FROM raw_messages LIMIT 3");
$rows = $stmt->fetchAll();

if (!empty($rows)) {
    echo "<table border='1'>\n";
    echo "<tr>";
    foreach (array_keys($rows[0]) as $key) {
        if (!is_numeric($key)) {
            echo "<th>$key</th>";
        }
    }
    echo "</tr>\n";
    
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                echo "<td>" . htmlspecialchars(substr((string)$value, 0, 100)) . "</td>";
            }
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "No data found in raw_messages table.\n";
}
?>
