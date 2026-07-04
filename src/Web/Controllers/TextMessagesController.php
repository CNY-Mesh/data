<?php
declare(strict_types=1);
namespace App\Web\Controllers;

final class TextMessagesController extends BaseController
{
    public function handle(): void
    {
        $limit = (int) ($_GET['limit'] ?? 100);
        $offset = (int) ($_GET['offset'] ?? 0);
        $filter_from = (int) ($_GET['filter_from'] ?? 0);
        $ajax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        $limit = min($limit, 1000); // Cap at 1000
        
        // Build the query with optional filtering
        $where_clause = '';
        $params = [];
        
        if ($filter_from > 0) {
            $where_clause = 'WHERE tm.node_from = :filter_from';
            $params[':filter_from'] = $filter_from;
        }
        
        // Get text messages with node information
        $sql = "
            SELECT tm.*, 
                   n_from.long_name as from_name, 
                   n_from.short_name as from_short,
                   n_to.long_name as to_name,
                   n_to.short_name as to_short,
                   tm.topic
            FROM text_messages tm
            LEFT JOIN nodes n_from ON tm.node_from = n_from.node_num
            LEFT JOIN nodes n_to ON tm.node_to = n_to.node_num
            {$where_clause}
            ORDER BY tm.rx_time DESC 
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) FROM text_messages tm {$where_clause}";
        $count_stmt = $this->db->pdo()->prepare($count_sql);
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();
        
        // Get filter node info if filtering
        $filter_node = null;
        if ($filter_from > 0) {
            $node_stmt = $this->db->pdo()->prepare('SELECT * FROM nodes WHERE node_num = :id');
            $node_stmt->execute([':id' => $filter_from]);
            $filter_node = $node_stmt->fetch();
        }
        
        // For AJAX requests, return JSON instead of HTML
        if ($ajax) {
            // Capture the rendered content
            ob_start();
            extract([
                'rows' => $rows,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'filter_from' => $filter_from,
                'filter_node' => $filter_node,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($total / $limit)
            ]);
            include __DIR__ . '/../Views/text_messages_content.php';
            $content = ob_get_clean();
            
            $this->json([
                'content' => $content,
                'total' => $total,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($total / $limit),
                'timestamp' => time()
            ]);
            return;
        }
        
        $this->render('text_messages', [
            'rows' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'filter_from' => $filter_from,
            'filter_node' => $filter_node,
            'current_page' => floor($offset / $limit) + 1,
            'total_pages' => ceil($total / $limit),
            'ajax' => $ajax
        ]);
    }
}
