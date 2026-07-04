<?php
declare(strict_types=1);

namespace App\Web\Controllers;

use App\Support\Env;

class SearchController extends BaseController
{
    public function handle(): void
    {
        // Get default search terms from environment
        $defaultSearchTermsStr = Env::get('DEFAULT_SEARCH_TERMS', 'CNYmesh,AK2X,NY/CNY');
        $defaultSearchTerms = array_map('trim', explode(',', $defaultSearchTermsStr));
        
        // Check if custom search terms were provided
        $customSearchTerms = $_POST['search_terms'] ?? $_GET['search_terms'] ?? '';
        
        // Get search type (default to 'nodes' for best performance)
        $searchType = $_POST['search_type'] ?? $_GET['search_type'] ?? 'nodes';
        
        if (!empty($customSearchTerms)) {
            // Use custom search terms from user input
            $searchTerms = array_map('trim', explode(',', $customSearchTerms));
            $searchTerms = array_filter($searchTerms); // Remove empty terms
        } else {
            // Use default search terms
            $searchTerms = $defaultSearchTerms;
        }
        
        $results = [];
        $isCustomSearch = !empty($customSearchTerms);
        
        // Only perform search if we have terms
        if (!empty($searchTerms)) {
            // Search only the selected table type
            switch ($searchType) {
                case 'nodes':
                    $nodeResults = $this->searchNodes($searchTerms);
                    if (!empty($nodeResults)) {
                        $results['nodes'] = $nodeResults;
                    }
                    break;
                    
                case 'text_messages':
                    $messageResults = $this->searchTextMessages($searchTerms);
                    if (!empty($messageResults)) {
                        $results['text_messages'] = $messageResults;
                    }
                    break;
                    
                case 'positions':
                    $positionResults = $this->searchPositions($searchTerms);
                    if (!empty($positionResults)) {
                        $results['positions'] = $positionResults;
                    }
                    break;
                    
                case 'telemetry':
                    $telemetryResults = $this->searchTelemetry($searchTerms);
                    if (!empty($telemetryResults)) {
                        $results['telemetry'] = $telemetryResults;
                    }
                    break;
                    
                case 'raw_messages':
                    $rawResults = $this->searchRawMessages($searchTerms);
                    if (!empty($rawResults)) {
                        $results['raw_messages'] = $rawResults;
                    }
                    break;
            }
        }
        
        $this->render('search', [
            'results' => $results,
            'searchTerms' => $searchTerms,
            'defaultSearchTerms' => $defaultSearchTerms,
            'customSearchTerms' => $customSearchTerms,
            'isCustomSearch' => $isCustomSearch,
            'searchType' => $searchType,
            'totalMatches' => array_sum(array_map('count', $results))
        ]);
    }
    
