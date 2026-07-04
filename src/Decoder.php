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
    public const ATAK_FORWARDER_APP   = 257;    // ATAK forwarder    // Built-in default LongFast PSK (override with LONGFAST_B64_KEY if set).
    // Updated to 32 bytes for AES-256 compatibility
    private const DEFAULT_LONGFAST_PSK = "\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01";

    private function getLongFastKey(): string {
        $b64 = Env::get('LONGFAST_B64_KEY', '');
        
        // Try to get key from environment first
        if ($b64 !== '') {
            $raw = base64_decode($b64, true);
            if ($raw !== false) {
                // If it's a single byte, try different expansion methods
                if (strlen($raw) === 1) {
                    // The Meshtastic default channel uses 0x01, but this might mean:
                    // 1. A specific well-known key derived from this byte
                    // 2. A key derivation using this as a seed
                    
                    // Try method 1: Traditional repeat (what we were doing)
                    $key1 = str_repeat($raw, 16);
                    
                    // Try method 2: Common crypto padding with zeros  
                    $key2 = $raw . str_repeat("\0", 15);
                    
                    // Try method 3: The actual Meshtastic default (might be all 0x01)
                    if ($raw === "\x01") {
                        // Some implementations use this exact pattern for default
                        $key3 = "\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01";
                        // Or try all zeros (no encryption)
                        $key4 = str_repeat("\0", 16);
                    }
                    
                    // For now, let's log all methods and use the repeat method
                    $this->debug("Single byte PSK detected: " . bin2hex($raw));
                    $this->debug("Method 1 (repeat): " . bin2hex($key1));
                    $this->debug("Method 2 (zero-pad): " . bin2hex($key2));
                    
                    // Use the traditional method for now
                    return $key1;
                }
                // If it's already 16 or 32 bytes, use as-is
                if (strlen($raw) == 16 || strlen($raw) == 32) {
                    return $raw;
                }
            }
        }
        
        // Fall back to the default PSK
        return self::DEFAULT_LONGFAST_PSK;
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
            
            // If no decoded data, try to decrypt
            $this->debug("No decoded data found, attempting decryption");
            $this->debug("About to call trySimpleDecryption");
            $decrypted = $this->trySimpleDecryption($env, $pkt);
            $this->debug("trySimpleDecryption returned: " . ($decrypted ? "success" : "null"));
            if ($decrypted !== null) {
                // For now, create a mock Data object from decrypted content
                // This is a simplified approach - in real implementation we'd need to 
                // properly construct the Data object
                $this->debug("Decryption successful");
                return [$decrypted, $pkt];
            }
            
            $this->debug("All decoding attempts failed");
            return null;
            
        } catch (\Throwable $e) {
            $this->debug("Exception in getDecodedData: " . $e->getMessage());
            return null;
        }
    }

    private function trySimpleDecryption(ServiceEnvelope $env, MeshPacket $pkt): ?array {
        try {
            $this->debug("*** ENTERED trySimpleDecryption METHOD ***");
            $channelId = (string)$env->getChannelId();
            $this->debug("Attempting to decrypt channel: $channelId");
            
            // Only try LongFast for now (most common working case)
            if (strcasecmp($channelId, 'LongFast') !== 0) {
                $this->debug("Skipping non-LongFast channel for decryption");
                return null;
            }

            $ciphertext = $pkt->getEncrypted(); 
            if ($ciphertext === '') {
                $this->debug("Empty ciphertext");
                return null;
            }
            
            $cipherLength = strlen($ciphertext);
            if ($cipherLength < 16) {
                $this->debug("Ciphertext too short ($cipherLength bytes) - minimum 16 bytes required");
                return null;
            }
            
            $this->debug("Ciphertext length: $cipherLength bytes");
            
            $key = $this->getLongFastKey();
            if (strlen($key) !== 32) {
                $this->debug("Invalid key length: " . strlen($key) . " bytes - expected 32");
                return null;
            }
            
            $this->debug("Using key length: " . strlen($key) . " bytes");
            $this->debug("Key hex: " . bin2hex($key));
            
            // Try the most common IV patterns
            $this->debug("Trying decryption with packet ID: " . $pkt->getId() . ", from: " . $pkt->getFrom());
            $ivPatterns = [
                // Pattern 1: From + ID in big-endian + padding
                pack('N', $pkt->getFrom()) . pack('N', $pkt->getId()) . str_repeat("\0", 8),
                // Pattern 2: From + ID in little-endian + padding  
                pack('V', $pkt->getFrom()) . pack('V', $pkt->getId()) . str_repeat("\0", 8),
                // Pattern 3: All zeros (sometimes used for testing)
                str_repeat("\0", 16),
                // Pattern 4: Just packet ID
                pack('N', $pkt->getId()) . str_repeat("\0", 12),
                pack('V', $pkt->getId()) . str_repeat("\0", 12),
                // Pattern 5: Channel-based IV
                substr(hash('sha256', $channelId, true), 0, 16),
            ];

            foreach (['aes-256-ctr', 'aes-128-ctr'] as $cipher) {
                if (str_contains($cipher, '256') && strlen($key) != 32) continue;
                if (str_contains($cipher, '128') && strlen($key) != 16) continue;
                
                $this->debug("Trying cipher: $cipher");
                
                foreach ($ivPatterns as $ivIndex => $iv) {
                    $this->debug("Trying IV pattern $ivIndex: " . bin2hex(substr($iv, 0, 8)) . "...");
                    $pt = @openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                    if ($pt !== false && strlen($pt) > 0) {
                        $this->debug("Decryption produced " . strlen($pt) . " bytes: " . bin2hex(substr($pt, 0, 16)) . "...");
                        if ($this->looksLikeValidProtobuf($pt)) {
                            $this->debug("Output looks like valid protobuf");
                            try {
                                // Suppress warnings during protobuf parsing
                                $oldErrorReporting = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
                                
                                $data = new Data();
                                $data->mergeFromString($pt); 
                                
                                // Restore error reporting
                                error_reporting($oldErrorReporting);
                                
                                if ($data->getPortnum() > 0 && $data->getPortnum() < 1000) {
                                    $this->debug("Successfully decrypted with $cipher, IV pattern $ivIndex, port " . $data->getPortnum());
                                    return [$data, $pkt];
                                } else {
                                    $this->debug("Port number out of range: " . $data->getPortnum());
                                }
                            } catch (\Throwable $e) {
                                // Restore error reporting in case of exception
                                error_reporting($oldErrorReporting);
                                $this->debug("Failed to parse as Data protobuf: " . $e->getMessage());
                            }
                        } else {
                            $this->debug("Output doesn't look like valid protobuf");
                        }
                    } else {
                        $this->debug("Decryption failed or produced empty result");
                    }
                }
            }
            
            $this->debug("Decryption failed for channel $channelId");
            return null;
            
        } catch (\Throwable $e) {
            $this->debug("Exception during decryption: " . $e->getMessage());
            return null;
        }
    }

    private function looksLikeValidProtobuf(string $data): bool {
        $length = strlen($data);
        if ($length < 2) return false;
        
        // Additional validation for protobuf structure
        if ($length > 1000) return false; // Reasonable size limit
        
        $firstByte = ord($data[0]);
        // Protobuf field tags should be reasonable (1-15 for efficient encoding)
        if ($firstByte > 0x7F) return false; // Wire type + field number should be < 128 for common cases
        
        // Check if first few bytes look reasonable for protobuf field encoding
        $validBytes = 0;
        for ($i = 0; $i < min(5, $length); $i++) {
            $byte = ord($data[$i]);
            // Protobuf uses varint encoding, so high bit set is common but not universal
            if ($byte >= 0x08 && $byte <= 0x7F) $validBytes++; // Common protobuf field tag ranges
            if ($byte < 128) $validBytes++; // ASCII-range bytes are good indicators
        }
        
        return $validBytes >= 2; // At least 2 reasonable bytes
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
