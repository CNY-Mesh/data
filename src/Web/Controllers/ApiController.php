<?php
declare(strict_types=1);
namespace App\Web\Controllers;

use App\Support\Env;

final class ApiController extends BaseController
{
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'POST') {
            $this->handlePost();
            return;
        }
        
        $a = $_GET['a'] ?? '';
        switch ($a) {
            case 'positions':
                $rows = $this->db->pdo()->query('SELECT p.node_num, 
                    COALESCE(n.long_name, "Unknown Node") as long_name, 
                    COALESCE(n.short_name, SUBSTR(printf("!%08x", p.node_num), -4)) as short_name, 
                    p.lat, p.lon, p.time, p.altitude, p.rx_rssi, p.rx_snr, p.topic,
                    CASE WHEN n.node_num IS NOT NULL THEN 1 ELSE 0 END as is_known_node
                    FROM positions p 
                    LEFT JOIN nodes n ON p.node_num = n.node_num
                    WHERE p.lat IS NOT NULL AND p.lon IS NOT NULL
                    ORDER BY p.time DESC LIMIT 1000')->fetchAll();
                $this->json($rows); return;
            case 'position_history':
                // Get position history for tracked nodes
                $node_num = $_GET['node_num'] ?? null;
                $limit = min((int)($_GET['limit'] ?? 100), 1000);
                
                if ($node_num) {
                    // Get history for specific node
                    $stmt = $this->db->pdo()->prepare('
                        SELECT ph.*, 
                            COALESCE(n.long_name, "Unknown Node") as long_name, 
                            COALESCE(n.short_name, SUBSTR(printf("!%08x", ph.node_num), -4)) as short_name,
                            DATETIME(ph.time, "unixepoch") as position_time,
                            DATETIME(ph.recorded_at, "unixepoch") as recorded_time
                        FROM position_history ph 
                        LEFT JOIN nodes n ON ph.node_num = n.node_num
                        WHERE ph.node_num = ? 
                        ORDER BY ph.time DESC 
                        LIMIT ?
                    ');
                    $stmt->execute([$node_num, $limit]);
                    $rows = $stmt->fetchAll();
                } else {
                    // Get recent history for all tracked nodes
                    $stmt = $this->db->pdo()->prepare('
                        SELECT ph.*, 
                            COALESCE(n.long_name, "Unknown Node") as long_name, 
                            COALESCE(n.short_name, SUBSTR(printf("!%08x", ph.node_num), -4)) as short_name,
                            DATETIME(ph.time, "unixepoch") as position_time,
                            DATETIME(ph.recorded_at, "unixepoch") as recorded_time
                        FROM position_history ph 
                        LEFT JOIN nodes n ON ph.node_num = n.node_num
                        ORDER BY ph.time DESC 
                        LIMIT ?
                    ');
                    $stmt->execute([$limit]);
                    $rows = $stmt->fetchAll();
                }
                $this->json($rows); return;
            case 'telemetry':
                $rows = $this->db->pdo()->query('SELECT t.*, n.long_name, n.short_name 
                    FROM telemetry t 
                    LEFT JOIN nodes n ON t.node_num = n.node_num 
                    ORDER BY t.updated_at DESC LIMIT 1000')->fetchAll();
                $this->json($rows); return;
            case 'nodes':
                $rows = $this->db->pdo()->query('SELECT * FROM nodes ORDER BY last_seen DESC LIMIT 1000')->fetchAll();
                $this->json($rows); return;
            case 'raw_messages':
                $limit = (int) ($_GET['limit'] ?? 100);
                $offset = (int) ($_GET['offset'] ?? 0);
                $limit = min($limit, 1000); // Cap at 1000
                    $rows = $this->db->pdo()->query("SELECT * FROM raw_messages ORDER BY COALESCE(processed_at, rx_time, id) DESC LIMIT $limit OFFSET $offset")->fetchAll();
                $this->json($rows); return;
            case 'debug_bundle':
                $this->handleDebugBundle();
                return;
            default:
                $this->json(['error'=>'unknown api']);
        }
    }

    private function handleDebugBundle(): void
    {
        if (!$this->isDebugAuthorized()) {
            http_response_code(403);
            $this->json(['error' => 'forbidden']);
            return;
        }

        $limit = min(max((int) ($_GET['limit'] ?? 50), 1), 200);
        $minutes = min(max((int) ($_GET['minutes'] ?? 30), 1), 1440);
        $sinceTs = time() - ($minutes * 60);

        $pdo = $this->db->pdo();

        $latestRowsStmt = $pdo->prepare("\n            SELECT\n                id, topic, channel_id, gateway_id, node_from, node_to,\n                port_num, message_type, is_encrypted, is_json,\n                payload_length, rx_time, processed_at,\n                SUBSTR(payload_hex, 1, 128) as payload_hex_preview\n            FROM raw_messages\n            ORDER BY COALESCE(processed_at, rx_time, id) DESC\n            LIMIT ?\n        ");
        $latestRowsStmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $latestRowsStmt->execute();
        $latestRows = $latestRowsStmt->fetchAll();

        $decodeErrorsStmt = $pdo->prepare("\n            SELECT\n                id, topic, channel_id, message_type, is_encrypted,\n                payload_length, rx_time, processed_at,\n                SUBSTR(payload_hex, 1, 128) as payload_hex_preview\n            FROM raw_messages\n            WHERE message_type = 'decode_error'\n            ORDER BY COALESCE(processed_at, rx_time, id) DESC\n            LIMIT ?\n        ");
        $decodeErrorsStmt->bindValue(1, min($limit, 100), \PDO::PARAM_INT);
        $decodeErrorsStmt->execute();
        $decodeErrors = $decodeErrorsStmt->fetchAll();

        $portSummaryStmt = $pdo->prepare("\n            SELECT\n                COALESCE(port_num, -1) as port_num,\n                COALESCE(message_type, '') as message_type,\n                SUM(CASE WHEN is_encrypted = 1 THEN 1 ELSE 0 END) as encrypted_count,\n                COUNT(*) as count\n            FROM raw_messages\n            WHERE CASE\n                WHEN processed_at IS NOT NULL AND processed_at > 0 THEN processed_at\n                WHEN rx_time IS NOT NULL AND rx_time > 0 THEN rx_time\n                ELSE NULL\n            END >= ?\n            GROUP BY COALESCE(port_num, -1), COALESCE(message_type, '')\n            ORDER BY count DESC\n            LIMIT 30\n        ");
        $portSummaryStmt->execute([$sinceTs]);
        $portSummary = $portSummaryStmt->fetchAll();

        $latestNode = $pdo->query("SELECT node_num, node_id, long_name, short_name, last_seen FROM nodes ORDER BY last_seen DESC LIMIT 1")->fetch();
        $latestPosition = $pdo->query("SELECT node_num, lat, lon, altitude, time, topic FROM positions ORDER BY time DESC LIMIT 1")->fetch();

        $latestMessageTs = $pdo->query("\n            SELECT MAX(CASE\n                WHEN processed_at IS NOT NULL AND processed_at > 0 THEN processed_at\n                WHEN rx_time IS NOT NULL AND rx_time > 0 THEN rx_time\n                ELSE NULL\n            END)\n            FROM raw_messages\n        ")->fetchColumn();
        $messagesLastWindowStmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM raw_messages\n            WHERE CASE\n                WHEN processed_at IS NOT NULL AND processed_at > 0 THEN processed_at\n                WHEN rx_time IS NOT NULL AND rx_time > 0 THEN rx_time\n                ELSE NULL\n            END >= ?\n        ");
        $messagesLastWindowStmt->execute([$sinceTs]);
        $messagesLastWindow = (int) $messagesLastWindowStmt->fetchColumn();

        $debugLogTail = $this->readDebugLogTail();

        $this->json([
            'ok' => true,
            'generated_at' => time(),
            'window_minutes' => $minutes,
            'messages_in_window' => $messagesLastWindow,
            'latest_message_ts' => $latestMessageTs ? (int) $latestMessageTs : null,
            'latest_node' => $latestNode ?: null,
            'latest_position' => $latestPosition ?: null,
            'port_summary' => $portSummary,
            'decode_errors' => $decodeErrors,
            'recent_raw_messages' => $latestRows,
            'worker_log_tail' => $debugLogTail,
        ]);
    }

    private function isDebugAuthorized(): bool
    {
        $configured = trim((string) Env::get('DEBUG_ENDPOINT_KEY', ''));
        if ($configured === '') {
            return false;
        }

        $provided = '';
        if (isset($_GET['key'])) {
            $provided = (string) $_GET['key'];
        } elseif (isset($_SERVER['HTTP_X_DEBUG_KEY'])) {
            $provided = (string) $_SERVER['HTTP_X_DEBUG_KEY'];
        }

        if ($provided === '') {
            return false;
        }

        return hash_equals($configured, $provided);
    }

    private function readDebugLogTail(int $lineCount = 60, int $maxBytes = 262144): array
    {
        $baseDir = dirname(dirname(dirname(dirname(__FILE__))));
        $logFile = $baseDir . '/data/mqtt_worker.log';

        if (!file_exists($logFile)) {
            return [
                'path' => $logFile,
                'exists' => false,
                'lines' => [],
            ];
        }

        $handle = @fopen($logFile, 'rb');
        if ($handle === false) {
            return [
                'path' => $logFile,
                'exists' => true,
                'error' => 'unable_to_open',
                'lines' => [],
            ];
        }

        if (fseek($handle, 0, SEEK_END) !== 0) {
            fclose($handle);
            return [
                'path' => $logFile,
                'exists' => true,
                'error' => 'unable_to_seek',
                'lines' => [],
            ];
        }

        $fileSize = ftell($handle);
        if ($fileSize === false || $fileSize <= 0) {
            fclose($handle);
            return [
                'path' => $logFile,
                'exists' => true,
                'lines' => [],
            ];
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
            $lines = [];
        }

        if (!empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        return [
            'path' => $logFile,
            'exists' => true,
            'lines' => array_slice($lines, -$lineCount),
        ];
    }
    
    private function handlePost(): void
    {
        $action = $_GET['a'] ?? '';
        
        switch ($action) {
            case 'mesh_data':
                $this->saveMeshData();
                break;
            default:
                http_response_code(400);
                $this->json(['error' => 'unknown POST action']);
        }
    }
    
    private function saveMeshData(): void
    {
        // Get JSON input
        $input = file_get_contents('php://input');
        if (!$input) {
            http_response_code(400);
            $this->json(['error' => 'No input data']);
            return;
        }
        
        $data = json_decode($input, true);
        if (!$data) {
            http_response_code(400);
            $this->json(['error' => 'Invalid JSON']);
            return;
        }
        
        try {
            // Start transaction with error handling
            try {
                $this->db->pdo()->beginTransaction();
            } catch (\PDOException $e) {
                http_response_code(500);
                $this->json(['error' => 'Failed to start transaction: ' . $e->getMessage()]);
                return;
            }
            
            $saved_count = 0;
            $error_count = 0;
            $errors = [];
            
            // Handle single message or array of messages
            $messages = isset($data['messages']) ? $data['messages'] : [$data];
            
            foreach ($messages as $message) {
                try {
                    // Validate message structure
                    if (!is_array($message)) {
                        throw new \Exception('Message must be an array');
                    }
                    
                    $this->processMessage($message);
                    $saved_count++;
                } catch (\Exception $e) {
                    $error_count++;
                    $errors[] = 'Message processing error: ' . $e->getMessage();
                }
            }
            
            // Commit transaction with error handling
            try {
                $this->db->pdo()->commit();
            } catch (\PDOException $e) {
                // If commit fails, try to rollback
                if ($this->db->pdo()->inTransaction()) {
                    $this->db->pdo()->rollback();
                }
                http_response_code(500);
                $this->json(['error' => 'Failed to commit transaction: ' . $e->getMessage()]);
                return;
            }
            
            $this->json([
                'success' => true,
                'saved_count' => $saved_count,
                'error_count' => $error_count,
                'errors' => array_slice($errors, 0, 10) // Limit error messages
            ]);
            
        } catch (\Exception $e) {
            // Only rollback if we're actually in a transaction
            if ($this->db->pdo()->inTransaction()) {
                $this->db->pdo()->rollback();
            }
            http_response_code(500);
            $this->json(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function processMessage(array $message): void
    {
        // Extract basic message information
        $topic = $message['topic'] ?? '';
        $channel_id = $this->extractChannelId($topic);
        $gateway_id = $this->extractGatewayId($topic);
        $timestamp = $message['timestamp'] ?? time();
        
        // Handle JSON messages
        if (isset($message['json_data']) && is_array($message['json_data'])) {
            $this->processJsonMessage($message['json_data'], $topic, $channel_id, $gateway_id, $timestamp);
        }
        
        // Handle decoded ServiceEnvelope messages
        if (isset($message['decoded_packet']) && is_array($message['decoded_packet'])) {
            $this->processDecodedPacket($message['decoded_packet'], $topic, $channel_id, $gateway_id, $timestamp);
        }
        
        // Store raw message data
        $this->storeRawMessage($message);
    }
    
    private function processJsonMessage(array $json_data, string $topic, string $channel_id, string $gateway_id, int $timestamp): void
    {
        $node_from = $json_data['from'] ?? null;
        $node_to = $json_data['to'] ?? null;
        $message_type = $json_data['type'] ?? 'unknown';
        $payload = $json_data['payload'] ?? [];
        
        // Ensure payload is an array
        if (!is_array($payload)) {
            return; // Skip if payload is not an array
        }
        
        // Update/insert node information
        if ($node_from) {
            $this->updateNode($node_from, $json_data['sender'] ?? null, $timestamp);
        }
        
        // Process based on message type - now passing topic to all methods
        switch ($message_type) {
            case 'position':
                $this->processPositionData($node_from, $payload, $json_data, $timestamp, $topic);
                break;
            case 'telemetry':
                $this->processTelemetryData($node_from, $payload, $timestamp, $topic);
                break;
            case 'nodeinfo':
                $this->processNodeInfo($node_from, $payload, $timestamp);
                break;
            case 'neighborinfo':
                $this->processNeighborInfo($node_from, $payload, $timestamp, $topic);
                break;
            case 'text':
                $this->processTextMessage($node_from, $node_to, $payload, $timestamp, $topic);
                break;
        }
    }
    
    private function processDecodedPacket(array $packet, string $topic, string $channel_id, string $gateway_id, int $timestamp): void
    {
        $node_from = $packet['from'] ?? null;
        $node_to = $packet['to'] ?? null;
        $decoded = $packet['decoded'] ?? [];

        if ($node_from) {
            // Ensure node row exists for binary/non-JSON packets before updating typed data.
            $this->updateNode((int) $node_from, null, $timestamp);
        }
        
        if (!$decoded || isset($decoded['decode_error'])) {
            return; // Skip messages with decode errors
        }
        
        $portnum = $decoded['portnum'] ?? 0;
        
        // Process based on port number - now passing topic to all methods
        switch ($portnum) {
            case 1: // TEXT_MESSAGE_APP
                if (isset($decoded['text_message'])) {
                    $this->processTextMessage($node_from, $node_to, ['text' => $decoded['text_message']], $timestamp, $topic);
                }
                break;
            case 3: // POSITION_APP
                if (isset($decoded['position'])) {
                    $this->processPositionData($node_from, $decoded['position'], [], $timestamp, $topic);
                }
                break;
            case 4: // NODEINFO_APP
                if (isset($decoded['nodeinfo'])) {
                    $this->processNodeInfo($node_from, $decoded['nodeinfo'], $timestamp);
                }
                break;
            case 67: // TELEMETRY_APP
                if (isset($decoded['telemetry'])) {
                    $this->processTelemetryData($node_from, $decoded['telemetry'], $timestamp, $topic);
                }
                break;
            case 73: // MAP_REPORT_APP
                if (isset($decoded['map_report'])) {
                    $this->processMapReport($node_from, $decoded['map_report'], $channel_id, $timestamp);
                }
                break;
        }
    }
    
    private function extractChannelId(string $topic): string
    {
        // Extract channel from variable-depth topics such as:
        // msh/US/2/e/LongFast/!gateway
        // msh/US/NY/CNY/2/e/LongFast/!gateway
        // msh/US/NY/CNY/2/json/LongFast/!gateway
        $parts = explode('/', $topic);
        $count = count($parts);

        for ($i = 0; $i < $count - 1; $i++) {
            if (($parts[$i] === 'e' || $parts[$i] === 'json') && isset($parts[$i + 1])) {
                return $parts[$i + 1];
            }
        }

        return '';
    }
    
    private function extractGatewayId(string $topic): string
    {
        // Extract gateway ID from end of topic
        $parts = explode('/', $topic);
        $last = end($parts);
        return (strpos($last, '!') === 0) ? $last : '';
    }
    
    private function storeRawMessage(array $message): void
    {
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO raw_messages (
                topic, channel_id, gateway_id, node_from, node_to, 
                port_num, payload_hex, payload_length, is_encrypted, 
                is_json, message_type, rx_time, rx_rssi, rx_snr, raw_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $topic = $message['topic'] ?? '';
        $channel_id = $this->extractChannelId($topic);
        $gateway_id = $this->extractGatewayId($topic);
        $topicIsEncrypted = str_contains($topic, '/e/');
        
        // Extract fields from message
        $node_from = null;
        $node_to = null;
        $port_num = null;
        $payload_hex = '';
        $payload_length = 0;
        $is_encrypted = $topicIsEncrypted;
        $is_json = isset($message['json_data']);
        $message_type = '';
        $rx_time = $message['timestamp'] ?? time();
        $rx_rssi = null;
        $rx_snr = null;
        
        if (isset($message['json_data'])) {
            $json = $message['json_data'];
            $node_from = $json['from'] ?? null;
            $node_to = $json['to'] ?? null;
            $message_type = $json['type'] ?? '';
            $rx_rssi = $json['rssi'] ?? null;
            $rx_snr = $json['snr'] ?? null;
            
            // Handle decode errors
            if (isset($json['decode_error']) && $json['decode_error']) {
                $message_type = 'decode_error';
                $is_encrypted = $json['likely_encrypted'] ?? false;
                $payload_length = $json['size'] ?? 0;
                $payload_hex = $json['hex_preview'] ?? '';
                
                // Debug logging for decode errors
                error_log("API: Received decode error message - type: $message_type, encrypted: " . ($is_encrypted ? 'yes' : 'no') . ", size: $payload_length");
            }
        }
        
        if (isset($message['decoded_packet'])) {
            $packet = $message['decoded_packet'];
            $node_from = $packet['from'] ?? null;
            $node_to = $packet['to'] ?? null;
            $decoded = $packet['decoded'] ?? [];
            $port_num = $decoded['portnum'] ?? null;
            $payload_hex = $decoded['payload_hex'] ?? '';
            $payload_length = $decoded['payload_size'] ?? 0;
            // Prefer explicit packet encryption field when present; otherwise trust topic path.
            if (array_key_exists('encrypted', $packet)) {
                $is_encrypted = true;
            }
        }
        
        $stmt->execute([
            $topic, $channel_id, $gateway_id, $node_from, $node_to,
            $port_num, $payload_hex, $payload_length, $is_encrypted ? 1 : 0,
            $is_json ? 1 : 0, $message_type, $rx_time, $rx_rssi, $rx_snr,
            json_encode($message)
        ]);
        
        // Debug logging for message insertion
        if ($message_type === 'decode_error') {
            error_log("API: Inserted decode error message - topic: $topic, type: $message_type, size: $payload_length, encrypted: " . ($is_encrypted ? 'yes' : 'no'));
        }
        
        // Automatically cleanup old raw messages (configurable timeframe)
        $this->cleanupOldRawMessages();
    }
    
    private function cleanupOldRawMessages(): void
    {
        try {
            // Only cleanup every 100th message to reduce overhead
            if (rand(1, 100) !== 1) {
                return;
            }
            
            // Delete raw messages older than configured hours
            $cleanupHours = (int) \App\Support\Env::get('RAW_MESSAGE_CLEANUP_HOURS', 1);
            $cutoff = time() - ($cleanupHours * 3600);
            $stmt = $this->db->pdo()->prepare("DELETE FROM raw_messages WHERE rx_time < ?");
            $stmt->execute([$cutoff]);
            
            // Optional: Log cleanup if debug logging is enabled
            if ($stmt->rowCount() > 0) {
                $hourText = $cleanupHours === 1 ? "1 hour" : "{$cleanupHours} hours";
                error_log("Auto-cleanup: Removed " . $stmt->rowCount() . " old raw messages (older than {$hourText})");
            }
        } catch (\Exception $e) {
            // Don't let cleanup errors break message processing
            error_log("Raw message cleanup failed: " . $e->getMessage());
        }
    }
    
    private function updateNode(int $node_num, ?string $node_id, int $timestamp): void
    {
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO nodes (node_num, node_id, last_seen) 
            VALUES (?, ?, ?)
            ON CONFLICT(node_num) DO UPDATE SET 
                node_id = COALESCE(excluded.node_id, node_id),
                last_seen = excluded.last_seen
        ");
        $stmt->execute([$node_num, $node_id, $timestamp]);
    }
    
    private function processPositionData(?int $node_from, array $payload, array $context, int $timestamp, string $topic = null): void
    {
        if (!$node_from) return;
        
        $lat = null;
        $lon = null;
        $altitude = null;
        $time = $timestamp;
        $rx_rssi = $context['rssi'] ?? null;
        $rx_snr = $context['snr'] ?? null;
        
        // Handle different payload formats
        if (isset($payload['latitude_i'], $payload['longitude_i'])) {
            $lat = $payload['latitude_i'] / 1e7;
            $lon = $payload['longitude_i'] / 1e7;
        } elseif (isset($payload['latitude'], $payload['longitude'])) {
            $lat = $payload['latitude'];
            $lon = $payload['longitude'];
        }
        
        if (isset($payload['altitude'])) {
            $altitude = $payload['altitude'];
        }
        
        if (isset($payload['time'])) {
            $time = $payload['time'];
        }
        
        if ($lat && $lon) {
            // Always update the main positions table (current behavior)
            $stmt = $this->db->pdo()->prepare("
                INSERT OR REPLACE INTO positions 
                (node_num, lat, lon, altitude, time, rx_rssi, rx_snr, topic) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$node_from, $lat, $lon, $altitude, $time, $rx_rssi, $rx_snr, $topic]);
            
            // Check if this is one of "our" nodes that should have position history tracked
            $ourNodes = $this->getOurNodes();
            $nodeIdHex = dechex($node_from);
            
            if (in_array($nodeIdHex, $ourNodes) || in_array((string)$node_from, $ourNodes)) {
                // Store position history for our tracked nodes
                $this->storePositionHistory($node_from, $lat, $lon, $altitude, $time, $rx_rssi, $rx_snr, $topic);
            }
        }
    }
    
    /**
     * Get the list of "our" nodes from environment config
     * Combines OUR_NODES and NODE_HISTORY_IDS for complete position tracking
     */
    private function getOurNodes(): array
    {
        $ourNodesEnv = \App\Support\Env::get('OUR_NODES', '');
        $historyNodesEnv = \App\Support\Env::get('NODE_HISTORY_IDS', '');
        
        $allNodes = [];
        
        // Add OUR_NODES to the list
        if (!empty($ourNodesEnv)) {
            $ourNodes = array_map('trim', explode(',', $ourNodesEnv));
            $allNodes = array_merge($allNodes, $ourNodes);
        }
        
        // Add NODE_HISTORY_IDS to the list
        if (!empty($historyNodesEnv)) {
            $historyNodes = array_map('trim', explode(',', $historyNodesEnv));
            $allNodes = array_merge($allNodes, $historyNodes);
        }
        
        // Remove duplicates and empty values
        $allNodes = array_unique(array_filter($allNodes, function($node) {
            return !empty(trim($node));
        }));
        
        return $allNodes;
    }
    
    /**
     * Store position history for tracked nodes
     */
    private function storePositionHistory(int $node_num, float $lat, float $lon, ?int $altitude, int $time, ?float $rx_rssi, ?float $rx_snr, ?string $topic): void
    {
        try {
            // Create position_history table if it doesn't exist
            $this->db->pdo()->exec("
                CREATE TABLE IF NOT EXISTS position_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    node_num INTEGER NOT NULL,
                    lat REAL NOT NULL,
                    lon REAL NOT NULL,
                    altitude INTEGER,
                    time INTEGER NOT NULL,
                    rx_rssi REAL,
                    rx_snr REAL,
                    topic TEXT,
                    recorded_at INTEGER NOT NULL,
                    UNIQUE(node_num, time, lat, lon) ON CONFLICT IGNORE
                )
            ");
            
            // Insert position history record
            $stmt = $this->db->pdo()->prepare("
                INSERT OR IGNORE INTO position_history 
                (node_num, lat, lon, altitude, time, rx_rssi, rx_snr, topic, recorded_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$node_num, $lat, $lon, $altitude, $time, $rx_rssi, $rx_snr, $topic, time()]);
            
            // Optional: Log position history saves for debugging
            if ($stmt->rowCount() > 0) {
                error_log("Position history saved for node " . dechex($node_num) . " at $lat,$lon");
            }
            
        } catch (\Exception $e) {
            error_log("Failed to store position history: " . $e->getMessage());
        }
    }
    
    private function processTelemetryData(?int $node_from, array $payload, int $timestamp, string $topic = null): void
    {
        if (!$node_from) return;
        
        $stmt = $this->db->pdo()->prepare("
            INSERT OR REPLACE INTO telemetry 
            (node_num, battery_level, voltage, channel_utilization, air_util_tx, uptime_seconds, updated_at, topic) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $node_from,
            $payload['battery_level'] ?? null,
            $payload['voltage'] ?? null,
            $payload['channel_utilization'] ?? null,
            $payload['air_util_tx'] ?? null,
            $payload['uptime_seconds'] ?? null,
            $timestamp,
            $topic
        ]);
    }
    
    private function processNodeInfo(?int $node_from, array $payload, int $timestamp): void
    {
        if (!$node_from) return;

        $stmt = $this->db->pdo()->prepare("
            INSERT INTO nodes (node_num, long_name, short_name, hardware, last_seen)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(node_num) DO UPDATE SET
                long_name = COALESCE(excluded.long_name, nodes.long_name),
                short_name = COALESCE(excluded.short_name, nodes.short_name),
                hardware = COALESCE(excluded.hardware, nodes.hardware),
                last_seen = excluded.last_seen
        ");

        $stmt->execute([
            $node_from,
            $payload['longname'] ?? $payload['long_name'] ?? null,
            $payload['shortname'] ?? $payload['short_name'] ?? null,
            $payload['hardware'] ?? $payload['hw_model'] ?? null,
            $timestamp,
        ]);
    }
    
    private function processNeighborInfo(?int $node_from, array $payload, int $timestamp, string $topic = null): void
    {
        if (!$node_from || !isset($payload['neighbors'])) return;
        
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO neighbors (reporter_node_num, neighbor_node_num, snr, heard_at, topic) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($payload['neighbors'] as $neighbor) {
            if (isset($neighbor['node_id'])) {
                $stmt->execute([
                    $node_from,
                    $neighbor['node_id'],
                    $neighbor['snr'] ?? null,
                    $timestamp,
                    $topic
                ]);
            }
        }
    }
    
    private function processTextMessage(?int $node_from, ?int $node_to, array $payload, int $timestamp, string $topic = null): void
    {
        if (!$node_from) return;
        
        // Fix: Check for both 'message' and 'text' fields
        $message_text = $payload['message'] ?? $payload['text'] ?? null;
        if (!$message_text) return;
        
        // Filter out garbage messages
        $garbage_filters = [
            2224786404 => ['TEST 2'],
            142224248 => ['Fake from MQTTool']
        ];
        
        // Node-specific filtering
        if (isset($garbage_filters[$node_from])) {
            foreach ($garbage_filters[$node_from] as $filtered_message) {
                if (trim($message_text) === $filtered_message) {
                    // Skip this garbage message
                    return;
                }
            }
        }
        
        // General garbage filtering - single word messages starting with "test"
        $trimmed_message = trim($message_text);
        if (!empty($trimmed_message)) {
            // Check if it's a single word (no spaces) and starts with "test" (case insensitive)
            if (strpos($trimmed_message, ' ') === false && 
                stripos($trimmed_message, 'test') === 0) {
                // Log filtered message for debugging
                error_log("Filtered test message from node $node_from: '$trimmed_message'");
                // Skip test messages
                return;
            }
        }
        
        // Generate message hash for deduplication
        // Hash based on sender + message + time (rounded to nearest minute) to catch near-duplicates
        $rounded_time = floor($timestamp / 60) * 60;
        $hash_input = $node_from . '|' . $message_text . '|' . $rounded_time;
        $message_hash = hash('sha256', $hash_input);
        
        // Check if this message hash already exists
        $check_stmt = $this->db->pdo()->prepare("SELECT id FROM text_messages WHERE message_hash = ?");
        $check_stmt->execute([$message_hash]);
        
        if ($check_stmt->fetch()) {
            // Message already exists, skip insertion
            return;
        }
        
        // Insert new message with hash
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO text_messages (node_from, node_to, message, rx_time, topic, message_hash) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([$node_from, $node_to, $message_text, $timestamp, $topic, $message_hash]);
        } catch (\PDOException $e) {
            // If unique constraint violation, it means another thread inserted the same hash
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                // Silently skip duplicate
                return;
            }
            // Re-throw other errors
            throw $e;
        }
    }
    
    private function processMapReport(?int $node_from, array $payload, string $channel_id, int $timestamp): void
    {
        if (!$node_from) return;
        
        // Update node info if available
        if (isset($payload['long_name']) || isset($payload['short_name'])) {
            $this->processNodeInfo($node_from, $payload, $timestamp);
        }
        
        // Update position if available
        if (isset($payload['latitude_i'], $payload['longitude_i'])) {
            $this->processPositionData($node_from, $payload, [], $timestamp);
        }
        
        // Store map report
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO map_reports (node_num, channel_id, raw_pb, saved_at) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$node_from, $channel_id, json_encode($payload), $timestamp]);
    }
}
