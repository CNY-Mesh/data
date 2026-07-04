<?php
/**
 * Port Type Analysis
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Meshtastic Port Type Analysis ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Get port number distribution from raw messages
    echo "=== Port Number Distribution (Last 1000 Messages) ===\n";
    $ports = $db->pdo()->query("
        SELECT 
            port_num,
            message_type,
            COUNT(*) as count,
            MAX(processed_at) as latest
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        ORDER BY id DESC
        LIMIT 1000
    ")->fetchAll();
    
    // Group by port number
    $portStats = [];
    foreach ($ports as $row) {
        $port = $row['port_num'];
        if (!isset($portStats[$port])) {
            $portStats[$port] = [
                'total' => 0,
                'types' => [],
                'latest' => 0
            ];
        }
        $portStats[$port]['total'] += $row['count'];
        $portStats[$port]['types'][$row['message_type']] = ($portStats[$port]['types'][$row['message_type']] ?? 0) + $row['count'];
        $portStats[$port]['latest'] = max($portStats[$port]['latest'], $row['latest']);
    }
    
    // Sort by total count
    uasort($portStats, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    // Port name mapping (from our expanded definitions)
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
    
    foreach ($portStats as $port => $stats) {
        $portName = $portNames[$port] ?? "UNKNOWN_PORT_$port";
        $latest = $stats['latest'] ? date('H:i:s', $stats['latest']) : 'unknown';
        
        echo sprintf("Port %2d (%s): %d messages, latest: %s\n", 
            $port, 
            $portName, 
            $stats['total'],
            $latest
        );
        
        // Show message type breakdown
        foreach ($stats['types'] as $type => $count) {
            echo sprintf("  └─ %s: %d\n", $type, $count);
        }
    }
    
    // Check for recent activity with specific ports
    echo "\n=== Recent Activity by Port (Last 50 Messages) ===\n";
    $recent = $db->pdo()->query("
        SELECT port_num, message_type, channel_id, processed_at
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        ORDER BY id DESC 
        LIMIT 50
    ")->fetchAll();
    
    foreach ($recent as $msg) {
        $portName = $portNames[$msg['port_num']] ?? "UNKNOWN_PORT_{$msg['port_num']}";
        $time = $msg['processed_at'] ? date('H:i:s', $msg['processed_at']) : 'unknown';
        
        echo sprintf("%s - Port %2d (%s) - %s - %s\n",
            $time,
            $msg['port_num'],
            substr($portName, 0, 20),
            $msg['message_type'],
            $msg['channel_id']
        );
    }
    
    // Show potential new port types we could decode
    echo "\n=== Expansion Opportunities ===\n";
    $unhandledPorts = $db->pdo()->query("
        SELECT DISTINCT port_num, COUNT(*) as count
        FROM raw_messages 
        WHERE message_type = 'DECODE_FAILED'
        AND port_num IS NOT NULL
        AND port_num NOT IN (1, 3, 4, 67, 70, 71, 73)  -- Currently handled ports
        GROUP BY port_num
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();
    
    foreach ($unhandledPorts as $port) {
        $portName = $portNames[$port['port_num']] ?? "UNKNOWN_PORT_{$port['port_num']}";
        echo sprintf("Port %2d (%s): %d failed decodes - could add handler\n",
            $port['port_num'],
            $portName,
            $port['count']
        );
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
