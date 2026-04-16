@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\DB;

    // 1. Authentication Check & User ID
    // Laravel handles the session_start and redirect automatically via middleware, 
    // but we will define $user_id to keep your variables consistent.
    $user = Auth::user();
    $user_id = $user ? $user->id : null;

    // 2. CSRF Token
    // Laravel generates this for you. You can access it via csrf_token()
    $csrf_token = csrf_token();

    // 3. Set Defaults
    $plan_type = 'free';
    $preview_attempts = 0;

    if ($user_id) {
        // 4. JOIN users and credits using Laravel's Query Builder
        // This replaces your $conn->prepare and bind_param logic
        $data = DB::table('users as u')
            ->leftJoin('credits as c', 'u.id', '=', 'c.user_id')
            ->where('u.id', $user_id)
            ->select('u.plan_type', 'c.listening_preview_attempts')
            ->first();

        if ($data) {
            $plan_type = $data->plan_type;
            
            // Use null-coalescing just like your original code
            $preview_attempts = $data->listening_preview_attempts ?? 0;

            // 5. Store in Session (Laravel style)
            // This makes them available across the app via session('plan_type')
            session([
                'plan_type' => $plan_type,
                'preview_attempts' => $preview_attempts
            ]);
        }
    }

    $pendingTask = DB::table('user_tasks')
        ->where('user_id', $user_id)
        ->where('status', 'pending')
        ->select('task_id')
        ->first();

    // 3. Create a clean variable for the JS to use
    $activeTaskId = $pendingTask ? $pendingTask->task_id : null;
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
    
    .component-wrapper { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
    }
    .glass { 
        background: rgba(255, 255, 255, 0.95); 
        backdrop-filter: blur(10px); 
        border: 1px solid #e2e8f0; 
    }
    input[type=range] { 
        -webkit-appearance: none; 
        height: 6px; 
        background: #e2e8f0; 
        border-radius: 99px; 
        width: 100%; 
    }
    input[type=range]::-webkit-slider-thumb { 
        -webkit-appearance: none; 
        width: 16px; 
        height: 16px; 
        background: #4f46e5; 
        border-radius: 50%; 
        cursor: pointer; 
        transition: 0.2s; 
    }
    input[type=range]:disabled::-webkit-slider-thumb { 
        background: #cbd5e1; 
    }
    select option:disabled {
        color: #64748b !important;
        background-color: #020617 !important;
        font-style: italic;
    }
    #listening-qType:focus {
        box-shadow: 0 0 20px rgba(99, 102, 241, 0.1);
    }
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    #generation-progress {
        background-size: 200% 100%;
        animation: flow 3s linear infinite;
    }
    @keyframes flow {
        0% { background-position: 0% 50%; }
        100% { background-position: 200% 50%; }
    }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="component-wrapper relative w-full bg-slate-50 rounded-[2rem] sm:rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden mb-10">

    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-2 w-full sm:w-auto justify-center sm:justify-start">
            <div class="bg-indigo-600 text-white p-2 rounded-lg"><i data-lucide="mic-2" class="w-5 h-5 sm:w-6 sm:h-6"></i></div>
            <span class="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-800">LISTENING<span class="text-indigo-600">Lab</span></span>
        </div>

        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3 sm:gap-6 w-full sm:w-auto">
            <div class="flex items-center gap-0.5 w-full sm:w-auto justify-end">
                        <div class="flex items-center justify-center gap-2 bg-amber-50 text-amber-700 px-3 sm:px-4 py-2 rounded-l-2xl border border-amber-100 border-r-0 shadow-sm w-full sm:w-auto" title="Available Credits">
                            <i data-lucide="zap" class="w-4 h-4 fill-amber-400 text-amber-400 shrink-0"></i>
                            <span class="text-[10px] sm:text-xs font-black uppercase tracking-tighter whitespace-nowrap">
                                <span id="creditCountListening"><?php echo $userCredits ?? 3; ?></span> Credits
                            </span>
                        </div>
                        <button onclick="showPage('buycredits-page')" type="button" class="flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white w-9 h-9 sm:w-9 sm:h-9 rounded-r-2xl border border-amber-500 shadow-sm transition-all hover:scale-105 active:scale-95 group shrink-0" title="Buy Credits">
                            <i data-lucide="plus" class="w-4 h-4 sm:w-5 sm:h-5 transition-transform group-hover:rotate-90"></i>
                        </button>
                    </div>

            <div class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider sm:border-l sm:pl-6 border-slate-200">
                Plan: <span class="<?php echo $plan_type === 'pro' ? 'text-indigo-600' : 'text-slate-500'; ?>">
                    <?php echo strtoupper($plan_type); ?>
                </span>
            </div>
        </div>
    </header>

    <main class="p-4 sm:p-6 lg:p-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
            
            <div class="lg:col-span-7 space-y-6">
                <div class="glass p-5 sm:p-8 rounded-3xl shadow-sm">
                    <div class="flex items-center gap-3 sm:gap-4 mb-6">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center shrink-0">
                            <i data-lucide="settings-2" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900">Test Configuration</h2>
                            <p class="text-xs sm:text-sm text-slate-500">Customize your practice session</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <div class="flex justify-between items-end mb-2 px-1">
                                <label class="block text-[10px] sm:text-xs font-bold text-slate-500 uppercase">Topic</label>
                                <label class="flex items-center gap-2 cursor-pointer ">
                                    <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 group-hover:text-indigo-600 transition-colors uppercase">AI Random</span>
                                    <div class="relative inline-block w-8 h-4">
                                        <input type="checkbox" id="listening-randomTopic" class="hidden peer" onchange="toggleListeningTopicInput()">
                                        <div class="absolute inset-0 bg-slate-200 rounded-full peer-checked:bg-indigo-600 transition-all"></div>
                                        <div class="absolute left-1 top-1 bg-white w-2 h-2 rounded-full peer-checked:translate-x-4 transition-all"></div>
                                    </div>
                                </label>
                            </div>
                            <div class="relative">
                                <input required id="listening-topic" type="text" placeholder="e.g. A conversation about renting an apartment" 
                                    class="w-full p-3 sm:p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm sm:text-base font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                <div id="listening-ai-badge" class="hidden absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 px-2 sm:px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-[9px] sm:text-[10px] font-black uppercase animate-pulse">
                                    <i data-lucide="dices" class="w-3 h-3"></i> AI Deciding
                                </div>
                            </div>
                            <div class="flex justify-between mt-2 px-1">
                                <p id="topic-error-msg" class="text-[10px] sm:text-xs text-red-500 font-medium hidden">Topic is too long (Max 50 words)</p>
                                <p class="text-[10px] sm:text-xs text-slate-400 ml-auto"><span id="word-count">0</span> / 50 words</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] sm:text-xs font-bold text-slate-500 uppercase mb-2 px-1">Section</label>
                                <select id="listening-section" onchange="updateListeningRequirements(); updateCreditCost();" class="w-full p-3 sm:p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 cursor-pointer outline-none">
                                    <option value="1">Part 1: Social Dialogue</option>
                                    <option value="2">Part 2: Social Monologue</option>
                                    <option value="3">Part 3: Academic Discussion</option>
                                    <option value="4">Part 4: Academic Lecture</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] sm:text-xs font-bold text-slate-500 uppercase mb-2 px-1">Difficulty</label>
                                <div class="flex bg-slate-100 p-1 rounded-xl gap-1">
                                    <button onclick="setListeningDifficulty('easy')" class="listening-diff-btn flex-1 py-2.5 sm:py-3 text-[9px] sm:text-[10px] font-black rounded-lg transition-all bg-white shadow-sm text-indigo-600" data-level="easy">EASY</button>
                                    <button onclick="setListeningDifficulty('medium')" class="listening-diff-btn flex-1 py-2.5 sm:py-3 text-[9px] sm:text-[10px] font-black rounded-lg transition-all text-slate-500 hover:bg-white/50" data-level="medium">MEDIUM</button>
                                    <button onclick="setListeningDifficulty('hard')" class="listening-diff-btn flex-1 py-2.5 sm:py-3 text-[9px] sm:text-[10px] font-black rounded-lg transition-all text-slate-500 hover:bg-white/50" data-level="hard">HARD</button>
                                </div>
                                <input type="hidden" id="listening-difficulty-hidden" value="easy">
                            </div>
                        </div>

                        <div>
                            <?php $isPro = ($user['plan_type'] === 'pro'); ?>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2 px-1">
                                Question Style
                            </label>
                            <div class="relative ">
                                <select id="listening-qType" onchange="updateCreditCost()" 
                                    class="w-full p-3 sm:p-4 bg-slate-50  border border-slate-200  rounded-2xl text-sm sm:text-base font-bold text-dark cursor-pointer outline-none focus:border-indigo-500 transition-all appearance-none">
                                    <option value="completion">📝 Fill in the Blanks</option>
                                    <option value="multiple_choice">✅ Multiple Choice</option>
                                    <option value="matching">🔗 Matching Items</option>
                                    <option value="mixed">🎲 Mixed Types (Standard)</option>
                                    <option value="map" disabled class="text-slate-500 bg-slate-950">⏳ Map Labeling (Coming Soon)</option>
                                </select>
                                
                                <div id="mixed-selection-panel" class="hidden mt-4 p-4 sm:p-5 bg-indigo-50/50 border border-indigo-100 rounded-2xl animate-in fade-in slide-in-from-top-2 duration-300">
                                    <div class="flex justify-between items-center mb-3">
                                        <label class="text-[9px] sm:text-[10px] font-black text-indigo-600 uppercase tracking-widest">
                                           Select exactly 2 types to mix
                                        </label>
                                        <span id="mixed-count-badge" class="px-2 py-0.5 bg-indigo-600 text-white text-[9px] sm:text-[10px] font-bold rounded-full">0/3</span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="mixed-options-container"></div>
                                </div>
                                
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>

                        <button onclick="generateListeningContent()" id="listening-genBtn" 
                            class="group relative w-full py-4 sm:py-5 bg-slate-900 text-white rounded-2xl font-bold text-base sm:text-lg hover:bg-indigo-600 transition-all duration-300 flex items-center justify-center gap-2 sm:gap-3 shadow-xl hover:shadow-indigo-200 overflow-hidden mt-4">
                            
                            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>

                            <i data-lucide="zap" class="w-4 h-4 sm:w-5 sm:h-5 text-amber-400 fill-amber-400"></i> 
                            <span class="tracking-tight">Generate Test</span>

                            <div class="ml-2 flex items-center gap-1.5 bg-white/10 px-2 sm:px-3 py-1 rounded-lg border border-white/20 shadow-inner">
                                <span id="btn-credit-cost" class="text-white font-black text-lg sm:text-xl leading-none">1</span>
                                <div class="flex flex-col items-start">
                                    <span class="text-[7px] sm:text-[8px] uppercase font-black leading-none text-indigo-200">Credits</span>
                                    <i data-lucide="coins" class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-amber-400"></i>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <div id="listening-exam-view" class="hidden space-y-6">
                    <div class="glass p-5 sm:p-8 rounded-3xl shadow-sm">
                        <audio id="listening-audio" controls class="w-full mb-6"></audio>
                        <div id="listening-limit-banner" class="hidden mb-4 p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                            <p id="listening-limit-text" class="text-xs font-bold text-indigo-700 text-center"></p>
                        </div>
                        <div id="listening-questions-container" class="space-y-4"></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="glass p-5 sm:p-6 rounded-3xl lg:sticky lg:top-6">
                   <div class="flex justify-between items-center mb-6">
    <h3 class="font-extrabold text-slate-800 flex items-center gap-2 text-sm sm:text-base">
        <i data-lucide="sliders" class="w-4 h-4"></i> Voice Studio
    </h3>
    <div class="flex items-center gap-2">
        <div id="preview-counter-container" class="hidden sm:flex flex-col items-end mr-2">
             <div class="flex items-center gap-1.5 mb-1">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Preview Bank</span>
                <span id="preview-count-text" class="text-[10px] font-black text-indigo-600"><?php echo $preview_attempts ?? 3; ?></span>
             </div>
             <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden border border-slate-200/50">
                <div id="preview-progress-bar" class="h-full bg-indigo-500 transition-all duration-500" style="width: 100%"></div>
             </div>
        </div>
        <span id="listening-speaker-badge" class="px-2 sm:px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-tight">2 Speakers</span>
    </div>
