<?php
/**
 * Real-time Port Monitoring
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Real-time Meshtastic Port Monitor ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
    $db = new Database($dsn);
    
    // Enhanced port definitions with descriptions
    $portDetails = [
        0 => ['name' => 'UNKNOWN_APP', 'desc' => 'Heartbeat/Keep-alive messages', 'type' => 'System'],
        1 => ['name' => 'TEXT_MESSAGE_APP', 'desc' => 'Text chat messages', 'type' => 'Communication'],
        2 => ['name' => 'REMOTE_HARDWARE_APP', 'desc' => 'Remote GPIO/hardware control', 'type' => 'Control'],
        3 => ['name' => 'POSITION_APP', 'desc' => 'GPS position updates', 'type' => 'Location'],
        4 => ['name' => 'NODEINFO_APP', 'desc' => 'Node information (name, hardware)', 'type' => 'Discovery'],
        5 => ['name' => 'ROUTING_APP', 'desc' => 'Mesh routing control messages', 'type' => 'Network'],
        6 => ['name' => 'ADMIN_APP', 'desc' => 'Administrative commands', 'type' => 'Management'],
        7 => ['name' => 'TEXT_MESSAGE_COMPRESSED_APP', 'desc' => 'Compressed text messages', 'type' => 'Communication'],
        8 => ['name' => 'WAYPOINT_APP', 'desc' => 'Waypoint/POI sharing', 'type' => 'Location'],
        9 => ['name' => 'AUDIO_APP', 'desc' => 'Audio messages', 'type' => 'Communication'],
        10 => ['name' => 'DETECTION_SENSOR_APP', 'desc' => 'Motion/detection sensors', 'type' => 'Sensor'],
        32 => ['name' => 'REPLY_APP', 'desc' => 'Message replies/acknowledgments', 'type' => 'Communication'],
        33 => ['name' => 'IP_TUNNEL_APP', 'desc' => 'IP tunneling over mesh', 'type' => 'Network'],
        34 => ['name' => 'PAXCOUNTER_APP', 'desc' => 'People/device counter', 'type' => 'Sensor'],
        64 => ['name' => 'SERIAL_APP', 'desc' => 'Serial data passthrough', 'type' => 'Data'],
        65 => ['name' => 'STORE_FORWARD_APP', 'desc' => 'Store and forward messages', 'type' => 'Network'],
        66 => ['name' => 'RANGE_TEST_APP', 'desc' => 'Range/signal testing', 'type' => 'Testing'],
        67 => ['name' => 'TELEMETRY_APP', 'desc' => 'Battery, signal, environmental', 'type' => 'Monitoring'],
        68 => ['name' => 'ZPS_APP', 'desc' => 'ZPS application', 'type' => 'Application'],
        69 => ['name' => 'SIMULATOR_APP', 'desc' => 'Network simulation', 'type' => 'Testing'],
        70 => ['name' => 'TRACEROUTE_APP', 'desc' => 'Network path tracing', 'type' => 'Network'],
        71 => ['name' => 'NEIGHBORINFO_APP', 'desc' => 'Neighbor discovery/info', 'type' => 'Discovery'],
        72 => ['name' => 'ATAK_PLUGIN_APP', 'desc' => 'ATAK tactical integration', 'type' => 'Military'],
        73 => ['name' => 'MAP_REPORT_APP', 'desc' => 'Map reporting/updates', 'type' => 'Location'],
        74 => ['name' => 'POWERSTRESS_APP', 'desc' => 'Power/stress testing', 'type' => 'Testing'],
        256 => ['name' => 'PRIVATE_APP', 'desc' => 'Private application base', 'type' => 'Custom'],
        257 => ['name' => 'ATAK_FORWARDER_APP', 'desc' => 'ATAK message forwarding', 'type' => 'Military']
    ];
    
    // Current activity summary
    echo "=== Current Activity Summary ===\n";
    $summary = $db->pdo()->query("
        SELECT 
            CASE 
                WHEN processed_at > strftime('%s', 'now', '-5 minutes') THEN 'Last 5 min'
                WHEN processed_at > strftime('%s', 'now', '-1 hour') THEN 'Last hour'
                WHEN processed_at > strftime('%s', 'now', '-1 day') THEN 'Last day'
                ELSE 'Older'
            END as timeframe,
            COUNT(*) as messages,
            COUNT(DISTINCT port_num) as unique_ports,
            COUNT(DISTINCT channel_id) as unique_channels
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        GROUP BY timeframe
        ORDER BY 
            CASE timeframe
                WHEN 'Last 5 min' THEN 1
                WHEN 'Last hour' THEN 2  
                WHEN 'Last day' THEN 3
                ELSE 4
            END
    ")->fetchAll();
    
    foreach ($summary as $period) {
        echo sprintf("%-12s: %d messages, %d ports, %d channels\n",
            $period['timeframe'],
            $period['messages'],
            $period['unique_ports'],
            $period['unique_channels']
        );
    }
    
    // Port activity breakdown
    echo "\n=== Port Activity (Last 24 Hours) ===\n";
    $portActivity = $db->pdo()->query("
        SELECT 
            port_num,
            COUNT(*) as count,
            COUNT(DISTINCT channel_id) as channels,
            MAX(processed_at) as latest
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        AND processed_at > strftime('%s', 'now', '-1 day')
        GROUP BY port_num
        ORDER BY count DESC
    ")->fetchAll();
    
    printf("%-4s %-25s %-15s %-8s %-8s %-12s\n", "Port", "Name", "Type", "Count", "Channels", "Latest");
    echo str_repeat("-", 75) . "\n";
    
    foreach ($portActivity as $port) {
        $details = $portDetails[$port['port_num']] ?? [
            'name' => "UNKNOWN_PORT_{$port['port_num']}", 
            'desc' => 'Unknown/Custom application',
            'type' => 'Unknown'
        ];
        
        $latest = $port['latest'] ? date('H:i:s', $port['latest']) : 'unknown';
        
        printf("%-4d %-25s %-15s %-8d %-8d %-12s\n",
            $port['port_num'],
            substr($details['name'], 0, 25),
            $details['type'],
            $port['count'],
            $port['channels'],
            $latest
        );
    }
    
    // Protocol insights
    echo "\n=== Protocol Insights ===\n";
    
    // Check JSON vs Binary distribution
    $format_dist = $db->pdo()->query("
        SELECT 
            CASE 
                WHEN topic LIKE '%/json/%' THEN 'JSON'
                WHEN topic LIKE '%/e/%' THEN 'Binary'
                ELSE 'Other'
            END as format,
            COUNT(*) as count
        FROM raw_messages 
        WHERE processed_at > strftime('%s', 'now', '-1 day')
        GROUP BY format
    ")->fetchAll();
    
    echo "Message Format Distribution (24h):\n";
    foreach ($format_dist as $fmt) {
        echo "  {$fmt['format']}: {$fmt['count']} messages\n";
    }
    
    // Check encryption status
    $encryption_dist = $db->pdo()->query("
        SELECT 
            is_encrypted,
            message_type,
            COUNT(*) as count
        FROM raw_messages 
        WHERE processed_at > strftime('%s', 'now', '-1 day')
        GROUP BY is_encrypted, message_type
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();
    
    echo "\nTop Message Types by Encryption (24h):\n";
    foreach ($encryption_dist as $enc) {
        $encrypted = $enc['is_encrypted'] ? 'Encrypted' : 'Plain';
        echo sprintf("  %-20s %-10s: %d\n", $enc['message_type'], $encrypted, $enc['count']);
    }
    
    // Show potential expansion opportunities
    $opportunities = $db->pdo()->query("
        SELECT 
            port_num,
            COUNT(*) as count
        FROM raw_messages 
        WHERE port_num IS NOT NULL
        AND port_num NOT IN (0, 1, 3, 4, 67, 70, 71, 73)  -- Currently handled
        AND processed_at > strftime('%s', 'now', '-1 day')
        GROUP BY port_num
        ORDER BY count DESC
        LIMIT 5
    ")->fetchAll();
    
    if (!empty($opportunities)) {
        echo "\n=== Expansion Opportunities ===\n";
        echo "Unhandled ports with significant traffic:\n";
        foreach ($opportunities as $opp) {
            $details = $portDetails[$opp['port_num']] ?? ['name' => "UNKNOWN_PORT_{$opp['port_num']}", 'desc' => 'Unknown application'];
            echo sprintf("  Port %d (%s): %d messages - %s\n",
                $opp['port_num'],
                $details['name'],
                $opp['count'],
                $details['desc']
            );
        }
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
