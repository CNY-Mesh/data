<?php
// Content-only view for AJAX updates - no header, footer, or navigation
?>
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
