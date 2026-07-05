<?php
declare(strict_types=1);
namespace App;

use Meshtastic\ServiceEnvelope;
use Meshtastic\MeshPacket;
use Meshtastic\Data;
use Meshtastic\User;
use Meshtastic\Position;
use Meshtastic\Telemetry;
use Meshtastic\RouteDiscovery;
use Meshtastic\NeighborInfo;
use Meshtastic\MapReport;
use App\Support\Env;

final class Decoder
{
    public function __construct() {
        $this->debug("*** DECODER OBJECT CREATED - CONSTRUCTOR CALLED ***");
    }
    
    // Core Meshtastic application ports (from official PortNum enum)
    public const UNKNOWN_APP          = 0;      // Unknown application
    public const TEXT_MESSAGE_APP     = 1;      // Text messages
    public const REMOTE_HARDWARE_APP  = 2;      // Remote hardware control
    public const POSITION_APP         = 3;      // Position updates
    public const NODEINFO_APP         = 4;      // Node information
    public const ROUTING_APP          = 5;      // Routing control
    public const ADMIN_APP            = 6;      // Administrative messages
    public const TEXT_MESSAGE_COMPRESSED_APP = 7; // Compressed text messages
    public const WAYPOINT_APP         = 8;      // Waypoint management
    public const AUDIO_APP            = 9;      // Audio messages
    public const DETECTION_SENSOR_APP = 10;     // Detection sensor data
    public const REPLY_APP            = 32;     // Reply messages
    public const IP_TUNNEL_APP        = 33;     // IP tunneling
    public const PAXCOUNTER_APP       = 34;     // People counter
    public const SERIAL_APP           = 64;     // Serial data
    public const STORE_FORWARD_APP    = 65;     // Store and forward
    public const RANGE_TEST_APP       = 66;     // Range testing
    public const TELEMETRY_APP        = 67;     // Telemetry data
    public const ZPS_APP              = 68;     // ZPS application
    public const SIMULATOR_APP        = 69;     // Simulator
    public const TRACEROUTE_APP       = 70;     // Traceroute
    public const NEIGHBORINFO_APP     = 71;     // Neighbor information
    public const ATAK_PLUGIN_APP      = 72;     // ATAK plugin
    public const MAP_REPORT_APP       = 73;     // Map reports
    public const POWERSTRESS_APP      = 74;     // Power stress testing
    public const PRIVATE_APP          = 256;    // Private applications start
    public const ATAK_FORWARDER_APP   = 257;    // ATAK forwarder

    // Meshtastic default primary channel key used by LongFast (16-byte AES key).
    private const DEFAULT_LONGFAST_PSK =
        "\xD4\xF1\xBB\x3A\x20\x29\x07\x59\xF0\xBC\xFF\xAB\xCF\x4E\x69\x01";

