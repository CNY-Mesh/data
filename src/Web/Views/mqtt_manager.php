<?php 
$title = "MQTT Worker Manager";
?>

<style>
.status { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
.status.running { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status.stopped { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.controls { margin: 20px 0; }
.btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
.btn-start { background: #28a745; color: white; }
.btn-stop { background: #dc3545; color: white; }
.btn-restart { background: #007bff; color: white; }
.btn:hover { opacity: 0.8; }
.btn:disabled, .btn.disabled { opacity: 0.5; cursor: not-allowed; }
.log-container { background: #1e1e1e; color: #fff; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
.message { padding: 10px; border-radius: 5px; margin: 10px 0; }
.message.success { background: #d4edda; color: #155724; }
.message.warning { background: #fff3cd; color: #856404; }
.message.info { background: #d1ecf1; color: #0c5460; }
.message pre { margin: 0; background: none; padding: 0; border: none; font-family: monospace; white-space: pre-wrap; }
.refresh { float: right; }
.refresh a { color: #007bff; text-decoration: none; }
.card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
.card-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #dee2e6; border-radius: 8px 8px 0 0; font-weight: bold; }
.card-body { padding: 15px; }
.commands-ref { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; }
</style>

<h2>
    MQTT Worker Manager 
    <span class="refresh"><a href="/?r=mqtt_manager">🔄 Refresh</a></span>
</h2>

<?php if ($message): ?>
    <div class="message <?= $messageType ?>">
        <pre><?= htmlspecialchars($message) ?></pre>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Worker Status
    </div>
    <div class="card-body">
        <div class="status <?= $isRunning ? 'running' : 'stopped' ?>">
            <strong>Status:</strong> 
            <?php if ($isRunning): ?>
                🟢 Python MQTT Worker is <strong>RUNNING</strong> (PID: <?= htmlspecialchars($pid) ?>)
            <?php else: ?>
                🔴 Python MQTT Worker is <strong>STOPPED</strong>
            <?php endif; ?>
        </div>
        
        <div class="controls">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="start">
                <button type="submit" class="btn btn-start" <?= $isRunning ? 'disabled' : '' ?>>
                    ▶️ Start Worker
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="stop">
                <button type="submit" class="btn btn-stop" <?= !$isRunning ? 'disabled' : '' ?>>
                    ⏹️ Stop Worker
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="restart">
                <button type="submit" class="btn btn-restart">
                    🔄 Restart Worker
                </button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Recent Log Output
        <?php if (file_exists($logFile)): ?>
            <small style="float: right; font-weight: normal;"><?= basename($logFile) ?></small>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($logEntries)): ?>
            <div class="log-container">
<?php foreach ($logEntries as $line): ?>
<?= htmlspecialchars($line) ?>
<?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No log file found or log is empty.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Manual Commands
    </div>
    <div class="card-body">
        <div class="commands-ref"># Start worker in background
php bin/run.php start

# Check worker status  
php bin/run.php status

# Stop worker
php bin/run.php stop

# Restart worker
php bin/run.php restart

# Direct Python execution (for debugging)
python bin/main.py</div>
        
        <h4>Files</h4>
        <ul>
            <li><strong>PID File:</strong> <code><?= htmlspecialchars($pidFile) ?></code></li>
            <li><strong>Log File:</strong> <code><?= htmlspecialchars($logFile) ?></code></li>
            <li><strong>Python Script:</strong> <code>bin/main.py</code></li>
            <li><strong>PHP Manager:</strong> <code>bin/run.php</code></li>
        </ul>
        
        <h4>Auto-Start Options</h4>
        <div class="commands-ref"># Cron job to ensure worker stays running (check every 5 minutes)
*/5 * * * * cd /path/to/project && php bin/run.php status >/dev/null || php bin/run.php start

# Systemd service (Linux)
# Create /etc/systemd/system/meshtastic-mqtt.service
[Unit]
Description=Meshtastic MQTT Worker
After=network.target

[Service]
Type=forking
User=www-data
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/php /path/to/project/bin/run.php start
ExecStop=/usr/bin/php /path/to/project/bin/run.php stop
Restart=always

[Install]
WantedBy=multi-user.target</div>
    </div>
</div>
