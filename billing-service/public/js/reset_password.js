   // Initialize countdown from PHP variable


function updateTimer() {
    if (secondsLeft <= 0) {
        document.getElementById('timer-clock').textContent = "00:00";
        // Optional: Disable the button if expired
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').classList.add('opacity-50', 'cursor-not-allowed');
        return;
    }

    // Format seconds into MM:SS
    const minutes = Math.floor(secondsLeft / 60);
    const seconds = Math.floor(secondsLeft % 60);
    
    // padStart adds the leading zero (e.g., 05 instead of 5)
    const displayTime = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    document.getElementById('timer-clock').textContent = displayTime;
    
    secondsLeft--;
}

// Run once immediately, then every second
updateTimer();
setInterval(updateTimer, 1000);

const countdownInterval = setInterval(updateTimer, 1000);
updateTimer();
        const resetForm = document.getElementById('resetForm');
        const passInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoader = document.getElementById('btnLoader');

        // 1. Password Visibility Toggle
        // 1. Updated Password Visibility Toggle Logic
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                // Show the "open eye" because the password is now visible
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                // Show the "slashed eye" because the password is now hidden
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        });
    });

        // 2. Real-time Password Rules Validation
        passInput.addEventListener('input', () => {
            const val = passInput.value;
            const rules = {
                length: val.length >= 8,
                upper: /[A-Z]/.test(val),
                number: /[0-9]/.test(val),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(val)
            };

            Object.keys(rules).forEach(rule => {
                const el = document.querySelector(`[data-rule="${rule}"]`);
                if (rules[rule]) {
                    el.classList.remove('bg-slate-100', 'text-slate-400', 'border-transparent');
                    el.classList.add('bg-orange-50', 'text-orange-600', 'border-orange-200');
                } else {
                    el.classList.add('bg-slate-100', 'text-slate-400', 'border-transparent');
                    el.classList.remove('bg-orange-50', 'text-orange-600', 'border-orange-200');
                }
            });
        });

        // 3. Async POST Request
        const errorMsg = document.getElementById('error-msg');

// Helper to show errors without alert()
const showError = (message, targetInput) => {
    errorMsg.innerText = message;
    errorMsg.classList.replace('opacity-0', 'opacity-100');
    
    if (targetInput) {
        targetInput.classList.add('input-error', 'shake');
        targetInput.focus();
    }
};

// Helper to clear errors
const clearErrors = () => {
    errorMsg.classList.replace('opacity-100', 'opacity-0');
    passInput.classList.remove('input-error', 'shake');
    confirmInput.classList.remove('input-error', 'shake');
};

resetForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();

    const password = passInput.value;
    const confirm = confirmInput.value;

    // 1. Validation Logic
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    if (!hasLength || !hasUpper || !hasNumber || !hasSpecial) {
        showError("Please meet all security requirements", passInput);
        return;
    }

    if (password !== confirm) {
        showError("Passwords do not match", confirmInput);
        return;
    }

    // UI State: Loading
    submitBtn.disabled = true;
    btnText.innerText = "Verifying...";
    btnLoader.classList.remove('hidden');
    const formData = new FormData(resetForm); // This gathers the token and password
    try {
        const response = await fetch(window.updateUrl, { // Using the route from Blade
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            // Laravel needs the CSRF token in the header for AJAX if not in FormData
        }
         });

        const result = await response.json();

        if (result.status === 'success') {
            window.location.href = window.successUrl;
        } else {
            showError(result.message || "An error occurred", null);
            resetUI();
        }

    } catch (error) {
        showError("Could not connect to the server", null);
        resetUI();
    }
});

// Clear red marks when user types
passInput.addEventListener('input', clearErrors);
confirmInput.addEventListener('input', clearErrors);

        function resetUI() {
            submitBtn.disabled = false;
            btnText.innerText = "Update Password";
            btnLoader.classList.add('hidden');
        }