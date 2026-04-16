
<div class="w-full max-w-5xl mx-auto space-y-6 animate-in fade-in duration-500">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 px-2">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Performance History</h2>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Review your past attempts</p>
        </div>
        <div class="flex items-center gap-0.5 w-full sm:w-auto justify-end">
            <div class="flex items-center justify-center gap-2 bg-amber-50 text-amber-700 px-3 sm:px-4 py-2 rounded-l-2xl border border-amber-100 border-r-0 shadow-sm w-full sm:w-auto" title="Available Credits">
                <i data-lucide="zap" class="w-4 h-4 fill-amber-400 text-amber-400 shrink-0"></i>
                <span class="text-[10px] sm:text-xs font-black uppercase tracking-tighter whitespace-nowrap">
                    <span id="creditCountCompleted"><?php echo e($userCredits); ?></span> Credits
                </span>
            </div>
            <button onclick="showPage('buycredits-page')" type="button" class="flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white w-9 h-9 sm:w-9 sm:h-9 rounded-r-2xl border border-amber-500 shadow-sm transition-all hover:scale-105 active:scale-95 group shrink-0" title="Buy Credits">
                <i data-lucide="plus" class="w-4 h-4 sm:w-5 sm:h-5 transition-transform group-hover:rotate-90"></i>
            </button>
        </div>

        <div class="inline-flex items-center gap-2 bg-indigo-50 border border-indigo-100 px-4 py-2 rounded-xl">
            <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
            <span class="text-[10px] font-black text-indigo-700 uppercase tracking-widest">
                <?php echo e($results->count()); ?> Total Tests
            </span>
        </div>
    </div>

    <div class="grid gap-3 max-h-[70vh] overflow-y-auto pr-2 pb-10 custom-scrollbar">
        <?php if($results->isEmpty()): ?>
            <div class="text-center py-16 bg-white rounded-3xl border border-dashed border-slate-200">
                <p class="text-sm text-slate-400 font-bold">No completed tests found yet.</p>
            </div>
        <?php endif; ?>

        <?php $__currentLoopData = $results; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $type = strtoupper($row->test_type);
                $topic = $row->topic_name ?: 'Untitled Assessment';
                $aiExists = (bool)$row->ai_exists;
                $isReading = ($type === 'READING');
                $bgTheme = $isReading ? 'bg-blue-50' : 'bg-purple-50';
                $textTheme = $isReading ? 'text-blue-600' : 'text-purple-600';
            ?>

            <div class="group bg-white p-4 sm:p-5 rounded-[2rem] border border-slate-100 hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-100/50 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="w-14 h-14 rounded-2xl <?php echo e($bgTheme); ?> <?php echo e($textTheme); ?> flex items-center justify-center shrink-0 shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <?php if($isReading): ?>
                                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-0.5-5z M8 7h6 M8 11h8"/>
                            <?php else: ?>
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5S19.832 6.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-800 tracking-tight leading-none mb-1">
                            <?php echo e($topic); ?>

                        </h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            <span class="<?php echo e($textTheme); ?>"><?php echo e($type); ?></span> • <?php echo e(\Carbon\Carbon::parse($row->submission_time)->format('M d, Y')); ?>

                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between sm:justify-end gap-6 sm:gap-8 w-full sm:w-auto px-2 sm:px-0">
                    <div class="text-right">
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 leading-none">
                            <?php echo e($row->score); ?><span class="text-slate-300 text-sm sm:text-base font-bold ml-1">/<?php echo e($row->total); ?></span>
                        </p>
                        <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mt-1">Score</p>
                    </div>

                    <?php if($aiExists): ?>
                        <a href="<?php echo e(url('/ai-review-feedback')); ?>?result_id=<?php echo e($row->id); ?>&test_type=<?php echo e($type); ?>" 
                           class="h-12 px-6 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black flex items-center justify-center transition-all shadow-lg shadow-indigo-200">
                            View AI Review
                        </a>
                    <?php else: ?>
                        <button onclick="openReview(<?php echo e($row->id); ?>, '<?php echo e($type); ?>', <?php echo e($row->test_id); ?>)" 
                                class="h-12 px-6 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black transition-all">
                            Review
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>

