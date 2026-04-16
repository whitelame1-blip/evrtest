<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Results | IELTS Lab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>
        (function() {
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function () {
                window.location.replace("<?php echo e(route('dashboard')); ?>");
            };
        })();
    </script>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-900">

    <div class="max-w-4xl mx-auto py-12 px-4">
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100 border border-slate-100 overflow-hidden mb-8">
            <div class="bg-indigo-600 p-8 text-white flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <span class="text-indigo-200 text-xs font-black uppercase tracking-widest">Listening Report</span>
                    <h1 class="text-3xl font-black mt-1"><?php echo e($topic); ?></h1>
                </div>
                <div class="relative w-24 h-24 flex items-center justify-center bg-white/10 rounded-full border-4 border-white/20">
                    <div class="text-center">
                        <span class="block text-2xl font-black leading-none"><?php echo e($score); ?>/<?php echo e($total); ?></span>
                        <span class="text-[10px] uppercase font-bold opacity-70">Score</span>
                    </div>
                </div>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                <div class="p-4 bg-slate-50 rounded-2xl">
                    <p class="text-xs font-bold text-slate-400 uppercase">Accuracy</p>
                    <p class="text-2xl font-black text-indigo-600"><?php echo e($percentage); ?>%</p>
                </div>
                <div class="p-4 bg-slate-50 rounded-2xl">
                    <p class="text-xs font-bold text-slate-400 uppercase">Correct</p>
                    <p class="text-2xl font-black text-emerald-500"><?php echo e($score); ?></p>
                </div>
                <div class="p-4 bg-slate-50 rounded-2xl">
                    <p class="text-xs font-bold text-slate-400 uppercase">Incorrect</p>
                    <p class="text-2xl font-black text-rose-500"><?php echo e($total - $score); ?></p>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2">
            <i class="bi bi-list-check text-indigo-600"></i> Answer Breakdown
        </h2>

        <div class="space-y-4">
            <?php $__currentLoopData = $results_comparison; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $res): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="bg-white p-6 rounded-2xl border <?php echo e($res['correct'] ? 'border-emerald-100' : 'border-rose-100'); ?> flex items-start gap-4 transition-transform hover:scale-[1.01]">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo e($res['correct'] ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'); ?>">
                        <i class="bi <?php echo e($res['correct'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill'); ?> text-xl"></i>
                    </div>
                    
                    <div class="flex-1">
                        <p class="text-xs font-black text-slate-400 uppercase mb-1">Question <?php echo e($res['num']); ?></p>
                        <p class="font-bold text-slate-700 mb-3"><?php echo e($res['text']); ?></p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="p-3 rounded-lg <?php echo e($res['correct'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'); ?>">
                                <span class="block text-[10px] uppercase font-black opacity-60">Your Answer</span>
                                <span class="font-bold"><?php echo e($res['u'] ?: '(No Answer)'); ?></span>
                            </div>
                            <?php if(!$res['correct']): ?>
                            <div class="p-3 rounded-lg bg-slate-100 text-slate-700 border border-slate-200">
                                <span class="block text-[10px] uppercase font-black opacity-60">Correct Answer</span>
                                <span class="font-bold"><?php echo e($res['c']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="mt-12 flex flex-col md:flex-row gap-4 justify-center">
            <a href="<?php echo e(route('dashboard')); ?>" class="px-8 py-4 bg-slate-800 text-white rounded-2xl font-black hover:bg-slate-900 transition-all flex items-center justify-center gap-2">
                <i class="bi bi-house"></i> Back to Dashboard
            </a>
            <button onclick="window.print()" class="px-8 py-4 bg-white border border-slate-200 text-slate-600 rounded-2xl font-black hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                <i class="bi bi-printer"></i> Print Results
            </button>
        </div>
    </div>

</body>
</html><?php /**PATH C:\xampp\htdocs\ieltsaireadinggenerator\billing-service\resources\views/taketest/process_results_listening.blade.php ENDPATH**/ ?>