<?php
declare(strict_types=1);
namespace App\Web\Controllers;

final class ToolsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        // Database not needed for this controller - it just scans files
    }
    
    public function handle(): void
    {
        // Get all available tools from the public directory
        $tools = $this->discoverTools();
        
        // Define featured tools (main application features)
        $featuredTools = $this->getFeaturedTools();
        
        $this->render('tools', [
            'title' => 'Debug Tools',
            'tools' => $tools,
            'stats' => $this->calculateStats($tools),
            'featuredTools' => $featuredTools
        ]);
    }
    
    private function discoverTools(): array
    {
        $publicDir = dirname(__DIR__, 3) . '/public';
        $baseUrl = '//' . $_SERVER['HTTP_HOST'] . '';
        
        // Scan for all PHP files except index.php
        $phpFiles = [];
        $files = scandir($publicDir);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && 
                $file !== 'index.php' && 
                $file !== 'debug_index.php') {
                $phpFiles[] = $file;
            }
        }
        
        sort($phpFiles);
        
        // Group files by category
        $categories = [];
        foreach ($phpFiles as $file) {
            $category = $this->categorizeFile($file);
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = [
                'filename' => $file,
                'description' => $this->getFileDescription($publicDir . '/' . $file),
                'url' => $baseUrl . '/' . $file,
                'size' => filesize($publicDir . '/' . $file),
                'modified' => filemtime($publicDir . '/' . $file)
            ];
        }
        
        // Sort categories
        ksort($categories);
        
        return $categories;
    }
    
    private function getFeaturedTools(): array
    {
        return [
            [
                'name' => 'Search',
                'route' => 'search',
                'description' => 'Search through all collected data including nodes, messages, and telemetry',
                'icon' => 'fas fa-search',
                'color' => 'primary'
            ],
            [
                'name' => 'Node Management',
                'route' => 'node_management',
                'description' => 'Configure which nodes to track for position history and mesh monitoring',
                'icon' => 'fas fa-broadcast-tower',
                'color' => 'primary'
            ],
            [
                'name' => 'MQTT Manager',
                'route' => 'mqtt_manager',
                'description' => 'Monitor and manage the MQTT worker process and connection status',
                'icon' => 'fas fa-network-wired',
                'color' => 'success'
            ],
            [
                'name' => 'Analytics',
                'route' => 'analytics',
                'description' => 'View comprehensive analytics and statistics about the mesh network',
                'icon' => 'fas fa-chart-bar',
                'color' => 'info'
            ],
            [
                'name' => 'Raw Data',
                'route' => 'rawdata',
                'description' => 'Access raw MQTT messages, decode errors, and low-level data',
                'icon' => 'fas fa-database',
                'color' => 'warning'
            ],
            [
                'name' => 'Password Hash',
                'route' => 'password_hash',
                'description' => 'Generate secure password hashes for user authentication',
                'icon' => 'fas fa-key',
                'color' => 'secondary'
            ],
            [
                'name' => 'Database Cleanup',
                'route' => 'cleanup',
                'description' => 'Clean up old raw messages and optimize database storage',
                'icon' => 'fas fa-broom',
                'color' => 'danger'
            ]
        ];
    }
    
    private function getFileDescription(string $filepath): string
    {
        if (!file_exists($filepath)) {
            return 'File not found';
        }
        
        $content = file_get_contents($filepath);
        
        // Look for description in various formats
        $patterns = [
            '/\/\*\*\s*\n\s*\*\s*(.+?)\s*\n/',           // /** * Description */
            '/\/\*\s*(.+?)\s*\*\//',                      // /* Description */
            '/<title>(.+?)<\/title>/i',                   // <title>Description</title>
            '/echo\s*["\']<h1>(.+?)<\/h1>["\'];/i',      // echo '<h1>Description</h1>';
            '/echo\s*["\']<h2>(.+?)<\/h2>["\'];/i',      // echo '<h2>Description</h2>';
            '/<h1[^>]*>(.+?)<\/h1>/i',                    // <h1>Description</h1>
            '/<h2[^>]*>(.+?)<\/h2>/i',                    // <h2>Description</h2>
            '/\/\/\s*(.+)/m',                             // // Single line comment
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $desc = trim($matches[1]);
                // Clean up HTML entities and extra whitespace
                $desc = html_entity_decode(strip_tags($desc));
                $desc = preg_replace('/\s+/', ' ', $desc);
                if (strlen($desc) > 3 && !preg_match('/^\s*<\?php/', $desc)) {
                    return $desc;
                }
            }
        }
        
        return 'No description available';
    }
    
    private function categorizeFile(string $filename): string
    {
        if (strpos($filename, 'debug') !== false) return 'Debug Tools';
        if (strpos($filename, 'test') !== false) return 'Test Scripts';
        if (strpos($filename, 'decrypt') !== false) return 'Decryption Tools';
        if (strpos($filename, 'analysis') !== false) return 'Analysis Tools';
        if (strpos($filename, 'topic') !== false) return 'Topic Analysis';
        if (in_array($filename, ['api_status.php', 'cleanup.php', 'check_database_schema.php'])) return 'System Tools';
        return 'Other Tools';
    }
    
    private function calculateStats(array $tools): array
    {
        $totalFiles = 0;
        $categories = count($tools);
        
        foreach ($tools as $category) {
            $totalFiles += count($category);
        }
        
        return [
            'total_tools' => $totalFiles,
            'categories' => $categories,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}
