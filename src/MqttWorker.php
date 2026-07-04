<?php
declare(strict_types=1);
namespace App;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Handlers\{ NodeInfoHandler, PositionHandler, NeighborInfoHandler, TelemetryHandler, TracerouteHandler, MapReportHandler, RawMessageHandler, TextMessageHandler, CustomPortHandler };
use App\Support\Env as E;

final class MqttWorker
{
    private $debugLog;
    private int $lastHeartbeat;
    
    public function __construct(
        private Database $db,
        private Decoder $decoder,
        private string $host,
        private int $port,
        private string $clientId
    ) {
        $this->debugLog = fopen(__DIR__ . '/../debug.log', 'a');
        $this->debug("=== MQTT Worker Started at " . date('Y-m-d H:i:s') . " ===");
        
        // Set up custom error handler to suppress protobuf warnings
        set_error_handler([$this, 'customErrorHandler'], E_WARNING | E_NOTICE);
    }
    
    /**
     * Custom error handler to suppress protobuf-related warnings
     */
    private function customErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Suppress "Uninitialized string offset" warnings from protobuf files
        if ($errno === E_WARNING && 
            (strpos($errstr, 'Uninitialized string offset') !== false) &&
            (strpos($errfile, '/Protos/Meshtastic/') !== false || strpos($errfile, '/Meshtastic/') !== false)) {
            
            $this->debug("Suppressed protobuf warning: $errstr in " . basename($errfile) . ":$errline");
            return true; // Suppress the warning
        }
        
