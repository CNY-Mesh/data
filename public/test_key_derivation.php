<?php
/**
 * Test Key Derivation
 */

require_once __DIR__ . '/../bootstrap.php';
use App\Support\Env;

echo "=== Key Derivation Test ===\n";

$b64 = Env::get('LONGFAST_B64_KEY', '');
echo "Environment LONGFAST_B64_KEY: '$b64'\n";

if ($b64 !== '') {
    $raw = base64_decode($b64, true);
    echo "Decoded raw key: " . bin2hex($raw) . " (" . strlen($raw) . " bytes)\n";
    
    if (strlen($raw) === 1) {
        $expanded = str_repeat($raw, 16);
        echo "Expanded to 16 bytes: " . bin2hex($expanded) . "\n";
    }
    
    // Test different expansion methods
    echo "\nAlternative key derivations:\n";
    echo "1. Pad with zeros: " . bin2hex($raw . str_repeat("\0", 15)) . "\n";
    echo "2. PKCS#7-style: " . bin2hex($raw . str_repeat(chr(15), 15)) . "\n";
    echo "3. Repeat pattern: " . bin2hex(str_repeat($raw, 16)) . "\n";
    
    // Test if this might be the actual issue
    echo "\nChecking if this is actually the Meshtastic 'default' vs 'none' key:\n";
    echo "All zeros (no encryption): " . bin2hex(str_repeat("\0", 16)) . "\n";
    echo "Single 0x01 byte: " . bin2hex($raw) . "\n";
}
