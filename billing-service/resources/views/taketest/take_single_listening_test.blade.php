<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> Listening | {{ $test['topic'] ?? 'Listening Practice' }}</title>
    <script>
    document.onkeydown = function (e) {
        if (e.key === "F5" || (e.ctrlKey && e.key === "r")) {}
    };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/greghub/green-audio-player/dist/css/green-audio-player.min.css">
    <script src="https://cdn.jsdelivr.net/gh/greghub/green-audio-player/dist/js/green-audio-player.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/take_single_listening_test.css') }}">
    <script src="https://unpkg.com/wavesurfer.js@7"></script>

    <style>
/* Adjust the Pro-Script when inside the Drawer */
#pro-script-review.is-static {
    position: relative; 
    width: 100%;
    min-height: 250px;
}

/* Floating mode responsive limits */
#pro-script-review.is-floating {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: min(320px, calc(100vw - 40px));
    height: min(350px, calc(100vh - 100px));
    z-index: 99999; /* Higher z-index to stay above mobile nav */
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    touch-action: none; /* Prevents weird mobile zoom/scroll while interacting */
}

#side-backdrop { z-index: 45; }
#side-panel { z-index: 50; }

/* Resize Handle Styles */
.resize-handle {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 24px;
    height: 24px;
    cursor: nwse-resize;
    z-index: 100;
}
.resize-handle::after {
    content: '';
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 0 12px 12px;
    border-color: transparent transparent #cbd5e1 transparent;
}

/* Prevent SweetAlert from hiding scrollbars and shifting the layout */
body.swal2-height-auto {
    height: 100% !important;
}

html.swal2-shown, body.swal2-shown {
    height: 100% !important;
    overflow: auto !important; /* Forces the background to stay stable */
    padding-right: 0 !important; /* Prevents the "shifting" to the left */
}
    </style>
</head>
<div id="error-toast" class="fixed top-20 left-1/2 -translate-x-1/2 z-[99999] bg-red-600 text-white px-6 py-4 rounded-2xl shadow-2xl transform transition-all duration-300 -translate-y-10 opacity-0 pointer-events-none flex items-center gap-3 w-[90%] max-w-md">
    <i data-lucide="alert-circle" class="w-6 h-6 flex-shrink-0"></i>
    <span class="font-bold text-sm md:text-base">Please answer all questions before submitting.</span>
</div>
{{-- 1. STRICT MODE MODAL (Only for brand new attempts) --}}
@if ($isStrictAttempt ?? false)
<div id="strict-warning-modal" class="fixed inset-0 z-[999999] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md">
    <div class="bg-white max-w-md w-full rounded-3xl shadow-2xl p-8 text-center border-t-4 border-amber-500">
        <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="alert-triangle" class="w-8 h-8"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800 mb-4">Exam Conditions</h3>
        <p class="text-slate-600 mb-6 leading-relaxed text-sm">
            Once you begin, the audio <b>will automatically play after 1 minute</b>. It <b>will not stop</b> and you <b>cannot rewind, pause, or fast-forward</b>. This simulates the unrelenting pressure of real exams.
        </p>
        <div class="flex flex-col gap-3">
            <button onclick="startStrictTest()" id="start-exam-btn" class="w-full py-4 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl transition-all shadow-lg shadow-amber-200">
                I am ready, Start Test
            </button>
            <a href="{{ route('dashboard') }}" class="w-full py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition-all">
                Cancel, I'll try later
            </a>
        </div>
    </div>
</div>
@endif

{{-- 2. INTERRUPTED ATTEMPT MODAL (For users who refreshed or left) --}}
@if ($isInterrupted ?? false)
<div style="display: none;" id="interrupted-modal" class="fixed inset-0 z-[999999] flex items-center justify-center p-4 bg-slate-900/90 backdrop-blur-md">
    <div class="bg-white max-w-md w-full rounded-3xl shadow-2xl p-8 text-center border-t-8 border-red-600">
        <div class="w-20 h-20 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6 animate-pulse">
            <i data-lucide="alert-octagon" class="w-10 h-10"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800 mb-4 tracking-tight">Attempt Interrupted</h3>
        <p class="text-slate-600 mb-6 leading-relaxed text-sm">
            There is <b>no second chance</b> in real exams. If you refreshed to cheat, try to be honest with yourself and do not gain false hopes. If it was an accident, be more careful on your next test.
            <br><br>
            <span class="text-red-600 font-bold block bg-red-50 p-3 rounded-lg border border-red-100">
                Navigation is now unlocked for practice purposes, but this is no longer considered a "Blind First Try."
            </span>
        </p>
        <button onclick="acknowledgeWarning()" class="w-full py-4 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-red-200">
    Acknowledge & Continue
</button>
    </div>
</div>
@endif
<body class="h-full overflow-hidden flex flex-col">

<nav class="h-16 bg-white border-b flex items-center justify-between px-4 lg:px-8 z-30 shrink-0 shadow-sm">
    <div class="flex items-center gap-3 lg:gap-4">
        <button onclick="toggleMobileMenu()" class="lg:hidden p-2 -ml-2 text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        <div class="w-8 h-8 lg:w-10 lg:h-10 bg-indigo-600 rounded-lg lg:rounded-xl flex items-center justify-center text-white shadow-indigo-200 shadow-lg">
            <i data-lucide="headphones" class="w-4 h-4 lg:w-5 lg:h-5"></i>
        </div>
        <div>
            <h1 class="font-bold text-slate-800 text-sm lg:text-base leading-tight">{{ $global_test_title ?? 'Test' }}</h1>
        </div>
    </div>
    <div class="flex items-center gap-3 lg:gap-6">
        <div class="flex items-center gap-2 bg-slate-100 px-3 py-1.5 lg:px-4 lg:py-2 rounded-full border border-slate-200">
            <i data-lucide="timer" class="w-3.5 h-3.5 lg:w-4 lg:h-4 text-indigo-500"></i>
            <span class="font-mono font-bold text-xs lg:text-sm text-slate-700" id="timer">00:00</span>
        </div>
        <button onclick="confirmExit()" class="p-2 text-slate-400 hover:text-red-500 transition-colors">
            <i data-lucide="log-out" class="w-5 h-5"></i>
        </button>
    </div>
</nav>

<main class="flex flex-col lg:flex-row flex-1 overflow-hidden relative">
    <div id="side-backdrop" onclick="toggleMobileMenu()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity duration-300 opacity-0"></div>
    
    <aside id="side-panel" class="fixed inset-y-0 left-0 z-50 w-80 bg-white border-r flex flex-col shrink-0 p-6 overflow-y-auto hide-scrollbar 
        transform -translate-x-full transition-transform duration-300 ease-in-out
        lg:relative lg:translate-x-0 lg:z-30 lg:max-h-full">
        <div class="flex lg:hidden items-center justify-between mb-6">
             <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Test Tools</span>
             <button onclick="toggleMobileMenu()" class="p-2 text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        
        <div id="audio-player-container" class="ielts-custom-player {{ ($isStrictAttempt ?? false) ? 'test-live' : 'test-review' }}">
    <div class="flex items-center gap-4">
        <button id="master-play-btn" class="w-10 h-10 lg:w-12 lg:h-12 bg-indigo-600 text-white rounded-full flex items-center justify-center hover:bg-indigo-700 transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed shrink-0 relative">
            <i data-lucide="play" id="play-icon" class="w-5 h-5 fill-current"></i>
            <div id="btn-lock-overlay" class="absolute inset-0 bg-white/20 hidden items-center justify-center rounded-full cursor-not-allowed">
                <i data-lucide="lock" class="w-4 h-4 text-white"></i>
            </div>
        </button>

        <div class="flex-1 min-w-0">
            <div class="relative group">
                <div id="progress-track" class="w-full h-2 bg-slate-100 rounded-full overflow-hidden cursor-pointer">
                    <div id="audio-progress-bar" class="h-full bg-indigo-500 transition-all duration-100" style="width: 0%"></div>
                </div>
                <div id="click-shield" class="absolute inset-0 z-20 cursor-not-allowed hidden"></div>
            </div>
            <div class="flex justify-between mt-1 text-[10px] font-bold font-mono text-slate-400">
                <span id="current-time">0:00</span>
                <span id="duration-time">0:00</span>
            </div>
        </div>

        <div class="relative flex items-center">
   <div class="volume-container">
    <input type="range" id="volume-slider" min="0" max="1" step="0.01" value="1" 
           style="writing-mode: vertical-lr; direction: rtl; width: 12px; height: 80px;">
</div>
    
    <button id="master-volume-btn" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
        <i data-lucide="volume-2" class="w-5 h-5 text-slate-600"></i>
    </button>
</div>
    </div>
</div>
        
        <div class="flex-1">
            <h2 class="text-[10px] lg:text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 lg:mb-4">Question Map</h2>
            <div class="grid grid-cols-8 sm:grid-cols-10 lg:grid-cols-5 gap-1.5 lg:gap-2">
               @for ($i = 0; $i < ($total_question_dots ?? 40); $i++)
    <div id="nav-{{ $i + 1 }}" 
         onclick="scrollToQuestion({{ $i + 1 }})"
         data-answered="false"
         class="nav-dot cursor-pointer w-full aspect-square bg-slate-50 border border-slate-200 rounded-lg lg:rounded-xl flex items-center justify-center text-[10px] lg:text-xs font-bold text-slate-400 transition-all">
        {{ $i + 1 }}
    </div>
