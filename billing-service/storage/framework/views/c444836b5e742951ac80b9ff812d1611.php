<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/games.css')); ?>">
    <title>Battle Prep IELTS</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">
  
        <header class="flex flex-col lg:flex-row lg:items-end justify-between mb-8 sm:mb-12 gap-6">
            <div class="flex flex-row items-center gap-4 sm:gap-6 p-3 sm:p-4 border-l-4 border-indigo-600 bg-white shadow-sm rounded-r-2xl">
                
                <div class="relative shrink-0 group cursor-pointer" onclick="openAvatarModal()">
                    <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full blur opacity-25 group-hover:opacity-60 transition duration-500"></div>
                    <div class="relative w-14 h-14 sm:w-16 sm:h-16 bg-slate-900 rounded-full flex items-center justify-center shadow-lg border-2 border-indigo-500/50 overflow-hidden">
                        <?php if(!empty($user_data->avatar_url)): ?>
                            <img src="<?php echo e($user_data->avatar_url); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i data-lucide="user" class="w-7 h-7 sm:w-8 sm:h-8 text-indigo-400"></i>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-indigo-600/80 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                        </div>
                    </div>
                    <div class="absolute -bottom-1 -right-1 bg-emerald-500 border-2 border-white w-4 h-4 sm:w-5 sm:h-5 rounded-full flex items-center justify-center shadow-sm">
                         <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div>
                    </div>
                </div>

                <div class="space-y-1 sm:space-y-2">
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-slate-900 tracking-tighter uppercase italic leading-none">
                        Battle Prep <span class="text-indigo-600 drop-shadow-[0_0_10px_rgba(79,70,229,0.2)]">IELTS</span>
                    </h1>
                    
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <div class="flex items-center gap-2 bg-slate-900 px-2 sm:px-3 py-1 rounded-md">
                            <span class="text-[8px] sm:text-[10px] font-black text-indigo-400 uppercase tracking-widest">Operator ID</span>
                            <span class="text-white font-mono font-bold text-xs sm:text-base">
                                <?php echo e($user_data->nickname ?? 'RECON_GUEST'); ?>

                            </span>
                        </div>
                        
                        <div class="flex items-center gap-1.5 px-2 py-1 bg-emerald-50 rounded-full border border-emerald-100">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-[9px] sm:text-[10px] font-bold text-emerald-600 uppercase">Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <div class="bg-indigo-50 border border-indigo-100 px-3 sm:px-4 py-2 rounded-xl flex items-center gap-2 sm:gap-3 flex-1 sm:flex-none">
                    <i data-lucide="star" class="text-indigo-500 w-4 h-4 sm:w-5 sm:h-5 fill-indigo-500"></i>
                    <div class="flex flex-col">
                        <span class="text-[8px] uppercase font-black text-indigo-400 leading-none">Total XP</span>
                        <span id="header-total-xp" class="font-bold text-indigo-700 leading-none text-sm sm:text-base">
                            <?php echo e(number_format($total_xp)); ?>

                        </span>
                    </div>
                    <button onclick="manualSync()" id="sync-btn" class="ml-auto sm:ml-2 p-1 hover:bg-indigo-200 rounded-full transition-colors text-indigo-400">
                        <i data-lucide="refresh-cw" class="w-3 h-3"></i>
                    </button>
                </div>
                
                <button onclick="openLootModal()" class="flex items-center gap-2 sm:gap-3 bg-slate-900 border border-indigo-500/50 px-3 sm:px-4 py-2 rounded-xl hover:bg-indigo-600 transition-all group shadow-[0_0_15px_rgba(79,70,229,0.2)]">
                    <i data-lucide="shopping-cart" class="text-indigo-400 group-hover:text-white w-4 h-4 sm:w-5 sm:h-5"></i>
                    <span class="text-[10px] sm:text-xs font-black text-white uppercase tracking-widest whitespace-nowrap">Supply Depot</span>
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
            
            <a href="#" class="group relative bg-white border-2 border-slate-100 rounded-[2rem] p-1 transition-all hover:border-indigo-500 hover:shadow-2xl hover:shadow-indigo-100">
                <div class="bg-slate-50 rounded-[1.8rem] p-6 sm:p-8 h-full flex flex-col">
                    <div class="flex justify-between items-start mb-6">
                        <div class="relative">
                             <div class="absolute inset-0 bg-indigo-400 blur-xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                             <div class="relative w-14 h-14 sm:w-16 sm:h-16 bg-white rounded-2xl flex items-center justify-center shadow-sm text-indigo-600 group-hover:-translate-y-2 transition-transform duration-300">
                                <i data-lucide="compass" class="w-7 h-7 sm:w-8 sm:h-8 anim-spin-slow"></i>
                             </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] sm:text-[10px] font-black text-indigo-500 uppercase tracking-widest bg-indigo-50 px-2 py-1 rounded-md">Rank: <?php echo e($rank_title); ?></span>
                            <div class="text-[10px] sm:text-xs font-bold text-slate-400 mt-1">
                                XP earned: <span id="solo-xp-display"><?php echo e(number_format($xp)); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="text-xl sm:text-2xl font-black text-slate-800 uppercase italic tracking-tight">Solo Quest</h2>
                    <p class="text-slate-500 text-xs sm:text-sm mt-2 leading-relaxed font-medium">
                        Conquer Reading & Listening modules. Unlock new vocabulary regions as you progress through the IELTS map.
                    </p>
                    
                    <div class="mt-auto pt-8 grid grid-cols-2 gap-3">
                        <button onclick="openRankModal(event)" class="bg-white border border-slate-100 p-3 rounded-2xl group/rank hover:border-indigo-400 hover:shadow-lg hover:shadow-indigo-100 transition-all text-left">
                            <div class="flex justify-between items-start">
                                <div class="text-[8px] font-black text-slate-400 uppercase">Global Rank</div>
                                <i data-lucide="external-link" class="w-3 h-3 text-slate-300 group-hover/rank:text-indigo-500 transition-colors"></i>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-base sm:text-lg font-black text-slate-800"># <?php echo e($global_rank_pos); ?></span>
                                <i data-lucide="award" class="w-4 h-4 text-indigo-500"></i>
                            </div>
                        </button>
                        
                        <div class="bg-white border border-slate-100 p-3 rounded-2xl">
                            <div class="text-[8px] font-black text-slate-400 uppercase">Arena XP</div>
                            <div class="flex items-center gap-1">
                                <span class="text-base sm:text-lg font-black text-slate-800"><?php echo e(number_format($xp_arena)); ?></span>
                                <i data-lucide="swords" class="w-4 h-4 text-rose-500"></i>
                            </div>
                        </div>
                    </div>

                    <button onclick="continueAdventure()" class="mt-6 w-full bg-white border-2 border-slate-200 py-3 rounded-2xl font-bold text-slate-700 group-hover:bg-indigo-600 group-hover:border-indigo-600 group-hover:text-white transition-all shadow-sm">
                        Continue Adventure
                    </button>
                </div>
            </a>

            
            <div class="group relative bg-white border-2 border-slate-100 rounded-[2rem] p-1 transition-all cursor-not-allowed overflow-hidden">
                <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/40 backdrop-blur-[1px]">
                    <div class="bg-slate-900 text-white px-4 sm:px-6 py-2 rounded-full font-black uppercase italic tracking-widest text-xs sm:text-sm shadow-2xl ring-4 ring-white">
                        Coming Soon
                    </div>
                </div>

                <div class="bg-slate-50 rounded-[1.8rem] p-6 sm:p-8 h-full flex flex-col opacity-50 grayscale">
                    <div class="flex justify-between items-start mb-6">
                        <div class="relative w-14 h-14 sm:w-16 sm:h-16 bg-white rounded-2xl flex items-center justify-center shadow-sm text-slate-400">
                            <i data-lucide="zap" class="w-7 h-7 sm:w-8 sm:h-8"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-200 px-2 py-1 rounded-md">Rank: Locked</span>
                        </div>
                    </div>
                    
                    <h2 class="text-xl sm:text-2xl font-black text-slate-400 uppercase italic tracking-tight">The Arena</h2>
                    <p class="text-slate-400 text-xs sm:text-sm mt-2 leading-relaxed font-medium">
                        Duel other students in Live Speaking & Grammar battles. Wager your "Study Coins" to climb the Global leaderboard.
                    </p>

                    <div class="mt-auto pt-8 flex items-center gap-4 bg-white p-4 rounded-2xl border border-slate-200">
                        <div class="flex -space-x-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-200 border-2 border-white flex items-center justify-center text-[9px] text-slate-400 font-bold">?</div>
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-300 border-2 border-white flex items-center justify-center text-[9px] text-white font-bold">VS</div>
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-200 border-2 border-white flex items-center justify-center text-[9px] text-slate-400 font-bold italic">?</div>
                        </div>

                        <div class="flex flex-col">
                            <div class="flex items-center gap-1.5">
                                <span class="relative flex h-2 w-2">
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-slate-300"></span>
                                </span>
                                <span class="text-[10px] sm:text-xs font-black text-slate-400 tracking-tight">0 Players</span>
                            </div>
                            <span class="text-[8px] sm:text-[9px] font-bold text-slate-300 uppercase tracking-widest">Unavailable</span>
                        </div>
                    </div>

                    <button disabled class="mt-6 w-full bg-slate-100 border-2 border-slate-200 py-3 rounded-2xl font-bold text-slate-400 cursor-not-allowed">
                        Feature Locked
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    <div id="loot-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-6">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" onclick="closeLootModal()"></div>
        <div class="relative w-full max-w-4xl max-h-[95vh] overflow-y-auto bg-slate-900 border-2 border-indigo-500 rounded-[1.5rem] sm:rounded-[2.5rem] shadow-2xl animate-in zoom-in duration-300">
            <button onclick="closeLootModal()" class="absolute top-4 right-4 sm:top-6 sm:right-6 z-10 text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-6 h-6 sm:w-8 sm:h-8"></i>
            </button>
            <div class="p-5 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 sm:mb-8 border-b border-slate-700 pb-6 gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-indigo-500/10 border border-indigo-500/50 rounded-2xl flex items-center justify-center">
                            <i data-lucide="refresh-cw" class="w-6 h-6 sm:w-8 sm:h-8 text-indigo-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl sm:text-2xl font-black text-white uppercase italic tracking-tight">Supply Converter</h3>
                            <p class="text-slate-400 text-[9px] sm:text-xs font-bold uppercase tracking-tighter">Authorized Personnel Only</p>
                        </div>
                    </div>
                    <div class="text-left sm:text-right">
                        <span id="modal-assets" class="text-[9px] sm:text-[10px] text-slate-500 uppercase font-black">Current Assets</span>
                        <div id="modal-total-xp" class="text-emerald-400 font-mono font-bold text-xl sm:text-2xl">
                            <?php echo e(number_format($total_xp)); ?> <span class="text-xs">XP</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-slate-950 border border-slate-700 rounded-3xl p-6 sm:p-8 shadow-inner">
                        <div class="flex flex-col items-center justify-center py-4 sm:py-6">
                            <span class="text-slate-600 text-[9px] sm:text-[10px] uppercase font-black mb-4 tracking-[0.2em]">Transaction Preview</span>
                            <div class="flex items-center gap-4 sm:gap-10">
                                <div class="text-center">
                                    <div id="xp-preview" class="text-2xl sm:text-4xl font-black text-white tracking-tighter">0</div>
                                    <div class="text-[8px] sm:text-[10px] text-indigo-400 uppercase font-black mt-1">XP Deduct</div>
                                </div>
                                <i data-lucide="chevrons-right" class="text-slate-800 w-6 h-6 sm:w-8 sm:h-8"></i>
                                <div class="text-center">
                                    <div id="credit-preview" class="text-2xl sm:text-4xl font-black text-emerald-500 tracking-tighter">0</div>
                                    <div class="text-[8px] sm:text-[10px] text-emerald-400 uppercase font-black mt-1">Credits Add</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5">
                            <button id="confirm-buy-btn" onclick="executeTrade()" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-3 sm:py-4 rounded-2xl uppercase italic tracking-widest transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed group text-xs sm:text-base">
                                <i data-lucide="zap" class="w-4 h-4 sm:w-5 sm:h-5 fill-white group-hover:animate-pulse"></i>
                                Confirm Conversion
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:gap-4">
                        <div class="bg-indigo-500/10 border border-indigo-500/30 rounded-xl p-3 flex items-start gap-3">
                            <i data-lucide="info" class="w-4 h-4 text-indigo-400 mt-0.5 shrink-0"></i>
                            <p class="text-[9px] sm:text-[10px] leading-tight text-slate-400 font-medium">
                                <span class="text-indigo-400 font-black uppercase block mb-0.5">Rank Benefit Detected</span>
                                Pro users receive a <span class="text-white font-bold">20% XP reduction</span>.
                            </p>
                        </div>

                        <button onclick="selectPack(4000, 5, this)" class="pack-button w-full bg-slate-700/50 border border-slate-600 p-4 sm:p-5 rounded-2xl transition-all text-left">
                            <span class="block text-[8px] sm:text-[10px] text-indigo-400 font-black uppercase mb-1">Standard Issue</span>
                            <span class="block text-white font-black text-base sm:text-lg">5 CREDITS</span>
                            <span class="block text-[10px] sm:text-xs text-slate-400">
                                Cost: <?php if($plan_type === 'pro'): ?> <span class="line-through opacity-50">4,000</span> <span class="text-amber-400 font-bold">3,200</span> <?php else: ?> 4,000 <?php endif; ?> XP
                            </span>
                        </button>

                        <button onclick="selectPack(10000, 10, this)" class="pack-button w-full bg-slate-700/50 border-2 border-amber-500/20 p-4 sm:p-5 rounded-2xl transition-all text-left relative">
                            <div class="absolute -top-2.5 right-4 bg-amber-500 text-slate-900 text-[8px] font-black px-2 py-0.5 rounded italic">BONUS +</div>
                            <span class="block text-[8px] sm:text-[10px] text-amber-500 font-black uppercase mb-1">Bulk Supply</span>
                            <span class="block text-white font-black text-base sm:text-lg">10 CREDITS</span>
                            <span class="block text-[10px] sm:text-xs text-slate-400">
                                Cost: <?php if($plan_type === 'pro'): ?> <span class="line-through opacity-50">10,000</span> <span class="text-amber-400 font-bold">8,000</span> <?php else: ?> 10,000 <?php endif; ?> XP
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div id="rank-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" onclick="closeRankModal()"></div>
        <div class="relative w-full max-w-2xl max-h-[90vh] bg-slate-900 border border-indigo-500/30 rounded-[1.5rem] sm:rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col animate-in zoom-in duration-300">
            <div class="bg-indigo-600 p-6 sm:p-8 text-white relative shrink-0">
                <div class="absolute top-0 right-0 p-4 opacity-10 hidden sm:block">
                    <i data-lucide="trophy" class="w-24 h-24 sm:w-32 sm:h-32"></i>
                </div>
                <div class="relative z-10">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl sm:text-3xl font-black uppercase italic tracking-tighter">Season Leaderboard</h3>
                        <button onclick="closeRankModal()" class="text-indigo-200 hover:text-white">
                            <i data-lucide="x" class="w-6 h-6 sm:w-8 sm:h-8"></i>
                        </button>
                    </div>
                    <p class="text-indigo-100 text-[9px] sm:text-xs font-bold uppercase tracking-[0.2em] mt-2">Rewards issued every 30 days</p>

                    <div class="mt-4 flex items-center gap-3 bg-slate-900/30 w-fit px-3 py-1.5 rounded-full border border-white/10 backdrop-blur-sm">
                        <i data-lucide="clock" class="w-3 h-3 text-indigo-300 animate-pulse"></i>
                        <div id="season-clock" class="font-mono text-[10px] sm:text-sm font-bold text-white tracking-wider tabular-nums uppercase">
                            00d 00h 00m 00s
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 overflow-y-auto grow custom-scrollbar">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                    <?php 
                        $prizes = [
                            1 => ['title' => 'Elite Pass + 500 Credits', 'color' => 'amber-400', 'bg' => 'bg-amber-500/10'],
                            2 => ['title' => 'Pro Pass + 200 Credits', 'color' => 'slate-300', 'bg' => 'bg-slate-400/10'],
                            3 => ['title' => '100 Bonus Credits', 'color' => 'orange-400', 'bg' => 'bg-orange-500/10']
                        ];
                    ?>
                    <?php for($i = 0; $i < 3; $i++): ?>
                        <?php if(isset($leaderboard[$i])): ?> 
                            <?php 
                                $leader = $leaderboard[$i];
                                $rank = $i + 1;
                                $prize = $prizes[$rank];
                            ?>
                            <div class="relative p-4 sm:p-5 rounded-3xl border-2 border-<?php echo e($prize['color']); ?>/30 <?php echo e($prize['bg']); ?> flex flex-row sm:flex-col items-center text-left sm:text-center gap-4 sm:gap-0">
                                <div class="absolute -top-3 left-4 sm:left-auto bg-<?php echo e($prize['color']); ?> text-slate-900 text-[9px] sm:text-[10px] font-black px-2 sm:px-3 py-1 rounded-full uppercase italic">
                                    #<?php echo e($rank); ?>

                                </div>
                                <div class="w-12 h-12 sm:w-16 sm:h-16 shrink-0 rounded-full bg-slate-800 border-2 border-<?php echo e($prize['color']); ?> flex items-center justify-center text-lg sm:text-xl font-black text-white sm:mb-3">
                                    <?php echo e(substr($leader->nickname, 0, 2)); ?>

                                </div>
                                <div class="flex-1 sm:w-full overflow-hidden">
                                    <h4 class="text-white font-bold text-xs sm:text-sm truncate"><?php echo e($leader->nickname); ?></h4>
                                    <p class="text-<?php echo e($prize['color']); ?> font-black text-[10px] sm:text-xs"><?php echo e(number_format($leader->total_xp_accumulated)); ?> XP</p>
                                    <div class="hidden sm:block mt-3 pt-3 border-t border-white/10">
                                        <span class="text-[8px] text-slate-400 uppercase block">Reward:</span>
                                        <span class="text-[9px] text-white font-black italic"><?php echo e($prize['title']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <div class="space-y-2 sm:space-y-3">
                    <h5 class="text-[9px] sm:text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2 sm:mb-4">Field Operatives</h5>
                    <?php $__currentLoopData = $leaderboard->slice(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $leader): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php 
                            $actual_rank = $index + 4;
                            $is_me = ($leader->nickname === ($user_data->nickname ?? ''));
                        ?>
                        <div class="flex items-center justify-between p-3 sm:p-4 <?php echo e($is_me ? 'bg-indigo-500/20 border-indigo-500' : 'bg-slate-800/40 border-slate-700'); ?> border rounded-2xl transition-all">
                            <div class="flex items-center gap-3 sm:gap-4 overflow-hidden">
                                <span class="w-5 text-[10px] sm:text-xs font-black text-slate-500">#<?php echo e($actual_rank); ?></span>
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-slate-700 rounded-xl shrink-0 flex items-center justify-center font-bold text-[10px] text-slate-300">
                                    <?php echo e(substr($leader->nickname, 0, 2)); ?>

                                </div>
                                <div class="truncate">
                                    <span class="text-xs sm:text-sm font-bold text-white flex items-center gap-2">
                                        <span class="truncate"><?php echo e($leader->nickname); ?></span>
                                        <?php if($is_me): ?> <span class="text-[7px] bg-indigo-500 text-white px-1.5 py-0.5 rounded-full shrink-0">YOU</span> <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-2">
                                <span class="text-xs sm:text-sm font-black text-indigo-400"><?php echo e(number_format($leader->total_xp_accumulated)); ?></span>
                                <span class="text-[8px] sm:text-[9px] text-slate-600 block font-bold uppercase leading-none">XP</span>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </div>

    
    <div id="avatar-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4">
        <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-xl" onclick="closeAvatarModal()"></div>
        <div class="relative w-full max-w-lg bg-slate-900 border-2 border-indigo-500/30 rounded-[1.5rem] sm:rounded-[2.5rem] shadow-2xl overflow-hidden animate-in zoom-in duration-300">
            <div class="p-6 sm:p-8 border-b border-slate-800 flex justify-between items-center bg-slate-800/50">
                <div>
                    <h3 class="text-xl sm:text-2xl font-black text-white uppercase italic leading-none">Neural Interface</h3>
                    <p class="text-[9px] sm:text-[10px] text-indigo-400 font-bold uppercase tracking-widest mt-1">Select Appearance</p>
                </div>
                <button onclick="closeAvatarModal()" class="text-slate-500 hover:text-white"><i data-lucide="x"></i></button>
            </div>

            <div class="p-6 sm:p-8">
                <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-6 sm:mb-8">
                    <?php $avatar_seeds = ['Buster', 'Midnight', 'Alpha', 'Rex', 'Viper', 'Neon', 'Ghost', 'Tank', 'Zenith']; ?>
                    <?php $__currentLoopData = $avatar_seeds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $av_url = "https://api.dicebear.com/7.x/bottts/svg?seed=$seed&backgroundColor=0f172a,312e81"; ?>
                        <button onclick="saveAvatar('<?php echo e($av_url); ?>')" class="group relative aspect-square rounded-xl sm:rounded-2xl bg-slate-800 border-2 border-slate-700 hover:border-indigo-500 transition-all overflow-hidden">
                            <img src="<?php echo e($av_url); ?>" class="w-full h-full object-cover p-1 sm:p-2 opacity-80 group-hover:opacity-100 transition-opacity">
                            <div class="absolute inset-0 bg-indigo-600/20 opacity-0 group-hover:opacity-100 flex items-center justify-center">
                                <div class="bg-indigo-500 p-1.5 rounded-full shadow-lg">
                                    <i data-lucide="check" class="w-3 h-3 sm:w-4 sm:h-4 text-white"></i>
                                </div>
                            </div>
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <button onclick="closeAvatarModal()" class="w-full py-3 sm:py-4 bg-slate-800 text-slate-400 font-black uppercase tracking-widest rounded-xl hover:bg-slate-700 transition-colors text-[10px] sm:text-xs">
                    Cancel Update
                </button>
            </div>
        </div>
    </div>

    
    <div id="successTradeModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1a1c2c] border border-emerald-500/30 rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
            <div class="w-20 h-20 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-6 border border-emerald-500/50">
                <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-white mb-2">Trade Successful!</h3>
            <p class="text-slate-400 mb-6">Your Neural Credits have been added to your account.</p>
            <button onclick="location.reload()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl transition-all shadow-lg shadow-emerald-900/20">
                Synchronize Data
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      lucide.createIcons();
    </script>

<script>
let selectedXp = 0;
let selectedCredits = 0;

function continueAdventure() {
    window.location.href = "<?php echo e(url('/single-play')); ?>";
}

function openLootModal() {
    const modal = document.getElementById('loot-modal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLootModal() {
    const modal = document.getElementById('loot-modal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLootModal();
});

function selectPack(baseXp, credits, buttonElement) {
    document.querySelectorAll('.pack-button').forEach(btn => {
        btn.classList.remove('border-indigo-500', 'bg-indigo-500/10');
        btn.classList.add('border-slate-600', 'bg-slate-700/50');
    });
    buttonElement.classList.add('border-indigo-500', 'bg-indigo-500/10');
    buttonElement.classList.remove('border-slate-600', 'bg-slate-700/50');

    updatePreview(baseXp, credits);
}

function updatePreview(baseXp, credits) {
    const confirmBtn = document.getElementById('confirm-buy-btn');
    const xpPreviewElement = document.getElementById('xp-preview');
    
    // Check if user is pro based on blade variable
    const isPro = "<?php echo e($plan_type); ?>" === 'pro';
    const discount = isPro ? 0.8 : 1.0;
    
    selectedXp = Math.floor(baseXp * discount);
    selectedCredits = credits;
    
    const userCurrentXp = <?php echo e((int)$total_xp); ?>;
    
    xpPreviewElement.innerText = selectedXp.toLocaleString();
    document.getElementById('credit-preview').innerText = selectedCredits.toLocaleString();

    if (selectedXp > userCurrentXp) {
        xpPreviewElement.classList.replace('text-white', 'text-red-500');
        confirmBtn.disabled = true;
        confirmBtn.className = "w-full bg-red-900/50 border border-red-500 text-red-400 py-4 rounded-2xl uppercase italic font-black flex items-center justify-center gap-3 cursor-not-allowed";
        confirmBtn.innerHTML = `<i data-lucide="alert-triangle" class="w-5 h-5"></i> Insufficient XP`;
    } else {
        xpPreviewElement.classList.replace('text-red-500', 'text-white');
        confirmBtn.disabled = false;
        confirmBtn.className = "w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-4 rounded-2xl uppercase italic tracking-widest shadow-[0_0_20px_rgba(79,70,229,0.4)] transition-all flex items-center justify-center gap-3 group";
        confirmBtn.innerHTML = `<i data-lucide="zap" class="w-5 h-5 fill-white group-hover:animate-pulse"></i> Confirm Conversion`;
    }
    
    if (window.lucide) lucide.createIcons();
}

async function executeTrade() {
    if (selectedXp === 0) return alert("Please select a supply pack first.");
    const confirmBtn = document.getElementById('confirm-buy-btn');
    
    const currentXp = parseInt(document.getElementById('header-total-xp').innerText.replace(/,/g, ''));

    if (selectedXp > currentXp) return alert("Insufficient XP for this conversion.");

    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processing Transfer...';
    if (window.lucide) lucide.createIcons();

    try {
        // Change this route to match your new Laravel route
        const response = await fetch('<?php echo e(url("/games/exchange-xp")); ?>', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                xp_cost: selectedXp,
                credits_awarded: selectedCredits
            })
        });

        if (!response.ok) throw new Error(`Server returned status ${response.status}`);

        const result = await response.json();

        if (result.success) {
            closeLootModal(); 
            const successModal = document.getElementById('successTradeModal');
            if (successModal) successModal.classList.remove('hidden');
        } else {
            alert("Transaction Failed: " + result.message);
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = 'Confirm Conversion';
        }

    } catch (error) {
        console.error('Trade Error:', error);
        alert("Communication Error: Could not reach the server.");
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = 'Confirm Conversion';
    }
}

