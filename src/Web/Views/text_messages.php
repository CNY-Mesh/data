<h2>
  Text Messages
  <?php if (isset($filter_node) && $filter_node): ?>
    <small class="text-muted">from <?= htmlspecialchars($filter_node['long_name'] ?? $filter_node['short_name'] ?? 'Node #' . $filter_node['node_num']) ?></small>
  <?php endif; ?>
  <span id="refresh-indicator" class="badge bg-success ms-2" style="display: none;">
    <i class="fas fa-sync-alt fa-spin"></i> Refreshing...
  </span>
</h2>

<?php if (isset($filter_node) && $filter_node): ?>
<div class="mb-3">
  <a href="/?r=text_messages" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Show All Messages
  </a>
</div>
<?php endif; ?>

<div class="mb-3 d-flex justify-content-between align-items-center">
  <p class="text-muted mb-0">
    <i class="fas fa-comments"></i> 
    Showing <span id="message-count"><?= number_format(count($rows)) ?></span> of <span id="total-count"><?= number_format($total) ?></span> text messages
    <?php if ($total > $limit): ?>
      (Page <span id="current-page"><?= $current_page ?></span> of <span id="total-pages"><?= $total_pages ?></span>)
    <?php endif; ?>
  </p>
  <div>
    <button id="toggle-auto-refresh" class="btn btn-outline-primary btn-sm" data-enabled="true">
      <i class="fas fa-pause"></i> Auto-refresh ON
    </button>
    <button id="manual-refresh" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-sync-alt"></i> Refresh Now
    </button>
  </div>
</div>

<?php if ($total > $limit): 
    $filter_param = $filter_from > 0 ? "&filter_from={$filter_from}" : '';
?>
<nav aria-label="Text messages pagination">
  <ul class="pagination">
    <?php if ($current_page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="/?r=text_messages&limit=<?= $limit ?>&offset=<?= ($current_page - 2) * $limit ?><?= $filter_param ?>">
          <i class="fas fa-chevron-left"></i> Previous
        </a>
      </li>
    <?php endif; ?>
    
    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
      <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
        <a class="page-link" href="/?r=text_messages&limit=<?= $limit ?>&offset=<?= ($i - 1) * $limit ?><?= $filter_param ?>">
          <?= $i ?>
        </a>
      </li>
    <?php endfor; ?>
    
    <?php if ($current_page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="/?r=text_messages&limit=<?= $limit ?>&offset=<?= $current_page * $limit ?><?= $filter_param ?>">
          Next <i class="fas fa-chevron-right"></i>
        </a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>

<!-- Refreshable Content Area -->
<div id="refreshable-content">
<div class="table-wrap">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th><i class="fas fa-user"></i> From</th>
        <th><i class="fas fa-user-check"></i> To</th>
        <th><i class="fas fa-comment"></i> Message</th>
        <th><i class="fas fa-satellite-dish"></i> Topic</th>
        <th><i class="fas fa-clock"></i> Received</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="5" class="text-center text-muted">
            <i class="fas fa-inbox"></i> No text messages found
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <div class="node-info">
                <strong>
                  <a href="/?r=node&id=<?= (int)$r['node_from'] ?>" class="text-decoration-none">
                    <?= htmlspecialchars($r['from_name'] ?: $r['from_short'] ?: 'Unknown') ?>
                  </a>
                </strong>
                <br>
                <small class="text-muted">
                  <i class="fas fa-hashtag"></i><?= (int)$r['node_from'] ?>
                </small>
              </div>
            </td>
            <td>
              <?php if ($r['node_to'] && $r['node_to'] != 4294967295): ?>
                <div class="node-info">
                  <strong>
                    <a href="/?r=node&id=<?= (int)$r['node_to'] ?>" class="text-decoration-none">
                      <?= htmlspecialchars($r['to_name'] ?: $r['to_short'] ?: 'Unknown') ?>
                    </a>
                  </strong>
                  <br>
                  <small class="text-muted">
                    <i class="fas fa-hashtag"></i><?= (int)$r['node_to'] ?>
                  </small>
                </div>
              <?php else: ?>
                <span class="badge bg-secondary">
                  <i class="fas fa-broadcast-tower"></i> Broadcast
                </span>
              <?php endif; ?>
            </td>
            <td>
              <div class="message-content">
                <?= nl2br(htmlspecialchars($r['message'] ?? '')) ?>
              </div>
            </td>
            <td>
              <?php if (!empty($r['topic'])): ?>
                <div class="topic-info">
                  <code class="small bg-light p-1 rounded"><?= htmlspecialchars($r['topic']) ?></code>
                  <br>
                  <small class="text-muted">
                    <?php 
                    $topic_parts = explode('/', $r['topic']);
                    if (count($topic_parts) >= 2) {
                      echo '<i class="fas fa-globe"></i> ' . htmlspecialchars($topic_parts[1]); // Region like "US"
                      if (count($topic_parts) >= 5) {
                        echo ' <i class="fas fa-key"></i> ' . (strpos($r['topic'], '/c/') !== false ? 'Encrypted' : 'Public');
                      }
                    }
                    ?>
                  </small>
                </div>
              <?php else: ?>
                <span class="text-muted">
                  <i class="fas fa-question-circle"></i> Unknown
                </span>
              <?php endif; ?>
            </td>
            <td>
              <div class="time-info">
                <strong><?= date('M j, Y', (int)$r['rx_time']) ?></strong>
                <br>
                <small class="text-muted">
                  <?= date('H:i:s', (int)$r['rx_time']) ?>
                </small>
                <br>
                <small class="text-info">
                  <?= $this->timeAgo((int)$r['rx_time']) ?>
                </small>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($total > $limit): ?>
<nav aria-label="Text messages pagination bottom">
  <ul class="pagination justify-content-center">
    <?php if ($current_page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="/?r=text_messages&limit=<?= $limit ?>&offset=<?= ($current_page - 2) * $limit ?>">
          <i class="fas fa-chevron-left"></i> Previous
        </a>
      </li>
    <?php endif; ?>
    
    <li class="page-item disabled">
      <span class="page-link">
        Page <?= $current_page ?> of <?= $total_pages ?>
      </span>
    </li>
    
    <?php if ($current_page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="/?r=text_messages&limit=<?= $limit ?>&offset=<?= $current_page * $limit ?>">
          Next <i class="fas fa-chevron-right"></i>
        </a>
      </li>
    <?php endif; ?>
  </ul>
</nav>

<div class="mt-3 text-center">
  <small class="text-muted">
    <i class="fas fa-info-circle"></i>
    Showing <?= $limit ?> messages per page. 
    <a href="/?r=text_messages&limit=50&offset=0" class="text-decoration-none">Show 50</a> | 
    <a href="/?r=text_messages&limit=100&offset=0" class="text-decoration-none">Show 100</a> | 
    <a href="/?r=text_messages&limit=500&offset=0" class="text-decoration-none">Show 500</a>
  </small>
</div>
<?php endif; ?>
</div>
<!-- End Refreshable Content Area -->

<script>
// Initialize auto-refresh when page loads using the new AutoRefresh class
document.addEventListener('DOMContentLoaded', function() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('ajax', '1');
    
    // Use the legacy support function for the older interface
    window.createAutoRefresh('refreshable-content', currentUrl.toString(), 30000);
});
</script>
