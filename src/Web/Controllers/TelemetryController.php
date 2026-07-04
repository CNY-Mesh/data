<?php
declare(strict_types=1);
namespace App\Web\Controllers;
final class TelemetryController extends BaseController
{
    public function handle(): void
    {
        $rows = $this->db->pdo()->query('SELECT * FROM telemetry ORDER BY updated_at DESC')->fetchAll();
        $this->render('telemetry', ['rows'=>$rows]);
    }
}
