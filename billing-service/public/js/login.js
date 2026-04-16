document.addEventListener('DOMContentLoaded', () => {
    // UI elements
    const panels = {
        login: document.getElementById('login-section'),
        signup: document.getElementById('signup-section'),
        forgot: document.getElementById('forgot-section')
    };
    const btns = {
        login: document.getElementById('login-panel-btn'),
        signup: document.getElementById('signup-panel-btn')
    };

    const loginForm = document.getElementById('login-form');
    const errorDisplay = document.getElementById('login-error-text');
    const emailGroup = document.getElementById('email-group');
    const passwordGroup = document.getElementById('password-group');
    const submitBtnlogin = document.getElementById('login-submit-btn');

    let lastAttemptTime = 0;
    let countdownTimer = null;

    // --- 1. UI SWITCHING LOGIC ---
    function showSection(sectionKey) {
        Object.values(panels).forEach(s => s.classList.remove('active'));
        Object.values(btns).forEach(b => b?.classList.remove('active'));
        panels[sectionKey].classList.add('active');
        if (btns[sectionKey]) btns[sectionKey].classList.add('active');
    }

    btns.login.onclick = () => showSection('login');
    btns.signup.onclick = () => showSection('signup');
    document.getElementById('login-forgot-link').onclick = (e) => {
        e.preventDefault();
        showSection('forgot');
    };

    // --- 2. LOGIN LOGIC ---
    // --- 2. LOGIN LOGIC ---
    loginForm.onsubmit = async function(e) {
        e.preventDefault();

        // Prevent spam clicking or clicks during lockout
        if (Date.now() - lastAttemptTime < 2000 || countdownTimer) return;

        // Reset UI
        errorDisplay.style.display = 'none';
        emailGroup.classList.remove('error-border');
        passwordGroup.classList.remove('error-border');

        const formData = new FormData(this);
        const email = formData.get('email').trim();
        const password = formData.get('password');

        // Client-side Validation
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            showError("Please enter a valid email address.");
            return;
        }
        if (password.length < 8) {
            showError("Password must be at least 8 characters long.");
            return;
        }

        // START LOADING STATE
        submitBtnlogin.textContent = 'PLEASE WAIT...';
        submitBtnlogin.disabled = true;
        lastAttemptTime = Date.now();

        try {
    // 1. Point to the Laravel route URL
    const response = await fetch('/login', { 
        method: 'POST',
        body: formData,
       headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        // GRAB THE TOKEN FROM THE META TAG
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
    });

    const result = await response.json();

    if (result.status === 'success') {
        // 2. Redirect the user to the Arena or Dashboard
        window.location.href = result.redirect_url || '/dashboard'; 
    } else {
                // SHOW ERROR AND RESET BUTTON
                showError(result.message);
                
                // If the message contains a number (seconds), start countdown
                const timeMatch = result.message.match(/\d+/);
                if (timeMatch) {
                    startCountdown(parseInt(timeMatch[0]));
                } else {
                    // RESET BUTTON if there is no lockout timer
                    submitBtnlogin.disabled = false;
                    submitBtnlogin.textContent = 'LOGIN';
                }
            }
        } catch (error) {
            console.error('Login Error:', error);
            showError("Connection error. Check your server.");
            // RESET BUTTON ON CRASH
            submitBtnlogin.disabled = false;
            submitBtnlogin.textContent = 'LOGIN';
        }
    };
    function startCountdown(seconds) {
        submitBtnlogin.disabled = true;
        clearInterval(countdownTimer);

        countdownTimer = setInterval(() => {
            if (seconds <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                submitBtnlogin.disabled = false;
                submitBtnlogin.textContent = 'LOGIN';
            } else {
                submitBtnlogin.textContent = `WAIT (${seconds}s)`;
                seconds--;
            }
        }, 1000);
    }

    function showError(message) {
        errorDisplay.textContent = message;
        errorDisplay.style.display = 'block';
        emailGroup.classList.add('error-border');
        passwordGroup.classList.add('error-border');
    }

document.querySelectorAll('.password-toggle-icon').forEach(icon => {
    icon.addEventListener('click', function() {
        // Get the target input ID from the data-target attribute
        const targetId = this.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);

        if (passwordInput.type === 'password') {
            // Switch to text to show password
            passwordInput.type = 'text';
            // Change icon to eye
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        } else {
            // Switch back to password to hide it
            passwordInput.type = 'password';
            // Change icon back to eye-slash
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        }
    });
});

const signupForm = document.getElementById('signup-form');
    const passwordInput = document.getElementById('signup-password-input');
    const submitBtnsignup = document.getElementById('signup-submit-btn');

    // Requirement items for visual feedback
    const reqItems = {
        length: document.querySelector('[data-requirement="length"]'),
        uppercase: document.querySelector('[data-requirement="uppercase"]'),
        symbol: document.querySelector('[data-requirement="symbol"]')
    };

    const signupPasswordInput = document.getElementById('signup-password-input');
    const passwordRequirements = document.getElementById('password-requirements');

// Show when user clicks/focuses
    signupPasswordInput.addEventListener('focus', () => {
        passwordRequirements.classList.add('visible');
    });

