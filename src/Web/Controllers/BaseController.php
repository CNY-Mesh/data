<?php
declare(strict_types=1);
namespace App\Web\Controllers;
use App\Database;
use App\Support\Env as E;

abstract class BaseController
{
    protected Database $db;
    public function __construct()
    {
        $this->db = new Database(E::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../../../data/meshtastic.sqlite');
    }
    protected function render(string $view, array $vars = []): void
    {
        extract($vars);
        include __DIR__ . '/../Views/_layout_top.php';
        include __DIR__ . '/../Views/' . $view . '.php';
        include __DIR__ . '/../Views/_layout_bottom.php';
    }
    protected function json($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    protected function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $diff . 's ago';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . 'd ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
}
