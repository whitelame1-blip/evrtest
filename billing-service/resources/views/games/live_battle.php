<?php
session_start();
require __DIR__ . '/../vendor/autoload.php'; // Ensure your DB connection is available

if (!isset($_SESSION['user_id']) || !isset($_GET['match_id'])) {
    header("Location: arena.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "aireadinggenerator");
$user_id = $_SESSION['user_id'];
$match_id = htmlspecialchars($_GET['match_id']);

// 1. Fetch match details to find the rival
$m_query = "SELECT p1_id, p2_id FROM matches WHERE match_id = ?";
$stmt = $conn->prepare($m_query);
$stmt->bind_param("s", $match_id);
$stmt->execute();
$m_res = $stmt->get_result()->fetch_assoc();

$rival_id = ($m_res['p1_id'] == $user_id) ? $m_res['p2_id'] : $m_res['p1_id'];

// 2. Fetch plan types for both
$u_query = "SELECT id, plan_type FROM users WHERE id IN (?, ?)";
$stmt_u = $conn->prepare($u_query);
$stmt_u->bind_param("ii", $user_id, $rival_id);
$stmt_u->execute();
$u_res = $stmt_u->get_result();

$plans = [];
while($row = $u_res->fetch_assoc()) {
    $plans[$row['id']] = $row['plan_type'];
}

$my_plan = $plans[$user_id] ?? 'free';
$rival_plan = $plans[$rival_id] ?? 'free';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Battle - The Arena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #0f172a; 
            color: white; 
        }
        .glass-panel { 
            background: rgba(30, 41, 59, 0.7); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255,255,255,0.1); 
        }
        /* The moving blue line across the card */
    .scan-line {
        animation: scan 4s linear infinite;
        opacity: 0.5;
    }

    @keyframes scan {
        0% { top: 0; }
        100% { top: 100%; }
    }

    /* Subtle pulse for the question card */
    #battle-ground.active {
        animation: pulse-border 2s infinite;
    }

    @keyframes pulse-border {
        0% { border-top-color: #6366f1; }
        50% { border-top-color: #818cf8; }
        100% { border-top-color: #6366f1; }
    }

    /* Option Button Hover Effect */
    .option-btn {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .option-btn:hover:not(:disabled) {
        transform: translateX(8px);
        background: rgba(99, 102, 241, 0.15);
        border-color: rgba(99, 102, 241, 0.5);
    }
    
    .option-btn:active {
        transform: scale(0.98);
    }

    /* Add a segmented grid look to the progress bars */
#my-progress, #rival-progress {
    background-image: linear-gradient(
        90deg, 
        rgba(255,255,255,0.1) 1px, 
        transparent 1px
    );
    background-size: 20% 100%; /* Creates 5 segments */
}

/* Make the bar look more like a "Pulse" when moving */
.progress-moving {
    animation: bar-glow 0.5s ease-out;
}

@keyframes bar-glow {
    0% { filter: brightness(1); }
    50% { filter: brightness(2) saturate(1.5); }
    100% { filter: brightness(1); }
}
    </style>
</head>
<body class="min-h-screen p-4 md:p-8 flex flex-col items-center">

    <div class="w-full max-w-5xl glass-panel rounded-3xl p-5 flex justify-between items-center mb-8 border-b-4 border-indigo-500 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/5 via-transparent to-rose-500/5 pointer-events-none"></div>

    <div class="flex items-center gap-4 w-1/3 relative z-10">
        <div class="relative">
            <div class="w-14 h-14 <?php echo $my_plan === 'pro' ? 'bg-indigo-500/20 border-2 border-indigo-400 shadow-[0_0_15px_rgba(99,102,241,0.4)]' : 'bg-slate-800 border border-slate-600'; ?> rounded-2xl flex items-center justify-center font-black text-xs transition-all">
                YOU
            </div>
            <div class="absolute -bottom-2 -right-1 px-2 py-0.5 rounded-md text-[8px] font-black uppercase tracking-tighter <?php echo $my_plan === 'pro' ? 'bg-indigo-500 text-white animate-pulse' : 'bg-slate-600 text-slate-300'; ?>">
                <?php echo $my_plan === 'pro' ? 'PRO' : 'FREE'; ?>
            </div>
        </div>
        <div class="flex-1">
    <div class="flex justify-between items-end mb-1">
        <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Your Position</span>
        <span id="my-score" class="font-black italic text-2xl text-white leading-none">Q1</span>
    </div>
    <div class="w-full bg-slate-800/50 h-2.5 rounded-full overflow-hidden border border-white/5">
        <div id="my-progress" class="bg-gradient-to-r from-indigo-600 to-indigo-400 h-full w-0 transition-all duration-700 ease-out shadow-[0_0_10px_rgba(99,102,241,0.5)]"></div>
    </div>
</div>
    </div>

    <div class="text-center w-1/4 relative z-10">
        <div class="inline-block px-4 py-1 rounded-full bg-slate-900/50 border border-slate-700 mb-2">
            <span class="text-rose-500 font-black italic text-2xl tracking-tighter">VS</span>
        </div>
        <div class="text-[9px] text-slate-500 font-mono tracking-[0.3em] uppercase">Sector: <?php echo substr($match_id, -6); ?></div>
    </div>

    <div class="flex items-center gap-4 w-1/3 justify-end relative z-10">
        <div class="flex-1 text-right">
            <div class="flex justify-between items-end mb-1 flex-row-reverse">
    <span class="text-[10px] font-bold text-rose-400 uppercase tracking-widest">Rival Position</span>
    <div class="flex flex-col items-end">
        <span id="rival-score" class="font-black italic text-2xl text-white leading-none">Q1</span>
        <span id="rival-status" class="text-[8px] text-rose-500/0 font-bold uppercase animate-pulse">Finished</span>
    </div>
</div>
            <div class="w-full bg-slate-800/50 h-2.5 rounded-full overflow-hidden border border-white/5 flex justify-end">
                <div id="rival-progress" class="bg-gradient-to-l from-rose-600 to-rose-400 h-full w-0 transition-all duration-700 ease-out shadow-[0_0_10px_rgba(244,63,94,0.5)]"></div>
            </div>
        </div>
        <div class="relative">
            <div class="w-14 h-14 <?php echo $rival_plan === 'pro' ? 'bg-rose-500/20 border-2 border-rose-400 shadow-[0_0_15px_rgba(244,63,94,0.4)]' : 'bg-slate-800 border border-slate-600'; ?> rounded-2xl flex items-center justify-center font-black text-xs transition-all">
                RVL
            </div>
            <div class="absolute -bottom-2 -left-1 px-2 py-0.5 rounded-md text-[8px] font-black uppercase tracking-tighter <?php echo $rival_plan === 'pro' ? 'bg-rose-500 text-white animate-pulse' : 'bg-slate-600 text-slate-300'; ?>">
                <?php echo $rival_plan === 'pro' ? 'PRO' : 'FREE'; ?>
            </div>
        </div>
    </div>
</div>

    <div id="battle-ground" class="w-full max-w-3xl mt-10 hidden group">
    <div class="glass-panel relative overflow-hidden rounded-[2rem] border-t-4 border-indigo-500 shadow-2xl transition-all duration-500">
        
        <div class="absolute top-0 left-0 w-full h-[2px] bg-indigo-500/50 shadow-[0_0_15px_indigo] scan-line"></div>

        <div class="p-6 md:p-10 relative z-10">
            <div id="question-header" class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 rounded-lg text-indigo-400 text-xs font-black tracking-tighter uppercase">
                        Question <span id="current-q-num">1</span> <span class="text-indigo-500/40">/ 05</span>
                    </span>
                </div>
                
                <div class="flex items-center gap-3 bg-slate-900/50 px-4 py-1.5 rounded-full border border-white/5">
                    <i data-lucide="timer" class="w-4 h-4 text-rose-500 animate-pulse"></i>
                    <span id="timer" class="text-indigo-400 font-mono font-bold tracking-widest text-lg">00:45</span>
                </div>
            </div>

            <div class="space-y-2 mb-10">
                <span class="text-[10px] font-black text-indigo-500/60 uppercase tracking-[0.4em] ml-1">Neural Input Required</span>
                <h3 id="question-text" class="text-2xl md:text-3xl font-extrabold leading-tight text-white tracking-tight drop-shadow-sm">
                    </h3>
            </div>

            <div id="options-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                </div>
        </div>

        <div class="bg-slate-900/80 px-10 py-3 border-t border-white/5 flex justify-between items-center">
            <div class="flex gap-1">
                <div class="w-1 h-1 bg-indigo-500 rounded-full animate-ping"></div>
                <div class="w-1 h-1 bg-indigo-500/40 rounded-full"></div>
                <div class="w-1 h-1 bg-indigo-500/40 rounded-full"></div>
            </div>
            <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest italic">Encrypted Connection Stable</span>
        </div>
    </div>
</div>

<div id="loading-state" class="w-full max-w-3xl text-center mt-10">
    <div class="inline-block animate-spin mb-4"><i data-lucide="refresh-cw" class="w-12 h-12 text-indigo-500"></i></div>
    <h2 class="text-3xl font-black italic uppercase text-slate-300">Synchronizing Battle Map...</h2>
    <p class="text-slate-500 mt-2 text-sm">Gemini AI is constructing your arena questions.</p>
</div>

    <script src="https://js.pusher.com/8.0/pusher.min.js"></script>
    <script>
        lucide.createIcons();
    
    let timeLeft = 45;
    let timerInterval = null;

// --- Timer Logic ---
function startTimer() {
    // Clear any existing timer
    clearInterval(timerInterval);
    timeLeft = 45;
    updateTimerDisplay();

    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            handleTimeout();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const timerElement = document.getElementById('timer');
    const seconds = timeLeft < 10 ? '0' + timeLeft : timeLeft;
    timerElement.innerText = `00:${seconds}`;
    
    // Visual warning when time is low
    if (timeLeft <= 10) {
        timerElement.classList.add('text-rose-500', 'animate-pulse');
    } else {
        timerElement.classList.remove('animate-pulse');
        timerElement.classList.add('text-indigo-400');
    }
}

function handleTimeout() {
    // Disable buttons so user can't click late
    document.querySelectorAll('.option-btn').forEach(b => b.disabled = true);
    
    // Visual feedback that time ran out
    const qText = document.getElementById('question-text');
    qText.innerHTML = `<span class="text-rose-500 font-bold">[TIME_EXPIRED]</span> ` + qText.innerText;

    // Move to next question after 1.5 seconds so they can see the "Time Expired" message
    setTimeout(nextQuestion, 1500);
}
        // 1. Setup Pusher Match Channel
        const pusher = new Pusher('0ebdc1f9053f6e8f9965', {
            cluster: 'ap3',
            forceTLS: true
        });

        const MY_USER_ID = "<?php echo $user_id; ?>";
        const MATCH_ID = "<?php echo $match_id; ?>";
        
        // Subscribe to the shared match channel
        const matchChannel = pusher.subscribe(`match-${MATCH_ID}`);

        // 2. Listen for Rival Progress
        matchChannel.bind('score-update', function(data) {
    // Log to confirm the message actually arrived from Pusher
    console.log("Pusher received:", data);

    // Use != (not !==) to handle string vs integer comparisons safely
    if (data.user_id != MY_USER_ID) {
        const rivalScoreEl = document.getElementById('rival-score');
        const rivalProgressEl = document.getElementById('rival-progress');
        const rivalStatusEl = document.getElementById('rival-status');

        // 1. Update the Text (Q1, Q2, etc)
        rivalScoreEl.innerText = `Q${data.current_q}`;
        
        // 2. Calculate Progress
        // If questions.length is 0, we fallback to 2 (your test count) 
        // to prevent division by zero.
        let total = (questions && questions.length > 0) ? questions.length : 2; 
        let percentage = (data.current_q / total) * 100;
        
        // 3. Apply the CSS
        rivalProgressEl.style.transition = "width 0.7s ease-out";
        rivalProgressEl.style.width = Math.min(percentage, 100) + "%";

        // 4. Check for completion
        if (data.current_q >= total) {
            rivalStatusEl.classList.remove('text-rose-500/0');
            rivalStatusEl.classList.add('text-rose-500');
            rivalScoreEl.innerText = "DONE";
        }
    } else {
        console.log("Ignoring my own update");
    }
});
function broadcastProgress() {
    let currentQ = currentQuestionIndex + 1;
    // Use the actual length of the questions array
    let totalQuestions = (questions && questions.length > 0) ? questions.length : 5;
    let progress = (currentQ / totalQuestions) * 100;

    // 1. Update YOUR local UI immediately
    const myScoreEl = document.getElementById('my-score');
    if (myScoreEl) myScoreEl.innerText = `Q${currentQ}`;
    
    const myBar = document.getElementById('my-progress');
    if (myBar) {
        myBar.style.width = progress + '%';
        myBar.classList.remove('progress-moving');
        void myBar.offsetWidth; // Force CSS reflow
        myBar.classList.add('progress-moving');
    }

    // 2. Send to server so Rival can see it via Pusher
    fetch(`update_live_score.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            match_id: MATCH_ID, 
            user_id: MY_USER_ID, 
            current_q: currentQ 
        })
    }).catch(err => console.error("Sync error:", err));
}

        // 3. Temporary Test Function
        let currentTotalScore = 0; 

function simulateScore() {
    currentTotalScore += 1;
    // We update the local variable, but for the UI, 
    // we keep showing the "Question Number" as we planned earlier.
    console.log("Current Score:", currentTotalScore);
}

        let questions = [];
let currentQuestionIndex = 0;

// 1. Start Polling for Match Readiness
// 1. Start Polling
const checkStatus = setInterval(async () => {
    try {
        const response = await fetch(`get_match_status.php?match_id=${MATCH_ID}`);
        // Check if response is actually OK before parsing JSON
        if (!response.ok) throw new Error("File not found");
        
        const data = await response.json();

        if (data.status === 'ready') {
            clearInterval(checkStatus);
            // Hide loading, show battle
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('battle-ground').classList.remove('hidden');
            loadQuestions();
        }
    } catch (err) { 
        console.error("Polling error:", err); 
    }
}, 2000);

async function loadQuestions() {
    try {
        // Double check this path! Is it fetch_questions.php or api/fetch_questions.php?
        const response = await fetch(`fetch_questions.php?match_id=${MATCH_ID}`);
        if (!response.ok) throw new Error("Failed to fetch questions");
        
        questions = await response.json();
        
        if(questions && questions.length > 0) {
            renderQuestion();
        } else {
            console.error("No questions returned from server");
        }
    } catch (error) {
        console.error("Error loading questions:", error);
        // Optional: show user an error message on screen
    }
}

// 3. Render the current question
function renderQuestion() {
    const q = questions[currentQuestionIndex];
    if (!q) return;
    broadcastProgress();
    // Reset UI and start timer
    document.getElementById('current-q-num').innerText = currentQuestionIndex + 1;
    document.getElementById('question-text').innerText = q.question_text;
    
    const optionsGrid = document.getElementById('options-grid');
    optionsGrid.innerHTML = ''; 

    ['a', 'b', 'c', 'd'].forEach((letter) => {
        if (!q['option_' + letter]) return;

        const btn = document.createElement('button');
        btn.className = "option-btn w-full p-4 text-left glass-panel border border-white/10 rounded-2xl flex items-center gap-4 group disabled:opacity-50 disabled:cursor-not-allowed transition-all";
        
        btn.innerHTML = `
            <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center group-hover:bg-indigo-500 group-hover:border-indigo-400 transition-all font-black text-sm uppercase">
                ${letter}
            </div>
            <span class="flex-1 font-semibold text-slate-200 group-hover:text-white transition-colors">${q['option_' + letter]}</span>
        `;
        
        btn.onclick = () => {
            clearInterval(timerInterval); // Stop timer on click
            document.querySelectorAll('.option-btn').forEach(b => b.disabled = true);
            submitAnswer(letter, q.correct_option);
        };
        
        optionsGrid.appendChild(btn);
    });

    if(window.lucide) lucide.createIcons();
    
    // START the 45s timer for this question
    startTimer();
}
function submitAnswer(selected, correct) {
    // Stop the timer immediately
    clearInterval(timerInterval); 

    const buttons = document.querySelectorAll('.option-btn');
    
    buttons.forEach(btn => {
        btn.disabled = true; 
        
        // Use a more robust way to get the letter
        const btnLetter = btn.querySelector('div').innerText.trim().toLowerCase();
        const correctLetter = correct.trim().toLowerCase();
        const selectedLetter = selected.toLowerCase();

        if (btnLetter === correctLetter) {
            btn.classList.add('border-emerald-500', 'bg-emerald-500/20');
            btn.querySelector('div').classList.replace('bg-slate-800', 'bg-emerald-500');
        } 
        
        if (btnLetter === selectedLetter && selectedLetter !== correctLetter) {
            btn.classList.add('border-rose-500', 'bg-rose-500/20');
            btn.querySelector('div').classList.replace('bg-slate-800', 'bg-rose-500');
        }
    });

    // Logical check
    if (selected.toLowerCase() === correct.toLowerCase()) {
        currentTotalScore++; 
    }
    fetch('store_session_choice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            question_index: currentQuestionIndex,
            choice: selected
        })
    });
    // Crucial: Use the updated variable in the nextQuestion save logic
    setTimeout(nextQuestion, 1500);
}
async function nextQuestion() {
    if (currentQuestionIndex < questions.length - 1) {
        currentQuestionIndex++;
        renderQuestion();
    } else {
        clearInterval(timerInterval);
        
        try {
            const response = await fetch('save_final_results.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    match_id: MATCH_ID, 
                    score: currentTotalScore // Updated variable name
                })
            });

            if (response.ok) {
                document.getElementById('battle-ground').classList.add('opacity-0');
                setTimeout(() => {
                    window.location.href = `arena_results.php?match_id=${MATCH_ID}`;
                }, 1000);
            }
        } catch (err) {
            console.error("Failed to save results:", err);
            window.location.href = `arena_results.php?match_id=${MATCH_ID}`;
        }
    }
}
</script>


</body>
</html>