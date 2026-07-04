<?php
declare(strict_types=1);
namespace App\Handlers;
use App\Database; use Meshtastic\MapReport;
final class MapReportHandler {
    public function __construct(private Database $db) {}
    public function store(int $nodeNum, string $channelId, MapReport $mr, int $rxTs, string $rawPayload): void {
        $stmt = $this->db->pdo()->prepare(
            "INSERT INTO map_reports (node_num, channel_id, raw_pb, saved_at)
             VALUES (:n,:ch,:raw,:ts)"
        );
        $stmt->execute([':n'=>$nodeNum, ':ch'=>$channelId, ':raw'=>$rawPayload, ':ts'=>$rxTs]);
    }
}
