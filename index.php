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
        'maxMessages' => max(1, (int)($_POST['maxMessages'] ?? 5)),
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
    'maxMessages'      => $cfg['maxMessages'] ?? 5,
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
    .pill.sending { background: #5865f2; color: #fff; animation: pulse 1s infinite; }
    .pill.waiting { background: #ee7d3d; color: #fff; }
    .pill.done    { background: #57f287; color: #1e1f22; }
    .pill.error   { background: #ed4245; color: #fff; }
    .pill.stopped { background: #ed4245; color: #fff; }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.6; }
        100% { opacity: 1; }
    }

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
    .logline.stop  { color: #ed4245; font-weight: bold; }
    
    .btn-group {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }
    .btn-group .btn {
        flex: 1;
    }
    
    .btn {
        padding: 14px; border: none; border-radius: 12px; cursor: pointer;
        font-size: 16px; font-weight: 700; color: #fff; transition: .2s;
    }
    .btn-start { background: #5865f2; }
    .btn-start:hover:not(:disabled) { background: #4752c4; }
    
    .btn-stop { background: #ed4245; }
    .btn-stop:hover:not(:disabled) { background: #c0353a; }
    
    .btn-reset { background: #23a55a; }
    .btn-reset:hover:not(:disabled) { background: #1a8a47; }
    
    .btn:disabled { background: #3f4147; cursor: not-allowed; opacity: 0.6; }
    
    .donebox {
        display: none; margin-top: 16px; text-align: center; background: #101f16;
        border: 1px solid #23a55a; border-radius: 12px; padding: 22px;
    }
    .donebox h2 { color: #57f287; margin: 0 0 6px; }
    
    .warning {
        background: #1a0f0a;
        border: 1px solid #ed4245;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        color: #f0b232;
        font-size: 13px;
    }
    
    @media (max-width: 600px) {
        .cards { grid-template-columns: 1fr 1fr; }
        .formrow { grid-template-columns: 1fr; }
        body { padding: 12px; }
        .btn-group { flex-direction: column; }
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>⚡ Auto Sender <span>•</span> Dashboard</h1>
        <div id="status" class="pill">🟢 Ready</div>
    </div>

    <div class="greet" id="greeting">Hello, friend 👋</div>
    
    <div class="warning">
        ⚠️ <strong>Disclaimer:</strong> This tool is for hacking purposes only
    </div>

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
        
        <div class="btn-group">
            <button class="btn btn-start" id="startBtn">▶ Start sending</button>
            <button class="btn btn-stop" id="stopBtn" disabled>⏹ Stop</button>
        </div>
    </div>

    <!-- ===== DASHBOARD ===== -->
    <div class="cards">
        <div class="card sent"><div class="num" id="sent">0</div><div class="lbl">Sent</div></div>
        <div class="card rem"><div class="num" id="remaining">0</div><div class="lbl">Remaining</div></div>
        <div class="card tot"><div class="num" id="total">0</div><div class="lbl">Total</div></div>
        <div class="card del"><div class="num" id="delay">—</div><div class="lbl">Next delay</div></div>
    </div>

    <div class="barwrap"><div class="bar" id="bar"></div></div>
    <div class="log" id="log"><div class="logline info">📝 Upar form bharo, phir ▶ Start sending dabao.</div></div>

    <div class="donebox" id="doneBox">
        <h2>🎉 All done!</h2>
        <p id="doneText">All messages were sent successfully.</p>
        <button class="btn btn-reset" id="againBtn">⟳ Run again (new random messages)</button>
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


const genuineMessages = [
     // ===== 1. INTRODUCTIONS & FIRST IMPRESSIONS (20) =====
    "Just joined CRSH Market today! Looks promising",
    "Hello everyone, new here! Loving the platform so far",
    "Hey guys, just discovered CRSH Market, anyone else new?",
    "First day on CRSH, excited to start predicting",
    "Just signed up for CRSH Market, the UI is clean",
    "Newbie here! Any tips for a beginner on CRSH?",
    "Hey everyone, been lurking for a while, finally joined",
    "Just made my first deposit on CRSH, let's go!",
    "Hello CRSH community! Excited to be here",
    "First impressions of CRSH Market are really good",
    "Just found this platform, looks interesting tbh",
    "Hey fam, new to CRSH, what should I know?",
    "Finally joined CRSH after hearing about it",
    "First day on the platform, loving the concept",
    "Hey all, new trader here, hope to learn from you",
    "Just discovered CRSH through a friend, looks solid",
    "Hello CRSH family! Ready to make some predictions",
    "New to the platform, the layout is impressive",
    "Just created my account, looking forward to this",
    "Hey everyone, excited to be part of this community",
    
    // ===== 2. PLATFORM OVERVIEW (30) =====
    "CRSH Market is a live prediction platform, simple concept",
    "You watch events, pick what you think will happen, see results",
    "Markets include streams, crypto, sports, IRL challenges, game shows",
    "CRSH supports multiple markets - crypto, sports, esports",
    "The live prediction feature is addictive! I'm hooked",
    "I watch crypto markets and predict on CRSH, great combo",
    "You can predict on streams, crypto, sports, IRL challenges",
    "The YES/NO predictions are simple but effective tbh",
    "CRSH is a prediction market for live events, basically",
    "Watch live events, join chat, place YES/NO predictions",
    "The platform is growing fast, lots of new features",
    "CRSH Market is a live prediction platform where you predict events",
    "You can watch streams, crypto charts, sports games and predict",
    "The concept is simple but execution is great",
    "CRSH is like a prediction market for anything live",
    "You can track your portfolio and see your performance",
    "The platform has rewards for active users, nice touch",
    "CRSH is building a whole ecosystem around predictions",
    "The live events make it super engaging",
    "You can predict on anything from crypto to esports",
    "The market variety is impressive on CRSH",
    "I love the real-time aspect of predictions",
    "CRSH is perfect for crypto traders who want more action",
    "The platform combines entertainment with earning potential",
    "You can watch events and trade predictions simultaneously",
    "The interface is clean and easy to navigate",
    "CRSH has a unique approach to prediction markets",
    "The events are updated regularly, always fresh content",
    "I like how you can track your prediction history",
    "The platform has great potential for growth",
    
    // ===== 3. HOW IT WORKS (25) =====
    "How it works - Pick A Live Market, Place Your Prediction, Multiply Returns",
    "First step: Pick a live market to predict on",
    "Second step: Place your YES or NO prediction",
    "Third step: Watch and see if you were right",
    "The 'How it Works' button in promo area explains everything",
    "Click 'View Ladder' to see all the details",
    "The help center has a great guide for beginners",
    "You pick what you think will happen, then wait for resolution",
    "The process is straightforward - pick, predict, profit",
    "You can choose from multiple live markets",
    "Each market has its own resolution time",
    "The ladder system shows your promo progress",
    "You can track your predictions in the portfolio",
    "The platform guides you through the process step by step",
    "It's literally pick, predict, and watch the outcome",
    "The tutorial makes it easy to understand",
    "You can see your prediction history anytime",
    "The dashboard shows all your active predictions",
    "Each market has clear rules and resolution criteria",
    "You can filter markets by type - crypto, sports, etc",
    "The platform makes it easy to get started",
    "You don't need any special skills to start predicting",
    "Just pick a market and make your prediction",
    "The rewards come from accurate predictions",
    "It's as simple as YES or NO on any event",
    
    // ===== 4. LADDER SYSTEM (40) =====
    "Still trying to understand the ladder system tbh",
    "The ladder playthrough explanation in help center is decent",
    "I love the new ladder system, so much better than before",
    "Wait so attached cash gets promoted when you win?",
    "The 1x bucket promotion to 0x unlocks the cash right?",
    "Has anyone actually completed a full ladder yet?",
    "The ladder system is confusing but rewarding once you get it",
    "If you bet $5 with promo in 9x bucket you win $15 total",
    "The ladder UI bug with '% remaining' is being fixed",
    "I like how you can promote part of the bucket now",
    "The 10x bucket promo system makes sense now",
    "I think winning promo should go to 10x bucket",
    "The promo playthrough system is unique",
    "Locked cash gets promoted when you win",
    "If you lose, you retain attached cash - that's good",
    "Only 10% of attached cash promotes per bet",
    "Before you had to promote entire stack, now partial",
    "Getting promo funds gives you their playthrough",
    "The 'View Ladder' button shows all rungs",
    "The ladder percentages are a UI bug only",
    "The $5 bet example in 9x bucket makes sense",
    "You get $5 promo back when you win",
    "Net $10 profit from $5 bet is solid",
    "Cash from 7x bucket gets promoted to 8x",
    "The 1x bucket has $2.50 in my example",
    "I need to study the ladder more",
    "The FAQ explains it well",
    "I wish I understood this earlier",
    "The ladder is just promo play, essentially",
    "$5 bet with promo in 9x bucket — you win $15 total",
    "The $5 you bet gets promoted from 9x to 8x",
    "You get $5 in cash from the promotion",
    "The attached cash gets promoted proportionally",
    "You can promote part of the bucket now, not all",
    "Before you had to promote the entire stack",
    "Now you only promote what you bet percentage-wise",
    "If there's $10 in a bucket and you bet $1, 10% promotes",
    "This is much better than the old system",
    "The ladder shows all rungs and their holdings",
    "Just click 'View Ladder' to see everything",
    
    // ===== 5. WITHDRAWALS & BALANCE (30) =====
    "Withdrawal minimum is now $50 right?",
    "Has anyone successfully withdrawn from CRSH?",
    "My balance wasn't showing but fixed after signing in",
    "The $100 min withdrawal was too high glad they lowered it",
    "CRSH is unprofitable with $50 withdrawals, respect!",
    "Still $0 withdrawals if you disagree with policy",
    "My trade was pending but got settled manually",
    "Ecosystem team is actually pretty responsive",
    "Anyone else having balance display issues?",
    "Balance does not show if you are not signed in",
    "During a trade, amount traded is reserved until settlement",
    "The $100 minimum withdraw was needed for profitability",
    "They turned it down to $50 starting Aug 15",
    "This makes them unprofitable but they listened to community",
    "There is still $0 withdrawals if you disagree",
    "The compromise was made to meet community halfway",
    "Withdrawals work smoothly once you meet the minimum",
    "I've had no issues withdrawing from CRSH",
    "The withdrawal process is straightforward",
    "You can withdraw in crypto, multiple options",
    "The team processes withdrawals regularly",
    "I've seen people share their withdrawal success stories",
    "The system reserves funds during active trades",
    "Balance updates in real-time after settlement",
    "You can check your balance on the dashboard",
    "The minimum withdrawal is now affordable for everyone",
    "They really listened to the community feedback",
    "I appreciate the transparency on withdrawals",
    "The withdrawal policy is clearly explained",
    "No hidden fees on withdrawals, that's good",
    
    // ===== 6. REWARDS & REFERRALS (25) =====
    "Message reward not received? They sent it manually",
    "The 1000 and 1500 message rewards went out",
    "51 eligible users got their reward confirmation",
    "Reward confirmation tx's were double-checked",
    "You can earn by referring friends to CRSH",
    "The referral program is pretty generous",
    "Share your referral link and earn rewards",
    "I got my first referral reward yesterday",
    "The message rewards are a nice bonus",
    "Active users get rewarded regularly",
    "You can earn rewards for community participation",
    "The rewards system is transparent",
    "I've earned multiple rewards on CRSH",
    "The team sends rewards with tx confirmation",
    "You can track your rewards in the dashboard",
    "Referral rewards are credited automatically",
    "The more you refer, the more you earn",
    "Message rewards are for active community members",
    "The reward system encourages participation",
    "I love getting rewards for being active",
    "The rewards make it worth using the platform",
    "You can earn passive income through referrals",
    "The referral link is easy to share",
    "I've referred 5 people already, got rewards",
    "The reward program is one of the best",
    
    // ===== 7. TEAM & TRANSPARENCY (25) =====
    "Founders actually listen to community feedback",
    "The team is transparent about everything",
    "Ecosystem team updated on top concerns",
    "The founders are in EST, they sleep normally lol",
    "I saw 'Founder silent' but they're just sleeping",
    "The team is human and needs rest too",
    "Ecosystem team is working on top concerns right now",
    "The watchdog cursor was stuck, they're fixing it",
    "Anything pending from past week will be manually repaired",
    "The team actually responds to concerns",
    "I respect the transparency from the founders",
    "They've delivered so far, I trust them",
    "The team is active in the community",
    "They listen to user feedback and make changes",
    "The founders are approachable and responsive",
    "The ecosystem team is on top of issues",
    "They're working hard to fix all concerns",
    "The team's communication is excellent",
    "I appreciate the regular updates from the team",
    "They're always improving the platform",
    "The team's dedication is visible",
    "They actually care about the community",
    "The transparency is refreshing",
    "The founders are involved in daily operations",
    "I've never seen such responsive founders",
    
    // ===== 8. TECHNICAL DETAILS (30) =====
    "Attached cash is stuck? It gets promoted on win",
    "Attached cash not reflecting in balance? It's reserved",
    "The attached cash system is actually smart",
    "You can't bet with locked cash directly",
    "Locked cash becomes unlocked when promoted",
    "Promoting from 1x to 0x unlocks the cash",
    "You have to win the bet to promote attached cash",
    "If there's $10 in a bucket and you bet $1, $5 promotes",
    "The promotion is proportional to bet amount",
    "Before, you had to promote the entire stack",
    "Now you can promote part of the bucket",
    "This system is much more flexible",
    "The promo funds give you their playthrough",
    "Before it would reset the playthrough",
    "Now you retain the playthrough from promo funds",
    "The technical improvements are impressive",
    "The UI bug with ladder percentages is being fixed",
    "The 'How it Works' button explains everything",
    "The help center has detailed explanations",
    "The platform is built on solid tech",
    "The settlement system is reliable",
    "Trades settle within reasonable time",
    "The reservation system during trades is smart",
    "You can track everything on the dashboard",
    "The technical architecture is well-designed",
    "The platform handles high volume well",
    "I've had no technical issues so far",
    "The site is fast and responsive",
    "The mobile experience is smooth",
    "Overall the tech is solid",
    
    // ===== 9. FEATURES (35) =====
    "The live prediction feature is addictive!",
    "I watch crypto markets and predict on CRSH",
    "You can predict on streams, crypto, sports, IRL challenges",
    "CRSH supports multiple markets - crypto, sports, esports",
    "The YES/NO predictions are simple but effective",
    "I like tracking my portfolio on CRSH",
    "The chat feature makes it more engaging",
    "You can share your referral link and earn",
    "Adding CRSH to home screen is easy",
    "The 'How it Works' section is helpful",
    "You can watch live streams and predict",
    "The crypto markets are real-time",
    "Sports predictions are available too",
    "Esports markets are getting popular",
    "IRL challenges are fun to predict on",
    "The portfolio tracker is comprehensive",
    "You can see your prediction history",
    "The dashboard shows all active markets",
    "You can filter markets by category",
    "The chat is active and engaging",
    "You can interact with other users",
    "The rewards section shows your earnings",
    "The referral section is easy to use",
    "You can add CRSH to home screen as an app",
    "The mobile experience is great",
    "The desktop version works well too",
    "You can track your performance",
    "The analytics show your win rate",
    "You can see your profit/loss",
    "The platform is feature-rich",
    "New features are added regularly",
    "The team is always innovating",
    "The features keep getting better",
    "I love the user interface",
    "Everything is well-organized",
    
    // ===== 10. COMMUNITY (20) =====
    "The community on CRSH is actually helpful",
    "The chat is active and engaging",
    "Everyone is friendly and supportive",
    "The community is growing fast",
    "I've made friends in the CRSH community",
    "People help each other understand the platform",
    "The mods are active and helpful",
    "The community feedback is taken seriously",
    "I love the community vibes",
    "The discussions are always interesting",
    "You can learn a lot from other users",
    "The community is diverse and active",
    "Everyone shares their strategies",
    "The chat is never boring",
    "People are genuinely excited about CRSH",
    "The community engagement is high",
    "I feel welcome in the community",
    "The support from other users is great",
    "The community is the heart of CRSH",
    "We're building something special together",
    
    // ===== 11. QUESTIONS & ANSWERS (40) =====
    "What's the best market to predict on?",
    "Can you withdraw instantly?",
    "How long do trades take to settle?",
    "Is there a limit on predictions?",
    "Can I use CRSH on mobile?",
    "What crypto chains does CRSH support?",
    "How do I get referral rewards?",
    "What are the message rewards?",
    "Is there a loyalty program?",
    "When do new markets get added?",
    "How does the ladder system work exactly?",
    "What is attached cash?",
    "How do I promote my cash?",
    "What's the minimum deposit?",
    "Can I use CRSH in my country?",
    "Is CRSH regulated?",
    "How do I contact support?",
    "What happens if my prediction is wrong?",
    "Can I cancel a prediction?",
    "How are markets resolved?",
    "What are the fees on CRSH?",
    "How do I add funds?",
    "What payment methods are accepted?",
    "Is there a mobile app?",
    "How do I verify my account?",
    "What is the KYC process?",
    "How do I change my password?",
    "Can I have multiple accounts?",
    "How do I delete my account?",
    "What is the withdrawal fee?",
    "How quickly are withdrawals processed?",
    "What happens to my funds if I lose?",
    "Can I recover lost funds?",
    "How do I report an issue?",
    "What is the response time for support?",
    "Are there any hidden fees?",
    "How do I check my balance?",
    "What is the maximum withdrawal?",
    "Can I withdraw in fiat?",
    "What is the loyalty program?",
    
    // ===== 12. USER EXPERIENCE (30) =====
    "I'm really enjoying the CRSH experience",
    "The platform is user-friendly",
    "The onboarding process was smooth",
    "I love the real-time updates",
    "The dashboard is intuitive",
    "The navigation is easy",
    "The design is modern and clean",
    "Everything loads quickly",
    "The mobile experience is great",
    "The notifications are helpful",
    "I like the dark mode option",
    "The charts are clear and readable",
    "The market data is accurate",
    "The resolution process is transparent",
    "I've had a great experience overall",
    "The platform exceeded my expectations",
    "I use CRSH every day now",
    "The interface is addictive",
    "I love checking the markets daily",
    "The experience keeps getting better",
    "The team is always improving things",
    "I appreciate the attention to detail",
    "The UX is top-notch",
    "Everything is well-designed",
    "The learning curve is gentle",
    "Even beginners can use it easily",
    "The advanced features are there for pros",
    "I like the balance of simplicity and depth",
    "The platform is enjoyable to use",
    "I look forward to using CRSH daily",
    
    // ===== 13. PREDICTIONS & STRATEGIES (25) =====
    "I always check the trends before predicting",
    "My strategy is to follow the momentum",
    "I like to predict on crypto markets mostly",
    "Sports predictions are easier for me",
    "I diversify my predictions",
    "I don't put all my eggs in one basket",
    "I use technical analysis for crypto",
    "I follow the news for sports predictions",
    "I've been pretty successful so far",
    "My win rate is around 60%",
    "I'm getting better at reading markets",
    "The key is to stay informed",
    "I always do my research first",
    "I never predict emotionally",
    "I follow a systematic approach",
    "I learned from my mistakes",
    "The more you predict, the better you get",
    "I watch multiple markets simultaneously",
    "I compare different markets before deciding",
    "I'm patient with my predictions",
    "I don't chase losses",
    "I set a budget and stick to it",
    "I take profits regularly",
    "I compound my winnings",
    "I'm always learning new strategies",
    
    // ===== 14. CRSH LINKS & INFO (15) =====
    "Official website: https://crshmarket.com/",
    "Follow them on Twitter: https://x.com/crshmarket",
    "Join the Telegram: https://t.me/+HGkL5uIEKVNkNTk1",
    "Instagram: https://www.instagram.com/crshmarket",
    "LinkedIn: https://www.linkedin.com/company/crshmarket",
    "Help Center: https://app.crshmarket.com/help-center",
    "Check out the official CRSH Market website",
    "Follow CRSH on Twitter for updates",
    "Join the Telegram community",
    "Instagram has great content",
    "LinkedIn for professional updates",
    "Help Center answers all questions",
    "All official links are available",
    "Check the website for announcements",
    "Follow them on social media for news",
    
    // ===== 15. COMPARISONS & OPINIONS (20) =====
    "CRSH is better than other prediction platforms I've used",
    "I've tried similar platforms but CRSH is the best",
    "The UX is way better than competitors",
    "CRSH has more variety than others",
    "The rewards are more generous here",
    "The community is more active than other platforms",
    "I prefer CRSH over traditional trading",
    "It's more engaging than other platforms",
    "The transparency is unmatched",
    "I've recommended CRSH over other platforms",
    "CRSH is definitely the leader in prediction markets",
    "Nothing else compares to CRSH",
    "I've converted from other platforms to CRSH",
    "The features are superior",
    "The team is more responsive than competitors",
    "CRSH is the most innovative platform",
    "The future looks bright for CRSH",
    "I'm bullish on CRSH compared to others",
    "CRSH has the best user experience",
    "I've been telling everyone about CRSH",
    
    // ===== 16. FUTURE OUTLOOK (15) =====
    "CRSH Market is going to be huge",
    "The platform has massive potential",
    "I see CRSH becoming the #1 prediction platform",
    "The growth has been incredible",
    "I'm excited for what's coming next",
    "The roadmap looks promising",
    "CRSH is just getting started",
    "The potential is limitless",
    "I'm investing my time and money in CRSH",
    "The future is bright for CRSH",
    "I can't wait for new features",
    "The team has big plans ahead",
    "CRSH will revolutionize prediction markets",
    "This is just the beginning",
    "I'm excited to be part of this journey",
    
    // ===== 17. RANDOM & MISCELLANEOUS (30) =====
    "This project actually has some decent fundamentals ngl",
    "The utility here is overlooked imo",
    "I trust the team, they've delivered so far",
    "The platform is underrated fr",
    "CRSH has some real potential",
    "This is actually a good project",
    "I'm bullish on CRSH Market",
    "The fundamentals are solid",
    "The team is consistent",
    "I'm impressed with the progress",
    "The community is growing rapidly",
    "The adoption is increasing daily",
    "I'm confident in CRSH's future",
    "The team is dedicated and hardworking",
    "The platform keeps getting better",
    "I've been supporting CRSH from the start",
    "The project has strong fundamentals",
    "The tokenomics make sense",
    "The team is transparent and accountable",
    "I'm holding my CRSH positions long-term",
    "The price action is looking good",
    "The volume is increasing steadily",
    "CRSH is gaining more recognition",
    "The partnerships are expanding",
    "The ecosystem is growing",
    "I'm proud to be part of this community",
    "The project has great vision",
    "The execution has been flawless",
    "CRSH is becoming mainstream",
    "The future looks bright for all of us",
    
    // ===== 18. SUPPORT & HELP (15) =====
    "If you need help, check the help center",
    "The support team is responsive",
    "You can submit a ticket for any issue",
    "The FAQ section covers most questions",
    "The community helps each other out",
    "Don't hesitate to ask for help",
    "The mods are helpful and friendly",
    "There's always someone to help in chat",
    "The help center has detailed guides",
    "You can reach support via email",
    "The team resolves issues quickly",
    "I've had good experiences with support",
    "The help system is well-organized",
    "You can find answers in the FAQ",
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
const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const greetingEl = document.getElementById('greeting');

// ---- Control flags ----
let isRunning = false;
let shouldStop = false;

// Live greeting as you type your name
document.getElementById('name').addEventListener('input', (e) => {
    const v = e.target.value.trim();
    greetingEl.textContent = v ? 'Hello, ' + v + ' 👋' : 'Hello, friend 👋';
});

// ---- Random helpers + message builder ----
const pick = (a) => a[Math.floor(Math.random() * a.length)];

function buildMessage() {
    const mention = (Math.floor(Math.random() * 4) === 0) ? pick(USERS) : '';
    let msg = pick(genuineMessages);
    if (mention) {
        const starters = ["hey", "yo", "", "what do you think", "hey"];
        msg = pick(starters) + " " + mention + " " + msg;
    }
    return msg.replace(/\s+/g, ' ').trim();
}

function buildMessages(count) {
    const messages = [];
    for (let i = 0; i < count; i++) {
        messages.push(buildMessage());
    }
    return messages;
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

function setStatus(txt, cls) { 
    statusEl.textContent = txt; 
    statusEl.className = 'pill ' + (cls || ''); 
}

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
            if (e.data && e.data.type === 'sendDone' && e.data.i === i) { 
                cleanup(); 
                resolve(true); 
            }
        };
        const timeout = setTimeout(() => { 
            cleanup(); 
            resolve(false); 
        }, 30000);
        
        function cleanup() { 
            if (!finished) { 
                finished = true; 
                window.removeEventListener('message', onMsg); 
                clearTimeout(timeout); 
            } 
        }
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
            if (left <= 0 || shouldStop) { 
                clearInterval(t); 
                updateDelay(0); 
                resolve(); 
            }
        }, 1000);
    });
}

// Save form values to the PHP session
async function saveConfig(cfg) {
    const fd = new FormData();
    fd.append('ajax', '1');
    Object.keys(cfg).forEach(k => fd.append(k, cfg[k]));
    const r = await fetch('index.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (!j.ok) throw new Error('Config save failed');
}

// ---- STOP function ----
function stopSending() {
    shouldStop = true;
    isRunning = false;
    setStatus('⏹ Stopped!', 'stopped');
    addLog('⏹ <b>STOPPED by user!</b>', 'stop');
    startBtn.disabled = false;
    startBtn.textContent = '▶ Start sending';
    stopBtn.disabled = true;
}

// ---- MAIN RUN function ----
async function run() {
    if (isRunning) return;
    
    const name = document.getElementById('name').value.trim();
    const channelId = document.getElementById('channelId').value.trim();
    const token = document.getElementById('token').value.trim();
    const xSuper = document.getElementById('xSuperProperties').value.trim();
    const install = document.getElementById('installationId').value.trim();
    
    // FIX: No max limit - direct value
    const max = Math.max(1, parseInt(document.getElementById('maxMessages').value, 10) || 1);
    const pool = parseDelays(document.getElementById('delayPool').value);

    if (!channelId || !token || !xSuper || !install) {
        alert('⚠️ Pehle Channel ID, Token, x-super-properties aur Installation ID bharo!');
        return;
    }

    // Warning for large number
    if (max > 500) {
        if (!confirm('⚠️ ' + max + ' messages bhejne mein ' + Math.round(max * 0.5) + ' minutes lag sakte hain. Kya aap sure hain?')) {
            return;
        }
    }

    // Reset stop flag
    shouldStop = false;
    isRunning = true;

    const cfg = { 
        name, 
        channelId, 
        token, 
        xSuperProperties: xSuper, 
        installationId: install, 
        maxMessages: max, 
        delayPool: document.getElementById('delayPool').value 
    };
    
    try { 
        localStorage.setItem('senderConfig', JSON.stringify(cfg)); 
    } catch (e) {}
    
    await saveConfig(cfg);

    const MESSAGES = buildMessages(max);
    const DELAYS = [];
    for (let k = 0; k < MESSAGES.length - 1; k++) DELAYS.push(pick(pool));
    const TOTAL = MESSAGES.length;
    totalEl.textContent = TOTAL;

    startBtn.disabled = true;
    startBtn.textContent = '⏳ Sending...';
    stopBtn.disabled = false;
    setStatus('Sending...', 'sending');
    addLog('🚀 Run started — ' + TOTAL + ' message(s) to send.', 'info');
    
    for (let i = 0; i < TOTAL; i++) {
        // Check if STOP was requested
        if (shouldStop) {
            addLog('⏹ Sending stopped by user at message #' + (i + 1), 'stop');
            break;
        }
        
        const n = i + 1;
        setStatus('📤 Sending #' + n + ' of ' + TOTAL + '…', 'sending');
        addLog('📤 Opening tab to send: <b>' + MESSAGES[i] + '</b>', 'info');

        const url = 'send.php?i=' + n + '&msg=' + encodeURIComponent(MESSAGES[i]);
        let w = null;
        try { 
            w = window.open(url, '_blank', 'width=650,height=600'); 
        } catch (e) { 
            w = null; 
        }
        if (!w) {
            document.getElementById('frame').src = url;
            addLog('📌 (popup blocked — sent in background)', 'wait');
        }

        const ok = await waitForSend(n);
        updateStats(n, TOTAL);
        addLog(ok ? '✅ Message #' + n + ' sent successfully.' : '⚠️ Message #' + n + ' finished (no response signal).', ok ? 'ok' : 'err');

        // Check stop flag before delay
        if (shouldStop) {
            addLog('⏹ Stopping before next message...', 'stop');
            break;
        }

        if (i < TOTAL - 1) {
            setStatus('⏳ Waiting before #' + (n + 1) + '…', 'waiting');
            await countdown(DELAYS[i]);
        }
    }
    
    isRunning = false;
    
    if (shouldStop) {
        setStatus('⏹ Stopped!', 'stopped');
        document.getElementById('doneText').textContent = 'Stopped by user after ' + sentEl.textContent + ' messages.';
        document.getElementById('doneBox').style.display = 'block';
        addLog('⏹ ===== STOPPED BY USER =====', 'stop');
    } else {
        setStatus('✅ All done!', 'done');
        document.getElementById('doneText').textContent = 'All ' + TOTAL + ' messages were sent successfully.';
        document.getElementById('doneBox').style.display = 'block';
        addLog('🎉 ===== ALL ' + TOTAL + ' MESSAGES SENT SUCCESSFULLY =====', 'ok');
    }
    
    startBtn.disabled = false;
    startBtn.textContent = '▶ Start sending';
    stopBtn.disabled = true;
}

// ---- Event Listeners ----
startBtn.addEventListener('click', run);

stopBtn.addEventListener('click', stopSending);

document.getElementById('againBtn').addEventListener('click', () => location.reload());

// Prefill form from last saved values
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

console.log('🟢 Discord Auto Sender loaded. Ready to send messages.');
</script>
</body>
</html>
