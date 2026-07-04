<?php
/**
 * Database Schema Export Tool
 * Exports the current database schema as SQL CREATE statements
 */

// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

require __DIR__ . '/../bootstrap.php';
use App\Support\Env;

// Get database path from environment
$dbDsn = Env::get('DB_DSN', 'sqlite:/var/www/cny-mesh/data/data/meshtastic.sqlite');
$dbPath = str_replace('sqlite:', '', $dbDsn);

if (!file_exists($dbPath)) {
    die("Database file not found: $dbPath");
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all tables
    $tablesQuery = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
    $tables = $pdo->query($tablesQuery)->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all indexes
    $indexesQuery = "SELECT name, sql FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%' AND sql IS NOT NULL";
    $indexes = $pdo->query($indexesQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // Start building the schema
    $schema = "-- Database Schema Export\n";
    $schema .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $schema .= "-- Database file: $dbPath\n\n";
    
    $schema .= "PRAGMA journal_mode=WAL;\n\n";
    
    // Export table schemas
    foreach ($tables as $table) {
        $createTableQuery = "SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'";
        $createSql = $pdo->query($createTableQuery)->fetchColumn();
        
        if ($createSql) {
            $schema .= "$createSql;\n\n";
        }
    }
    
    // Export indexes
    if (!empty($indexes)) {
        $schema .= "-- Indexes\n";
        foreach ($indexes as $index) {
            $schema .= $index['sql'] . ";\n";
        }
        $schema .= "\n";
    }
    
    // Get table row counts for reference
    $schema .= "-- Table Statistics (as of export time)\n";
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $schema .= "-- Table '$table': $count rows\n";
        } catch (Exception $e) {
            $schema .= "-- Table '$table': Error counting rows - " . $e->getMessage() . "\n";
        }
    }
    
    // Handle download vs display
    $action = $_GET['action'] ?? 'display';
    
    if ($action === 'download') {
        $filename = 'meshtastic_schema_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($schema));
        echo $schema;
        exit;
    }
    
} catch (Exception $e) {
    $error = "Error accessing database: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Export - Meshtastic MQTT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .schema-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.4;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .table-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-database"></i> Database Schema Export Tool
                        </h3>
                        <small>Export current database schema as SQL</small>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php else: ?>
                            <div class="table-info p-3 rounded mb-3">
                                <h5 class="mb-2">
                                    <i class="fas fa-info-circle"></i> Database Information
                                </h5>
                                <ul class="mb-0">
                                    <li><strong>Database Path:</strong> <code><?= htmlspecialchars($dbPath) ?></code></li>
                                    <li><strong>File Size:</strong> <?= number_format(filesize($dbPath) / 1024 / 1024, 2) ?> MB</li>
                                    <li><strong>Tables Found:</strong> <?= count($tables) ?></li>
                                    <li><strong>Indexes Found:</strong> <?= count($indexes) ?></li>
                                    <li><strong>Export Time:</strong> <?= date('Y-m-d H:i:s') ?></li>
                                </ul>
                            </div>
                            
                            <div class="d-flex gap-2 mb-3">
                                <a href="?action=download" class="btn btn-success">
                                    <i class="fas fa-download"></i> Download Schema (.sql)
                                </a>
                                <button class="btn btn-outline-secondary" onclick="copySchema()">
                                    <i class="fas fa-copy"></i> Copy to Clipboard
                                </button>
                                <a href="?" class="btn btn-outline-primary">
                                    <i class="fas fa-refresh"></i> Refresh
                                </a>
                                <a href="/index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home"></i> Back to Dashboard
                                </a>
                            </div>
                            
                            <h5>Schema Preview:</h5>
                            <div class="schema-output p-3" id="schemaOutput"><?= htmlspecialchars($schema) ?></div>
                            
                            <div class="mt-3">
                                <h6>Usage Notes:</h6>
                                <ul class="small">
                                    <li>This exports the complete database schema including all tables and indexes</li>
                                    <li>The exported schema can be used to recreate the database structure</li>
                                    <li>Table row counts are included as comments for reference</li>
                                    <li>Save this schema file to your version control system for backup</li>
                                    <li>Use this before making schema changes to have a restore point</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copySchema() {
            const schemaText = document.getElementById('schemaOutput').textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(schemaText).then(function() {
                    showToast('Schema copied to clipboard!', 'success');
                }).catch(function(err) {
                    console.error('Could not copy text: ', err);
                    fallbackCopy(schemaText);
                });
            } else {
                fallbackCopy(schemaText);
            }
        }
        
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showToast('Schema copied to clipboard!', 'success');
            } catch (err) {
                showToast('Could not copy to clipboard', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>
