<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoogleAuthController extends Controller
{
    // Redirect to Google
    public function redirect() {
        return Socialite::driver('google')->redirect();
    }

    // Handle Callback
    public function callback() {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // 1. Systems Check: Find user by email
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                // 2. Insert New User (Laravel style)
                $user = User::create([
                    'fullname' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'password' => bcrypt(str()->random(24)), // Random pass for security
                    'verified' => 1,
                ]);

                // 3. Give Starting Credits (Your Logic)
                DB::table('credits')->insert([
                    'user_id' => $user->id,
                    'balance' => 5,
                    'created_at' => now(),
                ]);
            } else {
                // 4. Existing User: Update Google ID if it's missing
                $user->update([
                    'google_id' => $googleUser->id,
                    'verified' => 1
                ]);
            }

            // 5. Log them in (Replaces your manual $_SESSION code)
            Auth::login($user);

            return redirect('/dashboard');

        } catch (\Exception $e) {
            return redirect('/index')->with('error', 'Something went wrong.');
        }
    }
}