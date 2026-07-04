<?php
header('Content-Type: text/html; charset=utf-8');

$logFile = __DIR__ . '/../debug.log';
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
$refresh = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <title>MQTT Debug Logs</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; margin: 20px; }
        .container { max-width: 1200px; }
        .log-entry { margin: 2px 0; padding: 2px; border-left: 3px solid #333; }
        .timestamp { color: #888; }
        .success { border-left-color: #00ff00; }
        .error { border-left-color: #ff0000; color: #ff6666; }
        .warning { border-left-color: #ffaa00; color: #ffcc66; }
        .debug { border-left-color: #0088ff; color: #66ccff; }
        .controls { background: #333; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .controls a { color: #00ff00; margin-right: 15px; text-decoration: none; }
        .controls a:hover { text-decoration: underline; }
        .stats { background: #333; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
    </style>
    <?php if ($refresh > 0): ?>
    <meta http-equiv="refresh" content="<?= $refresh ?>">
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <h1>MQTT Worker Debug Logs</h1>
        
        <div class="controls">
            <strong>Lines:</strong>
            <a href="?lines=50">50</a> |
            <a href="?lines=100">100</a> |
            <a href="?lines=500">500</a> |
            <a href="?lines=1000">1000</a> |
            <a href="?lines=all">All</a>
            
            <strong>Auto-refresh:</strong>
            <a href="?lines=<?= $lines ?>&refresh=5">5s</a> |
            <a href="?lines=<?= $lines ?>&refresh=10">10s</a> |
            <a href="?lines=<?= $lines ?>&refresh=30">30s</a> |
            <a href="?lines=<?= $lines ?>">Off</a>
        </div>

        <?php if (file_exists($logFile)): ?>
            <div class="stats">
                <strong>Log file:</strong> <?= $logFile ?><br>
                <strong>Size:</strong> <?= number_format(filesize($logFile)) ?> bytes<br>
                <strong>Last modified:</strong> <?= date('Y-m-d H:i:s', filemtime($logFile)) ?><br>
                <strong>Current time:</strong> <?= date('Y-m-d H:i:s') ?>
            </div>

            <div class="logs">
                <?php
                $content = file_get_contents($logFile);
                $logLines = explode("\n", $content);
                
                if ($lines !== 'all' && count($logLines) > $lines) {
                    $logLines = array_slice($logLines, -$lines);
                }
                
                foreach ($logLines as $line) {
                    if (trim($line) === '') continue;
                    
                    $class = 'log-entry';
                    if (strpos($line, 'ERROR') !== false || strpos($line, 'Failed') !== false) {
                        $class .= ' error';
                    } elseif (strpos($line, '🎉') !== false || strpos($line, 'SUCCESS') !== false) {
                        $class .= ' success';
                    } elseif (strpos($line, 'WARNING') !== false) {
                        $class .= ' warning';
                    } else {
                        $class .= ' debug';
                    }
                    
                    echo '<div class="' . $class . '">' . htmlspecialchars($line) . '</div>';
                }
                ?>
            </div>
        <?php else: ?>
            <div class="error">Log file not found: <?= $logFile ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