    private function getChannelKey(string $channelId): string
    {
        $channel = trim($channelId);
        $envKey = $this->buildChannelEnvKey($channel);

        if ($envKey !== null) {
            $fromEnv = Env::get($envKey, '');
            if ($fromEnv !== '') {
                $decoded = $this->decodeChannelKey($fromEnv, $channel, $envKey);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        $longFast = Env::get('LONGFAST_B64_KEY', '');
        if ($longFast !== '') {
            $decoded = $this->decodeChannelKey($longFast, $channel, 'LONGFAST_B64_KEY');
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return self::DEFAULT_LONGFAST_PSK;
    }

    private function buildChannelEnvKey(string $channelId): ?string
    {
        if ($channelId === '') {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $channelId) ?? '');
        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return null;
        }

        return $normalized . '_B64_KEY';
    }

    private function decodeChannelKey(string $b64, string $channelId, string $sourceVar): ?string
    {
        $raw = base64_decode($b64, true);
        if ($raw === false || $raw === '') {
            $this->debug("Invalid base64 key in $sourceVar for channel '$channelId'");
            return null;
        }

        $len = strlen($raw);
        if (in_array($len, [16, 24, 32], true)) {
            return $raw;
        }

        // Meshtastic commonly stores the default key as AQ== (single byte 0x01).
        if ($len === 1) {
            if ($raw === "\x01") {
                return self::DEFAULT_LONGFAST_PSK;
            }

            return str_repeat($raw, 16);
        }

        $this->debug("Unsupported key length ($len) in $sourceVar for channel '$channelId'");
        return null;
    }

    public function parseEnvelope(string $binary): ?ServiceEnvelope {
        // Input validation
        if (empty($binary)) {
            echo "[DEBUG] Empty binary data provided\n";
            return null;
        }
        
        $length = strlen($binary);
        if ($length < 2) {
            echo "[DEBUG] Binary data too short ($length bytes) - minimum 2 bytes required\n";
            return null;
        }
        
        // Check for obviously invalid data patterns
        if ($length > 10000) {
            echo "[DEBUG] Binary data suspiciously large ($length bytes) - likely corrupted\n";
            return null;
        }
        
        // Check if data appears to be valid protobuf (basic sanity check)
        $firstByte = ord($binary[0]);
        if ($firstByte > 0xF8) {
            echo "[DEBUG] Invalid protobuf start byte: 0x" . dechex($firstByte) . " - likely corrupted\n";
            return null;
        }
        
        echo "[DEBUG] Attempting to parse envelope. Payload length: $length bytes\n";
        echo "[DEBUG] First 20 bytes (hex): " . bin2hex(substr($binary, 0, min(20, $length))) . "\n";
        echo "[DEBUG] Is binary data: " . (ctype_print($binary) ? 'no' : 'yes') . "\n";
        
        try { 
            // Suppress PHP warnings during protobuf parsing
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
            
            $env = new ServiceEnvelope(); 
            $env->mergeFromString($binary); 
            
            // Restore error reporting
            error_reporting($oldErrorReporting);
            
            echo "[DEBUG] Successfully parsed ServiceEnvelope\n";
            echo "[DEBUG] Channel ID: " . $env->getChannelId() . "\n";
            echo "[DEBUG] Gateway ID: " . $env->getGatewayId() . "\n";
            echo "[DEBUG] Has packet: " . ($env->hasPacket() ? 'yes' : 'no') . "\n";
            return $env; 
        }
        catch (\Throwable $e) { 
            // Restore error reporting in case of exception
            error_reporting($oldErrorReporting);
            echo "[DEBUG] Failed to parse ServiceEnvelope: " . $e->getMessage() . "\n";
            echo "[DEBUG] Data hex dump: " . bin2hex(substr($binary, 0, 50)) . "\n";
            return null; 
        }
    }

    public function getDecodedData(ServiceEnvelope $env): ?array {
        try {
            $this->debug("*** ENTERING getDecodedData METHOD ***");
            
            $pkt = $env->getPacket();
            if (!$pkt instanceof MeshPacket) {
                $this->debug("No MeshPacket found in envelope");
                return null;
            }
            
            $this->debug("MeshPacket found successfully");
            
            // Try to decode the packet data
            $decoded = $pkt->getDecoded();
            if ($decoded !== null) {
                $this->debug("Found unencrypted decoded data - port: " . $decoded->getPortnum());
                return [$decoded, $pkt]; // Return in expected format
            }
            
            if (!$pkt->hasEncrypted()) {
                $this->debug("Packet has no decoded or encrypted payload");
                return null;
            }

            // If no decoded data, try to decrypt encrypted Data payload using Meshtastic nonce layout.
            $this->debug("No decoded data found, attempting Meshtastic decryption");
            $decryptedData = $this->decryptPacketData($env, $pkt);
            if ($decryptedData !== null) {
                $this->debug("Decryption successful for channel: " . (string) $env->getChannelId());
                return [$decryptedData, $pkt];
            }
            
            $this->debug("All decoding attempts failed");
            return null;
            
        } catch (\Throwable $e) {
            $this->debug("Exception in getDecodedData: " . $e->getMessage());
            return null;
        }
    }

    private function decryptPacketData(ServiceEnvelope $env, MeshPacket $pkt): ?Data
    {
        try {
            $channelId = (string) $env->getChannelId();
            $ciphertext = $pkt->getEncrypted();
            if ($ciphertext === '') {
                $this->debug("No encrypted payload to decrypt");
                return null;
            }

            $key = $this->getChannelKey($channelId);
            $keyLen = strlen($key);
            $cipher = match ($keyLen) {
                16 => 'aes-128-ctr',
                24 => 'aes-192-ctr',
                32 => 'aes-256-ctr',
                default => null,
            };

            if ($cipher === null) {
                $this->debug("Unsupported key length for decryption: $keyLen bytes");
                return null;
            }

            // Meshtastic nonce format: [id(le32), 0, from(le32), 0].
            $nonce = pack('V', $pkt->getId()) . pack('V', 0) . pack('V', $pkt->getFrom()) . pack('V', 0);
            $plaintext = @openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $nonce);
            if ($plaintext === false || $plaintext === '') {
                $this->debug("OpenSSL decryption failed for channel '$channelId'");
                return null;
            }

            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
            try {
                $data = new Data();
                $data->mergeFromString($plaintext);
            } finally {
                error_reporting($oldErrorReporting);
            }

            if ($data->getPortnum() <= 0 && $data->getPayload() === '') {
                $this->debug("Decrypted payload did not parse into a valid Data message");
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            $this->debug("Exception during packet decryption: " . $e->getMessage());
            return null;
        }
    }

    private function debug(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [DECODER] $message\n";
        echo $line;
        file_put_contents(__DIR__ . '/../debug.log', $line, FILE_APPEND);
    }

    public function parseUser(string $payload): ?User { 
        try { 
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
            $o = new User(); 
            $o->mergeFromString($payload); 
            error_reporting($oldErrorReporting);
            return $o; 
        } catch (\Throwable) { 
            error_reporting($oldErrorReporting ?? E_ALL);
            return null; 
        } 
    }
    
    public function parsePosition(string $payload): ?Position { 
        try { 
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
            $o = new Position(); 
            $o->mergeFromString($payload); 
            error_reporting($oldErrorReporting);
            return $o; 
        } catch (\Throwable) { 
            error_reporting($oldErrorReporting ?? E_ALL);
            return null; 
        } 
    }
    
    public function parseTelemetry(string $payload): ?Telemetry { 
        try { 
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
            $o = new Telemetry(); 
            $o->mergeFromString($payload); 
            error_reporting($oldErrorReporting);
            return $o; 
        } catch (\Throwable) { 
            error_reporting($oldErrorReporting ?? E_ALL);
            return null; 
        } 
    }
    
    public function parseNeighborInfo(string $payload): ?NeighborInfo { 
        try { 
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
            $o = new NeighborInfo(); 
            $o->mergeFromString($payload); 
            error_reporting($oldErrorReporting);
            return $o; 
        } catch (\Throwable) { 
            error_reporting($oldErrorReporting ?? E_ALL);
            return null; 
        } 
    }
    public function parseRouteDiscovery(string $payload): ?RouteDiscovery { try { $o=new RouteDiscovery(); $o->mergeFromString($payload); return $o; } catch (\Throwable) { return null; } }
    public function parseMapReport(string $payload): ?MapReport { try { $o=new MapReport(); $o->mergeFromString($payload); return $o; } catch (\Throwable) { return null; } }
}
