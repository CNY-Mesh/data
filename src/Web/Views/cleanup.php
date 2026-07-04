<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h2 class="mb-0">
                        <i class="fas fa-broom me-2"></i>
                        Database Cleanup
                    </h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Caution:</strong> These actions permanently delete data from the database. 
                        Use with care and ensure you have backups if needed.
                    </div>
                    
                    <?php if (!empty($result)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($result) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Database Statistics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Database Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-database me-2"></i>Raw Messages:</td>
                                                <td class="text-end"><strong><?= number_format($stats['raw_messages'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-network-wired me-2"></i>Nodes:</td>
                                                <td class="text-end"><strong><?= number_format($stats['nodes'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-map-marker-alt me-2"></i>Positions:</td>
                                                <td class="text-end"><strong><?= number_format($stats['positions'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-chart-line me-2"></i>Telemetry:</td>
                                                <td class="text-end"><strong><?= number_format($stats['telemetry'] ?? 0) ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-users me-2"></i>Neighbors:</td>
                                                <td class="text-end"><strong><?= number_format($stats['neighbors'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-comments me-2"></i>Text Messages:</td>
                                                <td class="text-end"><strong><?= number_format($stats['text_messages'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-hdd me-2"></i>Database Size:</td>
                                                <td class="text-end"><strong><?= formatBytes($stats['db_size'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-file me-2"></i>WAL Size:</td>
                                                <td class="text-end"><strong><?= formatBytes($stats['db_wal_size'] ?? 0) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-layer-group me-2"></i>Total DB Footprint:</td>
                                                <td class="text-end"><strong><?= formatBytes($stats['db_total_size'] ?? (($stats['db_size'] ?? 0) + ($stats['db_wal_size'] ?? 0) + ($stats['db_shm_size'] ?? 0))) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-file-alt me-2"></i>MQTT Worker Log Size:</td>
                                                <td class="text-end"><strong><?= formatBytes($stats['log_size'] ?? 0) ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cleanup Actions -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clock me-2"></i>
                                        Maintenance Cleanup
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Clean up old data and garbage messages that accumulate during normal operation.</p>
                                    <form method="POST" action="/?r=cleanup" class="mb-2">
                                        <input type="hidden" name="action" value="clear_old_raw_messages">
                                        <button type="submit" class="btn btn-warning btn-sm w-100" 
                                                onclick="return confirm('Clear raw messages older than 24 hours?')">
                                            <i class="fas fa-clock me-2"></i>
                                            Clear Old Raw Messages
                                        </button>
                                    </form>
                                    <form method="POST" action="/?r=cleanup" class="mb-2">
                                        <input type="hidden" name="action" value="clear_garbage_messages">
                                        <button type="submit" class="btn btn-warning btn-sm w-100" 
                                                onclick="return confirm('Clear garbage/test messages?')">
                                            <i class="fas fa-trash me-2"></i>
                                            Clear Garbage Messages
                                        </button>
                                    </form>
                                    <form method="POST" action="/?r=cleanup" class="mb-2">
                                        <input type="hidden" name="action" value="compact_database">
                                        <button type="submit" class="btn btn-warning btn-sm w-100" 
                                                onclick="return confirm('Run WAL checkpoint and VACUUM to reclaim disk space?')">
                                            <i class="fas fa-compress-alt me-2"></i>
                                            Compact Database
                                        </button>
                                    </form>
                                    <form method="POST" action="/?r=cleanup">
                                        <input type="hidden" name="action" value="clear_debug_log">
                                        <button type="submit" class="btn btn-warning btn-sm w-100" 
                                                onclick="return confirm('Clear the MQTT worker log file?')">
                                            <i class="fas fa-file-alt me-2"></i>
                                            Clear MQTT Worker Log
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        Individual Tables
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Clear specific data types selectively.</p>
                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <form method="POST" action="/?r=cleanup">
                                                <input type="hidden" name="action" value="clear_raw_messages">
                                                <button type="submit" class="btn btn-info btn-sm w-100" 
                                                        onclick="return confirm('Clear ALL raw messages?')">
                                                    <i class="fas fa-database me-1"></i>
                                                    Raw Messages
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <form method="POST" action="/?r=cleanup">
                                                <input type="hidden" name="action" value="clear_positions">
                                                <button type="submit" class="btn btn-info btn-sm w-100" 
                                                        onclick="return confirm('Clear all position data?')">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    Positions
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <form method="POST" action="/?r=cleanup">
                                                <input type="hidden" name="action" value="clear_telemetry">
                                                <button type="submit" class="btn btn-info btn-sm w-100" 
                                                        onclick="return confirm('Clear all telemetry data?')">
                                                    <i class="fas fa-chart-line me-1"></i>
                                                    Telemetry
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <form method="POST" action="/?r=cleanup">
                                                <input type="hidden" name="action" value="clear_text_messages">
                                                <button type="submit" class="btn btn-info btn-sm w-100" 
                                                        onclick="return confirm('Clear all text messages?')">
                                                    <i class="fas fa-comments me-1"></i>
                                                    Messages
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <form method="POST" action="/?r=cleanup">
                                                <input type="hidden" name="action" value="clear_neighbors">
                                                <button type="submit" class="btn btn-info btn-sm w-100" 
                                                        onclick="return confirm('Clear all neighbor data?')">
                                                    <i class="fas fa-users me-1"></i>
                                                    Neighbors
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <form method="POST" action="/?r=cleanup">
                                                <input type="hidden" name="action" value="clear_nodes">
                                                <button type="submit" class="btn btn-info btn-sm w-100" 
                                                        onclick="return confirm('Clear all node data? This will also clear related data.')">
                                                    <i class="fas fa-network-wired me-1"></i>
                                                    Nodes
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dangerous Actions -->
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Dangerous Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="card-text text-danger">
                                <strong>Warning:</strong> These actions will permanently delete large amounts of data and logs.
                            </p>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <form method="POST" action="/?r=cleanup">
                                        <input type="hidden" name="action" value="clear_all_data">
                                        <button type="submit" class="btn btn-danger w-100" 
                                                onclick="return confirm('Clear ALL database data? This cannot be undone!')">
                                            <i class="fas fa-trash-alt me-2"></i>
                                            Clear All Database Data
                                        </button>
                                    </form>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <form method="POST" action="/?r=cleanup">
                                        <input type="hidden" name="action" value="clear_everything">
                                        <button type="submit" class="btn btn-danger w-100" 
                                                onclick="return confirm('Clear EVERYTHING? This will delete all data and MQTT worker logs!')">
                                            <i class="fas fa-bomb me-2"></i>
                                            Clear Everything
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh statistics every 30 seconds
    setInterval(function() {
        if (!document.querySelector('form[method="POST"]').closest('.alert')) {
            location.reload();
        }
    }, 30000);
});
</script>
