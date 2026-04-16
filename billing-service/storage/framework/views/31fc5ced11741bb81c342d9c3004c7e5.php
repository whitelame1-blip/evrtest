
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <title>Pro App Dashboard</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icons/6.6.6/css/flag-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<script src="https://unpkg.com/wavesurfer.js@7"></script>
<script src="https://unpkg.com/wavesurfer.js@7/dist/plugins/regions.min.js"></script>
  <link rel="stylesheet" href="<?php echo e(asset('css/dashboard.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/voice.css')); ?>">

  
  <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar-transition { transition: transform 0.3s ease-in-out; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.5s ease-out forwards;
    }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* Modal Styles */
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }
@keyframes costPop {
  0% { transform: scale(1); }
  50% { transform: scale(1.4); color: #fde047; }
  100% { transform: scale(1); }
}

.animate-cost-change {
  animation: costPop 0.4s ease-out;
}
  </style>
</head>

<body class="bg-gray-50 text-gray-800">

  <div class="flex h-screen overflow-hidden relative">

    <div class="fixed top-0 inset-x-0 bg-slate-900/80 backdrop-blur-md text-white z-40 flex justify-between items-center p-4 border-b border-slate-800 md:hidden shadow-sm">
  <div class="flex items-center gap-2">
    <div class="h-8 w-8 bg-gradient-to-tr from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center text-white font-bold shadow-lg">T</div>
    <span class="font-bold text-lg tracking-wide">TestGen</span>
  </div>
  <button id="mobile-menu-btn" class="p-2 text-slate-300 hover:text-white bg-slate-800/50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  </button>
</div>
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden md:hidden transition-opacity duration-300 opacity-0"></div>
<aside id="sidebar" class="sidebar-transition transition-transform fixed inset-y-0 left-0 z-50 w-72 bg-[#0B1120] transition-transform duration-300 ease-in-out    text-slate-300 transform -translate-x-full md:translate-x-0 md:relative flex flex-col border-r border-slate-800/60 shadow-2xl ">
  
  <div class="h-20 flex items-center px-6 border-b border-slate-800/60">
    <div class="h-9 w-9 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center text-white font-black text-lg mr-3 shadow-lg shadow-indigo-500/20">
      T
    </div>
    <div class="relative">
    <h1 class="text-2xl font-black tracking-tighter italic transition-all duration-500 hover:drop-shadow-[0_0_8px_rgba(249,115,22,0.6)]">
        <span class="text-white">EVR</span>
        <span class="bg-clip-text text-transparent bg-gradient-to-r from-orange-400 to-orange-600">
            TEST
        </span>
        <span class="absolute bottom-0 left-0 w-0 h-[2px] bg-orange-500 transition-all duration-500 hover:w-full"></span>
    </h1>
</div>
  </div>

  <div class="px-4 py-5 hidden">
    <div class="relative group">
      <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 group-focus-within:text-indigo-400 transition-colors"></i>
      <input type="text" id="menu-search" placeholder="Search menu..." class="w-full bg-slate-900/50 text-sm text-white rounded-xl pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:bg-slate-800 placeholder-slate-500 border border-slate-800 transition-all shadow-inner">
    </div>
  </div>

  <nav class="flex-1 px-4 space-y-1 overflow-y-auto py-6 custom-scrollbar" id="nav-container">

    <a href="#" data-page="dashboard-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="mic-vocal" class="w-5 h-5 group-hover:text-blue-400 transition-transform duration-300 group-hover:rotate-12"></i>
      </div>
      <span class="flex-1">AI Listening</span>
    </a>

    <a href="#" data-page="generate-test-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="book-open" class="w-5 h-5 group-hover:text-pink-400 transition-transform duration-300 group-hover:scale-110"></i>
      </div>
      <span class="flex-1">AI Reading Test</span>
    </a>

    <a href="#" data-page="settings-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="mic" class="w-5 h-5 group-hover:text-green-400 transition-transform duration-300 group-hover:translate-x-1"></i>
      </div>
      <span class="flex-1">Pronunciation Check</span>
    </a>

    <a href="#" data-page="saved-tests-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="save" class="w-5 h-5 group-hover:text-purple-400 transition-transform duration-300 group-hover:rotate-12"></i>
      </div>
      <span class="flex-1">Saved Tests</span>
      <div id="testBadgeContainer">
        <?php if($new_tests > 0): ?>
          <span id="testBadge" class="bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[10px] font-black px-2 py-0.5 rounded-md shadow-sm">
            <?php echo e($new_tests); ?> New
          </span>
        <?php endif; ?>
      </div>
    </a>

    <a href="#" data-page="games-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="gamepad-2" class="w-5 h-5 group-hover:text-orange-400 transition-transform duration-300 group-hover:scale-110 group-hover:-translate-y-0.5"></i>
      </div>
      <span class="flex-1">Arena Mode</span>
    </a>


    <a href="#" data-page="completed-page" onclick="clearBadge()" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="check-circle" class="w-5 h-5 group-hover:text-teal-400 transition-transform duration-300 group-hover:rotate-[360deg]"></i>
      </div>
      <span class="flex-1">Completed</span>
      <div id="completedBadgeContainer">
        <?php if($new_tests > 0): ?>
          <span id="completedBadge" class="relative flex h-2 w-2 mr-1">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
          </span>
        <?php endif; ?>
      </div>
    </a>

    <a href="#" data-page="profile-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-slate-800/50 hover:text-white">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="user" class="w-5 h-5 group-hover:text-red-400 transition-transform duration-300 group-hover:-translate-y-0.5"></i>
      </div>
      <span class="flex-1">Profile</span>
    </a>

    <a href="#" data-page="buycredits-page" class="nav-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-slate-400 transition-all duration-300 hover:bg-amber-500/10 hover:text-amber-300 border border-transparent hover:border-amber-500/20">
      <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
        <i data-lucide="gem" class="w-5 h-5 group-hover:text-amber-400 transition-transform duration-300 group-hover:scale-110"></i>
      </div>
      <span class="flex-1">Buy Credits</span>
    </a>

    <?php if(strtolower($userPlan) === 'free'): ?>
    <div class="mt-6 mb-4 px-2 flex-1 flex flex-col justify-center md:block md:mt-10 md:flex-none">
        
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-b from-indigo-500/10 to-purple-500/5 border border-indigo-500/20 p-6 group hover:border-indigo-500/40 transition-all duration-500 shadow-xl shadow-black/20">
            
            <div class="absolute top-0 right-0 -mt-6 -mr-6 h-32 w-32 bg-indigo-500/15 blur-3xl rounded-full"></div>
            <div class="absolute bottom-0 left-0 -mb-6 -ml-6 h-24 w-24 bg-orange-500/10 blur-3xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-indigo-500/20 rounded-xl border border-indigo-500/30">
                        <i data-lucide="sparkles" class="h-5 w-5 text-indigo-400"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-black text-white uppercase tracking-widest">Pro Plan</span>
                        <span class="block text-[10px] text-indigo-400 font-medium">Limited Access</span>
                    </div>
                </div>
                
                <p class="text-[13px] text-slate-400 mb-5 leading-snug">
                    Level up your performance with unlimited AI tests.
                </p>
                
                <a href="unlock_pro.php" data-page="buycredits-page" class="relative group/btn flex items-center justify-center w-full py-3 rounded-2xl text-xs font-black text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-all active:scale-95 overflow-hidden">
                    <div class="absolute inset-0 translate-x-[-100%] group-hover/btn:translate-x-[100%] transition-transform duration-700 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                    <span class="relative">Upgrade to Pro</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
  </nav>

  <div class="border-t border-slate-800/60 p-4 bg-slate-900/20">
    <div class="flex items-center gap-3 p-2 rounded-xl transition-colors hover:bg-slate-800/50">
      <div class="h-10 w-10 bg-slate-800 rounded-full flex items-center justify-center text-indigo-400 font-bold border border-slate-700 shadow-sm flex-shrink-0">
        <?php echo e($userInitials); ?>

      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-slate-200 truncate"><?php echo e(htmlspecialchars($user['fullname'])); ?></p>
        <p class="text-[11px] text-slate-500 truncate"><?php echo e(htmlspecialchars($user['email'])); ?></p>
      </div>
      <button onclick="confirmLogout()" class="p-2 text-slate-500 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-all focus:outline-none" title="Logout">
         <i data-lucide="log-out" class="h-4 w-4"></i>
      </button>
    </div>
  </div>
</aside>

<style>
  .custom-scrollbar::-webkit-scrollbar { width: 4px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
  .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #475569; }
</style>

    <div id="mobile-overlay" class="fixed inset-0 bg-black opacity-50 z-30 hidden md:hidden"></div>

    <main class="flex-1 overflow-y-auto p-3 pt-20 md:p-8 md:pt-8 bg-gray-50 h-full relative w-full">

      <div id="dashboard-page" class="page-content">
          <?php echo $__env->make('ai_listening_generator', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

      <div id="generate-test-page" class="page-content hidden">       
        <div class="w-full max-w-7xl mx-auto flex flex-col lg:flex-row gap-6 lg:gap-8 items-start justify-center">
            
            <form id="generatorForm" class="space-y-6 sm:space-y-7 animate-fade-in w-full max-w-3xl mx-auto bg-white p-5 sm:p-8 rounded-[2rem] sm:rounded-[2.5rem] border border-slate-100 shadow-xl">
                
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4 sm:mb-8">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 p-2.5 sm:p-3 rounded-2xl shadow-lg shadow-indigo-100 shrink-0">
                            <i data-lucide="book-open" class="w-5 h-5 sm:w-6 sm:h-6 text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Generate Single Passage</h2>
                            <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest">Custom Reading Practice</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-0.5 w-full sm:w-auto justify-end">
                        <div class="flex items-center justify-center gap-2 bg-amber-50 text-amber-700 px-3 sm:px-4 py-2 rounded-l-2xl border border-amber-100 border-r-0 shadow-sm w-full sm:w-auto" title="Available Credits">
                            <i data-lucide="zap" class="w-4 h-4 fill-amber-400 text-amber-400 shrink-0"></i>
                            <span class="text-[10px] sm:text-xs font-black uppercase tracking-tighter whitespace-nowrap">
                                <span id="creditCountreading"><?php echo e($userCredits); ?></span> Credits
                            </span>
                        </div>
                        <button onclick="showPage('buycredits-page')" type="button" class="flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white w-9 h-9 sm:w-9 sm:h-9 rounded-r-2xl border border-amber-500 shadow-sm transition-all hover:scale-105 active:scale-95 group shrink-0" title="Buy Credits">
                            <i data-lucide="plus" class="w-4 h-4 sm:w-5 sm:h-5 transition-transform group-hover:rotate-90"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div id="topicWrapper" class="relative  space-y-2">
                        <div class="flex justify-between items-center px-1">
                            <label for="topic" class="text-[10px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider">Passage Topic</label>
                            <label class="flex items-center gap-2 cursor-pointer group/toggle">
                                <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 group-hover/toggle:text-indigo-600 transition-colors uppercase">AI Random</span>
                                <div class="relative inline-block w-8 h-4 sm:w-9 sm:h-5">
                                    <input type="checkbox" id="aiSuggestToggle" class="hidden peer">
                                    <div class="absolute inset-0 bg-slate-200 rounded-full peer-checked:bg-indigo-500 transition-all duration-300"></div>
                                    <div class="absolute left-1 top-1 bg-white w-2 h-2 sm:w-3 sm:h-3 rounded-full peer-checked:translate-x-4 transition-all duration-300 shadow-sm"></div>
                                </div>
                            </label>
                        </div>

                        <div class="relative flex items-center">
    <i data-lucide="search" class="pointer-events-none absolute left-4 w-4 h-4 text-slate-400"></i>
                            <input 
        id="topic" 
        name="topic" 
        class="w-full pl-10 sm:pl-11 pr-4 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none disabled:opacity-50 text-sm sm:text-base" 
        placeholder="e.g. The role of public transport in reducing urban pollution..." 
    />
    
    <div class="flex justify-between mt-2 px-1">
                                <p id="topic-error-msg-reading" class="text-[10px] sm:text-xs text-red-500 font-medium hidden">Topic is too long (Max 50 words)</p>
                                <p class="text-[10px] sm:text-xs text-slate-400 ml-auto"><span id="word-count-reading">0</span> / 50 words</p>
                            </div>
                            <div id="ai-badge" class="hidden absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 px-2 sm:px-3 py-1 sm:py-1.5 bg-indigo-600 text-white rounded-lg sm:rounded-xl text-[8px] sm:text-[9px] font-black uppercase animate-pulse shadow-lg shadow-indigo-200">
                                <i data-lucide="sparkles" class="w-3 h-3"></i> AI Deciding
                            </div>
                        </div>
                        <div id="topicError" class="text-red-500 text-[10px] sm:text-[11px] font-bold mt-1 hidden px-2"></div>
                    </div>

                    <div class="space-y-2 relative">
                        <label class="text-[10px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider px-1">Source Material</label>
                        
                        <label for="userFile" id="fileUploadLabel"
                            class="flex items-center justify-center w-full bg-white border-2 border-dashed border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/30 text-slate-600 px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl sm:rounded-2xl cursor-pointer transition-all group/file relative overflow-hidden">
                            
                            <i data-lucide="file-up" class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-slate-400 group-hover/file:text-indigo-500 transition-colors"></i>
                            <span class="text-xs sm:text-sm font-bold">Upload <span class="text-indigo-500 font-black">.PDF / .DOCX</span></span>

                            <?php if($userPlan !== 'pro'): ?>
                                <div class="absolute inset-0 bg-slate-50/80 backdrop-blur-[1px] flex items-center justify-center gap-1.5 cursor-not-allowed">
                                    <i data-lucide="lock" class="w-3 h-3 sm:w-4 sm:h-4 text-slate-400"></i>
                                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-tighter text-slate-500">Pro Feature</span>
                                </div>
                            <?php endif; ?>
                        </label>

                        <input type="file" id="userFile" accept=".pdf,.txt,.docx" class="hidden" <?php if($userPlan !== 'pro'): ?> disabled <?php endif; ?> />
                        
                        <div id="fileNameDisplay" class="text-[10px] sm:text-[11px] font-bold text-indigo-600 mt-2 px-1 break-all"></div>
                        <div id="fileError" class="text-red-500 text-[10px] sm:text-[11px] font-bold mt-1 hidden px-2"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider px-1">Question Style</label>
                    <div class="relative ">
                        <i data-lucide="settings-2" class=" pointer-events-none absolute left-3.5 sm:left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 transition-colors group-focus-within:text-indigo-500"></i>
                        <select name="question_type" id="questionType" class="w-full appearance-none pl-10 sm:pl-11 pr-10 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none cursor-pointer text-sm sm:text-base font-bold text-slate-700">
                            <option value="true_false_not_given" selected>True / False / Not Given</option>
                            <option value="matching_headings">Matching Headings</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="sentence_completion">Fill in the Gaps</option>
                            <option value="yes_no_not_given">Yes / No / Not Given</option>
                            <option value="mixed">Mixed Types </option>
                        </select>
                        
                        <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                        <style>
        /* When the select (peer) is focused, change the icon color */
        .peer:focus ~ .peer-icon {
            color: rgb(79 70 229); /* indigo-600 */
        }
    </style>
                    </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider px-1">Difficulty Level</label>
                        <div class="grid grid-cols-3 p-1 bg-slate-100 rounded-xl sm:rounded-2xl border border-slate-200">
                            <label class="difficulty-btn cursor-pointer py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-[9px] sm:text-[11px] font-black uppercase tracking-tight flex justify-center items-center transition-all hover:text-indigo-600">
                                <input type="radio" name="difficulty" value="beginner" class="hidden"> Beginner
                            </label>
                            <label class="difficulty-btn cursor-pointer py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-[9px] sm:text-[11px] font-black uppercase tracking-tight bg-white text-indigo-600 shadow-sm border border-slate-200/50 flex justify-center items-center transition-all">
                                <input type="radio" name="difficulty" value="intermediate" class="hidden" checked> Int.
                            </label>
                            <label class="difficulty-btn cursor-pointer py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-[9px] sm:text-[11px] font-black uppercase tracking-tight flex justify-center items-center transition-all hover:text-indigo-600">
                                <input type="radio" name="difficulty" value="advanced" class="hidden"> Adv.
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    <div class="space-y-2">
                        <label class="flex justify-between px-1">
                            <span class="text-[9px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider">Questions</span>
                            <span class="text-[8px] sm:text-[10px] font-bold text-indigo-500">Max 13</span>
                        </label>
                        <div class="relative">
                            <i data-lucide="list-checks" class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                            <input name="num_questions" type="number" id="questionCount" min="1" max="13" value="5" 
                                class="w-full pl-9 sm:pl-11 pr-3 sm:pr-4 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none font-bold text-slate-700 text-sm sm:text-base" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="flex justify-between px-1">
                            <span class="text-[9px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider">Words</span>
                            <span class="text-[8px] sm:text-[10px] font-bold text-indigo-500">Max 1200</span>
                        </label>
                        <div class="relative">
                            <i data-lucide="file-text" class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                            <input name="word_count" type="number" id="wordCount" min="10" max="1200" value="1200" 
                                class="w-full pl-9 sm:pl-11 pr-3 sm:pr-4 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none font-bold text-slate-700 text-sm sm:text-base" />
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    

                    <p id="mixedWarning" class="text-red-500 text-[10px] sm:text-[11px] font-black px-2 hidden"></p>

                    <div id="mixedOptions" class="hidden mt-4 p-4 sm:p-5 border-2 border-indigo-50 rounded-[1.5rem] sm:rounded-[2rem] bg-indigo-50/30 space-y-3">
                        <h3 class="text-[10px] sm:text-xs font-black text-indigo-900 uppercase tracking-widest mb-1">Select Types to Mix</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-white rounded-xl border border-indigo-100 hover:border-indigo-300 transition-all cursor-pointer text-[11px] sm:text-xs font-bold text-slate-700 shadow-sm">
                                <input type="checkbox" name="mixed_types[]" value="true_false_not_given" class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded text-indigo-600 focus:ring-indigo-500"> True/False/NG
                            </label>
                            <label class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-white rounded-xl border border-indigo-100 hover:border-indigo-300 transition-all cursor-pointer text-[11px] sm:text-xs font-bold text-slate-700 shadow-sm">
                                <input type="checkbox" name="mixed_types[]" value="matching_headings" class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded text-indigo-600 focus:ring-indigo-500"> Matching Headings
                            </label>
                            <label class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-white rounded-xl border border-indigo-100 hover:border-indigo-300 transition-all cursor-pointer text-[11px] sm:text-xs font-bold text-slate-700 shadow-sm">
                                <input type="checkbox" name="mixed_types[]" value="multiple_choice" class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded text-indigo-600 focus:ring-indigo-500"> Multiple Choice
                            </label>
                            <label class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-white rounded-xl border border-indigo-100 hover:border-indigo-300 transition-all cursor-pointer text-[11px] sm:text-xs font-bold text-slate-700 shadow-sm">
                                <input type="checkbox" name="mixed_types[]" value="sentence_completion" class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded text-indigo-600 focus:ring-indigo-500"> Fill in Gaps
                            </label>
                            <label class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-white rounded-xl border border-indigo-100 hover:border-indigo-300 transition-all cursor-pointer text-[11px] sm:text-xs font-bold text-slate-700 shadow-sm">
                                <input type="checkbox" name="mixed_types[]" value="yes_no_not_given" class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded text-indigo-600 focus:ring-indigo-500"> Yes/No/NG
                            </label>
                        </div>
                    </div>
                </div>

                <div id="creditError" class="hidden flex items-center gap-2 sm:gap-3 text-[10px] sm:text-[11px] font-black uppercase text-red-600 bg-red-50 p-3 sm:p-4 rounded-xl sm:rounded-2xl border border-red-100 animate-pulse">
                    <i data-lucide="alert-circle" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                    <span>Insufficient credits. Please top up.</span>
                </div>

                <div class="pt-4 sm:pt-6 border-t border-slate-100">
                    <button type="submit" id="submitBtn" class="group relative w-full bg-slate-900 hover:bg-indigo-600 text-white rounded-2xl sm:rounded-[1.5rem] shadow-xl sm:shadow-2xl shadow-indigo-200 transition-all duration-500 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed overflow-hidden">
                        <div class="absolute inset-0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        
                        <div class="flex items-center relative z-10">
                            <div class="flex-grow py-4 sm:py-5 px-4 sm:px-8 flex items-center justify-center gap-3">
                                <div id="btnText" class="flex items-center gap-2 sm:gap-3">
                                    <i data-lucide="sparkles" class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-400 group-hover:text-white transition-all duration-300"></i>
                                    <span class="font-black text-lg sm:text-xl tracking-tight uppercase italic whitespace-nowrap">Generate Test</span>
                                </div>
                                
                                <div id="btnLoader" class="hidden items-center gap-2 sm:gap-3 font-black text-lg sm:text-xl uppercase italic whitespace-nowrap">
                                    <i data-lucide="loader-2" class="w-5 h-5 sm:w-6 sm:h-6 animate-spin text-indigo-400"></i>
                                    <span>Processing...</span>
                                </div>
                            </div>

                            <div class="bg-white/10 backdrop-blur-xl py-4 sm:py-5 px-5 sm:px-8 border-l border-white/5 flex items-center gap-2 sm:gap-3 group-hover:bg-indigo-500 transition-colors shrink-0">
                                <i data-lucide="zap" class="w-4 h-4 sm:w-5 sm:h-5 fill-amber-400 text-amber-400"></i>
                                <div class="flex flex-col items-start leading-none">
                                    <span id="costValue" class="text-xl sm:text-2xl font-black">1</span>
                                    <span class="text-[7px] sm:text-[8px] font-black opacity-60 tracking-[0.2em] uppercase">Cost</span>
                                </div>
                            </div>
                        </div>
                    </button>
                    
                    <p class="mt-4 sm:mt-6 text-center text-[8px] sm:text-[9px] text-slate-400 font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] flex items-center justify-center gap-2 sm:gap-3">
                        <span class="h-[1px] w-6 sm:w-8 bg-slate-200"></span>
                        No charge if generation fails
                        <span class="h-[1px] w-6 sm:w-8 bg-slate-200"></span>
                    </p>
                </div>
            </form>

            <div class="w-full lg:w-80 shrink-0 space-y-4 animate-fade-in self-center px-1 sm:px-0" style="animation-delay: 0.2s;">
                <div class="bg-slate-900 rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-8 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-5 sm:mb-6">
                            <div class="bg-indigo-500/20 p-2 rounded-lg">
                                <i data-lucide="shield-check" class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-400"></i>
                            </div>
                            <h3 class="text-base sm:text-lg font-black uppercase italic tracking-tight">Guidelines</h3>
                        </div>

                        <ul class="space-y-4 sm:space-y-5">
                            <li class="flex gap-3 sm:gap-4">
                                <span class="text-indigo-400 font-black text-base sm:text-lg">01</span>
                                <div>
                                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-400 mb-0.5 sm:mb-1">Matching Headings</p>
                                    <p class="text-xs sm:text-[13px] text-slate-200 leading-snug">Question count may vary based on paragraphs available.</p>
                                </div>
                            </li>

                            <li class="flex gap-3 sm:gap-4">
                                <span class="text-indigo-400 font-black text-base sm:text-lg">02</span>
                                <div>
                                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-400 mb-0.5 sm:mb-1">Gen Time</p>
                                    <p class="text-xs sm:text-[13px] text-slate-200 leading-snug">Increases with <span class="text-indigo-300">high words</span>, <span class="text-indigo-300">mixed types</span> & <span class="text-indigo-300">advanced diff</span>.</p>
                                </div>
                            </li>

                            <li class="flex gap-3 sm:gap-4">
                                <span class="text-indigo-400 font-black text-base sm:text-lg">03</span>
                                <div>
                                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-400 mb-0.5 sm:mb-1">Credit Cost</p>
                                    <p class="text-xs sm:text-[13px] text-slate-200 leading-snug">Standard tests cost <span class="text-amber-400 font-bold">1c</span>. Mixed types require <span class="text-amber-400 font-bold">2c</span>.</p>
                                </div>
                            </li>

                            <li class="flex gap-3 sm:gap-4">
                                <span class="text-indigo-400 font-black text-base sm:text-lg">04</span>
                                <div>
                                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-400 mb-0.5 sm:mb-1">Source Docs</p>
                                    <p class="text-xs sm:text-[13px] text-slate-200 leading-snug">Ensure files are text-selectable. Scanned images fail.</p>
                                </div>
                            </li>
                        </ul>

                        <div class="mt-6 sm:mt-8 pt-5 sm:pt-6 border-t border-white/10">
                            <div class="flex items-center gap-3 p-3 sm:p-4 bg-indigo-500/10 rounded-xl sm:rounded-2xl border border-indigo-500/20">
                                <i data-lucide="timer" class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-400 animate-pulse shrink-0"></i>
                                <p class="text-[9px] sm:text-[10px] font-medium text-slate-300 leading-tight">Average time: <span class="text-white">30-60 sec</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>

     <div id="saved-tests-page" class="page-content hidden">
        <h2 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6 text-gray-800">Saved Tests</h2>
        <div class="bg-white p-4 sm:p-8 rounded-xl sm:rounded-[2rem] shadow-sm border border-gray-200 max-w-7xl">
            <div class="bg-white shadow-xl rounded-xl p-4 sm:p-6 overflow-x-auto" id="tests-container">
            <?php echo $__env->make('dashboard_saved_tests', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>
      </div>

      <div id="games-page" class="page-content hidden">
        <h2 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6 text-gray-800">Games</h2>
         <?php echo $__env->make('games/games', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

      <div id="completed-page" class="page-content hidden">
        
          <?php echo $__env->make('completed', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

      <div id="settings-page" class="page-content hidden">
            <?php echo $__env->make('voice', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

      <div id="profile-page" class="page-content hidden">
        <?php echo $__env->make('profile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

      <div id="buycredits-page" class="page-content hidden">
        <h2 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6 text-gray-800">Buy Credits</h2>
         <?php echo $__env->make('buycredits', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

    </main>
  </div>

 <div id="resultModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" onclick="document.getElementById('resetBtnModal').click()"></div>
    
    <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-[0_25px_70px_-15px_rgba(0,0,0,0.3)] relative z-10 overflow-hidden transform transition-all border border-slate-100">
        
        <div class="h-2 bg-gradient-to-r from-emerald-400 via-teal-500 to-cyan-500"></div>

        <div class="p-12 flex flex-col items-center text-center">
            
            <div class="relative mb-10">
                <div class="absolute inset-0 bg-emerald-100 rounded-full scale-[1.8] opacity-20 animate-pulse"></div>
                <div class="w-28 h-28 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-full flex items-center justify-center shadow-2xl shadow-emerald-200 relative">
                    <i data-lucide="sparkles" class="w-12 h-12 text-white animate-bounce-short"></i>
                </div>
            </div>

            <h3 class="text-4xl font-black text-slate-900 mb-4 tracking-tight">Your Test is Ready!</h3>
            
            <p class="text-lg text-slate-500 leading-relaxed mb-12 max-w-sm">
                We've successfully generated your custom reading passage on 
                <span id="displayTopic" class="font-extrabold text-indigo-600"></span>. 
                It is now saved and ready for practice.
            </p>

            <div class="flex flex-col w-full gap-4">
                <button  onclick="showPage('saved-tests-page'); document.getElementById('resultModal').classList.add('hidden');" 
                        class="group w-full py-5 rounded-2xl bg-slate-900 text-white text-lg font-bold hover:bg-indigo-600 hover:scale-[1.02] active:scale-95 shadow-xl transition-all duration-300 flex items-center justify-center gap-3">
                    Start Practicing Now
                    <i data-lucide="arrow-right" class="w-6 h-6 group-hover:translate-x-2 transition-transform"></i>
                </button>
                
                <button id="resetBtnModal" class="w-full py-4 rounded-2xl font-bold text-slate-400 hover:text-slate-600 transition-colors">
                    Create Another Test
                </button>
            </div>
        </div>
    </div>
</div>


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

<script>
 // 1. INITIALIZATION & CONFIGURATION
function updateCharCount(el) {
    const text = el.value.trim();
    
    // Split by whitespace and filter out empty strings to get accurate word count
    const wordCount = text ? text.split(/\s+/).length : 0;
    
    const counterDisplay = document.getElementById('char-counter');
    counterDisplay.innerText = wordCount + "/50 words";

    // Premium Touch: Change color if they hit the limit
    if (wordCount > 50) {
        counterDisplay.classList.add('text-rose-500', 'font-bold');
        counterDisplay.classList.remove('text-slate-400');
    } else {
        counterDisplay.classList.remove('text-rose-500', 'font-bold');
        counterDisplay.classList.add('text-slate-400');
    }
}
lucide.createIcons();

const elements = {
    // Layout Toggles
    aiSuggestBtn: document.getElementById('aiSuggestBtn'), // Add this line
    btnSingle: document.getElementById('btnSingle'),
    btnFull: document.getElementById('btnFull'),
    formSingle: document.getElementById('generatorForm'),
    formFull: document.getElementById('formFull'),
    wordCountDisplay: document.getElementById('word-count-reading'),
    errorMsg: document.getElementById('topic-error-msg-reading'),
    // Single Form Elements
    submitBtn: document.getElementById('submitBtn'),
    resetBtnModal: document.getElementById('resetBtnModal'),
    closeModalBtn: document.getElementById('closeModalBtn'),
    btnText: document.getElementById('btnText'),
    btnLoader: document.getElementById('btnLoader'),
    topic: document.getElementById('topic'),
    questionType: document.getElementById('questionType'),
    questionCount: document.getElementById('questionCount'),
    wordCount: document.getElementById('wordCount'),
    difficultyBtns: document.querySelectorAll('.difficulty-btn'),
    mixedOptionsBox: document.getElementById('mixedOptions'),
    mixedTypeInputs: document.querySelectorAll('input[name="mixed_types[]"]'),
    limitWarning: document.getElementById('limitWarning'),
    mixedWarning: document.getElementById('mixedWarning'),
    creditError: document.getElementById('creditError'),
    creditCount: document.getElementById('creditCountreading'),
    creditCountListening: document.getElementById('creditCountListening'),
    creditCountSpeaking: document.getElementById('creditCountSpeaking'),
    // Result Modal Elements
    resultModal: document.getElementById('resultModal'),
    resultState: document.getElementById('resultState'),
    resTitle: document.getElementById('resultTitle'),
    resDifficulty: document.getElementById('resultDifficulty'),
    resWords: document.getElementById('resultWords'),
    resType: document.getElementById('resultType'),
    savedTestsTable: document.getElementById("savedTestsTable"),
    badgeContainer: document.querySelector('[data-page="testBadgeContainer"]')
};

const PHP_CREDITS = <?php echo $userCredits; ?>;
let state = { credits: PHP_CREDITS, isGenerating: false };
// AI Suggestion Logic
// AI Suggestion Logic - Updated to clear files
const aiToggle = document.getElementById('aiSuggestToggle');
const topicInput = document.getElementById('topic');
const aiBadge = document.getElementById('ai-badge');
const fileInput = document.getElementById("userFile");
const fileNameDisplay = document.getElementById("fileNameDisplay");
const wordCountInput = document.getElementById("wordCount");

if (aiToggle) {
    aiToggle.addEventListener('change', function() {
        if (this.checked) {
            // 1. Clear files
            if (fileInput) fileInput.value = ""; 
            if (fileNameDisplay) fileNameDisplay.textContent = "";

            // 2. Lock input and show AI state
            topicInput.value = "AI's Choice: Random Topic";
            topicInput.disabled = true;
            topicInput.classList.add("bg-slate-100");
            aiBadge.classList.remove('hidden');

            // 3. Disable File Upload UI if function exists
            if (typeof disableFileUpload === "function") {
                disableFileUpload();
            }

            // 4. Reset/Enable Word Count
            if (wordCountInput) {
                wordCountInput.disabled = false;
                wordCountInput.classList.remove("bg-slate-200", "cursor-not-allowed");
                if (!wordCountInput.value) wordCountInput.value = 1200;
            }
        } else {
            // RETURN TO MANUAL MODE
            topicInput.value = "";
            topicInput.disabled = false;
            topicInput.classList.remove("bg-slate-100");
            topicInput.placeholder = "e.g. Climate Change, Space Exploration";
            aiBadge.classList.add('hidden');
            
            // Re-enable File Upload UI if function exists
            if (typeof enableFileUpload === "function") {
                enableFileUpload();
            }
        }
    });
}
if (elements.topic) {
    elements.topic.addEventListener('focus', function() {
        const aiChoicePrefix = "AI's Choice:";
        
        // Only empty the field if it currently contains the AI suggestion
        if (this.value.startsWith(aiChoicePrefix)) {
            this.value = "";
            
            // Re-enable file upload since the topic is now empty
            if (typeof enableFileUpload === "function") {
                enableFileUpload();
            }
        }
    });
}
// 2. TOGGLE GENERATOR MODE
function toggleGeneratorMode(mode) {
    if (mode === 'full') {
        elements.formFull.classList.remove('hidden');
        elements.formSingle.classList.add('hidden');
        elements.btnFull.classList.add('bg-white', 'text-indigo-600', 'shadow-sm', 'border', 'border-slate-100');
        elements.btnFull.classList.remove('text-slate-600', 'hover:bg-slate-50');
        elements.btnSingle.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm', 'border', 'border-slate-100');
        elements.btnSingle.classList.add('text-slate-600', 'hover:bg-slate-50');
    } else {
        elements.formSingle.classList.remove('hidden');
        elements.formFull.classList.add('hidden');
        elements.btnSingle.classList.add('bg-white', 'text-indigo-600', 'shadow-sm', 'border', 'border-slate-100');
        elements.btnSingle.classList.remove('text-slate-600', 'hover:bg-slate-50');
        elements.btnFull.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm', 'border', 'border-slate-100');
        elements.btnFull.classList.add('text-slate-600', 'hover:bg-slate-50');
    }
}
if(elements.btnSingle) elements.btnSingle.addEventListener('click', () => toggleGeneratorMode('single'));
if(elements.btnFull) elements.btnFull.addEventListener('click', () => toggleGeneratorMode('full'));


// 3. UI HELPER FUNCTIONS
function updateCreditDisplay() {
    // Ensure we have a valid number, default to 0 if state.credits is missing
    const currentCredits = (state.credits !== undefined && state.credits !== null) ? state.credits : 0;

    // Look for elements FRESH every time
    const readingDisplay = document.getElementById('creditCountreading');
    const listeningDisplay = document.getElementById('creditCountListening');
    const speakingDisplay = document.getElementById('creditCountSpeaking');
    const completedDisplay = document.getElementById('creditCountCompleted');

    if (readingDisplay) readingDisplay.textContent = currentCredits;
    if (listeningDisplay) listeningDisplay.textContent = currentCredits;
    if (speakingDisplay) speakingDisplay.textContent = currentCredits;
    if (completedDisplay) completedDisplay.textContent = currentCredits;

    // Handle button states
    const isZero = currentCredits <= 0;
    const submitBtn = document.getElementById('submitBtn');
    const recordBtn = document.getElementById('recordBtnn'); // Added Speaking button ID

    [submitBtn, recordBtn].forEach(btn => {
        if (btn) {
            btn.disabled = isZero;
            // Only toggle grayscale/opacity if it's actually zero
            btn.classList.toggle('opacity-50', isZero);
            btn.classList.toggle('cursor-not-allowed', isZero);
        }
    });
}

/**
 * The bridge function used by the AI task handlers
 */
function updateCreditDisplaylistening(newBalance) {
    if (newBalance !== undefined && newBalance !== null) {
        state.credits = newBalance; // Sync the global state
        updateCreditDisplay(); // Trigger the DOM update
    }
}

function checkLimits() {
    if (!elements.questionCount || !elements.wordCount) return;
    const isMaxQ = parseInt(elements.questionCount.value) >= 13;
    const isMaxW = parseInt(elements.wordCount.value) >= 1200;
    if (elements.limitWarning) elements.limitWarning.classList.toggle('hidden', !(isMaxQ || isMaxW));
}

function toggleLoadingState(isLoading) {
    if (elements.btnText) elements.btnText.classList.toggle('hidden', isLoading);
    if (elements.btnLoader) elements.btnLoader.classList.toggle('hidden', !isLoading);
    if (elements.submitBtn) elements.submitBtn.disabled = isLoading;
}

function updateDifficultyUI(selectedBtn) {
    elements.difficultyBtns.forEach(btn => {
        btn.className = 'difficulty-btn cursor-pointer px-3 py-2 rounded-lg text-sm font-medium transition-all border border-slate-200 hover:bg-slate-50 text-slate-600 flex justify-center items-center';
        const radio = btn.querySelector('input');
        if (radio) radio.checked = false;
    });
    if (selectedBtn) {
        selectedBtn.className = 'difficulty-btn cursor-pointer px-3 py-2 rounded-lg text-sm font-medium border bg-indigo-600 text-white border-indigo-600 shadow-md transform scale-105 flex justify-center items-center';
        const radio = selectedBtn.querySelector('input');
        if (radio) radio.checked = true;
    }
}

function getSelectedMixedTypes() {
    const selectedTypes = [];
    if (elements.mixedTypeInputs) {
        elements.mixedTypeInputs.forEach(input => {
            if (input.checked) selectedTypes.push(input.value);
        });
    }
    return selectedTypes;
}

// 4. FILE UPLOAD LOGIC
const fileLabelBox = document.querySelector("label[for='userFile']");

// --- REUSABLE FUNCTIONS ---

function enableFileUpload() {
    fileInput.disabled = false;
    fileLabelBox.classList.remove("cursor-not-allowed", "bg-red-100", "opacity-50");
    fileLabelBox.style.pointerEvents = "auto";
}

function disableFileUpload() {
    fileInput.disabled = true;
    fileLabelBox.classList.add("cursor-not-allowed", "bg-red-100", "opacity-50");
    fileLabelBox.style.pointerEvents = "none";
}

// --- LOGIC ---

// 1. Monitor Topic Input
topicInput.addEventListener("input", function() {
    // If there is ANY text (after removing spaces), disable file upload
    if (this.value.trim().length > 0) {
        disableFileUpload();
    } else {
        enableFileUpload();
    }
});

// 2. Monitor File Input
fileInput.addEventListener("change", function() {
    if (this.files.length > 0) {
        const fileName = this.files[0].name;
        fileNameDisplay.innerHTML = `
            <span class="text-slate-700 font-medium">Selected: ${fileName}</span>
            <button id="removeFileBtn" class="text-red-600 ml-2 font-bold hover:text-red-800 transition">✖</button>
        `;

        // Disable Topic & Word Count
        topicInput.disabled = true;
        topicInput.value = ""; // Clear topic if they upload a file
        topicInput.classList.add("bg-slate-200", "cursor-not-allowed");
        topicInput.placeholder = "Disabled (File selected)";
        
        wordCountInput.disabled = true;
        wordCountInput.classList.add("bg-slate-200", "cursor-not-allowed");
        wordCountInput.value = "";

        // Handle File Removal
        document.getElementById("removeFileBtn").addEventListener("click", () => {
            resetAllFields();
        });
    }
});

function resetAllFields() {
    fileInput.value = "";
    fileNameDisplay.textContent = "";
    
    topicInput.disabled = false;
    topicInput.classList.remove("bg-slate-200", "cursor-not-allowed", "bg-red-100");
    topicInput.placeholder = "Enter a topic...";
    
    wordCountInput.disabled = false;
    wordCountInput.classList.remove("bg-slate-200", "cursor-not-allowed", "bg-red-100");
    wordCountInput.value = 1200; // Your default
    
    enableFileUpload();
}

// 5. VALIDATIONS & MIXED TYPE
document.getElementById("topic").addEventListener("input", function() {
    const text = this.value;
    const nonEnglishRegex = /[^\x00-\x7F]+/;
    const topicError = document.getElementById("topicError");
    if (nonEnglishRegex.test(text)) {
        topicError.textContent = "❌ Topic name must be in English only.";
        topicError.classList.remove("hidden");
        this.classList.add("border-red-500", "bg-red-50");
    } else {
        topicError.classList.add("hidden");
        this.classList.remove("border-red-500", "bg-red-50");
    }
});

/**
 * REWRITTEN: validateMixed
 * Handles strict 13-question locking for Mixed Mode and UI constraints.
 */
function validateMixed() {
    if (!elements.questionType || !elements.questionCount) return;

    const isMixed = elements.questionType.value === "mixed";
    const isPro = (typeof USER_PLAN !== 'undefined' && USER_PLAN === 'pro');
    const maxAllowed = isPro ? 3 : 2;
    const warning = elements.mixedWarning;
    const submitBtn = elements.submitBtn;
    const costValueDisplay = document.getElementById('costValue');

    // 1. Toggle visibility of mixed options
    if (elements.mixedOptionsBox) {
        elements.mixedOptionsBox.classList.toggle('hidden', !isMixed);
    }

    // 2. STRICT LOCK: Mixed Mode forced to 13 questions
    if (isMixed) {
        elements.questionCount.value = 13;
        elements.questionCount.disabled = true;
        // Apply visual "locked" styles
        elements.questionCount.classList.add("bg-slate-100", "cursor-not-allowed", "font-bold", "text-indigo-600");
    } else {
        // Only unlock if a file isn't uploaded (since file uploads also lock this field)
        const hasFile = fileInput && fileInput.files.length > 0;
        if (!hasFile) {
            elements.questionCount.disabled = false;
            elements.questionCount.classList.remove("bg-slate-100", "cursor-not-allowed", "font-bold", "text-indigo-600");
        }
    }

    if (!isMixed) {
        if (warning) warning.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = false;
        if (costValueDisplay) costValueDisplay.innerText = "1";
        return;
    }

    // 3. Handle Mixed Type Selection Logic
    const checkedBoxes = document.querySelectorAll('input[name="mixed_types[]"]:checked');
    const allBoxes = document.querySelectorAll('input[name="mixed_types[]"]');
    const selectedCount = checkedBoxes.length;

    allBoxes.forEach(cb => {
        const parent = cb.parentElement;
        if (!cb.checked) {
            const isAtLimit = selectedCount >= maxAllowed;
            cb.disabled = isAtLimit;
            parent.classList.toggle('opacity-50', isAtLimit);
            
            // Show designed modal instead of browser alert on click
            parent.onclick = () => {
                if (isAtLimit) showMixedLimitNotice(isPro);
            };
        } else {
            cb.disabled = false;
            parent.classList.remove('opacity-50');
            parent.onclick = null;
        }
    });

    // 4. Final Validation & Cost
    let currentCost = (selectedCount >= 2) ? 2 : 1;
    if (costValueDisplay) costValueDisplay.innerText = currentCost;

    let valid = true;
    let msg = "";

    if (selectedCount < 2) {
        msg = isPro 
            ? "Pick at least 2 types (Locked to 13 questions for balance)." 
            : "Pick exactly 2 types (Upgrade to Pro for 3+ types).";
        valid = false;
    }

    if (warning) {
        if (valid) {
            warning.classList.add('hidden');
            if (submitBtn) submitBtn.disabled = false;
        } else {
            warning.innerHTML = `<i class="fas fa-info-circle mr-1"></i> ${msg}`;
            warning.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = true;
        }
    }
}

/**
 * Better Designed Notice
 * Replaces the ugly 'alert()'
 */
function showMixedLimitNotice(isAlreadyPro) {
    if (!isAlreadyPro) {
        // Reuse your existing Premium Modal function
        showPremiumModal("Unlock Mixed Mode Mastery.");
    } else {
        // For Pro users, a simple non-intrusive notification is better
        const notice = document.createElement('div');
        notice.className = "fixed bottom-5 right-5 bg-slate-800 text-white px-6 py-3 rounded-2xl shadow-2xl z-[200] animate-in slide-in-from-right-10 duration-300 border border-slate-700";
        notice.innerHTML = `<span class="text-amber-400 mr-2">✨</span> Pro limit reached (3 types max).`;
        document.body.appendChild(notice);
        setTimeout(() => {
            notice.classList.add('animate-out', 'fade-out', 'slide-out-to-right-10');
            setTimeout(() => notice.remove(), 500);
        }, 3000);
    }
}
// 6. ASYNC FUNCTIONS (Save/Refresh)


function refreshSavedTests() {
    // Fetch the clean table file
    return fetch('/saved-tests-data') 
        .then(res => res.text())
        .then(html => {
            const container = document.getElementById('tests-container');
            if (container) {
                // 1. Inject the fresh table
                container.innerHTML = html;
                
                // 2. Re-initialize icons for the new HTML
                if (typeof lucide !== 'undefined') lucide.createIcons();
                
                // 3. Immediately trigger the badge logic so "New" appears
                if (typeof window.checkAndShowBadges === 'function') {
                    window.checkAndShowBadges();
                }
            } else {
                console.error("❌ ERROR: Could not find 'tests-container' to inject saved tests.");
            }
        })
        .catch(err => console.error("Refresh failed:", err));
}

function refreshBadge() {
    fetch("/api/notifications/counts")
        .then(res => res.json())
        .then(data => {
            // Update Saved Tests Badge (Indigo)
            const savedContainer = document.getElementById("testBadgeContainer");
            if (savedContainer) {
                savedContainer.innerHTML = data.new_tests > 0 ? 
                    `<span id="testBadge" class="bg-indigo-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">${data.new_tests}</span>` : '';
            }

            // Update Completed Tests Badge (Emerald/Green)
            
        });
}
function clearBadge() {
    const badge = document.getElementById('completedBadge');
    if (badge) {
        // Hide visually immediately
        badge.style.display = 'none';
        
        // Notify the server to reset the session variable
        fetch('reset_badge_count.php');
    }
}
// 7. EVENT LISTENERS
if (elements.difficultyBtns) {
    elements.difficultyBtns.forEach(btn => {
        btn.addEventListener('click', () => updateDifficultyUI(btn));
        if (btn.querySelector('input')?.checked) updateDifficultyUI(btn);
    });
}
if (elements.questionCount) elements.questionCount.addEventListener('input', (e) => {
    let val = parseInt(e.target.value);
    if (val > 13) e.target.value = 13;
    if (val < 1) e.target.value = 1;
    checkLimits(); validateMixed();
});
if (elements.wordCount) elements.wordCount.addEventListener('input', (e) => {
    if (parseInt(e.target.value) > 1200) e.target.value = 1200;
    checkLimits();
});
if (elements.questionType) elements.questionType.addEventListener('change', validateMixed);
if (elements.mixedTypeInputs) elements.mixedTypeInputs.forEach(cb => cb.addEventListener('change', validateMixed));

// MODAL RESET LOGIC
function resetSingleGenerator() {
    // 1. Reset the actual Checkbox/Toggle
    if (aiToggle) {
        aiToggle.checked = false; // Uncheck the AI toggle
        // Optionally dispatch an event if you want the 'change' listener to run naturally
        // aiToggle.dispatchEvent(new Event('change')); 
    }

    // 2. Hide modal and reset basic form fields
    elements.resultModal.classList.add('hidden');
    elements.formSingle.reset();

    // 3. Reset File Input state
    fileInput.value = ""; 
    fileNameDisplay.textContent = "";
    
    // --- Re-enable File Upload ---
    fileInput.disabled = false;
    fileLabelBox.classList.remove("cursor-not-allowed", "bg-red-100", "opacity-50");
    fileLabelBox.style.pointerEvents = "auto";
    if (typeof enableFileUpload === "function") enableFileUpload();

    // 4. Reset Topic Input state
    topicInput.disabled = false; 
    topicInput.value = ""; // Clear "AI's Choice" text
    topicInput.classList.remove("bg-red-100", "bg-indigo-50", "bg-slate-100", "text-indigo-700", "font-medium");
    topicInput.placeholder = "Enter a topic...";

    // 5. Hide the AI Badge
    if (aiBadge) aiBadge.classList.add('hidden');

    // 6. Reset Word Count
    wordCountInput.disabled = false; 
    wordCountInput.classList.remove("bg-red-100", "bg-slate-200", "cursor-not-allowed");
    wordCountInput.value = 1200;

    // 7. Reset Difficulty to default
    if (elements.difficultyBtns) {
        elements.difficultyBtns.forEach(btn => { 
            if (btn.textContent.trim() === 'Intermediate') btn.click(); 
        });
    }

if (elements.questionCount) {
        elements.questionCount.disabled = false;
        elements.questionCount.value = 5; // Or your preferred default
        elements.questionCount.classList.remove("bg-slate-100", "cursor-not-allowed", "font-bold", "text-indigo-600");
    }
if (elements.mixedTypeInputs) {
        elements.mixedTypeInputs.forEach(cb => {
            cb.checked = false;
            cb.disabled = false;
            cb.parentElement.classList.remove('opacity-50', 'cursor-not-allowed');
        });
    }
    // 6. Refresh mixed validation
    validateMixed();
}
if(elements.resetBtnModal) elements.resetBtnModal.addEventListener('click', resetSingleGenerator);
if(elements.closeModalBtn) elements.closeModalBtn.addEventListener('click', resetSingleGenerator);
function showPremiumModal(message) {
    // Remove existing modal if it exists
    const existingModal = document.getElementById('premiumModal');
    if (existingModal) existingModal.remove();

    const modalHtml = `
        <div id="premiumModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center transform transition-all scale-100 animate-in zoom-in-95 duration-300">
                <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="crown" class="w-10 h-10 text-amber-600"></i>
                </div>
                
                <h3 class="text-2xl font-black text-slate-800 mb-2">Unlock Pro</h3>
                <p class="text-slate-500 font-medium mb-8"> Join Pro to ${message} and unlock other features.</p>
                
                <div class="flex flex-col gap-3">
                    <button onclick="window.location.href='unlock_pro.php'" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-200 transition-all active:scale-95">
                        View Pro Plans
                    </button>
                    <button onclick="document.getElementById('premiumModal').remove()" class="w-full py-3 text-slate-400 font-bold hover:text-slate-600 transition-colors">
                        Maybe Later
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Refresh Lucide icons to render the 'crown' icon
    if (window.lucide) lucide.createIcons();
}

const fileUploadLabel = document.getElementById('fileUploadLabel');

if (fileUploadLabel) {
    fileUploadLabel.addEventListener('click', function(e) {
        // Check if the user is NOT pro (using your PHP variable passed to JS)
        const isPro = (typeof USER_PLAN !== 'undefined' && USER_PLAN === 'pro');
        
        if (!isPro) {
            // 1. Prevent the file browser from opening
            e.preventDefault();
            
            // 2. Trigger your high-end Premium Modal
            showPremiumModal("Analyze PDFs and Documents.");
        }
    });
}
// --------------------------------------------------------------------------------
// SINGLE TEST SUBMISSION
// --------------------------------------------------------------------------------
if (elements.formSingle) {
    elements.formSingle.addEventListener("submit", async (e) => {
        e.preventDefault();
        

        const hasFile = fileInput && fileInput.files.length > 0;

        // 1. Validation Logic
        if (hasFile && USER_PLAN !== 'pro') {
            showPremiumModal("File uploads are a Pro feature.");
            fileInput.value = ""; 
            const fileNameDisplay = document.getElementById("fileNameDisplay");
            if (fileNameDisplay) fileNameDisplay.textContent = "";
            return; 
        }

        if (!hasFile && !elements.topic.value.trim()) {
            alert("Please enter a topic or upload a file.");
            return;
        }

        if (state.isGenerating) return;
        toggleLoadingState(true);
        if (state.credits <= 0) {
            if (elements.creditError) elements.creditError.classList.remove("hidden");
            return;
        }

        // 2. Prepare Payload
        let payload; 
        let useFileUpload = false;
        const requiredCost = (elements.questionType.value === "mixed") ? 2 : 1;

        if (hasFile) {
            useFileUpload = true;
            payload = new FormData();
            payload.append("file", fileInput.files[0]);
            payload.append("difficulty", document.querySelector('input[name="difficulty"]:checked')?.value || "Intermediate");
            payload.append("num_questions", parseInt(elements.questionCount.value));
            payload.append("question_type", elements.questionType.value);
            if (elements.questionType.value === "mixed") {
                payload.append("mixed_types", JSON.stringify(getSelectedMixedTypes()));
            }
        } else {
            payload = {
                topic: elements.topic.value,
                difficulty: document.querySelector('input[name="difficulty"]:checked')?.value || "Intermediate",
                num_questions: parseInt(elements.questionCount.value),
                word_count: parseInt(elements.wordCount.value),
                question_type: elements.questionType.value,
            };
            if (elements.questionType.value === "mixed") {
                payload.mixed_types = getSelectedMixedTypes();
            }
        }

        try {
            state.isGenerating = true;
            
            // 3. Initiate the Background Task
            const requestHeaders = { "X-CSRF-TOKEN": "<?php echo e(csrf_token()); ?>" };
            if (!useFileUpload) requestHeaders["Content-Type"] = "application/json";

            const initResponse = await fetch("api/generate/reading", {
                method: "POST",
                body: useFileUpload ? payload : JSON.stringify(payload),
                headers: requestHeaders
            });

            const initData = await initResponse.json();
            if (initData.error) throw new Error(initData.error);
            if (!initData.task_id) throw new Error("Server failed to start task.");

            // 4. HAND OFF TO UNIVERSAL PROCESSOR
            // This function handles the modal, polling, credits, and table refresh
            const result = await handleTaskProcessing(
                initData.task_id, 
                requiredCost, 
                "AI is reading your context..."
            );

            // 5. Handle Final Result UI (Success Modal)
            if (result && result.passage) {
                let aiTitle = "Reading Passage";
                if (result.questions && result.questions.length > 0) {
                    aiTitle = result.questions[0].passage_title || result.questions[0].topic || "Reading Passage";
                }

                const displayTopic = document.getElementById('displayTopic');
                if (displayTopic) displayTopic.textContent = aiTitle;

                if (elements.resultModal) elements.resultModal.classList.remove('hidden');
                if (window.lucide) lucide.createIcons();
            }

        } catch (err) {
            console.error("Generation Error:", err);
            Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
        } finally {
            state.isGenerating = false;
            toggleLoadingState(false);
        }
    });
}
// --------------------------------------------------------------------------------
// FULL TEST SUBMISSION LOGIC (Moved here)
// --------------------------------------------------------------------------------


// Initial Run
updateCreditDisplay();
validateMixed();

// Add this to your existing change listener
questionType.addEventListener('change', (e) => {
    if (e.target.value === 'matching_headings') {
        showMatchingModal();
        // Optionally auto-set Word Count to a higher default for better results
        wordCount.value = Math.max(wordCount.value, 800); 
    }
});

function showMatchingModal() {
    const modalHtml = `
        <div id="matchingModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl animate-in fade-in zoom-in duration-200">
                <div class="flex items-center gap-3 mb-4 text-indigo-600">
                    <div class="p-2 bg-indigo-50 rounded-lg">
                        <i data-lucide="info" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Matching Mode Active</h3>
                </div>
                <div class="space-y-3 text-sm text-slate-600 leading-relaxed">
                    <p>Because you selected <strong>Matching Headings</strong>:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Question Count:</strong> Automatically determined by the number of paragraphs in your passage.</li>
                        <li><strong>Passage Length:</strong> To get more questions, increase your <strong>Word Count</strong> (higher word counts generate more paragraphs).</li>
                    </ul>
                </div>
                <button onclick="closeMatchingModal()" class="w-full mt-6 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition-all shadow-lg shadow-indigo-200">
                    I Understand
                </button>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    lucide.createIcons(); // Re-render icons for the new modal
}

function closeMatchingModal() {
    const modal = document.getElementById('matchingModal');
    if (modal) modal.remove();
}
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');

    // 1. Toggle Sidebar on Button Click
    mobileMenuBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevents immediate closing
        sidebar.classList.toggle('-translate-x-full');
    });

    // 2. Close Sidebar when clicking anywhere else (Premium feel)
    document.addEventListener('click', function(e) {
        const isClickInsideSidebar = sidebar.contains(e.target);
        const isClickOnButton = mobileMenuBtn.contains(e.target);

        if (!isClickInsideSidebar && !isClickOnButton && !sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    });

    // 3. Close Sidebar on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.add('-translate-x-full');
        }
    });
});
// Function to bridge the button click to the sidebar logic
// 1. Attach click events to all sidebar navigation items
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault(); // Stop the '#' link from jumping to the top of the page
            const pageId = this.getAttribute('data-page');
            if (pageId) {
                showPage(pageId);
            }
        });
    });

    // Optional: Load the last active tab on page refresh
    const lastActiveTab = localStorage.getItem('activeDashboardTab');
    if (lastActiveTab) {
        showPage(lastActiveTab);
    }
});

