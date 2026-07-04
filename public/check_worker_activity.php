<?php
/**
 * Check Recent MQTT Worker Activity
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Support\Env;

echo "=== Recent MQTT Worker Activity ===\n";

$dsn = Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../data/meshtastic.sqlite';
$db = new Database($dsn);

// Check the most recent messages
echo "Last 10 messages received:\n";
$recent = $db->pdo()->query("
    SELECT id, channel_id, topic, message_type, is_encrypted, created_at, LENGTH(raw_message) as size
    FROM raw_messages 
    ORDER BY id DESC 
    LIMIT 10
")->fetchAll();

foreach ($recent as $msg) {
    echo sprintf("#%-5d %-12s %-25s %-15s enc:%-1d %s (%d bytes)\n", 
        $msg['id'],
        $msg['channel_id'],
        $msg['topic'],
        $msg['message_type'],
        $msg['is_encrypted'],
        $msg['created_at'],
        $msg['size']
    );
}

// Check if we're getting any successful decodes recently
echo "\n=== Recently Successful Decodes ===\n";
$successful = $db->pdo()->query("
    SELECT id, channel_id, topic, message_type, created_at
    FROM raw_messages 
    WHERE message_type NOT IN ('DECODE_FAILED', 'JSON_PARSE_ERROR')
    ORDER BY id DESC 
    LIMIT 10
")->fetchAll();

foreach ($successful as $msg) {
    echo sprintf("#%-5d %-12s %-25s %-15s %s\n", 
        $msg['id'],
        $msg['channel_id'],
        $msg['topic'],
        $msg['message_type'],
        $msg['created_at']
    );
}

// Check what's in the logs directory
$logDir = __DIR__ . '/../logs';
if (is_dir($logDir)) {
    echo "\n=== Log Files ===\n";
    $files = scandir($logDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $path = $logDir . '/' . $file;
            $size = filesize($path);
            $modified = date('Y-m-d H:i:s', filemtime($path));
            echo "$file: $size bytes, modified $modified\n";
        }
    }
}
?>
