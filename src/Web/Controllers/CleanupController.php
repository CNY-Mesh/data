<?php
declare(strict_types=1);
namespace App\Web\Controllers;

use App\Database;
use App\Support\Env;

final class CleanupController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle(): void
    {
        $action = $_POST['action'] ?? '';
        $result = null;
        $error = null;
        
        if ($action) {
            try {
                $pdo = $this->db->pdo();
                
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
                        $compactResult = $this->compactSqliteDatabase($pdo);
                        $result = "Cleared all data ($totalCleared total records). $compactResult";
                        break;
                        
                    case 'clear_debug_log':
                        $baseDir = dirname(dirname(dirname(dirname(__FILE__)))); // Go up from src/Web/Controllers to project root
                        $dataDir = $baseDir . '/data';
                        $logFile = $dataDir . '/mqtt_worker.log';
                        if (file_exists($logFile)) {
                            $size = filesize($logFile);
                            file_put_contents($logFile, "# MQTT worker log cleared at " . date('Y-m-d H:i:s') . "\n");
                            $result = "Cleared MQTT worker log (" . number_format($size) . " bytes)";
                        } else {
                            $result = "MQTT worker log file not found";
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
                        $baseDir = dirname(dirname(dirname(dirname(__FILE__)))); // Go up from src/Web/Controllers to project root
                        $dataDir = $baseDir . '/data';
                        $logFile = $dataDir . '/mqtt_worker.log';
                        $logSize = 0;
                        if (file_exists($logFile)) {
                            $logSize = filesize($logFile);
                            file_put_contents($logFile, "# MQTT worker log cleared at " . date('Y-m-d H:i:s') . "\n");
                        }
                        
                        $compactResult = $this->compactSqliteDatabase($pdo);
                        $result = "Cleared everything: $totalCleared database records, " . number_format($logSize) . " bytes of logs. $compactResult";
                        break;

                    case 'compact_database':
                        $result = $this->compactSqliteDatabase($pdo);
                        break;
                        
                    case 'clear_old_raw_messages':
                        // Clear raw messages older than configured hours
                        $cleanupHours = (int) Env::get('RAW_MESSAGE_CLEANUP_HOURS', 1);
                        $stmt = $pdo->prepare("DELETE FROM raw_messages WHERE rx_time < strftime('%s', 'now', '-{$cleanupHours} hours')");
                        $stmt->execute();
                        $cleared = $stmt->rowCount();
                        $result = "Cleared $cleared old raw messages (older than {$cleanupHours} hour" . ($cleanupHours !== 1 ? 's' : '') . ")";
                        break;
                        
                    case 'clear_garbage_messages':
                        // Clean up garbage text messages
                        $totalDeleted = 0;
                        
                        // Define garbage messages to remove
                        $garbageMessages = [
                            [2224786404, 'TEST 2'],
                            [142224248, 'Fake from MQTTool']
                        ];
                        
                        // Remove specific garbage messages
                        $stmt = $pdo->prepare('DELETE FROM text_messages WHERE node_from = ? AND message = ?');
                        foreach ($garbageMessages as [$nodeFrom, $message]) {
                            $stmt->execute([$nodeFrom, $message]);
                            $totalDeleted += $stmt->rowCount();
                        }
                        
                        // Remove single-word messages starting with "test" (case insensitive)
                        $testStmt = $pdo->prepare("
                            DELETE FROM text_messages 
                            WHERE LOWER(message) LIKE 'test%' 
                            AND message NOT LIKE '% %' 
                            AND LENGTH(TRIM(message)) > 0
                        ");
                        $testStmt->execute();
                        $totalDeleted += $testStmt->rowCount();
                        
                        $result = "Cleared $totalDeleted garbage text messages";
                        break;
                        
                    default:
                        $error = "Unknown action: $action";
                }
            } catch (\Exception $e) {
                $error = "Error: " . $e->getMessage();
                error_log("Cleanup error: " . $e->getMessage());
            }
        }
        
        // Get database statistics
        $stats = $this->getDatabaseStats();
        
        $this->render('cleanup', [
            'result' => $result,
            'error' => $error,
            'stats' => $stats
        ]);
    }
    
    private function getDatabaseStats(): array
    {
        $pdo = $this->db->pdo();
        $stats = [];
        
        $tables = ['raw_messages', 'nodes', 'positions', 'telemetry', 'neighbors', 'text_messages'];
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $stats[$table] = (int) $stmt->fetchColumn();
            } catch (\Exception $e) {
                $stats[$table] = 'Error';
            }
        }
        
        // Get SQLite file sizes (main DB plus WAL/SHM sidecar files)
        $dbFile = $this->getSqliteFilePath();
        $stats['db_size'] = file_exists($dbFile) ? filesize($dbFile) : 0;
        $stats['db_wal_size'] = file_exists($dbFile . '-wal') ? filesize($dbFile . '-wal') : 0;
        $stats['db_shm_size'] = file_exists($dbFile . '-shm') ? filesize($dbFile . '-shm') : 0;
        $stats['db_total_size'] = $stats['db_size'] + $stats['db_wal_size'] + $stats['db_shm_size'];
        
        // Get MQTT worker log size
        $baseDir = dirname(dirname(dirname(dirname(__FILE__)))); // Go up from src/Web/Controllers to project root
        $dataDir = $baseDir . '/data';
        $logFile = $dataDir . '/mqtt_worker.log';
        if (file_exists($logFile)) {
            $stats['log_size'] = filesize($logFile);
        } else {
            $stats['log_size'] = 0;
        }
        
        return $stats;
    }

    private function compactSqliteDatabase(\PDO $pdo): string
    {
        $dsn = Env::get('DB_DSN') ?: 'sqlite:' . $this->getSqliteFilePath();
        if (!str_starts_with($dsn, 'sqlite:')) {
            return 'Database compaction skipped (non-SQLite DSN).';
        }

        // First, force WAL pages back into the main DB and truncate the WAL file.
        $checkpointResult = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)');
        $checkpointText = 'checkpoint status unavailable';
        if ($checkpointResult !== false) {
            $row = $checkpointResult->fetch(\PDO::FETCH_NUM);
            if (is_array($row) && count($row) >= 3) {
                $checkpointText = "checkpoint rc={$row[0]}, frames={$row[1]}, checkpointed={$row[2]}";
            }
            $checkpointResult->closeCursor();
        }
        unset($checkpointResult);

        // VACUUM must run with no active statements on the connection.
        $vacuumPdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $vacuumPdo->exec('PRAGMA busy_timeout=5000');
        $vacuumPdo->exec('VACUUM');
        unset($vacuumPdo);

        return "Database compacted ($checkpointText; VACUUM complete).";
    }

    private function getSqliteFilePath(): string
    {
        $dsn = Env::get('DB_DSN') ?: '';

        if (str_starts_with($dsn, 'sqlite:')) {
            return substr($dsn, 7);
        }

        $baseDir = dirname(dirname(dirname(dirname(__FILE__))));
        return $baseDir . '/data/meshtastic.sqlite';
    }
}
