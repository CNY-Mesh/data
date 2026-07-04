<?php
/**
 * Debug Tools Index
 * Automatically discovers and lists all PHP debugging tools in the public folder
 */

// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

// Get the current directory
$publicDir = __DIR__;
$baseUrl = '//' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

// Scan for all PHP files
$phpFiles = [];
$files = scandir($publicDir);

foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && $file !== 'debug_index.php') {
        $phpFiles[] = $file;
    }
}

sort($phpFiles);

// Try to extract description from each file's docblock or comments
function getFileDescription($filepath) {
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

// Get file categories based on naming patterns
function categorizeFile($filename) {
    if (strpos($filename, 'debug') !== false) return 'Debug Tools';
    if (strpos($filename, 'test') !== false) return 'Test Scripts';
    if (strpos($filename, 'decrypt') !== false) return 'Decryption Tools';
    if (strpos($filename, 'analysis') !== false) return 'Analysis Tools';
    if (in_array($filename, ['index.php', 'restart_worker.php', 'cleanup.php'])) return 'System Tools';
    return 'Other Tools';
}

// Group files by category
$categories = [];
foreach ($phpFiles as $file) {
    $category = categorizeFile($file);
    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }
    $categories[$category][] = [
        'filename' => $file,
        'description' => getFileDescription($publicDir . '/' . $file),
        'url' => $baseUrl . '/' . $file,
        'size' => filesize($publicDir . '/' . $file),
        'modified' => filemtime($publicDir . '/' . $file)
    ];
}

// Sort categories
ksort($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Tools Index - Meshtastic MQTT Debug Suite</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .header .subtitle {
            color: #666;
            font-size: 16px;
        }
        .category {
            background: white;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .category-header {
            background: #007acc;
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 18px;
        }
        .file-list {
            padding: 0;
        }
        .file-item {
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.2s;
        }
        .file-item:hover {
            background: #f8f9fa;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-main {
            flex: 1;
        }
        .file-name {
            font-weight: 600;
            color: #007acc;
            text-decoration: none;
            font-size: 16px;
            display: block;
            margin-bottom: 5px;
        }
        .file-name:hover {
            text-decoration: underline;
        }
        .file-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        .file-meta {
            text-align: right;
            color: #999;
            font-size: 12px;
            min-width: 120px;
            margin-left: 20px;
        }
        .stats {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #007acc;
            display: block;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .actions {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007acc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .btn:hover {
            background: #005fa3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 Debug Tools Index</h1>
        <div class="subtitle">Meshtastic MQTT Debug Suite - All available debugging and analysis tools</div>
    </div>

    <div class="stats">
        <div class="stat-item">
            <span class="stat-number"><?= count($phpFiles) ?></span>
            <div class="stat-label">Total Tools</div>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= count($categories) ?></span>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= array_sum(array_map('count', $categories)) ?></span>
            <div class="stat-label">Debug Scripts</div>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= date('Y-m-d H:i:s') ?></span>
            <div class="stat-label">Last Updated</div>
        </div>
    </div>

    <?php foreach ($categories as $categoryName => $files): ?>
        <div class="category">
            <div class="category-header">
                <?= htmlspecialchars($categoryName) ?> (<?= count($files) ?> tools)
            </div>
            <div class="file-list">
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-main">
                            <a href="<?= htmlspecialchars($file['url']) ?>" class="file-name" target="_blank">
                                📄 <?= htmlspecialchars($file['filename']) ?>
                            </a>
                            <div class="file-description">
                                <?= htmlspecialchars($file['description']) ?>
                            </div>
                        </div>
                        <div class="file-meta">
                            <?= number_format($file['size'] / 1024, 1) ?> KB<br>
                            <?= date('M j, H:i', $file['modified']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="actions">
        <h3>Quick Actions</h3>
        <a href="debug_logs.php" class="btn">📊 View Debug Logs</a>
        <a href="restart_worker.php" class="btn">🔄 Restart Worker</a>
        <a href="cleanup.php" class="btn btn-secondary">🧹 Cleanup Data</a>
        <a href="index.php" class="btn btn-secondary">🏠 Main Dashboard</a>
        <a href="?" class="btn btn-secondary">🔄 Refresh Index</a>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-size: 14px; color: #666;">
        <strong>📝 Usage Notes:</strong>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Click any tool name to open it in a new tab</li>
            <li>Tools are automatically categorized based on their functionality</li>
            <li>File sizes and modification times help identify recently updated tools</li>
            <li>This index refreshes automatically and discovers new tools</li>
        </ul>
    </div>
</body>
</html>
