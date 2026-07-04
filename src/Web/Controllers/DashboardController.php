<?php
declare(strict_types=1);
namespace App\Web\Controllers;
use App\Support\Env;

final class DashboardController extends BaseController
{
    public function handle(): void
    {
        $pdo = $this->db->pdo();
        $nodes = (int)$pdo->query('SELECT COUNT(*) FROM nodes')->fetchColumn();
        $pos   = (int)$pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn();
        $nei   = (int)$pdo->query('SELECT COUNT(*) FROM neighbors')->fetchColumn();
        $tel   = (int)$pdo->query('SELECT COUNT(*) FROM telemetry')->fetchColumn();
        $trc   = (int)$pdo->query('SELECT COUNT(*) FROM traceroutes')->fetchColumn();
        $map   = (int)$pdo->query('SELECT COUNT(*) FROM map_reports')->fetchColumn();

        // Get "our nodes" list from environment
        $ourNodesStr = Env::get('OUR_NODES', '');
        $ourNodesList = array_filter(array_map('trim', explode(',', $ourNodesStr)));

        $rows = $pdo->query('SELECT p.node_num, 
                                COALESCE(n.long_name, "Unknown Node") as long_name, 
                                COALESCE(n.short_name, SUBSTR(printf("!%08x", p.node_num), -4)) as short_name, 
                                p.lat, p.lon, p.time, p.altitude, p.rx_rssi, p.rx_snr,
                                CASE WHEN n.node_num IS NOT NULL THEN 1 ELSE 0 END as is_known_node
                             FROM positions p 
                             LEFT JOIN nodes n ON p.node_num = n.node_num
                             WHERE p.lat IS NOT NULL AND p.lon IS NOT NULL
                             AND (
                                 -- Include positions less than 3 hours old (10800 seconds)
                                 p.time > (strftime(\'%s\', \'now\') - 10800)
                                 OR
                                 -- Include older positions only if they have recent node info (last_seen within 3 hours)
                                 (p.time <= (strftime(\'%s\', \'now\') - 10800) AND n.last_seen > (strftime(\'%s\', \'now\') - 10800))
                             )
                             ORDER BY p.time DESC LIMIT 200')->fetchAll();

        $this->render('dashboard', compact('nodes','pos','nei','tel','trc','map','rows','ourNodesList'));
    }
}
