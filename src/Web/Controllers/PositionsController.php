<?php

namespace App\Web\Controllers;

use App\Database;

class PositionsController extends BaseController
{
    public function handle(): void
    {
        $this->index();
    }
    
    public function index()
    {
        $filters = $this->getFilters();
        $ajax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        
        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->exportCsv($filters);
            return;
        }
        
        $positions = $this->getPositions($filters);
        $stats = $this->getPositionStats($filters);
        
        // Handle AJAX requests
        if ($ajax) {
            ob_start();
            extract([
                'positions' => $positions,
                'stats' => $stats,
                'filters' => $filters
            ]);
            include __DIR__ . '/../Views/positions_content.php';
            $content = ob_get_clean();
            
            $this->json([
                'content' => $content,
                'positionCount' => count($positions),
                'stats' => $stats,
                'filters' => $filters,
                'timestamp' => time()
            ]);
            return;
        }
        
        return $this->render('positions', [
            'positions' => $positions,
            'stats' => $stats,
            'filters' => $filters,
            'title' => 'Position Data'
        ]);
    }
    
    private function getFilters(): array
    {
        return [
            'node_num' => $_GET['node_num'] ?? '',
            'lat_min' => $_GET['lat_min'] ?? '',
            'lat_max' => $_GET['lat_max'] ?? '',
            'lon_min' => $_GET['lon_min'] ?? '',
            'lon_max' => $_GET['lon_max'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'has_altitude' => $_GET['has_altitude'] ?? '',
            'limit' => min(($_GET['limit'] ?? 100), 1000) // Max 1000 records
        ];
    }
    
    private function getPositions(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['node_num'])) {
            $where[] = 'p.node_id = ?';
            $params[] = $filters['node_num'];
        }
        
        if (!empty($filters['lat_min'])) {
            $where[] = 'p.lat >= ?';
            $params[] = (float)$filters['lat_min'];
        }
        
        if (!empty($filters['lat_max'])) {
            $where[] = 'p.lat <= ?';
            $params[] = (float)$filters['lat_max'];
        }
        
        if (!empty($filters['lon_min'])) {
            $where[] = 'p.lon >= ?';
            $params[] = (float)$filters['lon_min'];
        }
        
        if (!empty($filters['lon_max'])) {
            $where[] = 'p.lon <= ?';
            $params[] = (float)$filters['lon_max'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'p.created_at >= ?';
            $params[] = strtotime($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'p.created_at <= ?';
            $params[] = strtotime($filters['date_to'] . ' 23:59:59');
        }
        
        if ($filters['has_altitude'] === 'yes') {
            $where[] = 'p.altitude IS NOT NULL AND p.altitude != 0';
        } elseif ($filters['has_altitude'] === 'no') {
            $where[] = '(p.altitude IS NULL OR p.altitude = 0)';
        }
        
        if (!empty($filters['min_accuracy'])) {
            $where[] = 'p.precision_bits >= ?';
            $params[] = (int)$filters['min_accuracy'];
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = (int)$filters['limit'];
        
        $sql = "
            SELECT 
                p.*,
                n.long_name,
                n.short_name,
                n.hardware,
                p.topic,
                CASE 
                    WHEN n.long_name IS NOT NULL THEN 1 
                    ELSE 0 
                END as is_known_node,
                ROUND(p.lat, 6) as lat_rounded,
                ROUND(p.lon, 6) as lon_rounded,
                CASE 
                    WHEN p.altitude > 0 THEN ROUND(p.altitude, 1)
                    ELSE NULL
                END as altitude_m
            FROM positions p
            LEFT JOIN nodes n ON p.node_num = n.node_num
            WHERE {$whereClause}
            AND (
                -- Include positions less than 3 hours old (10800 seconds)
                p.time > (strftime('%s', 'now') - 10800)
                OR
                -- Include older positions only if they have recent node info (last_seen within 3 hours)
                (p.time <= (strftime('%s', 'now') - 10800) AND n.last_seen > (strftime('%s', 'now') - 10800))
            )
            ORDER BY p.time DESC
            LIMIT {$limit}
        ";
        
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getPositionStats(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        
        // Apply same filters for stats
        if (!empty($filters['node_num'])) {
            $where[] = 'p.node_id = ?';
            $params[] = $filters['node_num'];
        }
        
        if (!empty($filters['lat_min'])) {
            $where[] = 'p.lat >= ?';
            $params[] = (float)$filters['lat_min'];
        }
        
        if (!empty($filters['lat_max'])) {
            $where[] = 'p.lat <= ?';
            $params[] = (float)$filters['lat_max'];
        }
        
        if (!empty($filters['lon_min'])) {
            $where[] = 'p.lon >= ?';
            $params[] = (float)$filters['lon_min'];
        }
        
        if (!empty($filters['lon_max'])) {
            $where[] = 'p.lon <= ?';
            $params[] = (float)$filters['lon_max'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'p.created_at >= ?';
            $params[] = strtotime($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'p.created_at <= ?';
            $params[] = strtotime($filters['date_to'] . ' 23:59:59');
        }
        
        if ($filters['has_altitude'] === 'yes') {
            $where[] = 'p.altitude IS NOT NULL AND p.altitude != 0';
        } elseif ($filters['has_altitude'] === 'no') {
            $where[] = '(p.altitude IS NULL OR p.altitude = 0)';
        }
        
        if (!empty($filters['min_accuracy'])) {
            $where[] = 'p.precision_bits >= ?';
            $params[] = (int)$filters['min_accuracy'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT 
                COUNT(*) as total_positions,
                COUNT(DISTINCT p.node_num) as unique_nodes,
                COUNT(CASE WHEN n.long_name IS NOT NULL THEN 1 END) as known_nodes,
                COUNT(CASE WHEN p.altitude > 0 THEN 1 END) as with_altitude,
                MIN(p.lat) as min_lat,
                MAX(p.lat) as max_lat,
                MIN(p.lon) as min_lon,
                MAX(p.lon) as max_lon,
                MIN(p.time) as earliest,
                MAX(p.time) as latest
            FROM positions p
            LEFT JOIN nodes n ON p.node_num = n.node_num
            WHERE {$whereClause}
            AND (
                -- Include positions less than 3 hours old (10800 seconds)
                p.time > (strftime('%s', 'now') - 10800)
                OR
                -- Include older positions only if they have recent node info (last_seen within 3 hours)
                (p.time <= (strftime('%s', 'now') - 10800) AND n.last_seen > (strftime('%s', 'now') - 10800))
            )
        ";
        
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function exportCsv(array $filters): void
    {
        $positions = $this->getPositions($filters);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="positions_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Node Number',
            'Long Name',
            'Short Name',
            'Hardware',
            'Latitude',
            'Longitude',
            'Altitude (m)',
            'RX RSSI (dBm)',
            'RX SNR (dB)',
            'Timestamp',
            'Date/Time',
            'Is Known Node'
        ]);
        
        // CSV data
        foreach ($positions as $pos) {
            fputcsv($output, [
                $pos['node_num'],
                $pos['long_name'] ?: '',
                $pos['short_name'] ?: '',
                $pos['hardware'] ?: '',
                $pos['lat'],
                $pos['lon'],
                $pos['altitude'] > 0 ? $pos['altitude'] : '',
                $pos['rx_rssi'] ?: '',
                $pos['rx_snr'] ?: '',
                $pos['time'],
                date('Y-m-d H:i:s', $pos['time']),
                $pos['is_known_node'] ? 'Yes' : 'No'
            ]);
        }
        
        fclose($output);
        exit;
    }
}
