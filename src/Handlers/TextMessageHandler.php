<?php
declare(strict_types=1);
namespace App\Handlers;

use App\Database;

final class TextMessageHandler
{
    public function __construct(private Database $db) {}

    public function store(int $nodeFrom, int $nodeTo, string $text, int $rxTime): bool
    {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('
                INSERT INTO text_messages (node_from, node_to, message, rx_time) 
                VALUES (?, ?, ?, ?)
            ');
            
            return $stmt->execute([$nodeFrom, $nodeTo, $text, $rxTime]);
        } catch (\Throwable $e) {
            error_log("Failed to store text message: " . $e->getMessage());
            return false;
        }
    }
}
