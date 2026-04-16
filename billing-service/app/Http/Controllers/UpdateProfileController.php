<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User; 
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerifyEmailCode;
use Carbon\Carbon;
use Hash;

class UpdateProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function updateProfile(Request $request)
    {
        // 1. Validation (Replaces Manual Stage 1)
        $validator = Validator::make($request->all(), [
            'fullname'    => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'avatar_seed' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid Name or Email format.',
                'details' => $validator->errors()
            ], 400);
        }

        $user = Auth::user(); // Laravel handles session and user retrieval
        $newEmail = strtolower(trim($request->email));
        $newName = trim($request->fullname);
        $newSeed = trim($request->avatar_seed);

        // Track if we actually need to save anything
        $isDirty = false;

        // --- STAGE 3: LOGIC GATES ---

        // GATE 1: Email Logic
        if ($newEmail === strtolower($user->email)) {
            // CASE: Email is unchanged. Check if verified.
            if ((int)$user->verified !== 1) {
                return response()->json(['error' => 'Your current email is not verified.'], 403);
            }
        } else {
            // CASE: Email is different. Check email_verifications table.
            $verification = DB::table('email_verifications')
                ->where('user_id', $user->id)
                ->whereRaw('LOWER(new_email) = ?', [$newEmail])
                ->where('is_verified', 1)
                ->first();

            if (!$verification) {
                return response()->json([
                    'error' => "The new email ($newEmail) has not been verified for this account."
                ], 403);
            }

            // Verification confirmed!
            $user->email = $newEmail;
            $user->verified = 1; // Sync verified status
            $isDirty = true;
        }

        // GATE 2: Name Change
        if ($newName !== $user->fullname) {
            $user->fullname = $newName;
            $isDirty = true;
        }

        // GATE 3: Avatar Change
        if ($newSeed && $newSeed !== $user->avatar_seed) {
            $user->avatar_seed = $newSeed;
            $isDirty = true;
        }

        // --- STAGE 4: EXECUTION ---
        if ($isDirty) {
            try {
                $user->save();
                return response()->json(['message' => 'Success']);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Database Error.'], 500);
            }
        }

        return response()->json(['message' => 'No changes made']);
    }


    public function requestCode(Request $request)
    {
        $user = Auth::user();

        // 1. Validation (Handles all Payload Catchers automatically)
        $request->validate([
            'email' => 'required|email|max:255'
        ]);

        $newEmail = strtolower(trim($request->email));

        // 2. Logic Gates
        if ($newEmail === strtolower($user->email)) {
            return response()->json(['error' => 'This is already your current email address.'], 400);
        }

        // Check for existing users with this email
        $exists = \App\Models\User::where('email', $newEmail)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'This email is already in use.'], 400);
        }

        // 3. Generate Code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashedCode = Hash::make($code);

        // 4. Update or Insert into email_verifications
        DB::table('email_verifications')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'new_email' => $newEmail,
                'code_hash' => $hashedCode,
                'expires_at' => Carbon::now()->addMinutes(15),
                'is_verified' => 0,
                'updated_at' => Carbon::now()
            ]
        );

        // 5. Send Notification
        try {
            // We notify the "anonymous" email provided by the user
            Notification::route('mail', $newEmail)->notify(new VerifyEmailCode($code));
            return response()->json(['message' => 'Success']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Mailer Error: ' . $e->getMessage()], 500);
        }
    }

    public function verifyEmailCode(Request $request)
{
    // 1. Validation
    $request->validate([
        'email' => 'required|email',
        'code'  => 'required|string|size:6',
    ]);

    $userId = Auth::id();
    $email = strtolower(trim($request->email));

    // 2. Fetch the record using Query Builder
    $verification = DB::table('email_verifications')
        ->where('user_id', $userId)
        ->where('new_email', $email)
        ->where('expires_at', '>', now())
        ->first();

    // 3. Check if record exists
    if (!$verification) {
        return response()->json(['error' => 'Code expired or not found for this email.'], 400);
    }

    // 4. Check if already verified
    if ($verification->is_verified) {
        return response()->json(['error' => 'This code has already been used.'], 400);
    }

    // 5. Verify Hash
    if (Hash::check($request->code, $verification->code_hash)) {
        
        // Mark as verified in DB
        DB::table('email_verifications')
            ->where('user_id', $userId)
            ->where('new_email', $email)
            ->update([
                'is_verified' => 1,
                'updated_at' => now()
            ]);

        /** * PRO-TIP: Instead of a raw session variable, you could 
         * also check 'is_verified' directly in your profile update logic later.
         **/
        session(['verified_new_email' => $email]);

        return response()->json(['message' => 'Success']);
    }

    return response()->json(['error' => 'Invalid code.'], 400);
}
}