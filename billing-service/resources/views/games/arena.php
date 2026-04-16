<?php
session_start();
include '../localhost.php'; // Your connection file
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE game_users SET last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch the user's plan type
$is_pro = false;
$query = "SELECT plan_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($userAt = $result->fetch_assoc()) {
    // Check if plan_type is 'pro'. Adjust the string 'pro' to match your DB value.
    if ($userAt['plan_type'] === 'pro') {
        $is_pro = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arena - IELTS Battle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #0f172a; 
            color: white; 
            background-image: radial-gradient(#6366f1 0.5px, transparent 0.5px);
            background-size: 30px 30px;
            background-attachment: fixed;
        }
        .glass-panel { 
            background: rgba(30, 41, 59, 0.7); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255,255,255,0.1); 
        }
        .arena-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .arena-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 md:p-8">

    <div class="w-full max-w-4xl z-10">
        
        <div class="flex flex-wrap items-end justify-between gap-6 mb-10">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    <span class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-400">Combat Zone</span>
                </div>
                <h1 class="text-4xl md:text-5xl font-black italic uppercase tracking-tighter">The Arena</h1>
            </div>
            
            <div class="flex gap-4">
                <div class="glass-panel px-6 py-3 rounded-2xl border-b-2 border-indigo-500/50">
                    <span class="block text-[9px] text-slate-400 uppercase font-black tracking-widest mb-1">Current Rank</span>
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-indigo-400"></i>
                        <span class="text-lg font-black italic">SILVER II</span>
                    </div>
                </div>
                <div class="glass-panel px-6 py-3 rounded-2xl border-b-2 border-emerald-500/50">
                    <span class="block text-[9px] text-slate-400 uppercase font-black tracking-widest mb-1">Win Rate</span>
                    <div class="flex items-center gap-2">
                        <i data-lucide="trending-up" class="w-4 h-4 text-emerald-400"></i>
                        <span class="text-lg font-black italic text-emerald-400">68%</span>
                    </div>
                </div>
            </div>
        </div>
