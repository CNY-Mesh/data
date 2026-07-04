<?php
/**
 * Tools View - Debug and Development Tools Interface
 */
?>

<div class="container-fluid">
    <!-- Featured Tools Section -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">⭐ Featured Tools</h4>
                    <small class="opacity-75">Main application features and utilities</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($featuredTools as $tool): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="<?= $tool['icon'] ?> fa-3x text-<?= $tool['color'] ?>"></i>
                                        </div>
                                        <h5 class="card-title"><?= htmlspecialchars($tool['name']) ?></h5>
                                        <p class="card-text text-muted small">
                                            <?= htmlspecialchars($tool['description']) ?>
                                        </p>
                                        <a href="/?r=<?= $tool['route'] ?>" 
                                           class="btn btn-<?= $tool['color'] ?> btn-sm">
                                            <i class="<?= $tool['icon'] ?> me-1"></i>
                                            Open <?= htmlspecialchars($tool['name']) ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Tools Section -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">🔧 Debug Tools</h4>
                        <small class="opacity-75">Development and debugging utilities from /public folder</small>
                    </div>
                    <div class="text-end">
                        <small>Last updated: <?= $stats['last_updated'] ?></small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card text-center p-3 bg-light rounded">
                                <div class="stat-number h3 text-primary mb-1"><?= $stats['total_tools'] ?></div>
                                <div class="stat-label text-muted">Total Tools</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card text-center p-3 bg-light rounded">
                                <div class="stat-number h3 text-primary mb-1"><?= $stats['categories'] ?></div>
                                <div class="stat-label text-muted">Categories</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card text-center p-3 bg-light rounded">
                                <div class="stat-number h3 text-primary mb-1"><?= date('H:i') ?></div>
                                <div class="stat-label text-muted">Current Time</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> These are legacy debug tools from the /public folder. 
                        All tools are now password-protected and accessible only to authenticated users. 
                        For main application features, see the Featured Tools section above.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($tools as $categoryName => $files): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <?= htmlspecialchars($categoryName) ?> 
                            <span class="badge bg-light text-dark"><?= count($files) ?> tools</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tool</th>
                                        <th>Description</th>
                                        <th>Size</th>
                                        <th>Modified</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-file-code text-primary me-2"></i>
                                                <strong><?= htmlspecialchars($file['filename']) ?></strong>
                                            </td>
                                            <td class="text-muted">
                                                <?= htmlspecialchars($file['description']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= number_format($file['size'] / 1024, 1) ?> KB
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('M j, H:i', $file['modified']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="<?= htmlspecialchars($file['url']) ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   target="_blank"
                                                   title="Open <?= htmlspecialchars($file['filename']) ?> in new window">
                                                    <i class="fas fa-external-link-alt"></i> Open
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">📝 Usage Notes</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>Featured Tools:</strong> Main application features accessible through this unified interface</li>
                        <li><strong>Debug Tools:</strong> Legacy development utilities from the /public folder</li>
                        <li><strong>Authentication Required:</strong> All tools require login credentials to access</li>
                        <li><strong>External Tools:</strong> Debug tools open in new windows and may require separate authentication</li>
                        <li><strong>Auto-Discovery:</strong> Debug tools are automatically categorized based on their functionality</li>
                        <li><strong>Security:</strong> Direct access to /public/ files is restricted - use this interface instead</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.table th {
    border-top: none;
    font-weight: 600;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
}

.card-header {
    border-bottom: none;
}
</style>
