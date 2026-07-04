<h2>Traceroutes (recent)</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Packet</th><th>Src</th><th>Dst</th><th>Hop #</th><th>Hop Node</th><th>SNR</th><th>Logged</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= (int)$r['mesh_packet_id'] ?></td>
      <td><?= (int)$r['src_node_num'] ?></td>
      <td><?= (int)$r['dest_node_num'] ?></td>
      <td><?= (int)$r['hop_index'] ?></td>
      <td><?= (int)$r['hop_node_num'] ?></td>
      <td><?= htmlspecialchars((string)$r['snr']) ?></td>
      <td><?= date('Y-m-d H:i:s', (int)$r['logged_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