<div class="glass-panel rounded-[2rem] overflow-hidden mb-10 border-t-2 border-indigo-500/50 relative">
    <?php if($is_pro): ?>
        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 blur-[50px] pointer-events-none"></div>
    <?php endif; ?>

    <div class="p-6 relative z-10">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-indigo-500/10 rounded-xl flex items-center justify-center border border-indigo-500/20 shadow-[0_0_15px_rgba(99,102,241,0.1)]">
                    <i data-lucide="sliders" class="w-5 h-5 text-indigo-400"></i>
                </div>
                <div>
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-indigo-400 mb-1">Combat Config</h3>
                    <p class="text-[10px] text-slate-500 font-bold uppercase italic">Mission Parameters</p>
                </div>
            </div>

            <?php if($is_pro): ?>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-indigo-500/20 border border-indigo-500/30 rounded-lg">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                    <span class="text-[9px] text-indigo-400 font-black uppercase tracking-widest">Operator Access</span>
                </div>
            <?php else: ?>
                <a href="pricing.php" class="group flex items-center gap-2 px-4 py-2 bg-amber-500/10 border border-amber-500/30 rounded-xl hover:bg-amber-500 transition-all duration-300">
                    <i data-lucide="lock" class="w-3 h-3 text-amber-500 group-hover:text-black transition-colors"></i>
                    <span class="text-[9px] text-amber-500 font-black uppercase tracking-widest group-hover:text-black transition-colors">Unlock Domains</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="group/select relative">
                <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Subject Matter</label>
                <div class="relative">
                    <select id="topic-select" <?php echo !$is_pro ? 'disabled' : ''; ?> 
                        class="w-full bg-slate-900/80 border border-slate-700/50 rounded-2xl px-5 py-4 text-xs font-black italic uppercase tracking-wider text-white appearance-none focus:outline-none focus:border-indigo-500/50 transition-all disabled:opacity-40 disabled:grayscale">
                        <option value="random">Neural Randomizer</option>
                        <option value="tech">Digital Frontiers (Tech)</option>
                        <option value="env">Biosphere (Environment)</option>
                        <option value="edu">Social Construct (Education)</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 pointer-events-none"></i>
                </div>
            </div>

            <div class="group/select relative">
                <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Engagement Type</label>
                <div class="relative">
                    <select id="type-select" <?php echo !$is_pro ? 'disabled' : ''; ?> 
                        class="w-full bg-slate-900/80 border border-slate-700/50 rounded-2xl px-5 py-4 text-xs font-black italic uppercase tracking-wider text-white appearance-none focus:outline-none focus:border-indigo-500/50 transition-all disabled:opacity-40 disabled:grayscale">
                        <option value="mixed">Adaptive Matrix</option>
                        <option value="vocab">Lexical Strike</option>
                        <option value="grammar">Structural Logic</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 pointer-events-none"></i>
                </div>
            </div>

            <div class="group/select relative">
                <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Intensity Level</label>
                <div class="relative">
                    <select id="diff-select" <?php echo !$is_pro ? 'disabled' : ''; ?> 
                        class="w-full bg-slate-900/80 border border-slate-700/50 rounded-2xl px-5 py-4 text-xs font-black italic uppercase tracking-wider text-white appearance-none focus:outline-none focus:border-rose-500/50 transition-all disabled:opacity-40 disabled:grayscale">
                        <option value="adaptive">Auto-Scale</option>
                        <option value="hard">Veteran</option>
                        <option value="insane">Grandmaster (Band 9)</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 pointer-events-none"></i>
                </div>
            </div>
        </div>

        <?php if(!$is_pro): ?>
            <div class="mt-4 p-3 rounded-xl bg-slate-950/30 border border-slate-800/50 flex items-center justify-center gap-3">
                <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em]">Standard users default to Neural Randomizer</p>
                <span class="w-1 h-1 rounded-full bg-slate-700"></span>
            </div>
        <?php endif; ?>
    </div>
