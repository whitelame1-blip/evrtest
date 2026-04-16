<!DOCTYPE html>
<html lang="en" class="bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Review | {{ $testTitle }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Custom scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        
        /* Smooth scrolling for the whole page */
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <header class="bg-slate-900 text-white border-b-4 border-indigo-500 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <p class="text-indigo-400 font-bold text-xs tracking-widest uppercase mb-1">Kolibri AI Master Review</p>
                    <h1 class="text-2xl sm:text-3xl font-black">{{ $testTitle }}</h1>
                </div>
                <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto text-center px-6 py-2.5 bg-slate-800 hover:bg-slate-700 rounded-xl text-sm font-bold transition border border-slate-700">← Back to Dashboard</a>
            </div>
            
            <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
                <h2 class="text-rose-400 font-black text-xs uppercase mb-2 flex items-center tracking-wider">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Overall Diagnostic
                </h2>
                <p class="text-slate-200 text-sm sm:text-base leading-relaxed font-medium">
                    {{ $feedback['overall_analysis'] ?? 'No overall analysis provided.' }}
                </p>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <div class="lg:col-span-5 lg:sticky lg:top-8 transition-all">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-[500px] lg:h-[calc(100vh-4rem)]">
                    <div class="p-5 border-b bg-slate-50/50 flex justify-between items-center">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                            Original {{ $testType === 'READING' ? 'Passage' : 'Transcript' }}
                        </h3>
                        <span class="px-3 py-1 bg-white border rounded-full text-[10px] font-black text-slate-400 uppercase">{{ $testType }}</span>
                    </div>

                    <div class="p-6 overflow-y-auto custom-scroll flex-grow">
                        @if ($testType === 'READING')
                            <div class="prose prose-slate max-w-none text-slate-700 leading-relaxed">
                                @foreach (explode("\n", $sourceContent) as $p)
                                    @if (trim($p)) 
                                        <p class="mb-5">{{ $p }}</p> 
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-4">
                                @php $transcriptData = json_decode($sourceContent, true); @endphp
                                
                                @if (is_array($transcriptData))
                                    @foreach ($transcriptData as $chat)
                                        @php
                                            $isAgent = (stripos($chat['speaker'], 'Sarah') !== false || stripos($chat['speaker'], 'Agent') !== false);
                                            // Format **bold** text securely
                                            $formattedUtterance = str_replace(['**', '__'], ['<span class="font-bold underline">', '</span>'], htmlspecialchars($chat['utterance']));
                                        @endphp
                                        <div class="flex flex-col {{ $isAgent ? 'items-start' : 'items-end' }}">
                                            <span class="text-[9px] font-black text-slate-400 uppercase mb-1 ml-1">{{ $chat['speaker'] }}</span>
                                            <div class="max-w-[85%] p-3.5 rounded-2xl text-sm leading-relaxed {{ $isAgent ? 'bg-slate-100 text-slate-800 rounded-tl-none' : 'bg-indigo-600 text-white rounded-tr-none shadow-md shadow-indigo-100' }}">
                                                {!! $formattedUtterance !!}
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-slate-500 italic">{!! nl2br(e($sourceContent)) !!}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                        <h3 class="font-black text-orange-600 uppercase text-xs tracking-widest mb-4">Cognitive Gaps</h3>
                        <ul class="space-y-3">
                            @foreach($feedback['mistake_patterns'] ?? [] as $pattern)
                                <li class="flex items-start text-sm text-slate-600 font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 mt-1.5 mr-3 shrink-0"></span>
                                    {{ $pattern }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                        <h3 class="font-black text-indigo-600 uppercase text-xs tracking-widest mb-4">Actionable Directives</h3>
                        <ul class="space-y-3">
                            @foreach($feedback['focus_areas'] ?? [] as $focus)
                                <li class="flex items-start text-sm text-slate-600 font-medium">
                                    <svg class="w-4 h-4 text-indigo-500 mr-2 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                    {{ $focus }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="bg-rose-600 p-6 rounded-3xl shadow-lg shadow-rose-100 text-white">
                    <h3 class="font-black uppercase text-xs tracking-widest mb-2 opacity-80">Primary Test-Maker Trap</h3>
                    <p class="text-sm sm:text-base font-semibold leading-relaxed">
                        {{ $feedback['trap_logic'] ?? 'No specific trap logic identified.' }}
                    </p>
                </div>

                <div class="pt-4">
                    <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-3">
                        Detailed Question Analysis
                        <div class="h-px bg-slate-200 flex-grow"></div>
                    </h2>
                    
                    <div class="space-y-6">
                        @foreach($feedback['detailed_breakdown'] ?? [] as $q)
                            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                                <div class="bg-slate-50 px-6 py-3 border-b flex justify-between items-center">
                                    <span class="font-black text-slate-800">Question #{{ $q['question_number'] ?? '?' }}</span>
                                </div>
                                <div class="p-6 space-y-6">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="p-4 bg-rose-50 rounded-2xl border border-rose-100">
                                            <p class="text-[10px] text-rose-500 font-black uppercase mb-1">Your Answer</p>
                                            <p class="text-lg font-black text-rose-700">{{ $q['user_answer'] ?? 'Skipped' }}</p>
                                        </div>
                                        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100">
                                            <p class="text-[10px] text-emerald-600 font-black uppercase mb-1">Correct Answer</p>
                                            <p class="text-lg font-black text-emerald-700">{{ $q['correct_answer'] ?? 'N/A' }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Textual Evidence</p>
                                        <blockquote class="bg-slate-50 p-4 rounded-2xl border-l-4 border-indigo-500 italic text-slate-600 text-sm">
                                            "{{ $q['text_evidence'] ?? 'Not provided.' }}"
                                        </blockquote>
                                    </div>

                                    <div class="p-5 bg-indigo-50/50 rounded-2xl border border-indigo-100">
                                        <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2">Master Tutor Analysis</p>
                                        <p class="text-slate-800 text-sm leading-relaxed">
                                            {{ $q['explanation'] ?? 'No explanation generated.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pt-8">
                    <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-3">
                        Vocabulary Upgrade
                        <div class="h-px bg-slate-200 flex-grow"></div>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($feedback['vocabulary_expansion'] ?? [] as $vocab)
                            <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 hover:border-indigo-400 transition-colors group">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-black text-lg text-slate-900 group-hover:text-indigo-600 transition-colors">{{ $vocab['term'] ?? '' }}</h4>
                                    <span class="text-[10px] font-black text-slate-400 uppercase">Target</span>
                                </div>
                                <p class="text-xs text-slate-600 mb-4 font-medium">{{ $vocab['definition'] ?? '' }}</p>
                                <div class="p-3 bg-slate-50 rounded-xl mb-4 border border-slate-100">
                                    <p class="text-[9px] text-slate-400 font-black uppercase mb-1">Found in test as:</p>
                                    <p class="text-sm font-bold text-slate-700">{{ $vocab['synonym_in_test'] ?? '' }}</p>
                                </div>
                                <div class="text-xs text-indigo-700 bg-indigo-50 p-3 rounded-xl border border-indigo-100 italic">
                                    💡 {{ $vocab['usage_tip'] ?? '' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                
            </div>
        </div>
    </main>
</body>
</html>