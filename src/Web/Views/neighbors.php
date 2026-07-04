<h2>Neighbors (recent)</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>Reporter</th><th>Neighbor</th><th>SNR</th><th>Heard At</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>
        <a href="/?r=node&id=<?= (int)$r['reporter_node_num'] ?>" 
           class="text-decoration-none">
          <?= (int)$r['reporter_node_num'] ?>
        </a>
      </td>
      <td>
        <a href="/?r=node&id=<?= (int)$r['neighbor_node_num'] ?>" 
           class="text-decoration-none">
          <?= (int)$r['neighbor_node_num'] ?>
        </a>
      </td>
      <td><?= htmlspecialchars((string)$r['snr']) ?></td>
      <td><?= date('Y-m-d H:i:s', (int)$r['heard_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
