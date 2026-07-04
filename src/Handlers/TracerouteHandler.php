<?php
declare(strict_types=1);
namespace App\Handlers;
use App\Database; use Meshtastic\RouteDiscovery;
final class TracerouteHandler {
    public function __construct(private Database $db) {}
    public function insertRoute(int $packetId, int $src, int $dst, RouteDiscovery $rd, int $rxTs): void {
        $hops = $rd->getRoute();
        foreach ($hops as $i => $hop) {
            $stmt = $this->db->pdo()->prepare(
                "INSERT INTO traceroutes (mesh_packet_id, src_node_num, dest_node_num, hop_index, hop_node_num, snr, logged_at)
                 VALUES (:pid,:src,:dst,:i,:hop,:snr,:ts)"
            );
            $stmt->execute([':pid'=>$packetId, ':src'=>$src, ':dst'=>$dst, ':i'=>$i, ':hop'=>$hop->getNodeId(), ':snr'=>$hop->getSnr(), ':ts'=>$rxTs]);
        }
    }
}
