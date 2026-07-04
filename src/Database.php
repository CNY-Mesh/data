<?php
declare(strict_types=1);
namespace App;

use PDO;
use App\Support\Env as E;

final class Database {
    private PDO $pdo;
    public function __construct(string $dsn) {
        if (str_starts_with($dsn, 'sqlite:')) {
            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $this->pdo = new PDO(
                $dsn,
                E::get('DB_USER') ?: null,
                E::get('DB_PASS') ?: null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        $this->pdo->exec('PRAGMA journal_mode=WAL;');
    }
    public function pdo(): PDO { return $this->pdo; }
}
