<?php
declare(strict_types=1);

namespace App\Web\Controllers;

class MqttManagerController extends BaseController
{
    public function handle(): void
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'status';
        $baseDir = dirname(dirname(dirname(dirname(__FILE__)))); // Go up from src/Web/Controllers to project root
        
        // Use data directory for PID and log files since it has write permissions
        $dataDir = $baseDir . '/data';
        $pidFile = $dataDir . '/mqtt_worker.pid';
        $logFile = $dataDir . '/mqtt_worker.log';

        $message = '';
        $messageType = 'info';

        // Handle actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            switch ($action) {
                case 'start':
                    $message = $this->executeWorkerCommand('start');
                    $messageType = 'success';
                    break;
                case 'stop':
                    $message = $this->executeWorkerCommand('stop');
                    $messageType = 'warning';
                    break;
                case 'restart':
                    $message = $this->executeWorkerCommand('restart');
                    $messageType = 'info';
                    break;
            }
        }

        $isRunning = $this->isWorkerRunning($pidFile);
        $pid = $isRunning && file_exists($pidFile) ? trim(file_get_contents($pidFile)) : null;

        // Get recent log entries
        $logEntries = [];
        if (file_exists($logFile)) {
            $logEntries = $this->readLastLines($logFile, 20);
        }

        $this->render('mqtt_manager', [
            'isRunning' => $isRunning,
            'pid' => $pid,
            'logEntries' => $logEntries,
            'message' => $message,
            'messageType' => $messageType,
            'logFile' => $logFile,
            'pidFile' => $pidFile
        ]);
    }

    private function isWorkerRunning(string $pidFile): bool
    {
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

    private function executeWorkerCommand(string $action): string
    {
        $baseDir = dirname(dirname(dirname(dirname(__FILE__)))); // Go up from src/Web/Controllers to project root
        $runScript = $baseDir . '/bin/run.php';
        
        ob_start();
        $command = "php \"$runScript\" $action 2>&1";
        $output = shell_exec($command);
        ob_end_clean();
        
        return $output ?: "Command executed (no output)";
    }

    private function readLastLines(string $filePath, int $lineCount = 20, int $maxBytes = 262144): array
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return [];
        }

        if (fseek($handle, 0, SEEK_END) !== 0) {
            fclose($handle);
            return [];
        }

        $fileSize = ftell($handle);
        if ($fileSize === false || $fileSize <= 0) {
            fclose($handle);
            return [];
        }

        $buffer = '';
        $position = $fileSize;
        $bytesRead = 0;
        $chunkSize = 4096;

        while ($position > 0 && substr_count($buffer, "\n") <= $lineCount && $bytesRead < $maxBytes) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;

            if (fseek($handle, $position) !== 0) {
                break;
            }

            $chunk = fread($handle, $readSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer = $chunk . $buffer;
            $bytesRead += $readSize;
        }

        fclose($handle);

        $lines = preg_split('/\r\n|\n|\r/', $buffer);
        if ($lines === false) {
            return [];
        }

        if (!empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        $tail = array_slice($lines, -$lineCount);
        return array_map(static fn(string $line): string => $line . PHP_EOL, $tail);
    }
}
