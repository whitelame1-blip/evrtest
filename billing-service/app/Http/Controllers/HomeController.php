<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
class HomeController extends Controller
{
  public function dashboard()
{
    $user = Auth::user();
    $userId = $user->id;

    // --- 1. EXISTING DASHBOARD LOGIC ---
    $userCredits = DB::table('credits')->where('user_id', $userId)->value('balance') ?? 0;
    $userPlan = $user->plan_type ?? 'free';

    $last_seen_at = DB::table('user_test_notifications')
                        ->where('user_id', $userId)
                        ->value('last_seen_at');

    $new_tests = $this->countNewTests($userId, $last_seen_at);

    $reading = $this->calculateStats('user_test_responses_all', 'score', 'total', $userId);
    $listening = $this->calculateStats('practice_results', 'score', 'total_questions', $userId);
    $results = $this->getPerformanceHistory($userId);

    $userInitials = collect(explode(' ', $user->fullname))
        ->map(fn($name) => mb_substr($name, 0, 1))
        ->take(2)
        ->join('');
    
    $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($user->avatar_seed ?: $user->fullname);

    $pendingTask = DB::table('user_tasks')
        ->where('user_id', $userId)
        ->where('status', 'pending')
        ->select('task_id')
        ->first();

    // --- 2. EXISTING GAME LOGIC ---
    $user_data = DB::table('game_users')
        ->join('users', 'game_users.user_id', '=', 'users.id')
        ->where('game_users.user_id', $userId)
        ->select(
            'game_users.nickname', 
            'game_users.avatar_url', 
            'game_users.current_xp', 
            'game_users.user_rank_title', 
            'game_users.current_xp_arena', 
            'game_users.total_xp_accumulated',
            'users.plan_type'
        )
        ->first();

    if (!$user_data) {
        $user_data = (object)[
            'nickname' => 'New Recruit',
            'avatar_url' => null,
            'current_xp' => 0,
            'user_rank_title' => 'Novice',
            'current_xp_arena' => 0,
            'total_xp_accumulated' => 0,
            'plan_type' => $userPlan
        ];
    }

    $lifetime_xp = (int)$user_data->total_xp_accumulated;
    
    if ($lifetime_xp >= 50000) {
        $current_rank = "IELTS Legend";
    } elseif ($lifetime_xp >= 15000) {
        $current_rank = "Master Strategist";
    } elseif ($lifetime_xp >= 5000) {
        $current_rank = "Linguistic Elite";
    } else {
        $current_rank = "Novice Recon";
    }

    $rank_title = $user_data->user_rank_title ?: $current_rank;
    $xp = (int)$user_data->current_xp;
    $xp_arena = (int)$user_data->current_xp_arena;
    $total_xp = $xp + $xp_arena;
    $plan_type = $user_data->plan_type;

    $leaderboard = DB::table('game_users')
        ->select('nickname', 'total_xp_accumulated')
        ->orderBy('total_xp_accumulated', 'DESC')
        ->limit(10)
        ->get();

    $global_rank_pos = DB::table('game_users')
        ->where('total_xp_accumulated', '>', $lifetime_xp)
        ->count() + 1;

    $online_players = DB::table('game_users')
        ->where('last_seen', '>', \Carbon\Carbon::now()->subMinutes(5))
        ->count();

    // --- 3. NEW LOGIC (FETCHING DIRECTLY FROM USERS TABLE) ---
    
    // Fetch combined test history
    $result = DB::table(function ($query) use ($userId) {
        $query->select('id', 'topic as display_name', 'difficulty', 'created_at', DB::raw("'single' as test_category"), 'questions')
              ->from('reading_tests')
              ->where('user_id', $userId)
              ->unionAll(
                  DB::table('full_test')
                    ->select('id', 'test_name as display_name', DB::raw("'Mixed' as difficulty"), 'created_at', DB::raw("'full' as test_category"), DB::raw("NULL as questions"))
                    ->where('user_id', $userId)
              )
              ->unionAll(
                  DB::table('practice_sessions')
                    ->select('id', 'topic as display_name', DB::raw("CONCAT('Section ', section_number) as difficulty"), 'created_at', DB::raw("'listening' as test_category"), 'questions')
                    ->where('user_id', $userId)
              );
    }, 'combined')
    ->orderBy('created_at', 'DESC')
    ->get();

    // DIRECT FETCH: Ensure we get the latest download_attempts from the DB
    $current_downloads = DB::table('users')->where('id', $userId)->value('download_attempts') ?? 0;

    $plan = $user->plan_type ?? 'free';
    $max_downloads = ($plan === 'pro') ? 100 : 5;
    $remaining = max(0, $max_downloads - $current_downloads);
    
    $percentage = ($max_downloads > 0) ? ($current_downloads / $max_downloads) * 100 : 0;
    $barColor = $percentage > 80 ? 'bg-rose-500' : ($percentage > 50 ? 'bg-amber-500' : 'bg-emerald-500');

    // --- 4. COMBINED RETURN ---
    return view('dashboard', compact(
        'user', 'userCredits', 'new_tests', 'userInitials', 'userPlan',
        'reading', 'listening', 'results', 'avatarUrl', 'pendingTask',
        'user_data', 'rank_title', 'leaderboard', 'global_rank_pos', 
        'xp', 'xp_arena', 'total_xp', 'plan_type', 'online_players',
        'result', 'remaining', 'percentage', 'barColor', 'max_downloads',
        'current_downloads'
    ));
}
    /**
     * Helper to count new tests for notifications
     */
    private function countNewTests($userId, $last_seen_at)
    {
        $q1 = DB::table('reading_tests')->where('user_id', $userId);
        $q2 = DB::table('full_test')->where('user_id', $userId);
        $q3 = DB::table('practice_sessions')->where('user_id', $userId);

        if ($last_seen_at) {
            $q1->where('created_at', '>', $last_seen_at);
            $q2->where('created_at', '>', $last_seen_at);
            $q3->where('created_at', '>', $last_seen_at);
        }

        return $q1->count() + $q2->count() + $q3->count();
    }

