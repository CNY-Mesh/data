<?php
// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$action = $_POST['action'] ?? '';
$result = null;
$error = null;

if ($action) {
    try {
        $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
        $db = new \App\Database($dsn);
        $pdo = $db->pdo();
        
        switch ($action) {
            case 'clear_raw_messages':
                $stmt = $pdo->exec("DELETE FROM raw_messages");
                $result = "Cleared $stmt raw messages";
                break;
                
            case 'clear_nodes':
                $stmt = $pdo->exec("DELETE FROM nodes");
                $result = "Cleared $stmt nodes";
                break;
                
            case 'clear_positions':
                $stmt = $pdo->exec("DELETE FROM positions");
                $result = "Cleared $stmt positions";
                break;
                
            case 'clear_telemetry':
                $stmt = $pdo->exec("DELETE FROM telemetry");
                $result = "Cleared $stmt telemetry records";
                break;
                
            case 'clear_neighbors':
                $stmt = $pdo->exec("DELETE FROM neighbors");
                $result = "Cleared $stmt neighbor records";
                break;
                
            case 'clear_text_messages':
                $stmt = $pdo->exec("DELETE FROM text_messages");
                $result = "Cleared $stmt text messages";
                break;

            case 'compact_database':
                if (!str_starts_with($dsn, 'sqlite:')) {
                    $result = "Database compaction skipped (non-SQLite DSN)";
                    break;
                }

                $checkpointResult = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)');
                $checkpointText = 'checkpoint status unavailable';
                if ($checkpointResult !== false) {
                    $row = $checkpointResult->fetch(PDO::FETCH_NUM);
                    if (is_array($row) && count($row) >= 3) {
                        $checkpointText = "checkpoint rc={$row[0]}, frames={$row[1]}, checkpointed={$row[2]}";
                    }
                    $checkpointResult->closeCursor();
                }
                unset($checkpointResult);

                $vacuumPdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $vacuumPdo->exec('PRAGMA busy_timeout=5000');
                $vacuumPdo->exec('VACUUM');
                unset($vacuumPdo);
                $result = "Database compacted ($checkpointText; VACUUM complete)";
                break;
                
            case 'clear_all_data':
                $tables = ['raw_messages', 'nodes', 'positions', 'telemetry', 'neighbors', 'text_messages'];
                $totalCleared = 0;
                foreach ($tables as $table) {
                    $stmt = $pdo->exec("DELETE FROM $table");
                    $totalCleared += $stmt;
                }
                // Reset auto-increment counters
                foreach ($tables as $table) {
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
                }
                $result = "Cleared all data ($totalCleared total records)";
                break;
                
            case 'clear_debug_log':
                $logFile = __DIR__ . '/../debug.log';
                if (file_exists($logFile)) {
                    $size = filesize($logFile);
                    file_put_contents($logFile, "# Debug log cleared at " . date('Y-m-d H:i:s') . "\n");
                    $result = "Cleared debug log (" . number_format($size) . " bytes)";
                } else {
                    $result = "Debug log file not found";
                }
                break;
                
            case 'clear_everything':
                // Clear database
                $tables = ['raw_messages', 'nodes', 'positions', 'telemetry', 'neighbors', 'text_messages'];
                $totalCleared = 0;
                foreach ($tables as $table) {
                    $stmt = $pdo->exec("DELETE FROM $table");
                    $totalCleared += $stmt;
                }
                // Reset auto-increment counters
                foreach ($tables as $table) {
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
                }
                
                // Clear debug log
                $logFile = __DIR__ . '/../debug.log';
                $logSize = 0;
                if (file_exists($logFile)) {
                    $logSize = filesize($logFile);
                    file_put_contents($logFile, "# Debug log cleared at " . date('Y-m-d H:i:s') . "\n");
                }
                
                $result = "Cleared everything: $totalCleared database records and " . number_format($logSize) . " bytes of logs";
                break;
                
            default:
                $error = "Unknown action: $action";
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current statistics
$stats = [];
try {
    $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new \App\Database($dsn);
    $pdo = $db->pdo();
    
    $tables = ['raw_messages', 'nodes', 'positions', 'telemetry', 'neighbors', 'text_messages'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $stats[$table] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    // Debug log size
    $logFile = __DIR__ . '/../debug.log';
    $stats['debug_log_size'] = file_exists($logFile) ? filesize($logFile) : 0;
    
} catch (Exception $e) {
    $error = "Error getting stats: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database & Log Cleanup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h2 { margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-item { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #007cba; }
        .stat-label { color: #666; font-size: 0.9em; margin-top: 5px; }
        .button-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        button { background: #dc3545; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #c82333; }
        .danger { background: #ff4444 !important; }
        .danger:hover { background: #cc0000 !important; }
        .result { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .nav-links { text-align: center; margin-bottom: 20px; }
        .nav-links a { margin: 0 10px; padding: 8px 15px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; }
    </style>
    <script>
        function confirmAction(action, description) {
            return confirm(`Are you sure you want to ${description}?\n\nThis action cannot be undone!`);
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Database & Log Cleanup Utility</h1>
        
        <div class="nav-links">
            <a href="debug_logs.php">View Debug Logs</a>
            <a href="web_decode_test.php">Message Decoder</a>
            <a href="../">Dashboard</a>
        </div>

        <?php if ($result): ?>
        <div class="result">
            <strong>Success:</strong> <?= htmlspecialchars($result) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="error">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Current Data Statistics</h2>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['raw_messages'] ?? 0) ?></div>
                    <div class="stat-label">Raw Messages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['nodes'] ?? 0) ?></div>
                    <div class="stat-label">Nodes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['positions'] ?? 0) ?></div>
                    <div class="stat-label">Positions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['telemetry'] ?? 0) ?></div>
                    <div class="stat-label">Telemetry</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['neighbors'] ?? 0) ?></div>
                    <div class="stat-label">Neighbors</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['text_messages'] ?? 0) ?></div>
                    <div class="stat-label">Text Messages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format(($stats['debug_log_size'] ?? 0) / 1024, 1) ?> KB</div>
                    <div class="stat-label">Debug Log Size</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Individual Table Cleanup</h2>
            <div class="button-grid">
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_raw_messages" onclick="return confirmAction('clear_raw_messages', 'clear all raw messages')">
                        Clear Raw Messages
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_nodes" onclick="return confirmAction('clear_nodes', 'clear all nodes')">
                        Clear Nodes
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_positions" onclick="return confirmAction('clear_positions', 'clear all positions')">
                        Clear Positions
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_telemetry" onclick="return confirmAction('clear_telemetry', 'clear all telemetry')">
                        Clear Telemetry
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_neighbors" onclick="return confirmAction('clear_neighbors', 'clear all neighbors')">
                        Clear Neighbors
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_text_messages" onclick="return confirmAction('clear_text_messages', 'clear all text messages')">
                        Clear Text Messages
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Log Management</h2>
            <div class="button-grid">
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_debug_log" onclick="return confirmAction('clear_debug_log', 'clear the debug log')">
                        Clear Debug Log
                    </button>
                </form>
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="compact_database" onclick="return confirmAction('compact_database', 'compact the database (checkpoint + VACUUM) without deleting data')">
                        Compact Database (No Delete)
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>⚠️ Nuclear Options</h2>
            <div class="button-grid">
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_all_data" class="danger" onclick="return confirmAction('clear_all_data', 'clear ALL database data')">
                        Clear All Database Data
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <button type="submit" name="action" value="clear_everything" class="danger" onclick="return confirmAction('clear_everything', 'clear EVERYTHING (database + logs)')">
                        Clear Everything
                    </button>
                </form>
            </div>
            <p style="color: #666; font-size: 0.9em; margin-top: 15px;">
                <strong>Warning:</strong> These actions will permanently delete all data and cannot be undone. 
                Use only when you want to start completely fresh for debugging.
            </p>
        </div>
    </div>
</body>
</html>
