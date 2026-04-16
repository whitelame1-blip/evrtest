<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;
class GameController extends Controller
{
    public function generateGame(Request $request)
    {
        $attempts = DB::table('credits')->where('user_id', Auth::id())->value('game_solo_attempts');
        if ($attempts < 1) return response()->json(['error' => 'No solo attempts left'], 403);

        $payload = [
            'user_id' => Auth::id(),
            'difficulty' => $request->input('difficulty', 'easy'),
            'level' => $request->integer('level', 1),
            'num_questions' => $request->integer('num_questions', 5),
            'focus' => strip_tags($request->input('focus', 'general'))
        ];

        $response = Http::withHeaders(['X-Internal-Key' => config('services.flask.key')])
            ->post(config('services.flask.base_url') . '/generatesinglegame-test', $payload);

        return $response->json();
    }
public function exchangeXp(Request $request)
{
    $userId = Auth::id();
    $xpCost = (int) $request->input('xp_cost');
    $creditsToAdd = (int) $request->input('credits_awarded');

    try {
        // Use a transaction to ensure both operations succeed or both fail
        DB::transaction(function () use ($userId, $xpCost, $creditsToAdd) {
            
            // 1. Get current XP with a "Lock for Update" to prevent race conditions
            $gameUser = DB::table('game_users')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$gameUser || $gameUser->current_xp < $xpCost) {
                throw new \Exception("Insufficient XP balance.");
            }

            // 2. Deduct XP
            DB::table('game_users')
                ->where('user_id', $userId)
                ->decrement('current_xp', $xpCost);

            // 3. Update or Insert Credits (Upsert logic)
            // updateOrInsert is cleaner than checking row counts manually
            DB::table('credits')->updateOrInsert(
                ['user_id' => $userId],
                ['balance' => DB::raw("balance + $creditsToAdd")]
            );
        });

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false, 
            'message' => $e->getMessage()
        ], 400);
    }
}

public function getUserStats()
{
    // Auth::id() automatically handles the session check
    $userId = Auth::id();

    if (!$userId) {
        return response()->json(['error' => 'Not logged in'], 401);
    }

    $stats = DB::table('game_users')
        ->where('user_id', $userId)
        ->select('current_xp', 'current_xp_arena', 'total_xp_accumulated')
        ->first();

    // If no record exists, return default zeros
    if (!$stats) {
        return response()->json([
            'current_xp' => 0,
            'current_xp_arena' => 0,
            'total_xp_accumulated' => 0
        ]);
    }

    return response()->json($stats);
}


