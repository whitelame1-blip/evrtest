<?php

session_start();
require_once '../localhost.php'; 
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
date_default_timezone_set('Asia/Seoul');
$rawToken = $_GET['token'] ?? '';
$isValid = false;
$errorMessage = "";
$secondsRemaining = 0;

if (empty($rawToken)) {
    header("Location: ../index.php");
    exit;
}

// 1. Database Check - Looking specifically in the 'users' table
// 2. IMPORTANT: Hash the URL token so it matches what's in the DB
$tokenHash = hash('sha256', $rawToken);

// 3. Database Check - Search for the HASH, not the raw token
$stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
$stmt->bind_param("s", $tokenHash); // Use the hash here
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $expiresAt = strtotime($user['reset_expires']);
    $currentTime = time();

    if ($currentTime > $expiresAt) {
        $errorMessage = "This reset link has expired.";
    } else {
        $isValid = true;
        $secondsRemaining = $expiresAt - $currentTime;
    }
} else {
    $errorMessage = "This link is invalid or has already been used.";
}

if (!$isValid) {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h2 style='color:#1e293b;'>Access Denied</h2>
            <p style='color:#64748b;'>$errorMessage</p>
            <a href='test_reg.php' style='color:#f97316; font-weight:bold;'>Request a new link</a>
         </div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Evertest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .input-field:focus-within label,
        .input-field input:not(:placeholder-shown) + label {
            transform: translateY(-1.5rem) scale(0.85);
            color: #f97316;
        }
        .input-error {
        border-color: #ef4444 !important;
    }
    
    .shake {
        animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
    }

    @keyframes shake {
        10%, 90% { transform: translate3d(-1px, 0, 0); }
        20%, 80% { transform: translate3d(2px, 0, 0); }
        30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
        40%, 60% { transform: translate3d(4px, 0, 0); }
    }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl shadow-orange-100/50 p-8 md:p-12 transition-all">
            
            <div class="text-center mb-10">
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                    Kolibri <span class="text-orange-500">AI</span>
                </h1>
                <p class="text-slate-500 mt-2 text-sm">Secure your account with a new password</p>
            </div>

            <form id="resetForm" class="space-y-7">
                <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken); ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="relative input-field group">
    <input type="password" id="password" name="new_password" required placeholder=" "
        class="w-full border-b-2 border-slate-200 py-3 pr-10 outline-none focus:border-orange-500 transition-colors bg-transparent">
    <label class="absolute left-0 top-3 text-slate-400 pointer-events-none transition-all origin-left">
        New Password
    </label>
    <button type="button" class="absolute right-0 top-3 text-slate-400 hover:text-orange-500 toggle-password" data-target="password">
        <i class="fa-solid fa-eye-slash"></i>
    </button>
</div>

<div class="relative input-field group">
    <input type="password" id="confirm_password" name="confirm_password" required placeholder=" "
        class="w-full border-b-2 border-slate-200 py-3 pr-10 outline-none focus:border-orange-500 transition-colors bg-transparent">
    <label class="absolute left-0 top-3 text-slate-400 pointer-events-none transition-all origin-left">
        Confirm Password
    </label>
    <button type="button" class="absolute right-0 top-3 text-slate-400 hover:text-orange-500 toggle-password" data-target="confirm_password">
        <i class="fa-solid fa-eye-slash"></i>
    </button>
</div>

                <div class="grid grid-cols-2 gap-2 pt-2">
                    <div data-rule="length" class="text-[10px] uppercase tracking-wider font-bold px-3 py-2 rounded-full bg-slate-100 text-slate-400 transition-all text-center border border-transparent">8+ Characters</div>
                    <div data-rule="upper" class="text-[10px] uppercase tracking-wider font-bold px-3 py-2 rounded-full bg-slate-100 text-slate-400 transition-all text-center border border-transparent">Uppercase</div>
                    <div data-rule="number" class="text-[10px] uppercase tracking-wider font-bold px-3 py-2 rounded-full bg-slate-100 text-slate-400 transition-all text-center border border-transparent">Number</div>
                    <div data-rule="special" class="text-[10px] uppercase tracking-wider font-bold px-3 py-2 rounded-full bg-slate-100 text-slate-400 transition-all text-center border border-transparent">Special</div>
                </div>
<div id="error-msg" class="text-red-500 text-xs font-semibold text-center h-5 transition-opacity opacity-0 mb-2">
    </div>
                <button type="submit" id="submitBtn" 
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-4 rounded-2xl shadow-lg shadow-orange-200 transition-all transform active:scale-[0.98] flex justify-center items-center gap-3">
                    <span id="btnText">Update Password</span>
                    <svg id="btnLoader" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </form>
        </div>
        
        <p class="text-center text-slate-400 text-xs mt-8">
            Need help? Contact <a href="#" class="text-orange-500 hover:underline">Support Team</a>
        </p>
        <div class="text-center mt-6">
            <div id="expiry-timer" class="py-1 px-3 bg-orange-50 text-orange-600 rounded-full text-[11px] font-bold uppercase tracking-widest inline-block border border-orange-100">
                Link expires in: <span id="timer-clock">15:00</span>
            </div>
        </div>
    </div>

    <script src="assets/reset_password.js">

      let secondsLeft = <?= $secondsRemaining; ?>; 
    </script>
    
    
</body>
</html>