</div>
        <div class="grid md:grid-cols-2 gap-6 mb-12">
            
            <button id="quick-match-btn" onclick="startMatchmaking()" class="arena-card glass-panel group relative overflow-hidden p-8 rounded-[2.5rem] text-left border-l-4 border-l-rose-500">
                <div class="absolute right-[-20px] top-[-20px] opacity-5 group-hover:rotate-12 transition-transform">
                    <i data-lucide="zap" class="w-40 h-40 text-rose-500"></i>
                </div>
                
                <div class="w-14 h-14 bg-rose-500/20 rounded-2xl flex items-center justify-center mb-6 border border-rose-500/30 group-hover:scale-110 transition-transform">
                    <i data-lucide="zap" class="w-7 h-7 text-rose-500"></i>
                </div>
                
                <h3 class="text-2xl font-black italic uppercase mb-2">Quick Match</h3>
                <p class="text-slate-400 text-sm leading-relaxed mb-8 max-w-[250px]">
                    Engage in a neural uplink battle against a random opponent's ghost score.
                </p>
                
                <div class="flex items-center gap-2 text-rose-500 font-black uppercase text-xs tracking-widest">
                    <span>Initiate Battle</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </div>
            </button>

            <button class="arena-card glass-panel group relative overflow-hidden p-8 rounded-[2.5rem] text-left border-l-4 border-l-blue-500">
                <div class="absolute right-[-20px] top-[-20px] opacity-5 group-hover:rotate-12 transition-transform">
                    <i data-lucide="users" class="w-40 h-40 text-blue-500"></i>
                </div>

                <div class="w-14 h-14 bg-blue-500/20 rounded-2xl flex items-center justify-center mb-6 border border-blue-500/30 group-hover:scale-110 transition-transform">
                    <i data-lucide="users" class="w-7 h-7 text-blue-500"></i>
                </div>
                
                <h3 class="text-2xl font-black italic uppercase mb-2">Challenge Peer</h3>
                <p class="text-slate-400 text-sm leading-relaxed mb-8 max-w-[250px]">
                    Generate an encrypted link to invite a specific rival for a head-to-head test.
                </p>
                
                <div class="flex items-center gap-2 text-blue-500 font-black uppercase text-xs tracking-widest">
                    <span>Create Link</span>
                    <i data-lucide="link" class="w-4 h-4"></i>
                </div>
            </button>
            
        </div>

        <div class="space-y-4">
            <h2 class="flex items-center gap-3 text-[10px] font-black text-slate-500 uppercase tracking-[0.4em] px-2 mb-6">
                <span class="w-8 h-px bg-slate-800"></span> 
                Incoming Transmissions
                <span class="w-8 h-px bg-slate-800"></span>
            </h2>
            
            <div class="glass-panel rounded-3xl p-5 flex items-center justify-between border-r-4 border-indigo-500 group">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 flex items-center justify-center text-indigo-400 font-black italic border border-indigo-500/30">
                            JD
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-slate-900 rounded-full"></div>
                    </div>
                    <div>
                        <p class="text-sm font-black italic uppercase tracking-tight text-white">John Doe <span class="text-indigo-400 not-italic font-medium lowercase">is challenging you</span></p>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Daily Vocabulary • Hard Mode</p>
                    </div>
                </div>
                <button class="px-6 py-2 bg-white text-slate-950 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-400 transition-all hover:scale-105">
                    Accept
                </button>
            </div>

            <div class="glass-panel rounded-3xl p-5 flex items-center justify-between opacity-50 grayscale hover:grayscale-0 hover:opacity-100 transition-all border-r-4 border-slate-600">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center text-slate-500 font-black italic border border-slate-700">
                        AS
                    </div>
                    <div>
                        <p class="text-sm font-black italic uppercase tracking-tight text-white">Alex Smith</p>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Awaiting rival response...</p>
                    </div>
                </div>
                <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest italic">Sent 2h ago</span>
            </div>
        </div>
    </div>

    
    <script src="https://js.pusher.com/8.0/pusher.min.js"></script>

<script>
        lucide.createIcons();

    // 1. Initialize Pusher
    const PUSHER_KEY = '0ebdc1f9053f6e8f9965';
    const PUSHER_CLUSTER = 'ap3';
    // Assume you have the User ID from PHP
    const USER_ID = "<?php echo $_SESSION['user_id']; ?>"; 

    const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        forceTLS: true
    });

    // 2. Subscribe to personal notification channel
    const userChannel = pusher.subscribe(`user-notification-${USER_ID}`);

    // 3. Listen for "Match Found" signal from PHP backend
    userChannel.bind('match-found', function(data) {
        console.log("Match Found! Redirecting...");
        // Redirect both players to the same battle instance
        window.location.href = `live_battle.php?match_id=${data.match_id}`;
    });

    // 4. Function to trigger the matchmaking process
    function startMatchmaking() {
    const btn = document.getElementById('quick-match-btn');
    
    // Get values from selects. If disabled, they default to their first option.
    const topic = document.getElementById('topic-select').value;
    const type = document.getElementById('type-select').value;
    const diff = document.getElementById('diff-select').value;

    btn.classList.add('opacity-80', 'cursor-not-allowed');
    btn.innerHTML = `
        <div class="flex items-center gap-4">
            <div class="w-8 h-8 border-4 border-rose-500 border-t-transparent rounded-full animate-spin"></div>
            <h3 class="text-2xl font-black italic uppercase">Syncing...</h3>
        </div>
    `;

    fetch('matchmaker.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            user_id: USER_ID,
            topic: topic,
            type: type,
            difficulty: diff
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'matched') {
            window.location.href = `live_battle.php?match_id=${data.match_id}`;
        }
    })
    .catch(err => console.error("Matchmaking error:", err));
}

    // Initialize lucide icons
    lucide.createIcons();
</script>
</body>
</html>