public function singlePlayDashboard()
    {
        $userId = Auth::id();

        // 1. Check for Active Missions
        $activeMission = DB::table('game_missions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
        
        $activeMissionId = $activeMission ? $activeMission->mission_id : 0;

        // 2. Profile Setup
        $planType = Auth::user()->plan_type ?? 'free';
        $totalAvailable = ($planType === 'pro') ? 15 : 5;
        
        $profile = DB::table('game_users')->where('user_id', $userId)->first();
        $profileExists = !is_null($profile);

        // Default user template if not registered
        $user = $profile ? (array)$profile : ['nickname' => 'New Player', 'current_level' => 1, 'total_xp_solo' => 0];
        $userAvatar = $profile->avatar_url ?? null;
        
        $currentLvl = (int)($user['current_level'] > 0 ? $user['current_level'] : 1);
        $xpMaxForCurrentLevel = $currentLvl * 500;
        $progression = ((float)$user['total_xp_solo'] / $xpMaxForCurrentLevel) * 100;

        // 3. Credits & Timer Logic
        $credits = DB::table('credits')->where('user_id', $userId)->first();
        $userCredits = $credits ? (int)$credits->balance : 0;
        $remaining = $credits ? (int)$credits->game_solo_attempts : 0;
        $uplinkResetAt = $credits ? $credits->uplink_reset_at : null;

        $limitReached = false;
        $resetTimestamp = 0;

        if ($remaining <= 0 && $profileExists) {
            if (!$uplinkResetAt) {
                $uplinkResetAt = Carbon::now()->addHours(12);
                DB::table('credits')->where('user_id', $userId)->update(['uplink_reset_at' => $uplinkResetAt]);
            }

            if (Carbon::now()->greaterThanOrEqualTo($uplinkResetAt)) {
                // Timer finished, reward attempts
                DB::table('credits')->where('user_id', $userId)->update([
                    'game_solo_attempts' => $totalAvailable,
                    'uplink_reset_at' => null
                ]);
                $remaining = $totalAvailable;
            } else {
                $limitReached = true;
                $resetTimestamp = Carbon::parse($uplinkResetAt)->timestamp;
            }
        } elseif ($uplinkResetAt && $remaining > 0) {
            DB::table('credits')->where('user_id', $userId)->update(['uplink_reset_at' => null]);
        }

        return view('games.single_play', compact(
            'activeMissionId', 'profileExists', 'user', 'userAvatar', 
            'planType', 'totalAvailable', 'progression', 'userCredits', 
            'remaining', 'limitReached', 'resetTimestamp'
        ));
    }

    /**
     * Handle Nickname Registration
     */
    public function storeNickname(Request $request)
    {
        $request->validate([
            'nickname' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[a-zA-Z0-9 ]+$/']
        ]);

        DB::table('game_users')->insert([
            'user_id' => Auth::id(),
            'nickname' => trim($request->nickname),
            'current_level' => 1,
            'current_xp' => 0,
            'total_xp_solo' => 0,
            'streak_count' => 300,
            'user_rank_title' => 'Novice'
        ]);

        return redirect()->route('games.single_play');
    }

    /**
     * Handle the Active Game Session
     */
    public function playGame(Request $request)
    {
        $missionId = $request->input('mission_id');
        $difficultyLevel = $request->input('diff', 'medium');
        $userId = Auth::id();

        if (!$missionId) abort(400, "Uplink Failure: Critical Data missing.");

        // Security Gate
        $mission = DB::table('game_missions')->where('mission_id', $missionId)->first();

        if (!$mission || $mission->user_id !== $userId) {
            return redirect()->route('games.single_play')->with('error', 'unauthorized');
        }

        if ($mission->status === 'completed') {
            return redirect()->route('games.single_play');
        }

        // Fetch Questions
        $questions = DB::table('mission_questions')
            ->where('mission_id', $missionId)
            ->orderBy('question_id', 'asc')
            ->get();

        if ($questions->isEmpty()) {
            abort(500, "Error: Mission data corrupted or empty.");
        }

        $timeMapping = ['easy' => 60, 'medium' => 45, 'hard' => 30];
        $secondsPerQuestion = $timeMapping[$difficultyLevel] ?? 45;
        $totalQuestions = $questions->count();

        // Return view with No-Cache Headers (Laravel style)
        return response()
            ->view('games.single_play_game', compact(
                'questions', 'missionId', 'difficultyLevel', 
                'totalQuestions', 'secondsPerQuestion'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY');
    }



    private function getRankTitle($level)
    {
        if ($level >= 100) return 'Grandmaster';
        if ($level >= 75)  return 'Elite Operator';
        if ($level >= 50)  return 'Master';
        if ($level >= 30)  return 'Veteran';
        if ($level >= 15)  return 'Specialist';
        if ($level >= 5)   return 'Scout';
        return 'Novice';
    }

    /**
     * Process the mission submission (POST)
     */
    public function submitMission(Request $request, $mission_id)
    {
        $userId = Auth::id();

        // 1. Fetch Mission & User Data
        $data = DB::table('game_missions')
            ->join('game_users', 'game_missions.user_id', '=', 'game_users.user_id')
            ->where('game_missions.mission_id', $mission_id)
            ->where('game_missions.user_id', $userId)
            ->select('game_missions.*', 'game_users.current_xp', 'game_users.current_level', 'game_users.total_xp_solo', 'game_users.user_rank_title')
            ->first();

        if (!$data) abort(404, "Mission not found or unauthorized.");
        if ($data->status === 'completed') return redirect()->route('mission.debrief', $mission_id);

        // 2. Process Answers
        $userAnswersRaw = $request->input('answers', '');
        $userAnswers = explode(',', $userAnswersRaw);
        
        $questions = DB::table('mission_questions')->where('mission_id', $mission_id)->orderBy('question_id', 'asc')->get();
        
        $calculatedScore = 0;
        $totalQ = 0;

        foreach ($questions as $index => $q) {
            $uChoice = strtolower(trim($userAnswers[$index] ?? 'none'));
            if ($uChoice === strtolower(trim($q->correct_option))) {
                $calculatedScore++;
            }
            $totalQ++;
        }

        // 3. XP & Difficulty Logic
        $difficultyText = strtolower($data->difficulty ?? 'easy');
        $multiplier = match($difficultyText) {
            'hard' => 2.0,
            'medium' => 1.5,
            default => 1.0,
        };

        $accuracy = ($totalQ > 0) ? ($calculatedScore / $totalQ) : 0;
        $finalXpGain = (int)round(($data->xp_reward * $multiplier) * $accuracy);

        // 4. Level-up Logic
        $newTotalXpSolo = (int)$data->total_xp_solo + $finalXpGain;
        $newLifetimeXp = (int)$data->current_xp + $finalXpGain;
        $currentLevel = (int)$data->current_level;
        $bonusAttempts = 0;
        
        while (true) {
            $xpNeeded = $currentLevel * 500;
            if ($newTotalXpSolo >= $xpNeeded) {
                $newTotalXpSolo -= $xpNeeded;
                $currentLevel++;
                $bonusAttempts += 5;
            } else { 
                break; 
            }
        }
        
        $newTitle = $this->getRankTitle($currentLevel);

        // 5. Database Transaction
        DB::transaction(function () use ($userId, $mission_id, $newLifetimeXp, $newTotalXpSolo, $currentLevel, $newTitle, $finalXpGain, $bonusAttempts, $calculatedScore, $userAnswersRaw) {
            
            DB::table('game_users')->where('user_id', $userId)->update([
                'current_xp' => $newLifetimeXp,
                'total_xp_solo' => $newTotalXpSolo,
                'current_level' => $currentLevel,
                'user_rank_title' => $newTitle,
                'total_xp_accumulated' => DB::raw("total_xp_accumulated + $finalXpGain")
            ]);

            if ($bonusAttempts > 0) {
                DB::table('credits')->where('user_id', $userId)->increment('game_solo_attempts', $bonusAttempts);
                session()->flash('leveled_up', true);
                session()->flash('bonus_attempts', $bonusAttempts);
            }

            DB::table('game_missions')->where('mission_id', $mission_id)->update([
                'status' => 'completed',
                'score' => $calculatedScore,
                'last_answers' => $userAnswersRaw,
                'completed_at' => now()
            ]);
        });

        // Flash completion so debrief knows to show animations/multipliers
        session()->flash('rewards_issued', true);
        session()->flash('final_xp_gain', $finalXpGain);
        session()->flash('multiplier', $multiplier);

        return redirect()->route('mission.debrief', $mission_id);
    }

    /**
     * Display the Mission Debrief (GET)
     */
    public function showDebrief($mission_id)
    {
        $userId = Auth::id();

        $data = DB::table('game_missions')
            ->join('game_users', 'game_missions.user_id', '=', 'game_users.user_id')
            ->where('game_missions.mission_id', $mission_id)
            ->where('game_missions.user_id', $userId)
            ->first();

        if (!$data) abort(404, "Mission not found.");

        $questions = DB::table('mission_questions')->where('mission_id', $mission_id)->orderBy('question_id', 'asc')->get();
        $userAnswers = explode(',', $data->last_answers ?? '');
        
        $total = count($questions);
        $score = (int)$data->score;
        $wrongCount = max(0, $total - $score);

        // Retrieve flashed session variables (fallbacks if page refreshed)
        $rewardsIssued = session('rewards_issued', false);
        $leveledUp = session('leveled_up', false);
        $bonusAttempts = session('bonus_attempts', 0);
        $multiplier = session('multiplier', 1.0);
        
        // If refreshed, calculate XP gain just for display purposes
        if (!$rewardsIssued) {
            $difficultyText = strtolower($data->difficulty ?? 'easy');
            $multiplier = match($difficultyText) { 'hard' => 2.0, 'medium' => 1.5, default => 1.0 };
            $accuracy = ($total > 0) ? ($score / $total) : 0;
            $finalXpGain = (int)round(($data->xp_reward * $multiplier) * $accuracy);
        } else {
            $finalXpGain = session('final_xp_gain', 0);
        }

        return view('games.mission_complete', [
            'data' => $data,
            'questions' => $questions,
            'userAnswers' => $userAnswers,
            'score' => $score,
            'wrongCount' => $wrongCount,
            'rewardsIssued' => $rewardsIssued,
            'leveledUp' => $leveledUp,
            'bonusAttempts' => $bonusAttempts,
            'multiplier' => $multiplier,
            'finalXpGain' => $finalXpGain,
            'currentLevel' => $data->current_level,
            'newTitle' => $data->user_rank_title,
        ]);
    }
   public function exchange(Request $request)
    {
        // 1. Validation (Replaces your manual if/intval checks)
        $request->validate([
            'credits' => 'required|integer|min:1',
            'attempts' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();
        $creditsToSpend = $request->input('credits');
        $attemptsToGrant = $request->input('attempts');

        try {
            // 2. Execute Transaction
            $result = DB::transaction(function () use ($userId, $creditsToSpend, $attemptsToGrant) {
                
                // A. Check Balance with Row Level Locking (FOR UPDATE)
                $wallet = DB::table('credits')
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$wallet || $wallet->balance < $creditsToSpend) {
                    throw new Exception("Insufficient credits balance.");
                }

                // B. Deduct Credits
                DB::table('credits')
                    ->where('user_id', $userId)
                    ->decrement('balance', $creditsToSpend);

                // C. Grant Attempts
                DB::table('credits')
                    ->where('user_id', $userId)
                    ->increment('game_solo_attempts', $attemptsToGrant);

                return [
                    'success' => true,
                    'new_balance' => $wallet->balance - $creditsToSpend
                ];
            });

            // 3. Success Response
            return response()->json([
                'success' => true,
                'message' => 'Transaction Complete',
                'new_balance' => $result['new_balance']
            ]);

        } catch (Exception $e) {
            // Laravel DB::transaction automatically rolled back if Exception was thrown
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); // 400 for business logic errors
        }
    }


    public function abort(Request $request, $id)
    {
        // 1. Ownership & Existence Check
        $mission = DB::table('game_missions')
            ->where('mission_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$mission) {
            return response()->json([
                'success' => false, 
                'message' => 'Unauthorized: Mission not found or access denied.'
            ], 403);
        }

        // 2. Start Transaction to ensure data integrity
        return DB::transaction(function () use ($id) {
            try {
                // A. Delete the associated questions first
                // This cleans up your database and saves space on your 160GB SSD
                DB::table('mission_questions')
                    ->where('mission_id', $id)
                    ->delete();

                // B. Update the mission status to failed
                DB::table('game_missions')
                    ->where('mission_id', $id)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Mission scuttled: Questions purged and status updated.'
                ]);

            } catch (\Exception $e) {
                // If anything fails, Laravel automatically rolls back the transaction
                return response()->json([
        'success' => false,
        'error'   => $e->getMessage(), // Returns the actual PHP/MySQL error
    ], 500);
            }
        });
    }


    public function updateAvatar(Request $request)
    {
        // 1. Validation (Ensures avatar_url is present and looks like a URL)
        $request->validate([
            'avatar_url' => 'required|string',
        ]);

        try {
            $userId = Auth::id(); // Replaces $_SESSION['user_id']
            $avatarUrl = $request->input('avatar_url');

            // 2. Execute Update
            // Using DB::table is faster if you haven't built a User Model yet
            $updated = DB::table('game_users')
                ->where('user_id', $userId)
                ->update(['avatar_url' => $avatarUrl]);

            if ($updated === 0) {
                // This happens if the user record doesn't exist or the URL is identical
                return response()->json([
                    'success' => true,
                    'message' => 'No changes made to Neural Link'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Neural Link Synchronized',
                'new_url' => $avatarUrl
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'System Sync Failure'
            ], 500);
        }
    }
}