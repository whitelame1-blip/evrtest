<?php
session_start();
require __DIR__ . '/../vendor/autoload.php'; 
$conn = new mysqli("localhost", "root", "", "aireadinggenerator");

$match_id = $_GET['match_id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch match details to see who is finished
$m_stmt = $conn->prepare("SELECT p1_id, p1_finished, p2_id, p2_finished FROM matches WHERE match_id = ?");
$m_stmt->bind_param("s", $match_id);
$m_stmt->execute();
$match_status = $m_stmt->get_result()->fetch_assoc();

$i_am_p1 = ($match_status['p1_id'] == $user_id);
$rival_finished = $i_am_p1 ? $match_status['p2_finished'] : $match_status['p1_finished'];

// 2. Get YOUR results
$stmt = $conn->prepare("SELECT * FROM match_results WHERE match_id = ? AND user_id = ?");
$stmt->bind_param("si", $match_id, $user_id);
$stmt->execute();
$myResult = $stmt->get_result()->fetch_assoc();

// 3. Get RIVAL results
$stmt_r = $conn->prepare("SELECT * FROM match_results WHERE match_id = ? AND user_id != ?");
$stmt_r->bind_param("si", $match_id, $user_id);
$stmt_r->execute();
$rivalResult = $stmt_r->get_result()->fetch_assoc();
$show_winner_ui = ($match_status['p1_finished'] && $match_status['p2_finished']);
$myScore = $myResult['score'] ?? 0;
// If rival isn't finished, we don't even want to display their partial score
$rivalScore = $rival_finished ? ($rivalResult['score'] ?? 0) : 0;

// 4. Get detailed answer breakdown
// 4. Get the questions specifically for this match
// NOTE: I fixed your JOIN logic here to pull from arena_match_questions correctly
$q_query = "SELECT * FROM arena_match_questions WHERE match_id = ? ORDER BY id ASC";
$q_stmt = $conn->prepare($q_query);
$q_stmt->bind_param("s", $match_id);
$q_stmt->execute();
$questions_data = $q_stmt->get_result();

// 5. Get YOUR choices from the session (We'll store these during the game)
$user_choices = $_SESSION['temp_match_choices'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <style>
    body {
        background: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #0f172a 100%);
        color: white;
        min-height: 100 screen;
        overflow-x: hidden;
    }

    .glass-panel {
        background: rgba(30, 41, 59, 0.4);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Animated background glow orbs */
    .glow-orb {
        position: absolute;
        width: 400px;
        height: 400px;
        border-radius: 50%;
        filter: blur(80px);
        z-index: 0;
        opacity: 0.15;
        animation: orb-float 20s infinite alternate;
    }

    @keyframes orb-float {
        from { transform: translate(-10%, -10%); }
        to { transform: translate(10%, 10%); }
    }

    /* Winner Card Shimmer */
    .winner-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(45deg, transparent, rgba(99, 102, 241, 0.1), transparent);
        transform: translateX(-100%);
        animation: shimmer 3s infinite;
        border-radius: inherit;
    }

    @keyframes shimmer {
        100% { transform: translateX(100%); }
    }

    .stat-card {
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.03);
    }
</style>
</head>
<body>
   <body class="p-4 md:p-8 font-['Plus_Jakarta_Sans',_sans-serif]">

    <div class="glow-orb bg-indigo-600 top-0 left-0"></div>
    <div class="glow-orb bg-rose-600 bottom-0 right-0" style="animation-delay: -10s;"></div>

    <div class="text-center mb-16 relative z-10" id="battle-header">
    <?php if(!$show_winner_ui): ?>
        <h1 class="text-5xl md:text-7xl font-black italic tracking-tighter uppercase text-indigo-400 animate-pulse">Synchronizing...</h1>
        <p class="text-slate-500 mt-2 font-bold tracking-widest uppercase text-sm">Waiting for rival to complete sequence</p>
    <?php elseif($myScore > $rivalScore): ?>
        <h1 class="text-7xl md:text-9xl font-black italic tracking-tighter uppercase text-white victory-glow">Victory</h1>
        <p class="text-indigo-400 mt-2 font-bold tracking-widest uppercase text-sm">You dominated the arena</p>
    <?php elseif($myScore < $rivalScore): ?>
        <h1 class="text-7xl md:text-9xl font-black italic tracking-tighter uppercase text-rose-500 defeat-glow">Defeat</h1>
        <p class="text-rose-400 mt-2 font-bold tracking-widest uppercase text-sm">Neural link severed</p>
    <?php else: ?>
        <h1 class="text-7xl md:text-9xl font-black italic tracking-tighter uppercase text-slate-400">Draw</h1>
        <p class="text-slate-400 mt-2 font-bold tracking-widest uppercase text-sm">Evenly matched</p>
    <?php endif; ?>
</div>

        

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
            
            <div class="glass-panel p-8 md:p-12 rounded-[3rem] border-t-2 border-indigo-500/50 relative transition-all duration-500 hover:-translate-y-2 <?php echo ($myScore >= $rivalScore) ? 'winner-card' : 'opacity-70 grayscale-[0.3]'; ?>">
                
                <div class="flex justify-between items-start mb-10">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-500 flex items-center justify-center text-white shadow-[0_0_20px_rgba(99,102,241,0.4)]">
                            <i data-lucide="user" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Operator</span>
                            <h3 class="font-black text-2xl uppercase italic leading-none text-white">YOU</h3>
                        </div>
                    </div>
                    <?php if($myScore >= $rivalScore): ?>
                        <span class="bg-indigo-500 text-[10px] font-black px-3 py-1 rounded-lg shadow-lg">MVP</span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-10">
                    <div class="stat-card p-5 rounded-2xl border-l-4 border-indigo-500">
                        <p class="stat-label text-slate-400 uppercase mb-1">Score</p>
                        <div class="text-4xl font-black text-white"><?php echo $myScore; ?></div>
                    </div>
                    <div class="stat-card p-5 rounded-2xl border-l-4 border-indigo-500">
                        <p class="stat-label text-slate-400 uppercase mb-1">Accuracy</p>
                        <div class="text-4xl font-black text-white"><?php echo ($myScore/5)*100; ?><span class="text-lg opacity-50">%</span></div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between items-end">
                        <span class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-400">Synchronization Level</span>
                        <span class="text-sm font-mono font-bold text-white"><?php echo ($myScore/5)*100; ?>%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-4 rounded-xl border border-white/5 p-1 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 via-indigo-400 to-white h-full rounded-lg transition-all duration-1000 shadow-[0_0_15px_rgba(99,102,241,0.5)]" 
                             style="width: <?php echo ($myScore/5)*100; ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="glass-panel p-8 md:p-12 rounded-[3rem] border-t-2 border-rose-500/50 relative transition-all duration-500 hover:-translate-y-2 <?php echo ($show_winner_ui && $rivalScore > $myScore) ? 'winner-card' : 'opacity-70 grayscale-[0.3]'; ?>">
    
    <div class="flex justify-between items-start mb-10">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-rose-500 flex items-center justify-center text-white shadow-[0_0_20px_rgba(244,63,94,0.4)]">
                <i data-lucide="zap" class="w-7 h-7"></i>
            </div>
            <div>
                <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Opposition</span>
                <h3 class="font-black text-2xl uppercase italic leading-none text-white">RIVAL</h3>
            </div>
        </div>
        <?php if($show_winner_ui && $rivalScore > $myScore): ?>
            <span class="bg-rose-500 text-[10px] font-black px-3 py-1 rounded-lg shadow-lg">MVP</span>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-10 relative">
        <?php if(!$rival_finished): ?>
            <div class="absolute inset-0 z-20 backdrop-blur-md bg-slate-900/40 rounded-2xl flex flex-col items-center justify-center border border-white/5">
                <i data-lucide="lock" class="w-6 h-6 text-rose-500 mb-2"></i>
                <span class="text-[10px] font-black uppercase text-rose-400 tracking-tighter">Data Encrypted</span>
            </div>
        <?php endif; ?>

        <div class="stat-card p-5 rounded-2xl border-l-4 border-rose-500">
            <p class="stat-label text-slate-400 uppercase mb-1">Score</p>
            <div class="text-4xl font-black text-white"><?php echo $rival_finished ? $rivalScore : '--'; ?></div>
        </div>
        <div class="stat-card p-5 rounded-2xl border-l-4 border-rose-500">
            <p class="stat-label text-slate-400 uppercase mb-1">Accuracy</p>
            <div class="text-4xl font-black text-white">
                <?php echo $rival_finished ? ($rivalScore/5)*100 : '--'; ?><span class="text-lg opacity-50">%</span>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <div class="flex justify-between items-end">
            <span class="text-[9px] font-black uppercase tracking-[0.2em] text-rose-400">Combat Consistency</span>
            <span class="text-sm font-mono font-bold text-white"><?php echo $rival_finished ? ($rivalScore/5)*100 . '%' : 'ANALYZING...'; ?></span>
        </div>
        <div class="w-full bg-slate-950 h-4 rounded-xl border border-white/5 p-1 overflow-hidden">
            <div class="bg-gradient-to-r from-rose-600 via-rose-400 to-white h-full rounded-lg transition-all duration-1000 shadow-[0_0_15px_rgba(244,63,94,0.5)]" 
                 style="width: <?php echo $rival_finished ? ($rivalScore/5)*100 : 0; ?>%"></div>
        </div>
    </div>
</div>
<?php 
$idx = 0; // Track question index
while($q = $questions_data->fetch_assoc()): 
    // Get what you clicked from the session using the index
    $my_choice = $user_choices[$idx] ?? null; 
    $correct_opt = strtolower($q['correct_option']);
    $is_correct = (strtolower($my_choice) == $correct_opt);
?>
    <div class="glass-panel rounded-3xl overflow-hidden border-l-4 <?php echo $is_correct ? 'border-emerald-500' : 'border-rose-500'; ?> mb-6">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] font-black px-3 py-1 rounded-md bg-slate-900 text-slate-400 uppercase">
                    Question <?php echo ($idx + 1); ?>
                </span>
                <?php if($is_correct): ?>
                    <span class="text-emerald-400 flex items-center gap-1 text-xs font-bold uppercase">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Correct
                    </span>
                <?php else: ?>
                    <span class="text-rose-400 flex items-center gap-1 text-xs font-bold uppercase">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Incorrect
                    </span>
                <?php endif; ?>
            </div>
            
            <h3 class="text-xl font-bold mb-6 text-white"><?php echo $q['question_text']; ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php foreach(['a', 'b', 'c', 'd'] as $opt): 
                    $opt_val = $q["option_$opt"];
                    if(!$opt_val) continue;
                    
                    $is_this_correct = ($opt == $correct_opt);
                    $is_this_mine = (strtolower($opt) == strtolower($my_choice));
                    
                    // Logic for coloring the options
                    $border_class = "border-white/5 bg-slate-900/50";
                    if($is_this_correct) $border_class = "border-emerald-500/50 bg-emerald-500/10 text-emerald-400";
                    if($is_this_mine && !$is_this_correct) $border_class = "border-rose-500/50 bg-rose-500/10 text-rose-400";
                ?>
                    <div class="p-4 rounded-xl border <?php echo $border_class; ?> text-sm flex justify-between items-center">
                        <span><b class="uppercase mr-2"><?php echo $opt; ?>:</b> <?php echo $opt_val; ?></span>
                        <?php if($is_this_mine): ?>
                            <span class="text-[8px] font-black uppercase px-2 py-0.5 rounded bg-white/10">Your Choice</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 p-4 bg-indigo-500/5 rounded-2xl border border-indigo-500/10">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="brain-circuit" class="w-4 h-4 text-indigo-400"></i>
                    <span class="text-[10px] font-black uppercase text-indigo-400 tracking-widest">Neural Logic</span>
                </div>
                <p class="text-sm text-slate-300 leading-relaxed italic">
                    <?php echo htmlspecialchars($q['explanation']); ?>
                </p>
            </div>
        </div>
    </div>
