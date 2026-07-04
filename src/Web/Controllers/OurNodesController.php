<?php
declare(strict_types=1);

namespace App\Web\Controllers;

use App\Support\Env;

class OurNodesController extends BaseController
{
    public function handle(): void
    {
        $ajax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        
        // Get our nodes from environment (these are specific node IDs)
        $ourNodesStr = Env::get('OUR_NODES', '');
        $nodeIds = array_filter(array_map('trim', explode(',', $ourNodesStr)));
        
        // Get our nodes that match the node IDs
        $ourNodes = $this->getOurNodes($nodeIds);
        
        // Debug: Log detailed information about found nodes
        error_log("Found " . count($ourNodes) . " nodes from OUR_NODES list");
        $nodeNums = array_column($ourNodes, 'node_num');
        error_log("Node IDs found: " . implode(', ', $nodeNums));
        
        // Check for duplicates in the result set
        $uniqueNodeNums = array_unique($nodeNums);
        if (count($nodeNums) !== count($uniqueNodeNums)) {
            error_log("WARNING: Duplicate nodes detected in database result!");
            error_log("Total nodes: " . count($nodeNums) . ", Unique nodes: " . count($uniqueNodeNums));
        }
        
        // Enhance each node with additional data
        $enhancedNodes = [];
        error_log("Starting node enhancement for " . count($ourNodes) . " nodes");
        
        foreach ($ourNodes as $index => $node) {
            error_log("Processing node $index: ID=" . $node['node_num'] . ", Name=" . ($node['long_name'] ?: 'Unknown'));
            
            // Create a copy to avoid reference issues
            $enhancedNode = $node;
            
            // Get recent position data
            $enhancedNode['position'] = $this->getRecentPosition($enhancedNode['node_num']);
            
            // Debug: Log position results
            if ($enhancedNode['position']) {
                error_log("Node " . $enhancedNode['node_num'] . " has position: lat=" . $enhancedNode['position']['lat']);
            } else {
                error_log("Node " . $enhancedNode['node_num'] . " has NO position data");
            }
            
            // Get latest telemetry
            $enhancedNode['telemetry'] = $this->getLatestTelemetry($enhancedNode['node_num']);
            
            // Get recent public messages from this node
            $enhancedNode['recent_messages'] = $this->getRecentMessages($enhancedNode['node_num']);
            
            // Calculate time ago for last_seen
            $enhancedNode['last_seen_ago'] = $this->timeAgo($enhancedNode['last_seen']);
            
            $enhancedNodes[] = $enhancedNode;
            error_log("Added enhanced node $index to result array");
        }
        
        // Replace the original array with enhanced nodes
        $ourNodes = $enhancedNodes;
        
        // Final verification
        error_log("Final enhanced nodes count: " . count($ourNodes));
        $finalNodeNums = array_column($ourNodes, 'node_num');
        error_log("Final node IDs: " . implode(', ', $finalNodeNums));
        
        // For AJAX requests, return JSON instead of HTML
        if ($ajax) {
            $requestId = uniqid('ajax_');
            error_log("AJAX Request ID: $requestId - Processing our_nodes AJAX request");
            
            // Capture the rendered content
            ob_start();
            extract([
                'ourNodes' => $ourNodes,
                'nodeIds' => $nodeIds,
                'totalNodes' => count($ourNodes)
            ]);
            include __DIR__ . '/../Views/our_nodes_content.php';
            $content = ob_get_clean();
            
            error_log("AJAX Request ID: $requestId - Generated content length: " . strlen($content));
            
            $response = [
                'content' => $content,
                'nodeCount' => count($ourNodes),
                'nodeIds' => $nodeIds,
                'timestamp' => time(),
                'requestId' => $requestId,
                'debug_node_nums' => array_column($ourNodes, 'node_num'),
                'debug_node_names' => array_column($ourNodes, 'long_name')
            ];
            
            error_log("AJAX Request ID: $requestId - Sending response with " . count($ourNodes) . " nodes");
            $this->json($response);
            return;
        }
        
        $this->render('our_nodes', [
            'ourNodes' => $ourNodes,
            'nodeIds' => $nodeIds,
            'totalNodes' => count($ourNodes)
        ]);
    }
    
    private function getOurNodes(array $nodeIds): array
    {
        if (empty($nodeIds)) {
            return [];
        }
        
        // Convert hex node IDs to decimal for database lookup
        $decimalNodeIds = [];
        foreach ($nodeIds as $nodeId) {
            $nodeId = trim($nodeId);
            if (empty($nodeId)) continue;
            
            // Check if it's hex (contains letters) or already decimal
            if (preg_match('/^[0-9a-fA-F]+$/', $nodeId) && preg_match('/[a-fA-F]/', $nodeId)) {
                // It's hex, convert to decimal
                $decimal = hexdec($nodeId);
                $decimalNodeIds[] = $decimal;
                error_log("Converted hex '$nodeId' to decimal '$decimal'");
            } else {
                // It's already decimal
                $decimal = (int)$nodeId;
                $decimalNodeIds[] = $decimal;
                error_log("Using decimal '$nodeId' as '$decimal'");
            }
        }
        
        // Log the final decimal IDs and check for duplicates
        error_log("Final decimal node IDs: " . implode(', ', $decimalNodeIds));
        $uniqueDecimalIds = array_unique($decimalNodeIds);
        if (count($decimalNodeIds) !== count($uniqueDecimalIds)) {
            error_log("WARNING: Duplicate decimal node IDs detected after conversion!");
        }
        
        if (empty($decimalNodeIds)) {
            return [];
        }
        
        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($decimalNodeIds) - 1) . '?';
        
        $stmt = $this->db->pdo()->prepare("
            SELECT DISTINCT node_num, long_name, short_name, hardware, last_seen
            FROM nodes 
            WHERE node_num IN ($placeholders)
            ORDER BY last_seen DESC
        ");
        
        $stmt->execute($decimalNodeIds);
        return $stmt->fetchAll();
    }
    
    private function getRecentPosition($nodeNum): ?array
    {
        $stmt = $this->db->pdo()->prepare("
            SELECT p.* 
            FROM positions p 
            WHERE p.node_num = :n 
            AND p.lat IS NOT NULL 
            AND p.lon IS NOT NULL
            ORDER BY p.time DESC 
            LIMIT 1
        ");
        
        $stmt->execute([':n' => $nodeNum]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    private function getLatestTelemetry($nodeNum): ?array
    {
        $stmt = $this->db->pdo()->prepare("
            SELECT * 
            FROM telemetry 
            WHERE node_num = :n 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute([':n' => $nodeNum]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    private function getRecentMessages($nodeNum): array
    {
        $stmt = $this->db->pdo()->prepare("
            SELECT tm.*, 
                   n_to.long_name as to_name, 
                   n_to.short_name as to_short
            FROM text_messages tm
            LEFT JOIN nodes n_to ON tm.node_to = n_to.node_num
            WHERE tm.node_from = :n 
              AND tm.rx_time > (strftime('%s', 'now') - 86400) -- Last 24 hours
            ORDER BY tm.rx_time DESC 
            LIMIT 5
        ");
        
        $stmt->execute([':n' => $nodeNum]);
        return $stmt->fetchAll();
    }
}
