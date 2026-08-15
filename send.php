<?php
// ============================================================
//  send.php - sends ONE message using the config saved on
//  index.php (session). Shows the response, then closes.
//  Called by index.php: send.php?i=1&msg=Hello%20world
// ============================================================
session_start();
$cfg = $_SESSION['sender_config'] ?? null;
if (!$cfg) {
    http_response_code(400);
    die('Config nahi mili. Pehle index.php kholo aur ▶ Start sending dabao.');
}

$channelId        = $cfg['channelId'];
$token            = $cfg['token'];
$xSuperProperties = $cfg['xSuperProperties'];
$installationId   = $cfg['installationId'];
$name             = $cfg['name'] ?? '';

$i   = (int)($_GET['i'] ?? 1);
$msg = trim((string)($_GET['msg'] ?? 'hello'));
$msg = mb_substr($msg, 0, 1900);

$url = "https://discord.com/api/v9/channels/{$channelId}/messages";

// Complete headers - mimicking a real browser
$headers = [
    'Accept: */*',
    'Accept-Encoding: */*',
    'Accept-Language: en-US,en;q=0.9',
    'Authorization: ' . $token,
    'Content-Type: application/json',
    'Origin: https://discord.com',
    'Referer: https://discord.com/channels/'.$channelId.'/',
    'Sec-Ch-Ua: "Not=A?Brand";v="99", "Chromium";v="130", "Google Chrome";v="130"',
    'Sec-Ch-Ua-Mobile: ?0',
    'Sec-Ch-Ua-Platform: "Windows"',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-origin',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    'X-Context-Properties: eyJsb2NhdGlvbiI6ImNoYXRfaW5wdXQifQ==',
    'X-Debug-Options: bugReporterEnabled',
    'X-Discord-Locale: en-US',
    'X-Discord-Timezone: America/New_York',
    'x-super-properties: ' . $xSuperProperties,
    'x-installation-id: ' . $installationId,
];

// Function to send with retry logic
function sendWithRetry($url, $headers, $payload, $maxRetries = 3) {
    $attempt = 0;
    $httpCode = 0;
    $response = '';
    
    while ($attempt < $maxRetries) {
        $ch = curl_init($url);
        
        // Create a temporary cookie jar
        $cookieJar = tempnam(sys_get_temp_dir(), 'discord_cookies_');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clean up temp file
        if (file_exists($cookieJar)) {
            unlink($cookieJar);
        }

        // If rate limited, wait and retry
        if ($httpCode === 429) {
            $data = json_decode($response, true);
            $retryAfter = ($data['retry_after'] ?? 5) + rand(1, 5);
            sleep($retryAfter);
            $attempt++;
            continue;
        }
        
        // If success or non-retryable error, return
        if ($httpCode !== 429 && $httpCode !== 503 && $httpCode !== 504) {
            break;
        }
        
        $attempt++;
        sleep(2); // Wait before retry
    }
    
    return ['code' => $httpCode, 'response' => $response];
}

// Handle Discord API errors
function handleDiscordError($response, $httpCode) {
    $data = json_decode($response, true);
    
    switch ($httpCode) {
        case 429:
            $retryAfter = $data['retry_after'] ?? 5;
            return "⚠️ Rate limited! Wait {$retryAfter} seconds.";
        case 401:
            return "❌ Invalid token! Check your authorization.";
        case 403:
            return "🚫 Forbidden! You don't have permission to send messages here.";
        case 404:
            return "🔍 Channel not found! Check the channel ID.";
        case 400:
            return "⚠️ Bad request! Check your message content.";
        default:
            return $data['message'] ?? 'Unknown error';
    }
}

// Send the message with retry logic
$payload = ['content' => $msg];
$result = sendWithRetry($url, $headers, $payload);
$httpCode = $result['code'];
$response = $result['response'];
$error = '';

// Check if we got an error
if ($httpCode >= 400) {
    $error = handleDiscordError($response, $httpCode);
}

$ok = ($httpCode >= 200 && $httpCode < 300);
$short = $response !== false ? substr($response, 0, 400) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Message #<?= $i ?> — Result</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 28px; font-family: 'Segoe UI', system-ui, sans-serif;
        background: #1e1f22; color: #dbdee1;
    }
    .wrap { max-width: 620px; margin: 0 auto; }
    .top {
        display: flex; justify-content: space-between; align-items: center;
        background: #2b2d31; border-radius: 12px; padding: 10px 16px; margin-bottom: 16px;
        font-size: 13px; color: #b5bac1;
    }
    .top b { color: #57f287; }
    .badge {
        display: inline-block; padding: 6px 14px; border-radius: 999px;
        font-weight: 700; font-size: 14px; margin-bottom: 16px;
    }
    .badge.ok { background: #57f287; color: #1e1f22; }
    .badge.fail { background: #ed4245; color: #fff; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .sub { color: #b5bac1; font-size: 13px; margin-bottom: 20px; }
    .box { background: #2b2d31; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
    .box .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #b5bac1; margin-bottom: 6px; }
    .msg { background: #111214; border-radius: 8px; padding: 12px; font-size: 15px; word-break: break-word; }
    .raw { font: 11px Consolas, monospace; color: #9ca0a8; word-break: break-word; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
    .note { color: #f0b232; font-size: 13px; text-align: center; margin-top: 18px; }
    .error-msg { color: #ed4245; background: #1a0a0a; padding: 8px 12px; border-radius: 6px; }
    .success-msg { color: #57f287; background: #0a1a0a; padding: 8px 12px; border-radius: 6px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <span>Sending as <b><?= htmlspecialchars($name !== '' ? $name : 'unknown') ?></b></span>
        <span>Channel <b><?= htmlspecialchars($channelId) ?></b></span>
    </div>

    <span class="badge <?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✔ SUCCESS' : '✖ FAILED' ?> — HTTP <?= $httpCode ?></span>
    <h1>Message #<?= $i ?></h1>
    <div class="sub">Sent at <?= date('H:i:s') ?></div>

    <div class="box">
        <div class="lbl">Message sent</div>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    </div>

    <div class="box">
        <div class="lbl">Response from server</div>
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($ok): ?>
            <div class="success-msg">✅ Message delivered successfully</div>
        <?php endif; ?>
        <div class="raw" style="margin-top: 8px;"><?= htmlspecialchars($short) ?></div>
    </div>

    <div class="note">⚠️ This tab will close automatically in 7 seconds...</div>
</div>

<script>
    // Dashboard ko batana ke ye message done ho gaya
    if (window.opener) { 
        try {
            window.opener.postMessage({ type: 'sendDone', i: <?= $i ?> }, '*');
        } catch(e) {}
    }
    if (window.parent && window.parent !== window) { 
        try {
            window.parent.postMessage({ type: 'sendDone', i: <?= $i ?> }, '*');
        } catch(e) {}
    }
    // Tab ko band karo
    setTimeout(() => { 
        try { 
            window.close(); 
        } catch (e) {
            // If can't close, show a message
            document.body.innerHTML += '<div style="text-align:center;margin-top:20px;color:#b5bac1;">⚠️ Please close this tab manually.</div>';
        }
    }, 7000);
</script>
</body>
</html>
