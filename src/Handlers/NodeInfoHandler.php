<?php
declare(strict_types=1);
namespace App\Handlers;
use App\Database; use Meshtastic\User;
final class NodeInfoHandler {
    public function __construct(private Database $db) {}
    public function upsert(int $nodeNum, User $u, int $rxTs): void {
        $stmt = $this->db->pdo()->prepare(
            "INSERT INTO nodes (node_num, node_id, long_name, short_name, hardware, last_seen)
             VALUES (:n,:id,:ln,:sn,:hw,:ts)
             ON CONFLICT(node_num) DO UPDATE SET
               node_id=excluded.node_id,
               long_name=COALESCE(excluded.long_name, nodes.long_name),
               short_name=COALESCE(excluded.short_name, nodes.short_name),
               hardware=COALESCE(excluded.hardware, nodes.hardware),
               last_seen=MAX(nodes.last_seen, excluded.last_seen)"
        );
        $stmt->execute([':n'=>$nodeNum, ':id'=>$u->getId()?:null, ':ln'=>$u->getLongName()?:null, ':sn'=>$u->getShortName()?:null, ':hw'=>$u->getHwModel()?:null, ':ts'=>$rxTs]);
    }
}
