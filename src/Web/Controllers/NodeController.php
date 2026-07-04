<?php
declare(strict_types=1);
namespace App\Web\Controllers;

use App\Support\Env;
use App\Web\Auth;

final class NodeController extends BaseController
{
    public function handle(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { header('Location: /?r=nodes'); return; }

        $pdo = $this->db->pdo();
        $node = $pdo->prepare('SELECT * FROM nodes WHERE node_num = :n'); $node->execute([':n'=>$id]); $node = $node->fetch();
        
        // Get position only if it's recent (< 3 hours) or node has recent activity
        $pos = $pdo->prepare('
            SELECT p.* 
            FROM positions p 
            LEFT JOIN nodes n ON p.node_num = n.node_num 
            WHERE p.node_num = :n 
            AND (
                -- Include positions less than 3 hours old (10800 seconds)
                p.time > (strftime(\'%s\', \'now\') - 10800)
                OR
                -- Include older positions only if they have recent node info (last_seen within 3 hours)
                (p.time <= (strftime(\'%s\', \'now\') - 10800) AND n.last_seen > (strftime(\'%s\', \'now\') - 10800))
            )
            ORDER BY p.time DESC 
            LIMIT 1
        '); 
        $pos->execute([':n'=>$id]); 
        $pos = $pos->fetch();
        
        $tele = $pdo->prepare('SELECT * FROM telemetry WHERE node_num = :n'); $tele->execute([':n'=>$id]); $tele = $tele->fetch();

        $neighbors = $pdo->prepare('
            SELECT n.*, 
                   nodes.long_name as neighbor_long_name, 
                   nodes.short_name as neighbor_short_name 
            FROM neighbors n
            LEFT JOIN nodes ON n.neighbor_node_num = nodes.node_num
            WHERE n.reporter_node_num = :n 
            ORDER BY n.heard_at DESC 
            LIMIT 200
        '); 
        $neighbors->execute([':n'=>$id]); 
        $neighbors = $neighbors->fetchAll();

        // Get text messages sent from this node
        $text_messages = $pdo->prepare('
            SELECT tm.*, 
                   n_to.long_name as to_name, 
                   n_to.short_name as to_short,
                   tm.topic
            FROM text_messages tm
            LEFT JOIN nodes n_to ON tm.node_to = n_to.node_num
            WHERE tm.node_from = :n 
            ORDER BY tm.rx_time DESC 
            LIMIT 50
        '); 
        $text_messages->execute([':n'=>$id]); 
        $text_messages = $text_messages->fetchAll();

        // Check if user is authenticated and get position history if available
        $auth = new Auth();
        $isAuthenticated = $auth->isAuthenticated();
        $positionHistory = [];
        $isTrackedNode = false;
        
        if ($isAuthenticated) {
            // Check if this node is in our tracked nodes list (for display purposes)
            // Combine OUR_NODES and NODE_HISTORY_IDS for complete tracking
            $ourNodes = Env::get('OUR_NODES', '');
            $historyNodes = Env::get('NODE_HISTORY_IDS', '');
            
            $allTrackedNodes = [];
            
            // Add OUR_NODES to the list
            if (!empty($ourNodes)) {
                $ourNodesArray = array_map('trim', explode(',', $ourNodes));
                $allTrackedNodes = array_merge($allTrackedNodes, $ourNodesArray);
            }
            
            // Add NODE_HISTORY_IDS to the list
            if (!empty($historyNodes)) {
                $historyNodesArray = array_map('trim', explode(',', $historyNodes));
                $allTrackedNodes = array_merge($allTrackedNodes, $historyNodesArray);
            }
            
            // Remove duplicates and empty values
            $allTrackedNodes = array_unique(array_filter($allTrackedNodes, function($node) {
                return !empty(trim($node));
            }));
            
            $nodeIdHex = dechex($id);
            
            if (in_array($nodeIdHex, $allTrackedNodes) || in_array((string)$id, $allTrackedNodes)) {
                $isTrackedNode = true;
            }
            
            // Get position history for ANY node if it exists (not just tracked ones)
            $stmt = $pdo->prepare("
                SELECT name FROM sqlite_master WHERE type='table' AND name='position_history'
            ");
            $stmt->execute();
            $historyTableExists = $stmt->fetch() !== false;
            
            if ($historyTableExists) {
                $stmt = $pdo->prepare("
                    WITH filtered_positions AS (
                        SELECT ph.*, 
                            DATETIME(ph.time, 'unixepoch') as position_time,
                            DATETIME(ph.recorded_at, 'unixepoch') as recorded_time,
                            LAG(ph.lat) OVER (ORDER BY ph.time) as prev_lat,
                            LAG(ph.lon) OVER (ORDER BY ph.time) as prev_lon,
                            LAG(ph.altitude) OVER (ORDER BY ph.time) as prev_alt
                        FROM position_history ph 
                        WHERE ph.node_num = ?
                        ORDER BY ph.time
                    )
                    SELECT * FROM filtered_positions
                    WHERE (
                        prev_lat IS NULL OR 
                        COALESCE(lat, 0) != COALESCE(prev_lat, 0) OR 
                        COALESCE(lon, 0) != COALESCE(prev_lon, 0) OR 
                        COALESCE(altitude, 0) != COALESCE(prev_alt, 0)
                    )
                    ORDER BY time DESC 
                    LIMIT 100
                ");
                $stmt->execute([$id]);
                $positionHistory = $stmt->fetchAll();
            }
        }

        $this->render('node', compact('node','pos','tele','neighbors','text_messages','isAuthenticated','isTrackedNode','positionHistory'));
    }
}