@endfor
            </div>
        </div>

        @php 
            $hasScript = !empty($test['word_data_json']) && $test['word_data_json'] !== 'null';
            $containerClass = $hasScript ? 'is-floating' : 'is-static';
        @endphp

        <div id="transcript-sidebar-anchor" class="mt-auto">
            <div id="pro-script-review" class="rounded-3xl overflow-hidden flex flex-col {{ $containerClass }}">
                <div class="relative w-full flex items-center justify-between p-3 shrink-0 border-b border-slate-100 min-h-[56px] drag-header">
                    
                    <div class="flex-1 flex justify-start items-center gap-3">
                        <button id="settings-btn" onclick="toggleSettings()" 
                            class="p-2 text-slate-400 hover:text-indigo-600 transition-colors {{ !$hasScript ? 'hidden' : '' }}">
                            <i data-lucide="settings-2" class="w-4 h-4"></i>
                        </button>

                        <div id="master-toggle-wrapper" class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200 {{ !$hasScript ? 'hidden' : '' }}">
                            <span id="script-status-text" class="text-[9px] font-extrabold uppercase tracking-tighter text-slate-400">Off</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="master-script-toggle" class="sr-only peer" onchange="toggleScriptEngine(this.checked)">
                                <div class="w-7 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-center flex-1">
                        <div id="main-drag-grip" class="drag-handle-circle {{ !$hasScript ? 'hidden' : '' }}" style="margin: 0;">
                            <div class="flex gap-1 pointer-events-none">
                                <div class="w-1 h-1 bg-white rounded-full"></div>
                                <div class="w-1 h-1 bg-white rounded-full"></div>
                                <div class="w-1 h-1 bg-white rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 flex justify-end"></div>
                </div>
     
                <div class="flex-1 overflow-hidden relative">
                    <div id="script-unlock-overlay" 
     class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-slate-50/95 backdrop-blur-sm {{ $hasScript ? 'hidden' : '' }}">
    <div class="p-4 text-center">
        @if(!$hasSubmittedBefore)
            {{-- STATE 1: User has NEVER submitted this test --}}
            <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3">
                <i data-lucide="lock" class="w-6 h-6"></i>
            </div>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">Transcript Locked</p>
            <p class="text-slate-400 text-[9px] mb-3 px-4">Complete the test once to view the AI transcript</p>
            <button disabled 
                    class="bg-slate-200 text-slate-400 px-6 py-2.5 rounded-xl font-bold text-xs cursor-not-allowed">
                Unlock Transcript
            </button>
        @else
            {{-- STATE 2: User has submitted and is allowed to unlock --}}
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-3">
                <i data-lucide="sparkles" class="w-6 h-6"></i>
            </div>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-3">AI Analysis Required</p>
            <button onclick="unlockProScript('{{ $test['id'] }}')" 
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-bold text-xs shadow-lg shadow-indigo-200 transition-all">
                Unlock Transcript
            </button>
        @endif
    </div>
</div>

                    <div id="transcript-container" 
                         class="h-full overflow-y-auto p-6 text-slate-800 leading-relaxed text-sm transition-opacity duration-700 {{ $hasScript ? 'opacity-100' : 'opacity-10' }}">
                    </div>
                </div>

                <div id="resize-handle" class="resize-handle {{ !$hasScript ? 'hidden' : '' }}"></div>
            </div>
        </div>

        <button type="button" 
        id="submit-test-btn" 
        onclick="submitTest()" 
        @if($isStrictAttempt) disabled @endif
        class="mt-3 lg:mt-6 w-full bg-indigo-600 text-white py-4 rounded-2xl font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
    Submit Answers
    
</button>
@if($isStrictAttempt)
    <p id="submit-lock-hint" class="text-center text-[10px] text-slate-400 mt-2">
        <i data-lucide="lock" class="w-3 h-3 inline mr-1"></i>
        Submission is locked until audio finishes
    </p>