</div>

<div class="sm:hidden mb-4 p-3 bg-indigo-50 rounded-xl border border-indigo-100 flex justify-between items-center">
    <span class="text-[10px] font-black text-indigo-700 uppercase tracking-widest"> Previews Left</span>
    <span id="preview-count-mobile" class="text-xs font-black text-indigo-800">0 / 3</span>
</div>

<div id="listening-voice-config" class="space-y-4 sm:space-y-6 max-h-[50vh] lg:max-h-[70vh] overflow-y-auto pr-2 custom-scrollbar"></div>
                </div>
            </div>

        </div>
    </main>
</div>

<div id="listening-toast-container" class="fixed top-4 right-4 sm:top-6 sm:right-6 z-[100] space-y-2 pointer-events-none w-64 sm:w-80"></div>

<script>



    

function updateCreditDisplaylistening(newBalance) { 
    const ids = ['creditCountListening', 'creditCountreading', 'creditCountCompleted'];
    
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            console.log("Updating element:", id, "to", newBalance);
            el.textContent = newBalance;
        } else {
            console.error("Could not find element with ID:", id);
        }
    });

    if (typeof stateListening !== 'undefined') {
        stateListening.credits = newBalance;
    }
}
    // --- STATE ---
   // --- STATE ---
const LISTENING_PLAN = <?php echo json_encode($plan_type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// Change from const to let
let LISTENING_PREVIEW_ATTEMPTS = <?= (int)$preview_attempts ?>; 

let listeningVoices = [];
let listeningRequiredSpeakers = 2;

    // --- INIT ---
  window.addEventListener('DOMContentLoaded', async () => {
    if (window.lucide) lucide.createIcons();

    try {
        const res = await fetch("/api/voices");
        
        if (res.status === 401) {
            throw new Error("Please log in again");
        }

        // Check if response is NOT successful (anything other than 200-299)
        if (!res.ok) {
            throw new Error("Server error");
        }
        
        // This will now only run if res.ok is true
        listeningVoices = await res.json();
        
        if (listeningVoices.error) {
            showListeningToast(listeningVoices.error, "error");
        } else {
            updateListeningRequirements();
        }
    } catch(e) { 
        console.error("Voice Fetch Error:", e);
        showListeningToast(e.message || "Voice service unavailable", "error"); 
    }
});

function renderListeningVoiceConfigs(savedSettings = null) {
    const container = document.getElementById('listening-voice-config');
    if (!container) return;
    container.innerHTML = "";
    
    const attemptsLeft = parseInt(LISTENING_PREVIEW_ATTEMPTS);
    const canTune = attemptsLeft > 0; 

    const countText = document.getElementById('preview-count-text');
    const countMobile = document.getElementById('preview-count-mobile');
    const progressBar = document.getElementById('preview-progress-bar');
    
    if (countText) countText.innerText = attemptsLeft;
    if (countMobile) countMobile.innerText = `${attemptsLeft} Left`;
    if (progressBar) {
        const width = Math.min((attemptsLeft / 3) * 100, 100);
        progressBar.style.width = `${width}%`;
    }

    for (let i = 1; i <= listeningRequiredSpeakers; i++) {
        // Use saved settings for this specific speaker index, or use defaults
        const s = (savedSettings && savedSettings[i]) ? savedSettings[i] : {
            voice: null,
            stab: 0.5,
            sim: 0.75,
            style: 0.0,
            speed: 1.0
        };

        const div = document.createElement('div');
        div.className = "bg-white border border-slate-100 p-4 sm:p-5 rounded-2xl shadow-sm relative overflow-hidden group";
        
        const optionsHtml = listeningVoices.map((v, index) => {
            // If we have a saved voice, select it. Otherwise, default to index-based selection
            const isSelected = s.voice ? (s.voice === v.voice_id ? 'selected' : '') : (index === i - 1 ? 'selected' : '');
            return `<option value="${v.voice_id}" ${isSelected}>${v.name}</option>`;
        }).join('');

        const lockOverlay = !canTune ? `
            <div onclick="showListeningToast('No previews left. Using default settings.', 'info')" 
                 class="absolute inset-0 z-20 bg-white/40 backdrop-blur-[1px] cursor-not-allowed flex items-center justify-center p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <div class="bg-white/90 px-3 py-2 rounded-xl shadow-sm border border-slate-100 flex items-center gap-2">
                    <i data-lucide="lock" class="w-3 h-3 text-slate-400"></i>
                    <span class="text-[9px] font-bold text-slate-500 uppercase">Tuning Locked</span>
                </div>
            </div>
        ` : '';

        div.innerHTML = `
            <div class="flex justify-between items-center mb-3">
                <span class="text-[10px] sm:text-xs font-black text-indigo-600 bg-indigo-50 px-2 py-1 rounded">SPEAKER ${i}</span>
                <span class="text-[9px] font-bold ${canTune ? 'text-amber-600 bg-amber-50' : 'text-slate-400 bg-slate-50'} px-2 py-1 rounded italic">
                    ${canTune ? `${attemptsLeft} PREVIEWS LEFT` : '0 PREVIEWS LEFT'}
                </span>
            </div>
            
            <select id="listening-voice-${i}" 
                    class="voice-selector w-full p-2.5 sm:p-3 bg-slate-50 rounded-xl text-xs sm:text-sm font-bold border border-slate-100 outline-none mb-4"
                    onchange="syncVoiceSelectors()">
                ${optionsHtml}
            </select>

            <div class="space-y-4 relative p-1">
                ${lockOverlay}
                <div class="${!canTune ? 'opacity-40 grayscale' : ''}">
                    <div class="flex justify-between text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase mb-1">
                        <span>Stability</span> <span id="val-listening-stab-${i}">${parseFloat(s.stab).toFixed(2)}</span>
                    </div>
                    <input type="range" id="listening-stab-${i}" min="0" max="1" step="0.05" value="${s.stab}" 
                        ${!canTune ? 'disabled' : `oninput="document.getElementById('val-listening-stab-${i}').innerText=parseFloat(this.value).toFixed(2)"`}>
                </div>

                <div class="${!canTune ? 'opacity-40 grayscale' : ''}">
                    <div class="flex justify-between text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase mb-1">
                        <span>Clarity</span> <span id="val-listening-sim-${i}">${parseFloat(s.sim).toFixed(2)}</span>
                    </div>
                    <input type="range" id="listening-sim-${i}" min="0" max="1" step="0.05" value="${s.sim}" 
                        ${!canTune ? 'disabled' : `oninput="document.getElementById('val-listening-sim-${i}').innerText=parseFloat(this.value).toFixed(2)"`}>
                </div>

                <div class="${!canTune ? 'opacity-40 grayscale' : ''}">
                    <div class="flex justify-between text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase mb-1">
                        <span>Exaggeration</span> <span id="val-listening-style-${i}">${parseFloat(s.style).toFixed(2)}</span>
                    </div>
                    <input type="range" id="listening-style-${i}" min="0" max="1" step="0.05" value="${s.style}" 
                        ${!canTune ? 'disabled' : `oninput="document.getElementById('val-listening-style-${i}').innerText=parseFloat(this.value).toFixed(2)"`}>
                </div>

                <div class="${!canTune ? 'opacity-40 grayscale' : ''}">
                    <div class="flex justify-between text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase mb-1">
                        <span>Speed</span> <span id="val-listening-speed-${i}">${s.speed}x</span>
                    </div>
                    <input type="range" id="listening-speed-${i}" min="0.7" max="1.3" step="0.1" value="${s.speed}" 
                        ${!canTune ? 'disabled' : `oninput="document.getElementById('val-listening-speed-${i}').innerText=this.value+'x'"`}>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
        <button onclick="previewListeningVoice(${i}, true)" 
            title="Play Original"
            class="w-12 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-500 border border-slate-100 rounded-lg flex items-center justify-center transition-all">
            <i data-lucide="play" class="w-4 h-4"></i>
        </button>

        <button onclick="previewListeningVoice(${i}, false)" 
            class="flex-1 py-2.5 bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 rounded-lg text-[10px] sm:text-xs font-black transition-all uppercase tracking-widest border border-indigo-100 flex items-center justify-center gap-2">
            <i data-lucide="sparkles" class="w-3 h-3"></i>
            <span>Test Voice</span>
        </button>
    </div>
        `;
        container.appendChild(div);
    }
    syncVoiceSelectors();
    if (window.lucide) lucide.createIcons();
}


function playDefaultVoice(btn, idx) {
    const voiceId = document.getElementById(`listening-voice-${idx}`).value;
    const voiceData = listeningVoices.find(v => v.voice_id === voiceId);

    if (!voiceData || !voiceData.preview_url) {
        showListeningToast('Original preview not available.', 'error');
        return;
    }

    const icon = btn.querySelector('i');
    const originalIcon = icon.getAttribute('data-lucide');
    
    // Simple loading state
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin text-indigo-500"></i>`;
    if(window.lucide) lucide.createIcons();

    const audio = new Audio(voiceData.preview_url);
    audio.play();

    audio.onended = () => {
        btn.innerHTML = `<i data-lucide="${originalIcon}" class="w-4 h-4"></i>`;
        if(window.lucide) lucide.createIcons();
    };
}

    function syncVoiceSelectors() {
    const selectors = document.querySelectorAll('.voice-selector');
    if (!selectors.length) return;

    const currentSelections = Array.from(selectors).map(s => s.value);

    selectors.forEach((selector) => {
        const options = selector.querySelectorAll('option');
        const myValue = selector.value;

        options.forEach((option) => {
            const isSelectedByOther = currentSelections.includes(option.value) && option.value !== myValue;
            
            if (isSelectedByOther) {
                option.disabled = true;
                option.style.opacity = "0.5";
                option.style.backgroundColor = "#f1f5f9"; 
            } else {
                option.disabled = false;
                option.style.opacity = "1";
                option.style.backgroundColor = "";
            }
        });
    });
}

   function toggleListeningTopicInput() {
    // 1. Check the state dynamically right here
    const isRandom = document.getElementById('listening-randomTopic').checked; 
    
    const input = document.getElementById('listening-topic');
    const badge = document.getElementById('listening-ai-badge');
    
    const wordCountDisplay = document.getElementById('word-count');
    const errorMsg = document.getElementById('topic-error-msg');
    const genBtn = document.getElementById('listening-genBtn');

    if (isRandom) {
        input.value = "AI's Choice: Random Listening Topic";
        input.disabled = true;
        input.classList.add("bg-slate-100", "text-indigo-700", "font-medium");
        badge.classList.remove('hidden');

        input.classList.remove('border-red-500', 'ring-red-500');
        if (errorMsg) errorMsg.classList.add('hidden');
        if (wordCountDisplay) wordCountDisplay.innerText = "0";

        if (genBtn) {
            genBtn.disabled = false;
            genBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }

    } else {
        input.value = "";
        input.disabled = false;
        input.classList.remove("bg-slate-100", "text-indigo-700", "font-medium");
        input.placeholder = "e.g. A conversation about renting an apartment";
        badge.classList.add('hidden');

        if (wordCountDisplay) wordCountDisplay.innerText = "0";
    }
}

    function setListeningDifficulty(level) {
        document.getElementById('listening-difficulty-hidden').value = level;
        const buttons = document.querySelectorAll('.listening-diff-btn');
        buttons.forEach(btn => {
            if (btn.getAttribute('data-level') === level) {
                btn.classList.add('bg-white', 'shadow-sm', 'text-indigo-600');
                btn.classList.remove('text-slate-500');
            } else {
                btn.classList.remove('bg-white', 'shadow-sm', 'text-indigo-600');
                btn.classList.add('text-slate-500');
            }
        });
    }

// Add 'isStatic = false' here
async function previewListeningVoice(idx, isStatic = false) {
    let currentSettingsSnapshot = {};
    for (let i = 1; i <= listeningRequiredSpeakers; i++) {
        currentSettingsSnapshot[i] = {
            voice: document.getElementById(`listening-voice-${i}`).value,
            stab: document.getElementById(`listening-stab-${i}`).value,
            sim: document.getElementById(`listening-sim-${i}`).value,
            style: document.getElementById(`listening-style-${i}`).value,
            speed: document.getElementById(`listening-speed-${i}`).value
        };
    }

    const vid = currentSettingsSnapshot[idx].voice;
    // Target the specific button that was clicked
    const btn = event.currentTarget; 
    const orgContent = btn.innerHTML; 
    const speedVal = parseFloat(currentSettingsSnapshot[idx].speed);

    const settings = {
        voice_id: vid,
        is_static: isStatic, // NEW: Tell the backend if this is a free preview
        stability: parseFloat(currentSettingsSnapshot[idx].stab),
        similarity_boost: parseFloat(currentSettingsSnapshot[idx].sim),
        style: parseFloat(currentSettingsSnapshot[idx].style),
        speed: isFinite(speedVal) ? speedVal : 1.0,
    };

    // UI Loading State
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="animate-spin w-4 h-4 ${isStatic ? 'text-slate-400' : 'text-indigo-500'}"></i>`;
    if (window.lucide) lucide.createIcons();

    try {
        const response = await fetch("/api/voices/preview", {
            method: "POST",
            headers: { 
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(settings) 
        });

        const data = await response.json();
        if (data.error) throw new Error(data.error);

        // Only re-render if it was NOT a static call (because credits only change on tuned calls)
        if (!isStatic && data.attempts_left !== undefined) {
            LISTENING_PREVIEW_ATTEMPTS = parseInt(data.attempts_left);
            renderListeningVoiceConfigs(currentSettingsSnapshot);
        }

        const audio = new Audio(data.audio_data);
        audio.playbackRate = isStatic ? 1.0 : settings.speed; // Default voice usually plays at 1x
        audio.play();
        
        audio.onended = () => {
            btn.innerHTML = orgContent;
            btn.disabled = false;
            if (window.lucide) lucide.createIcons();
        };

    } catch (e) {
        btn.innerHTML = orgContent;
        btn.disabled = false;
        alert("Error: " + e.message);
    }
}
function updateCreditCost() {
    const section = document.getElementById('listening-section').value;
    const qType = document.getElementById('listening-qType').value;
    const costSpan = document.getElementById('btn-credit-cost');
    
    const sectionCosts = {
        "1": 2,
        "2": 3,
        "3": 4,
        "4": 5
    };
    
    let cost = sectionCosts[section] || 2;
    if (qType === 'mixed') cost += 1;
    costSpan.innerText = cost;
}

document.addEventListener('DOMContentLoaded', async () => {
    // We use the variable we defined in the @php block above
    @if($activeTaskId)
        const activeTaskId = @json($activeTaskId);
        handleTaskProcessingListening(activeTaskId, 2); 
    @endif
});

async function handleTaskProcessingListening(taskId, requiredCost = 2) {
    const modalElement = document.getElementById('generationModal');

    if (typeof GenerationModal !== 'undefined') {
        GenerationModal.show(); 
    }
    
    if (modalElement) {
        modalElement.classList.replace('hidden', 'flex');
        document.body.style.overflow = 'hidden';
    }
    try {
        GenerationModal.update("AI is drafting your exam script...", 15);
        const data = await pollTask(taskId);
        
        GenerationModal.hide();

        if (data.new_balance !== undefined) {
            updateCreditDisplaylistening(data.new_balance);
        } else {
            const currentLabel = document.getElementById('creditCountListening');
            const oldBalance = parseInt(currentLabel.textContent) || 0;
            updateCreditDisplaylistening(oldBalance - requiredCost);
        }

        if (typeof refreshSavedTests === "function") {
            refreshSavedTests().then(() => { 
                if (typeof window.checkAndShowBadges === "function") {
                    window.checkAndShowBadges();
                }
            }).catch(err => console.error("Refresh failed:", err));
        }
        if (typeof refreshBadge === "function") refreshBadge();
        
        const generatedTitle = (data.questions && data.questions[0]) ? data.questions[0].script_title : 'New Test';

        Swal.fire({
            title: 'Success!',
            html: `
                <div class="mt-2 p-3 bg-slate-50 rounded-xl border border-slate-100 mb-4">
                    <p class="text-[10px] uppercase tracking-widest text-slate-400 font-bold mb-1">Generated Test</p>
                    <p class="text-indigo-600 font-bold text-lg leading-tight">${generatedTitle}</p>
                </div>
            `,
            icon: 'success',
            heightAuto: false,
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Go to Saved Tests',
            cancelButtonText: 'Create Another',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-3', cancelButton: 'rounded-xl px-6 py-3' }
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof showPage === "function") showPage('saved-tests-page');
                else window.location.reload(); 
            }
        });

    } catch (e) {
        modalElement.classList.replace('flex', 'hidden');
        document.body.style.overflow = 'auto';
        Swal.fire({ title: 'Failed', text: e.message, icon: 'error' });
    }
}

window.addEventListener('DOMContentLoaded', updateCreditCost);

async function generateListeningContent() {
    const btn = document.getElementById('listening-genBtn');
    const originalContent = btn.innerHTML;
    
    // 1. Grab the fresh values at the moment the button is clicked!
    const isRandom = document.getElementById('listening-randomTopic').checked;
    const userTopic = document.getElementById('listening-topic').value.trim();
    
    const section = document.getElementById('listening-section').value;
    const qType = document.getElementById('listening-qType').value;
    const sectionCosts = { "1": 2, "2": 3, "3": 4, "4": 5 };
    
    let requiredCost = sectionCosts[section] || 2;
    if (qType === 'mixed') requiredCost += 1;

    const currentLabel = document.getElementById('creditCountListening');
    const currentBalance = parseInt(currentLabel.textContent) || 0;

    if (currentBalance < requiredCost) {
        Swal.fire({
            title: 'Insufficient Credits',
            text: `This Section ${section} test requires ${requiredCost} credits, but you only have ${currentBalance}.`,
            icon: 'warning',
            confirmButtonColor: '#4f46e5',
            showCancelButton: true,
            confirmButtonText: 'Upgrade Now',
            cancelButtonText: 'Close'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'upgrade.php';
            }
        });
        return;
    }

    btn.disabled = true;
    btn.classList.add('cursor-wait');
    
    const statusMessages = [
        "Brainstorming topic...",
        "Drafting exam script...",
        "Synthesizing AI voices...",
        "Finalizing audio file..."
    ];
    
    let msgIndex = 0;
    btn.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="relative flex items-center justify-center">
                <i data-lucide="loader-2" class="animate-spin w-5 h-5 text-indigo-400"></i>
                <div class="absolute inset-0 animate-ping rounded-full bg-indigo-400/20"></div>
            </div>
            <div class="flex flex-col items-start leading-none">
                <span id="loading-status-text" class="text-sm font-bold tracking-wide animate-pulse">
                    ${statusMessages[0]}
                </span>
                <span class="text-[10px] text-slate-400 font-medium">Please wait, task started</span>
            </div>
        </div>
    `;
    if (window.lucide) lucide.createIcons();

    const statusInterval = setInterval(() => {
        msgIndex = (msgIndex + 1) % statusMessages.length;
        const textEl = document.getElementById('loading-status-text');
        if (textEl) textEl.innerText = statusMessages[msgIndex];
    }, 2500);

    const speakers = [];
    for(let i=1; i<=listeningRequiredSpeakers; i++) {
        const selectedVoiceId = document.getElementById(`listening-voice-${i}`).value;
        const voiceMetadata = listeningVoices.find(v => v.voice_id === selectedVoiceId);
        speakers.push({
            voice_id: selectedVoiceId,
            gender: voiceMetadata ? (voiceMetadata.gender || 'unknown') : 'unknown', 
            settings: {
                stability: parseFloat(document.getElementById(`listening-stab-${i}`).value),
                similarity_boost: parseFloat(document.getElementById(`listening-sim-${i}`).value),
                style: parseFloat(document.getElementById(`listening-style-${i}`).value),
                speed: parseFloat(document.getElementById(`listening-speed-${i}`).value)
            }
        });
    }

    
    if (!isRandom && userTopic === "") {
        Swal.fire({
            title: 'Topic Required',
            text: 'Please enter a topic or check "AI Random Topic".',
            icon: 'warning',
            confirmButtonColor: '#4f46e5'
        });
        btn.innerHTML = originalContent;
        btn.disabled = false;
        btn.classList.remove('cursor-wait');
        clearInterval(statusInterval);
        return; 
    }

    const finalTopic = isRandom ? "RANDOM" : userTopic; 
    let selectedSubtypes = [];
    if (qType === 'mixed') {
        selectedSubtypes = Array.from(document.querySelectorAll('input[name="mixed_subtype"]:checked')).map(cb => cb.value);
        if (selectedSubtypes.length !== 2) {
            showListeningToast("Please select exactly 2 types to mix", "error");
            clearInterval(statusInterval);
            btn.innerHTML = originalContent;
            btn.disabled = false;
            btn.classList.remove('cursor-wait');
            return; 
        }
    }
    
    try {
        const res = await fetch("api/generate/listening", {
            method: "POST",
            headers: { 
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                topic: isRandom ? "RANDOM" : userTopic,
                difficulty: document.getElementById('listening-difficulty-hidden').value,
                section: section,
                question_type: qType,
                speakers: speakers,
                mixed_type: selectedSubtypes
            })
        });

        const initialData = await res.json();
        if(initialData.error) throw new Error(initialData.error);

        handleTaskProcessingListening(initialData.task_id, requiredCost);

    } catch(e) {
        Swal.fire({ title: 'Generation Failed', text: e.message, icon: 'error' });
    } finally {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        btn.classList.remove('cursor-wait');
        clearInterval(statusInterval);
        if (window.lucide) lucide.createIcons();
    }
}

