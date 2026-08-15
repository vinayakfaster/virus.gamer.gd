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
            <input id="token" type="password" placeholder="Your Discord token" value="<?= htmlspecialchars($defaults['token']) ?>">
        </label>
        <label>x-super-properties
            <textarea id="xSuperProperties" rows="3" placeholder="Base64 x-super-properties"><?= htmlspecialchars($defaults['xSuperProperties']) ?></textarea>
        </label>
        <div class="formrow">
            <label>Installation ID
                <input id="installationId" type="text" value="<?= htmlspecialchars($defaults['installationId']) ?>">
            </label>
            <label>Max messages
                <input id="maxMessages" type="number" min="1" max="100" value="<?= (int)$defaults['maxMessages'] ?>">
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
    "9k members completed ask them for the gift now",
    "hey whats up dude got nothing much just chillin",
    "It's not possible for every project",
    "Wow really, so you're a perp trader on forex right?",
    "hey yeah my buddy is a huge ab devilliers fan to",
    "What is the team's goal? 50,000 members on Discord",
    "That's super amazing",
    "Are they also give promo code",
    "hey whats up just chillin online nothing much going on",
    "just woke up, anything crazy happen overnight?",
    "so is the market looking bullish today or what",
    "anyone else stuck holding heavy bags right now",
    "gm everyone, hope we get a green day",
    "didn't expect that pump just now lol",
    "is the dev team active in here?",
    "what's the floor price on that drop",
    "yeah I agree with you on that",
    "sounds like a solid plan to me",
    "just took some profits, feeling good",
    "are we still waiting for the official announcement?",
    "bro the gas fees are crazy right now",
    "i missed the entry on that one, sadge",
    "how long until the next snapshot?",
    "this project actually has some decent fundamentals ngl",
    "wagmi, just hold and don't panic sell",
    "did anyone catch the AMA yesterday?",
    "looks like we are consolidating nicely on the 1h chart",
    "just bought a small bag, let's see what happens",
    "can someone explain the tokenomics to me?",
    "absolutely, the utility here is overlooked",
    "nah i think we dump first before going up",
    "why is the volume so low today?",
    "good morning folks, ready for the trading day",
    "that was a massive liquidation wick just now",
    "i trust the team, they've delivered so far",
    "any airdrop requirements I need to know about?",
    "didn't realize the staking APY was that high",
    "okay just DCA'd a bit more, we stay safe",
    "this chart looks super bullish ngl",
    "Is there an active airdrop going on right now?",
    "What are the requirements to qualify for the airdrop?",
    "Do I need to hold a minimum amount of tokens to be eligible?",
    "How do I connect my wallet to claim the airdrop?",
    "Is this airdrop available globally or region-locked?",
    "What's the snapshot date for the airdrop?",
    "Can I participate with multiple wallets?",
    "Do I need to complete any tasks like following Twitter or joining Discord?",
    "Is there a referral bonus for the airdrop?",
    "When will the airdrop tokens be distributed?",
    "What chain is this airdrop on?",
    "Do I need to stake tokens to get the airdrop?",
    "Is the airdrop confirmed or just rumored?",
    "How much are people getting from this airdrop on average?",
    "Has the team announced the total airdrop supply?",
    "Do I need to verify my identity for the airdrop?",
    "Is there a deadline to claim the airdrop?",
    "What happens if I miss the snapshot?",
    "Can I still join if I'm new to the project?",
    "Are there any gas fees involved in claiming?",
    "Is there a vesting period for the airdrop tokens?",
    "How do I check my airdrop allocation?",
    "Is this airdrop legit or a scam?",
    "Has the team done airdrops before?",
    "Do I need to hold NFTs to qualify?",
    "Is there a minimum wallet activity required?",
    "Can I participate if I'm using a hardware wallet?",
    "Will the airdrop tokens be tradable immediately?",
    "Is there a Telegram bot to check eligibility?",
    "How long does it take to receive the tokens after claiming?",
    "Do I need to add the token manually to my wallet?",
    "Is there a whitelist for the airdrop?",
    "Can I still participate if I'm late?",
    "What's the total value of this airdrop?",
    "Is this airdrop for early supporters only?",
    "Do I need to interact with the protocol to qualify?",
    "Are there any quizzes or tests to complete?",
    "Is there a minimum ETH/BSC balance required?",
    "How often does this project do airdrops?",
    "Can I claim on behalf of someone else?",
    "Is there a mobile app to claim the airdrop?",
    "Do I need to sign a message with my wallet?",
    "What's the difference between this airdrop and others?",
    "Is there a cap on how many people can claim?",
    "Are there any hidden fees?",
    "Can I use a VPN to claim?",
    "Is there a Discord role for airdrop participants?",
    "How do I know if I'm eligible?",
    "Will there be more airdrops in the future?",
    "Is this airdrop part of a larger campaign?",
    "Do I need to hold the token for a certain time?",
    "Can I claim with a fresh wallet?",
    "Is there a YouTube tutorial for this airdrop?",
    "What's the best wallet to use for this?",
    "Is there a minimum transaction count required?",
    "Can I participate if I'm in the US?",
    "Is there a fee to claim early?",
    "How do I add the token to MetaMask?",
    "Is there a staking requirement for the airdrop?",
    "What happens if I don't claim in time?",
    "Is there a snapshot history I can check?",
    "Can I still join the Discord to qualify?",
    "Is there a whitelist application process?",
    "Do I need to invite friends to get more tokens?",
    "Is there a bonus for early claimers?",
    "What's the contract address for the airdrop token?",
    "Can I swap the airdrop tokens immediately?",
    "Is there a liquidity pool for this token?",
    "What's the expected listing price?",
    "Is there a presale before the airdrop?",
    "Do I need to KYC for this airdrop?",
    "Is this airdrop on Ethereum or L2?",
    "Can I use Trust Wallet to claim?",
    "Is there a minimum age for the wallet?",
    "How do I track my airdrop status?",
    "Is there a leaderboard for referrals?",
    "What's the total supply of the token?",
    "Is there a burn mechanism?",
    "Can I stake the airdrop tokens?",
    "Is there a governance component?",
    "How do I vote with my tokens?",
    "Is there a DAO for this project?",
    "What's the team's background?",
    "Is this project audited?",
    "Where can I find the whitepaper?",
    "Is there a roadmap for Q3/Q4?",
    "How do I join the community?",
    "Is there a Spanish/Chinese/Japanese community?",
    "Are there any community events?",
    "How do I become a moderator?",
    "Is there a bug bounty program?",
    "How do I report a scam?",
    "Is there a support email?",
    "How do I reset my wallet connection?",
    "Is there a mobile-friendly claim page?",
    "Can I claim using WalletConnect?",
    "Is there a gasless claim option?",
    "What's the claim fee in USD?",
    "Can I claim with a Ledger?",
    "Is there a minimum swap volume required?",
    "Do I need to provide liquidity?",
    "Is there a lock-up period?",
    "Can I sell immediately after claim?",
    "Is there a price floor?",
    "What exchanges will list this token?",
    "Is there a CEX listing coming?",
    "When is the TGE?",
    "Is there a private sale round?",
    "How do I participate in the IDO?",
    "Is there a launchpad partner?",
    "What's the initial market cap?",
    "Is there a vesting schedule for team tokens?",
    "How transparent is the team?",
    "Is there a community wallet?",
    "How are funds managed?",
    "Is there a multi-sig wallet?",
    "Can I see the smart contract?",
    "Is the code open-source?",
    "How do I verify the contract?",
    "Is there a testnet version?",
    "Can I test the platform before mainnet?",
    "Is there a faucet for test tokens?",
    "How do I switch to mainnet?",
    "Is there a bridge for cross-chain?",
    "Can I transfer tokens between chains?",
    "Is there a native wallet?",
    "How do I backup my wallet?",
    "Is there a recovery phrase option?",
    "What if I lose my private key?",
    "Is there a customer support chat?",
    "How do I report a bug?",
    "Is there a bounty for finding bugs?",
    "Can I contribute to the codebase?",
    "Is there a developer portal?",
    "How do I build on this protocol?",
    "Is there an API for developers?",
    "Can I integrate this with my dApp?",
    "Is there a SDK available?",
    "What's the gas optimization like?",
    "Is there a fee-sharing model?",
    "How do I earn passive income?",
    "Is there a yield farming program?",
    "What's the current APY?",
    "Is the APY variable or fixed?",
    "Can I compound rewards?",
    "Is there a reward booster?",
    "How often are rewards distributed?",
    "Is there a minimum stake period?",
    "Can I unstake anytime?",
    "Is there an unlock fee?",
    "What's the penalty for early unstaking?",
    "Is there a vesting contract?",
    "Can I delegate my stake?",
    "Is there a governance token?",
    "How do I propose a change?",
    "Is there a voting power based on stake?",
    "Can I vote with locked tokens?",
    "Is there a snapshot for governance?",
    "How do I create a proposal?",
    "Is there a minimum token requirement for proposals?",
    "What's the voting period?",
    "Can I vote anonymously?",
    "Is there a treasury?",
    "How is the treasury managed?",
    "Can I request funds from the treasury?",
    "Is there a spending proposal process?",
    "What's the team's doxx status?",
    "Is the team active in the community?",
    "How often does the team do AMAs?",
    "Is there a YouTube channel?",
    "Can I watch past AMAs?",
    "Is there a podcast?",
    "Are there any partnerships?",
    "Who are the main investors?",
    "Is there a venture capital backing?",
    "Is this a community-driven project?",
    "How decentralized is this project?",
    "Is there a roadmap update soon?",
    "What's the next milestone?",
    "Is there a mainnet launch date?",
    "What's the beta release schedule?",
    "Is there a public testnet?",
    "Can I get test tokens?",
    "Is there a bug reporting form?",
    "How do I apply for an ambassador role?",
    "Is there a referral program?",
    "Can I earn by referring friends?",
    "What's the referral reward?",
    "Is there a leaderboard for referrals?",
    "How do I check my referral link?",
    "Can I refer without a wallet?",
    "Is there a social media campaign?",
    "How do I participate in the Twitter campaign?",
    "Is there a retweet requirement?",
    "Can I use multiple social accounts?",
    "Is there a content creation bounty?",
    "Can I submit memes for rewards?",
    "Is there an art contest?",
    "What's the prize pool?",
    "How do I submit my entry?",
    "Is there a deadline for submissions?",
    "Can I submit in any language?",
    "Is there a community translation program?",
    "How do I join the translation team?",
    "Is there a token for translation?",
    "Can I moderate the community?",
    "Is there a moderator application?",
    "What are the mod responsibilities?",
    "Is there a mod compensation?",
    "How do I report spam?",
    "Is there a anti-scam bot?",
    "Can I suggest new features?",
    "Is there a feedback channel?",
    "How do I propose a new idea?",
    "Is there a bounty for feature suggestions?",
    "Can I see the feature roadmap?",
    "What's the priority for development?",
    "Is there a mobile app?",
    "Can I use the app on iOS?",
    "Is there an Android version?",
    "What's the app store link?",
    "Is there a desktop version?",
    "Can I use the platform on browser?",
    "Is there a MetaMask integration?",
    "Can I use Phantom wallet?",
    "Is there support for Solana?",
    "Can I use Polygon network?",
    "Is there a BSC version?",
    "What's the contract address on BSC?",
    "Is there a bridge to Arbitrum?",
    "Can I use Optimism?",
    "Is there a zkSync integration?",
    "What's the gas fee on L2?",
    "Is there a discount for L2 users?",
    "Can I claim on multiple chains?",
    "Is there a unified balance?",
    "How do I switch networks?",
    "Is there a network fee?",
    "Can I use a custom RPC?",
    "Is there a public RPC endpoint?",
    "How do I check transaction status?",
    "Is there a block explorer?",
    "Can I track my tokens on Etherscan?",
    "Is there a dashboard?",
    "How do I view my portfolio?",
    "Is there a staking dashboard?",
    "Can I see my rewards history?",
    "Is there a claim history?",
    "How do I export my transaction data?",
    "Is there a CSV export option?",
    "Can I connect multiple wallets?",
    "Is there a wallet disconnect option?",
    "How do I change my wallet?",
    "Is there a wallet security check?",
    "Can I set up 2FA?",
    "Is there a security audit report?",
    "Can I view the audit results?",
    "Is there a insurance fund?",
    "What's the insurance coverage?",
    "Can I claim insurance?",
    "Is there a dispute resolution?",
    "How do I file a complaint?",
    "Is there a legal team?",
    "What's the jurisdiction?",
    "Is this project registered?",
    "Are there any legal disclaimers?",
    "Can I use this in my country?",
    "Is there a restricted countries list?",
    "Can I participate anonymously?",
    "Is there a privacy feature?",
    "Can I use a proxy?",
    "Is there a Tor support?",
    "Can I use a ENS domain?",
    "Is there a Unstoppable Domains integration?",
    "Can I set up a subdomain?",
    "Is there a NFT collection?",
    "Can I mint NFTs?",
    "Is there a NFT marketplace?",
    "Can I trade NFTs here?",
    "Is there a royalty system?",
    "How do I create an NFT?",
    "Is there a NFT launchpad?",
    "Can I participate in NFT drops?",
    "Is there a whitelist for NFTs?",
    "How do I get on the NFT whitelist?",
    "Is there a NFT staking program?",
    "Can I earn rewards with NFTs?",
    "Is there a metaverse integration?",
    "Can I use avatars?",
    "Is there a virtual land sale?",
    "Can I build on this platform?",
    "Is there a game integration?",
    "Can I play-to-earn?",
    "Is there a leaderboard for games?",
    "What's the in-game currency?",
    "Can I withdraw game rewards?",
    "Is there a tournament?",
    "What's the prize pool for tournaments?",
    "Can I spectate games?",
    "Is there a streaming feature?",
    "Can I tip streamers?",
    "Is there a content creator program?",
    "How do I apply as a creator?",
    "Is there a revenue share?",
    "Can I monetize my content?",
    "Is there a subscription model?",
    "Can I create a paid channel?",
    "Is there a tipping system?",
    "Can I use the token to tip?",
    "Is there a community fund for creators?",
    "How do I withdraw my earnings?",
    "Is there a minimum withdrawal?",
    "What's the withdrawal fee?",
    "Can I withdraw in stablecoins?",
    "Is there a fiat on-ramp?",
    "Can I buy tokens with credit card?",
    "Is there a bank transfer option?",
    "Can I use PayPal?",
    "Is there a P2P marketplace?",
    "Can I trade with other users?",
    "Is there a limit order book?",
    "Can I use stop-loss?",
    "Is there a margin trading feature?",
    "What's the leverage available?",
    "Is there a liquidation price?",
    "Can I use perpetual futures?",
    "Is there a options market?",
    "Can I hedge my position?",
    "Is there a portfolio tracker?",
    "Can I set price alerts?",
    "Is there a mobile notification?",
    "Can I get SMS alerts?",
    "Is there a email newsletter?",
    "How do I subscribe?",
    "Can I unsubscribe?",
    "Is there a privacy policy?",
    "Can I delete my data?",
    "Is there a GDPR compliance?",
    "Can I request my data?",
    "Is there a cookie policy?",
    "Can I opt out of tracking?",
    "Is there a ad-free version?",
    "Can I pay to remove ads?",
    "Is there a premium tier?",
    "What are the premium benefits?",
    "Can I get a discount?",
    "Is there a referral discount?",
    "Can I pay annually?",
    "Is there a money-back guarantee?",
    "Can I get a refund?",
    "What's the refund policy?",
    "Is there a trial period?",
    "Can I test before buying?",
    "Is there a demo?",
    "Can I get a walkthrough?",
    "Is there a help center?",
    "Can I search the FAQ?",
    "Is there a knowledge base?",
    "Can I submit a ticket?",
    "Is there a live chat?",
    "What are the support hours?",
    "Can I get 24/7 support?",
    "Is there a phone number?",
    "Can I get a callback?",
    "Is there a community forum?",
    "Can I ask questions on Reddit?",
    "Is there a dedicated subreddit?",
    "Can I join the Telegram group?",
    "Is there a Discord server?",
    "What's the Discord invite link?",
    "Can I get a role in Discord?",
    "Is there a verified member role?",
    "How do I get verified?",
    "Is there a bot in Discord?",
    "Can I use commands?",
    "What are the bot commands?",
    "Is there a FAQ bot?",
    "Can I get price updates in Discord?",
    "Is there a announcement channel?",
    "Can I mute notifications?",
    "Is there a weekly recap?",
    "Can I get a digest email?",
    "Is there a podcast summary?",
    "Can I listen on Spotify?",
    "Is there a YouTube recap?",
    "Can I watch on mobile?",
    "Is there a Twitter feed?",
    "Can I follow on Instagram?",
    "Is there a TikTok account?",
    "Can I share on social media?",
    "Is there a share button?",
    "Can I invite friends via link?",
    "Is there a QR code?",
    "Can I scan to join?",
    "Is there a physical event?",
    "Can I attend a meetup?",
    "Is there a conference?",
    "Can I speak at an event?",
    "Is there a hackathon?",
    "Can I participate in a hackathon?",
    "Is there a prize for winners?",
    "Can I get a certificate?",
    "Is there a badge system?",
    "Can I earn XP?",
    "Is there a level system?",
    "Can I rank up?",
    "Is there a leaderboard?",
    "Can I compete with others?",
    "Is there a season pass?",
    "Can I buy a battle pass?",
    "Is there a reward track?",
    "Can I unlock exclusive content?",
    "Is there a hidden channel?",
    "Can I get access after certain level?",
    "Is there a NFT gated channel?",
    "Can I join with an NFT?",
    "Is there a token-gated community?",
    "Can I join with any token?",
    "Is there a minimum token balance?",
    "Can I use my wallet to prove membership?",
    "Is there a snapshots for roles?",
    "Can I get roles based on holdings?",
    "Is there a role for long-term holders?",
    "Can I get a OG role?",
    "Is there a whitelist for early supporters?",
    "Can I get a early adopter badge?",
    "Is there a founder's circle?",
    "Can I join the council?",
    "Is there a advisory board?",
    "Can I become an advisor?",
    "Is there a application process?",
    "Can I submit my resume?",
    "Is there a job opening?",
    "Can I work for the project?",
    "Is there a remote job?",
    "Can I apply as a developer?",
    "Is there a design role?",
    "Can I apply as a marketer?",
    "Is there a community manager position?",
    "Can I apply as a moderator?",
    "Is there a paid internship?",
    "Can I volunteer?",
    "Is there a DAO contribution program?",
    "Can I get compensated in tokens?",
    "Is there a bounty board?",
    "Can I complete tasks for rewards?",
    "Is there a task list?",
    "Can I see available bounties?",
    "Is there a reward for social tasks?",
    "Can I get tokens for tweeting?",
    "Is there a content creation bounty?",
    "Can I make a video for rewards?",
    "Is there a meme contest?",
    "Can I win tokens for memes?",
    "Is there a sticker pack?",
    "Can I submit a sticker?",
    "Is there a emoji set?",
    "Can I use custom emojis?",
    "Is there a merch store?",
    "Can I buy branded items?",
    "Is there a discount for token holders?",
    "Can I pay with tokens?",
    "Is there a physical item shipment?",
    "Can I get a hoodie?",
    "Is there a limited edition drop?",
    "Can I get a collectible?",
    "Is there a digital art drop?",
    "Can I mint a POAP?",
    "Is there a POAP for events?",
    "Can I collect POAPs?",
    "Is there a badge for attending AMA?",
    "Can I get a role for AMA attendance?",
    "Is there a quiz for rewards?",
    "Can I win tokens by quizzing?",
    "Is there a trivia night?",
    "Can I participate in trivia?",
    "Is there a bounty for top scorers?",
    "Can I get a title?",
    "Is there a custom flair?",
    "Can I choose my own role?",
    "Is there a nickname change?",
    "Can I use color roles?",
    "Is there a voice chat?",
    "Can I join voice channels?",
    "Is there a stage channel?",
    "Can I host a stage?",
    "Is there a podcast room?",
    "Can I listen live?",
    "Is there a recording?",
    "Can I download recordings?",
    "Is there a transcript?",
    "Can I read the summary?",
    "Is there a newsletter archive?",
    "Can I view past announcements?",
    "Is there a blog?",
    "Can I read the blog posts?",
    "Is there a medium page?",
    "Can I follow on Medium?",
    "Is there a mirror page?",
    "Can I read on Mirror?",
    "Is there a substack?",
    "Can I subscribe on Substack?",
    "Is there a RSS feed?",
    "Can I add to my reader?",
    "Is there a podcast on Apple?",
    "Can I leave a review?",
    "Is there a rating system?",
    "Can I rate the project?",
    "Is there a survey?",
    "Can I give feedback?",
    "Is there a suggestion box?",
    "Can I upvote ideas?",
    "Is there a roadmap voting?",
    "Can I vote on features?",
    "Is there a priority poll?",
    "Can I see the poll results?",
    "Is there a live poll?",
    "Can I create a poll?",
    "Is there a proposal template?",
    "Can I use a template?",
    "Is there a guideline?",
    "Can I read the guidelines?",
    "Is there a code of conduct?",
    "Can I report violations?",
    "Is there a ban appeal?",
    "Can I appeal a ban?",
    "Is there a re-entry after ban?",
    "Can I rejoin after timeout?",
    "Is there a cooling period?",
    "Can I request unban?",
    "Is there a support ticket?",
    "Can I escalate a issue?",
    "Is there a priority support?",
    "Can I pay for priority?",
    "Is there a VIP support?",
    "Can I get a dedicated manager?",
    "Is there a concierge service?",
    "Can I get white-glove treatment?",
    "Is there a beta tester program?",
    "Can I become a beta tester?",
    "Is there a early access?",
    "Can I get early access?",
    "Is there a sneak peek?",
    "Can I see upcoming features?",
    "Is there a development update?",
    "Can I get weekly updates?",
    "Is there a changelog?",
    "Can I read the changelog?",
    "Is there a version history?",
    "Can I rollback?",
    "Is there a backup?",
    "Can I restore my data?",
    "Is there a export feature?",
    "Can I export my wallet?",
    "Is there a import feature?",
    "Can I import my wallet?",
    "Is there a seed phrase backup?",
    "Can I backup my seed?",
    "Is there a recovery service?",
    "Can I recover my account?",
    "Is there a identity verification?",
    "Can I verify with ID?",
    "Is there a passport option?",
    "Can I use driving license?",
    "Is there a video verification?",
    "Can I do a live selfie?",
    "Is there a facial recognition?",
    "Can I use biometrics?",
    "Is there a fingerprint login?",
    "Can I use Face ID?",
    "Is there a PIN setup?",
    "Can I set a PIN?",
    "Is there a password manager?",
    "Can I integrate with 1Password?",
    "Is there a hardware wallet support?",
    "Can I connect Ledger?",
    "Can I connect Trezor?",
    "Is there a mobile wallet app?",
    "Can I use the mobile app?",
    "Is there a desktop wallet?",
    "Can I download the desktop app?",
    "Is there a web app?",
    "Can I use it in browser?",
    "Is there a PWA?",
    "Can I install as PWA?",
    "Is there a offline mode?",
    "Can I use offline?",
    "Is there a sync feature?",
    "Can I sync across devices?",
    "Is there a cloud backup?",
    "Can I backup to cloud?",
    "Is there a encryption?",
    "Can I encrypt my data?",
    "Is there a zero-knowledge proof?",
    "Can I use ZK proofs?",
    "Is there a privacy mode?",
    "Can I hide my balance?",
    "Is there a stealth address?",
    "Can I use stealth?",
    "Is there a mixer?",
    "Can I mix my tokens?",
    "Is there a privacy pool?",
    "Can I join a privacy pool?",
    "Is there a compliance tool?",
    "Can I check compliance?",
    "Is there a risk score?",
    "Can I see my risk score?",
    "Is there a reputation system?",
    "Can I build reputation?",
    "Is there a trust score?",
    "Can I improve my trust score?",
    "Is there a verification badge?",
    "Can I get a blue check?",
    "Is there a influencer program?",
    "Can I become an influencer?",
    "Is there a partnership program?",
    "Can I partner with the project?",
    "Is there a integration guide?",
    "Can I integrate with my app?",
    "Is there a widget?",
    "Can I embed a widget?",
    "Is there a button?",
    "Can I add a button to my site?",
    "Is there a API key?",
    "Can I get an API key?",
    "Is there a rate limit?",
    "What's the API rate limit?",
    "Can I increase the limit?",
    "Is there a enterprise plan?",
    "Can I get a enterprise plan?",
    "Is there a white-label solution?",
    "Can I white-label this?",
    "Is there a custom domain?",
    "Can I use my own domain?",
    "Is there a branding guide?",
    "Can I use the logo?",
    "Is there a media kit?",
    "Can I download media kit?",
    "Is there a press release?",
    "Can I read press releases?",
    "Is there a media contact?",
    "Can I contact press?",
    "Is there a PR agency?",
    "Can I hire them?",
    "Is there a community spotlight?",
    "Can I be featured?",
    "Is there a interview series?",
    "Can I be interviewed?",
    "Is there a podcast guest spot?",
    "Can I be a guest?",
    "Is there a webinar?",
    "Can I host a webinar?",
    "Is there a workshop?",
    "Can I attend a workshop?",
    "Is there a bootcamp?",
    "Can I join a bootcamp?",
    "Is there a certification?",
    "Can I get certified?",
    "Is there a course?",
    "Can I take a course?",
    "Is there a tutorial series?",
    "Can I follow tutorials?",
    "Is there a documentation?",
    "Can I read the docs?",
    "Is there a GitHub repo?",
    "Can I contribute to GitHub?",
    "Is there a issue tracker?",
    "Can I report issues?",
    "Is there a feature request?",
    "Can I request a feature?",
    "Is there a pull request?",
    "Can I submit a PR?",
    "Is there a code review?",
    "Can I review code?",
    "Is there a testing framework?",
    "Can I run tests?",
    "Is there a staging environment?",
    "Can I test on staging?",
    "Is there a devnet?",
    "Can I use devnet?",
    "Is there a mainnet fork?",
    "Can I fork the mainnet?",
    "Is there a local environment?",
    "Can I run locally?",
    "Is there a docker image?",
    "Can I use Docker?",
    "Is there a CI/CD pipeline?",
    "Can I see the pipeline?",
    "Is there a monitoring tool?",
    "Can I monitor the network?",
    "Is there a status page?",
    "Can I check status?",
    "Is there a uptime guarantee?",
    "What's the uptime SLA?",
    "Is there a downtime alert?",
    "Can I get alerts?",
    "Is there a incident report?",
    "Can I read incident reports?",
    "Is there a post-mortem?",
    "Can I see post-mortems?",
    "Is there a bug fix timeline?",
    "How long for fixes?",
    "Is there a upgrade schedule?",
    "When is the next upgrade?",
    "Is there a hard fork coming?",
    "Can I prepare for the fork?",
    "Is there a migration plan?",
    "Can I migrate my tokens?",
    "Is there a swap feature?",
    "Can I swap tokens?",
    "Is there a liquidity pool?",
    "Can I provide liquidity?",
    "Is there a impermanent loss protection?",
    "Is there a yield booster?",
    "Can I boost my yield?",
    "Is there a auto-compounder?",
    "Can I auto-compound?",
    "Is there a vault?",
    "Can I deposit into vault?",
    "Is there a strategy?",
    "What's the strategy?",
    "Is there a risk level?",
    "What's the risk?",
    "Is there a audit for vaults?",
    "Can I see audit reports?",
    "Is there a insurance for vaults?",
    "Can I insure my deposit?",
    "Is there a withdrawal limit?",
    "Is there a deposit limit?",
    "Can I increase limits?",
    "Is there a KYC for large amounts?",
    "Can I do KYC online?",
    "Is there a video call for KYC?",
    "Can I do KYC in person?",
    "Is there a notary option?",
    "Can I use e-signature?",
    "Is there a legal agreement?",
    "Can I sign electronically?",
    "Is there a terms of service?",
    "Can I read the ToS?",
    "Is there a privacy notice?",
    "Can I read the privacy policy?",
    "Is there a cookie consent?",
    "Can I manage cookies?",
    "Is there a data retention policy?",
    "How long is data kept?",
    "Can I request deletion?",
    "Is there a data portability?",
    "Can I transfer my data?",
    "Is there a complaint procedure?",
    "Can I file a complaint?",
    "Is there a ombudsman?",
    "Can I escalate to ombudsman?",
    "Is there a arbitration clause?",
    "Can I opt out of arbitration?",
    "Is there a class action waiver?",
    "Can I join a class action?",
    "Is there a governing law?",
    "What's the governing law?",
    "Is there a dispute resolution?",
    "Can I resolve disputes?",
    "Is there a mediation option?",
    "Can I mediate?",
    "Is there a settlement process?",
    "Can I settle?",
    "Is there a compensation fund?",
    "Can I claim compensation?",
    "Is there a insurance claim?",
    "Can I file insurance?",
    "Is there a support group?",
    "Can I join support group?",
    "Is there a mental health resource?",
    "Can I access resources?",
    "Is there a community wellness program?",
    "Can I participate?",
    "Is there a wellness check?",
    "Can I request a check?",
    "Is there a crisis line?",
    "Can I call a crisis line?",
    "Is there a emergency contact?",
    "Can I set emergency contact?",
    "Is there a next of kin?",
    "Can I add next of kin?",
    "Is there a will feature?",
    "Can I set a crypto will?",
    "Is there a inheritance plan?",
    "Can I plan inheritance?",
    "Is there a trust setup?",
    "Can I set up a trust?",
    "Is there a multisig for inheritance?",
    "Can I use multisig?",
    "Is there a social recovery?",
    "Can I set social recovery?",
    "Is there a guardian feature?",
    "Can I add a guardian?",
    "Is there a recovery contact?",
    "Can I set recovery contact?",
    "Is there a timeout recovery?",
    "Can I recover after timeout?",
    "Is there a delayed recovery?",
    "Can I delay recovery?",
    "Is there a backup validator?",
    "Can I set a backup?",
    "Is there a failover?",
    "Can I set failover?",
    "Is there a redundancy?",
    "Is there a high availability?",
    "Is there a disaster recovery?",
    "Can I test DR?",
    "Is there a backup plan?",
    "Can I see the backup plan?",
    "Is there a continuity plan?",
    "Can I review continuity?",
    "Is there a business continuity?",
    "Can I get BCP?",
    "Is there a incident response?",
    "Can I see IR plan?",
    "Is there a crisis management?",
    "Can I join crisis team?",
    "Is there a communication plan?",
    "Can I see comms plan?",
    "Is there a public relations?",
    "Can I contact PR?",
    "Is there a media training?",
    "Can I get media training?",
    "Is there a spokesperson?",
    "Can I become a spokesperson?",
    "Is there a brand ambassador?",
    "Can I become an ambassador?",
    "Is there a influencer outreach?",
    "Can I collaborate?",
    "Is there a affiliate program?",
    "Can I join affiliate?",
    "Is there a commission structure?",
    "What's the commission?",
    "Is there a cookie duration?",
    "How long is cookie?",
    "Can I track referrals?",
    "Is there a dashboard for referrals?",
    "Can I see my referral stats?",
    "Is there a payout schedule?",
    "When are payouts?",
    "Is there a minimum payout?",
    "Can I get paid in crypto?",
    "Is there a fiat payout?",
    "Can I get bank transfer?",
    "Is there a PayPal option?",
    "Can I get gift cards?",
    "Is there a rewards catalog?",
    "Can I redeem rewards?",
    "Is there a points system?",
    "Can I earn points?",
    "Is there a loyalty program?",
    "Can I join loyalty?",
    "Is there a tiered rewards?",
    "Can I reach higher tiers?",
    "Is there a bonus for tier?",
    "Can I get bonuses?",
    "Is there a seasonal campaign?",
    "Can I participate?",
    "Is there a holiday event?",
    "Can I join holiday event?",
    "Is there a special edition?",
    "Can I get special edition?",
    "Is there a limited time offer?",
    "Can I claim limited offer?",
    "Is there a flash sale?",
    "Can I buy in flash sale?",
    "Is there a pre-order?",
    "Can I pre-order?",
    "Is there a waitlist?",
    "Can I join waitlist?",
    "Is there a early bird discount?",
    "Can I get early bird?",
    "Is there a group discount?",
    "Can I get group discount?",
    "Is there a bulk purchase?",
    "Can I buy in bulk?",
    "Is there a wholesale price?",
    "Can I get wholesale?",
    "Is there a reseller program?",
    "Can I become a reseller?",
    "Is there a white-label partner?",
    "Can I become a partner?",
    "Is there a referral fee?",
    "What's the referral fee?",
    "Is there a lifetime commission?",
    "Can I get lifetime commission?",
    "Is there a recurring commission?",
    "Can I get recurring?",
    "Is there a bonus for top referrers?",
    "Can I win bonuses?",
    "Is there a contest for referrers?",
    "Can I join contest?",
    "Is there a leaderboard prize?",
    "Can I win leaderboard?",
    "Is there a grand prize?",
    "What's the grand prize?",
    "Is there a trip giveaway?",
    "Can I win a trip?",
    "Is there a crypto giveaway?",
    "Can I win crypto?",
    "Is there a raffle?",
    "Can I enter raffle?",
    "Is there a lottery?",
    "Can I buy lottery tickets?",
    "Is there a sweepstakes?",
    "Can I enter sweepstakes?",
    "Is there a random draw?",
    "Can I participate?",
    "Is there a lucky draw?",
    "Can I join lucky draw?",
    "Is there a spin wheel?",
    "Can I spin wheel?",
    "Is there a surprise box?",
    "Can I open surprise box?",
    "Is there a mystery box?",
    "Can I buy mystery box?",
    "Is there a blind box?",
    "Can I buy blind box?",
    "Is there a booster pack?",
    "Can I buy booster pack?",
    "Is there a card pack?",
    "Can I buy card pack?",
    "Is there a digital pack?",
    "Can I open digital pack?",
    "Is there a collectible pack?",
    "Can I collect all?",
    "Is there a set completion?",
    "Can I complete set?",
    "Is there a reward for collection?",
    "Can I get reward?",
    "Is there a showcase?",
    "Can I showcase my collection?",
    "Is there a gallery?",
    "Can I view gallery?",
    "Is there a museum?",
    "Can I visit museum?",
    "Is there a virtual tour?",
    "Can I take virtual tour?",
    "Is there a 3D view?",
    "Can I view 3D?",
    "Is there a AR feature?",
    "Can I use AR?",
    "Is there a VR feature?",
    "Can I use VR?",
    "Is there a metaverse event?",
    "Can I join metaverse?",
    "Is there a virtual concert?",
    "Can I attend concert?",
    "Is there a virtual meetup?",
    "Can I join meetup?",
    "Is there a networking event?",
    "Can I network?",
    "Is there a speed networking?",
    "Can I participate?",
    "Is there a roundtable?",
    "Can I join roundtable?",
    "Is there a panel discussion?",
    "Can I join panel?",
    "Is there a keynote?",
    "Can I watch keynote?",
    "Is there a fireside chat?",
    "Can I join fireside?",
    "Is there a Q&A session?",
    "Can I ask questions?",
    "Is there a AMA?",
    "Can I join AMA?",
    "Is there a live stream?",
    "Can I watch live?",
    "Is there a recorded session?",
    "Can I watch recording?",
    "Is there a transcript?",
    "Can I read transcript?",
    "Is there a summary?",
    "Can I read summary?",
    "Is there a highlight reel?",
    "Can I watch highlights?",
    "Is there a behind-the-scenes?",
    "Can I see BTS?",
    "Is there a documentary?",
    "Can I watch doc?",
    "Is there a series?",
    "Can I binge watch?",
    "Is there a trailer?",
    "Can I watch trailer?",
    "Is there a teaser?",
    "Can I see teaser?",
    "Is there a announcement?",
    "Can I read announcement?",
    "Is there a press kit?",
    "Can I download press kit?",
    "Is there a logo pack?",
    "Can I download logo?",
    "Is there a brand guidelines?",
    "Can I read guidelines?",
    "Is there a style guide?",
    "Can I use style guide?",
    "Is there a color palette?",
    "Can I use colors?",
    "Is there a font pack?",
    "Can I download fonts?",
    "Is there a icon pack?",
    "Can I use icons?",
    "Is there a sticker pack?",
    "Can I download stickers?",
    "Is there a emoji set?",
    "Can I use emojis?",
    "Is there a gif pack?",
    "Can I use gifs?",
    "Is there a meme pack?",
    "Can I use memes?",
    "Is there a template?",
    "Can I use templates?",
    "Is there a presentation?",
    "Can I use slides?",
    "Is there a pitch deck?",
    "Can I view pitch deck?",
    "Is there a one-pager?",
    "Can I read one-pager?",
    "Is there a fact sheet?",
    "Can I read fact sheet?",
    "Is there a infographic?",
    "Can I view infographic?",
    "Is there a chart?",
    "Can I see chart?",
    "Is there a graph?",
    "Can I see graph?",
    "Is there a dashboard?",
    "Can I view dashboard?",
    "Is there a analytics?",
    "Can I see analytics?",
    "Is there a report?",
    "Can I download report?",
    "Is there a whitepaper?",
    "Can I read whitepaper?",
    "Is there a litepaper?",
    "Can I read litepaper?",
    "Is there a technical paper?",
    "Can I read technical paper?",
    "Is there a research paper?",
    "Can I read research?",
    "Is there a case study?",
    "Can I read case study?",
    "Is there a use case?",
    "Can I see use case?",
    "Is there a demo video?",
    "Can I watch demo?",
    "Is there a tutorial video?",
    "Can I watch tutorial?",
    "Is there a walkthrough video?",
    "Can I watch walkthrough?",
    "Is there a explainer video?",
    "Can I watch explainer?",
    "Is there a animation?",
    "Can I view animation?",
    "Is there a interactive demo?",
    "Can I try demo?",
    "Is there a sandbox?",
    "Can I use sandbox?",
    "Is there a playground?",
    "Can I play in playground?",
    "Is there a testnet?",
    "Can I use testnet?",
    "Is there a devnet?",
    "Can I use devnet?",
    "Is there a staging?",
    "Can I use staging?",
    "Is there a preview?",
    "Can I see preview?",
    "Is there a prototype?",
    "Can I test prototype?",
    "Is there a beta?",
    "Can I join beta?",
    "Is there a early access?",
    "Can I get early access?",
    "Is there a waitlist?",
    "Can I join waitlist?",
    "Is there a priority list?",
    "Can I get priority?",
    "Is there a vip list?",
    "Can I join vip?",
    "Is there a exclusive group?",
    "Can I join exclusive?",
    "Is there a inner circle?",
    "Can I join inner circle?",
    "Is there a council?",
    "Can I join council?",
    "Is there a senate?",
    "Can I join senate?",
    "Is there a parliament?",
    "Can I join parliament?",
    "Is there a governance forum?",
    "Can I join forum?",
    "Is there a discussion board?",
    "Can I post?",
    "Is there a comment section?",
    "Can I comment?",
    "Is there a upvote system?",
    "Can I upvote?",
    "Is there a downvote?",
    "Can I downvote?",
    "Is there a reputation?",
    "Can I earn reputation?",
    "Is there a badge?",
    "Can I earn badges?",
    "Is there a achievement?",
    "Can I earn achievements?",
    "Is there a trophy?",
    "Can I earn trophies?",
    "Is there a medal?",
    "Can I earn medals?",
    "Is there a ribbon?",
    "Can I earn ribbons?",
    "Is there a star?",
    "Can I earn stars?",
    "Is there a crown?",
    "Can I earn crown?",
    "Is there a title?",
    "Can I earn title?",
    "Is there a rank?",
    "Can I rank up?",
    "Is there a level?",
    "Can I level up?",
    "Is there a XP?",
    "Can I earn XP?",
    "Is there a streak?",
    "Can I maintain streak?",
    "Is there a bonus for streaks?",
    "Can I get bonus?",
    "Is there a daily reward?",
    "Can I claim daily?",
    "Is there a weekly reward?",
    "Can I claim weekly?",
    "Is there a monthly reward?",
    "Can I claim monthly?",
    "Is there a seasonal reward?",
    "Can I claim seasonal?",
    "Is there a event reward?",
    "Can I claim event?",
    "Is there a special reward?",
    "Can I claim special?",
    "Is there a mystery reward?",
    "Can I claim mystery?",
    "Is there a random reward?",
    "Can I get random?",
    "Is there a lucky reward?",
    "Can I get lucky?",
    "Is there a bonus round?",
    "Can I join bonus?",
    "Is there a extra chance?",
    "Can I get extra?",
    "Is there a multiplier?",
    "Can I get multiplier?",
    "Is there a boost?",
    "Can I get boost?",
    "Is there a power-up?",
    "Can I use power-up?",
    "Is there a skill tree?",
    "Can I upgrade skills?",
    "Is there a talent system?",
    "Can I unlock talents?",
    "Is there a class system?",
    "Can I choose class?",
    "Is there a specialization?",
    "Can I specialize?",
    "Is there a mastery?",
    "Can I achieve mastery?",
    "Is there a prestige?",
    "Can I prestige?",
    "Is there a reset?",
    "Can I reset progress?",
    "Is there a new game+?",
    "Can I start NG+?",
    "Is there a challenge mode?",
    "Can I try challenge?",
    "Is there a hard mode?",
    "Can I try hard mode?",
    "Is there a nightmare mode?",
    "Can I try nightmare?",
    "Is there a survival mode?",
    "Can I try survival?",
    "Is there a endless mode?",
    "Can I try endless?",
    "Is there a speedrun?",
    "Can I speedrun?",
    "Is there a leaderboard for speedrun?",
    "Can I compete?",
    "Is there a time trial?",
    "Can I try time trial?",
    "Is there a race?",
    "Can I race?",
    "Is there a duel?",
    "Can I duel?",
    "Is there a battle?",
    "Can I battle?",
    "Is there a war?",
    "Can I join war?",
    "Is there a clan?",
    "Can I join clan?",
    "Is there a guild?",
    "Can I join guild?",
    "Is there a faction?",
    "Can I choose faction?",
    "Is there a alliance?",
    "Can I form alliance?",
    "Is there a team?",
    "Can I create team?",
    "Is there a group?",
    "Can I create group?",
    "Is there a party?",
    "Can I join party?",
    "Is there a squad?",
    "Can I join squad?",
    "Is there a crew?",
    "Can I join crew?",
    "Is there a gang?",
    "Can I join gang?",
    "Is there a tribe?",
    "Can I join tribe?",
    "Is there a community?",
    "Can I join community?",
    "Is there a sub-community?",
    "Can I join sub-community?",
    "Is there a regional group?",
    "Can I join regional?",
    "Is there a language group?",
    "Can I join language group?",
    "Is there a interest group?",
    "Can I join interest group?",
    "Is there a hobby group?",
    "Can I join hobby group?",
    "Is there a skill group?",
    "Can I join skill group?",
    "Is there a mentorship group?",
    "Can I get mentorship?",
    "Is there a coaching group?",
    "Can I get coaching?",
    "Is there a learning group?",
    "Can I learn?",
    "Is there a study group?",
    "Can I study?",
    "Is there a book club?",
    "Can I join book club?",
    "Is there a movie club?",
    "Can I join movie club?",
    "Is there a game club?",
    "Can I join game club?",
    "Is there a sports club?",
    "Can I join sports club?",
    "Is there a fitness group?",
    "Can I join fitness?",
    "Is there a health group?",
    "Can I join health?",
    "Is there a wellness group?",
    "Can I join wellness?",
    "Is there a meditation group?",
    "Can I meditate?",
    "Is there a yoga group?",
    "Can I do yoga?",
    "Is there a music group?",
    "Can I join music?",
    "Is there an art group?",
    "Can I join art?",
    "Is there a photography group?",
    "Can I join photography?",
    "Is there a design group?",
    "Can I join design?",
    "Is there a coding group?",
    "Can I code?",
    "Is there a hack group?",
    "Can I hack?",
    "Is there a security group?",
    "Can I join security?",
    "Is there a privacy group?",
    "Can I join privacy?",
    "Is there a crypto group?",
    "Can I join crypto?",
    "Is there a trading group?",
    "Can I join trading?",
    "Is there an investing group?",
    "Can I join investing?",
    "Is there a finance group?",
    "Can I join finance?",
    "Is there a business group?",
    "Can I join business?",
    "Is there a startup group?",
    "Can I join startup?",
    "Is there an entrepreneurship group?",
    "Can I join entrepreneurship?",
    "Is there a marketing group?",
    "Can I join marketing?",
    "Is there a sales group?",
    "Can I join sales?",
    "Is there a growth group?",
    "Can I join growth?",
    "Is there a product group?",
    "Can I join product?",
    "Is there an engineering group?",
    "Can I join engineering?",
    "Is there a science group?",
    "Can I join science?",
    "Is there a research group?",
    "Can I join research?",
    "Is there an innovation group?",
    "Can I join innovation?",
    "Is there a creativity group?",
    "Can I join creativity?",
    "Is there a writing group?",
    "Can I write?",
    "Is there a poetry group?",
    "Can I share poetry?",
    "Is there a storytelling group?",
    "Can I tell stories?",
    "Is there a podcast group?",
    "Can I podcast?",
    "Is there a video group?",
    "Can I make videos?",
    "Is there a streaming group?",
    "Can I stream?",
    "Is there a gaming group?",
    "Can I game?",
    "Is there an esports group?",
    "Can I join esports?",
    "Is there a competitive group?",
    "Can I compete?",
    "Is there a casual group?",
    "Can I chill?",
    "Is there a social group?",
    "Can I socialize?",
    "Is there a dating group?",
    "Can I date?",
    "Is there a friendship group?",
    "Can I make friends?",
    "Is there a networking group?",
    "Can I network?",
    "Is there a professional group?",
    "Can I network professionally?",
    "Is there a career group?",
    "Can I advance career?",
    "Is there a job group?",
    "Can I find jobs?",
    "Is there a freelance group?",
    "Can I find gigs?",
    "Is there a remote work group?",
    "Can I find remote work?",
    "Is there a side hustle group?",
    "Can I side hustle?",
    "Is there a passive income group?",
    "Can I earn passive income?",
    "Is there a wealth group?",
    "Can I build wealth?",
    "Is there a financial freedom group?",
    "Can I achieve freedom?",
    "Is there a retirement group?",
    "Can I plan retirement?",
    "Is there a life advice group?",
    "Can I get advice?",
    "Is there a motivation group?",
    "Can I get motivated?",
    "Is there an inspiration group?",
    "Can I get inspired?",
    "Is there a positivity group?",
    "Can I stay positive?",
    "Is there a gratitude group?",
    "Can I practice gratitude?",
    "Is there a mindfulness group?",
    "Can I be mindful?",
    "Is there a self-care group?",
    "Can I self-care?",
    "Is there a personal development group?",
    "Can I develop?",
    "Is there a growth mindset group?",
    "Can I grow?",
    "Is there a learning group?",
    "Can I learn?",
    "Is there an education group?",
    "Can I educate?",
    "Is there a knowledge group?",
    "Can I share knowledge?",
    "Is there a wisdom group?",
    "Can I gain wisdom?",
    "Is there a philosophy group?",
    "Can I discuss philosophy?",
    "Is there a spirituality group?",
    "Can I explore spirituality?",
    "Is there a religion group?",
    "Can I discuss religion?",
    "Is there a culture group?",
    "Can I explore culture?",
    "Is there a travel group?",
    "Can I travel?",
    "Is there a food group?",
    "Can I share food?",
    "Is there a recipe group?",
    "Can I share recipes?",
    "Is there a cooking group?",
    "Can I cook?",
    "Is there a baking group?",
    "Can I bake?",
    "Is there a gardening group?",
    "Can I garden?",
    "Is there a nature group?",
    "Can I enjoy nature?",
    "Is there an animal group?",
    "Can I love animals?",
    "Is there a pet group?",
    "Can I share pets?",
    "Is there a dog group?",
    "Can I share dogs?",
    "Is there a cat group?",
    "Can I share cats?",
    "Is there a bird group?",
    "Can I share birds?",
    "Is there a fish group?",
    "Can I share fish?",
    "Is there a plant group?",
    "Can I share plants?",
    "Is there a home group?",
    "Can I share home?",
    "Is there a diy group?",
    "Can I diy?",
    "Is there a crafting group?",
    "Can I craft?",
    "Is there a sewing group?",
    "Can I sew?",
    "Is there a knitting group?",
    "Can I knit?",
    "Is there a woodworking group?",
    "Can I woodwork?",
    "Is there a metalworking group?",
    "Can I metalwork?",
    "Is there a 3d printing group?",
    "Can I 3d print?",
    "Is there a robotics group?",
    "Can I build robots?",
    "Is there an electronics group?",
    "Can I build electronics?",
    "Is there a coding group?",
    "Can I code?",
    "Is there a webdev group?",
    "Can I webdev?",
    "Is there an appdev group?",
    "Can I appdev?",
    "Is there a gamedev group?",
    "Can I gamedev?",
    "Is there a blockchain group?",
    "Can I blockchain?",
    "Is there a defi group?",
    "Can I defi?",
    "Is there an nft group?",
    "Can I nft?",
    "Is there a metaverse group?",
    "Can I metaverse?",
    "Is there an ai group?",
    "Can I ai?",
    "Is there an ml group?",
    "Can I ml?",
    "Is there a data science group?",
    "Can I data science?",
    "Is there a big data group?",
    "Can I big data?",
    "Is there a cloud group?",
    "Can I cloud?",
    "Is there a devops group?",
    "Can I devops?",
    "Is there a sysadmin group?",
    "Can I sysadmin?",
    "Is there a security group?",
    "Can I security?",
    "Is there an ethical hacking group?",
    "Can I ethical hack?",
    "Is there a bug bounty group?",
    "Can I bug bounty?",
    "Is there a penetration testing group?",
    "Can I pentest?",
    "Is there a forensics group?",
    "Can I forensics?",
    "Is there a compliance group?",
    "Can I compliance?",
    "Is there an audit group?",
    "Can I audit?",
    "Is there a finance group?",
    "Can I finance?",
    "Is there an accounting group?",
    "Can I accounting?",
    "Is there a tax group?",
    "Can I tax?",
    "Is there a legal group?",
    "Can I legal?",
    "Is there an hr group?",
    "Can I hr?",
    "Is there a recruitment group?",
    "Can I recruit?",
    "Is there a talent group?",
    "Can I talent?",
    "Is there a leadership group?",
    "Can I lead?",
    "Is there a management group?",
    "Can I manage?",
    "Is there a strategy group?",
    "Can I strategize?",
    "Is there a consulting group?",
    "Can I consult?",
    "Is there a coaching group?",
    "Can I coach?",
    "Is there a mentoring group?",
    "Can I mentor?"
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
    const max = Math.min(100, Math.max(1, parseInt(document.getElementById('maxMessages').value, 10) || 1));
    const pool = parseDelays(document.getElementById('delayPool').value);

    if (!channelId || !token || !xSuper || !install) {
        alert('⚠️ Pehle Channel ID, Token, x-super-properties aur Installation ID bharo!');
        return;
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
