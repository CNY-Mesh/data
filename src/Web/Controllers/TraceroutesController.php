<?php
declare(strict_types=1);
namespace App\Web\Controllers;
final class TraceroutesController extends BaseController
{
    public function handle(): void
    {
        $rows = $this->db->pdo()->query('SELECT * FROM traceroutes ORDER BY logged_at DESC LIMIT 500')->fetchAll();
        $this->render('traceroutes', ['rows'=>$rows]);
    }
}
