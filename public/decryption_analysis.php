<?php
require_once '../bootstrap.php';

use App\Database;

$db = new Database('sqlite:../data/meshtastic.sqlite');
$pdo = $db->pdo();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Decryption Failure Analysis - Meshtastic Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .problem-box { background: #ffe6e6; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #d32f2f; }
        .solution-box { background: #e8f5e8; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #4caf50; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: #e8f4f8; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
        .stat-label { color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .channel-unknown { background: #ffe6e6; }
        .channel-longfast { background: #e8f5e8; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Decryption Failure Analysis</h1>
        
        <?php
        // Analyze the current situation
        $totalEncrypted = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE topic LIKE '%/e/%'")->fetchColumn();
        $recentEncrypted = $pdo->query("SELECT COUNT(*) FROM raw_messages WHERE topic LIKE '%/e/%' AND created_at > datetime('now', '-1 hour')")->fetchColumn();
        $channels = $pdo->query("SELECT channel, COUNT(*) as count FROM raw_messages WHERE topic LIKE '%/e/%' GROUP BY channel ORDER BY count DESC")->fetchAll();
        ?>

        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($totalEncrypted); ?></div>
                <div class="stat-label">Total Encrypted Messages</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($recentEncrypted); ?></div>
                <div class="stat-label">Recent Encrypted (1 hour)</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count($channels); ?></div>
                <div class="stat-label">Different Channels</div>
            </div>
        </div>

        <div class="problem-box">
            <h3>🚨 Problem Analysis</h3>
            <p>Based on the debug logs and database analysis, here are the key issues preventing decryption:</p>
            <ul>
                <li><strong>Multiple Channel Types:</strong> We're seeing channels like TFamily, LongMod, ENMARC, MediumSlow - not just LongFast</li>
                <li><strong>Missing Decryption Logs:</strong> Debug logs show envelope parsing but no actual decryption attempts</li>
                <li><strong>Channel Filtering:</strong> Decoder was skipping non-LongFast channels</li>
                <li><strong>Key Management:</strong> We only have a LongFast key, but need keys for other channels</li>
            </ul>
        </div>

        <h2>📊 Channel Distribution</h2>
        <table>
            <tr>
                <th>Channel</th>
                <th>Message Count</th>
                <th>Percentage</th>
                <th>Decryption Status</th>
                <th>Required Action</th>
            </tr>
            <?php foreach ($channels as $channel): 
                $percentage = round(($channel['count'] / $totalEncrypted) * 100, 1);
                $isLongFast = strcasecmp($channel['channel'], 'LongFast') === 0;
                $cssClass = $isLongFast ? 'channel-longfast' : 'channel-unknown';
            ?>
            <tr class="<?php echo $cssClass; ?>">
                <td><strong><?php echo htmlspecialchars($channel['channel']); ?></strong></td>
                <td><?php echo number_format($channel['count']); ?></td>
                <td><?php echo $percentage; ?>%</td>
                <td><?php echo $isLongFast ? '✅ Key Available' : '❌ No Key'; ?></td>
                <td><?php echo $isLongFast ? 'Should work' : 'Need channel key'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="solution-box">
            <h3>🔧 Solutions Implemented</h3>
            <ol>
                <li><strong>Removed Channel Filtering:</strong> Modified decoder to attempt decryption on all channels</li>
                <li><strong>Enhanced IV Patterns:</strong> Added more IV construction patterns</li>
                <li><strong>Enabled /e/ Processing:</strong> Worker now attempts to decrypt instead of skipping</li>
            </ol>
        </div>

        <h2>🔑 Key Management Analysis</h2>
        <div style="background: #fff3cd; padding: 15px; border-radius: 6px;">
            <h4>Current Key Configuration:</h4>
            <ul>
                <li><strong>LongFast Key:</strong> ✅ Available (from LONGFAST_B64_KEY or default)</li>
                <?php foreach ($channels as $channel): 
                    if (strcasecmp($channel['channel'], 'LongFast') !== 0): ?>
                <li><strong><?php echo htmlspecialchars($channel['channel']); ?> Key:</strong> ❌ Missing (<?php echo $channel['count']; ?> messages need this key)</li>
                <?php endif; endforeach; ?>
            </ul>
            
            <h4>Why Decryption Fails:</h4>
            <ul>
                <li>Each Meshtastic channel has its own encryption key</li>
                <li>We only have the LongFast key (public default or configured)</li>
                <li>Private channels like TFamily, ENMARC require their specific keys</li>
                <li>Without the correct key, decryption produces garbage data</li>
            </ul>
        </div>

        <h2>📋 Recommended Next Steps</h2>
        <div style="background: #e8f4f8; padding: 15px; border-radius: 6px;">
            <h4>Immediate Actions:</h4>
            <ol>
                <li><strong>Restart Worker:</strong> Apply the decoder changes</li>
                <li><strong>Check LongFast Decryption:</strong> See if LongFast messages now decrypt</li>
                <li><strong>Monitor Debug Logs:</strong> Look for actual decryption attempt messages</li>
            </ol>
            
            <h4>To Get More Node Data:</h4>
            <ol>
                <li><strong>Focus on LongFast:</strong> <?php 
                    $longFastCount = 0;
                    foreach ($channels as $channel) {
                        if (strcasecmp($channel['channel'], 'LongFast') === 0) {
                            $longFastCount = $channel['count'];
                            break;
                        }
                    }
                    echo $longFastCount ? number_format($longFastCount) . ' messages' : 'No messages found';
                ?> should be decryptable</li>
                <li><strong>Get Channel Keys:</strong> Contact operators for private channel keys</li>
                <li><strong>Alternative Sources:</strong> Look for unencrypted node announcements</li>
            </ol>
        </div>

        <h2>🔬 Technical Details</h2>
        <div style="font-family: monospace; background: #f8f9fa; padding: 15px; border-radius: 6px;">
            <strong>Meshtastic Encryption:</strong><br>
            • Each channel has a unique AES key<br>
            • LongFast = Public default channel (known key)<br>
            • Private channels = Custom keys set by operators<br>
            • NodeInfo packets often on private channels<br>
            • Position data usually on public channels<br>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="debug_logs.php" style="margin: 10px; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 4px;">View Debug Logs</a>
            <a href="restart_worker.php" style="margin: 10px; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;">Restart Worker</a>
            <a href="?r=dashboard" style="margin: 10px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Dashboard</a>
        </div>
    </div>
</body>
</html>
