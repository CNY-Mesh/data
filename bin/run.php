#!/usr/bin/env php
<?php
declare(strict_types=1);

// Updated: This script now launches the Python MQTT worker in the background
// The Python implementation provides better performance and protobuf handling

echo "=== PHP MQTT Worker Launcher ===\n";

// Check if we should launch the Python script or just show status
$action = $argv[1] ?? 'start';

switch ($action) {
    case 'start':
        launchPythonWorker();
        break;
    case 'status':
        checkWorkerStatus();
        break;
    case 'stop':
        stopPythonWorker();
        break;
    case 'restart':
        stopPythonWorker();
        sleep(2);
        launchPythonWorker();
        break;
    default:
        echo "Usage: php run.php [start|stop|status|restart]\n";
        echo "  start   - Launch Python MQTT worker in background\n";
        echo "  stop    - Stop running Python MQTT worker\n";
        echo "  status  - Check if Python MQTT worker is running\n";
        echo "  restart - Stop and start Python MQTT worker\n";
        exit(1);
}

function launchPythonWorker(): void {
    $baseDir = dirname(__DIR__);
    $pythonScript = $baseDir . '/bin/main.py';
    
    // Use data directory for PID and log files since it has write permissions
    $dataDir = $baseDir . '/data';
    $logFile = $dataDir . '/mqtt_worker.log';
    $pidFile = $dataDir . '/mqtt_worker.pid';
    
    // Check if already running
    if (isWorkerRunning($pidFile)) {
        echo "Python MQTT worker is already running (PID: " . trim(file_get_contents($pidFile)) . ")\n";
        return;
    }
    
    echo "Starting Python MQTT worker...\n";
    echo "Script: $pythonScript\n";
    echo "Log file: $logFile\n";
    
    // Determine the correct Python executable
    $pythonExe = findPythonExecutable($baseDir);
    
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: Use start command to run in background
        $command = "start /B \"\" \"$pythonExe\" -u \"$pythonScript\" >> \"$logFile\" 2>&1";
        popen($command, 'r');
    } else {
        // Linux/Mac: Use nohup and & to run in background
        $command = "nohup \"$pythonExe\" -u \"$pythonScript\" >> \"$logFile\" 2>&1 & echo $! > \"$pidFile\"";
        exec($command);
    }
    
    sleep(2); // Give it a moment to start
    
    if (PHP_OS_FAMILY === 'Windows') {
        echo "Python MQTT worker launched in background (Windows)\n";
        echo "Check $logFile for output\n";
    } else {
        if (isWorkerRunning($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            echo "Python MQTT worker started successfully (PID: $pid)\n";
            echo "Check $logFile for output\n";
        } else {
            echo "Failed to start Python MQTT worker\n";
            echo "Check $logFile for errors\n";
        }
    }
}

function checkWorkerStatus(): void {
    $baseDir = dirname(__DIR__);
    $dataDir = $baseDir . '/data';
    $pidFile = $dataDir . '/mqtt_worker.pid';
    $logFile = $dataDir . '/mqtt_worker.log';
    
    if (isWorkerRunning($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        echo "Python MQTT worker is running (PID: $pid)\n";
        
        if (file_exists($logFile)) {
            echo "Recent log entries:\n";
            echo "---\n";
            $lines = file($logFile);
            $recentLines = array_slice($lines, -10);
            echo implode('', $recentLines);
            echo "---\n";
        }
    } else {
        echo "Python MQTT worker is not running\n";
        
        if (file_exists($logFile)) {
            echo "Last log entries:\n";
            echo "---\n";
            $lines = file($logFile);
            $recentLines = array_slice($lines, -5);
            echo implode('', $recentLines);
            echo "---\n";
        }
    }
}

function stopPythonWorker(): void {
    $baseDir = dirname(__DIR__);
    $dataDir = $baseDir . '/data';
    $pidFile = $dataDir . '/mqtt_worker.pid';
    
    if (!isWorkerRunning($pidFile)) {
        echo "Python MQTT worker is not running\n";
        return;
    }
    
    $pid = trim(file_get_contents($pidFile));
    echo "Stopping Python MQTT worker (PID: $pid)...\n";
    
    if (PHP_OS_FAMILY === 'Windows') {
        exec("taskkill /PID $pid /F 2>nul", $output, $result);
    } else {
        exec("kill $pid 2>/dev/null", $output, $result);
    }
    
    sleep(1);
    
    if (!isWorkerRunning($pidFile)) {
        unlink($pidFile);
        echo "Python MQTT worker stopped successfully\n";
    } else {
        echo "Failed to stop Python MQTT worker\n";
    }
}

function isWorkerRunning(string $pidFile): bool {
    if (!file_exists($pidFile)) {
        return false;
    }
    
    $pid = trim(file_get_contents($pidFile));
    if (!$pid) {
        return false;
    }
    
    if (PHP_OS_FAMILY === 'Windows') {
        exec("tasklist /PID $pid 2>nul", $output, $result);
        return $result === 0;
    } else {
        exec("kill -0 $pid 2>/dev/null", $output, $result);
        return $result === 0;
    }
}

function findPythonExecutable(string $baseDir): string {
    // Try virtual environment first
    $venvPython = $baseDir . '/.venv/Scripts/python.exe';
    if (PHP_OS_FAMILY === 'Windows' && file_exists($venvPython)) {
        return $venvPython;
    }
    
    $venvPython = $baseDir . '/.venv/bin/python';
    if (PHP_OS_FAMILY !== 'Windows' && file_exists($venvPython)) {
        return $venvPython;
    }
    
    // Fall back to system Python
    return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
}

/*
// DISABLED CODE - use Python script instead
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Decoder;
use App\MqttWorker;
use App\Support\Env as E;


// Unique client id: prefix from env + timestamp + random
$prefix = E::get('MQTT_CLIENT_ID', 'phpmesh');
$suffix = date('YmdHis') . '-' . rand(1000,9999);
$clientId = substr($prefix . '-' . $suffix, 0, 23);

$dsn = E::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
echo "[DEBUG] DB DSN: $dsn\n";
echo "[DEBUG] MQTT Host: " . E::get('MQTT_HOST', 'mqtt.meshtastic.org') . "\n";
echo "[DEBUG] MQTT Port: " . E::get('MQTT_PORT', '1883') . "\n";
echo "[DEBUG] MQTT Username: " . E::get('MQTT_USERNAME', 'none') . "\n";
echo "[DEBUG] MQTT Password: " . E::get('MQTT_PASSWORD', 'none') . "\n";
echo "[DEBUG] MQTT Topic: " . E::get('MQTT_TOPIC', 'msh/US/#') . "\n";
echo "[DEBUG] MQTT Client ID: $clientId\n";

$db = new Database($dsn);
$decoder = new Decoder();

echo "[DEBUG] Starting MQTT worker...\n";
$worker = new MqttWorker(
    $db,
    $decoder,
    E::get('MQTT_HOST', 'mqtt.meshtastic.org'),
    (int) E::get('MQTT_PORT', '1883'),
    $clientId
);
$worker->run();
*/