<div id="reviewModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeReview()"></div>
    
    <div id="modalContent" class="absolute right-0 top-0 h-full w-full max-w-md bg-slate-50 shadow-2xl transition-transform duration-300 translate-x-full flex flex-col">
        
        <div class="p-6 bg-white border-b border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <button onclick="closeReview()" class="p-2 -ml-2 hover:bg-slate-100 rounded-full transition-colors">
                    <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
                </button>
                <div id="modalTypeBadge"></div> </div>
            <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Attempt Review</h2>
        </div>

        <div id="reviewList" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
            </div>

        <div class="p-6 bg-white border-t border-slate-200">
            <button onclick="startAiAnalysis()" class="w-full h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest flex items-center justify-center gap-3 transition-all shadow-lg shadow-indigo-200 group">
                <i data-lucide="sparkles" class="w-5 h-5 fill-indigo-400 text-indigo-400 group-hover:animate-pulse"></i>
                Generate AI Breakdown
            </button>
        </div>
    </div>
</div>

<div id="successModalCompleted" class="fixed inset-0 z-[110] flex items-center justify-center hidden p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" onclick="this.parentElement.classList.add('hidden')"></div>
    <div id="successModalContent" class="relative bg-white w-full max-w-sm rounded-[2.5rem] p-8 shadow-2xl border border-white transform transition-all scale-95 duration-300">
        <div class="flex flex-col items-center text-center">
            <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mb-6 shadow-inner">
                <i data-lucide="check-circle-2" class="w-10 h-10 text-emerald-500"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800 mb-2 uppercase tracking-tight">Analysis Ready!</h3>
            <p class="text-slate-500 text-sm font-medium leading-relaxed mb-6">Your neural breakdown has been generated. Redirecting to your report...</p>
            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                <div class="bg-emerald-500 h-full animate-[progress_2.5s_ease-in-out]"></div>
            </div>
        </div>
    </div>
</div>
<style>
    @keyframes progress {
        0% { width: 0%; }
        100% { width: 100%; }
    }
