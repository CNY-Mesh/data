<?php
declare(strict_types=1);
namespace App\Web\Controllers;

use App\Support\Env;
use App\Web\Auth;

final class NodeManagementController extends BaseController
{
    public function handle(): void
    {
        // Check authentication
        $auth = new Auth();
        if (!$auth->isAuthenticated()) {
            header('Location: /?r=login&redirect=' . urlencode('/?r=node_management'));
            return;
        }

        $message = '';
        $error = '';

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ourNodes = trim($_POST['our_nodes'] ?? '');
            $nodeHistoryIds = trim($_POST['node_history_ids'] ?? '');

            try {
                // Validate the node IDs (basic validation)
                $this->validateNodeIds($ourNodes, 'OUR_NODES');
                $this->validateNodeIds($nodeHistoryIds, 'NODE_HISTORY_IDS');

                // Update the .env file
                $this->updateEnvFile($ourNodes, $nodeHistoryIds);
                $message = 'Node configuration updated successfully!';
            } catch (\Exception $e) {
                $error = 'Error updating configuration: ' . $e->getMessage();
            }
        }

        // Get current values
        $currentOurNodes = Env::get('OUR_NODES', '');
        $currentNodeHistoryIds = Env::get('NODE_HISTORY_IDS', '');

        // Get node details for display
        $ourNodesDetails = $this->getNodeDetails($currentOurNodes);
        $nodeHistoryDetails = $this->getNodeDetails($currentNodeHistoryIds);

        $this->render('node_management', compact(
            'currentOurNodes', 
            'currentNodeHistoryIds', 
            'ourNodesDetails', 
            'nodeHistoryDetails', 
            'message', 
            'error'
        ));
    }

    private function validateNodeIds(string $nodeIds, string $fieldName): void
    {
        if (empty($nodeIds)) {
            return; // Allow empty values
        }

        $nodes = array_map('trim', explode(',', $nodeIds));
        foreach ($nodes as $node) {
            if (empty($node)) {
                continue;
            }

            // Check if it's a valid hex ID or decimal ID
            if (!preg_match('/^[0-9a-fA-F]+$/', $node) && !preg_match('/^[0-9]+$/', $node)) {
                throw new \Exception("Invalid node ID format in $fieldName: $node");
            }

            // Reasonable length check
            if (strlen($node) > 12) {
                throw new \Exception("Node ID too long in $fieldName: $node");
            }
        }
    }

    private function updateEnvFile(string $ourNodes, string $nodeHistoryIds): void
    {
        $envFile = __DIR__ . '/../../../.env';
        
        if (!file_exists($envFile)) {
            throw new \Exception('.env file not found');
        }

        $content = file_get_contents($envFile);
        if ($content === false) {
            throw new \Exception('Could not read .env file');
        }

        // Update OUR_NODES
        if (preg_match('/^OUR_NODES=.*$/m', $content)) {
            $content = preg_replace('/^OUR_NODES=.*$/m', 'OUR_NODES="' . $ourNodes . '"', $content);
        } else {
            $content .= "\nOUR_NODES=\"$ourNodes\"\n";
        }

        // Update NODE_HISTORY_IDS
        if (preg_match('/^NODE_HISTORY_IDS=.*$/m', $content)) {
            $content = preg_replace('/^NODE_HISTORY_IDS=.*$/m', 'NODE_HISTORY_IDS="' . $nodeHistoryIds . '"', $content);
        } else {
            $content .= "\nNODE_HISTORY_IDS=\"$nodeHistoryIds\"\n";
        }

        if (file_put_contents($envFile, $content) === false) {
            throw new \Exception('Could not write to .env file');
        }
    }

    private function getNodeDetails(string $nodeIds): array
    {
        if (empty($nodeIds)) {
            return [];
        }

        $nodes = array_map('trim', explode(',', $nodeIds));
        $nodes = array_filter($nodes, function($node) {
            return !empty($node);
        });

        if (empty($nodes)) {
            return [];
        }

        $pdo = $this->db->pdo();
        $placeholders = str_repeat('?,', count($nodes) - 1) . '?';
        
        // Convert hex to decimal for database lookup
        $decimalNodes = [];
        foreach ($nodes as $node) {
            if (preg_match('/^[0-9a-fA-F]+$/', $node) && !preg_match('/^[0-9]+$/', $node)) {
                // Looks like hex, convert to decimal
                $decimalNodes[] = hexdec($node);
            } else {
                // Already decimal
                $decimalNodes[] = (int)$node;
            }
        }

        $stmt = $pdo->prepare("
            SELECT node_num, long_name, short_name, 
                   DATETIME(last_seen, 'unixepoch') as last_seen_time,
                   hardware
            FROM nodes 
            WHERE node_num IN ($placeholders)
            ORDER BY last_seen DESC
        ");
        $stmt->execute($decimalNodes);
        
        return $stmt->fetchAll();
    }
}
