<?php
// Simple worker restart utility
require_once __DIR__ . '/../bootstrap.php';

echo "<h1>MQTT Worker Restart Utility</h1>\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_logs') {
        $logFile = __DIR__ . '/../debug.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, "# MQTT Worker Debug Log\n# Started: " . date('Y-m-d H:i:s') . "\n");
            echo "<p style='color: green;'>✅ Debug log cleared successfully</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Debug log file not found</p>\n";
        }
    }
    
    if ($_POST['action'] === 'check_status') {
        $logFile = __DIR__ . '/../debug.log';
        if (file_exists($logFile)) {
            $logSize = filesize($logFile);
            $lastModified = filemtime($logFile);
            $timeSinceModified = time() - $lastModified;
            
            echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<h3>Worker Status</h3>";
            echo "<p><strong>Log file size:</strong> " . number_format($logSize) . " bytes</p>";
            echo "<p><strong>Last modified:</strong> " . date('Y-m-d H:i:s', $lastModified) . " (" . $timeSinceModified . " seconds ago)</p>";
            
            if ($timeSinceModified < 60) {
                echo "<p style='color: green;'>✅ Worker appears to be active (recent log activity)</p>";
            } else {
                echo "<p style='color: red;'>❌ Worker may be inactive (no recent log activity)</p>";
            }
            echo "</div>";
        }
    }
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
button { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; }
.action { background-color: #007cba; color: white; }
.danger { background-color: #dc3545; color: white; }
.warning { background-color: #ffc107; color: black; }
</style>

<form method="post">
    <button type="submit" name="action" value="check_status" class="action">Check Worker Status</button>
    <button type="submit" name="action" value="clear_logs" class="warning">Clear Debug Logs</button>
</form>

<h2>Instructions</h2>
<p><strong>To restart the MQTT worker:</strong></p>
<ol>
    <li>Stop the current worker (Ctrl+C in the terminal running it)</li>
    <li>Clear the debug logs using the button above</li>
    <li>Run: <code>php bin/run.php</code> in the project directory</li>
</ol>

<h2>Recent Debug Log (Last 50 lines)</h2>
<div style="background: #f8f9fa; padding: 10px; border: 1px solid #ddd; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">
<?php
$logFile = __DIR__ . '/../debug.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    echo htmlspecialchars(implode('', $lastLines));
} else {
    echo "Debug log file not found.";
}
?>
</div>

<p><a href="debug_logs.php">View Full Debug Logs</a> | <a href="/">Back to Dashboard</a></p>
