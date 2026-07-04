<?php
/**
 * Historical Port Analysis
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Historical Port Analysis ===\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Get all port numbers we've ever seen
    echo "=== All Port Numbers Ever Seen ===\n";
    $allPorts = $db->pdo()->query("
        SELECT 
            port_num,
            COUNT(*) as total_count,
            COUNT(DISTINCT message_type) as message_types,
            MIN(processed_at) as first_seen,
            MAX(processed_at) as last_seen
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        GROUP BY port_num
        ORDER BY total_count DESC
    ")->fetchAll();
    
    $portNames = [
        0 => 'UNKNOWN_APP (Heartbeat)',
        1 => 'TEXT_MESSAGE_APP',
        2 => 'REMOTE_HARDWARE_APP', 
        3 => 'POSITION_APP',
        4 => 'NODEINFO_APP',
        5 => 'ROUTING_APP',
        6 => 'ADMIN_APP',
        7 => 'TEXT_MESSAGE_COMPRESSED_APP',
        8 => 'WAYPOINT_APP',
        9 => 'AUDIO_APP',
        10 => 'DETECTION_SENSOR_APP',
        32 => 'REPLY_APP',
        33 => 'IP_TUNNEL_APP',
        34 => 'PAXCOUNTER_APP',
        64 => 'SERIAL_APP',
        65 => 'STORE_FORWARD_APP',
        66 => 'RANGE_TEST_APP',
        67 => 'TELEMETRY_APP',
        68 => 'ZPS_APP',
        69 => 'SIMULATOR_APP',
        70 => 'TRACEROUTE_APP',
        71 => 'NEIGHBORINFO_APP',
        72 => 'ATAK_PLUGIN_APP',
        73 => 'MAP_REPORT_APP',
        74 => 'POWERSTRESS_APP',
        256 => 'PRIVATE_APP',
        257 => 'ATAK_FORWARDER_APP'
    ];
    
    foreach ($allPorts as $port) {
        $portName = $portNames[$port['port_num']] ?? "UNKNOWN_PORT_{$port['port_num']}";
        $firstSeen = $port['first_seen'] ? date('Y-m-d H:i', $port['first_seen']) : 'unknown';
        $lastSeen = $port['last_seen'] ? date('Y-m-d H:i', $port['last_seen']) : 'unknown';
        
        echo sprintf("Port %2d (%s):\n", $port['port_num'], $portName);
        echo sprintf("  Total: %d messages, %d message types\n", $port['total_count'], $port['message_types']);
        echo sprintf("  Period: %s to %s\n\n", $firstSeen, $lastSeen);
    }
    
    // Check what message types we see for each port
    echo "=== Message Types by Port ===\n";
    $messageTypes = $db->pdo()->query("
        SELECT 
            port_num,
            message_type,
            COUNT(*) as count
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        GROUP BY port_num, message_type
        ORDER BY port_num, count DESC
    ")->fetchAll();
    
    $currentPort = null;
    foreach ($messageTypes as $row) {
        if ($currentPort !== $row['port_num']) {
            $currentPort = $row['port_num'];
            $portName = $portNames[$currentPort] ?? "UNKNOWN_PORT_$currentPort";
            echo "\nPort $currentPort ($portName):\n";
        }
        echo sprintf("  %-20s: %d\n", $row['message_type'], $row['count']);
    }
    
    // Show successful vs failed decodes by port
    echo "\n=== Success Rate by Port ===\n";
    $successRates = $db->pdo()->query("
        SELECT 
            port_num,
            CASE 
                WHEN message_type IN ('DECODE_FAILED', 'JSON_PARSE_ERROR') THEN 'Failed'
                ELSE 'Success'
            END as status,
            COUNT(*) as count
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        GROUP BY port_num, status
        ORDER BY port_num
    ")->fetchAll();
    
    $portSuccessData = [];
    foreach ($successRates as $row) {
        $port = $row['port_num'];
        if (!isset($portSuccessData[$port])) {
            $portSuccessData[$port] = ['Success' => 0, 'Failed' => 0];
        }
        $portSuccessData[$port][$row['status']] = $row['count'];
    }
    
    foreach ($portSuccessData as $port => $data) {
        $portName = $portNames[$port] ?? "UNKNOWN_PORT_$port";
        $total = $data['Success'] + $data['Failed'];
        $successRate = $total > 0 ? round(($data['Success'] / $total) * 100, 1) : 0;
        
        echo sprintf("Port %2d (%s): %d%% success (%d/%d)\n",
            $port,
            substr($portName, 0, 25),
            $successRate,
            $data['Success'],
            $total
        );
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
