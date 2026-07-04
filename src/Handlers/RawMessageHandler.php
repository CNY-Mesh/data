<?php
declare(strict_types=1);
namespace App\Handlers;

use App\Database;

final class RawMessageHandler
{
    public function __construct(private Database $db) {}

    public function store(
        string $topic,
        ?string $channelId,
        ?string $gatewayId,
        ?int $nodeFrom,
        ?int $nodeTo,
        ?int $portNum,
        ?string $payload,
        bool $isEncrypted,
        bool $isJson,
        ?string $messageType,
        ?int $rxTime,
        ?float $rxRssi,
        ?float $rxSnr,
        string $rawMessage
    ): bool {
        try {
            $pdo = $this->db->pdo();
            $stmt = $pdo->prepare('
                INSERT INTO raw_messages (
                    topic, channel_id, gateway_id, node_from, node_to, 
                    port_num, payload_hex, payload_length, is_encrypted, is_json,
                    message_type, rx_time, rx_rssi, rx_snr, raw_message
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $payloadHex = $payload ? bin2hex($payload) : null;
            $payloadLength = $payload ? strlen($payload) : 0;
            
            return $stmt->execute([
                $topic,
                $channelId,
                $gatewayId,
                $nodeFrom,
                $nodeTo,
                $portNum,
                $payloadHex,
                $payloadLength,
                $isEncrypted ? 1 : 0,
                $isJson ? 1 : 0,
                $messageType,
                $rxTime,
                $rxRssi,
                $rxSnr,
                $rawMessage
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to store raw message: " . $e->getMessage());
            return false;
        }
    }
}
