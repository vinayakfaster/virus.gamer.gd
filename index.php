<?php
// ============================================================
//  Discord Auto Sender - Dashboard
//  Fill the form once → press Start. Each message opens a tab
//  (send.php) that sends it, shows the response, and closes.
// ============================================================
session_start();

// Save form values into the session when Start is clicked (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['sender_config'] = [
        'name'             => trim((string)($_POST['name'] ?? '')),
        'channelId'        => trim((string)($_POST['channelId'] ?? '')),
        'token'            => trim((string)($_POST['token'] ?? '')),
        'xSuperProperties' => trim((string)($_POST['xSuperProperties'] ?? '')),
        'installationId'   => trim((string)($_POST['installationId'] ?? '')),
        'maxMessages'      => max(1, (int)($_POST['maxMessages'] ?? 5)),
        'delayPool'        => trim((string)($_POST['delayPool'] ?? '')),
    ];
    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}

$cfg = $_SESSION['sender_config'] ?? [];
$defaults = [
    'name'             => $cfg['name'] ?? '',
    'channelId'        => $cfg['channelId'] ?? '',
    'token'            => $cfg['token'] ?? '',
    'xSuperProperties' => $cfg['xSuperProperties'] ?? '',
    'installationId'   => $cfg['installationId'] ?? '',
    'maxMessages'      => $cfg['maxMessages'] ?? 500,
    'delayPool'        => $cfg['delayPool'] ?? '30, 35, 36, 87, 45, 52, 96, 200, 300, 120, 15, 36, 10',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auto Sender Dashboard</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 24px; font-family: 'Segoe UI', system-ui, sans-serif;
        background: #1e1f22; color: #dbdee1; min-height: 100vh;
    }
    .wrap { max-width: 860px; margin: 0 auto; }
    .header {
        display: flex; align-items: center; justify-content: space-between;
        background: #2b2d31; border-radius: 12px; padding: 16px 20px; margin-bottom: 12px;
    }
    .header h1 { font-size: 18px; margin: 0; }
    .header h1 span { color: #57f287; }
    .pill {
        padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
        background: #3f4147; color: #b5bac1;
    }
    .pill.sending { background: #5865f2; color: #fff; }
    .pill.waiting { background: #ee7d3d; color: #fff; }
    .pill.done    { background: #57f287; color: #1e1f22; }

    .greet {
        background: linear-gradient(90deg, #101f16, #23a55a22);
        border: 1px solid #23a55a; border-radius: 12px; padding: 14px 18px;
        font-size: 18px; font-weight: 700; color: #57f287; margin-bottom: 16px;
    }

    .formcard {
        background: #2b2d31; border: 1px solid #3f4147; border-radius: 12px;
        padding: 18px; margin-bottom: 20px;
    }
    .formcard label { display: block; font-size: 12px; color: #b5bac1; margin-bottom: 12px; font-weight: 600; }
    .formcard input, .formcard textarea {
        width: 100%; margin-top: 6px; background: #111214; border: 1px solid #3f4147;
        border-radius: 8px; padding: 10px 12px; color: #dbdee1; font-size: 14px;
        font-family: inherit; outline: none;
    }
    .formcard input:focus, .formcard textarea:focus { border-color: #5865f2; }
    .formcard textarea { resize: vertical; font-family: Consolas, monospace; font-size: 12px; }
    .formrow { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .hint { font-size: 12px; color: #8a8f98; margin: -4px 0 12px; }

    .cards {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;
    }
    .card {
        background: #2b2d31; border-radius: 12px; padding: 14px 16px; text-align: center;
    }
    .card .num { font-size: 26px; font-weight: 700; }
    .card .lbl { font-size: 12px; color: #b5bac1; margin-top: 2px; }
    .card.sent .num { color: #57f287; }
    .card.rem  .num { color: #f0b232; }
    .card.tot  .num { color: #5865f2; }
    .card.del  .num { color: #ee7d3d; }
    .barwrap { background: #111214; border-radius: 999px; height: 12px; overflow: hidden; margin-bottom: 20px; }
    .bar { width: 0%; height: 100%; background: linear-gradient(90deg, #57f287, #23a55a); transition: width .4s; }
    .log {
        background: #111214; border: 1px solid #3f4147; border-radius: 12px;
        padding: 14px; height: 46vh; overflow-y: auto; font: 13px Consolas, monospace;
        white-space: pre-wrap; margin-bottom: 16px;
    }
    .logline { padding: 2px 0; }
    .logline .t { color: #b5bac1; }
    .logline.ok    { color: #57f287; }
    .logline.err   { color: #ed4245; }
    .logline.info  { color: #b5bac1; }
    .logline.wait  { color: #f0b232; }
    .btn {
        width: 100%; padding: 14px; border: none; border-radius: 12px; cursor: pointer;
        font-size: 16px; font-weight: 700; color: #fff; background: #5865f2; transition: .2s;
    }
    .btn:hover:not(:disabled) { background: #4752c4; }
    .btn:disabled { background: #3f4147; cursor: not-allowed; }
    .donebox {
        display: none; margin-top: 16px; text-align: center; background: #101f16;
        border: 1px solid #23a55a; border-radius: 12px; padding: 22px;
    }
    .donebox h2 { color: #57f287; margin: 0 0 6px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>⚡ Auto Sender <span>•</span> Dashboard</h1>
        <div id="status" class="pill">Ready</div>
    </div>

    <div class="greet" id="greeting">Hello, friend 👋</div>

    <!-- ===== CONFIG FORM ===== -->
    <div class="formcard">
        <div class="formrow">
            <label>Your Name
                <input id="name" type="text" placeholder="e.g. Trader" value="<?= htmlspecialchars($defaults['name']) ?>">
            </label>
            <label>Channel ID
                <input id="channelId" type="text" placeholder="e.g. 1525319977026981889" value="<?= htmlspecialchars($defaults['channelId']) ?>">
            </label>
        </div>
        <label>Token
            <input id="token" type="text" placeholder="Your Discord token" value="<?= htmlspecialchars($defaults['token']) ?>">
        </label>
        <label>x-super-properties
            <textarea id="xSuperProperties" rows="3" placeholder="Base64 x-super-properties"><?= htmlspecialchars($defaults['xSuperProperties']) ?></textarea>
        </label>
        <div class="formrow">
            <label>Installation ID
                <input id="installationId" type="text" value="<?= htmlspecialchars($defaults['installationId']) ?>">
            </label>
            <label>Max messages
                <input id="maxMessages" type="number" min="1" value="<?= (int)$defaults['maxMessages'] ?>">
            </label>
        </div>
        <label>Delay pool (seconds, comma separated)
            <input id="delayPool" type="text" value="<?= htmlspecialchars($defaults['delayPool']) ?>">
        </label>
        <div class="hint">Har message ke beech ka gap is list se randomly choose hota hai.</div>
        <button class="btn" id="startBtn">▶ Start sending</button>
    </div>

    <!-- ===== DASHBOARD ===== -->
    <div class="cards">
        <div class="card sent"><div class="num" id="sent">0</div><div class="lbl">Sent</div></div>
        <div class="card rem"><div class="num" id="remaining">0</div><div class="lbl">Remaining</div></div>
        <div class="card tot"><div class="num" id="total">0</div><div class="lbl">Total</div></div>
        <div class="card del"><div class="num" id="delay">—</div><div class="lbl">Next delay</div></div>
    </div>

    <div class="barwrap"><div class="bar" id="bar"></div></div>
    <div class="log" id="log"><div class="logline info">Upar form bharo, phir ▶ Start sending dabao.</div></div>

    <div class="donebox" id="doneBox">
        <h2>🎉 All done!</h2>
        <p id="doneText">All messages were sent successfully.</p>
        <button class="btn" id="againBtn" style="background:#23a55a">⟳ Run again (new random messages)</button>
    </div>

    <iframe id="frame" style="display:none"></iframe>
</div>

<script>
// ---- User list (random mentions) ----
const USERS = [
    "@70x3", "@Zayn", "@I", "@Amaan", "@Ponyal", "@A.Noxen_", "@AAU elite",
    "@Ab", "@Abu Thamer", "@AceerBlade", "@Adam", "@Adaption", "@ahari",
    "@AK", "@Akshat SG", "@Albedo", "@Alex", "@AlexGLX", "@alfred",
    "@raaaahhhhrhhh", "@RAMIM", "@Rana", "@RONALDO", "@Ryan Mitchell", "@SANJU",
    "@Shilpa Tamang", "@Shiv", "@THE_FALLEN(Z_Z)", "@Tired", "@Vicky", "@Cool",
    "@Wayne", "@INVISIBLE", "@Markets Team", "@Dictar", "@Server Booster", "@! AVE",
    "@dadaa_.a", "@Daddy connor", "@danny11", "@dcione", "@Ddtm_.jaehee",
    "@Dead_ppi", "@Deebo.Sr", "@DeeThree", "@DeFiDuchess", "@Dekid", "@depende",
    "@Dnd_demon", "@Dog", "@dogoking", "@Dream Balderas", "@dz01k", "@DaTk",
    "@HkGaming", "@hl", "@Hossain", "@HWOKOre", "@IceSpice69", "@idgg789",
    "@Idonotcare", "@InanimateBat", "@Isaac", "@Isaaccvr", "@Jack", "@Jacob",
    "@Jason", "@Jay", "@JET", "@!Aqua_sol", "@!SAAD", "@!ART Kung", "@!!Berlin",
    "@#SWAG.", "@$000", "@-ZAKY", "@AoTn", "@.growthmindset", "@00_miah",
    "@OnlyHer_onyx", "@HushOfLife", "@Kay", "@Legend88", "@LonelyRockstar", "@Zain",
    "@LUNA*)", "@BRUTALxZUDO", "@Chandrapal", "@Disha singh", "@Rohit jaat",
    "@Thanos", "@RAJA_FX", "@Arjun Sharma", "@ASH", "@Arohi", "@Asura",
    "@Doma", "@Hidim57", "@Maheem", "@NoAccount", "@PJ {CRSH MOD}", "@Rafey",
    "@Sami", "@Silence", "@SukunaBTC", "@Wild fire", "@0xNkems", "@Bandd",
    "@Prime__XR", "@Unknown", "@B3HIND_X", "@GOLU", "@Ben_PoOd", "@Berli",
    "@BERLIN", "@Bidzz", "@Big3", "@Binit", "@Bo", "@Bostom", "@Bottie",
    "@Brayan Desacalo", "@bunni", "@Candy!!", "@CaptainFEMA", "@Carl-bot",
    "@CHEN", "@Chris", "@Cloudy", "@Craze", "@daddy_mofo", "@diegovas",
    "@donshowzy", "@Dr.gymy", "@Fortizo", "@Jaxson", "@Ke", "@Krish ff",
    "@Mercy", "@monasex", "@Nostal Rimi", "@Parvez", "@Emlyns", "@Eternityyyy",
    "@extrafrontman", "@Faisal", "@Fat Hawk", "@Felii", "@Finn", "@fire",
    "@FI9246", "@FLEXY", "@Flowery", "@Fluxio", "@FrankSriracha", "@Frost.Kid.",
    "@gochi", "@GodHunter",
];

const DEFAULT_DELAYS = [30, 35, 36, 87, 45, 52, 96, 200, 300, 120, 15, 36, 10];

// ---- DOM refs ----
const logEl = document.getElementById('log');
const sentEl = document.getElementById('sent');
const remainEl = document.getElementById('remaining');
const totalEl = document.getElementById('total');
const delayEl = document.getElementById('delay');
const barEl = document.getElementById('bar');
const statusEl = document.getElementById('status');
const btn = document.getElementById('startBtn');
const greetingEl = document.getElementById('greeting');

// Live greeting as you type your name
document.getElementById('name').addEventListener('input', (e) => {
    const v = e.target.value.trim();
    greetingEl.textContent = v ? 'Hello, ' + v + ' 👋' : 'Hello, Hacker 👋';
});

// ---- Random helpers + message builder (same logic as before, in JS) ----
const pick = (a) => a[Math.floor(Math.random() * a.length)];
function buildMessage() {
    const openers      = ["Hey", "Yo", "What's up", "Hi", "Hello", "Good morning", "Good evening"];
    const topics       = ["Ceshmarket", "the market", "this platform", "that site", "the trading",
                          "prices", "the current deals", "the offers", "the trades", "the new listings",
                          "the market trends", "the community", "the platform's features", "what's going on here"];
    const questions    = ["what's happening with", "how's it going with", "any news on", "what's the latest on",
                          "anyone know about", "have you seen the updates for", "what are your thoughts on",
                          "is it still active", "how's the trading looking for", "any good deals on",
                          "what's the status of", "can someone explain", "any tips for"];
    const observations = ["Just saw something interesting on", "Looking at", "Checking out", "Thinking about",
                          "Curious about", "Trying to figure out", "Wondering about"];
    const casual       = ["Just chilling here", "Taking a break", "Browsing around",
                          "Hope everyone's having a good day", "What is everyone up to?",
                          "Anything exciting happening?", "Just checking in", "How's your day going?"];
    const reactions    = ["That's pretty wild", "Interesting!", "Wow, really?", "Makes sense", "Good point",
                          "I see", "Hmm, let me check", "Noted", "Got it", "Cool"];

    const mention = (Math.floor(Math.random() * 3) === 0) ? pick(USERS) : '';

    let parts = [];
    switch (Math.floor(Math.random() * 7) + 1) {
        case 1: parts = [pick(openers), pick(questions), pick(topics)]; break;
        case 2: parts = [pick(observations), pick(topics)]; break;
        case 3: parts = [pick(casual)]; break;
        case 4: parts = [pick(reactions), pick(topics)]; break;
        default: parts = [pick(questions), pick(topics)]; break;
    }
    if (mention) parts.push(mention);
    return parts.join(' ').replace(/\s+/g, ' ').trim();
}

// Build a list of unique messages (with a safety guard)
function buildMessages(max) {
    const used = {}, out = [];
    let guard = 0;
    while (out.length < max && guard < max * 60) {
        guard++;
        const m = buildMessage();
        if (!used[m]) { used[m] = 1; out.push(m); }
    }
    return out;
}

function parseDelays(str) {
    const arr = String(str).split(',').map(s => parseInt(s.trim(), 10)).filter(n => Number.isFinite(n) && n > 0);
    return arr.length ? arr : DEFAULT_DELAYS;
}

// ---- UI helpers ----
function addLog(text, cls) {
    const d = document.createElement('div');
    d.className = 'logline ' + (cls || '');
    d.innerHTML = '<span class="t">[' + new Date().toLocaleTimeString() + ']</span> ' + text;
    logEl.appendChild(d);
    logEl.scrollTop = logEl.scrollHeight;
}
function setStatus(txt, cls) { statusEl.textContent = txt; statusEl.className = 'pill ' + (cls || ''); }
function updateStats(done, total) {
    sentEl.textContent = done;
    remainEl.textContent = total - done;
    barEl.style.width = Math.round(done / total * 100) + '%';
}
function updateDelay(s) { delayEl.textContent = s > 0 ? s + 's' : '—'; }

// Resolves when send.php signals "done" for message #i
function waitForSend(i) {
    return new Promise((resolve) => {
        let finished = false;
        const onMsg = (e) => {
            if (e.data && e.data.type === 'sendDone' && e.data.i === i) { cleanup(); resolve(true); }
        };
        const timeout = setTimeout(() => { cleanup(); resolve(false); }, 20000);
        function cleanup() { if (!finished) { finished = true; window.removeEventListener('message', onMsg); clearTimeout(timeout); } }
        window.addEventListener('message', onMsg);
    });
}

// Live countdown between messages
function countdown(secs) {
    return new Promise((resolve) => {
        let left = secs;
        updateDelay(left);
        addLog('⏳ Waiting <b>' + left + 's</b> before next message…', 'wait');
        const t = setInterval(() => {
            left--;
            updateDelay(left);
            if (left <= 0) { clearInterval(t); updateDelay(0); resolve(); }
        }, 1000);
    });
}

// Save form values to the PHP session (so send.php sees them too)
async function saveConfig(cfg) {
    const fd = new FormData();
    fd.append('ajax', '1');
    Object.keys(cfg).forEach(k => fd.append(k, cfg[k]));
    const r = await fetch('index.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (!j.ok) throw new Error('Config save failed');
}

async function run() {
    const name = document.getElementById('name').value.trim();
    const channelId = document.getElementById('channelId').value.trim();
    const token = document.getElementById('token').value.trim();
    const xSuper = document.getElementById('xSuperProperties').value.trim();
    const install = document.getElementById('installationId').value.trim();
    const max = Math.max(1, parseInt(document.getElementById('maxMessages').value, 10) || 1);
    const pool = parseDelays(document.getElementById('delayPool').value);

    if (!channelId || !token || !xSuper || !install) {
        alert('Pehle Channel ID, Token, x-super-properties aur Installation ID bharo!');
        return;
    }

    const cfg = { name, channelId, token, xSuperProperties: xSuper, installationId: install, maxMessages: max, delayPool: document.getElementById('delayPool').value };
    try { localStorage.setItem('senderConfig', JSON.stringify(cfg)); } catch (e) {}
    await saveConfig(cfg);

    const MESSAGES = buildMessages(max);
    const DELAYS = [];
    for (let k = 0; k < MESSAGES.length - 1; k++) DELAYS.push(pick(pool));
    const TOTAL = MESSAGES.length;
    totalEl.textContent = TOTAL;

    btn.disabled = true;
    addLog('🚀 Run started — ' + TOTAL + ' message(s) to send.', 'info');
    for (let i = 0; i < TOTAL; i++) {
        const n = i + 1;
        setStatus('Sending #' + n + ' of ' + TOTAL + '…', 'sending');
        addLog('Opening tab to send: <b>' + MESSAGES[i] + '</b>', 'info');

        const url = 'send.php?i=' + n + '&msg=' + encodeURIComponent(MESSAGES[i]);
        let w = null;
        try { w = window.open(url, '_blank'); } catch (e) { w = null; }
        if (!w) {
            document.getElementById('frame').src = url;
            addLog('(popup blocked by browser — sent in the background instead)', 'wait');
        }

        const ok = await waitForSend(n);
        updateStats(n, TOTAL);
        addLog(ok ? '✅ Message #' + n + ' sent.' : '⚠️ Message #' + n + ' finished (no signal).', ok ? 'ok' : 'err');

        if (i < TOTAL - 1) {
            setStatus('Paused before #' + (n + 1) + '…', 'waiting');
            await countdown(DELAYS[i]);
        }
    }
    setStatus('All done ✔', 'done');
    document.getElementById('doneText').textContent = 'All ' + TOTAL + ' messages were sent successfully.';
    document.getElementById('doneBox').style.display = 'block';
    addLog('===== ALL ' + TOTAL + ' MESSAGES SENT SUCCESSFULLY =====', 'ok');
}

btn.addEventListener('click', run);
document.getElementById('againBtn').addEventListener('click', () => location.reload());

// Prefill form from last saved values (localStorage)
try {
    const s = JSON.parse(localStorage.getItem('senderConfig') || '{}');
    if (s.name) document.getElementById('name').value = s.name;
    if (s.channelId) document.getElementById('channelId').value = s.channelId;
    if (s.token) document.getElementById('token').value = s.token;
    if (s.xSuperProperties) document.getElementById('xSuperProperties').value = s.xSuperProperties;
    if (s.installationId) document.getElementById('installationId').value = s.installationId;
    if (s.maxMessages) document.getElementById('maxMessages').value = s.maxMessages;
    if (s.delayPool) document.getElementById('delayPool').value = s.delayPool;
    greetingEl.textContent = s.name ? 'Hello, ' + s.name + ' 👋' : 'Hello, friend 👋';
} catch (e) {}
</script>
</body>
</html>
