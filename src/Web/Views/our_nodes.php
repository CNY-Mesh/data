<?php
/**
 * Our Nodes View
 * Displays nodes that match our criteria (default search terms)
 */
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Our Nodes</h2>
                    <p class="text-muted mb-0">
                        Community nodes that have been active in the last 7 days
                    </p>
                </div>
                <div class="text-end">
                    <!-- Auto-refresh controls -->
                    <div class="d-flex align-items-center justify-content-end mb-2">
                        <span class="badge bg-secondary me-2" id="refreshCountdown">30</span>
                        <button class="btn btn-sm btn-outline-primary me-2" id="refreshNow" title="Refresh now">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="toggleAutoRefresh" title="Toggle auto-refresh">
                            <i class="fas fa-pause"></i>
                        </button>
                        <div class="spinner-border spinner-border-sm ms-2 d-none" id="loadingSpinner" role="status"></div>
                    </div>
                    <div class="small text-muted" id="nodeCount">
                        <?= count($ourNodes) ?> nodes found
                    </div>
                </div>
            </div>

            <!-- Refreshable content container -->
            <div id="refreshableContent">
                <?php include 'our_nodes_content.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize auto-refresh for OurNodes page
document.addEventListener('DOMContentLoaded', function() {
    const autoRefresh = new AutoRefresh('/?r=our_nodes&ajax=1', {
        refreshButtonId: 'refreshNow',
        toggleButtonId: 'toggleAutoRefresh',
        countdownId: 'refreshCountdown',
        loadingSpinnerId: 'loadingSpinner',
        contentContainerId: 'refreshableContent',
        interval: 30000,
        onRefresh: function(data) {
            // Update node count
            const nodeCountElement = document.getElementById('nodeCount');
            if (nodeCountElement && data.nodeCount !== undefined) {
                nodeCountElement.innerHTML = data.nodeCount + ' nodes found';
            }
            
            // Debug: Log received data
            console.log('AJAX Refresh Data:', {
                nodeCount: data.nodeCount,
                requestId: data.requestId,
                debug_node_nums: data.debug_node_nums,
                debug_node_names: data.debug_node_names,
                timestamp: data.timestamp
            });
        }
    });
});
</script>
