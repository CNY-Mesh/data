<h2>Map Reports (stored)</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Node</th><th>Channel</th><th>Bytes</th><th>Saved</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= (int)$r['node_num'] ?></td>
      <td><?= htmlspecialchars($r['channel_id'] ?? '') ?></td>
      <td><?= (int)($r['bytes'] ?? 0) ?></td>
      <td><?= date('Y-m-d H:i:s', (int)$r['saved_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p>These are not widely adopted yet, so they are stored only for future use.</p>
