<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Active Mission - IELTS Battle Prep</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; color: white; overflow-y: auto; overflow-x: hidden;}
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
        .timer-svg { transform: rotate(-90deg); }
        .timer-circle { transition: stroke-dashoffset 1s linear; stroke-dasharray: 283; }
        .option-btn { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (min-width: 1024px) { .option-btn:hover:not(:disabled) { background: rgba(99, 102, 241, 0.2); border-color: #6366f1; transform: translateX(10px); } }
        .correct { background: #10b981 !important; border-color: #059669 !important; box-shadow: 0 0 20px rgba(16, 185, 129, 0.4); }
        .wrong { background: #ef4444 !important; border-color: #dc2626 !important; animation: shake 0.4s; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
        .animate-shake { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; border-color: #ef4444 !important; }
    </style>
</head>
<body class="min-h-screen flex items-start md:items-center justify-center p-3 md:p-6 bg-slate-950 text-slate-100">

    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute top-0 left-0 w-full h-full opacity-20" style="background-image: radial-gradient(#6366f1 1px, transparent 1px); background-size: 40px 40px;"></div>
    </div>

    <div class="w-full max-w-6xl flex flex-col lg:grid lg:grid-cols-12 gap-4 z-10">
        
        <div class="col-span-12 lg:col-span-3 grid grid-cols-2 lg:flex lg:flex-col gap-3">
            <div class="glass-panel rounded-2xl p-4 text-center flex flex-col justify-center">
                <p class="text-[8px] md:text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Progress</p>
                <div class="text-xl md:text-3xl font-black italic">
                    <span id="curr-idx">1</span><span class="text-indigo-500">/</span>{{ $totalQuestions }}
                </div>
            </div>
            
            <div class="glass-panel rounded-2xl p-4 flex flex-col items-center justify-center">
                <div class="relative w-12 h-12 md:w-20 md:h-20 mb-1">
                    <svg class="timer-svg w-full h-full" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="8"></circle>
                        <circle id="timer-bar" cx="50" cy="50" r="45" fill="none" stroke="#6366f1" stroke-width="8" stroke-linecap="round" class="timer-circle" stroke-dashoffset="0"></circle>
                    </svg>
                    <div id="timer-text" class="absolute inset-0 flex items-center justify-center font-black text-base md:text-xl">{{ $secondsPerQuestion }}</div>
                </div>
                <p class="text-[8px] md:text-[10px] font-black uppercase text-slate-400">Seconds</p>
            </div>

            <div id="streak-meter" class="col-span-2 lg:col-span-1 glass-panel rounded-2xl p-3 md:p-5 text-center border-orange-500/0 transition-all">
                <p class="text-[8px] md:text-[10px] font-black uppercase text-slate-400 mb-1">Streak</p>
                <div id="streak-val" class="text-lg md:text-2xl font-black text-orange-500">0</div>
                <div class="flex justify-center gap-1 mt-1" id="streak-dots">
                    <div class="w-1.5 h-1.5 rounded-full bg-white/10"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/10"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/10"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/10"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/10"></div>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-9">
            <div id="q-card" class="glass-panel rounded-3xl md:rounded-[2.5rem] p-5 md:p-10 min-h-[350px] md:min-h-[500px] flex flex-col relative">
                <div id="category-tag" class="w-fit px-3 py-1 bg-indigo-500/20 border border-indigo-500/30 rounded-full text-[8px] md:text-[10px] font-black uppercase text-indigo-400 tracking-widest mb-4">
                    Category: Loading...
                </div>
                
                <h2 id="question-text" class="text-lg md:text-2xl lg:text-3xl font-bold leading-snug mb-8">
                    Pulling data from AI encrypted stream...
                </h2>

                <div id="options-grid" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-auto"></div>
            </div>
        </div>
    </div>
    <script>
        let userAnswers = [];
        const questions = @json($questions);
        const missionId = {{ $missionId }};
        const difficulty = "{{ $difficultyLevel }}"; 
        const serverCsrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let currentIdx = 0;
        let score = 0;
        let streak = 0;
        let timer;
        const TIME_LIMIT = {{ $secondsPerQuestion }}; 
        let timeLeft = TIME_LIMIT;

        function initQuestion() {
            if (currentIdx >= questions.length) return finishGame();

            const q = questions[currentIdx];
            document.getElementById('curr-idx').innerText = currentIdx + 1;
            document.getElementById('category-tag').innerHTML = `Category: ${escapeHTML(q.category)} | <span class="text-indigo-300 tracking-normal">${difficulty.toUpperCase()}</span>`;
            document.getElementById('question-text').innerText = q.question_text;
            
            const grid = document.getElementById('options-grid');
            grid.innerHTML = '';
            
            ['a', 'b', 'c', 'd'].forEach(key => {
                const btn = document.createElement('button');
                btn.className = "option-btn glass-panel p-5 rounded-2xl text-left font-bold flex items-center gap-4 group";
                btn.innerHTML = `<span class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-xs border border-white/10 group-hover:border-indigo-500">${key.toUpperCase()}</span><span>${escapeHTML(q['option_' + key])}</span>`;
                btn.onclick = () => checkAnswer(key, btn);
                grid.appendChild(btn);
            });

            resetTimer();
            gsap.from("#q-card", { duration: 0.5, y: 30, opacity: 0, ease: "power2.out" });
        }

        function handleTimeOut() {
            clearInterval(timer);
            streak = 0;
            updateStreakUI();
            userAnswers.push("timeout");
            const qCard = document.getElementById('q-card');
            const correctOption = questions[currentIdx].correct_option.toLowerCase();
            
            const warning = document.createElement('div');
            warning.className = "absolute inset-0 flex items-center justify-center bg-rose-950/60 backdrop-blur-sm rounded-[2.5rem] z-50";
            warning.innerHTML = `<h1 class="text-4xl font-black italic text-rose-500 tracking-tighter uppercase animate-pulse">Uplink Expired</h1>`;
            qCard.appendChild(warning);
            qCard.classList.add('animate-shake');

            const buttons = document.querySelectorAll('.option-btn');
            buttons.forEach(btn => {
                btn.disabled = true;
                if (btn.innerText.trim().toLowerCase().startsWith(correctOption)) btn.classList.add('correct');
            });

            setTimeout(() => {
                warning.remove();
                qCard.classList.remove('animate-shake');
                currentIdx++;
                initQuestion();
            }, 2000);
        }

        function resetTimer() {
            clearInterval(timer);
            timeLeft = TIME_LIMIT;
            updateTimerUI();
            timer = setInterval(() => {
                timeLeft--;
                updateTimerUI();
                if (timeLeft <= 0) { clearInterval(timer); handleTimeOut(); }
            }, 1000);
        }

        function updateTimerUI() {
            const offset = 283 - (283 * timeLeft / TIME_LIMIT);
            document.getElementById('timer-bar').style.strokeDashoffset = offset;
            document.getElementById('timer-text').innerText = timeLeft;
            document.getElementById('timer-text').className = timeLeft < 10 
                ? 'absolute inset-0 flex items-center justify-center font-black text-base md:text-xl text-rose-500 animate-pulse' 
                : 'absolute inset-0 flex items-center justify-center font-black text-base md:text-xl';
        }

        function checkAnswer(choice, btn) {
            clearInterval(timer);
            const correct = questions[currentIdx].correct_option.toLowerCase();
            userAnswers.push(choice);
            const allBtns = document.querySelectorAll('.option-btn');
            allBtns.forEach(b => b.disabled = true);

            if (choice === correct) {
                btn.classList.add('correct');
                score++; streak++;
            } else {
                btn.classList.add('wrong');
                streak = 0;
                allBtns.forEach(b => { if (b.innerText.toLowerCase().startsWith(correct)) b.classList.add('correct'); });
            }
            updateStreakUI();
            setTimeout(() => { currentIdx++; initQuestion(); }, 1500);
        }

        function updateStreakUI() {
            document.getElementById('streak-val').innerText = streak;
            const dots = document.getElementById('streak-dots').children;
            const activeCount = streak % 6;
            for(let i=0; i<5; i++) {
                dots[i].className = i < activeCount ? "w-2 h-2 rounded-full bg-orange-500 shadow-[0_0_10px_#f97316]" : "w-2 h-2 rounded-full bg-white/10";
            }
            document.getElementById('streak-meter').classList.toggle('border-orange-500/50', streak >= 5);
            document.getElementById('streak-meter').classList.toggle('bg-orange-500/10', streak >= 5);
        }

        function finishGame() {
    const form = document.createElement('form');
    form.method = 'POST';
    
    // 1. Correct the URL to include the missionId as a parameter
    form.action = `/mission/${missionId}/submit`; 

    // 2. We only need answers and the CSRF token now
    const params = { 
        answers: userAnswers.join(','), 
        _token: serverCsrfToken 
    };

    for (const key in params) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

        function escapeHTML(str) {
            return String(str).replace(/[&<>'"]/g, tag => ({'&': '&amp;','<': '&lt;','>': '&gt;',"'": '&#39;','"': '&quot;'}[tag] || tag));
        }

        initQuestion();
        lucide.createIcons();

        window.addEventListener('pageshow', e => { if (e.persisted) window.location.reload(); });
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = () => window.location.href = '/single-play';
    </script>
</body>
</html>