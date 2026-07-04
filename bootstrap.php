<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use App\Support\Env;

// Load .env from the project directory
Env::load(__DIR__);

// Initialize SQLite schema automatically if DSN is sqlite:
$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/data/meshtastic.sqlite';
if (str_starts_with($dsn, 'sqlite:')) {
    $path = substr($dsn, 7);
    $dir  = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    
    // Only create an empty database file if it doesn't exist
    // The schema should be loaded manually using the schema tools
    if (!file_exists($path)) { 
        touch($path); 
        
        // Only for completely new databases, load the schema
        $pdo = new PDO($dsn);
        $pdo->exec(file_get_contents(__DIR__ . '/schema/sqlite.sql'));
    }
}
