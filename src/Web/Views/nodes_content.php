<?php
// Content-only view for AJAX updates - no header, footer, or navigation
?>
<div class="table-wrap">
<table class="table table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th><i class="fas fa-hashtag"></i> Node #</th>
      <th><i class="fas fa-id-card"></i> Long Name</th>
      <th><i class="fas fa-tag"></i> Short Name</th>
      <th><i class="fas fa-microchip"></i> Hardware</th>
      <th><i class="fas fa-clock"></i> Last Seen</th>
      <th><i class="fas fa-chart-line"></i> Activity</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($rows)): ?>
    <tr>
      <td colspan="6" class="text-center text-muted">
        <i class="fas fa-network-wired"></i> No nodes found
      </td>
    </tr>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <a href="/?r=node&id=<?= (int)$r['node_num'] ?>" class="text-decoration-none">
            <strong><?= (int)$r['node_num'] ?></strong>
          </a>
        </td>
        <td>
          <div class="node-name">
            <?php if (!empty($r['long_name'])): ?>
              <strong><?= htmlspecialchars($r['long_name']) ?></strong>
            <?php else: ?>
              <span class="text-muted">Unknown</span>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <?php if (!empty($r['short_name'])): ?>
            <code class="bg-light p-1 rounded"><?= htmlspecialchars($r['short_name']) ?></code>
          <?php else: ?>
            <span class="text-muted">-</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($r['hardware'])): ?>
            <span class="badge bg-info"><?= htmlspecialchars($r['hardware']) ?></span>
          <?php else: ?>
            <span class="text-muted">Unknown</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['last_seen']): ?>
            <div class="time-info">
              <strong><?= date('M j, Y', (int)$r['last_seen']) ?></strong>
              <br>
              <small class="text-muted"><?= date('H:i:s', (int)$r['last_seen']) ?></small>
              <br>
              <small class="text-info"><?= $this->timeAgo((int)$r['last_seen']) ?></small>
            </div>
          <?php else: ?>
            <span class="text-muted">Never</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="activity-info">
            <?php if ($r['last_activity']): ?>
              <small class="text-success">
                <i class="fas fa-pulse"></i> <?= $this->timeAgo((int)$r['last_activity']) ?>
              </small>
              <br>
            <?php endif; ?>
            <div class="topic-stats">
              <?php if ($r['position_topics'] > 0): ?>
                <span class="badge bg-primary me-1" title="Position topics">
                  <i class="fas fa-map-marker-alt"></i> <?= $r['position_topics'] ?>
                </span>
              <?php endif; ?>
              <?php if ($r['message_topics'] > 0): ?>
                <span class="badge bg-success me-1" title="Message topics">
                  <i class="fas fa-comments"></i> <?= $r['message_topics'] ?>
                </span>
              <?php endif; ?>
              <?php if ($r['telemetry_topics'] > 0): ?>
                <span class="badge bg-warning me-1" title="Telemetry topics">
                  <i class="fas fa-chart-line"></i> <?= $r['telemetry_topics'] ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>
</div>