function openRankModal(event) {
    event.preventDefault();
    event.stopPropagation();
    const modal = document.getElementById('rank-modal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if(window.lucide) lucide.createIcons();
}

function closeRankModal() {
    const modal = document.getElementById('rank-modal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

async function manualSync() {
    const btn = document.getElementById('sync-btn');
    const headerTotalXp = document.getElementById('header-total-xp');
    const soloXpDisplay = document.getElementById('solo-xp-display');
    const arenaXpDisplay = document.getElementById('arena-xp-display');
    const modalTotalXp = document.getElementById('modal-total-xp'); 
    
    btn.classList.add('animate-spin', 'text-indigo-600');
    
    try {
        // Change this route to match your new Laravel route
        const response = await fetch('<?php echo e(url("/games/user-stats")); ?>');
        const data = await response.json();

        if (data && data.current_xp !== undefined) {
            const sXP = parseInt(data.current_xp);
            const aXP = parseInt(data.current_xp_arena);
            const total = sXP + aXP;

            if (soloXpDisplay) animateValue(soloXpDisplay, parseInt(soloXpDisplay.innerText.replace(/,/g, '')), sXP, 1000);
            if (arenaXpDisplay) animateValue(arenaXpDisplay, parseInt(arenaXpDisplay.innerText.replace(/,/g, '')), aXP, 1000);

            if (headerTotalXp) {
                animateValue(headerTotalXp, parseInt(headerTotalXp.innerText.replace(/,/g, '')), total, 1000);
                headerTotalXp.classList.add('text-emerald-500', 'transition-colors');
                setTimeout(() => headerTotalXp.classList.remove('text-emerald-500'), 2000);
            }

            if (modalTotalXp) {
                const oldVal = parseInt(modalTotalXp.innerText.replace(/,/g, '').replace('XP', ''));
                animateValue(modalTotalXp, oldVal, total, 1000, " XP");
            }
        }
    } catch (e) {
        console.error("Sync failed", e);
    } finally {
        setTimeout(() => btn.classList.remove('animate-spin', 'text-indigo-600'), 500);
    }
}

function animateValue(obj, start, end, duration, suffix = "") {
    if (start === end) {
        obj.innerHTML = end.toLocaleString() + (suffix ? `<span class="text-xs">${suffix}</span>` : "");
        return;
    }
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        
        obj.innerHTML = current.toLocaleString() + (suffix ? `<span class="text-xs">${suffix}</span>` : "");
        
        if (progress < 1) window.requestAnimationFrame(step);
    };
    window.requestAnimationFrame(step);
}

function startSeasonCountdown() {
    const now = new Date();
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59);
    
    function updateClock() {
        const distance = endOfMonth.getTime() - new Date().getTime();
        const clockDisplay = document.getElementById("season-clock");
        
        if (clockDisplay) {
            if (distance < 0) {
                clockDisplay.innerHTML = "SEASON ENDED";
            } else {
                const d = String(Math.floor(distance / (1000 * 60 * 60 * 24))).padStart(2, '0');
                const h = String(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                const m = String(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
                const s = String(Math.floor((distance % (1000 * 60)) / 1000)).padStart(2, '0');
                
                clockDisplay.innerHTML = `${d}d ${h}h ${m}m ${s}s`;
            }
        }
    }
    updateClock();
    setInterval(updateClock, 1000);
}

document.addEventListener('DOMContentLoaded', startSeasonCountdown);

function openAvatarModal() {
    document.getElementById('avatar-modal').classList.remove('hidden');
}

function closeAvatarModal() {
    document.getElementById('avatar-modal').classList.add('hidden');
}

async function saveAvatar(url) {
    // 1. Trigger the "Neural Identity" Warning
    const confirmation = await Swal.fire({
        title: 'REASSIGN AVATAR?',
        text: "This will update your profile signature across the entire platform.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'SYNCHRONIZE',
        cancelButtonText: 'ABORT',
        background: '#0f172a',
        color: '#fff',
        confirmButtonColor: '#6366f1', // Indigo
        cancelButtonColor: '#334155',
        reverseButtons: true
    });

    // If user aborts, stop execution
    if (!confirmation.isConfirmed) return;

    // 2. Visual Lockdown (Processing State)
    const modalContainer = document.querySelector('#avatar-modal .relative');
    modalContainer.style.opacity = '0.5';
    modalContainer.style.pointerEvents = 'none';

    try {
        // 3. The Laravel-specific Fetch call
        const response = await fetch('/games/update-avatar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                avatar_url: url
            })
        });

        const data = await response.json();

        if (data.success) {
            // 4. Instant UI Feedback (No reload needed if image exists)
            const headerImg = document.querySelector('.shrink-0 img');
            if (headerImg) {
                headerImg.src = url;
                
                // Success Alert
                await Swal.fire({
                    icon: 'success',
                    title: 'SYNC COMPLETE',
                    text: 'Neural signature has been updated.',
                    timer: 2000,
                    showConfirmButton: false,
                    background: '#0f172a', color: '#fff'
                });
            } else {
                window.location.reload();
            }
            closeAvatarModal();
        } else {
            throw new Error(data.message || "Uplink Rejected");
        }

    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'CONNECTION FAILED',
            text: error.message || "The Neural Link could not be established.",
            background: '#0f172a', color: '#fff'
        });
    } finally {
        // 5. Release Lockdown
        modalContainer.style.opacity = '1';
        modalContainer.style.pointerEvents = 'auto';
    }
}
</script>
</body>
</html><?php /**PATH C:\xampp\htdocs\ieltsaireadinggenerator\billing-service\resources\views/games/games.blade.php ENDPATH**/ ?>