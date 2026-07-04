<?php
declare(strict_types=1);
namespace App\Web\Controllers;
final class NodesController extends BaseController
{
    public function handle(): void
    {
        $ajax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        
        $rows = $this->db->pdo()->query('
            SELECT n.*,
                   COUNT(DISTINCT p.topic) as position_topics,
                   COUNT(DISTINCT tm.topic) as message_topics,
                   COUNT(DISTINCT t.topic) as telemetry_topics,
                   MAX(COALESCE(p.time, tm.rx_time, t.updated_at)) as last_activity
            FROM nodes n
            LEFT JOIN positions p ON n.node_num = p.node_num AND p.topic IS NOT NULL
            LEFT JOIN text_messages tm ON n.node_num = tm.node_from AND tm.topic IS NOT NULL  
            LEFT JOIN telemetry t ON n.node_num = t.node_num AND t.topic IS NOT NULL
            GROUP BY n.node_num
            ORDER BY n.last_seen DESC
        ')->fetchAll();
        
        // For AJAX requests, return JSON instead of HTML
        if ($ajax) {
            // Capture the rendered content
            ob_start();
            extract(['rows' => $rows]);
            include __DIR__ . '/../Views/nodes_content.php';
            $content = ob_get_clean();
            
            $this->json([
                'content' => $content,
                'node_count' => count($rows),
                'timestamp' => time()
            ]);
            return;
        }
        
        $this->render('nodes', [
            'rows' => $rows
        ]);
    }
}
