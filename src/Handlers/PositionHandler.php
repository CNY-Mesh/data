<?php
declare(strict_types=1);
namespace App\Handlers;
use App\Database; use Meshtastic\Position;
final class PositionHandler {
    public function __construct(private Database $db) {}
    public function upsert(int $nodeNum, Position $p, ?float $rxRssi, ?float $rxSnr, int $rxTs): void {
        $lat = $p->getLatitudeI() / 1e7;
        $lon = $p->getLongitudeI() / 1e7;
        $alt = $p->getAltitude();
        $stmt = $this->db->pdo()->prepare(
            "INSERT INTO positions (node_num, lat, lon, altitude, time, rx_rssi, rx_snr)
             VALUES (:n,:lat,:lon,:alt,:t,:rssi,:snr)
             ON CONFLICT(node_num) DO UPDATE SET
               lat=excluded.lat, lon=excluded.lon, altitude=excluded.altitude,
               time=excluded.time, rx_rssi=excluded.rx_rssi, rx_snr=excluded.rx_snr"
        );
        $stmt->execute([':n'=>$nodeNum, ':lat'=>$lat, ':lon'=>$lon, ':alt'=>$alt, ':t'=>$rxTs, ':rssi'=>$rxRssi, ':snr'=>$rxSnr]);
    }
}
