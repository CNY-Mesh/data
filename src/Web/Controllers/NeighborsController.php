<?php
declare(strict_types=1);
namespace App\Web\Controllers;
final class NeighborsController extends BaseController
{
    public function handle(): void
    {
        $rows = $this->db->pdo()->query('SELECT * FROM neighbors ORDER BY heard_at DESC LIMIT 500')->fetchAll();
        $this->render('neighbors', ['rows'=>$rows]);
    }
}
