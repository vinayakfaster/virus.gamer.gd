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

$headers = [
    'Content-Type: application/json',
    'Authorization: ' . $token,
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36',
    'x-super-properties: ' . $xSuperProperties,
    'x-installation-id: ' . $installationId,
    'Origin: https://discord.com',
    'Referer: https://discord.com/channels/' . $channelId,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => json_encode(['content' => $msg]),
    CURLOPT_TIMEOUT        => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

$ok    = ($error === '' && $httpCode >= 200 && $httpCode < 300);
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
    .msg { background: #111214; border-radius: 8px; padding: 12px; font-size: 15px; }
    .raw { font: 11px Consolas, monospace; color: #9ca0a8; word-break: break-word; white-space: pre-wrap; }
    .note { color: #f0b232; font-size: 13px; text-align: center; margin-top: 18px; }
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
        <div class="raw"><?= htmlspecialchars($error !== '' ? 'cURL error: ' . $error : $short) ?></div>
    </div>

    <div class="note">Ye tab khud band ho jayega aur dashboard pe wapas le jayega…</div>
</div>

<script>
    // Dashboard ko batana ke ye message done ho gaya
    if (window.opener) { window.opener.postMessage({ type: 'sendDone', i: <?= $i ?> }, '*'); }
    if (window.parent && window.parent !== window) { window.parent.postMessage({ type: 'sendDone', i: <?= $i ?> }, '*'); }
    // Tab ko band karo
    setTimeout(() => { try { window.close(); } catch (e) {} }, 7000);
</script>
</body>
</html>