function showListeningToast(msg, type="info") {
    const container = document.getElementById('listening-toast-container');
    const t = document.createElement('div');
    t.className = `p-3 sm:p-4 rounded-xl text-white font-bold shadow-xl text-xs sm:text-sm mb-2 transform transition-all translate-x-full ${type==='error'?'bg-red-500':'bg-indigo-600'}`;
    t.innerText = msg;
    container.appendChild(t);
    requestAnimationFrame(() => t.classList.remove('translate-x-full'));
    setTimeout(() => t.remove(), 3000);
}

function handleSelection(selectElement) {
    const value = selectElement.value;
    const isPro = false; 

    if (value === 'map' && !isPro) {
        selectElement.value = 'mixed';
        Swal.fire({
            title: 'PRO ACCESS REQUIRED',
            text: 'Map Labeling missions require an active Pro Uplink.',
            icon: 'lock',
            background: '#0f172a',
            color: '#ffffff',
            showCancelButton: true,
            confirmButtonText: 'Upgrade Now',
            confirmButtonColor: '#6366f1',
            cancelButtonText: 'Maybe Later',
            customClass: {
                popup: 'rounded-[2rem] border border-white/10 shadow-2xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'upgrade.php'; 
            }
        });
    } else {
        updateCreditCost();
    }
}