    private function searchNodes(array $terms): array
    {
        $searchConditions = [];
        $params = [];
        
        foreach ($terms as $term) {
            $searchConditions[] = "(long_name LIKE ? OR short_name LIKE ? OR hardware LIKE ? OR node_num LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%"; 
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        
        $whereClause = implode(' OR ', $searchConditions);
        
        // Build dynamic CASE statement for all terms
        $caseConditions = [];
        $caseParams = [];
        foreach ($terms as $term) {
            $termPattern = "%$term%";
            $caseConditions[] = "WHEN long_name LIKE ? THEN 'long_name'";
            $caseConditions[] = "WHEN short_name LIKE ? THEN 'short_name'";
            $caseConditions[] = "WHEN hardware LIKE ? THEN 'hardware'";
            $caseConditions[] = "WHEN node_num LIKE ? THEN 'node_num'";
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
        }
        $caseClause = implode(' ', $caseConditions);
        
        $stmt = $this->db->pdo()->prepare("
            SELECT node_num, long_name, short_name, hardware, last_seen, 
                   'nodes' as table_name,
                   CASE 
                       $caseClause
                       ELSE 'unknown'
                   END as match_field
            FROM nodes 
            WHERE $whereClause
            ORDER BY last_seen DESC
            LIMIT 300
        ");
        
        $allParams = array_merge($params, $caseParams);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }
    
    private function searchTextMessages(array $terms): array
    {
        $searchConditions = [];
        $params = [];
        
        foreach ($terms as $term) {
            $searchConditions[] = "(tm.message LIKE ? OR tm.node_from LIKE ? OR tm.node_to LIKE ? OR tm.topic LIKE ? OR n.long_name LIKE ? OR n.short_name LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        
        $whereClause = implode(' OR ', $searchConditions);
        
        // Build dynamic CASE statement for all terms
        $caseConditions = [];
        $caseParams = [];
        foreach ($terms as $term) {
            $termPattern = "%$term%";
            $caseConditions[] = "WHEN tm.message LIKE ? THEN 'message'";
            $caseConditions[] = "WHEN tm.topic LIKE ? THEN 'topic'";
            $caseConditions[] = "WHEN n.long_name LIKE ? THEN 'sender_name'";
            $caseConditions[] = "WHEN tm.node_from LIKE ? THEN 'node_from'";
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
        }
        $caseClause = implode(' ', $caseConditions);
        
        $stmt = $this->db->pdo()->prepare("
            SELECT tm.*, n.long_name, n.short_name,
                   'text_messages' as table_name,
                   CASE 
                       $caseClause
                       ELSE 'other'
                   END as match_field
            FROM text_messages tm
            LEFT JOIN nodes n ON tm.node_from = n.node_num
            WHERE $whereClause
            ORDER BY tm.rx_time DESC
            LIMIT 500
        ");
        
        $allParams = array_merge($params, $caseParams);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }
    
    private function searchPositions(array $terms): array
    {
        $searchConditions = [];
        $params = [];
        
        foreach ($terms as $term) {
            $searchConditions[] = "(p.topic LIKE ? OR p.node_num LIKE ? OR n.long_name LIKE ? OR n.short_name LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        
        $whereClause = implode(' OR ', $searchConditions);
        
        // Build dynamic CASE statement for all terms
        $caseConditions = [];
        $caseParams = [];
        foreach ($terms as $term) {
            $termPattern = "%$term%";
            $caseConditions[] = "WHEN p.topic LIKE ? THEN 'topic'";
            $caseConditions[] = "WHEN n.long_name LIKE ? THEN 'node_name'";
            $caseConditions[] = "WHEN p.node_num LIKE ? THEN 'node_num'";
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
        }
        $caseClause = implode(' ', $caseConditions);
        
        $stmt = $this->db->pdo()->prepare("
            SELECT p.*, n.long_name, n.short_name,
                   'positions' as table_name,
                   CASE 
                       $caseClause
                       ELSE 'other'
                   END as match_field
            FROM positions p
            LEFT JOIN nodes n ON p.node_num = n.node_num
            WHERE ($whereClause)
            AND (
                -- Include positions less than 3 hours old (10800 seconds)
                p.time > (strftime('%s', 'now') - 10800)
                OR
                -- Include older positions only if they have recent node info (last_seen within 3 hours)
                (p.time <= (strftime('%s', 'now') - 10800) AND n.last_seen > (strftime('%s', 'now') - 10800))
            )
            ORDER BY p.time DESC
            LIMIT 200
        ");
        
        $allParams = array_merge($params, $caseParams);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }
    
    private function searchTelemetry(array $terms): array
    {
        $searchConditions = [];
        $params = [];
        
        foreach ($terms as $term) {
            $searchConditions[] = "(t.topic LIKE ? OR t.node_num LIKE ? OR n.long_name LIKE ? OR n.short_name LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        
        $whereClause = implode(' OR ', $searchConditions);
        
        // Build dynamic CASE statement for all terms
        $caseConditions = [];
        $caseParams = [];
        foreach ($terms as $term) {
            $termPattern = "%$term%";
            $caseConditions[] = "WHEN t.topic LIKE ? THEN 'topic'";
            $caseConditions[] = "WHEN n.long_name LIKE ? THEN 'node_name'";
            $caseConditions[] = "WHEN t.node_num LIKE ? THEN 'node_num'";
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
        }
        $caseClause = implode(' ', $caseConditions);
        
        $stmt = $this->db->pdo()->prepare("
            SELECT t.*, n.long_name, n.short_name,
                   'telemetry' as table_name,
                   CASE 
                       $caseClause
                       ELSE 'other'
                   END as match_field
            FROM telemetry t
            LEFT JOIN nodes n ON t.node_num = n.node_num
            WHERE $whereClause
            ORDER BY t.updated_at DESC
            LIMIT 200
        ");
        
        $allParams = array_merge($params, $caseParams);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }
    
    private function searchRawMessages(array $terms): array
    {
        $searchConditions = [];
        $params = [];
        
        foreach ($terms as $term) {
            $searchConditions[] = "(topic LIKE ? OR channel_id LIKE ? OR gateway_id LIKE ? OR node_from LIKE ? OR node_to LIKE ? OR message_type LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        
        $whereClause = implode(' OR ', $searchConditions);
        
        // Build dynamic CASE statement for all terms
        $caseConditions = [];
        $caseParams = [];
        foreach ($terms as $term) {
            $termPattern = "%$term%";
            $caseConditions[] = "WHEN topic LIKE ? THEN 'topic'";
            $caseConditions[] = "WHEN channel_id LIKE ? THEN 'channel_id'";
            $caseConditions[] = "WHEN gateway_id LIKE ? THEN 'gateway_id'";
            $caseConditions[] = "WHEN node_from LIKE ? THEN 'node_from'";
            $caseConditions[] = "WHEN node_to LIKE ? THEN 'node_to'";
            $caseConditions[] = "WHEN message_type LIKE ? THEN 'message_type'";
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
            $caseParams[] = $termPattern;
        }
        $caseClause = implode(' ', $caseConditions);
        
        $stmt = $this->db->pdo()->prepare("
            SELECT *, 'raw_messages' as table_name,
                   CASE 
                       $caseClause
                       ELSE 'other'
                   END as match_field
            FROM raw_messages 
            WHERE $whereClause
            ORDER BY rx_time DESC
            LIMIT 100
        ");
        
        $allParams = array_merge($params, $caseParams);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }
}
