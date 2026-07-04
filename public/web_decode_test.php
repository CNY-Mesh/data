<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$result = null;
$error = null;

if ($_POST) {
    try {
        $decoder = new \App\Decoder();
        $payload = $_POST['payload'] ?? '';
        $topic = $_POST['topic'] ?? '';
        $keyB64 = $_POST['key'] ?? 'AQ==';
        
        if ($payload) {
            // Convert hex to binary if needed
            if (ctype_xdigit($payload)) {
                $binaryPayload = hex2bin($payload);
            } else {
                $binaryPayload = base64_decode($payload);
            }
            
            if (!$binaryPayload) {
                throw new Exception("Invalid payload format - use hex or base64");
            }
            
            $result = [
                'payload_length' => strlen($binaryPayload),
                'first_20_hex' => bin2hex(substr($binaryPayload, 0, 20)),
                'envelope_parse' => false,
                'decryption_attempts' => []
            ];
            
            // Try parsing as ServiceEnvelope
            try {
                $env = $decoder->parseEnvelope($binaryPayload);
                if ($env) {
                    $result['envelope_parse'] = true;
                    $result['channel_id'] = $env->getChannelId();
                    $result['gateway_id'] = $env->getGatewayId();
                    $result['has_packet'] = $env->hasPacket();
                    
                    // Try to get decoded data
                    $decoded = $decoder->getDecodedData($env);
                    if ($decoded) {
                        [$data, $packet] = $decoded;
                        $result['decoded'] = [
                            'from' => $packet->getFrom(),
                            'to' => $packet->getTo(),
                            'port' => $data->getPortnum(),
                            'payload_length' => strlen($data->getPayload())
                        ];
                    }
                }
            } catch (Exception $e) {
                $result['envelope_error'] = $e->getMessage();
            }
            
            // Try direct decryption
            $key = base64_decode($keyB64);
            $ciphers = strlen($key) === 16 ? ['aes-128-ctr'] : ['aes-256-ctr', 'aes-128-ctr'];
            
            foreach ($ciphers as $cipher) {
                $ivPatterns = [
                    str_repeat("\0", 16),
                    substr(hash('md5', $topic, true), 0, 16),
                    substr($binaryPayload, 0, 16),
                    substr($binaryPayload, -16)
                ];
                
                foreach ($ivPatterns as $ivIndex => $iv) {
                    $decrypted = @openssl_decrypt($binaryPayload, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                    
                    if ($decrypted !== false && strlen($decrypted) > 0) {
                        $attempt = [
                            'cipher' => $cipher,
                            'iv_pattern' => $ivIndex,
                            'decrypted_length' => strlen($decrypted),
                            'first_20_hex' => bin2hex(substr($decrypted, 0, 20))
                        ];
                        
                        // Try parsing as Data protobuf
                        try {
                            $data = new \Meshtastic\Data();
                            $data->mergeFromString($decrypted);
                            $attempt['protobuf_success'] = true;
                            $attempt['port'] = $data->getPortnum();
                            $attempt['payload_length'] = strlen($data->getPayload());
                        } catch (Exception $e) {
                            $attempt['protobuf_error'] = $e->getMessage();
                        }
                        
                        $result['decryption_attempts'][] = $attempt;
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>MQTT Message Decoder</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h2 { margin-top: 0; color: #333; }
        textarea { width: 100%; height: 100px; font-family: monospace; }
        input[type="text"] { width: 100%; padding: 8px; margin: 5px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .result { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0; }
        .success { border-color: #28a745; background: #d4edda; }
        .error { border-color: #dc3545; background: #f8d7da; }
        .warning { border-color: #ffc107; background: #fff3cd; }
        pre { background: #333; color: #00ff00; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MQTT Message Decoder</h1>
        
        <div class="card">
            <h2>Test Message Decryption</h2>
            <form method="post">
                <div>
                    <label><strong>Payload (hex or base64):</strong></label>
                    <textarea name="payload" placeholder="Enter message payload as hex or base64..."><?= htmlspecialchars($_POST['payload'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label><strong>Topic:</strong></label>
                    <input type="text" name="topic" value="<?= htmlspecialchars($_POST['topic'] ?? 'msh/US/2/e/LongFast/!test') ?>" placeholder="msh/US/2/e/LongFast/!test">
                </div>
                
                <div>
                    <label><strong>Encryption Key (base64):</strong></label>
                    <input type="text" name="key" value="<?= htmlspecialchars($_POST['key'] ?? 'AQ==') ?>" placeholder="AQ==">
                </div>
                
                <button type="submit">Decode Message</button>
            </form>
        </div>

        <?php if ($error): ?>
        <div class="card">
            <div class="result error">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($result): ?>
        <div class="card">
            <h2>Decoding Results</h2>
            
            <div class="result">
                <strong>Payload Analysis:</strong><br>
                Length: <?= $result['payload_length'] ?> bytes<br>
                First 20 bytes: <code><?= $result['first_20_hex'] ?></code>
            </div>

            <div class="result <?= $result['envelope_parse'] ? 'success' : 'warning' ?>">
                <strong>ServiceEnvelope Parsing:</strong> 
                <?= $result['envelope_parse'] ? 'SUCCESS' : 'FAILED' ?><br>
                
                <?php if ($result['envelope_parse']): ?>
                    Channel ID: <?= htmlspecialchars($result['channel_id']) ?><br>
                    Gateway ID: <?= htmlspecialchars($result['gateway_id']) ?><br>
                    Has Packet: <?= $result['has_packet'] ? 'Yes' : 'No' ?><br>
                    
                    <?php if (isset($result['decoded'])): ?>
                    <strong>Decoded Data:</strong><br>
                    From: <?= $result['decoded']['from'] ?><br>
                    To: <?= $result['decoded']['to'] ?><br>
                    Port: <?= $result['decoded']['port'] ?><br>
                    Payload Length: <?= $result['decoded']['payload_length'] ?> bytes
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (isset($result['envelope_error'])): ?>
                Error: <?= htmlspecialchars($result['envelope_error']) ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($result['decryption_attempts'])): ?>
            <div class="result">
                <strong>Direct Decryption Attempts:</strong><br>
                <?php foreach ($result['decryption_attempts'] as $attempt): ?>
                <div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <strong>Cipher:</strong> <?= $attempt['cipher'] ?>, 
                    <strong>IV Pattern:</strong> <?= $attempt['iv_pattern'] ?><br>
                    <strong>Decrypted Length:</strong> <?= $attempt['decrypted_length'] ?> bytes<br>
                    <strong>First 20 bytes:</strong> <code><?= $attempt['first_20_hex'] ?></code><br>
                    
                    <?php if (isset($attempt['protobuf_success'])): ?>
                    <span style="color: green;">✓ Protobuf parsing succeeded!</span><br>
                    <strong>Port:</strong> <?= $attempt['port'] ?><br>
                    <strong>Payload Length:</strong> <?= $attempt['payload_length'] ?> bytes
                    <?php elseif (isset($attempt['protobuf_error'])): ?>
                    <span style="color: red;">✗ Protobuf parsing failed:</span> <?= htmlspecialchars($attempt['protobuf_error']) ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="result warning">
                <strong>Direct Decryption:</strong> No successful decryption attempts
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Recent Encrypted Messages (for testing)</h2>
            <p>You can copy payloads from recent encrypted messages to test decryption:</p>
            <?php
            try {
                $db = new \App\Database();
                $pdo = $db->pdo();
                $stmt = $pdo->query("
                    SELECT topic, raw_payload, created_at
                    FROM raw_messages 
                    WHERE topic LIKE '%/e/%' 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($messages as $msg): ?>
                <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                    <strong>Topic:</strong> <?= htmlspecialchars($msg['topic']) ?><br>
                    <strong>Time:</strong> <?= $msg['created_at'] ?><br>
                    <strong>Payload (first 100 chars):</strong><br>
                    <code style="word-break: break-all;"><?= htmlspecialchars(substr(bin2hex($msg['raw_payload']), 0, 100)) ?>...</code>
                </div>
                <?php endforeach;
            } catch (Exception $e) {
                echo "<p>Error loading messages: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>
