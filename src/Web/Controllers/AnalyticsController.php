<?php
declare(strict_types=1);
namespace App\Web\Controllers;

use App\Database;
use App\Support\Env;

final class AnalyticsController extends BaseController
{
    public function handle(): void
    {
        $this->index();
    }
    
    public function index(): void
    {
        try {
            $dsn = Env::get('DB_DSN') ?: 'sqlite:' . dirname(__DIR__, 2) . '/data/meshtastic.sqlite';
            $db = new Database($dsn);
            $pdo = $db->pdo();
            
            // Get overall statistics
            $stats = $this->getOverallStats($pdo);
            
            // Get port breakdown
            $portBreakdown = $this->getPortBreakdown($pdo);
            
            // Get channel breakdown
            $channelBreakdown = $this->getChannelBreakdown($pdo);
            
            // Get JSON message types
            $jsonTypes = $this->getJsonMessageTypes($pdo);
            
            // Get structured data counts
            $structuredData = $this->getStructuredDataCounts($pdo);
            
            // Get recent activity
            $recentActivity = $this->getRecentActivity($pdo);
            
            $this->render('analytics', [
                'stats' => $stats,
                'portBreakdown' => $portBreakdown,
                'channelBreakdown' => $channelBreakdown,
                'jsonTypes' => $jsonTypes,
                'structuredData' => $structuredData,
                'recentActivity' => $recentActivity
            ]);
            
        } catch (\Exception $e) {
            $this->render('analytics', [
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }
    
    private function getOverallStats(\PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM raw_messages");
            $rawCount = $stmt->fetch()['count'] ?? 0;
            
            $stmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT node_from) as unique_senders,
                    MIN(processed_at) as first_message,
                    MAX(processed_at) as last_message
                FROM raw_messages 
                WHERE node_from IS NOT NULL
            ");
            $stats = $stmt->fetch();
            
            return [
                'total_messages' => $rawCount,
                'unique_senders' => $stats['unique_senders'] ?? 0,
                'first_message' => $stats['first_message'] ?? null,
                'last_message' => $stats['last_message'] ?? null
            ];
        } catch (\Exception $e) {
            // raw_messages table doesn't exist yet
            return [
                'total_messages' => 0,
                'unique_senders' => 0,
                'first_message' => null,
                'last_message' => null
            ];
        }
    }
    
    private function getPortBreakdown(\PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("
                SELECT port_num, COUNT(*) as count, 
                       AVG(payload_length) as avg_payload_len,
                       SUM(CASE WHEN payload_length > 0 THEN 1 ELSE 0 END) as with_payload
                FROM raw_messages 
                WHERE is_json = 0 
                GROUP BY port_num 
                ORDER BY count DESC
                LIMIT 20
            ");
            
            $knownPorts = [
                0 => 'HEARTBEAT',
                1 => 'TEXT_MESSAGE_APP',
                3 => 'POSITION_APP',
                4 => 'NODEINFO_APP',
                32 => 'REPLY_APP',
                33 => 'IP_TUNNEL_APP',
                64 => 'SERIAL_APP',
                65 => 'STORE_FORWARD_APP',
                66 => 'RANGE_TEST_APP',
                67 => 'TELEMETRY_APP',
                70 => 'TRACEROUTE_APP',
                71 => 'NEIGHBORINFO_APP',
                72 => 'ATAK_PLUGIN_APP',
                73 => 'MAP_REPORT_APP',
                256 => 'PRIVATE_APP',
                257 => 'ATAK_FORWARDER_APP'
            ];
            
            $results = [];
            while ($row = $stmt->fetch()) {
                $port = $row['port_num'];
                $row['port_name'] = $knownPorts[$port] ?? 'UNKNOWN';
                $row['avg_payload_len'] = round($row['avg_payload_len'], 1);
                $results[] = $row;
            }
            
            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getChannelBreakdown(\PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("
                SELECT channel_id, COUNT(*) as count
                FROM raw_messages 
                WHERE channel_id IS NOT NULL AND channel_id != ''
                GROUP BY channel_id 
                ORDER BY count DESC 
                LIMIT 20
            ");
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getJsonMessageTypes(\PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("
                SELECT message_type, COUNT(*) as count
                FROM raw_messages 
                WHERE is_json = 1
                GROUP BY message_type 
                ORDER BY count DESC
            ");
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getStructuredDataCounts(\PDO $pdo): array
    {
        $tables = [
            'positions' => 'position data',
            'telemetry' => 'telemetry data', 
            'text_messages' => 'text messages',
            'nodes' => 'node info',
            'neighbors' => 'neighbor reports',
            'traceroutes' => 'traceroute data',
            'map_reports' => 'map reports'
        ];
        
        $results = [];
        foreach ($tables as $table => $description) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch()['count'];
                $results[] = [
                    'table' => $table,
                    'description' => $description,
                    'count' => $count
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'table' => $table,
                    'description' => $description,
                    'count' => 'N/A'
                ];
            }
        }
        
        return $results;
    }
    
    private function getRecentActivity(\PDO $pdo): array
    {
        try {
            $recentTime = time() - 600; // 10 minutes ago
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count, 
                       COUNT(DISTINCT node_from) as unique_nodes
                FROM raw_messages 
                WHERE processed_at > ?
            ");
            $stmt->execute([$recentTime]);
            
            return $stmt->fetch();
        } catch (\Exception $e) {
            return ['count' => 0, 'unique_nodes' => 0];
        }
    }
}
