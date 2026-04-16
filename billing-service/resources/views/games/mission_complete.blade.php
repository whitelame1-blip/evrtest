



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mission Debrief - IELTS Battle Prep</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; color: white; }
        .glass { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .review-card { border-left: 4px solid #6366f1; }
    </style>
</head>
<body class="p-4 md:p-12">

@if ($leveledUp)
<div id="levelup-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="glass max-w-sm w-full rounded-[2.5rem] md:rounded-[3rem] p-6 md:p-10 text-center border-indigo-500/50 shadow-[0_0_50px_rgba(99,102,241,0.3)]">
        <div class="w-16 h-16 md:w-20 md:h-20 bg-indigo-600 rounded-2xl md:rounded-3xl flex items-center justify-center mx-auto mb-6 rotate-12">
            <i data-lucide="trending-up" class="w-8 h-8 md:w-10 md:h-10 text-white"></i>
        </div>
        <h2 class="text-3xl md:text-4xl font-black italic uppercase tracking-tighter mb-2">Level Up!</h2>
        <p class="text-indigo-400 font-bold uppercase tracking-widest text-[10px] md:text-xs mb-2">Promoted to Level {{ $currentLevel }}</p>
        <div class="inline-block px-4 py-1 bg-indigo-500/20 border border-indigo-500/40 rounded-full text-white text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] mb-6">
            Rank: {{ $newTitle }}
        </div>
        <div class="bg-white/5 rounded-2xl p-4 mb-8 border border-white/10">
            <p class="text-[9px] md:text-[10px] text-slate-500 uppercase font-black mb-2">Rewards Unlocked</p>
            <div class="flex items-center justify-center gap-2 text-emerald-400 font-bold text-sm md:text-base">
                <i data-lucide="zap" class="w-4 h-4"></i>
                <span>+{{ $bonusAttempts }} Neural Attempts</span>
            </div>
        </div>

        <button onclick="document.getElementById('levelup-modal').remove()" class="w-full py-4 bg-white text-indigo-900 rounded-2xl font-black uppercase tracking-widest text-xs md:text-sm">
            Continue Mission
        </button>
    </div>
</div>
@endif

    <div class="max-w-4xl mx-auto">
        <div class="glass rounded-[2rem] md:rounded-[2.5rem] p-6 md:p-8 text-center mb-6 md:mb-10 border-b-4 border-indigo-500/30">
            <div class="w-12 h-12 md:w-16 md:h-16 bg-emerald-500/20 rounded-xl md:rounded-2xl flex items-center justify-center mx-auto mb-4 border border-emerald-500/30 shadow-[0_0_20px_rgba(16,185,129,0.2)]">
                <i data-lucide="award" class="w-6 h-6 md:w-8 md:h-8 text-emerald-400"></i>
            </div>
            <h1 class="text-2xl md:text-4xl font-black uppercase italic tracking-tighter mb-2">Mission Debrief</h1>
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-[0.1em] md:tracking-[0.2em]">Tactical Performance Report</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-4 mt-8">
                <div class="bg-white/5 rounded-2xl md:rounded-3xl p-4 md:p-5 border border-white/5 relative overflow-hidden group text-left md:text-center">
                    <div class="absolute -right-2 -top-2 opacity-10 group-hover:scale-110 transition-transform">
                        <i data-lucide="check-circle" class="w-10 h-10 md:w-12 md:h-12 text-emerald-400"></i>
                    </div>
                    <p class="text-[9px] md:text-[10px] text-emerald-400 font-black uppercase tracking-widest mb-1">Correct</p>
                    <p class="text-2xl md:text-3xl font-black">{{ $score }}</p>
                </div>

                <div class="bg-white/5 rounded-2xl md:rounded-3xl p-4 md:p-5 border border-white/5 relative overflow-hidden group text-left md:text-center">
                    <div class="absolute -right-2 -top-2 opacity-10 group-hover:scale-110 transition-transform">
                        <i data-lucide="x-circle" class="w-10 h-10 md:w-12 md:h-12 text-rose-500"></i>
                    </div>
                    <p class="text-[9px] md:text-[10px] text-rose-500 font-black uppercase tracking-widest mb-1">Failed</p>
                    <p class="text-2xl md:text-3xl font-black">{{ $wrongCount }}</p>
                </div>

                <div class="bg-indigo-500/10 rounded-2xl md:rounded-3xl p-4 md:p-5 border border-indigo-500/20 relative overflow-hidden group text-left md:text-center">
                    <div class="absolute -right-2 -top-2 opacity-10 group-hover:scale-110 transition-transform">
                        <i data-lucide="zap" class="w-10 h-10 md:w-12 md:h-12 text-amber-400"></i>
                    </div>
                    <p class="text-[9px] md:text-[10px] text-amber-400 font-black uppercase tracking-widest mb-1">XP Gained</p>
                    <p class="text-2xl md:text-3xl font-black text-amber-400">+{{ $finalXpGain }}</p>
                    @if ($rewardsIssued)
                        <span class="text-[8px] md:text-[9px] text-emerald-400 uppercase font-bold block">{{ $multiplier }}x Multiplier</span>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="w-full h-px bg-gradient-to-r from-transparent via-white/10 to-transparent mb-8 md:mb-10"></div>
        
        <h3 class="flex items-center gap-2 text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] md:tracking-[0.3em] text-indigo-400 mb-6">
            <i data-lucide="database" class="w-4 h-4"></i> Tactical Analysis
        </h3>

        <div class="space-y-4 mb-12">
            @foreach($questions as $index => $q)
                @php
                    $userChoice = $userAnswers[$index] ?? 'none';
                    $isCorrect = (strtolower($userChoice) === strtolower($q->correct_option));
                    $borderColor = $isCorrect ? 'border-emerald-500' : 'border-rose-500';
                    $bgTint = $isCorrect ? 'bg-emerald-500/5' : 'bg-rose-500/5';
                @endphp
            <div class="glass rounded-xl md:rounded-2xl p-4 md:p-6 border-l-4 {{ $borderColor }} {{ $bgTint }}">
                <div class="flex flex-col sm:flex-row justify-between items-start gap-2 mb-4">
                    <span class="text-[9px] md:text-[10px] font-bold bg-white/5 px-3 py-1 rounded-lg text-slate-400 uppercase">
                        Q{{ $index + 1 }} 
                        <span class="{{ $isCorrect ? 'text-emerald-500' : 'text-rose-500' }} ml-1 md:ml-2">
                            — {{ $isCorrect ? 'SECURED' : 'FAILED' }}
                        </span>
                    </span>
                    <span class="text-[9px] md:text-[10px] font-black text-indigo-400 uppercase tracking-widest">{{ $q->category }}</span>
                </div>
                
                <p class="text-base md:text-lg font-bold mb-4 leading-tight">{{ $q->question_text }}</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
                    <div class="p-3 rounded-lg md:rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs md:text-sm">
                        <span class="text-emerald-500 font-bold uppercase text-[8px] md:text-[9px] block mb-1">Correct Objective</span>
                        <span class="text-white font-bold">{{ $q->{'option_' . $q->correct_option} }}</span>
                    </div>

                    @if(!$isCorrect)
                    <div class="p-3 rounded-lg md:rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs md:text-sm">
                        <span class="text-rose-500 font-bold uppercase text-[8px] md:text-[9px] block mb-1">Your Input</span>
                        <span class="text-white font-bold">
                            @if($userChoice === 'timeout')
                                SIGNAL LOST
                            @else
                                {{ $q->{'option_' . $userChoice} ?? 'Unknown' }}
                            @endif
                        </span>
                    </div>
                    @endif
                </div>

                <div class="p-3 md:p-4 bg-indigo-500/5 rounded-lg md:rounded-xl border border-indigo-500/10">
                    <div class="flex gap-2 items-start text-[11px] md:text-xs leading-relaxed text-slate-300">
                        <i data-lucide="info" class="w-3.5 h-3.5 md:w-4 md:h-4 text-indigo-400 shrink-0 mt-0.5"></i>
                        <p><span class="text-white font-bold">AI Insight:</span> {!! nl2br(e($q->explanation)) !!}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="flex justify-center pb-12">
            <a href="/single-play" class="w-full text-center md:w-auto px-12 py-4 md:py-5 bg-indigo-600 rounded-xl md:rounded-2xl font-black uppercase italic tracking-widest hover:scale-105 transition-all text-sm md:text-base inline-block">
                Confirm
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>