// 2. The unified showPage function
window.showPage = function(pageId) {
    // 1. Prevent redundant execution if already on the page
    const currentActive = localStorage.getItem('activeDashboardTab');
    if (currentActive === pageId) return;

    localStorage.setItem('activeDashboardTab', pageId);

    // 2. Batch DOM updates to prevent layout thrashing (the shaking)
    requestAnimationFrame(() => {
        // Hide all pages
        document.querySelectorAll('.page-content').forEach(page => {
            page.classList.add('hidden');
        });

        // Show target
        const targetPage = document.getElementById(pageId);
        if (targetPage) targetPage.classList.remove('hidden');

        // Update Sidebar Visuals
        document.querySelectorAll('.nav-item').forEach(nav => {
            nav.classList.remove('bg-slate-800', 'text-white'); // Clean state
        });

        const targetNavItem = document.querySelector(`.nav-item[data-page="${pageId}"]`);
        if (targetNavItem) {
            targetNavItem.classList.add('bg-slate-800', 'text-white');
        }

        // 3. Handle Badge Sync
        if (pageId === 'saved-tests-page') {
            handleBadgeDismissal();
        }

        // 4. Re-render icons AFTER the layout has settled
        setTimeout(() => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }, 50); 
    });
};

// Separate the fetch logic to keep showPage clean
function handleBadgeDismissal() {
    const badge = document.getElementById("testBadgeContainer");
    if (badge) badge.innerHTML = ''; 

    fetch('api/notifications/dismiss', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        }
    }).catch(err => console.error("Sync failed:", err));
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
    
    <?php if($pendingTask): ?>
        const activeTaskId = <?php echo json_encode($pendingTask->task_id, 15, 512) ?>;
        
        // Pass the task ID to your existing handler
        handleTaskProcessing(activeTaskId, 2); 
    <?php endif; ?>
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



