<?php
declare(strict_types=1);
namespace App\Support;

use Dotenv\Dotenv;

final class Env {
    /** Load .env from the given directory. */
    public static function load(string $fileOrDir): void {
        $dir = is_dir($fileOrDir) ? $fileOrDir : dirname($fileOrDir);
        if (file_exists($dir.'/.env')) {
            Dotenv::createImmutable($dir)->safeLoad();
        }
    }

    /** Read env from $_ENV/$_SERVER first, then getenv(), with a default. */
    public static function get(string $key, $default = null): ?string {
        if (array_key_exists($key, $_ENV))    return $_ENV[$key];
        if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];
        $v = getenv($key);
        if ($v !== false && $v !== '') return $v;
        return $default;
    }
}