</style>
<script>
let currentReviewId = null;
let currentReviewType = null;
let currentTestId = null;
let currentResultId = null; //
async function openReview(id, type, testId) {
    currentResultId = id;
    currentReviewType = type;
    currentTestId = testId;
    
    const modal = document.getElementById('reviewModal');
    const content = document.getElementById('modalContent');
    const list = document.getElementById('reviewList');
    const badge = document.getElementById('modalTypeBadge');
    
    // 1. Show Modal & Trigger Animation
    modal.classList.remove('hidden');
    setTimeout(() => content.classList.remove('translate-x-full'), 10);
    
    // 2. Loading State (Keep your current nice spinner)
    list.innerHTML = `
        <div class="flex flex-col items-center justify-center py-32 text-indigo-400">
            <div class="w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
            <p class="text-[10px] font-black uppercase tracking-widest">Retrieving Neural Logs...</p>
        </div>`;

    try {
        // 3. Updated Fetch to Laravel Endpoint
        // We use backticks to inject the ID and type as a query parameter
        const response = await fetch(`/tests/review/${id}?type=${type}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Unknown Sync Error');
        }

        // 4. Update UI Badge
        const isReading = data.type === 'READING';
        badge.innerText = `${data.type} TEST • SCORE: ${data.score}/${data.total}`;
        badge.className = `px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest mb-1.5 inline-block ${
            isReading ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'
        }`;

        // 5. Build the List
        list.innerHTML = '';
        
        if (data.review.length === 0) {
            list.innerHTML = `
                <div class="text-center p-8 bg-white rounded-3xl border border-slate-200 text-slate-400 font-bold text-sm">
                    No detailed answer breakdown available.
                </div>`;
            return;
        }

        // Map through data.review and build cards
        const reviewHtml = data.review.map(item => {
            const isCorrect = item.isCorrect;
            return `
                <div class="bg-white p-5 rounded-[1.5rem] border border-slate-200 shadow-sm mb-4">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-100 text-slate-700 text-xs font-black shrink-0">
                                Q${item.number}
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-widest ${isCorrect ? 'text-emerald-500' : 'text-rose-500'}">
                                ${isCorrect ? 'Correct' : 'Mistake'}
                            </span>
                        </div>
                        <div class="w-6 h-6 rounded-full ${isCorrect ? 'bg-emerald-50 text-emerald-500' : 'bg-rose-50 text-rose-500'} flex items-center justify-center shrink-0">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="${isCorrect ? 'M20 6 9 17l-5-5' : 'M18 6 6 18M6 6l12 12'}"/>
                            </svg>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Your Answer</p>
                            <p class="text-sm font-bold text-slate-800 break-words">${item.user}</p>
                        </div>
                        ${!isCorrect ? `
                        <div class="p-3.5 rounded-xl bg-indigo-50 border border-indigo-100">
                            <p class="text-[9px] font-black text-indigo-400 uppercase tracking-wider mb-1">Target Answer</p>
                            <p class="text-sm font-bold text-indigo-700 break-words">${item.correct}</p>
                        </div>` : ''}
                    </div>
                </div>`;
        }).join('');

        list.innerHTML = reviewHtml;

    } catch (err) {
        console.error("Sync Error:", err);
        list.innerHTML = `
            <div class="text-center p-8 bg-rose-50 rounded-3xl border border-rose-100 text-rose-500 font-bold text-sm">
                FAILED TO LOAD LOGS: ${err.message}
            </div>`;
    }
}
async function startAiAnalysis() {
    // 1. Beautiful Styled Confirmation
    const result_confirm = await Swal.fire({
        title: 'GENERATE AI REVIEW?',
        text: "This will deduct 1 credit to generate a detailed neural breakdown of your performance.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5', // Indigo-600
        cancelButtonColor: '#94a3b8',  // Slate-400
        confirmButtonText: 'YES, GENERATE',
        cancelButtonText: 'CANCEL',
        background: '#ffffff',
        borderRadius: '2rem',
        customClass: {
            title: 'font-black tracking-tight text-slate-800',
            htmlContainer: 'font-medium text-slate-500',
            confirmButton: 'rounded-xl px-6 py-3 text-xs font-black uppercase tracking-widest',
            cancelButton: 'rounded-xl px-6 py-3 text-xs font-black uppercase tracking-widest'
        }
    });

    if (!result_confirm.isConfirmed) return;

    // 2. Button Loading State
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `
        <div class="flex items-center gap-4">
            <div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            <span class="text-xs font-bold uppercase tracking-widest">Initializing AI...</span>
        </div>`;

    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/api/tests/generate-ai-review', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token 
            },
            body: JSON.stringify({
                test_id: currentTestId,
                result_id: currentResultId,
                type: currentReviewType
            })
        });

        const data = await response.json();
        if (!response.ok) throw new Error(data.error || "Uplink failed");

        closeReview();

        // 3. Process Task (Suppressing background dashboard refreshes)
        const taskResult = await handleTaskProcessing(
            data.task_id, 
            1, 
            "AI is analyzing your context...",
            false // <--- Keeps it from firing /saved-tests-data
        );

        if (taskResult) {
            // Use unique ID for the success popup to avoid 'null' errors
            const successModal = document.getElementById('successModalCompleted');
            const successContent = document.getElementById('successModalContent'); 
            
            if (successModal && successContent) {
                successModal.classList.remove('hidden');
                setTimeout(() => successContent.classList.replace('scale-95', 'scale-100'), 10);
            }

            // Re-init icons if available
            if (typeof lucide !== 'undefined') lucide.createIcons();

            setTimeout(() => {
                window.location.href = `/ai-review-feedback?result_id=${taskResult.result_id}&test_type=${taskResult.test_type}`;
            }, 2500);
        }
    } catch (err) {
        console.error("AI Analysis Error:", err);
        
        // Styled Error Alert
        Swal.fire({
            title: 'CONNECTION ERROR',
            text: err.message || "A synchronization error occurred. Please try again.",
            icon: 'error',
            confirmButtonColor: '#ef4444',
            borderRadius: '2rem'
        });

        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}
function closeReview() {
    const content = document.getElementById('modalContent');
    content.classList.add('translate-x-full');
    setTimeout(() => document.getElementById('reviewModal').classList.add('hidden'), 300);
}
</script>

<style>
/* Slim scrollbar for the modal list */
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style><?php /**PATH C:\xampp\htdocs\ieltsaireadinggenerator\billing-service\resources\views/completed.blade.php ENDPATH**/ ?>