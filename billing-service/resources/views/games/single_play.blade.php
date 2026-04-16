<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Solo Quest - Battle Prep IELTS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%); color: white; }
        .grid-bg { background-image: linear-gradient(#ffffff05 1px, transparent 1px), linear-gradient(90deg, #ffffff05 1px, transparent 1px); background-size: 30px 30px; mask-image: radial-gradient(circle, black, transparent 80%); }
        .mission-card { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-locked { cursor: not-allowed; filter: grayscale(1); opacity: 0.5; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
        .animate-shake { animation: shake 0.3s ease-in-out; }
        @keyframes shimmer { 100% { transform: translateX(100%); } }
        .animate-shimmer { animation: shimmer 2s infinite; }
        .layout-container { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5rem; width: 100%; max-width: 1200px; z-index: 10; }
        @media (min-width: 1024px) { .layout-container { flex-direction: row; align-items: stretch; } }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 overflow-hidden">
    <div class="fixed inset-0 grid-bg"></div>

    <div class="absolute top-6 left-6 z-20">
        <a href="/dashboard" class="flex items-center gap-2 text-slate-500 hover:text-rose-400 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Exit</span>
        </a>
    </div>

    @if($activeMissionId > 0)
        <div id="mission-alert" class="fixed top-6 right-6 z-50 w-72 md:w-80 transition-all duration-500">
            <div class="border-amber-500/30 bg-amber-500/10 rounded-2xl p-4 backdrop-blur-md shadow-2xl border">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0 border border-amber-500/40">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-amber-500 animate-pulse"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-[10px] font-black uppercase text-amber-500 tracking-widest mb-1 italic">Active Signal Detected</h4>
                        <p class="text-[11px] text-slate-300 font-semibold leading-relaxed mb-3">Mission in progress. Complete or scuttle the objective to enable new uplinks.</p>
                        <button onclick="document.getElementById('mission-alert').style.display='none'" class="text-[8px] font-black uppercase tracking-tighter text-slate-500 hover:text-white">Dismiss</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="layout-container">
        <main class="mission-card relative max-w-lg w-full rounded-[2.5rem] p-8 md:p-10 z-10 border-t border-white/10">
            
            @if(!$profileExists)
                <header class="mb-8 text-center">
                    <div class="w-20 h-20 bg-indigo-600/20 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-indigo-500/30">
                        <i data-lucide="shield-plus" class="w-10 h-10 text-indigo-400"></i>
                    </div>
                    <h1 class="text-3xl font-black italic tracking-tight uppercase leading-none mb-2">Registration</h1>
                    <p class="text-[10px] text-slate-400 uppercase tracking-[0.3em] font-bold">New Operator Detected</p>
                </header>

                <form action="{{ route('game.store_nickname') }}" method="POST" class="space-y-6">
                    @csrf
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.2em] px-1">Tactical Codename</label>
                        <input type="text" name="nickname" placeholder="Choose your name..." required maxlength="20"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-bold">
                    </div>
                    <button type="submit" class="w-full py-5 bg-indigo-600 rounded-2xl font-black uppercase italic tracking-widest text-white shadow-lg hover:scale-[1.02] active:scale-95 transition-all">
                        Initialize Profile
                    </button>
                </form>

            @else
                
                <header class="mb-6">
                    <div class="flex items-center justify-between mb-5 gap-2 md:gap-4 overflow-hidden">
                        <section class="flex-shrink-0">
                            @if($userAvatar)
                                <div class="relative group">
                                    <div class="absolute -inset-1.5 bg-gradient-to-tr from-indigo-600 via-purple-500 to-indigo-400 rounded-full blur opacity-30 group-hover:opacity-60 transition duration-700"></div>
                                    <div class="relative w-12 h-12 md:w-20 md:h-20 rounded-full border-2 border-white/20 overflow-hidden bg-slate-900 ring-4 ring-black/20 shadow-2xl">
                                        <img src="{{ asset($userAvatar) }}" class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500" alt="Operator">
                                    </div>
                                </div>
                            @endif
                        </section>

                        <div class="flex-1 min-w-0 flex flex-col gap-1">
                            <span class="text-white font-black italic uppercase tracking-tight text-sm md:text-2xl drop-shadow-md truncate">
                                {{ $user['nickname'] }}
                            </span>
                            <div class="flex items-center gap-1.5 bg-indigo-950/40 px-2 py-0.5 md:px-3 md:py-1 rounded-lg border border-indigo-500/20 w-fit">
                                <i data-lucide="compass" class="w-3 h-3 md:w-3.5 md:h-3.5 text-indigo-400"></i>
                                <span class="font-black tracking-widest text-[7px] md:text-[10px] text-indigo-100 italic whitespace-nowrap">LVL {{ $user['current_level'] }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-end py-1 flex-shrink-0">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <span class="text-[7px] md:text-[9px] font-black text-slate-400 uppercase tracking-widest opacity-80">Neural Link</span>
                                <i data-lucide="{{ $limitReached ? 'battery-low' : 'zap' }}" class="w-3 h-3 md:w-3.5 md:h-3.5 {{ $limitReached ? 'text-rose-500' : 'text-indigo-400 animate-pulse' }}"></i>
                            </div>
                            
                            <div class="w-16 md:w-28 h-1.5 md:h-2 bg-black/40 rounded-full overflow-hidden border border-white/5 p-[1px]">
                                <div class="h-full rounded-full transition-all duration-1000 {{ $limitReached ? 'bg-gradient-to-r from-rose-600 to-rose-400 shadow-[0_0_10px_#f43f5e]' : 'bg-gradient-to-r from-indigo-600 to-indigo-400 shadow-[0_0_10px_#6366f1]' }}" 
                                     style="width: {{ ($totalAvailable > 0) ? ($remaining / $totalAvailable) * 100 : 0 }}%"></div>
                            </div>

                            <div class="flex items-center gap-2 md:gap-3 mt-1.5">
                                <div class="flex flex-col items-start">
                                    <span class="text-[10px] md:text-xs font-black italic tracking-tighter {{ $limitReached ? 'text-rose-500 drop-shadow-[0_0_8px_rgba(244,63,94,0.4)]' : 'text-indigo-400' }}">
                                        {{ str_pad($remaining, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                                <div class="h-3 md:h-4 w-[1px] bg-white/10 rotate-[20deg]"></div>
                                <div class="flex flex-col">
                                    <span class="text-[6px] md:text-[8px] font-black uppercase tracking-[0.1em] md:tracking-[0.2em] {{ $limitReached ? 'text-rose-400/70' : 'text-slate-500' }}">Attempts</span>
                                    <span class="text-[5px] md:text-[7px] font-bold uppercase tracking-widest text-slate-600 -mt-1">Remaining</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative w-full h-1.5 bg-black/40 rounded-full overflow-hidden border border-white/5">
                        <div class="h-full bg-gradient-to-r from-indigo-500 via-indigo-400 to-purple-500 transition-all duration-1000 shadow-[0_0_15px_rgba(99,102,241,0.3)]" 
                             style="width: {{ $progression }}%"></div>
                    </div>
                </header>

                <section class="flex items-center gap-5 mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-[0_0_20px_rgba(79,70,229,0.4)]">
                        <i data-lucide="zap" class="w-8 h-8 text-white fill-white"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black italic tracking-tight uppercase leading-none">Solo Quest</h1>
                        <p class="text-[10px] text-slate-400 mt-2 uppercase tracking-[0.3em] font-bold">Battle Protocol Active</p>
                    </div>
                </section>

                <section class="mb-6">
                    <div class="flex justify-between items-end mb-4 px-1">
                        <h3 class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.2em]">Set Intensity</h3>
                        <span id="lock-msg" class="text-[9px] text-rose-400 font-bold uppercase hidden animate-pulse">Lvl 5 Required</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <button onclick="setDifficulty('easy')" id="btn-easy" class="difficulty-btn relative flex flex-col items-center gap-2 p-4 rounded-2xl border border-white/5 bg-white/5 transition-all">
                            <i data-lucide="leaf" class="w-5 h-5 text-emerald-500"></i>
                            <span class="text-[10px] font-bold uppercase">Easy</span>
                        </button>
                        <button onclick="setDifficulty('medium')" id="btn-medium" class="difficulty-btn relative flex flex-col items-center gap-2 p-4 rounded-2xl border-indigo-500/50 bg-indigo-500/20 ring-2 ring-indigo-500/50 transition-all">
                            <i data-lucide="swords" class="w-5 h-5 text-indigo-400"></i>
                            <span class="text-[10px] font-bold uppercase">Medium</span>
                        </button>
                        <button onclick="setDifficulty('hard')" id="btn-hard" class="difficulty-btn {{ ($user['current_level'] < 5) ? 'btn-locked' : '' }} relative flex flex-col items-center gap-2 p-4 rounded-2xl border border-white/5 bg-white/5 transition-all">
                            <i data-lucide="flame" class="w-5 h-5 text-rose-500"></i>
                            <span class="text-[10px] font-bold uppercase">Hard</span>
                        </button>
                    </div>
                </section>

                <section class="grid grid-cols-3 gap-2 mb-8 p-4 bg-black/40 rounded-3xl border border-white/5">
                    <div class="flex flex-col items-center border-r border-white/10">
                        <span class="text-[8px] text-slate-500 uppercase font-black mb-1">Items</span>
                        <span id="q-display" class="text-xs font-bold text-white">20 Qs</span>
                    </div>
                    <div class="flex flex-col items-center border-r border-white/10">
                        <span class="text-[8px] text-slate-500 uppercase font-black mb-1">Time</span>
                        <span id="t-display" class="text-xs font-bold text-emerald-400">45s/Q</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-[8px] text-slate-500 uppercase font-black mb-1">Reward</span>
                        <span id="xp-display" class="text-xs font-bold text-amber-400">50 XP</span>
                    </div>
                </section>

                <section class="mb-8">
                    <select id="mission-focus" class="w-full bg-black/90 border border-white/10 rounded-2xl px-6 py-4 text-sm font-bold text-white focus:outline-none focus:border-indigo-500 transition-all cursor-pointer">
                        <option value="mixed">Mixed Training</option>
                        <option value="grammar"> Grammar Overload</option>
                        <option value="vocabulary"> Lexical Strike</option>
                        <option value="paraphrase">Tactical Paraphrase</option>
                    </select>
                </section>

                <section>
                    @if($activeMissionId > 0)
                        <div class="space-y-3">
                            <a href="{{ route('game.single_play_game', ['mission_id' => $activeMissionId]) }}" class="relative w-full py-5 bg-amber-600 rounded-2xl flex items-center justify-center gap-3 overflow-hidden hover:scale-[1.02] transition-all shadow-lg">
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full animate-shimmer"></div>
                                <span class="relative text-white font-black uppercase italic tracking-widest flex items-center gap-3">Resume Mission <i data-lucide="play-circle"></i></span>
                            </a>
                            <button onclick="abortMission({{ $activeMissionId }})" class="w-full text-[9px] font-black uppercase text-slate-500 hover:text-rose-500 transition-colors">Scuttle Current Mission</button>
                        </div>
                    @else
                        @if($limitReached)
                            <div class="space-y-6">
                                <button disabled class="w-full py-5 bg-slate-800/50 border border-rose-500/20 rounded-2xl opacity-90 flex flex-col items-center justify-center relative overflow-hidden">
                                    <div class="absolute inset-0 bg-rose-500/5 animate-pulse"></div>
                                    <span class="relative z-10 text-slate-500 font-black uppercase italic tracking-widest flex items-center gap-3">
                                        Mission Locked <i data-lucide="lock" class="w-4 h-4 text-rose-500"></i>
                                    </span>
                                    <span id="countdown-timer" class="relative z-10 text-lg font-black text-rose-500 mt-1 font-mono tracking-tighter">00:00:00</span>
                                </button>
                                <div class="text-center">
                                    <button onclick="handleAdUplink()" class="px-4 py-2 rounded-full border border-white/5 bg-white/[0.02] hover:bg-indigo-500/10 transition-all text-[10px] font-black uppercase text-slate-400 hover:text-indigo-400 flex items-center gap-2 mx-auto">
                                        Emergency Ad Uplink
                                    </button>
                                </div>
                            </div>
                        @else
                            <button onclick="startGame()" id="startBtn" class="w-full py-5 bg-indigo-600 rounded-2xl font-black uppercase italic tracking-widest text-white shadow-lg hover:scale-[1.02] active:scale-95 transition-all">
                                Deploy Solo Quest
                            </button>
                        @endif
                    @endif
                </section>
            @endif
        </main>

        @if($profileExists && $limitReached)
            <aside class="mission-card w-full lg:w-80 rounded-[2.5rem] p-6 border-t border-white/10 flex flex-col">
                <header class="mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 bg-amber-500/20 rounded-lg border border-amber-500/30"><i data-lucide="shopping-cart" class="w-4 h-4 text-amber-500"></i></div>
                        <h2 class="text-sm font-black uppercase italic tracking-widest">Neural Shop</h2>
                    </div>
                    <div class="bg-black/40 rounded-2xl p-4 border border-white/5 flex justify-between items-center">
                        <span class="text-[10px] font-bold text-slate-500 uppercase">Available Credits</span>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400 font-black">{{ $userCredits }}</span>
                            <i data-lucide="database" class="w-3 h-3 text-amber-500"></i>
                        </div>
                    </div>
                </header>

                <div class="space-y-4 flex-1">
                    <div class="p-5 rounded-2xl bg-white/5 border border-white/10">
                        <p class="text-xs font-black uppercase italic text-white mb-1">Bulk Energy Refill</p>
                        <p class="text-[9px] text-indigo-400 font-bold mb-4 uppercase">1 Credit = 5 Attempts</p>
                        <div class="space-y-3">
                            <input type="number" id="purchase-amount" min="1" value="1" oninput="updatePreview()" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm font-bold text-white focus:outline-none focus:border-indigo-500">
                            <div class="bg-indigo-500/10 rounded-xl p-3 border border-indigo-500/20 text-center">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Granting:</p>
                                <p class="text-lg font-black text-indigo-400 italic"><span id="attempt-preview">5</span> ATTEMPTS</p>
                            </div>
                            <button onclick="buyBulkAttempt({{ $userCredits }})" class="w-full py-3 rounded-xl bg-indigo-600 font-black uppercase tracking-widest text-white text-[10px] hover:scale-[1.02] transition-all">Authorize</button>
                        </div>
                    </div>
                </div>
            </aside>
        @endif
    </div>

    <script>
        lucide.createIcons();
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const USER_LEVEL = {{ $user['current_level'] ?? 1 }}; 
        const ATTEMPT_RATE = 5; 
        let currentDifficulty = 'medium';

        const settings = {
            easy: { q: '10 Qs', xp: '25 XP', time: '60s', theme: '#10b981', minLvl: 0 },
            medium: { q: '20 Qs', xp: '50 XP', time: '45s', theme: '#4f46e5', minLvl: 0 },
            hard: { q: '40 Qs', xp: '200 XP', time: '30s', theme: '#e11d48', minLvl: 5 }
        };

        function setDifficulty(level) {
            const config = settings[level];
            const btn = document.getElementById(`btn-${level}`);

            if (USER_LEVEL < config.minLvl) {
                btn.classList.add('animate-shake');
                document.getElementById('lock-msg').classList.remove('hidden');
                setTimeout(() => btn.classList.remove('animate-shake'), 300);
                return;
            }

            currentDifficulty = level;
            document.getElementById('q-display').innerText = config.q;
            document.getElementById('xp-display').innerText = config.xp;
            document.getElementById('t-display').innerText = config.time + '/Q';
            
            document.querySelectorAll('.difficulty-btn').forEach(b => {
                b.className = "difficulty-btn relative flex flex-col items-center gap-2 p-4 rounded-2xl border border-white/5 bg-white/5 transition-all";
                if(b.id === 'btn-hard' && USER_LEVEL < 5) b.classList.add('btn-locked');
            });

            btn.classList.add('bg-indigo-500/20', 'border-indigo-500/50', 'ring-2', 'ring-indigo-500/50');
            const startBtn = document.getElementById('startBtn');
            if(startBtn) startBtn.style.backgroundColor = config.theme;
        }

        async function startGame() {
            const remaining = {{ $remaining ?? 0 }};
            if (remaining <= 0) {
                alert("Neural energy depleted. Visit the shop.");
                return;
            }

            const startBtn = document.getElementById('startBtn');
            const originalText = startBtn.innerHTML;
            
            startBtn.innerHTML = `<span>Connecting Uplink...</span>`;
            startBtn.classList.add('animate-pulse', 'pointer-events-none');

           try {
    const response = await fetch('/api/generate-game', { // Check if /api/ is needed based on your routes file
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'Accept': 'application/json', // Tell Laravel you want JSON back
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            difficulty: currentDifficulty,
            level: USER_LEVEL,
            num_questions: parseInt(settings[currentDifficulty].q),
            focus: document.getElementById('mission-focus').value
        })
    });

                const data = await response.json();
                if (!data.task_id) throw new Error(data.error || "Uplink Rejected");
                pollMissionStatus(data.task_id);

            } catch (err) {
                alert("Error: " + err.message);
                startBtn.classList.remove('animate-pulse', 'pointer-events-none');
                startBtn.innerHTML = originalText;
            }
        }

        function pollMissionStatus(taskId) {
    const startBtn = document.getElementById('startBtn');
    const statusText = document.getElementById('status-message'); // Optional: if you have a status label

    if(startBtn) startBtn.innerHTML = `<span>Synthesizing Game...</span>`;

    const checkInterval = setInterval(async () => {
        try {
            // Updated URL to match Laravel route
            const r = await fetch(`/api/task-status/${encodeURIComponent(taskId)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const statusData = await r.json();

            // Check for SUCCESS
            if (statusData.state === 'SUCCESS') {
                clearInterval(checkInterval);
                
                // Laravel controller returns result_id or result.mission_id
                const mid = statusData.result_id || (statusData.result && statusData.result.mission_id);
                
                if (mid) {
                    window.location.href = `/single-play/game?mission_id=${mid}&diff=${currentDifficulty}`;
                } else {
                    throw new Error("Mission ID missing from successful response");
                }

            } else if (statusData.state === 'FAILURE' || statusData.error) {
                clearInterval(checkInterval);
                alert("AI Engine failed: " + (statusData.error || "Generation error"));
                location.reload();
            } else {
                // ENHANCEMENT: Show queue position if available
                if (statusData.queue_message && startBtn) {
                    startBtn.innerHTML = `<span>${statusData.queue_message}</span>`;
                }
            }
        } catch (e) {
            console.error("Polling failed", e);
        }
    }, 2500);
}

        function updatePreview() {
            const val = parseInt(document.getElementById('purchase-amount').value) || 0;
            document.getElementById('attempt-preview').innerText = val * ATTEMPT_RATE;
        }

       async function buyBulkAttempt(currentBalance) {
    const purchaseInput = document.getElementById('purchase-amount');
    const credits = parseInt(purchaseInput.value);
    const attempts = credits * ATTEMPT_RATE;

    // 1. Validation check
    if (isNaN(credits) || credits <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'INVALID INPUT',
            text: 'Please enter a valid amount of credits.',
            background: '#1e293b', color: '#fff', confirmButtonColor: '#6366f1'
        });
        return;
    }

    if (currentBalance < credits) {
        Swal.fire({
            icon: 'warning',
            title: 'INSUFFICIENT CREDITS',
            text: 'Your current balance is too low for this transaction.',
            background: '#1e293b', color: '#fff', confirmButtonColor: '#ef4444'
        });
        return;
    }

    // 2. Themed Confirmation
    const result = await Swal.fire({
        title: 'CONFIRM EXCHANGE',
        html: `Process <b class="text-indigo-400">${credits} credits</b> for <b class="text-emerald-400">${attempts} attempts</b>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'AUTHORIZE',
        cancelButtonText: 'ABORT',
        background: '#0f172a',
        color: '#fff',
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#334155',
        reverseButtons: true
    });

    if (!result.isConfirmed) return;

    // 3. Show "Processing" Loader
    Swal.fire({
        title: 'ESTABLISHING UPLINK',
        text: 'Synchronizing with secure credit ledger...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#0f172a', color: '#fff'
    });

    try {
        const response = await fetch('/credits/exchange', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ credits, attempts })
        });

        const data = await response.json();

        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'TRANSACTION SECURED',
                text: `Successfully added ${attempts} attempts to your account.`,
                background: '#0f172a', color: '#fff', confirmButtonColor: '#10b981'
            });
            window.location.reload();
        } else {
            throw new Error(data.message || "Uplink Rejected");
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'SYSTEM ERROR',
            text: error.message || 'The transaction could not be completed.',
            background: '#0f172a', color: '#fff', confirmButtonColor: '#ef4444'
        });
    }
}

        async function abortMission(mid) {
    const result = await Swal.fire({
        title: 'SCUTTLE MISSION?',
        text: "Progress will be purged. This action is irreversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'CONFIRM ABORT',
        cancelButtonText: 'STAY ON MISSION',
        background: '#0f172a',
        color: '#fff',
        confirmButtonColor: '#ef4444', // Red for destruction
        cancelButtonColor: '#334155',
        reverseButtons: true
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/mission/${mid}/abort`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'MISSION TERMINATED',
                    text: data.message,
                    icon: 'success',
                    background: '#0f172a',
                    color: '#fff'
                });
                window.location.href = '/single-play'; // Redirect to HQ
            } else {
                Swal.fire('ERROR', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('UPLINK ERROR', 'Could not reach command.', 'error');
        }
    }
}

        // Timer initialization
        const resetTime = {{ $resetTimestamp ?? 0 }} * 1000; 
        if (resetTime > 0) {
            const timerElement = document.getElementById('countdown-timer');
            const updateCountdown = () => {
                const distance = resetTime - new Date().getTime();
                if (distance < 0) {
                    timerElement.innerText = "SYNC COMPLETE";
                    clearInterval(timerInterval);
                    setTimeout(() => location.reload(), 1000);
                    return;
                }
                const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)).toString().padStart(2, '0');
                const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)).toString().padStart(2, '0');
                const s = Math.floor((distance % (1000 * 60)) / 1000).toString().padStart(2, '0');
                timerElement.innerText = `${h}:${m}:${s}`;
            };
            const timerInterval = setInterval(updateCountdown, 1000);
            updateCountdown();
        }
    </script>
</body>
</html>