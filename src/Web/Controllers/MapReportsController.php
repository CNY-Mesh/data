<?php
declare(strict_types=1);
namespace App\Web\Controllers;
final class MapReportsController extends BaseController
{
    public function handle(): void
    {
        $rows = $this->db->pdo()->query('SELECT id, node_num, channel_id, saved_at, LENGTH(raw_pb) as bytes FROM map_reports ORDER BY saved_at DESC LIMIT 200')->fetchAll();
        $this->render('mapreports', ['rows'=>$rows]);
    }
}
