<?php

namespace App\Web\Controllers;
use App\Database;

class RawDataController extends BaseController
{
    public function handle()
    {
        $dsn = \App\Support\Env::get('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../../../data/meshtastic.sqlite';
        $db = new \App\Database($dsn);
        $pdo = $db->pdo();
        
        $tables = [
            'nodes', 'positions', 'neighbors', 'telemetry', 'traceroutes', 'map_reports', 'raw_messages', 'text_messages'
        ];
        
        $data = [];
        foreach ($tables as $table) {
            try {
                if ($table === 'raw_messages') {
                    // Get regular raw messages (excluding decode errors)
                    $stmt = $pdo->query("SELECT * FROM raw_messages WHERE message_type != 'decode_error' OR message_type IS NULL ORDER BY processed_at DESC LIMIT 100");
                } else {
                    $stmt = $pdo->query("SELECT * FROM $table ORDER BY ROWID DESC LIMIT 100");
                }
                $data[$table] = $stmt ? $stmt->fetchAll() : [];
            } catch (\Exception $e) {
                // Table might not exist yet
                $data[$table] = [];
            }
        }
        
        // Get decode errors separately
        try {
            $stmt = $pdo->query("SELECT * FROM raw_messages WHERE message_type = 'decode_error' ORDER BY processed_at DESC LIMIT 100");
            $data['decode_errors'] = $stmt ? $stmt->fetchAll() : [];
        } catch (\Exception $e) {
            $data['decode_errors'] = [];
        }
        
        return $this->render('rawdata', ['data' => $data]);
    }
}
