<?php

require_once __DIR__ . '/bootstrap.php';

use App\Database;
use App\Decoder;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Support\Env as E;

class PortAnalyzer
{
    private array $portCounts = [];
    private int $messageCount = 0;
    private int $startTime;
    private Database $db;
    private Decoder $decoder;

    public function __construct()
    {
        $this->db = new Database();
        $this->decoder = new Decoder();
        $this->startTime = time();
    }

    public function start(): void
    {
        $settings = (new ConnectionSettings())
            ->setUseTls(false)
            ->setKeepAliveInterval((int) E::get('MQTT_KEEPALIVE', 60))
            ->setUsername(E::get('MQTT_USERNAME') ?: 'meshdev')
            ->setPassword(E::get('MQTT_PASSWORD') ?: 'large4cats');

        try {
            $client = new MqttClient(
                E::get('MQTT_HOST', 'mqtt.meshtastic.org'),
                (int) E::get('MQTT_PORT', 1883),
                'port-analyzer-' . uniqid()
            );
            
            $client->connect($settings, true);
            echo "Connected to MQTT broker. Analyzing for 30 seconds...\n";
            
            $client->subscribe(E::get('MQTT_TOPIC', 'msh/+/+/json'), function ($topic, $message) {
                $this->handleMessage($topic, $message);
            });
            
            $client->loop(true);
            $client->disconnect();
        } catch (\Throwable $e) {
            echo "MQTT Error: " . $e->getMessage() . "\n";
        }
    }

    private function handleMessage(string $topic, string $message): void
    {
        $this->messageCount++;
        
        // Only run for 30 seconds
        if (time() - $this->startTime > 30) {
            $this->printResults();
            exit(0);
        }

        try {
            // Handle JSON messages
            if (strpos($topic, '/json') !== false) {
                $data = json_decode($message, true);
                if (isset($data['type'])) {
                    $type = $data['type'];
                    if (!isset($this->portCounts["JSON_$type"])) {
                        $this->portCounts["JSON_$type"] = ['count' => 0, 'with_payload' => 0, 'example_payload_len' => 0];
                    }
                    $this->portCounts["JSON_$type"]['count']++;
                    $this->portCounts["JSON_$type"]['with_payload']++;
                }
                return;
            }

            // Handle binary messages
            $env = $this->decoder->parseServiceEnvelope($message);
            if (!$env || !$env->hasPacket()) {
                return;
            }

            $packet = $env->getPacket();
            $decodedData = $this->decoder->getDecodedData($env);
            
            if ($decodedData && isset($decodedData['port'])) {
                $port = $decodedData['port'];
                $payloadLen = isset($decodedData['payload']) ? strlen($decodedData['payload']) : 0;
                
                if (!isset($this->portCounts[$port])) {
                    $this->portCounts[$port] = ['count' => 0, 'with_payload' => 0, 'example_payload_len' => 0];
                }
                
                $this->portCounts[$port]['count']++;
                if ($payloadLen > 0) {
                    $this->portCounts[$port]['with_payload']++;
                    if ($this->portCounts[$port]['example_payload_len'] == 0) {
                        $this->portCounts[$port]['example_payload_len'] = $payloadLen;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silent error handling for malformed messages
        }

        // Print progress every 50 messages
        if ($this->messageCount % 50 == 0) {
            echo "Processed $this->messageCount messages...\n";
        }
    }

    private function printResults(): void
    {
        echo "\n=== PORT ANALYSIS RESULTS ===\n";
        echo "Total messages processed: $this->messageCount\n";
        echo "Unique ports/types found: " . count($this->portCounts) . "\n\n";

        // Known handlers
        $knownPorts = [
            3 => 'POSITION_APP',
            4 => 'NODEINFO_APP', 
            67 => 'TELEMETRY_APP',
            70 => 'TRACEROUTE_APP',
            71 => 'NEIGHBORINFO_APP',
            73 => 'MAP_REPORT_APP'
        ];

        echo "PORT/TYPE BREAKDOWN:\n";
        ksort($this->portCounts);
        
        foreach ($this->portCounts as $port => $data) {
            if (is_string($port) && strpos($port, 'JSON_') === 0) {
                $handler = 'JSON HANDLER';
            } else {
                $handler = $knownPorts[$port] ?? 'NO HANDLER';
            }
            
            $withPayload = $data['with_payload'];
            $total = $data['count'];
            $exampleLen = $data['example_payload_len'];
            
            echo sprintf(
                "%-15s: %-20s | %4d total, %4d with payload (example len: %d)\n",
                $port,
                $handler,
                $total,
                $withPayload,
                $exampleLen
            );
        }
        
        echo "\nMISSING HANDLERS (ports with data):\n";
        foreach ($this->portCounts as $port => $data) {
            if (is_numeric($port) && !isset($knownPorts[$port]) && $data['with_payload'] > 0) {
                echo "Port $port: {$data['with_payload']} messages with payload data\n";
            }
        }
    }
}

$analyzer = new PortAnalyzer();
$analyzer->start();