<?php 
    $idx++; 
endwhile; 
?>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-6 mt-20 relative z-10 pb-20">
            <a href="arena.php" class="w-full sm:w-auto px-12 py-5 bg-white text-slate-950 rounded-2xl font-black uppercase tracking-widest transition-all hover:scale-110 hover:shadow-[0_0_30px_rgba(255,255,255,0.3)] flex items-center justify-center gap-3">
                <i data-lucide="play-circle" class="w-5 h-5"></i>
                Re-Enter Arena
            </a>
            <a href="dashboard.php" class="w-full sm:w-auto px-12 py-5 glass-panel rounded-2xl font-black uppercase tracking-widest transition-all hover:bg-white/10 hover:scale-105 flex items-center justify-center gap-3">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                Command Center
            </a>
        </div>
    </div>
</body>
<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script>
    lucide.createIcons();

    <?php if(!$rival_finished): ?>
    // 1. Enable Pusher logging to see errors in the browser console
    Pusher.logToConsole = true;

    const pusher = new Pusher('0ebdc1f9053f6e8f9965', {
        cluster: 'ap3',
        forceTLS: true
    });

    const matchChannel = pusher.subscribe('match-<?php echo $match_id; ?>');

    // 2. Listen for the event
    matchChannel.bind('rival-finished', function(data) {
        console.log("Signal Received from Rival:", data);
        
        // Use loose inequality (!=) to handle string vs integer IDs
        if (data.finished_user_id != "<?php echo $user_id; ?>") {
            console.log("Rival detected. Triggering Reload...");
            window.location.reload();
        }
    });

    // 3. Test if connection is actually working
    pusher.connection.bind('connected', function() {
        console.log("Live Link Established. Waiting for Rival...");
    });
    <?php endif; ?>
</script>
</body>
</html>