const MIX_SUBTYPES = [
    { id: 'Sentence_completion', label: '✍️ Sentence Completion' },
    { id: 'Multiple_choice', label: '🔘 Multiple Choice' },
    { id: 'Matching', label: '🔗 Matching' },
    { id: 'Map_labeling', label: '⏳ Map Labeling (Soon)', disabled: true }
];

function initMixedPanel() {
    const container = document.getElementById('mixed-options-container');
    const badge = document.getElementById('mixed-count-badge');
    
    if (badge) badge.innerText = "0/2";

    container.innerHTML = MIX_SUBTYPES.map(type => `
        <label class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 bg-white border border-slate-200 rounded-xl transition-all group 
            ${type.disabled ? 'opacity-50 cursor-not-allowed grayscale' : 'cursor-pointer hover:border-indigo-300'}">
            
            <input type="checkbox" name="mixed_subtype" value="${type.id}" 
                ${type.disabled ? 'disabled' : ''} 
                onchange="handleMixedCheckboxChange(event)" 
                class="w-3 h-3 sm:w-4 sm:h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 
                ${type.disabled ? 'cursor-not-allowed' : ''}">
                
            <span class="text-[10px] sm:text-xs font-bold text-slate-700 ${!type.disabled ? 'group-hover:text-indigo-600' : ''}">
                ${type.label}
            </span>
        </label>
    `).join('');
}

