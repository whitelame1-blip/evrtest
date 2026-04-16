<div class="min-h-screen py-12 px-4 flex items-center justify-center font-sans text-slate-200">
    
    <div class="fixed inset-0 overflow-hidden -z-10">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-indigo-500/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/10 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-5xl">
        <div class="grid lg:grid-cols-2 gap-0 overflow-hidden bg-slate-900/60 backdrop-blur-3xl border border-white/10 rounded-[2.5rem] shadow-2xl">
            
            <div class="p-8 lg:p-12 border-b lg:border-b-0 lg:border-r border-white/5">
                <header class="mb-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 mb-4">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-400">Live Credit Forge</span>
                    </div>
                    <h1 class="text-4xl font-black text-white leading-tight">Customize Your <br><span class="text-indigo-400">Energy Pack.</span></h1>
                </header>

                <div class="space-y-10">
                    <div class="relative group p-10 bg-slate-950/40 rounded-[2rem] border border-white/5 flex flex-col items-center justify-center text-center transition-all hover:border-indigo-500/30">
                        <div class="absolute inset-0 bg-gradient-to-b from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-[2rem]"></div>
                        
                        <div class="relative">
                            <span id="display-amount" class="text-7xl font-black text-white tracking-tighter transition-all">2,500</span>
                            <p class="text-indigo-400 font-bold tracking-[0.3em] uppercase text-xs mt-2">Available Credits</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between text-xs font-bold text-slate-500 uppercase tracking-widest px-1">
                            <span>Entry</span>
                            <span id="tier-label" class="text-indigo-400">Professional Choice</span>
                            <span>Elite</span>
                        </div>
                        <input type="range" id="main-range" min="500" max="10000" step="500" value="2500" 
                            class="custom-slider w-full h-2 bg-slate-800 rounded-full appearance-none cursor-pointer outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white/5 rounded-2xl p-4 border border-white/5 hover:bg-white/10 transition-colors">
                            <p class="text-[10px] text-slate-500 font-bold uppercase mb-1">Estimated Sessions</p>
                            <p id="stat-sessions" class="text-xl font-bold text-white">~50 Tests</p>
                        </div>
                        <div class="bg-white/5 rounded-2xl p-4 border border-white/5 hover:bg-white/10 transition-colors">
                            <p class="text-[10px] text-slate-500 font-bold uppercase mb-1">Processing Priority</p>
                            <p id="stat-priority" class="text-xl font-bold text-white">High</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8 lg:p-12 bg-indigo-600/5 flex flex-col justify-center">
                <div class="bg-slate-950/50 backdrop-blur-md rounded-3xl p-8 border border-white/10 shadow-inner">
                    <h2 class="text-xl font-bold text-white mb-8 flex items-center gap-2">
                        <i data-lucide="shopping-bag" class="h-5 w-5 text-indigo-400"></i>
                        Checkout Summary
                    </h2>

                    <div class="space-y-6 mb-10">
                        <div class="flex justify-between items-center group">
                            <span class="text-slate-400 group-hover:text-white transition-colors">Credit Quantity</span>
                            <span id="summary-qty" class="text-white font-mono font-bold text-lg">2,500</span>
                        </div>
                        <div class="flex justify-between items-center group">
                            <span class="text-slate-400 group-hover:text-white transition-colors">Volume Discount</span>
                            <span id="summary-discount" class="text-green-400 font-bold text-sm bg-green-400/10 px-2 py-1 rounded-md">-15%</span>
                        </div>
                        <div class="h-px bg-white/10 w-full"></div>
                        <div class="flex justify-between items-end">
                            <div>
                                <p class="text-xs font-bold text-slate-500 uppercase">Total Investment</p>
                                <p class="text-4xl font-black text-white tracking-tight">$<span id="total-price">37.50</span></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-slate-500 font-bold line-through">$45.00</p>
                                <p class="text-[10px] text-indigo-400 font-bold uppercase tracking-tighter">Instant Credit</p>
                            </div>
                        </div>
                    </div>

                    <form action="process_credits.php" method="POST" class="space-y-4">
                        <input type="hidden" name="credit_count" id="hidden-credits" value="2500">
                        <button type="submit" class="w-full py-5 bg-indigo-500 hover:bg-indigo-400 text-white rounded-2xl font-black text-sm uppercase tracking-[0.2em] shadow-lg shadow-indigo-500/20 transition-all active:scale-[0.98] flex items-center justify-center gap-3">
                            Confirm and Pay
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </button>
                    </form>

                    <div class="mt-8 flex items-center justify-center gap-6 opacity-40 grayscale group-hover:grayscale-0 transition-all">
                        <i data-lucide="shield-check" class="h-5 w-5"></i>
                        <i data-lucide="fingerprint" class="h-5 w-5"></i>
                        <i data-lucide="credit-card" class="h-5 w-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Slider Styling */
.custom-slider::-webkit-slider-runnable-track {
    background: linear-gradient(to right, #6366f1 var(--range-progress), #1e293b var(--range-progress));
    border-radius: 99px;
    height: 8px;
}

.custom-slider::-webkit-slider-thumb {
    appearance: none;
    height: 24px;
    width: 24px;
    background: #ffffff;
    border-radius: 50%;
    border: 4px solid #6366f1;
    margin-top: -8px;
    box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
    transition: all 0.2s ease;
}

.custom-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 0 25px rgba(99, 102, 241, 0.6);
}
</style>

<script>
const range = document.getElementById('main-range');
const display = document.getElementById('display-amount');
const summaryQty = document.getElementById('summary-qty');
const totalPrice = document.getElementById('total-price');
const tierLabel = document.getElementById('tier-label');
const hiddenInput = document.getElementById('hidden-credits');
const statSessions = document.getElementById('stat-sessions');

function updateCalculator() {
    const val = parseInt(range.value);
    
    // Logic: Price per credit drops as quantity increases
    let unitPrice = 0.02;
    let discount = "0%";
    let tier = "Starter";

    if (val >= 2500) { 
        unitPrice = 0.015; 
        discount = "-25%"; 
        tier = "Professional";
    }
    if (val >= 7500) { 
        unitPrice = 0.012; 
        discount = "-40%"; 
        tier = "Elite Level";
    }

    const total = (val * unitPrice).toFixed(2);
    const progress = (val - range.min) / (range.max - range.min) * 100;

    // UI Updates
    range.style.setProperty('--range-progress', `${progress}%`);
    display.innerText = val.toLocaleString();
    summaryQty.innerText = val.toLocaleString();
    totalPrice.innerText = total;
    tierLabel.innerText = tier;
    hiddenInput.value = val;
    statSessions.innerText = `~${Math.floor(val / 50)} Tests`;
    document.getElementById('summary-discount').innerText = discount;
}

range.addEventListener('input', updateCalculator);
window.onload = updateCalculator;
</script>