@endif

    </aside>
    <section class="flex-1 overflow-y-auto p-4 lg:p-8 bg-slate-50">
        <div class="max-w-6xl mx-auto space-y-10 pb-20">
            
            <div class="text-center space-y-2 mb-10">
                <span class="bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">Section Overview</span>
                <h2 class="text-3xl font-extrabold text-slate-900">{{ $test['form_title'] ?? 'Exam Module' }}</h2>
            </div>

            @if (!empty($test['map_svg']))
            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 shadow-sm mb-10">
                <div class="bg-slate-50 rounded-3xl p-6 flex justify-center border border-dashed border-slate-300">
                    <div class="w-full max-w-md drop-shadow-xl">{!! $test['map_svg'] !!}</div>
                </div>
            </div>
            @endif
            
            <form id="testForm" action="{{ route('test.submitListening') }}" method="POST">
                @csrf
                <input type="hidden" name="test_id" value="{{ $test_id ?? ($test['id'] ?? '') }}">

                @php 
                $global_dot_pointer = 0;
                $layout_index = 0;
                $global_q_num = 1;
                @endphp

                @foreach ($blocks as $block)
                    @php
                        $b_type = $block['question_type'];
                        $subtype = $block['subtype'];
                        $b_questions = $block['questions'];
                    @endphp

                    <div class="mb-14">

                    @if ($b_type === 'completion' || in_array($subtype, ['FORM', 'TABLE', 'FLOW-CHART', 'FLOWCHART', 'NOTES', 'SUMMARY']))
                        
                        @php
                            $layout_data = $layouts[$layout_index] ?? $layouts[0] ?? [];
                            $structure = $layout_data['structure'] ?? [];
                            $word_limit = $layout_data['word_limit_instruction'] ?? $test['word_limit_rule'] ?? '';
                            $layout_index++;
                        @endphp

                        @if (!empty($word_limit))
                            <div class="mb-6">
                                <p class="text-slate-500 text-sm italic border-l-4 border-indigo-400 pl-3">
                                    Instruction: Write <strong>{{ $word_limit }}</strong> for each answer.
                                </p>
                            </div>
                        @endif

                        @if ($subtype === 'FORM')
                            <div class="max-w-3xl mx-auto bg-white border border-slate-300 shadow-lg rounded-xl overflow-hidden mb-10">
                                @foreach($structure as $item)
                                    @if(($item['type'] ?? '') === 'HEADING')
                                        <div class="bg-slate-800 p-6 text-center text-white font-bold text-xl uppercase tracking-widest">
                                            {{ $item['text'] ?? '' }}
                                        </div>
                                    @elseif(($item['type'] ?? '') === 'SUB_HEADING')
                                        <div class="bg-slate-100 px-8 py-3 border-y border-slate-200 font-bold text-slate-700 uppercase text-xs tracking-widest">
                                            {{ $item['text'] ?? '' }}
                                        </div>
                                    @else
                                        <div class="px-8 py-4 border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                            {{-- Render Item Snippet --}}
                                            <div class="flex items-baseline gap-3 min-h-[28px]">
                                                @if (($item['type'] ?? '') === 'QUESTION')
                                                    @php
                                                        $q_num = $item['q_id'];
                                                        $tracker_idx = $q_num - 1;
                                                        $prefix = trim($item['prefix'] ?? '');
                                                        $suffix = trim($item['suffix'] ?? '');
                                                    @endphp
                                                    <span class="text-[10px] font-black bg-indigo-600 text-white w-5 h-5 flex items-center justify-center rounded shadow-sm shrink-0">{{ $q_num }}</span>
                                                    <div class="flex-1 text-slate-700 text-sm leading-relaxed">
                                                        @if($prefix) {{ $prefix }} @endif
                                                        <input type="text" name="ans[{{ $q_num }}]" oninput="updateTracker({{ $q_num }}, this.value)" class="exam-input w-full max-w-[200px]" placeholder="................">
                                                        @if($suffix) {{ $suffix }} @endif
                                                    </div>
                                                @else
                                                    <div class="w-5 h-5 flex items-center justify-center shrink-0"><div class="w-1.5 h-1.5 rounded-full bg-slate-300"></div></div>
                                                    <div class="flex-1 text-slate-700 text-sm leading-relaxed">
                                                        @php $parts = explode(':', $item['text'] ?? '', 2); @endphp
                                                        @if (count($parts) > 1)
                                                            <span class="text-slate-500 font-medium">{{ trim($parts[0]) }}:</span>
                                                            <span class="font-bold text-slate-800">{{ trim($parts[1]) }}</span>
                                                        @else
                                                            <span class="font-bold text-slate-800">{{ $item['text'] ?? '' }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                        @elseif ($subtype === 'FLOW-CHART' || $subtype === 'FLOWCHART')
                            @php
                                $heading = "Process Flow";
                                $nodes = [];
                                $current_node = [];
                                
                                foreach ($structure as $item) {
                                    if (($item['type'] ?? '') === 'HEADING') {
                                        $heading = $item['text'];
                                    } elseif (($item['type'] ?? '') === 'QUESTION') {
                                        if (!empty($current_node)) $nodes[] = $current_node;
                                        $current_node = [$item];
                                    } elseif (($item['type'] ?? '') === 'STATIC_POINT') {
                                        $current_node[] = $item;
                                    }
                                }
                                if (!empty($current_node)) $nodes[] = $current_node;
                            @endphp
                            <div class="max-w-2xl mx-auto mb-10">
                                <h3 class="text-2xl font-extrabold text-slate-800 text-center mb-10 border-b-2 border-slate-200 pb-4">{{ $heading }}</h3>
                                <div class="flex flex-col items-center">
                                    @foreach ($nodes as $index => $node)
                                        <div class="bg-white border-2 border-slate-200 shadow-md rounded-2xl p-6 w-full max-w-lg hover:border-indigo-300 transition-colors">
                                            <div class="space-y-3">
                                                @foreach($node as $item)
                                                    {{-- Render Item Snippet --}}
                                                    <div class="flex items-baseline gap-3 min-h-[28px]">
                                                        @if (($item['type'] ?? '') === 'QUESTION')
                                                            @php
                                                                $q_num = $item['q_id'];
                                                                $tracker_idx = $q_num - 1;
                                                                $prefix = trim($item['prefix'] ?? '');
                                                                $suffix = trim($item['suffix'] ?? '');
                                                            @endphp
                                                            <span class="text-[10px] font-black bg-indigo-600 text-white w-5 h-5 flex items-center justify-center rounded shadow-sm shrink-0">{{ $q_num }}</span>
                                                            <div class="flex-1 text-slate-700 text-sm leading-relaxed">
                                                                @if($prefix) {{ $prefix }} @endif
                                                                <input type="text" name="ans[{{ $q_num }}]" oninput="updateTracker({{ $q_num }}, this.value)" class="exam-input w-full max-w-[200px]" placeholder="................">
                                                                @if($suffix) {{ $suffix }} @endif
                                                            </div>
                                                        @else
                                                            <div class="w-5 h-5 flex items-center justify-center shrink-0"><div class="w-1.5 h-1.5 rounded-full bg-slate-300"></div></div>
                                                            <div class="flex-1 text-slate-700 text-sm leading-relaxed">
                                                                @php $parts = explode(':', $item['text'] ?? '', 2); @endphp
                                                                @if (count($parts) > 1)
                                                                    <span class="text-slate-500 font-medium">{{ trim($parts[0]) }}:</span>
                                                                    <span class="font-bold text-slate-800">{{ trim($parts[1]) }}</span>
                                                                @else
                                                                    <span class="font-bold text-slate-800">{{ $item['text'] ?? '' }}</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @if ($index < count($nodes) - 1)
                                            <div class="flex flex-col items-center my-2">
                                                <div class="w-0.5 h-6 bg-slate-300"></div>
                                                <div class="w-3 h-3 border-r-2 border-b-2 border-slate-400 transform rotate-45 -mt-1"></div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                        @elseif ($subtype === 'TABLE' || $subtype === 'table')
                            @php
                                $headers = $layout_data['table_headers'] ?? ['Category', 'Details', 'Features'];
                                $column_count = count($headers);
                                $groups = [];
                                $current_group_name = "";
                                
                                foreach ($structure as $item) {
                                    if (($item['type'] ?? '') === 'SUB_HEADING') {
                                        $current_group_name = $item['text'];
                                        foreach ($headers as $h) {
                                            $groups[$current_group_name][$h] = [];
                                        }
                                    } elseif (in_array(($item['type'] ?? ''), ['QUESTION', 'STATIC_POINT'])) {
                                        $header_key = $item['belongs_header'] ?? '';
                                        if ($current_group_name !== "" && isset($groups[$current_group_name][$header_key])) {
                                            $groups[$current_group_name][$header_key][] = $item;
                                        }
                                    }
                                }
                            @endphp
                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-xl overflow-x-auto mb-10">
                                <table class="w-full border-collapse min-w-[800px]"> 
                                    <thead>
                                        <tr class="bg-slate-900 text-white">
                                            @foreach ($headers as $index => $header)
                                                <th class="p-6 text-left text-xs font-black uppercase tracking-[0.2em] {{ ($index < $column_count - 1) ? 'border-r border-slate-700' : '' }}">
                                                    {{ $header }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach ($groups as $row_title => $columns)
                                        <tr class="group hover:bg-slate-50/50 transition-colors">
                                            @foreach ($headers as $index => $header_name)
                                                <td class="p-8 align-top {{ ($index < $column_count - 1) ? 'border-r border-slate-200' : '' }} space-y-4">
                                                    @if ($index === 0)
                                                        <span class="font-black text-indigo-600">{{ str_replace(['The ', 'Material: '], '', $row_title) }}</span>
                                                    @endif
                                                    @if (!empty($columns[$header_name]))
                                                        @foreach ($columns[$header_name] as $item)
                                                            {{-- Render Item Snippet --}}
                                                            <div class="flex items-baseline gap-3 min-h-[28px]">
                                                                @if (($item['type'] ?? '') === 'QUESTION')
                                                                    @php
                                                                        $q_num = $item['q_id'];
                                                                        $tracker_idx = $q_num - 1;
                                                                        $prefix = trim($item['prefix'] ?? '');
                                                                        $suffix = trim($item['suffix'] ?? '');
                                                                    @endphp
                                                                    <span class="text-[10px] font-black bg-indigo-600 text-white w-5 h-5 flex items-center justify-center rounded shadow-sm shrink-0">{{ $q_num }}</span>
                                                                    <div class="flex-1 text-slate-700 text-sm leading-relaxed">
                                                                        @if($prefix) {{ $prefix }} @endif
                                                                       <input type="text" name="ans[{{ $q_num }}]" oninput="updateTracker({{ $q_num }}, this.value)" class="exam-input w-full max-w-[200px]" placeholder="................">
                                                                        @if($suffix) {{ $suffix }} @endif
                                                                    </div>
                                                                @else
                                                                    <div class="w-5 h-5 flex items-center justify-center shrink-0"><div class="w-1.5 h-1.5 rounded-full bg-slate-300"></div></div>
                                                                    <div class="flex-1 text-slate-700 text-sm leading-relaxed">
                                                                        @php $parts = explode(':', $item['text'] ?? '', 2); @endphp
                                                                        @if (count($parts) > 1)
                                                                            <span class="text-slate-500 font-medium">{{ trim($parts[0]) }}:</span>
                                                                            <span class="font-bold text-slate-800">{{ trim($parts[1]) }}</span>
                                                                        @else
                                                                            <span class="font-bold text-slate-800">{{ $item['text'] ?? '' }}</span>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    @else
                        
                       @if ($subtype === 'STANDARD' || $subtype === 'MCQ')
    {{-- max-w-4xl for Desktop, but full width with padding (px-4) for Mobile --}}
    <div class="max-w-4xl mx-auto space-y-4 md:space-y-8 px-4 md:px-0">
        @foreach ($b_questions as $q)
            @php
                $q_num = $q['id'];
                $tracker_idx = $q_num - 1;
            @endphp
            {{-- Reduced padding on mobile (p-5) vs desktop (p-8) --}}
            <div class="bg-white p-5 md:p-8 rounded-2xl md:rounded-[2rem] border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                
                {{-- Question Header: Better alignment for long questions --}}
                <div class="flex items-start gap-3 md:gap-4 mb-5 md:mb-6">
                    <span class="flex-shrink-0 w-8 h-8 md:w-10 md:h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center font-bold shadow-lg shadow-indigo-100 text-sm md:text-base">
                        {{ $q_num }}
                    </span>
                    <h3 class="text-base md:text-lg font-bold text-slate-800 leading-snug pt-1">
                        {{ $q['question_text'] ?? '' }}
                    </h3>
                </div>

                {{-- Options Grid: ml-0 on mobile so text has more room to breathe --}}
                <div class="grid grid-cols-1 gap-2 md:gap-3 ml-0 md:ml-12">
                    @foreach ($q['options'] as $key => $value)
                        <label class="group relative flex items-center p-4 md:p-5 rounded-xl md:rounded-2xl border-2 border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/30 cursor-pointer transition-all active:scale-[0.98]">
                            
                            {{-- Larger Radio Input for better touch targets --}}
                            <input type="radio" 
                                   name="ans[{{ $q_num }}]" 
                                   value="{{ $key }}"
                                   onchange="updateTracker({{ $q_num }}, this.value)"
                                   class="w-5 h-5 md:w-6 md:h-6 text-indigo-600 border-slate-300 focus:ring-indigo-500 cursor-pointer">
                            
                            <div class="ml-3 md:ml-4 flex gap-2 items-baseline">
                                <span class="font-black text-indigo-500 text-sm md:text-base">{{ $key }}.</span>
                                <span class="text-slate-600 font-medium group-hover:text-slate-900 text-sm md:text-base leading-tight">
                                    {{ $value }}
                                </span>
                            </div>
                            
                            {{-- Check icon hidden on very small screens to save space, visible on tablet/desktop --}}
                            <div class="absolute right-4 hidden sm:block opacity-0 group-has-[:checked]:opacity-100 transition-opacity">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-indigo-600"></i>
                            </div>

                            {{-- Mobile-only background highlight for checked state --}}
                            <div class="absolute inset-0 rounded-xl md:rounded-2xl border-indigo-500 opacity-0 group-has-[:checked]:border-2 pointer-events-none transition-all"></div>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif

                       @if ($subtype === 'MULTI-SELECT' || $subtype === 'MULTISELECT' || $subtype === 'multi_select')
    @php
        $grouped_questions = [];
        foreach ($b_questions as $q) {
            $gid = $q['task_group_id'] ?? $q['id'];
            $grouped_questions[$gid][] = $q;
        }
    @endphp
    <div class="max-w-4xl mx-auto space-y-6 lg:space-y-10 px-2 sm:px-4">
        @foreach ($grouped_questions as $gid => $group)
            @php
                $first_q = $group[0];
                $num_expected = count($group);
                $words = [1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR', 5 => 'FIVE'];
                $instruction_word = $words[$num_expected] ?? $num_expected;
                
                $display_id = (count($group) > 1) 
                    ? $group[0]['id'] . '-' . end($group)['id'] 
                    : $group[0]['id'];
            @endphp
            
            {{-- Responsive Padding: p-5 for mobile, p-8 for desktop --}}
            <div class="bg-white p-5 sm:p-8 rounded-[1.5rem] sm:rounded-[2.5rem] border border-slate-200 shadow-sm transition-all overflow-hidden">
                
                {{-- Header Section: Stacked on mobile, side-by-side on tablet+ --}}
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4 sm:mb-2">
                    <span class="w-fit flex-shrink-0 px-3 h-8 bg-indigo-600 text-white rounded-lg flex items-center justify-center font-bold shadow-lg shadow-indigo-100 text-sm">
                        {{ $display_id }}
                    </span>
                    <h3 class="text-base sm:text-lg font-bold text-slate-800 leading-snug">
                        {{ $first_q['question_text'] ?? '' }}
                    </h3>
                </div>
                
                {{-- Instruction: Adjusted margin for mobile --}}
                <p class="sm:ml-14 text-[9px] sm:text-[10px] text-indigo-500 font-black uppercase tracking-wider mb-6">
                    Select {{ $instruction_word }} options
                </p>

                {{-- Grid: 1 col on mobile, 2 col on md+, 3 col on xl+ (optional) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 sm:ml-14">
                    @foreach ($first_q['options'] as $key => $value)
                        <label class="group flex items-start p-3 sm:p-4 rounded-xl sm:rounded-2xl border-2 border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/30 cursor-pointer transition-all">
                            <div class="flex items-center h-5">
                                @php
    // Collect all associated question IDs (e.g., [11, 12]) so the tracker knows which dots to color
    $qids_json = json_encode(array_column($group, 'id'));
@endphp
<input type="checkbox" 
       name="ans_group[{{ $gid }}][]" 
       value="{{ $key }}"
       data-qids="{{ $qids_json }}"
       data-max="{{ $num_expected }}"
       onchange="handleMultiSelectGroup(this, {{ $num_expected }})"
       class="w-4 h-4 sm:w-5 sm:h-5 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500 cursor-pointer multi-check">
                            </div>
                            <div class="ml-3 sm:ml-4 flex gap-2 overflow-hidden">
                                <span class="flex-shrink-0 font-black text-indigo-400 text-sm sm:base">
    {{ chr(65 + $loop->index) }}.
</span>
                               <span class="text-slate-600 font-medium group-hover:text-slate-900 text-sm sm:text-base leading-tight break-words">
    {{-- This regex removes "A. ", "B. ", etc., from the start of the string if it exists --}}
    {{ preg_replace('/^[A-Z]\.\s+/', '', $value) }}
</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
                        @if ($subtype === 'MATCHING')
                            <div class="max-w-4xl mx-auto space-y-12">
                                @foreach ($b_questions as $q_item)
                                    <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden">
                                        <div class="p-8 bg-slate-50 border-b border-slate-100">
                                            <h3 class="text-lg font-extrabold text-slate-800 mb-6 flex items-center gap-3">
                                                <i data-lucide="layers" class="w-5 h-5 text-indigo-500"></i>
                                                {{ $q_item['question_text'] ?? '' }}
                                            </h3>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                @foreach ($q_item['options'] as $key => $value)
                                                    <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-200 text-sm">
                                                        <span class="font-black text-indigo-600 w-6 h-6 flex items-center justify-center bg-indigo-50 rounded-lg shrink-0">{{ $key }}</span>
                                                        <span class="text-slate-600">{{ $value }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="p-8 space-y-4">
                                            @foreach ($q_item['items'] as $item)
                                                @php
                                                    $q_num = $global_q_num++; 
                                                    $tracker_idx = $q_num - 1;
                                                    $input_name = "ans[" . $q_num . "]"; 
                                                @endphp
                                                <div class="flex flex-col md:flex-row md:items-center justify-between p-5 rounded-2xl border-2 border-slate-50 hover:border-indigo-100 transition-all gap-4">
                                                    <div class="flex items-center gap-4">
                                                        <span class="w-8 h-8 bg-slate-800 text-white rounded-lg flex items-center justify-center font-bold text-sm">
                                                            {{ $q_num }} 
                                                        </span>
                                                        <span class="font-bold text-slate-700">{{ $item['text'] ?? '' }}</span>
                                                    </div>

                                                    <select name="{{ $input_name }}" 
                                                            onchange="updateTracker({{ $q_num }}, this.value)"
                                                            class="bg-white border-2 border-slate-200 rounded-xl px-4 py-2 font-bold text-indigo-600 outline-none cursor-pointer">
                                                        <option value="" disabled selected>Select...</option>
                                                        @foreach ($q_item['options'] as $key => $value)
                                                            <option value="{{ $key }}">{{ $key }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (in_array($subtype, ['OPINION', 'CLASSIFICATION', 'PROCEDURAL', 'FACT_MATCHING']))
                            @php
                                $grouped_blocks = [];
                                
                                foreach ($b_questions as $q) {
                                    $groupId = $q['task_group_id'] ?? ($q['script_title'] ?? 'default');
                                    
                                    if (!isset($grouped_blocks[$groupId])) {
                                        $grouped_blocks[$groupId] = [
                                            'min_id' => 999, 
                                            'max_id' => 0,
                                            'instruction' => $q['instruction'] ?? 'Match the following:',
                                            'options'     => $q['matching_options'] ?? $q['options'] ?? [],
                                            'items'       => [],
                                            'all_answers' => []
                                        ];
                                    }

                                    if (isset($q['items']) && is_array($q['items'])) {
                                        foreach ($q['items'] as $subItem) {
                                            $grouped_blocks[$groupId]['items'][] = [
                                                'id'   => $subItem['id'],
                                                'text' => $subItem['text']
                                            ];
                                            $grouped_blocks[$groupId]['min_id'] = min($grouped_blocks[$groupId]['min_id'], $subItem['id']);
                                            $grouped_blocks[$groupId]['max_id'] = max($grouped_blocks[$groupId]['max_id'], $subItem['id']);
                                        }
                                        if (isset($q['answer']) && is_array($q['answer'])) {
                                            $grouped_blocks[$groupId]['all_answers'] = array_merge($grouped_blocks[$groupId]['all_answers'], array_values($q['answer']));
                                        }
                                    } else {
                                        $grouped_blocks[$groupId]['items'][] = [
                                            'id'   => $q['id'],
                                            'text' => $q['text'] ?? $q['question_text'] ?? ''
                                        ];
                                        $grouped_blocks[$groupId]['min_id'] = min($grouped_blocks[$groupId]['min_id'], $q['id']);
                                        $grouped_blocks[$groupId]['max_id'] = max($grouped_blocks[$groupId]['max_id'], $q['id']);
                                        
                                        if (isset($q['answer'])) {
                                            $grouped_blocks[$groupId]['all_answers'][] = is_array($q['answer']) ? reset($q['answer']) : $q['answer'];
                                        }
                                    }
                                }
                            @endphp

                            <div class="max-w-4xl mx-auto space-y-12">
                                @foreach ($grouped_blocks as $block)
                                    @php
                                        $options = $block['options'];
                                        $answer_counts = array_count_values(array_filter($block['all_answers']));
                                        $has_duplicates = false;
                                        foreach ($answer_counts as $count) { if ($count > 1) { $has_duplicates = true; break; } }
                                    @endphp

                                    <div class="task-group-block relative p-4 md:p-8 bg-white rounded-[2.5rem] border border-slate-100 shadow-sm">
                                        
                                        <div class="mb-8">
                                            <div class="border-l-4 border-indigo-600 bg-slate-50 p-5 rounded-r-2xl border border-slate-200">
                                                <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded mb-2 inline-block">
                                                    Task Section
                                                </span>
                                                <h2 class="text-slate-800 text-base md:text-lg font-bold leading-snug">
                                                    (Questions {{ $block['min_id'] }}–{{ $block['max_id'] }}) 
                                                    {{ $block['instruction'] }}
                                                </h2>
                                                
                                                @if ($has_duplicates)
                                                    <div class="mt-3 flex items-center gap-2 text-amber-600 font-bold text-xs italic bg-amber-50 w-fit px-3 py-1.5 rounded-lg border border-amber-200">
                                                        <i data-lucide="info" class="w-3.5 h-3.5"></i>
                                                        NB: You may use any letter more than once.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        @if (!empty($options))
                                        <div class=" sticky top-3 z-50 bg-slate-900 rounded-3xl p-5 mb-8 shadow-xl border border-slate-700">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                                @foreach ($options as $key => $opt)
                                                    @php
                                                        if (strpos($opt, '. ') !== false) {
                                                            $parts = explode('. ', $opt, 2);
                                                            $letter = $parts[0];
                                                            $label = $parts[1];
                                                        } else {
                                                            $letter = is_numeric($key) ? $key : $key; 
                                                            $label = $opt;
                                                        }
                                                    @endphp
                                                    <div class="flex items-center gap-3 bg-slate-800/50 p-2.5 rounded-xl border border-slate-700/50">
                                                        <span class="w-7 h-7 shrink-0 bg-amber-500 text-slate-900 rounded-lg flex items-center justify-center font-black text-xs">
                                                            {{ $letter }}
                                                        </span>
                                                        <span class="text-slate-200 font-semibold text-[11px] leading-tight">
                                                            {{ $label }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                        <div class="space-y-4">
                                            @foreach ($block['items'] as $item)
                                                @php
                                                    $q_id = $item['id'];
                                                    $dot_idx = $global_dot_pointer++; 
                                                @endphp
                                                <div class="bg-slate-50/50 p-4 md:p-5 rounded-2xl border border-slate-100 flex flex-col md:flex-row items-center gap-6 hover:border-indigo-300 transition-all group">
                                                    
                                                    <div class="w-10 h-10 shrink-0 flex items-center justify-center bg-white rounded-xl text-slate-400 font-black text-xs group-hover:bg-indigo-600 group-hover:text-white transition-colors shadow-sm border border-slate-100">
                                                        {{ $q_id }}
                                                    </div>

                                                    <div class="flex-1">
                                                        <p class="text-slate-700 font-medium text-sm leading-relaxed">
                                                            {{ $item['text'] ?? '' }}
                                                        </p>
                                                    </div>

                                                    <div class="shrink-0 w-full md:w-auto">
                                                        <select name="ans[{{ $q_id }}]" 
                                                                oninput="updateTracker({{ $q_id }}, this.value)"
                                                                class="w-full md:w-44 bg-white border-2 border-slate-200 rounded-xl px-4 py-3 font-black text-indigo-600 focus:border-indigo-500 outline-none transition-all cursor-pointer text-xs uppercase">
                                                            <option value="">Select...</option>
                                                            @foreach ($options as $key => $opt)
                                                                @php
                                                                    $letter = (strpos($opt, '. ') !== false) ? explode('. ', $opt)[0] : $key;
                                                                @endphp
                                                                <option value="{{ $letter }}">Option {{ $letter }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                        @endif

                    @endif
                    
                    </div> @endforeach
                
            </form>
            
        </div>
    </section>

    <div id="generationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 backdrop-blur-md p-4 transition-all duration-500">
        <div class="w-full max-w-md transform overflow-hidden rounded-[2.5rem] bg-white/90 p-10 text-center shadow-[0_20px_50px_rgba(0,0,0,0.1)] border border-white/20 relative">
            
            <div class="absolute -top-24 -left-24 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl"></div>

            <div class="relative flex items-center justify-center mb-8">
                <div class="h-20 w-20 rounded-full border-[3px] border-slate-100"></div>
                <div class="absolute h-20 w-20 animate-spin rounded-full border-[3px] border-transparent border-t-indigo-600 border-r-indigo-600/30"></div>
                <div class="absolute h-12 w-12 animate-pulse rounded-full bg-indigo-50 flex items-center justify-center">
                    <div class="h-2 w-2 bg-indigo-600 rounded-full"></div>
                </div>
            </div>
            
            <h4 class="text-2xl font-black text-slate-800 mb-2 tracking-tight">Crafting Excellence</h4>
            <p id="status-text" class="text-xs font-medium uppercase tracking-[0.2em] text-indigo-600/60 mb-8">Initializing AI Neural Engine...</p>

            <div class="relative w-full bg-slate-100 rounded-full h-3 mb-10 p-0.5 shadow-inner">
                <div id="generation-progress" 
                     class="bg-gradient-to-r from-indigo-600 via-violet-500 to-indigo-600 h-2 rounded-full transition-all duration-700 ease-out relative" 
                     style="width: 0%">
                     <div class="absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite] rounded-full"></div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-blue-500 rounded-3xl blur opacity-10 group-hover:opacity-20 transition duration-1000"></div>
                <div class="relative p-6 bg-white rounded-2xl border border-slate-100 shadow-sm text-left">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-1.5 bg-indigo-600 rounded-lg">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Mastery Tip</span>
                    </div>
                    <p id="ielts-tip" class="text-[13px] leading-relaxed text-slate-600 font-medium italic transition-opacity duration-500 ease-in-out">
                        "Success in IELTS is about precision. Read the prompt twice, respond once."
                    </p>
                </div>
            </div>
        </div>
    </div>

</main>
<div id="script-settings-menu" class="hidden fixed bg-white border border-slate-200 rounded-2xl p-4 shadow-2xl z-[99999] w-56 max-h-[70vh] overflow-y-auto custom-scrollbar transition-opacity duration-200">
    <div class="mb-5">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Theme</p>
        <div class="flex gap-3">
            <button onclick="setTheme('light')" class="w-7 h-7 rounded-full bg-white border-2 border-slate-100 ring-offset-2 focus:ring-2 focus:ring-indigo-500 transition-all"></button>
            <button onclick="setTheme('dark')" class="w-7 h-7 rounded-full bg-slate-800 border-2 border-slate-900 ring-offset-2 focus:ring-2 focus:ring-slate-400 transition-all"></button>
            <button onclick="setTheme('sepia')" class="w-7 h-7 rounded-full bg-[#f4ebd7] border-2 border-[#d6c4a5] ring-offset-2 focus:ring-2 focus:ring-yellow-600 transition-all"></button>
        </div>
    </div>
    <div class="mb-5 pt-4 border-t border-slate-50">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Custom Highlight</p>
        <div class="flex gap-2 mb-3">
            <button onclick="setCustomColor('#ff3d00')" class="w-5 h-5 rounded-full bg-[#ff3d00] hover:scale-110 transition-transform"></button>
            <button onclick="setCustomColor('#00e676')" class="w-5 h-5 rounded-full bg-[#00e676] hover:scale-110 transition-transform"></button>
            <button onclick="setCustomColor('#00b0ff')" class="w-5 h-5 rounded-full bg-[#00b0ff] hover:scale-110 transition-transform"></button>
            <button onclick="setCustomColor('#f50057')" class="w-5 h-5 rounded-full bg-[#f50057] hover:scale-110 transition-transform"></button>
        </div>
        <div class="flex items-center gap-3">
            <input type="color" id="accent-color-picker" value="#6366f1" 
                   oninput="setCustomColor(this.value)" 
                   class="w-10 h-10 border-none bg-transparent cursor-pointer rounded-lg overflow-hidden">
            <span class="text-[10px] font-mono font-bold text-slate-400 uppercase" id="color-hex">#6366f1</span>
        </div>
    </div>
    <div class="mb-5 pt-4">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Reading Mode</p>
        <div id="reading-mode-group" class="flex flex-col gap-1.5">
            <button data-mode="karaoke" onclick="setCaptionStyle('karaoke')" class="mode-btn text-left text-xs p-2.5 rounded-xl hover:bg-slate-50 flex items-center gap-3 transition-all group">
                <i data-lucide="type" class="w-4 h-4 text-slate-400"></i> <span class="font-semibold">Karaoke (Word)</span>
            </button>
            <button data-mode="sentence" onclick="setCaptionStyle('sentence')" class="mode-btn text-left text-xs p-2.5 rounded-xl hover:bg-slate-50 flex items-center gap-3 transition-all group">
                <i data-lucide="align-left" class="w-4 h-4 text-slate-400"></i> <span class="font-semibold">Sentence Block</span>
            </button>
            <button data-mode="focus" onclick="setCaptionStyle('focus')" class="mode-btn text-left text-xs p-2.5 rounded-xl hover:bg-slate-50 flex items-center gap-3 transition-all group">
                <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i> <span class="font-semibold">Focus Mode</span>
            </button>
        </div>
    </div>
    <div class="mb-5 pt-4 border-t border-slate-50">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Visual Style</p>
        <div id="visual-style-group" class="grid grid-cols-2 gap-2">
            <button data-preset="style-glow" onclick="setVisualPreset('style-glow')" class="preset-btn text-[10px] font-bold py-2 border border-slate-100 rounded-lg hover:border-indigo-500 transition-all">Glow</button>
            <button data-preset="style-neon" onclick="setVisualPreset('style-neon')" class="preset-btn text-[10px] font-bold py-2 border border-slate-100 rounded-lg hover:border-indigo-500 transition-all">Neon</button>
            <button data-preset="style-modern" onclick="setVisualPreset('style-modern')" class="preset-btn text-[10px] font-bold py-2 border border-slate-100 rounded-lg hover:border-indigo-500 transition-all">Modern</button>
            <button data-preset="style-glass" onclick="setVisualPreset('style-glass')" class="preset-btn text-[10px] font-bold py-2 border border-slate-100 rounded-lg hover:border-indigo-500 transition-all">Glass</button>
        </div>
    </div>
    <div class="pt-4 border-t border-slate-50">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Font Size</p>
        <input type="range" min="12" max="24" value="14" oninput="setFontSize(this.value)" 
               class="w-full h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-indigo-600">
        <div class="flex justify-between mt-2 text-[10px] font-bold text-slate-300 uppercase">
            <span>Small</span>
            <span>Large</span>
        </div>
    </div>
</div>
<script>


// --- GLOBAL STATE ---
let isDragging = false;
let isResizing = false;
let offsetX, offsetY, startX, startY, startWidth, startHeight;
let audioSyncLoop = null;

const isInterrupted = @json($isInterrupted ?? false);
const testId = @json($test_id ?? ($test['id'] ?? ''));

let testTimerInterval;
let elapsedSeconds = 0;
let currentAudioId = null;
// --- 1. INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    initDraggable();
    initResizable();
    initPlayer();
    // Float script UI logic
    const el = document.getElementById('pro-script-review');
    if (el && el.classList.contains('is-floating')) {
        document.body.appendChild(el);
        el.style.bottom = '20px';
        el.style.right = '20px';
    }

    window.addEventListener('resize', autoCorrectBounds);

    const rawDbData = @json($test['word_data_json'] ?? null);
    const isPro = @json($isPro ?? false);
    
    if (isPro && rawDbData && rawDbData !== 'null') {
        try {
            const wordData = typeof rawDbData === 'string' ? JSON.parse(rawDbData) : rawDbData;
            if (Array.isArray(wordData) && wordData.length > 0) {
                activateFloatingMode(wordData);
            }
        } catch (e) { console.error("Failed to parse DB word data:", e); }
    }

    // Start timer immediately if they are reviewing or interrupted
    if (!isStrictAttempt) {
        startTimer();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('interrupted-modal');
    if (!modal) return;

    // Create a unique key for this specific test
    const storageKey = 'interruption_seen_' + "{{ $test['id'] }}";

    // Only show if the user hasn't acknowledged it in this browser yet
    if (!localStorage.getItem(storageKey)) {
        modal.style.display = 'flex';
    } else {
        modal.remove(); // Already seen, remove from DOM
    }
});

function acknowledgeInterruption() {
    const modal = document.getElementById('interrupted-modal');
    const storageKey = 'interruption_seen_' + "{{ $test['id'] }}";

    // Mark as seen in localStorage
    localStorage.setItem(storageKey, 'true');
    
    if (modal) modal.remove();
}


const isStrictAttempt = @json($isStrictAttempt ?? false);
console.log("Strict Attempt Mode:", isStrictAttempt);
const audioUrl = "{{ asset('static/ielts_aa10.mp3') }}";

let wavesurfer;

function initPlayer() {
    if (wavesurfer) return;

   wavesurfer = WaveSurfer.create({
        container: '#progress-track',
        height: 8,              // Small height makes it look like a bar
        waveColor: '#e2e8f0',   // Light gray for the background track
        progressColor: '#4f46e5', // Indigo for the progress
        cursorColor: '#4f46e5', // Color of the playhead
        cursorWidth: 2,
        barWidth: 0,            // Disable waveform bars
        dragToSeek: true,       // CRITICAL: Enables dragging on mobile/desktop
        responsive: true,
        url: audioUrl,
        interact: !isStrictAttempt // Blocks clicking/dragging if it's the first attempt
    });

    wavesurfer.on('ready', () => {
        document.getElementById('duration-time').innerText = formatTime(wavesurfer.getDuration());
    });

    wavesurfer.on('audioprocess', () => {
    const currentTime = wavesurfer.getCurrentTime();
    const duration = wavesurfer.getDuration();
    const progress = (currentTime / duration) * 100;
    
    // Move the visual thumb
    document.getElementById('progress-track').style.setProperty('--progress-x', progress + '%');
    
    // Existing UI updates
    document.getElementById('current-time').innerText = formatTime(currentTime);
});

    wavesurfer.on('play', () => updatePlayIcon('pause'));
    wavesurfer.on('pause', () => updatePlayIcon('play'));
    wavesurfer.on('finish', () => {
        updatePlayIcon('play');
        if (isStrictAttempt) handleAutoSubmission();
    });

    // NAVIGATION LOCK: Block seeking if test is live
    wavesurfer.on('interaction', () => {
        if (isStrictAttempt && document.body.classList.contains('test-started')) {
            return false; 
        }else{
            const progress = (wavesurfer.getCurrentTime() / wavesurfer.getDuration()) * 100;
             document.getElementById('progress-track').style.setProperty('--progress-x', progress + '%');
        }
    });

    // --- VOLUME LOGIC ---
    const volBtn = document.getElementById('master-volume-btn');
    const volSlider = document.getElementById('volume-slider');
    const volContainer = document.querySelector('.volume-container');

    // Toggle slider visibility
    volBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        volContainer.classList.toggle('active');
    });

    // Close volume slider when clicking outside
    document.addEventListener('click', () => {
        volContainer.classList.remove('active');
    });

    volContainer.addEventListener('click', (e) => e.stopPropagation());

    // Volume Adjustment (ALWAYS works, even in strict mode)
    volSlider.addEventListener('input', (e) => {
        const val = parseFloat(e.target.value);
        wavesurfer.setVolume(val);
    });
}
function updatePlayIcon(state) {
    const icon = document.getElementById('play-icon');
    if(icon) {
        icon.setAttribute('data-lucide', state);
        if (window.lucide) lucide.createIcons();
    }
}

function updateUI() {
    if (!window.ieltsPlayer || currentAudioId === null) return;
    
    // STRICTLY pass the currentAudioId to get accurate numbers
    const seek = window.ieltsPlayer.seek(currentAudioId) || 0;
    const duration = window.ieltsPlayer.duration(currentAudioId) || 1;
    const progress = (seek / duration) * 100;

    document.getElementById('audio-progress-bar').style.width = Math.min(progress, 100) + '%';
    document.getElementById('current-time').innerText = formatTime(seek);

    if (window.ieltsPlayer.playing(currentAudioId)) {
        requestAnimationFrame(updateUI);
    }
}

function formatTime(secs) {
    const minutes = Math.floor(secs / 60) || 0;
    const seconds = Math.floor(secs - minutes * 60) || 0;
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
}

async function startStrictTest() {
    const btn = document.getElementById('start-exam-btn');
    btn.disabled = true;

    try {
        const response = await fetch(`/api/test/${testId}/register-start`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });

        if (!response.ok) throw new Error("Database lock failed");

        // UI Changes
        document.getElementById('strict-warning-modal').style.display = 'none';
        document.body.classList.add('test-live', 'test-started');
        
        // Ensure progress track cannot be clicked
        document.getElementById('progress-track').style.pointerEvents = 'none';

        startTimer();

        // 1 MINUTE DELAY (60000 ms)
        console.log("Test started. Audio will begin in 60 seconds.");
        setTimeout(() => {
            if (wavesurfer) {
                wavesurfer.play();
            }
        }, 60000);

    } catch (e) {
        console.error(e);
        btn.disabled = false;
    }
}
// FIX: Global Play/Pause Button strictly tied to ID
document.getElementById('master-play-btn').addEventListener('click', function() {
    // If strict mode AND test has started, BLOCK the button
    if (isStrictAttempt && document.body.classList.contains('test-started')) {
        console.log("Controls are locked during the first attempt.");
        return; 
    }
    
    if (wavesurfer) wavesurfer.playPause();
});

// Update the Navigation Bar Logic
document.getElementById('progress-track').addEventListener('click', function(e) {
    if (isStrictAttempt && document.body.classList.contains('test-started')) {
        e.stopPropagation(); // Block clicks on the bar during exam
        return;
    }
});



function handleAutoSubmission() {
    const submitBtn = document.getElementById('submit-test-btn');
    
    // 1. Visually and functionally enable the button just before submission
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        submitBtn.innerText = "Submitting Answers...";
    }

    // 2. Show the alert
    Swal.fire({
        title: 'Time is Up!',
        text: 'The audio has finished playing. Your test is being automatically submitted.',
        icon: 'info',
        timer: 3500,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        // 3. Find the form and trigger submission
        const form = document.getElementById('testForm');
        if (form) {
            // Remove leave-page warnings so the form can submit without a browser popup
            window.onpopstate = null; 
            window.onbeforeunload = null;
            form.submit();
        }
    });
}
function startTimer() {
    const timerEl = document.getElementById('timer');
    if (!timerEl) return;
    
    if (testTimerInterval) clearInterval(testTimerInterval);

    testTimerInterval = setInterval(() => {
        elapsedSeconds++;
        const m = Math.floor(elapsedSeconds / 60).toString().padStart(2, '0');
        const s = (elapsedSeconds % 60).toString().padStart(2, '0');
        timerEl.innerText = `${m}:${s}`;
    }, 1000);
}
// Ensure accidental exit warning
window.onbeforeunload = function() {
    const isStarted = document.querySelector('.test-started');
    if (isStarted && !autoSubmitTriggered) {
        return "Are you sure? You cannot restart the strict exam once you leave.";
    }
};

// --- 2. THE MORPH LOGIC ---
const IS_PRO_USER = @json($isPro ?? false);

function openProModal() {
    const modal = document.getElementById('pro-modal');
    const content = document.getElementById('modal-content');
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeProModal() {
    const modal = document.getElementById('pro-modal');
    const content = document.getElementById('modal-content');
    
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
function initResizable() {
    const el = document.getElementById('pro-script-review');
    const handle = document.getElementById('resize-handle');
    if(!handle || !el) return;

    const startResize = (e) => {
        if (!el.classList.contains('is-floating')) return;
        isResizing = true;
        
        ensureAbsolutePosition(el);

        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

        startX = clientX;
        startY = clientY;
        startWidth = el.offsetWidth;
        startHeight = el.offsetHeight;
    };

    const doResize = (e) => {
        if (!isResizing) return;
        if(e.cancelable) e.preventDefault(); // Stop scrolling while resizing
        
        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

        let newWidth = startWidth + (clientX - startX);
        let newHeight = startHeight + (clientY - startY);

        newWidth = Math.max(260, Math.min(newWidth, window.innerWidth - el.offsetLeft - 10));
        newHeight = Math.max(200, Math.min(newHeight, window.innerHeight - el.offsetTop - 10));

        el.style.width = newWidth + 'px';
        el.style.height = newHeight + 'px';
    };

    const stopResize = () => { isResizing = false; };

    handle.addEventListener('mousedown', startResize);
    handle.addEventListener('touchstart', startResize, {passive: false});
    document.addEventListener('mousemove', doResize);
    document.addEventListener('touchmove', doResize, {passive: false});
    document.addEventListener('mouseup', stopResize);
    document.addEventListener('touchend', stopResize);
}
async function unlockProScript(testId) {
    const hasSubmitted = {{ $hasSubmittedBefore ? 'true' : 'false' }};
    
    if (!hasSubmitted) {
        Swal.fire({
            title: 'Test Required',
            text: 'Please submit your answers before accessing the transcript.',
            icon: 'info'
        });
        return;
    }
    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML; // Save original state (icon + text)

    btn.disabled = true;

// Wrap in a flex container to align items vertically
btn.innerHTML = `
    <span class="inline-flex items-center gap-2">
        <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i>
        <span>Analyzing...</span>
    </span>
`;

lucide.createIcons();
    try {
        const wordData = await startProAnalysis(testId); 
        
        // If wordData is null (user cancelled, was not Pro, or error handled by Alert)
        if (!wordData) {
            btn.disabled = false;
            btn.innerHTML = originalHTML; // Reset to "Unlock Transcript"
            lucide.createIcons();
            return;
        }

        activateFloatingMode(wordData);
        // Optional: hide the button entirely if analysis was successful
        btn.classList.add('hidden');

    } catch (error) {
        console.error(error);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        lucide.createIcons();
    }
}
            
// --- 3. TRANSCRIPT RENDERING & SYNC ---
function renderTranscript(wordData) {
    const container = document.getElementById('transcript-container');
    if (!container) return;
    container.innerHTML = ''; 
    wordData.forEach((item, index) => {
        const span = document.createElement('span');
        span.innerText = item.word + ' ';
        span.id = `word-${index}`;
        span.className = 'word-span cursor-pointer hover:text-indigo-500';
        container.appendChild(span);
    });
}

// --- 4. FLASK COMMUNICATION ---
async function startProAnalysis(testId) {
    try {
        const response = await fetch("/api/ai-review", { 
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ test_id: testId })
        });

        // 1. Check if the response is NOT okay (like 403 Forbidden)
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            
            // 2. Specifically check for the "Premium Feature" error
            if (response.status === 403 && errorData.is_pro === false) {
                Swal.fire({
    title: '<strong>PRO Feature</strong>',
    icon: 'info',
    html: `<b>${errorData.message}</b><br><br>Get unlimited transcripts and syncing!`,
    showCancelButton: true,
    confirmButtonText: 'Upgrade Now',
    cancelButtonText: 'Maybe Later',
    confirmButtonColor: '#4f46e5',
    // ADD THESE TWO LINES:
    backdrop: true,
    scrollbarPadding: false 
}).then((result) => {
                    return null;
                    if (result.isConfirmed) {
                        window.location.href = '/unlock_pro'; // Redirect to your pro page
                    }
                });
                return null; // Stop the execution
            }

            // Handle other general errors
            throw new Error(errorData.error || "Server Error");
        }

        const initData = await response.json();
        if (initData.task_id) {
            const result = await handleTaskProcessing(initData.task_id);
            return result.word_data;
        }
        
        return null;

    } catch (error) {
        console.error("Analysis Error:", error);
        Swal.fire('Error', error.message, 'error');
        return null;
    }
}
const autoCorrectBounds = () => {
    const el = document.getElementById('pro-script-review');
    if (el && el.classList.contains('is-floating')) {
        const rect = el.getBoundingClientRect();
        let updated = false;
        let newLeft = rect.left;
        let newTop = rect.top;

        if (rect.right > window.innerWidth) {
            newLeft = Math.max(0, window.innerWidth - rect.width - 10);
            updated = true;
        }
        if (rect.bottom > window.innerHeight) {
            newTop = Math.max(0, window.innerHeight - rect.height - 10);
            updated = true;
        }
        
        if (updated && el.style.left) {
            el.style.left = newLeft + 'px';
            el.style.top = newTop + 'px';
        }
    }
};

const ensureAbsolutePosition = (el) => {
    if (el.style.right || el.style.bottom) {
        const rect = el.getBoundingClientRect();
        el.style.left = rect.left + 'px';
        el.style.top = rect.top + 'px';
        el.style.right = '';
        el.style.bottom = '';
    }
};
// --- 5. DRAGGING & RESIZING ---
function initDraggable() {
    const el = document.getElementById('pro-script-review');
    const grip = document.getElementById('main-drag-grip');
    if(!grip || !el) return;

    const startDrag = (e) => {
        if (!el.classList.contains('is-floating')) return;
        isDragging = true;
        
        ensureAbsolutePosition(el);

        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
        const rect = el.getBoundingClientRect();
        
        offsetX = clientX - rect.left;
        offsetY = clientY - rect.top;
    };

    const doDrag = (e) => {
        if (!isDragging) return;
        if(e.cancelable) e.preventDefault(); // Stop scrolling while dragging

        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

        let x = clientX - offsetX;
        let y = clientY - offsetY;
        
        // Prevent going off screen
        x = Math.max(0, Math.min(x, window.innerWidth - el.offsetWidth));
        y = Math.max(0, Math.min(y, window.innerHeight - el.offsetHeight));
        
        el.style.left = x + 'px';
        el.style.top = y + 'px';
    };

    const stopDrag = () => { isDragging = false; };

    grip.addEventListener('mousedown', startDrag);
    grip.addEventListener('touchstart', startDrag, {passive: false});
    document.addEventListener('mousemove', doDrag);
    document.addEventListener('touchmove', doDrag, {passive: false});
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchend', stopDrag);
}

function toggleScriptSize() {
    const el = document.getElementById('pro-script-review');
    const icon = document.getElementById('expand-icon');
    const isExpanding = !el.classList.contains('is-expanded');
    
    el.classList.toggle('is-expanded');
    
    icon.setAttribute('data-lucide', isExpanding ? 'minimize-2' : 'maximize');
    lucide.createIcons();
}

// --- 6. UTILS (Submit, Tracker, Back Button) ---
function setupHistoryTrap() {
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = () => confirmExit();
}

function confirmExit() {
    Swal.fire({
        title: 'Exit the test?',
        text: "Progress will be lost.",
        icon: 'warning',
        heightAuto: false, 
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, exit',
        cancelButtonText: 'Keep testing',
        allowOutsideClick: false 
    }).then((result) => {
        if (result.isConfirmed) {
            window.onpopstate = null;
            window.onbeforeunload = null;
            window.location.href = '{{ route("dashboard") }}';
        } else {
            window.history.pushState(null, null, window.location.href);
        }
    });
}

function submitTest() {
    const form = document.getElementById('testForm');
    
    // 1. Safety Check
    if (!form) {
        console.error("Form with ID 'testForm' not found!");
        return;
    }

    // 2. Strict Mode Check (Prevent submission while audio is still playing)
    if (isStrictAttempt && typeof wavesurfer !== 'undefined' && wavesurfer.isPlaying()) {
        Swal.fire({
            title: 'Wait!',
            text: 'In Strict Mode, you must listen to the full audio before submitting.',
            icon: 'warning',
            heightAuto: false,
        });
        return;
    }

    const unansweredDots = document.querySelectorAll('.nav-dot[data-answered="false"]');
    
    if (unansweredDots.length > 0) {
        // Collect the missing numbers for the alert
        const missingNumbers = Array.from(unansweredDots).map(dot => dot.innerText).join(', ');
        
        // Show Toast
        const toast = document.getElementById('error-toast');
        toast.querySelector('span').innerText = `Missing questions: ${missingNumbers}`;
        toast.classList.remove('-translate-y-10', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
        
        setTimeout(() => {
            toast.classList.remove('translate-y-0', 'opacity-100');
            toast.classList.add('-translate-y-10', 'opacity-0');
        }, 4000);

        // Scroll to the first missing one
        const firstMissing = unansweredDots[0].innerText;
        const targetElement = document.getElementById(`q-${firstMissing}`);
        if(targetElement) targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        return;
    }
    // 3. The Confirmation Dialog
    Swal.fire({
        title: 'Submit Test?',
        text: "Make sure you have answered all questions! You cannot change them after this.",
        icon: 'warning',
        heightAuto: false,
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Submit',
        cancelButtonText: 'Review Answers'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show a loading state so the user doesn't click twice
            Swal.fire({
                title: 'Submitting...',
                text: 'Please wait while we grade your test.',
                allowOutsideClick: false,
                showConfirmButton: false,
                heightAuto: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Remove navigation locks so the browser doesn't block the redirect
            window.onpopstate = null; 
            window.onbeforeunload = null;

            // Trigger the actual submission
            form.submit();
        }
    });
}
document.getElementById('testForm').addEventListener('input', function(e) {
    const input = e.target;
    
    // 1. MULTI-SELECT LOGIC (Requires EXACT amount)
    if (input.classList.contains('multi-check') || input.hasAttribute('data-max')) {
        const maxRequired = parseInt(input.getAttribute('data-max') || 1, 10);
        const groupName = input.getAttribute('name');
        const checkedCount = document.querySelectorAll(`input[name="${CSS.escape(groupName)}"]:checked`).length;
        
        // Only mark as filled if they meet the exact requirement
        const isFilled = (checkedCount === maxRequired);
        
        // Multi-select often spans multiple questions (e.g., Q11 and Q12). 
        // We parse the data-qids attribute to update ALL related dots at once.
        const qidsStr = input.getAttribute('data-qids');
        if (qidsStr) {
            const qids = JSON.parse(qidsStr);
            qids.forEach(qNum => {
                const navDot = document.getElementById(`nav-${qNum}`);
                if (navDot) {
                    if (isFilled) {
                        navDot.setAttribute('data-answered', 'true');
                        navDot.classList.remove('bg-slate-50', 'text-slate-400', 'border-slate-200');
                        navDot.classList.add('bg-emerald-500', 'text-white', 'border-emerald-600');
                    } else {
                        navDot.setAttribute('data-answered', 'false');
                        navDot.classList.add('bg-slate-50', 'text-slate-400', 'border-slate-200');
                        navDot.classList.remove('bg-emerald-500', 'text-white', 'border-emerald-600');
                    }
                }
            });
        }
        return; // Exit early so standard logic doesn't override this
    }

    // 2. STANDARD QUESTION LOGIC (Text, Radio, Standard Checkbox)
    const nameMatch = input.name.match(/(?:ans|answer)(?:_group)?\[(\d+)\]/);
    
    if (nameMatch) {
        const qNum = nameMatch[1];
        const navDot = document.getElementById(`nav-${qNum}`);
        
        if (navDot) {
            let isFilled = false;
            
            if (input.type === 'checkbox' || input.type === 'radio') {
                const group = document.getElementsByName(input.name);
                isFilled = Array.from(group).some(i => i.checked);
            } else {
                isFilled = input.value.trim() !== "";
            }

            if (isFilled) {
                navDot.setAttribute('data-answered', 'true');
                navDot.classList.remove('bg-slate-50', 'text-slate-400', 'border-slate-200');
                navDot.classList.add('bg-emerald-500', 'text-white', 'border-emerald-600');
            } else {
                navDot.setAttribute('data-answered', 'false');
                navDot.classList.add('bg-slate-50', 'text-slate-400', 'border-slate-200');
                navDot.classList.remove('bg-emerald-500', 'text-white', 'border-emerald-600');
            }
        }
    }
});
function updateTracker(questionNum, value) {
    const dot = document.getElementById('nav-' + questionNum);
    if (!dot) return;

    if (value && value.trim() !== "") {
        dot.setAttribute('data-answered', 'true'); // <- ADD THIS
        dot.classList.remove('bg-slate-50', 'text-slate-400', 'border-slate-200');
        dot.classList.add('bg-green-500', 'text-white', 'border-green-600');
    } else {
        dot.setAttribute('data-answered', 'false'); // <- ADD THIS
        dot.classList.add('bg-slate-50', 'text-slate-400', 'border-slate-200');
        dot.classList.remove('bg-green-500', 'text-white', 'border-green-600');
    }
}

function scrollToQuestion(qNum) {
    const element = document.getElementById(`q-${qNum}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Optional: Flash the question to show which one was clicked
        element.classList.add('ring-2', 'ring-indigo-400');
        setTimeout(() => element.classList.remove('ring-2', 'ring-indigo-400'), 2000);
    }
}

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const qId = entry.target.id.replace('q-', '');
            // Reset all dots to original state (unless answered)
            document.querySelectorAll('.nav-dot').forEach(dot => {
                dot.classList.remove('ring-2', 'ring-indigo-600', 'ring-offset-2');
            });
            // Highlight current dot
            const activeDot = document.getElementById(`nav-${qId}`);
            if (activeDot) {
                activeDot.classList.add('ring-2', 'ring-indigo-600', 'ring-offset-2');
            }
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('[id^="q-"]').forEach(q => observer.observe(q));

function toggleSettings() {
    const menu = document.getElementById('script-settings-menu');
    const proContainer = document.getElementById('pro-script-review');
    
    if (!menu.classList.contains('hidden')) {
        menu.classList.add('hidden');
        return;
    }

    const rect = proContainer.getBoundingClientRect();
    let leftPos = rect.left - 236; 
    let topPos = rect.top;

    // Bounds check for the settings popup
    if (leftPos < 10) {
        leftPos = Math.min(rect.right + 12, window.innerWidth - 246);
        if(leftPos < 10) leftPos = 10;
    }

    menu.style.top = `${topPos}px`;
    menu.style.left = `${leftPos}px`;
    menu.style.position = 'fixed';
    menu.classList.remove('hidden');
}

function setTheme(theme) {
    const proBox = document.getElementById('pro-script-review');
    const menu = document.getElementById('script-settings-menu');
    
    const themeClasses = ['theme-dark', 'theme-sepia'];
    [proBox, menu].forEach(el => el.classList.remove(...themeClasses));
    
    if (theme !== 'light') {
        proBox.classList.add(`theme-${theme}`);
        menu.classList.add(`theme-${theme}`);
    }
}

function setFontSize(size) {
    const container = document.getElementById('transcript-container');
    container.style.setProperty('--script-font-size', size + 'px');
    container.style.lineHeight = (size / 14 * 2.2); 
}

document.addEventListener('mousedown', (e) => {
    const menu = document.getElementById('script-settings-menu');
    if (!menu.contains(e.target) && !e.target.closest('button[onclick="toggleSettings()"]')) {
        menu.classList.add('hidden');
    }
});

let currentCaptionStyle = 'karaoke'; 
let currentWordData = []; 

function setCaptionStyle(style) {
    currentCaptionStyle = style; 
    
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-mode') === style);
    });

    const container = document.getElementById('transcript-container');
    container.classList.remove('focus-active');
    document.querySelectorAll('.sentence-highlight, .word-active').forEach(el => {
        el.classList.remove('sentence-highlight', 'word-active');
    });
}

function activateFloatingMode(wordData) {
    const el = document.getElementById('pro-script-review');
    const container = document.getElementById('transcript-container');
    const overlay = document.getElementById('script-unlock-overlay');
    
    currentWordData = wordData;

    if (el) {
        // TELEPORT to body
        document.body.appendChild(el);
        
        el.classList.remove('is-static');
        el.classList.add('is-floating');
        
        // Reset and explicitly apply safe corners
        el.style.width = '';
        el.style.height = '';
        el.style.left = '';
        el.style.top = '';
        el.style.bottom = '20px';
        el.style.right = '20px';
    }

    if (overlay) overlay.classList.add('hidden');
    if (container) {
        container.classList.remove('opacity-10');
        container.classList.add('opacity-100');
    }

    ['main-drag-grip', 'settings-btn', 'master-toggle-wrapper', 'resize-handle'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.classList.remove('hidden');
    });

    renderTranscript(wordData);
    
    const masterToggle = document.getElementById('master-script-toggle');
    if (masterToggle) {
        masterToggle.checked = true;
        toggleScriptEngine(true);
    }

    // Auto-Close Mobile menu so user can see it!
    const sidePanel = document.getElementById('side-panel');
    if (sidePanel && !sidePanel.classList.contains('-translate-x-full') && window.innerWidth < 1024) {
        toggleMobileMenu();
    }
}
function initAudioSync() {
    let lastActiveIndex = -1;
    const container = document.getElementById('transcript-container');

    function syncLoop() {
        // Ensure Howler is active, we have an ID, and it's actually playing
        if (!isScriptEngineActive || !window.ieltsPlayer || currentAudioId === null) {
            audioSyncLoop = requestAnimationFrame(syncLoop);
            return;
        }

        if (window.ieltsPlayer.playing(currentAudioId) && currentWordData.length > 0) {
            // Get the current time directly from the Howler instance
            const currentTime = window.ieltsPlayer.seek(currentAudioId);
            
            let activeIndex = currentWordData.findIndex(item => 
                currentTime >= item.start && currentTime <= item.end
            );

            if (activeIndex === -1) {
                for (let i = currentWordData.length - 1; i >= 0; i--) {
                    if (currentTime >= currentWordData[i].start) {
                        activeIndex = i;
                        break;
                    }
                }
            }

            if (activeIndex !== -1 && activeIndex !== lastActiveIndex) {
                document.querySelectorAll('.word-active, .sentence-highlight').forEach(el => {
                    el.classList.remove('word-active', 'sentence-highlight');
                });

                const activeWord = document.getElementById(`word-${activeIndex}`);
                if (activeWord) {
                    if (currentCaptionStyle === 'karaoke') {
                        activeWord.classList.add('word-active');
                    } else if (currentCaptionStyle === 'sentence') {
                        applySentenceHighlight(activeIndex);
                    }
                    
                    const wordTop = activeWord.offsetTop;
                    container.scrollTo({
                        top: wordTop - (container.offsetHeight / 2),
                        behavior: 'smooth'
                    });
                }
                lastActiveIndex = activeIndex;
            }
        }
        audioSyncLoop = requestAnimationFrame(syncLoop);
    }
    
    if (audioSyncLoop) cancelAnimationFrame(audioSyncLoop);
    syncLoop();
}
function applySentenceHighlight(index) {
    let start = index;
    let end = index;

    while (start > 0) {
        const prevWord = currentWordData[start - 1].word;
        if (/[.!?]/.test(prevWord)) break;
        start--;
    }

    while (end < currentWordData.length - 1) {
        const thisWord = currentWordData[end].word;
        if (/[.!?]/.test(thisWord)) break;
        end++;
    }

    for (let i = start; i <= end; i++) {
        const el = document.getElementById(`word-${i}`);
        if (el) el.classList.add('sentence-highlight');
    }
}

function setVisualPreset(preset) {
    const container = document.getElementById('transcript-container');
    const allPresets = ['style-glow', 'style-neon', 'style-modern', 'style-glass'];
    
    container.classList.remove(...allPresets);
    container.classList.add(preset);

    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-preset') === preset);
    });
}

function setCustomColor(color) {
    const container = document.getElementById('pro-script-review');
    const hexLabel = document.getElementById('color-hex');
    
    container.style.setProperty('--script-accent', color);
    container.style.setProperty('--script-accent-faded', color + '26'); 
    
    if (hexLabel) hexLabel.innerText = color.toUpperCase();
    document.getElementById('accent-color-picker').value = color;
}

let isScriptEngineActive = false; 

function toggleScriptEngine(isActive) {
    isScriptEngineActive = isActive;
    const statusText = document.getElementById('script-status-text');
    const container = document.getElementById('transcript-container');

    if (isActive) {
        statusText.innerText = "On";
        statusText.classList.remove('text-slate-400');
        statusText.classList.add('text-indigo-600');
        
        container.style.filter = "none";
        container.style.opacity = "1";
        container.style.pointerEvents = "auto"; 
        container.style.userSelect = "auto";    
        
        if (currentWordData && currentWordData.length > 0) {
            initAudioSync(); 
        }
    } else {
        statusText.innerText = "Off";
        statusText.classList.remove('text-indigo-600');
        statusText.classList.add('text-slate-400');
        
        container.style.filter = "grayscale(1) blur(4px)"; 
        container.style.opacity = "0.4";
        container.style.pointerEvents = "none"; 
        container.style.userSelect = "none";    
        
        document.querySelectorAll('.word-active, .sentence-highlight').forEach(el => {
            el.classList.remove('word-active', 'sentence-highlight');
        });
        
        if (audioSyncLoop) cancelAnimationFrame(audioSyncLoop);
    }
}

const GenerationModal = {
    el: null,
    status: null, 
    progress: null, 
    tip: null,
    progressContainer: null, // New: to hide/show the bar
    
    tips: [
        "Read the instructions carefully: 'NO MORE THAN TWO WORDS' means exactly that.",
        "The Speaking test is a conversation, not an exam. Speak naturally.",
        "In Listening, the answers usually follow the order of the audio."
    ],
    interval: null,

    show() {
        this.el = document.getElementById('generationModal');
        this.status = document.getElementById('status-text');
        this.progress = document.getElementById('generation-progress');
        this.progressContainer = document.getElementById('progress-container'); // Ensure you have this ID in HTML
        this.tip = document.getElementById('ielts-tip');

        this.el.classList.replace('hidden', 'flex');
        document.body.style.overflow = 'hidden';
        
        if (this.interval) clearInterval(this.interval);
        let i = 0;
        this.interval = setInterval(() => {
            if (this.tip) {
                this.tip.style.opacity = 0;
                setTimeout(() => {
                    i = (i + 1) % this.tips.length;
                    this.tip.innerText = this.tips[i];
                    this.tip.style.opacity = 1;
                }, 500); 
            }
        }, 7000);
    },

    // NEW: Specifically for the Queue phase
    updateQueue(message, isTopThree) {
        if (this.status) this.status.innerText = message;
        
        if (isTopThree) {
            // Pulse animation for Top 3 to show "it's about to happen"
            this.status.classList.add('animate-pulse', 'text-blue-500');
            if (this.progressContainer) this.progressContainer.classList.remove('hidden');
        } else {
            this.status.classList.remove('animate-pulse', 'text-blue-500');
            // Hide progress bar while deep in queue to avoid "0%" frustration
            if (this.progressContainer) this.progressContainer.classList.add('hidden');
        }
    },

    update(text, percent) {
        if (this.status) {
            this.status.classList.remove('animate-pulse', 'text-blue-500');
            this.status.innerText = text;
        }
        if (this.progressContainer) this.progressContainer.classList.remove('hidden');
        if (percent !== undefined && this.progress) {
            this.progress.style.width = percent + '%';
        }
    },

    hide() {
        if (this.el) this.el.classList.replace('flex', 'hidden');
        document.body.style.overflow = 'auto';
        clearInterval(this.interval);
    }
};
document.addEventListener('DOMContentLoaded', async () => {
    // Replaced mysqli task logic with Laravel DB logic cleanly injected via blade
    @php
        $pendingTask = \Illuminate\Support\Facades\DB::table('user_tasks')
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->first();
    @endphp

    @if ($pendingTask)
        const activeTaskId = @json($pendingTask->task_id);
        handleTaskProcessing(activeTaskId, 2); 
    @endif
});

const pollTask = async (taskId) => {
    let checkCount = 0;
    let currentInterval = 3000;

    return new Promise((resolve, reject) => {
        const checkStatus = async () => {
            try {
                const statusRes = await fetch(`/api/task-status/${taskId}?t=${Date.now()}`);
                const statusData = await statusRes.json();

                // 1. Handle Final States
                if (statusData.state === 'SUCCESS') {
                    GenerationModal.update("Complete!", 100);
                    resolve(statusData.result);
                    return;
                } 
                if (statusData.state === 'FAILURE') {
                    reject(new Error("System Error: " + (statusData.error || "Unknown")));
                    return;
                }

                // 2. Handle the "Queue" phase (The new logic)
                if (statusData.queue_position !== undefined) {
                    GenerationModal.updateQueue(
                        statusData.queue_message, 
                        statusData.in_top_three
                    );
                }

                // 3. Handle the "Active Processing" phase (if your Flask sends meta)
                if (statusData.state === 'PROGRESS' && statusData.meta) {
                    GenerationModal.update(
                        statusData.meta.status || "Processing...", 
                        statusData.meta.progress || 0
                    );
                }

                // Smart Polling
                checkCount++;
                if (checkCount > 10) currentInterval = 5000;
                setTimeout(checkStatus, currentInterval);

            } catch (err) {
                setTimeout(checkStatus, 5000); 
            }
        };
        checkStatus();
    });
};
async function handleTaskProcessing(taskId, requiredCost = 2, initialData = null, loadingText = "Processing...") {
    
    if (typeof GenerationModal === 'undefined') return;

    GenerationModal.show(); 

    // Use initial data from the FIRST Laravel response (the one that gave us the taskId)
    if (initialData) {
        // Update Queue UI
        if (initialData.initial_queue_position !== undefined) {
            GenerationModal.updateQueue(
                initialData.initial_queue_position <= 3 
                    ? "You're next! Starting AI..." 
                    : `Queue Position: #${initialData.initial_queue_position}`,
                initialData.initial_queue_position <= 3
            );
        }

        // Update Credit Display Immediately using Laravel's calculation
        if (initialData.new_balance !== undefined) {
            if (typeof updateCreditDisplaylistening === "function") {
                updateCreditDisplaylistening(initialData.new_balance);
            } else {
                const creditLabel = document.getElementById('creditCountSpeaking');
                if (creditLabel) creditLabel.innerText = initialData.new_balance;
            }
        }
    } else {
        GenerationModal.update(loadingText, 5);
    }

    try {
        // WAIT for the polling loop to finish
        const data = await pollTask(taskId);
        
        // Success! Hide the loader
        GenerationModal.hide();

        // Update Credits
       

        // Refresh UI components
        if (typeof refreshSavedTests === "function") refreshSavedTests();
        if (typeof refreshBadge === "function") refreshBadge();

        return data;

    } catch (e) {
        GenerationModal.hide();

        // --- NEW: Refresh credits from server on failure ---
        // We fetch from the server to get the REAL balance after the refund
        try {
            const balanceRes = await fetch('/api/user/balance'); // Ensure you have this route
            const balanceData = await balanceRes.json();
            if (balanceData.balance !== undefined) {
                updateCreditDisplaylistening(balanceData.balance);
            }
        } catch (refreshErr) {
            console.error("Failed to sync refund balance:", refreshErr);
        }
        let alertTitle = 'Generation Failed';
        let alertIcon = 'error';
        let alertMessage = e.message;

        if (e.message.includes("Validation Error: ")) {
            alertTitle = 'File Requirement';
            alertIcon = 'warning';
            alertMessage = e.message.replace("Validation Error: ", "");
        } else if (e.message.includes("System Error: ")) {
            alertTitle = 'System Glitch';
            alertIcon = 'error';
            alertMessage = e.message.replace("System Error: ", "");
        }

        Swal.fire({ 
            title: alertTitle, 
            text: alertMessage, 
            icon: alertIcon 
        });
        
        throw e; 
    }
}
async function acknowledgeWarning() {
    const modal = document.getElementById('interrupted-modal');
    
    // 1. Immediately hide the modal so the user isn't waiting
    if (modal) modal.remove();

    // 2. Send request to the backend to flip 'is_warning_shown' to 1
    try {
        await fetch(`/api/test/{{ $test['id'] }}/acknowledge-warning`, {
            method: 'POST',
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        console.log("Warning acknowledged in database.");
    } catch (e) {
        console.error("Failed to acknowledge warning:", e);
    }
}
function toggleMobileMenu() {
    const sidePanel = document.getElementById('side-panel');
    const backdrop = document.getElementById('side-backdrop');
    const isOpening = sidePanel.classList.contains('-translate-x-full');

    if (isOpening) {
        sidePanel.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        setTimeout(() => backdrop.classList.add('opacity-100'), 10);
        document.body.style.overflow = 'hidden'; 
    } else {
        sidePanel.classList.add('-translate-x-full');
        backdrop.classList.remove('opacity-100');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
        document.body.style.overflow = ''; 
    }
}

function handleMultiSelectGroup(checkbox, maxAllowed) {
    const groupName = checkbox.getAttribute('name');
    const checkedBoxes = document.querySelectorAll(`input[name="${CSS.escape(groupName)}"]:checked`);
    
    if (checkedBoxes.length > maxAllowed) {
        checkbox.checked = false; // Undo the user's click
        
        // Mobile vibration feedback
        if (navigator.vibrate) navigator.vibrate(50); 
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: `You must select exactly ${maxAllowed} options.`,
            showConfirmButton: false,
            timer: 2500
        });
    }
}
</script>



<div id="pro-modal" class="hidden fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0" id="modal-content">
        <div class="relative p-8 text-center">
            <button onclick="closeProModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 rotate-3">
                <i data-lucide="crown" class="w-8 h-8"></i>
            </div>

            <h3 class="text-xl font-black text-slate-800 mb-2">Unlock LISTENING Lab Pro</h3>
            <p class="text-slate-500 text-sm mb-8">
                Get unlimited AI transcripts, 5+ visual styles, and other pro features.
            </p>

            <div class="space-y-3 mb-8 text-left inline-block mx-auto">
                <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-green-500"></i>
                    AI-Powered Word Syncing
                </div>
                <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-green-500"></i>
                    Custom Reading Themes
                </div>
                <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-green-500"></i>
                    Advanced Performance Analytics
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <a href="{{ url('unlock_pro') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200 transition-all">
                    Upgrade Now
                </a>
                <button onclick="closeProModal()" class="text-slate-400 text-xs font-bold hover:text-slate-600 transition-colors">
                    Maybe Later
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>