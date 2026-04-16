<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login / Sign Up / Forgot Password</title>
    
    <link rel="stylesheet" href="{{ asset('css/index.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        /* Your existing CSS remains here */
    </style>
</head>
<body>
    @if(session('success'))
    <div class="alert alert-success" style="color: #2ecc71; text-align: center; margin-bottom: 15px;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-error" style="color: #ff4d4d; text-align: center; margin-bottom: 15px;">
        {{ session('error') }}
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info" style="color: #3498db; text-align: center; margin-bottom: 15px;">
        {{ session('info') }}
    </div>
@endif
    <div class="container">
        <div class="left-panel">
            <div class="left-panel-content">
                <button class="panel-button active" id="login-panel-btn">LOGIN</button>
                <button class="panel-button" id="signup-panel-btn">SIGN UP</button>
            </div>
        </div>

        <div class="right-panel">
            <div class="form-section active" id="login-section">
                <div class="logo">
                    <img src="https://via.placeholder.com/60x60/FF6B6B/FFFFFF?text=L" alt="Logo">
                    <div class="logo-text">LOGIN</div>
                </div>
                <form class="login-form" id="login-form">
                    @csrf

                    <div class="input-group" id="email-group">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="input-group" id="password-group">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" name="password" placeholder="Password" id="login-password-input" required>
                        <i class="fas fa-eye-slash password-toggle-icon" data-target="login-password-input"></i> 
                    </div>
                    <div id="login-error-text" class="error-message"></div>
                    <a href="#" class="forgot-password" id="login-forgot-link">Forgot Password?</a>
                    <button type="submit" class="action-button" id="login-submit-btn">LOGIN</button>
                </form>
                
                <div class="or-login-with">
                    <span class="line"></span>
                    <span>Or Login with</span>
                    <span class="line"></span>
                </div>
                <div class="social-login">
                    <a style="text-decoration: none; color: black;" href="{{ url('auth/google') }}" class="social-button google">
                        <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo" width="20">
                        Google
                    </a>
                </div>
            </div>

            <div class="form-section" id="signup-section">
                <div class="logo">
                    <div class="logo-text">SIGN UP</div>
                </div>
                <form class="signup-form" id="signup-form">
                    @csrf

                    <div class="input-group" id="signup-name-group">
                        <i class="fas fa-user icon"></i>
                        <input type="text" name="fullname" placeholder="Full Name" required>
                    </div>
                    <div id="fullname-error" class="error-message"></div>

                    <div class="input-group" id="signup-email-group">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div id="email-error" class="error-message"></div>

                    <div class="input-group" id="signup-pass-group">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" name="password" id="signup-password-input" placeholder="Password" required>
                        <i class="fas fa-eye-slash password-toggle-icon" data-target="signup-password-input"></i>
                    </div>
                    <div id="password-error" class="error-message"></div>

                    <div class="password-requirements" id="password-requirements">
                        <p class="req-item" data-requirement="length"><i class="fas fa-check-circle"></i> At least 8 characters</p>
                        <p class="req-item" data-requirement="uppercase"><i class="fas fa-check-circle"></i> Upper and lower case letters</p>
                        <p class="req-item" data-requirement="symbol"><i class="fas fa-check-circle"></i> At least one symbol</p>
                    </div>

                    <button type="submit" class="action-button">SIGN UP</button>
                </form>
                <div class="or-login-with">
                    <span class="line"></span>
                    <span>Or Sign Up with</span>
                    <span class="line"></span>
                </div>
                <div class="social-login">
                    <a style="text-decoration: none; color: black;" href="{{ url('auth/google') }}" class="social-button google">
                        <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo" width="20">
                        Google
                    </a>
                </div>
            </div>

            <div class="form-section" id="forgot-section">
                <div class="logo">
                    <img src="https://via.placeholder.com/60x60/FF6B6B/FFFFFF?text=L" alt="Logo">
                    <div class="logo-text-forgotpassword ">FORGOT PASSWORD</div>
                </div>
                <form id="forgot-form">
                    @csrf
                    <p style="text-align: center;">Enter your email and we'll send you a link to reset your password.</p>
                    <div class="input-group">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" name="email" id="forgot-email" placeholder="Email" required>
                    </div>
                    <div id="email-error-forgot" style="color: #ff4d4d; font-size: 0.85rem; margin: 5px 0 15px 5px; display: none; text-align: left;"></div>
                    <button type="submit" class="action-button">SEND RESET LINK</button>
                </form>
            </div>
        </div>
    </div>

    <div id="toast-notification" class="toast" style="display: none;">
        <i class="fas fa-envelope"></i> &nbsp; Success! Please check your email.
    </div>

    <script src="{{ asset('js/login.js') }}"></script>
</body>
</html>