<div class="container mt-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <h1>Node Management</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

<div class="node-management-container">
    <div class="info-section">
        <h2>About Node Tracking</h2>
        <div class="info-card">
            <p><strong>OUR_NODES:</strong> Primary nodes that belong to your mesh network. These are typically your own devices.</p>
            <p><strong>NODE_HISTORY_IDS:</strong> Additional nodes to track position history for. These can be any nodes you want to monitor.</p>
            <p>Node IDs can be specified in either decimal format (e.g., 3126879184) or hexadecimal format (e.g., ba6063d0).</p>
            <p>Separate multiple node IDs with commas.</p>
        </div>
    </div>

    <form method="POST" class="node-form">
        <div class="form-section">
            <h3>Our Nodes</h3>
            <div class="form-group">
                <label for="our_nodes">OUR_NODES (comma-separated):</label>
                <input type="text" 
                       id="our_nodes" 
                       name="our_nodes" 
                       value="<?= htmlspecialchars($currentOurNodes) ?>" 
                       placeholder="e.g., ba6063d0,3126879184,ba620828">
                <small>Primary nodes belonging to your mesh network</small>
            </div>

            <?php if (!empty($ourNodesDetails)): ?>
                <div class="node-details">
                    <h4>Current Our Nodes (<?= count($ourNodesDetails) ?> nodes):</h4>
                    <div class="node-grid">
                        <?php foreach ($ourNodesDetails as $node): ?>
                            <div class="node-card">
                                <div class="node-id">
                                    <a href="/?r=node&id=<?= $node['node_num'] ?>" class="node-link">
                                        <?= $node['node_num'] ?> (<?= dechex($node['node_num']) ?>)
                                    </a>
                                </div>
                                <div class="node-name"><?= htmlspecialchars($node['long_name'] ?: 'Unknown') ?></div>
                                <div class="node-short"><?= htmlspecialchars($node['short_name'] ?: 'N/A') ?></div>
                                <div class="node-last-seen">Last seen: <?= $node['last_seen_time'] ?: 'Never' ?></div>
                                <div class="node-hardware"><?= htmlspecialchars($node['hardware'] ?: 'Unknown') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-section">
            <h3>Position History Nodes</h3>
            <div class="form-group">
                <label for="node_history_ids">NODE_HISTORY_IDS (comma-separated):</label>
                <input type="text" 
                       id="node_history_ids" 
                       name="node_history_ids" 
                       value="<?= htmlspecialchars($currentNodeHistoryIds) ?>" 
                       placeholder="e.g., 5d7eba6a,1568586346">
                <small>Additional nodes to track position history for</small>
            </div>

            <?php if (!empty($nodeHistoryDetails)): ?>
                <div class="node-details">
                    <h4>Current History Nodes (<?= count($nodeHistoryDetails) ?> nodes):</h4>
                    <div class="node-grid">
                        <?php foreach ($nodeHistoryDetails as $node): ?>
                            <div class="node-card">
                                <div class="node-id">
                                    <a href="/?r=node&id=<?= $node['node_num'] ?>" class="node-link">
                                        <?= $node['node_num'] ?> (<?= dechex($node['node_num']) ?>)
                                    </a>
                                </div>
                                <div class="node-name"><?= htmlspecialchars($node['long_name'] ?: 'Unknown') ?></div>
                                <div class="node-short"><?= htmlspecialchars($node['short_name'] ?: 'N/A') ?></div>
                                <div class="node-last-seen">Last seen: <?= $node['last_seen_time'] ?: 'Never' ?></div>
                                <div class="node-hardware"><?= htmlspecialchars($node['hardware'] ?: 'Unknown') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Node Configuration</button>
            <a href="/?r=tools" class="btn btn-secondary">Back to Tools</a>
        </div>
    </form>
</div>

<style>
.node-management-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.info-section {
    margin-bottom: 30px;
}

.info-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    padding: 20px;
    border-radius: 8px;
    margin-top: 10px;
}

.info-card p {
    margin-bottom: 10px;
}

.node-form {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    padding: 30px;
    border-radius: 8px;
}

.form-section {
    margin-bottom: 40px;
}

.form-section h3 {
    color: var(--primary-color);
    margin-bottom: 20px;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 5px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: var(--text-color);
}

.form-group input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    background: var(--input-bg);
    color: var(--text-color);
}

.form-group input[type="text"]:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: var(--muted-color);
    font-size: 12px;
}

.node-details {
    margin-top: 20px;
    padding: 20px;
    background: var(--secondary-bg);
    border-radius: 6px;
    border: 1px solid var(--border-color);
}

.node-details h4 {
    margin-bottom: 15px;
    color: var(--text-color);
}

.node-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.node-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    padding: 15px;
    border-radius: 6px;
    transition: box-shadow 0.2s;
}

.node-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.node-id {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.node-link {
    color: var(--primary-color);
    text-decoration: none;
    transition: color 0.2s ease;
}

.node-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.node-link:visited {
    color: var(--primary-color);
}

.node-name {
    font-weight: bold;
    color: var(--text-color);
    margin-bottom: 3px;
}

.node-short {
    color: var(--muted-color);
    font-size: 12px;
    margin-bottom: 5px;
}

.node-last-seen {
    font-size: 11px;
    color: var(--muted-color);
    margin-bottom: 3px;
}

.node-hardware {
    font-size: 11px;
    color: var(--muted-color);
}

.form-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.2s;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-weight: bold;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

@media (max-width: 768px) {
    .node-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn {
        text-align: center;
    }
}
</style>

        </div>
    </div>
</div>
