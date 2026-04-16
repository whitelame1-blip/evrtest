<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results | {{ $test_data['topic'] ?? 'Test Results' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="bg-[#f1f5f9] text-slate-900 antialiased min-h-screen pb-12">

{{-- Laravel Flash Session for Retake Note --}}
@if (session('is_retake'))
    <div class="max-w-4xl mx-auto mt-4 px-4">
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-2 rounded-xl text-xs">
            Note: You have already completed this test. This score will not be saved to your history.
        </div>
    </div>
@endif

<nav class="sticky top-0 z-50 border-b border-slate-200/60 glass px-4 md:px-8 py-4 no-print">
    <div class="max-w-6xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <span class="font-extrabold text-xl tracking-tight hidden sm:inline-block">IELTS Lab <span class="text-slate-400 font-medium">/ Results</span></span>
        </div>
        <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:text-indigo-600 transition-all shadow-sm">Exit Review</a>
    </div>
</nav>

<main class="max-w-4xl mx-auto py-8 md:py-12 px-4">
    <div class="relative overflow-hidden bg-slate-900 rounded-[2rem] md:rounded-[3rem] p-8 md:p-12 mb-8 shadow-2xl shadow-slate-300">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8 text-center md:text-left">
            <div class="flex-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-black uppercase tracking-widest mb-6">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    Submission Report
                </div>
                <h1 class="text-3xl md:text-5xl font-black text-white leading-tight mb-3">
                    {{ $topic_name ?? $test_data['topic'] }}
                </h1>
                <p class="text-slate-400 font-medium">Completed on {{ now()->format('F j, Y') }}</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-4 md:gap-8">
                <div class="text-center bg-white/5 border border-white/10 p-6 md:p-8 rounded-[2rem] backdrop-blur-md min-w-[140px]">
                    <span class="block text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">Band Score</span>
                    <span class="text-5xl md:text-7xl font-black text-white">{{ $estimatedBand }}</span>
                </div>
                <div class="hidden sm:block w-px h-16 bg-white/10"></div>
                <div class="text-center">
                    <span class="block text-indigo-400 text-3xl md:text-4xl font-black">{{ $score }}/{{ $total }}</span>
                    <span class="block text-slate-500 text-[10px] font-bold uppercase tracking-wider">Correct Items</span>
                </div>
            </div>
        </div>
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-indigo-500/20 rounded-full blur-[100px]"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-blue-500/10 rounded-full blur-[100px]"></div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-12">
        @php
            $stats = [
                ['Accuracy', $percentage . '%', 'text-emerald-600', 'bg-emerald-50', 'border-emerald-100'],
                ['Raw Score', "$score / $total", 'text-blue-600', 'bg-blue-50', 'border-blue-100'],
                ['Status', 'Completed', 'text-slate-600', 'bg-white', 'border-slate-200'],
                ['Feedback', $percentage > 70 ? 'Excellent' : 'Keep Going', 'text-indigo-600', 'bg-indigo-50', 'border-indigo-100']
            ];
        @endphp
        
        @foreach ($stats as $stat)
        <div class="{{ $stat[3] }} p-5 rounded-[1.5rem] border {{ $stat[4] }} shadow-sm">
            <span class="block text-slate-400 text-[10px] font-extrabold uppercase tracking-widest mb-1">{{ $stat[0] }}</span>
            <span class="block text-lg md:text-xl font-black {{ $stat[2] }}">{{ $stat[1] }}</span>
        </div>
        @endforeach
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 px-2">
        <h3 class="text-2xl font-black text-slate-800 tracking-tight">Answer Breakdown</h3>
        <button onclick="window.print()" class="no-print flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            Print Performance Report
        </button>
    </div>

    <div class="space-y-4">
        @foreach ($results_comparison as $res)
            <div class="group bg-white p-5 md:p-6 rounded-[1.75rem] border border-slate-200/80 shadow-sm hover:border-indigo-200 transition-all duration-300">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start md:items-center gap-5">
                        <div class="flex-shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-2xl flex items-center justify-center font-black text-lg md:text-xl transition-transform group-hover:scale-105 {{ $res['isCorrect'] ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                            {{ $res['number'] }}
                        </div>
                        
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-[9px] font-black uppercase tracking-[0.1em] {{ $res['isCorrect'] ? 'text-emerald-500' : 'text-rose-500' }}">
                                    {{ $res['isCorrect'] ? 'Correct' : 'Incorrect' }}
                                </span>
                                @if ($res['context'])
                                    <span class="text-slate-300 font-bold">•</span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-[9px] font-bold uppercase text-slate-500 tracking-wider">
                                        {{ $res['context'] }}
                                    </span>
                                @endif
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-3">
                                <div>
                                    <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-tighter mb-1">Your Entry</span>
                                    <span class="text-sm font-bold {{ $res['isCorrect'] ? 'text-slate-700' : 'text-rose-700' }} uppercase break-words">
                                        {{ !empty($res['user']) ? $res['user'] : 'No Answer' }}
                                    </span>
                                </div>
                                
                                @if (!$res['isCorrect'])
                                <div>
                                    <span class="block text-[9px] font-bold text-indigo-400 uppercase tracking-tighter mb-1">Correct Answer</span>
                                    <span class="text-sm font-black text-indigo-600 uppercase break-words">
                                        {{ $res['correct'] }}
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex-shrink-0 hidden md:flex items-center justify-center">
                        @if ($res['isCorrect'])
                            <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-lg shadow-emerald-100">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </div>
                        @else
                            <div class="w-10 h-10 rounded-full bg-rose-500 text-white flex items-center justify-center shadow-lg shadow-rose-100">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-16 p-8 md:p-12 rounded-[2.5rem] bg-indigo-600 text-center text-white shadow-2xl shadow-indigo-200 no-print">
        <h4 class="text-2xl font-black mb-3">Ready for the next challenge?</h4>
        <p class="text-indigo-100 font-medium mb-8 max-w-md mx-auto opacity-80">Use these insights to patch your weak areas. Every test is a step closer to your target band.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto px-10 py-4 bg-white text-indigo-600 font-black rounded-2xl hover:bg-indigo-50 transition-all shadow-xl">New Practice Test</a>
            <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="w-full sm:w-auto px-10 py-4 bg-indigo-500/30 text-white font-bold rounded-2xl hover:bg-indigo-500/50 transition-all">Review Score</button>
        </div>
    </div>
</main>

<footer class="py-12 text-center no-print">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">© {{ date('Y') }} IELTS Lab AI Learning System • All Rights Reserved</p>
</footer>

</body>
</html>