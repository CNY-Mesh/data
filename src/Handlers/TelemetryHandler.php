<?php
declare(strict_types=1);
namespace App\Handlers;
use App\Database; use Meshtastic\Telemetry;
final class TelemetryHandler {
    public function __construct(private Database $db) {}
    public function upsert(int $nodeNum, Telemetry $t, int $rxTs): void {
        $dm = $t->getDeviceMetrics();
        $stmt = $this->db->pdo()->prepare(
            "INSERT INTO telemetry (node_num, battery_level, voltage, channel_utilization, air_util_tx, uptime_seconds, updated_at)
             VALUES (:n,:bat,:v,:cu,:autx,:up,:ts)
             ON CONFLICT(node_num) DO UPDATE SET
               battery_level=excluded.battery_level,
               voltage=excluded.voltage,
               channel_utilization=excluded.channel_utilization,
               air_util_tx=excluded.air_util_tx,
               uptime_seconds=excluded.uptime_seconds,
               updated_at=excluded.updated_at"
        );
        $stmt->execute([':n'=>$nodeNum, ':bat'=>$dm?->getBatteryLevel(), ':v'=>$dm?->getVoltage(), ':cu'=>$dm?->getChannelUtilization(), ':autx'=>$dm?->getAirUtilTx(), ':up'=>$dm?->getUptimeSeconds(), ':ts'=>$rxTs]);
    }
}
