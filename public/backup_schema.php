<?php
/**
 * Database Schema Backup Tool
 * Exports current production schema and saves it to the schema directory
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
    
    // Build the schema
    $schema = "-- Production Database Schema\n";
    $schema .= "-- Auto-generated from production database\n";
    $schema .= "-- Last updated: " . date('Y-m-d H:i:s') . "\n";
    $schema .= "-- Source: $dbPath\n\n";
    
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
        foreach ($indexes as $index) {
            $schema .= $index['sql'] . ";\n";
        }
        $schema .= "\n";
    }
    
    // Save to schema directory
    $schemaDir = dirname(__DIR__) . '/schema';
    $currentSchemaFile = $schemaDir . '/sqlite.sql';
    $backupSchemaFile = $schemaDir . '/sqlite_production_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    $action = $_GET['action'] ?? 'display';
    $messages = [];
    
    if ($action === 'save') {
        // Create backup of current schema file
        if (file_exists($currentSchemaFile)) {
            copy($currentSchemaFile, $backupSchemaFile);
            $messages[] = [
                'type' => 'info',
                'message' => "Backed up existing schema to: " . basename($backupSchemaFile)
            ];
        }
        
        // Save new schema
        if (file_put_contents($currentSchemaFile, $schema) !== false) {
            $messages[] = [
                'type' => 'success',
                'message' => "Schema successfully saved to: schema/sqlite.sql"
            ];
        } else {
            $messages[] = [
                'type' => 'danger',
                'message' => "Failed to save schema file"
            ];
        }
    }
    
    // Get table statistics
    $tableStats = [];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $tableStats[$table] = $count;
        } catch (Exception $e) {
            $tableStats[$table] = 'Error: ' . $e->getMessage();
        }
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
    <title>Schema Backup Tool - Meshtastic MQTT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-save"></i> Database Schema Backup Tool
                        </h3>
                        <small>Export production schema and save to codebase</small>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php else: ?>
                            
                            <?php foreach ($messages as $msg): ?>
                                <div class="alert alert-<?= $msg['type'] ?>">
                                    <i class="fas fa-<?= $msg['type'] === 'success' ? 'check-circle' : ($msg['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                                    <?= htmlspecialchars($msg['message']) ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-database"></i> Database Info
                                            </h5>
                                            <ul class="mb-0">
                                                <li><strong>Source:</strong> <code><?= htmlspecialchars(basename($dbPath)) ?></code></li>
                                                <li><strong>Size:</strong> <?= number_format(filesize($dbPath) / 1024 / 1024, 2) ?> MB</li>
                                                <li><strong>Tables:</strong> <?= count($tables) ?></li>
                                                <li><strong>Indexes:</strong> <?= count($indexes) ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-table"></i> Table Row Counts
                                            </h5>
                                            <ul class="mb-0 small">
                                                <?php foreach ($tableStats as $table => $count): ?>
                                                    <li><strong><?= htmlspecialchars($table) ?>:</strong> <?= is_numeric($count) ? number_format($count) : $count ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mb-3">
                                <a href="?action=save" class="btn btn-success" 
                                   onclick="return confirm('This will overwrite the current schema file and create a backup. Continue?')">
                                    <i class="fas fa-save"></i> Save Schema to Codebase
                                </a>
                                <a href="export_schema.php?action=download" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Download Schema File
                                </a>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-refresh"></i> Refresh
                                </a>
                                <a href="/index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home"></i> Back to Dashboard
                                </a>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> What this tool does:</h6>
                                <ul class="mb-0">
                                    <li><strong>Exports current production schema</strong> from the live database</li>
                                    <li><strong>Saves to <code>schema/sqlite.sql</code></strong> in your codebase</li>
                                    <li><strong>Creates backup</strong> of existing schema file before overwriting</li>
                                    <li><strong>Includes all tables and indexes</strong> from the production database</li>
                                    <li><strong>Updates version control</strong> with current production schema</li>
                                </ul>
                            </div>
                            
                            <?php if (!empty($tables)): ?>
                                <h5>Tables in Production Database:</h5>
                                <div class="row">
                                    <?php foreach (array_chunk($tables, ceil(count($tables)/3)) as $tableGroup): ?>
                                        <div class="col-md-4">
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($tableGroup as $table): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <code><?= htmlspecialchars($table) ?></code>
                                                        <span class="badge bg-secondary rounded-pill">
                                                            <?= is_numeric($tableStats[$table]) ? number_format($tableStats[$table]) : 'Error' ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
