<?php
declare(strict_types=1);
namespace App\Handlers;
use App\Database; use Meshtastic\NeighborInfo;
final class NeighborInfoHandler {
    public function __construct(private Database $db) {}
    public function insertReport(int $reporterNodeNum, NeighborInfo $info, int $rxTs): void {
        foreach ($info->getNeighbors() as $neighbor) {
            $stmt = $this->db->pdo()->prepare(
                "INSERT INTO neighbors (reporter_node_num, neighbor_node_num, snr, heard_at)
                 VALUES (:r,:n,:snr,:ts)"
            );
            $stmt->execute([':r'=>$reporterNodeNum, ':n'=>$neighbor->getNodeId(), ':snr'=>$neighbor->getSnr(), ':ts'=>$rxTs]);
        }
    }
}
