<?php
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($passage_title); ?> | IELTS Practice</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @media (min-width: 1024px) {
            .pane-content { 
                height: calc(100vh - 5rem); 
                overflow-y: auto; 
            }
            body { overflow: hidden; }
        }
        html { scroll-behavior: smooth; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<div id="error-toast" class="fixed top-24 left-1/2 -translate-x-1/2 z-[60] bg-red-600 text-white px-6 py-4 rounded-2xl shadow-2xl transform transition-all duration-300 -translate-y-10 opacity-0 pointer-events-none flex items-center gap-3 w-[90%] max-w-md">
    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <span class="font-bold text-sm md:text-base">Please answer all questions before submitting.</span>
</div>
<body class="bg-slate-50 antialiased">

<nav class="bg-white border-b border-slate-200 px-4 md:px-6 py-4 flex justify-between items-center shadow-sm h-20 sticky top-0 z-50">
    <div class="max-w-[60%]">
        <h1 class="text-sm md:text-lg font-bold text-slate-800 truncate"><?php echo e($passage_title); ?></h1>
        <p class="text-[10px] md:text-xs text-indigo-600 font-bold uppercase tracking-wider">Reading Practice</p>
    </div>
    <div class="flex items-center gap-3 md:gap-6">
        <div class="bg-red-50 px-3 md:px-4 py-1.5 md:py-2 rounded-xl border border-red-100 flex items-center gap-2 md:gap-3">
            <span class="hidden md:inline text-red-700 font-bold text-xs uppercase">Time:</span>
            <span id="timer-display" class="text-lg md:text-xl font-mono font-black text-red-600">20:00</span>
        </div>
        <button onclick="exitTest()" class="text-slate-400 hover:text-red-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>
</nav>

<div class="lg:hidden sticky top-20 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200 px-4 py-3">
    <div class="flex items-center gap-3 overflow-x-auto no-scrollbar pb-1" id="mobile-nav-container">
        <span class="text-[10px] font-black text-slate-400 uppercase rotate-180 [writing-mode:vertical-lr]">Jump to:</span>
        <?php for($i = 1; $i < $qNum; $i++): ?>
            
            <a href="#q-<?php echo e($i); ?>" data-answered="false" class="nav-jump-btn flex-shrink-0 w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-sm font-bold text-slate-600 transition-all">
                <?php echo e($i); ?>

            </a>
        <?php endfor; ?>
    </div>
</div>

<form id="takeTestForm" method="POST" action="<?php echo e(url('/submit-test')); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="test_id" value="<?php echo e($test_id); ?>">

    <div class="flex flex-col lg:flex-row h-[calc(100vh-5rem)] overflow-hidden">
       <div class="w-full h-1/2 lg:h-full lg:w-1/2 bg-white p-6 md:p-8 border-b lg:border-b-0 lg:border-r border-slate-200 overflow-y-auto pane-content">
        <div class="max-w-3xl mx-auto">
            <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-lg text-xs font-bold uppercase mb-4 inline-block">Reading Passage</span>
            <h2 class="text-2xl md:text-3xl font-serif font-bold text-slate-900 mb-6"><?php echo e($passage_title); ?></h2>
            <div class="text-base md:text-lg text-slate-800 leading-relaxed font-serif whitespace-pre-line shadow-sm p-6 md:p-8 rounded-2xl bg-slate-50 border border-slate-100">
                <?php echo e($clean_passage); ?>

            </div>
        </div>
    </div>

        <div class="w-full h-1/2 lg:h-full lg:w-1/2 bg-slate-50/50 p-6 md:p-8 overflow-y-auto pane-content shadow-inner">
        <div class="max-w-2xl mx-auto">
            <?php 
                $display_number = 1; 
                $displayedBoxes = [];
            ?>
                
                <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <header class="mb-8 border-b-2 border-slate-300 pb-4 mt-10 first:mt-0">
                        <h2 class="text-xl md:text-2xl font-black text-slate-900">
                            Questions <?php echo e($group['start']); ?> – <?php echo e($group['end']); ?>

                        </h2>
                        <div class="mt-2 flex flex-col gap-1">
                            <span class="text-indigo-600 font-black text-[10px] uppercase tracking-widest">
                                <?php echo e($group['format_type']); ?>

                            </span>
                            <p class="text-slate-600 text-sm font-medium italic">
                                <?php echo e($group['instruction']); ?>

                            </p>
                        </div>
                    </header>

                    <?php $__currentLoopData = $group['questions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $subtype = strtoupper($q['subtype'] ?? $q['type'] ?? 'STANDARD');
                            $metadata = $q['metadata'] ?? [];
                            $q_id = $q['id'] ?? $index;
                            $statement = $q['question_statement'] ?? $metadata['question_statement'] ?? '';
                        ?>

                        <div id="q-<?php echo e($display_number); ?>" class="mb-8 p-5 md:p-6 border border-slate-200 rounded-2xl bg-white shadow-sm hover:shadow-md transition-shadow scroll-mt-44 lg:scroll-mt-10">
                            
                            <?php if($subtype === 'STANDARD'): ?>
                                <div class="p-4 bg-slate-50 border border-slate-100 rounded-xl">
                                    <p class="text-base md:text-lg font-medium text-slate-800 leading-loose">
                                        <span class="text-indigo-600 font-black mr-2"><?php echo e($display_number++); ?>.</span>
                                        <?php echo e($q['prefix'] ?? ''); ?>

                                        <span class="inline-block px-2 min-w-[140px] border-b-2 border-slate-300 focus-within:border-indigo-500">
                                            <input type="text" name="answer[<?php echo e($q_id); ?>]" class="w-full focus:outline-none bg-transparent text-indigo-700 font-bold text-center placeholder:text-slate-300 text-sm md:text-base" placeholder="type answer...">
                                        </span>
                                        <?php echo e($q['suffix'] ?? ''); ?>

                                    </p>
                                </div>

                            <?php elseif($subtype === 'MCQ-SINGLE'): ?>
                                <div class="space-y-4">
                                    <p class="text-base md:text-lg font-bold text-slate-900 leading-snug mb-4">
                                        <span class="text-indigo-600 mr-2"><?php echo e($display_number++); ?>.</span>
                                        <?php echo e($q['question_text'] ?? $q['prefix'] ?? ''); ?>

                                    </p>
                                    <div class="grid grid-cols-1 gap-3">
                                        <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $parts = explode(':', $opt, 2);
                                                $letter = trim($parts[0]);
                                                $text = trim($parts[1] ?? $opt);
                                            ?>
                                            <label class="group relative flex items-start p-4 border-2 border-slate-100 rounded-2xl cursor-pointer hover:border-indigo-200 hover:bg-indigo-50/30 transition-all has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/50">
                                                <input type="radio" name="answer[<?php echo e($q_id); ?>]" value="<?php echo e($letter); ?>" class="peer hidden">
                                                <div class="mt-0.5 w-6 h-6 rounded-full border-2 border-slate-300 flex-shrink-0 flex items-center justify-center peer-checked:border-indigo-600 peer-checked:bg-indigo-600">
                                                    <div class="w-2 h-2 rounded-full bg-white opacity-0 peer-checked:opacity-100"></div>
                                                </div>
                                                <div class="ml-4 flex-grow">
                                                    <span class="text-sm font-black text-slate-400 group-hover:text-indigo-500"><?php echo e($letter); ?>.</span>
                                                    <span class="ml-1 text-slate-700 font-medium leading-relaxed text-sm md:text-base"><?php echo e($text); ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>

                           <?php elseif($subtype === 'MCQ-MULTI'): ?>
    <?php
        // Determine how many answers are required (default to 2 if not set)
        $maxChoices = $q['target_count'] ?? 2; 
    ?>
    <div class="space-y-4 question-block" id="q-<?php echo e($q_id); ?>">
        <div class="p-4 bg-indigo-50/50 rounded-xl border border-indigo-100 mb-4">
            <p class="text-base md:text-lg font-bold text-slate-900 leading-snug">
                <span class="text-indigo-600 mr-2"><?php echo e($display_number++); ?>.</span>
                <?php echo e($q['prefix'] ?? ''); ?> 
                <span class="text-xs font-normal text-indigo-400 block mt-1">(Select exactly <?php echo e($maxChoices); ?>)</span>
            </p>
        </div>
        <div class="grid grid-cols-1 gap-3">
            <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $parts = explode(':', $opt, 2);
                    $letter = trim($parts[0]);
                    $text = trim($parts[1] ?? $opt);
                ?>
                <label class="group relative flex items-start p-4 border-2 border-slate-100 rounded-2xl cursor-pointer hover:border-indigo-200 hover:bg-indigo-50/30 transition-all has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/50">
                    <input type="checkbox" 
                           name="answer[<?php echo e($q_id); ?>][]" 
                           value="<?php echo e($letter); ?>" 
                           data-max="<?php echo e($maxChoices); ?>" 
                           data-qgroup="<?php echo e($q_id); ?>"
                           class="peer hidden multi-check">
                    
                    <div class="mt-0.5 w-6 h-6 rounded-md border-2 border-slate-300 flex-shrink-0 flex items-center justify-center peer-checked:border-indigo-600 peer-checked:bg-indigo-600">
                        <svg class="w-4 h-4 text-white opacity-0 peer-checked:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-grow">
                        <span class="text-sm font-black text-slate-400 group-hover:text-indigo-500"><?php echo e($letter); ?>.</span>
                        <span class="ml-1 text-slate-700 font-medium leading-relaxed text-sm md:text-base"><?php echo e($text); ?></span>
                    </div>
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

                            <?php elseif($subtype === 'MCQ-GLOBAL'): ?>
                                <div class="space-y-4">
                                    <p class="text-lg md:text-xl font-bold text-slate-900 leading-tight">
                                        <span class="text-indigo-600 mr-2"><?php echo e($display_number++); ?>.</span>
                                        <?php echo e($q['prefix'] ?? $q['question_text'] ?? ''); ?>

                                    </p>
                                    <div class="grid grid-cols-1 gap-4">
                                        <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $parts = explode(':', $opt, 2);
                                                $letter = trim($parts[0]);
                                                $text = trim($parts[1] ?? $opt);
                                            ?>
                                            <label class="group relative flex items-start p-5 border-2 border-slate-200 rounded-2xl cursor-pointer hover:border-indigo-500 hover:bg-white transition-all shadow-sm has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/50">
                                                <input type="radio" name="answer[<?php echo e($q_id); ?>]" value="<?php echo e($letter); ?>" class="peer hidden">
                                                <div class="mt-1 w-6 h-6 rounded-full border-2 border-slate-300 flex-shrink-0 flex items-center justify-center peer-checked:border-indigo-600 peer-checked:bg-indigo-600">
                                                    <div class="w-2 h-2 rounded-full bg-white opacity-0 peer-checked:opacity-100"></div>
                                                </div>
                                                <div class="ml-4">
                                                    <?php if($letter): ?>
                                                        <span class="block text-xs font-black text-indigo-500 uppercase mb-1">Option <?php echo e($letter); ?></span>
                                                    <?php endif; ?>
                                                    <span class="text-slate-700 font-bold leading-relaxed text-sm md:text-base"><?php echo e($text); ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>

                            <?php elseif(in_array($subtype, ['TFNG', 'YNNG'])): ?>
                                <div class="space-y-5">
                                    <div class="flex items-start gap-4">
                                        <span class="flex-shrink-0 w-8 h-8 rounded-xl bg-slate-900 text-white text-xs font-black flex items-center justify-center shadow-lg">
                                            <?php echo e($display_number++); ?>

                                        </span>
                                        <p class="text-base md:text-lg font-semibold text-slate-800 leading-relaxed pt-0.5">
                                            <?php echo e($statement); ?>

                                        </p>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 md:gap-3 ml-0 md:ml-12">
                                        <?php
                                            $labels = $subtype === 'TFNG' ? ['TRUE', 'FALSE', 'NOT GIVEN'] : ['YES', 'NO', 'NOT GIVEN'];
                                        ?>
                                        <?php $__currentLoopData = $labels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $unique_id = "q_{$q_id}_" . str_replace(' ', '', $val);
                                            ?>
                                            <label for="<?php echo e($unique_id); ?>" class="relative flex items-center justify-center p-3 border-2 border-slate-100 rounded-xl cursor-pointer transition-all hover:bg-slate-50 has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-500">
                                                <input type="radio" id="<?php echo e($unique_id); ?>" name="answer[<?php echo e($q_id); ?>]" value="<?php echo e($val); ?>" class="peer hidden"> 
                                                <span class="text-[9px] md:text-[10px] font-black tracking-widest text-slate-500 peer-checked:text-indigo-700 uppercase"><?php echo e($val); ?></span>
                                            </label>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>

                            <?php elseif($subtype === 'MATCHING-HEADINGS'): ?>
                                <div class="space-y-6">
                                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-5 md:p-6">
                                        <h3 class="text-amber-800 font-bold text-xs uppercase mb-4">List of Headings</h3>
                                        <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="text-xs md:text-sm text-slate-700 mb-2">
                                                <b><?php echo e(explode(':', $heading)[0]); ?></b> <?php echo e(explode(':', $heading)[1] ?? ''); ?>

                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                    <?php if(isset($q['answer']) && is_array($q['answer'])): ?>
                                        <?php $__currentLoopData = $q['answer']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pLabel => $ans): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200">
                                                <span class="font-bold text-slate-800 text-sm"><?php echo e($display_number++); ?>. <?php echo e($pLabel); ?></span>
                                                <select name="answer[<?php echo e($q_id); ?>][<?php echo e($pLabel); ?>]" class="bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs md:text-sm font-bold text-indigo-600 outline-none">
                                                    <option value="">Select...</option>
                                                    <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <?php $val = trim(explode(':', $opt)[0]); ?>
                                                        <option value="<?php echo e($val); ?>"><?php echo e($val); ?></option>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </select>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    <?php endif; ?>
                                </div>

                            <?php elseif(in_array($subtype, ['MATCHING-INFO', 'MATCHING-FEATURES'])): ?>
                                <div class="space-y-6">
                                    <?php
                                        $boxSignature = md5(json_encode($metadata['options_box']));
                                    ?>
                                    
                                    <?php if(!isset($displayedBoxes[$boxSignature])): ?>
                                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                            <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $boxItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php
                                                    $boxItem = preg_replace('/\s+/', ' ', trim($boxItem));
                                                    $parts = str_contains($boxItem, ':') ? explode(':', $boxItem, 2) : explode(' ', $boxItem, 2);
                                                    $letter = trim($parts[0]);
                                                    $description = trim($parts[1] ?? 'Section ' . $letter);
                                                ?>
                                                <div class="flex items-center gap-3 bg-white p-2 md:p-3 rounded-xl border border-slate-100 shadow-sm">
                                                    <span class="bg-indigo-100 text-indigo-700 font-black rounded-md px-2 py-1 text-[10px] md:text-xs"><?php echo e($letter); ?></span>
                                                    <span class="text-[10px] md:text-xs font-bold text-slate-600 leading-tight"><?php echo e($description); ?></span>
                                                </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                        <?php $displayedBoxes[$boxSignature] = true; ?>
                                    <?php endif; ?>

                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border border-slate-200 rounded-xl bg-white hover:border-indigo-100 transition-colors">
                                        <div class="flex items-start gap-3 flex-grow">
                                            <span class="flex-shrink-0 w-8 h-8 rounded-lg bg-slate-900 text-white text-xs font-black flex items-center justify-center"><?php echo e($display_number++); ?></span>
                                            <p class="text-slate-700 font-medium leading-relaxed pt-1 text-sm md:text-base"><?php echo e($statement); ?></p>
                                        </div>
                                        <select name="answer[<?php echo e($q_id); ?>]" class="w-full sm:w-32 bg-slate-50 border-2 border-slate-200 text-slate-800 font-black py-2 rounded-xl focus:border-indigo-500 outline-none text-center text-sm">
                                            <option value="" disabled selected>—</option>
                                            <?php $__currentLoopData = $metadata['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php
                                                    $opt = preg_replace('/\s+/', ' ', trim($opt));
                                                    $val = str_contains($opt, ':') ? trim(explode(':', $opt)[0]) : trim(explode(' ', $opt)[0]);
                                                ?>
                                                <option value="<?php echo e($val); ?>"><?php echo e($val); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                </div>

                            <?php elseif($subtype === 'SUMMARY-BANK'): ?>
                                <div class="space-y-6">
                                    <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100">
                                        <h4 class="text-[10px] font-black uppercase text-indigo-400 tracking-widest mb-3">Word Bank</h4>
                                        <div class="flex flex-wrap gap-2">
                                            <?php $__currentLoopData = $q['options_box']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <span class="px-2 py-1 bg-white border border-indigo-200 shadow-sm rounded-lg text-[10px] md:text-xs font-bold text-indigo-900"><?php echo e($opt); ?></span>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    </div>
                                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 text-slate-700 leading-relaxed font-serif text-base md:text-lg">
                                        <?php echo nl2br(e($q['summary_text'] ?? '')); ?>

                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
                                        <?php $__currentLoopData = $q['questions_options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subQ): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl">
                                                <span class="font-bold text-slate-600 text-xs md:text-sm">
                                                    <span class="text-indigo-600 mr-1"><?php echo e($display_number++); ?>.</span> Gap [<?php echo e($subQ['gap_number']); ?>]
                                                </span>
                                                <input type="text" name="answer[<?php echo e($subQ['id']); ?>]" placeholder="A, B..." class="w-14 p-2 border-2 border-slate-200 rounded-lg text-center font-black text-indigo-600 focus:border-indigo-500 outline-none uppercase text-sm">
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <div class="pb-32 lg:pb-20">
                    <button type="submit" id="submitBtn" class="w-full py-5 bg-indigo-600 text-white rounded-2xl font-black text-xl shadow-xl hover:bg-indigo-700 transition-all active:scale-[0.98]">
                        Submit Practice Test
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    const takeTestForm = document.getElementById('takeTestForm');
    const timerDisplay = document.getElementById('timer-display');
    
    // --- TIMER LOGIC ---
    let timeRemaining = 20 * 60;
    const timerInterval = setInterval(() => {
        if (timeRemaining <= 0) { 
            clearInterval(timerInterval); 
            takeTestForm.submit(); 
            return; 
        }
        timeRemaining--;
        const mins = Math.floor(timeRemaining / 60).toString().padStart(2, '0');
        const secs = (timeRemaining % 60).toString().padStart(2, '0');
        timerDisplay.textContent = `${mins}:${secs}`;
    }, 1000);

    function exitTest() { 
        if(confirm("Are you sure you want to exit? Progress will be lost.")) {
            window.location.href = "<?php echo e(url('/dashboard')); ?>"; 
        }
    }

    // --- NAVIGATION HIGHLIGHT LOGIC ---
    let currentActiveId = '1';

    function updateNavVisuals() {
        document.querySelectorAll('.nav-jump-btn').forEach(link => {
            const isAnswered = link.getAttribute('data-answered') === 'true';
            const isActive = link.getAttribute('href') === `#q-${currentActiveId}`;

            // Reset all dynamic classes first
            link.className = 'nav-jump-btn flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold transition-all border';

            if (isActive) {
                if (isAnswered) {
                    link.classList.add('bg-emerald-600', 'text-white', 'border-emerald-700', 'ring-2', 'ring-emerald-200', 'ring-offset-1');
                } else {
                    link.classList.add('bg-indigo-600', 'text-white', 'border-indigo-700', 'ring-2', 'ring-indigo-200', 'ring-offset-1');
                }
                link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else {
                if (isAnswered) {
                    link.classList.add('bg-emerald-500', 'text-white', 'border-emerald-600');
                } else {
                    link.classList.add('bg-slate-100', 'text-slate-600', 'border-slate-200');
                }
            }
        });
    }

    // --- SCROLL OBSERVER ---
    const observerOptions = {
        root: null,
        rootMargin: '-10% 0px -80% 0px',
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                currentActiveId = entry.target.getAttribute('id').replace('q-', '');
                updateNavVisuals();
            }
        });
    }, observerOptions);

    document.querySelectorAll('[id^="q-"]').forEach((q) => observer.observe(q));

    // --- ANSWER TRACKING LOGIC ---
    function checkQuestionAnswered(questionDiv) {
        if (!questionDiv) return;
        
        let isAnswered = false;
        const multiCheckInputs = questionDiv.querySelectorAll('.multi-check');

        // 1. If it's a MULTI-SELECT question
        if (multiCheckInputs.length > 0) {
            const maxRequired = parseInt(multiCheckInputs[0].getAttribute('data-max') || 1, 10);
            const checkedCount = questionDiv.querySelectorAll('.multi-check:checked').length;
            
            // Only mark as answered if they select EXACTLY the required amount
            isAnswered = (checkedCount === maxRequired);
        } 
        // 2. If it's a STANDARD question
        else {
            const inputs = questionDiv.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'radio' || input.type === 'checkbox') {
                    if (input.checked) isAnswered = true;
                } else {
                    if (input.value.trim() !== '') isAnswered = true;
                }
            });
        }

        // Find corresponding nav button and update its data attribute
        const qId = questionDiv.getAttribute('id').replace('q-', '');
        const navLink = document.querySelector(`a[href="#q-${qId}"]`);
        
        if (navLink) {
            navLink.setAttribute('data-answered', isAnswered ? 'true' : 'false');
            updateNavVisuals();
        }
    }

    // --- EVENT LISTENERS FOR INPUTS ---
    takeTestForm.addEventListener('change', (e) => {
        // Prevent users from selecting MORE than the max allowed
        if (e.target.classList.contains('multi-check')) {
            const checkbox = e.target;
            const maxAllowed = parseInt(checkbox.getAttribute('data-max'), 10);
            const questionDiv = checkbox.closest('[id^="q-"]');
            const checkedBoxes = questionDiv.querySelectorAll('.multi-check:checked');

            if (checkedBoxes.length > maxAllowed) {
                checkbox.checked = false; // Undo the click
                
                Swal.fire({
                    title: 'Limit Reached',
                    text: `You can only select exactly ${maxAllowed} answers for this question.`,
                    icon: 'warning',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        }

        const questionDiv = e.target.closest('[id^="q-"]');
        checkQuestionAnswered(questionDiv);
    });

    takeTestForm.addEventListener('input', (e) => {
        const questionDiv = e.target.closest('[id^="q-"]');
        checkQuestionAnswered(questionDiv);
    });

    // --- FORM SUBMISSION VALIDATION ---
    takeTestForm.addEventListener('submit', function(e) {
        // Find all navigation buttons that are NOT answered (this will now correctly include multi-selects that are missing options)
        const unansweredLinks = document.querySelectorAll('.nav-jump-btn[data-answered="false"]');
        
        if (unansweredLinks.length > 0) {
            e.preventDefault(); // Stop the form from submitting
            
            // Show the error toast
            const toast = document.getElementById('error-toast');
            toast.classList.remove('-translate-y-10', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            
            // Hide the toast after 3 seconds
            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('-translate-y-10', 'opacity-0');
            }, 3000);

            // Automatically scroll to the FIRST unanswered question and flash it red
            const firstUnansweredId = unansweredLinks[0].getAttribute('href').replace('#', '');
            const firstUnansweredElement = document.getElementById(firstUnansweredId);
            
            if (firstUnansweredElement) {
                firstUnansweredElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Add a temporary red glow to draw attention
                firstUnansweredElement.classList.add('ring-4', 'ring-red-500', 'border-red-500');
                setTimeout(() => {
                    firstUnansweredElement.classList.remove('ring-4', 'ring-red-500', 'border-red-500');
                }, 2000);
            }
        }
    });

    window.addEventListener("pageshow", function (event) {
        const historyTraversal = event.persisted || 
                               (typeof window.performance != "undefined" && 
                                window.performance.navigation.type === 2);
        if (historyTraversal) {
            // Force the user back to the dashboard
            window.location.href = "<?php echo e(url('/dashboard')); ?>";
        }
    });

    // Optional: Replace the current history entry so the user can't "Back" into the form
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script><?php /**PATH C:\xampp\htdocs\ieltsaireadinggenerator\billing-service\resources\views/taketest/take_test.blade.php ENDPATH**/ ?>