<?php

namespace App\Handlers;

use App\Database;

/**
 * Handler for custom/unknown port types that appear in traffic
 */
class CustomPortHandler
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Handle unknown ports with basic analysis
     */
    public function handleUnknownPort(int $port, int $nodeFrom, string $payload, ?int $rxTs = null): array
    {
        $analysis = $this->analyzePayload($payload);
        
        // Store in custom_messages table for analysis
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO custom_messages (
                port_num, node_from, payload_length, payload_type, 
                has_text, has_binary, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $port,
            $nodeFrom,
            strlen($payload),
            $analysis['type'],
            $analysis['has_text'] ? 1 : 0,
            $analysis['has_binary'] ? 1 : 0,
            $rxTs ?: time()
        ]);
        
        return [
            'port' => $port,
            'node' => $nodeFrom,
            'analysis' => $analysis,
            'stored' => true
        ];
    }
    
    /**
     * Analyze payload to determine characteristics
     */
    private function analyzePayload(string $payload): array
    {
        $length = strlen($payload);
        $hasText = false;
        $hasBinary = false;
        $type = 'unknown';
        
        if ($length == 0) {
            $type = 'empty';
        } elseif ($length < 4) {
            $type = 'short';
            $hasText = ctype_print($payload);
        } else {
            // Check for common patterns
            if (ctype_print($payload)) {
                $hasText = true;
                if (json_decode($payload) !== null) {
                    $type = 'json';
                } elseif (preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $payload)) {
                    $type = 'text';
                } else {
                    $type = 'mixed_text';
                }
            } else {
                $hasBinary = true;
                
                // Check for protobuf patterns (field tags)
                if (preg_match('/[\x08-\x0F]/', $payload) || preg_match('/[\x10-\x1F]/', $payload)) {
                    $type = 'protobuf_likely';
                } elseif (ord($payload[0]) == 0x7E || ord($payload[0]) == 0x7F) {
                    $type = 'frame_delimited';
                } else {
                    $type = 'binary';
                }
            }
        }
        
        return [
            'type' => $type,
            'length' => $length,
            'has_text' => $hasText,
            'has_binary' => $hasBinary,
            'first_bytes' => bin2hex(substr($payload, 0, min(8, $length))),
            'printable_chars' => preg_match_all('/[[:print:]]/', $payload)
        ];
    }
    
    /**
     * Get statistics for custom ports
     */
    public function getCustomPortStats(): array
    {
        $stmt = $this->db->pdo()->query("
            SELECT 
                port_num,
                COUNT(*) as count,
                COUNT(DISTINCT node_from) as unique_nodes,
                AVG(payload_length) as avg_length,
                MAX(created_at) as latest,
                payload_type,
                COUNT(CASE WHEN has_text = 1 THEN 1 END) as text_count,
                COUNT(CASE WHEN has_binary = 1 THEN 1 END) as binary_count
            FROM custom_messages 
            WHERE created_at > strftime('%s', 'now', '-7 days')
            GROUP BY port_num, payload_type
            ORDER BY count DESC, port_num ASC
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