    /**
     * Helper to fetch combined performance history
     */
    private function getPerformanceHistory($userId)
    {
        $responses = DB::table('user_test_responses_all as r')
            ->leftJoin('ai_review_results as ai', function($join) {
                $join->on('r.id', '=', 'ai.result_id')->on('r.test_type', '=', 'ai.test_type');
            })
            ->select('r.id', 'r.test_id', 'r.test_type', 'r.topic_name', 'r.score', 'r.total', 'r.submission_time', DB::raw('IF(ai.id IS NOT NULL, 1, 0) AS ai_exists'))
            ->where('r.user_id', $userId)
            ->whereNotNull('r.test_id');

        return DB::table('practice_results as p')
            ->leftJoin('ai_review_results as ai', function($join) {
                $join->on('p.id', '=', 'ai.result_id')->on('p.test_type', '=', 'ai.test_type');
            })
            ->select('p.id', 'p.test_id', 'p.test_type', 'p.topic_name', 'p.score', 'p.total_questions as total', 'p.submitted_at as submission_time', DB::raw('IF(ai.id IS NOT NULL, 1, 0) AS ai_exists'))
            ->where('p.user_id', $userId)
            ->whereNotNull('p.test_id')
            ->unionAll($responses)
            ->orderBy('submission_time', 'DESC')
            ->get();
    }

    /**
     * Helper for calculating stats
     */
    private function calculateStats($table, $scoreCol, $totalCol, $userId)
    {
        $summary = DB::table($table)
            ->where('user_id', $userId)
            ->selectRaw("COUNT(*) as count, AVG(($scoreCol * 100.0 / $totalCol)) as avg")
            ->first();

        $count = $summary->count ?? 0;
        $avg = round($summary->avg ?? 0);
        $trend = 0;

        if ($count >= 5) {
            $scores = DB::table($table)
                ->where('user_id', $userId)
                ->orderBy('id', 'asc')
                ->selectRaw("($scoreCol * 100.0 / $totalCol) as pct")
                ->pluck('pct')
                ->toArray();

            $first5 = array_slice($scores, 0, 5);
            $last5 = array_slice($scores, -5);
            $trend = round((array_sum($last5) / 5) - (array_sum($first5) / 5));
        }

        return ['count' => $count, 'avg' => $avg, 'trend' => $trend];
    }

     public function index()
    {
        $user_id = Auth::id();
        
        // 1. Fetch User Data with Join
        $user_data = DB::table('game_users')
            ->join('users', 'game_users.user_id', '=', 'users.id')
            ->where('game_users.user_id', $user_id)
            ->select(
                'game_users.nickname', 
                'game_users.avatar_url', 
                'game_users.current_xp', 
                'game_users.user_rank_title', 
                'game_users.current_xp_arena', 
                'game_users.total_xp_accumulated',
                'users.plan_type'
            )
            ->first();

        // Fallback if user not found
        if (!$user_data) {
            $user_data = (object)[
                'nickname' => 'New Recruit',
                'avatar_url' => null,
                'current_xp' => 0,
                'user_rank_title' => 'Novice',
                'current_xp_arena' => 0,
                'total_xp_accumulated' => 0,
                'plan_type' => 'free'
            ];
        }

        $lifetime_xp = (int)$user_data->total_xp_accumulated;
        
        // 2. Determine Rank Title based on TOTAL XP (Lifetime)
        if ($lifetime_xp >= 50000) {
            $current_rank = "IELTS Legend";
        } elseif ($lifetime_xp >= 15000) {
            $current_rank = "Master Strategist";
        } elseif ($lifetime_xp >= 5000) {
            $current_rank = "Linguistic Elite";
        } else {
            $current_rank = "Novice Recon";
        }

        // 3. Hall of Fame Leaderboard
        $leaderboard = DB::table('game_users')
            ->select('nickname', 'total_xp_accumulated')
            ->orderBy('total_xp_accumulated', 'DESC')
            ->limit(10)
            ->get();

        // 4. Global Rank Position
        $global_rank_pos = DB::table('game_users')
            ->where('total_xp_accumulated', '>', $lifetime_xp)
            ->count() + 1;

        // 5. Online Count (Users active in last 5 minutes)
        $online_players = DB::table('game_users')
            ->where('last_seen', '>', Carbon::now()->subMinutes(5))
            ->count();

        // 6. Map variables for the view
        $rank_title = $user_data->user_rank_title ?: $current_rank;
        $xp         = (int)$user_data->current_xp;
        $xp_arena   = (int)$user_data->current_xp_arena;
        $total_xp   = $xp + $xp_arena;
        $plan_type  = $user_data->plan_type;

        return view('dashboard', compact(
            'user_data', 
            'rank_title', 
            'leaderboard', 
            'global_rank_pos', 
            'xp', 
            'xp_arena', 
            'total_xp', 
            'plan_type',
            'online_players'
        ));
    }
}