if (elements.topic) {
    elements.topic.addEventListener('input', function() {
        // FIX: Match these names to the variables used below
        const MAX_WORDS = 50; 
        const MAX_CHARS = 300; 

        if (this.disabled) return;

        const text = this.value.trim();
        const words = text ? text.split(/\s+/).length : 0;
        const chars = text.length;
        
        // Update display
        if(elements.wordCountDisplay) {
            elements.wordCountDisplay.innerText = words;
        }

        // The logic was failing because MAX_WORDS was undefined
        const isInvalid = words > MAX_WORDS || chars > MAX_CHARS;

        if (isInvalid) {
            this.classList.add('border-red-500', 'ring-red-500/20');
            this.classList.remove('focus:border-indigo-500', 'focus:ring-indigo-500/10');
            
            if(elements.errorMsg) {
                elements.errorMsg.innerText = words > MAX_WORDS ? "Topic exceeds 50 words!" : "Too many characters!";
                elements.errorMsg.classList.remove('hidden');
            }
        } else {
            this.classList.remove('border-red-500', 'ring-red-500/20');
            this.classList.add('focus:border-indigo-500', 'focus:ring-indigo-500/10');
            
            if(elements.errorMsg) {
                elements.errorMsg.classList.add('hidden');
            }
        }

        // Handle Button State
        if(elements.btnText) {
            elements.btnText.disabled = isInvalid;
            elements.btnText.classList.toggle('opacity-50', isInvalid);
            elements.btnText.classList.toggle('cursor-not-allowed', isInvalid);
        }
    });
}
/* --- Universal Task Polling --- */
/* --- Universal Task Polling --- */
// Add initialData to the arguments so the function can actually see it
async function handleTaskProcessing(taskId, requiredCost = 2, initialData = null, loadingText = "Processing...", shouldRefresh = true) {
    
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
        if (shouldRefresh) {
            if (typeof refreshSavedTests === "function") refreshSavedTests();
            if (typeof refreshBadge === "function") refreshBadge();
        }

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
// Refresh count every 30 seconds
 window.confirmLogout = function() {
        Swal.fire({
            title: 'Logout?',
            text: "Are you sure you want to log out?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Yes, logout'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = "login";
        });
    };

    const overlay = document.getElementById('sidebar-overlay');

mobileMenuBtn.addEventListener('click', function() {
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    setTimeout(() => overlay.classList.add('opacity-100'), 10);
});

// Function to close
function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.remove('opacity-100');
    setTimeout(() => overlay.classList.add('hidden'), 300);
}
</script>
</body>
</html><?php /**PATH C:\xampp\htdocs\ieltsaireadinggenerator\billing-service\resources\views/dashboard.blade.php ENDPATH**/ ?>