// Optional: Hide when user clicks away IF the password is empty
    signupPasswordInput.addEventListener('blur', () => {
        if (signupPasswordInput.value === "") {
            passwordRequirements.classList.remove('visible');
        }
    });

    // Live Validation (Green Checks)
    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        val.length >= 8 ? reqItems.length.classList.add('valid') : reqItems.length.classList.remove('valid');
        (/[a-z]/.test(val) && /[A-Z]/.test(val)) ? reqItems.uppercase.classList.add('valid') : reqItems.uppercase.classList.remove('valid');
        (/[!@#$%^&*(),.?":{}|<>]/.test(val)) ? reqItems.symbol.classList.add('valid') : reqItems.symbol.classList.remove('valid');
    });

    // Form Submission
    signupForm.onsubmit = async function(e) {
    e.preventDefault();

    // Elements
    const nameGroup = document.getElementById('signup-name-group');
    const nameError = document.getElementById('fullname-error');
    const emailGroup = document.getElementById('signup-email-group');
    const emailError = document.getElementById('email-error');
    const passGroup = document.getElementById('signup-pass-group');
    const passError = document.getElementById('password-error');

    // Reset UI before validating
    [nameGroup, emailGroup, passGroup].forEach(g => g.classList.remove('error-border'));
    [nameError, emailError, passError].forEach(e => { e.textContent = ''; e.style.display = 'none'; });

    const formData = new FormData(this);
    const fullname = formData.get('fullname').trim();
    const email = formData.get('email').trim();
    const password = formData.get('password');

    let hasError = false;

    // 1. Full Name Validation
    if (fullname.length < 2 || fullname.length > 100) {
        nameGroup.classList.add('error-border');
        nameError.textContent = "Full name must be 2-100 characters.";
        nameError.style.display = 'block';
        hasError = true;
    }

    // 2. Email Validation
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        emailGroup.classList.add('error-border');
        emailError.textContent = "Please enter a valid email address.";
        emailError.style.display = 'block';
        hasError = true;
    }

    // 3. Password Validation
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasSymbol = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    if (password.length < 8 || !hasUpper || !hasLower || !hasSymbol) {
        passGroup.classList.add('error-border');
        passError.textContent = "Password does not meet requirements.";
        passError.style.display = 'block';
        hasError = true;
    }

    // BLOCK THE REQUEST if any errors exist
    if (hasError) return;

    // --- Proceed to Fetch if all filters pass ---
    const submitBtnsignup = this.querySelector('button[type="submit"]');
    submitBtnsignup.textContent = 'CREATING ACCOUNT...';
    submitBtnsignup.disabled = true;

    try {
       const response = await fetch('/register', {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
});

        const data = await response.json();

        if (data.status === 'success') {
    const toast = document.getElementById('toast-notification');
    toast.style.display = 'block';
    
    // Move back to login immediately
    showSection('login');

    // Hide the toast after 4 seconds
    setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}else {
            // Handle backend errors (like "Email already exists")
           const msg = data.message.toLowerCase();

    // 1. Handle Email Errors (Duplicate or Invalid)
    if (msg.includes("email")) {
        emailGroup.classList.add('error-border');
        emailError.textContent = data.message;
        emailError.style.display = 'block';
        emailGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } 
    // 2. Handle Password Errors (Too weak or Incorrect)
    else if (msg.includes("password")) {
        passGroup.classList.add('error-border');
        passError.textContent = data.message;
        passError.style.display = 'block';
        passGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    // 3. Handle Full Name Errors
    else if (msg.includes("name")) {
        nameGroup.classList.add('error-border');
        nameError.textContent = data.message;
        nameError.style.display = 'block';
    }else {
                alert(data.message);
            }
        }
     } catch (error) {
        console.error('Parsing Error:', error); // This will show the PHP Notice in the console
        alert("Server error. Check the console for details.");
    } finally {
        submitBtnsignup.textContent = 'SIGN UP';
        submitBtnsignup.disabled = false;
    }
};


// --- 3. FORGOT PASSWORD LOGIC ---
    const forgotForm = document.getElementById('forgot-form');
    const toast = document.getElementById('toast-notification');

    forgotForm.onsubmit = async function(e) {
        e.preventDefault();

        const submitBtnforgot = this.querySelector('button');
    const originalText = submitBtnforgot.textContent;
    
    // Select elements specific to the forgot section
    const emailInput = document.getElementById('forgot-email');
    const errorDisplay = document.getElementById('email-error-forgot'); // FIXED ID HERE

    // Reset UI on new attempt
    emailInput.style.borderColor = '';
    errorDisplay.style.display = 'none';
    errorDisplay.innerText = '';
    
    submitBtnforgot.textContent = 'SENDING...';
    submitBtnforgot.disabled = true;

    const formData = new FormData(this);

        try {
            
            const response = await fetch('/forgot-password', {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
});

            const data = await response.json();

            if (data.status === 'success') {
                // Show success toast
                toast.innerHTML = '<i class="fas fa-paper-plane"></i> &nbsp; Reset link sent! Check your inbox.';
                toast.style.background = '#3498db'; // Info blue
                toast.style.display = 'block';

                // Go back to login after 3 seconds
                setTimeout(() => {
                    toast.style.display = 'none';
                    showSection('login');
                }, 3000);
            } else {
            // 1. Show the red text below the input
            errorDisplay.innerText = data.message;
            errorDisplay.style.display = 'block';

            // 2. Make the input border red
            emailInput.style.borderColor = '#ff4d4d'; // Using your CSS red
            emailInput.focus();
        }
    } catch (error) {
        console.error('Forgot Password Error:', error);
        errorDisplay.innerText = "Connection error. Please try again.";
        errorDisplay.style.display = 'block';
    } finally {
        submitBtnforgot.textContent = originalText;
        submitBtnforgot.disabled = false;
    }
    };

}); // Fixed: Properly closed the DOMContentLoaded listener