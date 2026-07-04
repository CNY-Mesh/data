<h2>Telemetry (latest per node)</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>Node</th><th>Battery</th><th>Voltage</th><th>Chan Util</th><th>Air Util Tx</th><th>Uptime</th><th>Updated</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="/?r=node&id=<?= (int)$r['node_num'] ?>"><?= (int)$r['node_num'] ?></a></td>
      <td><?= htmlspecialchars((string)($r['battery_level'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($r['voltage'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($r['channel_utilization'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($r['air_util_tx'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($r['uptime_seconds'] ?? '')) ?></td>
      <td><?= date('Y-m-d H:i:s', (int)$r['updated_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