        // Let other errors be handled normally
        return false;
    }
    
    private function debug(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message\n";
        echo $line;
        if ($this->debugLog) {
            fwrite($this->debugLog, $line);
            fflush($this->debugLog);
        }
    }
    
    public function __destruct()
    {
        if ($this->debugLog) {
            $this->debug("=== MQTT Worker Stopped at " . date('Y-m-d H:i:s') . " ===");
            fclose($this->debugLog);
        }
    }

    public function run(): void
    {
        $settings = (new ConnectionSettings())
            ->setUseTls(false)
            ->setKeepAliveInterval((int) E::get('MQTT_KEEPALIVE', 60))
            ->setUsername(E::get('MQTT_USERNAME') ?: 'meshdev')
            ->setPassword(E::get('MQTT_PASSWORD') ?: 'large4cats');

        try {
            $client = new MqttClient($this->host, $this->port, $this->clientId);
            $client->connect($settings, (bool)(int)(E::get('MQTT_CLEAN_SESSION', '1')));
            $this->debug("Connected to MQTT broker: {$this->host}:{$this->port}");
        } catch (\Throwable $e) {
            $this->debug("Failed to connect to MQTT broker: " . $e->getMessage());
            return;
        }

        $topic = E::get('MQTT_TOPIC', 'msh/US/#');
        try {
            $client->subscribe($topic, function (string $topic, string $message, bool $retained) {
                $this->debug("Received message on topic: $topic (length: " . strlen($message) . " bytes)");
                $this->handleMessage($topic, $message);
            }, 0);
            $this->debug("Subscribed to topic: $topic");
        } catch (\Throwable $e) {
            $this->debug("Failed to subscribe: " . $e->getMessage());
            return;
        }

        try {
            $client->loop(true);
        } catch (\Throwable $e) {
            echo "[ERROR] Error in MQTT loop: " . $e->getMessage() . "\n";
        }
        $client->disconnect();
    }

    private function handleMessage(string $topic, string $payload): void
    {
        if ($payload === '') {
            return;
        }

        // Handle JSON messages first (they were working)
        if (isset($payload[0]) && $payload[0] == '{') {
            $jsonData = json_decode($payload, true);
            if ($jsonData !== null && json_last_error() === JSON_ERROR_NONE) {
                $this->debug("Processing JSON message");
                $this->handleJsonMessage($topic, $payload);
                return;
            }
        }

        // Skip obvious text messages
        if (ctype_print($payload) && strlen($payload) < 50) {
            $this->debug("Skipping short text message");
            return;
        }

        // Process binary protobuf messages
        $this->debug("Processing binary message on topic: $topic");
        $this->debug("Payload length: " . strlen($payload) . " bytes");
        $this->debug("First 20 bytes (hex): " . bin2hex(substr($payload, 0, min(20, strlen($payload)))));
        
        // Parse the ServiceEnvelope
        $env = $this->decoder->parseEnvelope($payload);
        if ($env === null) {
            $this->debug("Failed to parse ServiceEnvelope");
            return;
        }

        $this->debug("Successfully parsed ServiceEnvelope");
        $this->debug("Channel ID: " . $env->getChannelId());
        $this->debug("Gateway ID: " . $env->getGatewayId());
        $this->debug("Has packet: " . ($env->hasPacket() ? 'yes' : 'no'));

        if (!$env->hasPacket()) {
            $this->debug("No packet in envelope");
            return;
        }

        // Get the decoded data
        $this->debug("Getting decoded data from envelope");
        $decoded = $this->decoder->getDecodedData($env);
        if ($decoded === null) {
            $this->debug("Failed to decode data - storing raw for analysis");
            // Store the raw data even if we can't decode it
            $this->storeRawFailure($topic, $env, $payload);
            return;
        }

        [$data, $packet] = $decoded;
        $this->debug("Successfully decoded data");
        
        // Extract packet info
        $nodeFrom = $packet->getFrom();
        $nodeTo   = $packet->getTo();
        $rxTs     = $packet->getRxTime() ?: time();
        $rssi     = $packet->hasRxRssi() ? $packet->getRxRssi() : null;
        $snr      = $packet->hasRxSnr()  ? $packet->getRxSnr()  : null;
        $port     = $data->getPortnum();
        $raw      = $data->getPayload();
        
        $this->debug("Processing message: From=$nodeFrom, To=$nodeTo, Port=$port, PayloadLen=" . strlen($raw));

        // Store raw message data
        $rawHandler = new RawMessageHandler($this->db);
        $rawHandler->store(
            $topic,
            (string)$env->getChannelId(),
            (string)$env->getGatewayId(),
            $nodeFrom,
            $nodeTo,
            $port,
            $raw,
            strpos($topic, '/e/') !== false, // was encrypted
            false, // binary data
            $this->getPortName($port),
            $rxTs,
            $rssi,
            $snr,
            $payload
        );

        // Process based on port (expanded with more port types)
        $result = match ($port) {
            Decoder::TEXT_MESSAGE_APP => (new TextMessageHandler($this->db))->store($nodeFrom, $nodeTo, $raw, $rxTs),
            Decoder::NODEINFO_APP     => (new NodeInfoHandler($this->db))->upsert($nodeFrom, $this->decoder->parseUser($raw) ?: new \Meshtastic\User(), $rxTs),
            Decoder::POSITION_APP     => (new PositionHandler($this->db))->upsert($nodeFrom, $this->decoder->parsePosition($raw) ?: new \Meshtastic\Position(), $rssi, $snr, $rxTs),
            Decoder::NEIGHBORINFO_APP => (new NeighborInfoHandler($this->db))->insertReport($nodeFrom, $this->decoder->parseNeighborInfo($raw) ?: new \Meshtastic\NeighborInfo(), $rxTs),
            Decoder::TELEMETRY_APP    => (new TelemetryHandler($this->db))->upsert($nodeFrom, $this->decoder->parseTelemetry($raw) ?: new \Meshtastic\Telemetry(), $rxTs),
            Decoder::TRACEROUTE_APP   => (new TracerouteHandler($this->db))->insertRoute($packet->getId(), $nodeFrom, $nodeTo, $this->decoder->parseRouteDiscovery($raw) ?: new \Meshtastic\RouteDiscovery(), $rxTs),
            Decoder::MAP_REPORT_APP   => (new MapReportHandler($this->db))->store($nodeFrom, (string)$env->getChannelId(), $this->decoder->parseMapReport($raw) ?: new \Meshtastic\MapReport(), $rxTs, $raw),
            
            // Additional message types - log for now, can add specific handlers later
            Decoder::REMOTE_HARDWARE_APP => $this->logUnhandledPort($port, 'REMOTE_HARDWARE_APP', $nodeFrom, $raw),
            Decoder::ROUTING_APP => $this->logUnhandledPort($port, 'ROUTING_APP', $nodeFrom, $raw),
            Decoder::ADMIN_APP => $this->logUnhandledPort($port, 'ADMIN_APP', $nodeFrom, $raw),
            Decoder::TEXT_MESSAGE_COMPRESSED_APP => $this->logUnhandledPort($port, 'TEXT_MESSAGE_COMPRESSED_APP', $nodeFrom, $raw),
            Decoder::WAYPOINT_APP => $this->logUnhandledPort($port, 'WAYPOINT_APP', $nodeFrom, $raw),
            Decoder::AUDIO_APP => $this->logUnhandledPort($port, 'AUDIO_APP', $nodeFrom, $raw),
            Decoder::DETECTION_SENSOR_APP => $this->logUnhandledPort($port, 'DETECTION_SENSOR_APP', $nodeFrom, $raw),
            Decoder::REPLY_APP => $this->logUnhandledPort($port, 'REPLY_APP', $nodeFrom, $raw),
            Decoder::IP_TUNNEL_APP => $this->logUnhandledPort($port, 'IP_TUNNEL_APP', $nodeFrom, $raw),
            Decoder::PAXCOUNTER_APP => $this->logUnhandledPort($port, 'PAXCOUNTER_APP', $nodeFrom, $raw),
            Decoder::SERIAL_APP => $this->logUnhandledPort($port, 'SERIAL_APP', $nodeFrom, $raw),
            Decoder::STORE_FORWARD_APP => $this->logUnhandledPort($port, 'STORE_FORWARD_APP', $nodeFrom, $raw),
            Decoder::RANGE_TEST_APP => $this->logUnhandledPort($port, 'RANGE_TEST_APP', $nodeFrom, $raw),
            Decoder::ZPS_APP => $this->logUnhandledPort($port, 'ZPS_APP', $nodeFrom, $raw),
            Decoder::SIMULATOR_APP => $this->logUnhandledPort($port, 'SIMULATOR_APP', $nodeFrom, $raw),
            Decoder::ATAK_PLUGIN_APP => $this->logUnhandledPort($port, 'ATAK_PLUGIN_APP', $nodeFrom, $raw),
            Decoder::POWERSTRESS_APP => $this->logUnhandledPort($port, 'POWERSTRESS_APP', $nodeFrom, $raw),
            Decoder::PRIVATE_APP => $this->logUnhandledPort($port, 'PRIVATE_APP', $nodeFrom, $raw),
            Decoder::ATAK_FORWARDER_APP => $this->logUnhandledPort($port, 'ATAK_FORWARDER_APP', $nodeFrom, $raw),
            
            default => $this->logUnhandledPort($port, "UNKNOWN_PORT_$port", $nodeFrom, $raw)
        };
        
        $this->debug("Handler result for port $port: " . ($result ? 'success' : 'logged'));
    }

    private function logUnhandledPort(int $port, string $portName, int $nodeFrom, string $raw): bool
    {
        $this->debug("Unhandled port $port ($portName) from node $nodeFrom, payload length: " . strlen($raw));
        if (strlen($raw) > 0) {
            $this->debug("Payload hex (first 32 bytes): " . bin2hex(substr($raw, 0, 32)));
        }
        
        // Store custom port data for analysis
        try {
            $customHandler = new CustomPortHandler($this->db);
            $result = $customHandler->handleUnknownPort($port, $nodeFrom, $raw, time());
            $this->debug("Custom port analysis: " . json_encode($result['analysis']));
        } catch (\Exception $e) {
            $this->debug("Error handling custom port: " . $e->getMessage());
        }
        
        return true; // Mark as "handled" for logging purposes
    }

    private function getPortName(int $port): ?string
    {
        return match ($port) {
            Decoder::UNKNOWN_APP => 'UNKNOWN_APP',
            Decoder::TEXT_MESSAGE_APP => 'TEXT_MESSAGE_APP',
            Decoder::REMOTE_HARDWARE_APP => 'REMOTE_HARDWARE_APP',
            Decoder::POSITION_APP => 'POSITION_APP',
            Decoder::NODEINFO_APP => 'NODEINFO_APP', 
            Decoder::ROUTING_APP => 'ROUTING_APP',
            Decoder::ADMIN_APP => 'ADMIN_APP',
            Decoder::TEXT_MESSAGE_COMPRESSED_APP => 'TEXT_MESSAGE_COMPRESSED_APP',
            Decoder::WAYPOINT_APP => 'WAYPOINT_APP',
            Decoder::AUDIO_APP => 'AUDIO_APP',
            Decoder::DETECTION_SENSOR_APP => 'DETECTION_SENSOR_APP',
            Decoder::REPLY_APP => 'REPLY_APP',
            Decoder::IP_TUNNEL_APP => 'IP_TUNNEL_APP',
            Decoder::PAXCOUNTER_APP => 'PAXCOUNTER_APP',
            Decoder::SERIAL_APP => 'SERIAL_APP',
            Decoder::STORE_FORWARD_APP => 'STORE_FORWARD_APP',
            Decoder::RANGE_TEST_APP => 'RANGE_TEST_APP',
            Decoder::TELEMETRY_APP => 'TELEMETRY_APP',
            Decoder::ZPS_APP => 'ZPS_APP',
            Decoder::SIMULATOR_APP => 'SIMULATOR_APP',
            Decoder::TRACEROUTE_APP => 'TRACEROUTE_APP',
            Decoder::NEIGHBORINFO_APP => 'NEIGHBORINFO_APP',
            Decoder::ATAK_PLUGIN_APP => 'ATAK_PLUGIN_APP',
            Decoder::MAP_REPORT_APP => 'MAP_REPORT_APP',
            Decoder::POWERSTRESS_APP => 'POWERSTRESS_APP',
            Decoder::PRIVATE_APP => 'PRIVATE_APP',
            Decoder::ATAK_FORWARDER_APP => 'ATAK_FORWARDER_APP',
            0 => 'HEARTBEAT',
            default => "UNKNOWN_PORT_$port"
        };
    }

    private function storeRawFailure(string $topic, $env, string $payload): void
    {
        try {
            $rawHandler = new RawMessageHandler($this->db);
            $rawHandler->store(
                $topic,
                (string)$env->getChannelId(),
                (string)$env->getGatewayId(),
                null, 
                null,
                null,
                null,
                strpos($topic, '/e/') !== false,
                false,
                'DECODE_FAILED',
                time(),
                null,
                null,
                $payload
            );
        } catch (\Throwable $e) {
            $this->debug("Failed to store decode failure: " . $e->getMessage());
        }
    }

    private function handleJsonMessage(string $topic, string $payload): void
    {
        try {
            $data = json_decode($payload, true);
            if ($data === null) {
                $this->debug("Failed to decode JSON payload");
                return;
            }
            
            // Extract common fields
            $nodeFrom = $data['from'] ?? null;
            $nodeTo = $data['to'] ?? null;
            $type = $data['type'] ?? null;
            $timestamp = $data['timestamp'] ?? time();
            $channelIndex = $data['channel'] ?? null;
            
            // Extract channel name from topic (e.g., "msh/US/NY/2/json/LongFast/!123" -> "LongFast")
            $topicParts = explode('/', $topic);
            $channelName = null;
            if (count($topicParts) >= 6 && $topicParts[4] === 'json') {
                $channelName = $topicParts[5];
            }
            
            $this->debug("JSON message: type=$type, from=$nodeFrom, to=$nodeTo, channel=$channelIndex, channelName=$channelName");
            
            // Always store raw JSON message
            $rawHandler = new RawMessageHandler($this->db);
            $rawHandler->store(
                $topic,
                $channelName, // Use channel name from topic, not index number
                null, // Gateway ID not available in JSON
                $nodeFrom,
                $nodeTo,
                null, // Port number not available in JSON
                json_encode($data),
                false, // JSON messages are not encrypted in transit
                true,  // This is JSON data
                $type, // Message type available in JSON
                $timestamp,
                null,  // RSSI not available in JSON
                null,  // SNR not available in JSON
                $payload
            );
            
            if (!$nodeFrom || !$type) {
                $this->debug("Missing required fields: nodeFrom=$nodeFrom, type=$type");
                return;
            }
            
            // Handle different Message types
            switch ($type) {
                case 'position':
                    $this->debug("Processing JSON position message");
                    if (isset($data['payload'])) {
                        $this->handleJsonPosition($nodeFrom, $data['payload'], $data['rssi'] ?? null, $data['snr'] ?? null, $timestamp);
                    }
                    break;
                case 'telemetry':
                    $this->debug("Processing JSON telemetry message");
                    if (isset($data['payload'])) {
                        $this->handleJsonTelemetry($nodeFrom, $data['payload'], $timestamp);
                    }
                    break;
                case 'nodeinfo':
                    $this->debug("Processing JSON nodeinfo message");
                    if (isset($data['payload'])) {
                        $this->handleJsonNodeInfo($nodeFrom, $data['payload'], $timestamp);
                    }
                    break;
                case 'text':
                    $this->debug("Processing JSON text message");
                    if (isset($data['payload'])) {
                        $this->handleJsonTextMessage($nodeFrom, $nodeTo, $data['payload'], $timestamp);
                    }
                    break;
                case 'neighborinfo':
                    $this->debug("Processing JSON neighborinfo message");
                    if (isset($data['payload'])) {
                        $this->handleJsonNeighborInfo($nodeFrom, $data['payload'], $timestamp);
                    }
                    break;
                case 'traceroute':
                    $this->debug("Processing JSON traceroute message");
                    if (isset($data['payload'])) {
                        $this->handleJsonTraceroute($nodeFrom, $data['payload'], $timestamp);
                    }
                    break;
                default:
                    $this->debug("Unknown JSON message type: $type");
                    break;
            }
        } catch (\Throwable $e) {
            $this->debug("Exception in handleJsonMessage: " . $e->getMessage());
        }
    }

    private function handleJsonPosition(int $nodeFrom, array $payload, ?float $rssi, ?float $snr, int $timestamp): void
    {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO positions (node_num, lat, lon, altitude, time, rx_rssi, rx_snr) VALUES (?, ?, ?, ?, ?, ?, ?)');
            
            $lat = isset($payload['latitude_i']) ? $payload['latitude_i'] / 10000000.0 : null;
            $lon = isset($payload['longitude_i']) ? $payload['longitude_i'] / 10000000.0 : null;
            $altitude = $payload['altitude'] ?? null;
            $time = $payload['time'] ?? $timestamp;
            
            $stmt->execute([$nodeFrom, $lat, $lon, $altitude, $time, $rssi, $snr]);
            $this->debug("Stored JSON position for node $nodeFrom: lat=$lat, lon=$lon, alt=$altitude");
        } catch (\Throwable $e) {
            $this->debug("Error storing JSON position: " . $e->getMessage());
        }
    }

    private function handleJsonTelemetry(int $nodeFrom, array $payload, int $timestamp): void
    {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO telemetry (node_num, battery_level, voltage, channel_utilization, air_util_tx, uptime_seconds, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            
            $battery = $payload['battery_level'] ?? null;
            $voltage = $payload['voltage'] ?? null;
            $channelUtil = $payload['channel_utilization'] ?? null;
            $airUtil = $payload['air_util_tx'] ?? null;
            $uptime = $payload['uptime_seconds'] ?? null;
            
            $stmt->execute([$nodeFrom, $battery, $voltage, $channelUtil, $airUtil, $uptime, $timestamp]);
            $this->debug("Stored JSON telemetry for node $nodeFrom");
        } catch (\Throwable $e) {
            $this->debug("Error storing JSON telemetry: " . $e->getMessage());
        }
    }

    private function handleJsonNodeInfo(int $nodeFrom, array $payload, int $timestamp): void
    {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO nodes (node_num, node_id, long_name, short_name, hardware, last_seen) VALUES (?, ?, ?, ?, ?, ?)');
            
            // Map JSON field names to database columns
            $nodeId = $payload['id'] ?? null;
            $shortName = $payload['shortname'] ?? null;
            $longName = $payload['longname'] ?? null;
            $hardware = $payload['hardware'] ?? null;
            
            $stmt->execute([$nodeFrom, $nodeId, $longName, $shortName, $hardware, $timestamp]);
            $this->debug("Stored JSON nodeinfo for node $nodeFrom: $shortName ($longName) - ID: $nodeId, HW: $hardware");
        } catch (\Throwable $e) {
            $this->debug("Error storing JSON nodeinfo: " . $e->getMessage());
        }
    }

    private function handleJsonTextMessage(int $nodeFrom, ?int $nodeTo, array $payload, int $timestamp): void
    {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('INSERT INTO text_messages (from_node, to_node, message_text, created_at) VALUES (?, ?, ?, ?)');
            
            $text = $payload['text'] ?? null;
            if ($text !== null) {
                $stmt->execute([$nodeFrom, $nodeTo, $text, $timestamp]);
                $this->debug("Stored JSON text message from node $nodeFrom to $nodeTo");
            }
        } catch (\Throwable $e) {
            $this->debug("Error storing JSON text message: " . $e->getMessage());
        }
    }

    private function handleJsonNeighborInfo(int $nodeFrom, array $payload, int $timestamp): void
    {
        try {
            $pdo = $this->db->pdo();
            
            // First, clear old neighbor data for this node
            $deleteStmt = $pdo->prepare('DELETE FROM neighbors WHERE node_num = ?');
            $deleteStmt->execute([$nodeFrom]);
            
            $insertStmt = $pdo->prepare('INSERT INTO neighbors (node_num, neighbor_node_num, snr, last_heard, updated_at) VALUES (?, ?, ?, ?, ?)');
            
            $neighbors = $payload['neighbors'] ?? [];
            $count = 0;
            foreach ($neighbors as $neighbor) {
                $neighborNodeNum = $neighbor['node_id'] ?? null;
                $snr = $neighbor['snr'] ?? null;
                $lastHeard = $neighbor['last_heard'] ?? null;
                
                if ($neighborNodeNum !== null) {
                    $insertStmt->execute([$nodeFrom, $neighborNodeNum, $snr, $lastHeard, $timestamp]);
                    $count++;
                }
            }
            
            $this->debug("Stored $count JSON neighbors for node $nodeFrom");
        } catch (\Throwable $e) {
            $this->debug("Error storing JSON neighbors: " . $e->getMessage());
        }
    }

    private function handleJsonTraceroute(int $nodeFrom, array $payload, int $timestamp): void
    {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('INSERT INTO traceroutes (from_node, to_node, route, created_at) VALUES (?, ?, ?, ?)');
            
            $toNode = $payload['dest'] ?? null;
            $route = isset($payload['route']) ? json_encode($payload['route']) : null;
            
            if ($toNode !== null && $route !== null) {
                $stmt->execute([$nodeFrom, $toNode, $route, $timestamp]);
                $this->debug("Stored JSON traceroute from node $nodeFrom to $toNode");
            }
        } catch (\Throwable $e) {
            $this->debug("Error storing JSON traceroute: " . $e->getMessage());
        }
    }

    private function handleMqttEncryptedMessage(string $topic, string $payload): void
    {
        try {
            // Parse topic structure: msh/US/NY/2/e/LongFast/!node_id
            $topicParts = explode('/', $topic);
            $channelName = null;
            $nodeIdHex = null;
            
            foreach ($topicParts as $part) {
                if (in_array($part, ['LongFast', 'LongSlow', 'MediumFast', 'MediumSlow', 'ShortFast', 'ShortSlow', 'VeryLongSlow', 'private', 'admin'])) {
                    $channelName = $part;
                }
                if (strpos($part, '!') === 0) {
                    $nodeIdHex = $part;
                }
            }
            
            $this->debug("MQTT encrypted message from channel: $channelName, node: $nodeIdHex");
            
            // Handle all channels with default key
            if (!$channelName) {
                $this->debug("No channel name found in topic");
                return;
            }
            
            // First try parsing as ServiceEnvelope (like regular messages)
            // The /e/ topic might still contain ServiceEnvelopes with encrypted packets inside
            $this->debug("Trying to parse /e/ message as ServiceEnvelope first");
            $env = $this->decoder->parseEnvelope($payload);
            if ($env !== null) {
                $this->debug("Successfully parsed /e/ message as ServiceEnvelope");
                $decoded = $this->decoder->getDecodedData($env);
                if ($decoded !== null) {
                    [$data, $packet] = $decoded;
                    
                    $nodeFrom = $packet->getFrom();
                    $nodeTo   = $packet->getTo();
                    $port = $data->getPortnum();
                    $payloadData = $data->getPayload();
                    
                    $this->debug("🎉 Successfully decoded /e/ ServiceEnvelope! Port: $port, From: $nodeFrom, Payload: " . strlen($payloadData) . " bytes");
                    
                    if ($port > 0) {
                        $rxTs = time();
                        
                        // Store raw message
                        $rawHandler = new \App\Handlers\RawMessageHandler($this->db);
                        $rawHandler->store(
                            $topic,
                            $channelName,
                            (string)$env->getGatewayId(),
                            $nodeFrom,
                            $nodeTo,
                            $port,
                            $payloadData,
                            true, // Was encrypted
                            false, // Not JSON
                            null, // Message type
                            $rxTs,
                            $packet->hasRxRssi() ? $packet->getRxRssi() : null,
                            $packet->hasRxSnr() ? $packet->getRxSnr() : null,
                            $payload
                        );
                        
                        // Process with appropriate handler
                        $result = match ($port) {
                            \App\Decoder::TEXT_MESSAGE_APP => (new \App\Handlers\TextMessageHandler($this->db))->store($nodeFrom, $nodeTo, $payloadData, $rxTs),
                            \App\Decoder::NODEINFO_APP     => (new \App\Handlers\NodeInfoHandler($this->db))->upsert($nodeFrom, $this->decoder->parseUser($payloadData) ?: new \Meshtastic\User(), $rxTs),
                            \App\Decoder::POSITION_APP     => (new \App\Handlers\PositionHandler($this->db))->upsert($nodeFrom, $this->decoder->parsePosition($payloadData) ?: new \Meshtastic\Position(), null, null, $rxTs),
                            \App\Decoder::NEIGHBORINFO_APP => (new \App\Handlers\NeighborInfoHandler($this->db))->insertReport($nodeFrom, $this->decoder->parseNeighborInfo($payloadData) ?: new \Meshtastic\NeighborInfo(), $rxTs),
                            \App\Decoder::TELEMETRY_APP    => (new \App\Handlers\TelemetryHandler($this->db))->upsert($nodeFrom, $this->decoder->parseTelemetry($payloadData) ?: new \Meshtastic\Telemetry(), $rxTs),
                            \App\Decoder::TRACEROUTE_APP   => (new \App\Handlers\TracerouteHandler($this->db))->insertRoute(0, $nodeFrom, $nodeTo, $this->decoder->parseRouteDiscovery($payloadData) ?: new \Meshtastic\RouteDiscovery(), $rxTs),
                            \App\Decoder::MAP_REPORT_APP   => (new \App\Handlers\MapReportHandler($this->db))->store($nodeFrom, $channelName, $this->decoder->parseMapReport($payloadData) ?: new \Meshtastic\MapReport(), $rxTs, $payloadData),
                            default => null
                        };
                        
                        $this->debug("Handler result for port $port: " . ($result ? 'SUCCESS' : 'NULL'));
                        return; // Success, exit early
                    }
                }
            }
            
            $this->debug("ServiceEnvelope parsing failed, trying raw decryption");
            
            // If ServiceEnvelope parsing fails, try direct payload decryption
            // Get encryption key - try channel-specific first, then default
            $channelKeyVar = strtoupper($channelName) . '_B64_KEY';
            $b64Key = \App\Support\Env::get($channelKeyVar) ?: \App\Support\Env::get('LONGFAST_B64_KEY', 'AQ==');
            $key = base64_decode($b64Key);
            
            // For single-byte keys, pad or use different approach
            if (strlen($key) === 1) {
                // Keep the original single byte - this might be the correct approach
                echo "[DEBUG] Using single-byte key: " . bin2hex($key) . "\n";
            } elseif (strlen($key) !== 16 && strlen($key) !== 32) {
                $key = base64_decode('AQ=='); // Default single-byte key
            }
            
            echo "[DEBUG] Using key length: " . strlen($key) . " bytes for channel: $channelName\n";
            
            // Try different decryption approaches
            $ciphers = strlen($key) === 16 ? ['aes-128-ctr'] : ['aes-256-ctr'];
            
            foreach ($ciphers as $cipher) {
                // Try different IV patterns - Meshtastic uses specific IV construction
                $nodeId = $nodeIdHex ? intval(substr($nodeIdHex, 1), 16) : 0;
                
                $ivPatterns = [
                    str_repeat("\0", 16), // All zeros
                    pack('V', $nodeId) . str_repeat("\0", 12), // Node ID as little-endian + zeros
                    pack('N', $nodeId) . str_repeat("\0", 12), // Node ID as big-endian + zeros
                    substr(hash('md5', $nodeIdHex ?? 'test', true), 0, 16), // MD5 of node ID
                    substr($payload, 0, 16), // First bytes as IV (unlikely but worth trying)
                    substr($payload, -16), // Last bytes as IV
                ];
                
                foreach ($ivPatterns as $ivIndex => $iv) {
                    $decrypted = @openssl_decrypt($payload, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                    
                    if ($decrypted !== false && strlen($decrypted) > 4) {
                        // Check if this looks like valid protobuf data
                        // Protobuf messages typically start with field tags (small positive numbers)
                        $firstByte = ord($decrypted[0]);
                        if ($firstByte > 0 && $firstByte < 128) {
                            try {
                                $data = new \Meshtastic\Data();
                                $data->mergeFromString($decrypted);
                                
                                $port = $data->getPortnum();
                                $payloadData = $data->getPayload();
                                
                                // Validate that we got reasonable data
                                if ($port > 0 && $port < 1000) {
                                    echo "[DEBUG] 🎉 Successfully decrypted with IV pattern $ivIndex! Port: $port, Payload: " . strlen($payloadData) . " bytes\n";
                                    
                                    // Extract node info from hex string
                                    $nodeFrom = $nodeId;
                                    $nodeTo = 0xffffffff; // Broadcast
                                    $rxTs = time();
                                    
                                    // Store raw message
                                    $rawHandler = new \App\Handlers\RawMessageHandler($this->db);
                                    $rawHandler->store(
                                        $topic,
                                        $channelName,
                                        null, // Gateway ID
                                        $nodeFrom,
                                        $nodeTo,
                                        $port,
                                        $payloadData,
                                        true, // Was encrypted
                                        false, // Not JSON
                                        null, // Message type
                                        $rxTs,
                                        null, // RSSI
                                        null, // SNR
                                        $payload
                                    );
                                    
                                    // Process with appropriate handler
                                    $result = match ($port) {
                                        \App\Decoder::TEXT_MESSAGE_APP => (new \App\Handlers\TextMessageHandler($this->db))->store($nodeFrom, $nodeTo, $payloadData, $rxTs),
                                        \App\Decoder::NODEINFO_APP     => (new \App\Handlers\NodeInfoHandler($this->db))->upsert($nodeFrom, $this->decoder->parseUser($payloadData) ?: new \Meshtastic\User(), $rxTs),
                                        \App\Decoder::POSITION_APP     => (new \App\Handlers\PositionHandler($this->db))->upsert($nodeFrom, $this->decoder->parsePosition($payloadData) ?: new \Meshtastic\Position(), null, null, $rxTs),
                                        \App\Decoder::NEIGHBORINFO_APP => (new \App\Handlers\NeighborInfoHandler($this->db))->insertReport($nodeFrom, $this->decoder->parseNeighborInfo($payloadData) ?: new \Meshtastic\NeighborInfo(), $rxTs),
                                        \App\Decoder::TELEMETRY_APP    => (new \App\Handlers\TelemetryHandler($this->db))->upsert($nodeFrom, $this->decoder->parseTelemetry($payloadData) ?: new \Meshtastic\Telemetry(), $rxTs),
                                        \App\Decoder::TRACEROUTE_APP   => (new \App\Handlers\TracerouteHandler($this->db))->insertRoute(0, $nodeFrom, $nodeTo, $this->decoder->parseRouteDiscovery($payloadData) ?: new \Meshtastic\RouteDiscovery(), $rxTs),
                                        \App\Decoder::MAP_REPORT_APP   => (new \App\Handlers\MapReportHandler($this->db))->store($nodeFrom, $channelName, $this->decoder->parseMapReport($payloadData) ?: new \Meshtastic\MapReport(), $rxTs, $payloadData),
                                        default => null
                                    };
                                    
                                    echo "[DEBUG] Handler result for port $port: " . ($result ? 'SUCCESS' : 'NULL') . "\n";
                                    return; // Success, exit early
                                }
                            } catch (\Throwable $e) {
                                echo "[DEBUG] Protobuf parse error with IV pattern $ivIndex: " . $e->getMessage() . "\n";
                                // Continue trying other patterns
                            }
                        }
                    }
                }
            }
            
            echo "[DEBUG] Failed to decrypt MQTT message from $topic\n";
            
        } catch (\Throwable $e) {
            echo "[DEBUG] Error processing MQTT encrypted message: " . $e->getMessage() . "\n";
        }
    }
}
