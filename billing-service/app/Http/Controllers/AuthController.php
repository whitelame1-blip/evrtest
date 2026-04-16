<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Input Extraction & Validation (Replaces filter_var and empty checks)
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Rate Limiting (Replaces your login_attempts logic)
        // Laravel uses a "Throttle" system based on the email + IP address
        $throttleKey = strtolower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => 'error', 
                'message' => "Too many attempts. Please wait $seconds seconds."
            ], 429);
        }

        // 3. Attempt Database Operation & Password Verification
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            
            // 4. Account Status Check (Verified)
            if (!Auth::user()->verified) {
                Auth::logout();
                return response()->json(['status' => 'error', 'message' => 'Please verify your email.'], 403);
            }

            // 5. Success! Reset attempts and regenerate session
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);

            return response()->json([
                'status' => 'success', 
                'message' => 'Logged in!',
                'user' => Auth::user()->fullname,
                'redirect_url' => route('dashboard')
            ]);
        }

        // 6. Failed Path
        RateLimiter::hit($throttleKey, 300); // Lock for 5 mins after 5 failures
        
        return response()->json([
            'status' => 'error', 
            'message' => '❌ Incorrect password or email!'
        ], 401);
    }
    public function register(Request $request)
{
    // 1. Validation (The Safety Net)
    $request->validate([
        'fullname' => ['required', 'string', 'min:2', 'max:100'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
    ]);

    // 2. Begin Transaction (Ensures User + Credits are created together)
    DB::beginTransaction();

    try {
        $v_code = Str::random(32);

        // 3. Create the User
        $user = User::create([
            'fullname' => $request->fullname,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'verification_code' => $v_code,
            'verified' => 0,
    // Add these two lines here:
            
        ]);

        // 4. Setup Initial Data (Credits, Notifications, Transactions)
        // Using your specific table structure
        DB::table('credits')->insert([
            'user_id' => $user->id,
            'balance' => 3,
            'game_solo_attempts' => 10,
            'listening_preview_attempts' => 5,
        ]);

        DB::table('user_test_notifications')->insert([
            'user_id' => $user->id
        ]);

        DB::table('credit_transactions')->insert([
            'user_id' => $user->id,
            'change_amount' => 3,
            'reason' => 'Signup Bonus'
        ]);

        // 5. Send Verification Email
        $verification_link = url("/verify?email=" . urlencode($user->email) . "&token=$v_code");

        Mail::send([], [], function ($message) use ($user, $verification_link) {
            $message->to($user->email)
                ->subject('Verify your email - evrtest.com')
                ->html("
                    <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
                        <h2 style='color: #333;'>Welcome to Kolibri AI, {$user->fullname}!</h2>
                        <p>Please click the button below to verify your email address and claim your 3 free credits:</p>
                        <a href='$verification_link' style='display:inline-block; background:#2ecc71; color:#fff; padding:12px 25px; border-radius:5px; text-decoration:none; font-weight:bold;'>Verify My Account</a>
                        <p style='font-size: 12px; color: #777; margin-top: 20px;'>If you did not create this account, please ignore this email.</p>
                    </div>
                ");
        });

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Verification email sent!'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

// Inside app/Http/Controllers/AuthController.php

public function verify(Request $request)
{
    $email = $request->query('email');
    $token = $request->query('token');

    // Default configuration
    $config = [
        'type' => 'info',
        'message' => 'Processing your request...',
        'iconClass' => 'fa-info-circle',
        'themeColor' => '#3498db',
        'title' => 'Note'
    ];

    if (!$email || !$token) {
        $config = [
            'type' => 'error',
            'message' => '⚠️ Missing verification details.',
            'iconClass' => 'fa-times-circle',
            'themeColor' => '#e74c3c',
            'title' => 'Oops!'
        ];
    } else {
        $user = User::where('email', $email)->where('verification_code', $token)->first();

        if ($user) {
            if ($user->verified) {
                $config = [
                    'type' => 'info',
                    'message' => 'Your email is already verified. You can proceed to login.',
                    'iconClass' => 'fa-info-circle',
                    'themeColor' => '#3498db',
                    'title' => 'Already Verified'
                ];
            } else {
                $user->verified = 1;
                $user->verification_code = null;
                $user->save();

                $config = [
                    'type' => 'success',
                    'message' => '✅ Email verified successfully! Welcome to Kolibri AI.',
                    'iconClass' => 'fa-check-circle',
                    'themeColor' => '#2ecc71',
                    'title' => 'Success!'
                ];
            }
        } else {
            $config = [
                'type' => 'error',
                'message' => '❌ Invalid or expired verification link.',
                'iconClass' => 'fa-times-circle',
                'themeColor' => '#e74c3c',
                'title' => 'Invalid Link'
            ];
        }
    }

    // Return the view with the config array
    return view('auth.message', $config);
}

public function forgotPassword(Request $request)
{
    // 1. Basic Validation
    $request->validate([
        'email' => 'required|email',
    ]);

    $email = $request->email;
    $genericMessage = 'If an account with that email exists and is verified, we have sent reset instructions. (Valid for 15 minutes)';

    // 2. Check if user exists and is verified
    $user = User::where('email', $email)->first();

    if (!$user || $user->verified == 0) {
        return response()->json(['status' => 'success', 'message' => $genericMessage]);
    }

    // 3. Generate Secure Token & Hash
    $resetToken = Str::random(64);
    $tokenHash = hash('sha256', $resetToken);
    $expiresAt = Carbon::now()->addMinutes(15);

    // 4. Update User Table
    // Ensure you have these columns in your users table!
    $user->update([
        'reset_token' => $tokenHash,
        'reset_expires' => $expiresAt
    ]);

    // 5. Send the Email
    $resetLink = url("/reset-password?token=" . urlencode($resetToken));

    try {
        Mail::send([], [], function ($message) use ($user, $resetLink) {
            $message->to($user->email)
                ->subject('Reset your password - Evrtest.com')
                ->html("
                    <div style='background-color: #f4f7f6; padding: 50px 0; font-family: Arial, sans-serif; color: #333;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                            <div style='background: #2c3e50; padding: 20px; text-align: center;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px;'>Evrtest</h1>
                            </div>
                            <div style='padding: 40px; line-height: 1.6;'>
                                <h2 style='color: #2c3e50; margin-top: 0;'>Password Reset Request</h2>
                                <p>Hello, <strong>{$user->fullname}</strong>,</p>
                                <p>We received a request to reset the password for your account. No changes have been made yet.</p>
                                <div style='text-align: center; margin: 35px 0;'>
                                    <a href='$resetLink' style='background-color: #2ecc71; color: #ffffff; padding: 14px 28px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 16px; display: inline-block;'>Reset My Password</a>
                                </div>
                                <p style='font-size: 14px; color: #e74c3c; text-align: center;'><strong>Note: This link will expire in 15 minutes.</strong></p>
                                <hr style='border: none; border-top: 1px solid #eeeeee; margin: 30px 0;'>
                                <p style='font-size: 13px; color: #7f8c8d;'>If you did not request this reset, please ignore this email. Your account remains secure.</p>
                            </div>
                        </div>
                    </div>
                ");
        });

        return response()->json(['status' => 'success', 'message' => $genericMessage]);

    } catch (\Exception $e) {
        // In a real project, you'd log $e->getMessage()
        return response()->json(['status' => 'error', 'message' => 'Could not send reset email. Please try again later.'], 500);
    }
}
public function showResetForm(Request $request) {
    $token = $request->query('token');
    if (empty($token)) {
        return abort(403, 'Access Denied: No token provided.');
    }

    $tokenHash = hash('sha256', $token);
    $user = User::where('reset_token', $tokenHash)->first();

    if (!$user || Carbon::parse($user->reset_expires)->isPast()) {
        return response()->view('auth.message', [
            'title' => 'Link Invalid or Expired',
            'message' => 'Please request a new password reset link.',
            'themeColor' => '#e74c3c',
            'iconClass' => 'fa-times-circle'
        ]);
    }

    $secondsRemaining = (int) floor(Carbon::now()->diffInSeconds(Carbon::parse($user->reset_expires), false));

    return view('auth.reset_password', [
        'rawToken' => $token,
        'secondsRemaining' => $secondsRemaining
    ]);
}

// 2. Handle the Update
public function updatePassword(Request $request) {
    $request->validate([
        'new_password' => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/',
        'confirm_password' => 'required|same:new_password',
        'token' => 'required'
    ]);

    $tokenHash = hash('sha256', $request->token);
    $user = User::where('reset_token', $tokenHash)->first();

    if (!$user || Carbon::parse($user->reset_expires)->isPast()) {
        return response()->json(['status' => 'error', 'message' => 'This link is invalid or expired.'], 400);
    }

    // Update password and clear reset fields
    $user->update([
        'password' => Hash::make($request->new_password),
        'reset_token' => null,
        'reset_expires' => null
    ]);

    return response()->json([
        'status' => 'success', 
        'message' => 'Your password has been updated successfully.'
    ]);
}
};