function handleMixedCheckboxChange(event) {
    const qType = document.getElementById('listening-qType').value;
    const genBtn = document.getElementById('listening-genBtn');
    
    if (qType === 'mixed') {
        let checked = document.querySelectorAll('input[name="mixed_subtype"]:checked');
        const badge = document.getElementById('mixed-count-badge');
        
        if (checked.length > 2) {
            event.target.checked = false;
            checked = document.querySelectorAll('input[name="mixed_subtype"]:checked');

            Swal.fire({
                title: 'Limit Reached',
                text: 'You can only mix exactly 2 question types.',
                icon: 'warning',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: '#fff',
                color: '#1e293b'
            });
        }

        badge.innerText = `${checked.length}/2`;
        
        if (checked.length === 2) {
            genBtn.disabled = false;
            genBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            badge.classList.replace('bg-slate-400', 'bg-indigo-600');
        } else {
            genBtn.disabled = true;
            genBtn.classList.add('opacity-50', 'cursor-not-allowed');
            badge.classList.replace('bg-indigo-600', 'bg-slate-400');
        }

        const allCheckboxes = document.querySelectorAll('input[name="mixed_subtype"]');
        allCheckboxes.forEach(cb => {
            const isComingSoon = MIX_SUBTYPES.find(t => t.id === cb.value)?.disabled;

            if (isComingSoon) {
                cb.disabled = true;
                cb.parentElement.classList.add('opacity-50', 'cursor-not-allowed');
            } else if (checked.length >= 2 && !cb.checked) {
                cb.disabled = true;
                cb.parentElement.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                cb.disabled = false;
                cb.parentElement.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    }
}

document.getElementById('listening-qType').addEventListener('change', function(e) {
    const panel = document.getElementById('mixed-selection-panel');
    const genBtn = document.getElementById('listening-genBtn');
    
    if (e.target.value === 'mixed') {
        panel.classList.remove('hidden');
        genBtn.disabled = true;
        genBtn.classList.add('opacity-50', 'cursor-not-allowed');
        initMixedPanel(); 
    } else {
        panel.classList.add('hidden');
        genBtn.disabled = false;
        genBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    updateCreditCost();
});

const topicInputEl = document.getElementById('listening-topic');
if(topicInputEl) {
    topicInputEl.addEventListener('input', function() {
        const wordCountDisplay = document.getElementById('word-count');
        const errorMsg = document.getElementById('topic-error-msg');
        const genBtn = document.getElementById('listening-genBtn');

        const MAX_WORDS = 50;
        const MAX_CHARS = 300; 

        if (this.disabled) return;
        const text = this.value.trim();
        const words = text ? text.split(/\s+/).length : 0;
        const chars = text.length;
        
        wordCountDisplay.innerText = words;

        if (words > MAX_WORDS || chars > MAX_CHARS) {
            this.classList.add('border-red-500', 'ring-red-500');
            errorMsg.innerText = words > MAX_WORDS ? "Too many words!" : "Input is too long!";
            errorMsg.classList.remove('hidden');
            
            if(genBtn) {
                genBtn.disabled = true;
                genBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        } else {
            this.classList.remove('border-red-500', 'ring-red-500');
            errorMsg.classList.add('hidden');
            
            if(genBtn) {
                genBtn.disabled = false;
                genBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    });
}


function updateListeningRequirements() {
    // 1. Get the selected test section
    const section = document.getElementById('listening-section').value;
    const speakerBadge = document.getElementById('listening-speaker-badge');

    // 2. Set the number of speakers based on standard IELTS formats
    // Section 1 (Social Dialogue) and Section 3 (Academic Discussion) use 2 speakers
    // Section 2 (Social Monologue) and Section 4 (Academic Lecture) use 1 speaker
    if (section === "1" || section === "3") {
        listeningRequiredSpeakers = 2;
        if (speakerBadge) speakerBadge.innerText = "2 Speakers";
    } else {
        listeningRequiredSpeakers = 1;
        if (speakerBadge) speakerBadge.innerText = "1 Speaker";
    }

    // 3. Re-render the Voice Studio panel to show the correct number of voice selectors
    renderListeningVoiceConfigs();
}
</script>