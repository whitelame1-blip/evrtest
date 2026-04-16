<style>
    #saveProfileBtn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        filter: grayscale(1);
        transform: none !important;
    }
</style>

<div class="min-h-screen bg-slate-50 p-4 md:p-8 lg:p-12 font-sans text-slate-900">
    <div class="max-w-7xl mx-auto space-y-10">
        
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">Dashboard</h1>
                <p class="text-slate-500 font-medium">Welcome back, {{ explode(' ', $user->fullname)[0] }}! Here's your latest progress.</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="p-3 bg-white border border-slate-200 rounded-2xl shadow-sm hover:bg-slate-50 transition-colors relative">
                    <i data-lucide="bell" class="w-5 h-5 text-slate-600"></i>
                    <span class="absolute top-2.5 right-2.5 w-2.5 h-2.5 bg-rose-500 border-2 border-white rounded-full"></span>
                </button>
                <div class="h-10 w-[1px] bg-slate-200 mx-2 hidden md:block"></div>
                <div class="flex items-center gap-3 bg-white p-1.5 pr-4 border border-slate-200 rounded-2xl shadow-sm">
                    <div class="w-8 h-8 bg-indigo-600 rounded-xl flex items-center justify-center text-white font-bold text-xs">
                        {{ $userInitials }}
                    </div>
                    <span class="text-sm font-bold text-slate-700 hidden sm:inline">{{ $user->fullname }}</span>
                </div>
            </div>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <div class="group bg-white p-1 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-xl transition-all duration-500">
                <div class="p-6 rounded-[2.2rem] bg-gradient-to-br from-white to-indigo-50/30 h-full">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-indigo-600 rounded-2xl text-white shadow-lg">
                            <i data-lucide="book-open" class="w-6 h-6"></i>
                        </div>
                        @if ($reading['count'] >= 5)
                            <span class="flex items-center gap-1 {{ $reading['trend'] >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50' }} px-2.5 py-1 rounded-full text-xs font-black">
                                <i data-lucide="{{ $reading['trend'] >= 0 ? 'trending-up' : 'trending-down' }}" class="w-3 h-3"></i> 
                                {{ abs($reading['trend']) }}%
                            </span>
                        @endif
                    </div>
                    
                    <div class="text-4xl font-black tracking-tighter text-slate-900">{{ $reading['count'] }}</div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Reading Modules</p>
                    
                    @if ($reading['count'] < 5)
                        <div class="mt-4">
                            <div class="flex justify-between text-[10px] font-bold text-slate-400 mb-1 uppercase">
                                <span>Trend Unlock</span>
                                <span>{{ $reading['count'] }}/5</span>
                            </div>
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-indigo-400 h-full transition-all" style="width: {{ ($reading['count']/5)*100 }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="group bg-white p-1 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-xl transition-all duration-500">
                <div class="p-6 rounded-[2.2rem] bg-gradient-to-br from-white to-purple-50/30 h-full">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-purple-600 rounded-2xl text-white shadow-lg">
                            <i data-lucide="headphones" class="w-6 h-6"></i>
                        </div>
                        @if ($listening['count'] >= 5)
                            <span class="flex items-center gap-1 {{ $listening['trend'] >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50' }} px-2.5 py-1 rounded-full text-xs font-black">
                                <i data-lucide="{{ $listening['trend'] >= 0 ? 'trending-up' : 'trending-down' }}" class="w-3 h-3"></i> 
                                +{{ abs($listening['trend']) }}%
                            </span>
                        @endif
                    </div>
                    <div class="text-4xl font-black tracking-tighter text-slate-900">{{ $listening['count'] }}</div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Listening Tasks</p>
                    @if ($listening['count'] < 5)
                        <div class="mt-4">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-purple-400 h-full" style="width: {{ ($listening['count']/5)*100 }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="group bg-slate-900 p-6 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/20 rounded-full blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10 h-full flex flex-col justify-between">
                    <div class="p-3 w-fit bg-white/10 backdrop-blur-md rounded-2xl text-white border border-white/10">
                        <i data-lucide="zap" class="w-6 h-6 text-indigo-400"></i>
                    </div>
                    <div>
                        <div class="text-4xl font-black tracking-tighter text-white">
                            {{ $reading['avg'] }}<span class="text-indigo-400 font-light">%</span>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Reading Avg</p>
                    </div>
                </div>
            </div>

            <div class="group bg-white p-6 rounded-[2.5rem] border-2 border-dashed border-slate-200 flex flex-col justify-center items-center text-center">
                <div class="text-4xl font-black tracking-tighter text-emerald-600">
                    {{ $listening['avg'] }}<span class="text-slate-300 font-light">%</span>
                </div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Listening Avg</p>
                <div class="mt-4 flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-full border border-slate-100">
                    <i data-lucide="award" class="w-3 h-3 text-amber-500"></i>
                    <span class="text-[10px] font-black text-slate-600 uppercase">Verified Level</span>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-[3rem] border border-slate-100 shadow-sm overflow-hidden">
            <div class="h-48 bg-gradient-to-r from-indigo-600 via-violet-600 to-purple-600 relative">
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_50%_120%,rgba(255,255,255,1),transparent)]"></div>
            </div>
            
            <div class="px-6 md:px-10 pb-10">
                <div class="relative flex flex-col md:flex-row items-center md:items-end gap-6 -mt-20">
                    <div class="relative group">
                        <div class="w-40 h-40 bg-white rounded-[2.5rem] p-2 shadow-2xl transition-transform duration-500 group-hover:scale-105">
                            <div class="w-full h-full bg-slate-100 rounded-[2rem] flex items-center justify-center overflow-hidden">
                                <img src="{{ $avatarUrl }}" alt="Avatar" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex-1 text-center md:text-left mb-2">
                        <div class="flex flex-col md:flex-row items-center gap-3 mb-2">
                            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ $user->fullname }}</h2>
                            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-[10px] font-black uppercase tracking-widest border border-indigo-100">
                                {{ $user->plan_type ?? 'Free Plan' }}
                            </span>
                        </div>
                        <div class="flex flex-wrap justify-center md:justify-start gap-4">
                            <div class="flex items-center gap-1.5 text-slate-500 font-semibold text-sm">
                                <i data-lucide="mail" class="w-4 h-4"></i> {{ $user->email }}
                            </div>
                            <div class="flex items-center gap-1.5 text-emerald-600 font-bold text-sm">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> Verified Candidate
                            </div>
                        </div>
                    </div>
                    
                    <button id="openModalBtnprofile" class="w-full md:w-auto px-8 py-4 bg-slate-900 text-white rounded-[1.5rem] font-bold hover:bg-indigo-600 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="settings-2" class="w-5 h-5"></i> Edit Profile
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-[100000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-[3rem] w-full max-w-md overflow-hidden shadow-2xl transform transition-all relative" id="modalContent">
        
        <div class="relative h-24 bg-gradient-to-r from-indigo-600 to-violet-600">
            <button id="closeModalBtnprofile" type="button" class="absolute top-4 right-4 bg-white/20 hover:bg-white/40 text-white p-2 rounded-full transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="px-8 pb-8">
            <form id="profileForm" class="relative">
                @csrf
                <input type="hidden" name="avatar_seed" id="selectedAvatarSeed" value="{{ $user->avatar_seed ?? $user->fullname }}">
                
                <div class="flex flex-col items-center -mt-12 mb-6">
                    <div class="relative group cursor-pointer" id="triggerAvatarPicker">
                        <div class="w-28 h-28 bg-white p-1.5 rounded-[2rem] shadow-xl ring-4 ring-white">
                            <div class="w-full h-full bg-slate-100 rounded-[1.7rem] overflow-hidden">
                                <img id="profilePreview" src="{{ $avatarUrl }}" alt="Avatar" class="w-full h-full object-cover">
                            </div>
                        </div>
                        <div class="absolute inset-1.5 flex items-center justify-center bg-slate-900/40 rounded-[1.7rem] opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-white text-[10px] font-black uppercase tracking-tighter">Change</span>
                        </div>
                    </div>
                    <h3 class="mt-4 text-xl font-black text-slate-900">Edit Profile</h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 mb-1.5 ml-1">Full Name</label>
                        <input type="text" name="fullname" value="{{ $user->fullname }}" required maxlength="50"
                               class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition-all font-bold text-slate-700">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 mb-1.5 ml-1">Enter new email Address</label>
                        <div class="relative flex items-center">
                            <input type="email" id="userEmail" name="email" value="{{ $user->email }}" required maxlength="100"
                                   class="w-full px-5 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition-all font-bold text-slate-700 pr-32">
                            <button type="button" id="sendEmailCodeBtn" class="absolute right-2 px-4 py-2 bg-indigo-100 text-indigo-700 rounded-xl text-[10px] font-black uppercase hover:bg-indigo-200 transition-all">
                                Send Code
                            </button>
                        </div>
                    </div>
                    <div id="verificationSection" class="hidden animate-in fade-in slide-in-from-top-2 duration-300">
    <div class="flex justify-between items-center mb-1.5 ml-1">
        <label class="block text-[10px] font-black uppercase tracking-[0.15em] text-rose-500">
            6-Digit Verification Code
        </label>
        <div class="flex items-center gap-2">
            <span id="verifyStatus" class="text-[9px] font-bold text-slate-400 uppercase"></span>
            <span id="timerDisplay" class="text-[10px] font-black text-rose-500 bg-rose-50 px-2 py-0.5 rounded-md hidden">15:00</span>
        </div>
    </div>

    <div class="flex gap-2">
        <input type="text" name="email_code" id="email_code" placeholder="000000" maxlength="6"
               class="flex-1 px-5 py-3.5 bg-rose-50 border border-rose-100 rounded-2xl focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 focus:outline-none transition-all font-black text-center tracking-[1em] text-slate-700">
        <button type="button" id="verifyCodeBtn" class="px-6 py-3.5 bg-rose-500 text-white rounded-2xl text-[10px] font-black uppercase hover:bg-rose-600 transition-all shadow-lg shadow-rose-200">
            Verify
        </button>
    </div>
</div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-8">
                    <button type="button" onclick="closeProfileModal()" class="py-4 bg-slate-100 text-slate-600 rounded-2xl font-black hover:bg-slate-200 transition-all">Cancel</button>
                    <button type="submit" id="saveProfileBtn" class="py-4 bg-indigo-600 text-white rounded-2xl font-black hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all active:scale-95">Save</button>
                </div>
            </form>
            
            <div id="avatarPicker" class="absolute inset-0 bg-white z-20 translate-y-full transition-transform duration-300 p-8 flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="text-xl font-black text-slate-900">Choose Avatar</h4>
                    <button type="button" id="closePicker" class="text-slate-400 hover:text-slate-900">
                        <i data-lucide="arrow-left" class="w-6 h-6"></i>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-4 overflow-y-auto pr-2 pb-4" id="avatarGrid">
                    </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // 1. GLOBAL STATE & MASTER VALIDATION
    // ==========================================
   const originalData = {
    fullname: @json($user->fullname).trim(),
    email: @json(strtolower($user->email)).trim(),
    seed: @json($user->avatar_seed ?? $user->fullname).trim()
};

    let isEmailVerified = false;
    let countdownInterval;

    // Master function to check if we should enable the Save button
    function updateSaveButtonState() {
        const saveBtn = document.getElementById('saveProfileBtn');
        const currentName = document.querySelector('input[name="fullname"]').value.trim();
        const currentEmail = document.getElementById('userEmail').value.trim().toLowerCase();
        const currentSeed = document.getElementById('selectedAvatarSeed').value.trim();

        // Condition A: Name or Avatar changed
        const infoChanged = (currentName !== originalData.fullname) || (currentSeed !== originalData.seed);

        // Condition B: Email changed AND verified
        const emailChanged = (currentEmail !== originalData.email);
        
        if (emailChanged) {
            saveBtn.disabled = !isEmailVerified;
        } else {
            saveBtn.disabled = !infoChanged;
        }
    }

    // Disable button on initial load
    document.getElementById('saveProfileBtn').disabled = true;

    // Listen for manual typing changes
    document.querySelector('input[name="fullname"]').addEventListener('input', updateSaveButtonState);
    document.getElementById('userEmail').addEventListener('input', () => {
        isEmailVerified = false; // Reset verification if they type a new email
        updateSaveButtonState();
    });


    // ==========================================
    // 2. EMAIL VERIFICATION FLOW
    // ==========================================
    
    // Timer Function
    function startTimer(duration) {
        const timerDisplay = document.getElementById('timerDisplay');
        const verifyBtn = document.getElementById('verifyCodeBtn');
        const sendBtn = document.getElementById('sendEmailCodeBtn');
        const statusText = document.getElementById('verifyStatus');

        clearInterval(countdownInterval);
        timerDisplay.classList.remove('hidden');
        
        let timer = duration, minutes, seconds;
        
        countdownInterval = setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            timerDisplay.textContent = minutes + ":" + seconds;

            if (--timer < 0) {
                clearInterval(countdownInterval);
                verifyBtn.disabled = true;
                verifyBtn.classList.replace('bg-rose-500', 'bg-slate-400');
                
                sendBtn.disabled = false;
                sendBtn.innerHTML = 'Resend Code';
                sendBtn.classList.replace('bg-emerald-100', 'bg-indigo-100');
                sendBtn.classList.replace('text-emerald-700', 'text-indigo-700');
                
                statusText.innerText = "Code expired. Please resend.";
                timerDisplay.classList.add('hidden');
            }
        }, 1000);
    }

    // Send Code Button
    document.getElementById('sendEmailCodeBtn').addEventListener('click', async function() {
    const emailInput = document.getElementById('userEmail');
    const newEmail = emailInput.value.trim().toLowerCase();
    const btn = this;
    const verifyBtn = document.getElementById('verifyCodeBtn');
    const statusText = document.getElementById('verifyStatus');

    const strictEmailRegex = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,10}$/;

    if (!strictEmailRegex.test(newEmail)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Email',
            text: 'Please enter a complete email address.',
            target: document.getElementById('editModal')
        });
        return;
    }
    
    // originalData.email should be the email currently in your database
    if (newEmail === originalData.email) {
        return Swal.fire({
            icon: 'warning',
            title: 'This is already your current email.',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
            target: document.getElementById('editModal'),
            didOpen: (toast) => { toast.style.zIndex = '100001'; }
        });
    }

    btn.disabled = true;
    btn.innerHTML = 'Sending...';

    try {
        // 1. Get the CSRF token from the meta tag
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // 2. Point to the new Laravel route (match web.php)
        const response = await fetch('/profile/request-email-code', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json', // 3. Tell Laravel we want JSON back
                'X-CSRF-TOKEN': token        // 4. Add the security token
            },
            body: JSON.stringify({ email: newEmail }) // 5. Send as JSON string
        });

        // 6. Parse as JSON instead of text
        const result = await response.json();

        if (response.ok) {
            clearInterval(countdownInterval); 
            document.getElementById('timerDisplay').classList.add('hidden');
            document.getElementById('verificationSection').classList.remove('hidden');
            document.getElementById('email_code').focus();
            
            Swal.fire({
                icon: 'success', title: 'Code sent!',
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                target: document.getElementById('editModal'),
                didOpen: (toast) => { toast.style.zIndex = '100001'; }
            });

            verifyBtn.disabled = false;
            verifyBtn.classList.replace('bg-slate-400', 'bg-rose-500');
            btn.innerHTML = 'Sent';
            statusText.innerText = "Code is valid for:";
            
            startTimer(15 * 60); // Updated to 15 mins to match Laravel logic

        } else {
            // Handle Laravel validation or logic errors
            throw new Error(result.error || result.message || "Failed to send code");
        }
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = 'Send Code';
        Swal.fire({
            icon: 'error', 
            title: 'Error',
            text: error.message,
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
            target: document.getElementById('editModal'),
            didOpen: (toast) => { toast.style.zIndex = '100001'; }
        });
    }
});
    // Verify Code Button
    document.getElementById('verifyCodeBtn').addEventListener('click', async function() {
    const codeInput = document.getElementById('email_code');
    const code = codeInput.value.trim();
    const email = document.getElementById('userEmail').value.trim();
    const statusText = document.getElementById('verifyStatus');
    const btn = this;

    if (code.length !== 6) {
        codeInput.classList.add('ring-4', 'ring-rose-500/20', 'border-rose-500', 'animate-shake');
        setTimeout(() => codeInput.classList.remove('animate-shake'), 500);
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '...';

    try {
        // 1. Get CSRF Token from the meta tag in your dashboard.blade.php
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // 2. Use the Laravel Route URL
        const response = await fetch('/profile/verify-email-code', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', // Send as JSON
                'Accept': 'application/json',       // Expect JSON back
                'X-CSRF-TOKEN': token               // Required for Laravel security
            },
            body: JSON.stringify({ email: email, code: code })
        });

        // 3. Parse result as JSON
        const result = await response.json();

        if (response.ok) {
            // SUCCESS LOGIC: Unlock the save button!
            isEmailVerified = true; 
            updateSaveButtonState(); 

            if (statusText) {
                statusText.innerText = "✓ Email Verified Successfully";
                statusText.classList.replace('text-slate-400', 'text-emerald-500');
            }
            
            codeInput.classList.replace('bg-rose-50', 'bg-emerald-50');
            codeInput.classList.replace('border-rose-100', 'border-emerald-200');
            codeInput.classList.replace('text-slate-700', 'text-emerald-700');
            codeInput.readOnly = true;
            document.getElementById('userEmail').readOnly = true;

            btn.classList.replace('bg-rose-500', 'bg-emerald-500');
            btn.classList.replace('shadow-rose-200', 'shadow-emerald-200');
            btn.innerHTML = '✓';

            if (typeof countdownInterval !== 'undefined') {
                clearInterval(countdownInterval);
                document.getElementById('timerDisplay').classList.add('hidden');
            }
        } else {
            // Handle Laravel error messages (like 'Invalid code' or 'Code expired')
            throw new Error(result.error || "Verification failed");
        }
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = 'Verify';

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message,
            target: document.getElementById('editModal'), 
            didOpen: (popup) => {
                popup.style.zIndex = '100001';
            }
        });
    }
});


    // ==========================================
    // 3. AVATAR PICKER SYSTEM
    // ==========================================
    const seeds = [
        'Felix', 'Aneka', 'Mario', 'Sophie', 'Bear', 'Luna', 'Max', 'Bella', 
        'Alexander', 'Scarlett', 'Sebastian', 'Grace', 'William', 'Victoria',
        'Buster', 'Cookie', 'Gizmo', 'Peanut', 'Zorro', 'Simba', 'Shadow',
        'Joker', 'Zenith', 'Nova', 'Titan', 'Astro', 'Pixel', 'Vibe', 'Flow',
        'Hiroshi', 'Amara', 'Mateo', 'Yuki', 'Chen', 'Sasha', 'Malik'
    ];
    
    const colors = ['bbf7d0', 'fecaca', 'fed7aa', 'fef08a', 'd9f99d', 'bfdbfe', 'c7d2fe', 'ddd6fe', 'f5d0fe', 'fbcfe8'];
    const grid = document.getElementById('avatarGrid');
    const picker = document.getElementById('avatarPicker');
    const seedInput = document.getElementById('selectedAvatarSeed');
    const preview = document.getElementById('profilePreview');

    seeds.forEach((seed, index) => {
        const randomBg = colors[index % colors.length];
        const div = document.createElement('div');
        
        div.className = "aspect-square rounded-[1.5rem] bg-slate-50 p-2 cursor-pointer hover:scale-105 hover:ring-4 hover:ring-indigo-500/20 transition-all border border-slate-100 flex items-center justify-center group";
        const avatarUrl = `https://api.dicebear.com/7.x/avataaars/svg?seed=${seed}&backgroundColor=${randomBg}`;
        
        div.innerHTML = `<img src="${avatarUrl}" class="w-full h-full object-contain drop-shadow-sm">`;
        div.onclick = () => selectAvatar(seed, randomBg);
        grid.appendChild(div);
    });

    function selectAvatar(seed, bg) {
        const valueToSave = `${seed}&backgroundColor=${bg}`;
        const finalUrl = `https://api.dicebear.com/7.x/avataaars/svg?seed=${valueToSave}`;
        
        seedInput.value = valueToSave; 
        preview.src = finalUrl;
        picker.classList.add('translate-y-full');
        
        updateSaveButtonState(); // Check if save button should unlock
    }

    document.getElementById('triggerAvatarPicker').onclick = () => picker.classList.remove('translate-y-full');
    document.getElementById('closePicker').onclick = () => picker.classList.add('translate-y-full');


    // ==========================================
    // 4. MODAL CONTROLS & FORM SUBMISSION
    // ==========================================
    const modal = document.getElementById('editModal');

    document.addEventListener('click', (e) => {
        if (e.target.closest('#openModalBtnprofile')) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
        }
        if (e.target.closest('#closeModalBtnprofile') || e.target === modal) {
            closeProfileModal();
        }
    });

    window.closeProfileModal = () => {
        modal.style.display = 'none';
        modal.classList.add('hidden');
        picker.classList.add('translate-y-full');
    };

   document.getElementById('profileForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('saveProfileBtn');
    const originalText = btn.innerHTML;
    const nameValue = this.querySelector('input[name="fullname"]').value.trim();
    const emailValue = document.getElementById('userEmail').value.trim();

    // Basic frontend validation
    if (nameValue.length > 50) return Swal.fire('Error', 'Name is too long (Max 50 characters)', 'error');
    if (emailValue.length > 100) return Swal.fire('Error', 'Email is too long (Max 100 characters)', 'error');

    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    try {
        // 1. Get the CSRF Token from the meta tag in your dashboard.blade.php
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // 2. Prepare FormData
        const formData = new FormData(this);

        // 3. Update the URL and add Headers for CSRF and JSON response
        const response = await fetch('/profile/update', { 
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json' // Tells Laravel to return JSON even if validation fails
            },
            body: formData
        });

        // 4. Parse the result as JSON
        const result = await response.json();
        
        if (response.ok) {
            closeProfileModal();
            Swal.fire({
                title: 'Success!',
                text: 'Profile updated successfully.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            }).then(() => {
                location.reload(); 
            });
        } else {
            // Laravel returns validation errors in a specific format
            // If the controller has $request->validate(), the errors are in result.errors
            const errorMessage = result.message || result.error || 'Update failed';
            throw new Error(errorMessage);
        }
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = originalText;
        Swal.fire({ 
            title: 'Error', 
            text: error.message, 
            icon: 'error',
            target: document.getElementById('editModal') // Keep it on top of the modal
        });
    }
});

}); // End DOMContentLoaded
</script>