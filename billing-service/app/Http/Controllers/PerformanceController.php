<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PerformanceController extends Controller
{
    public function completed()
    {
        $user = Auth::user();
        $userId = $user->id;

        // 1. Calculate Stats for Reading & Listening
        $reading = $this->calculateStats('user_test_responses_all', 'score', 'total', $userId);
        $listening = $this->calculateStats('practice_results', 'score', 'total_questions', $userId);

        // 2. Performance History (Combined UNION query)
        $responses = DB::table('user_test_responses_all as r')
            ->leftJoin('ai_review_results as ai', function($join) {
                $join->on('r.id', '=', 'ai.result_id')
                     ->on('r.test_type', '=', 'ai.test_type');
            })
            ->select(
                'r.id', 'r.test_id', 'r.test_type', 'r.topic_name', 'r.score', 'r.total', 
                'r.submission_time', 
                DB::raw('IF(ai.id IS NOT NULL, 1, 0) AS ai_exists')
            )
            ->where('r.user_id', $userId)
            ->whereNotNull('r.test_id');

        $results = DB::table('practice_results as p')
            ->leftJoin('ai_review_results as ai', function($join) {
                $join->on('p.id', '=', 'ai.result_id')
                     ->on('p.test_type', '=', 'ai.test_type');
            })
            ->select(
                'p.id', 'p.test_id', 'p.test_type', 'p.topic_name', 'p.score', 
                'p.total_questions as total', 
                'p.submitted_at as submission_time',
                DB::raw('IF(ai.id IS NOT NULL, 1, 0) AS ai_exists')
            )
            ->where('p.user_id', $userId)
            ->whereNotNull('p.test_id')
            ->unionAll($responses)
            ->orderBy('submission_time', 'DESC')
            ->get();

        // 3. Avatar & Profile UI logic
        $initials = collect(explode(' ', $user->fullname))
            ->map(fn($name) => mb_substr($name, 0, 1))
            ->take(2)
            ->join('');
        
        $currentSeed = $user->avatar_seed ?: $user->fullname;
        $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($currentSeed);

        // 4. Credits
        $userCredits = $user->credits ?? 0;
        $pendingTask = DB::table('user_tasks')
    ->where('user_id', $userId)
    ->where('status', 'pending')
    ->select('task_id')
    ->first();
        // Pass everything to the main dashboard shell
        return view('dashboard', compact(
            'results', 
            'userCredits', 
            'reading', 
            'listening', 
            'user', 
            'initials', 
            'avatarUrl',
            'pendingTask'
        ));
    }

    /**
     * Laravel-style helper for calculating percentages and trends
     */
    private function calculateStats($table, $scoreCol, $totalCol, $userId)
    {
        // Get Total Count and Average percentage
        $summary = DB::table($table)
            ->where('user_id', $userId)
            ->selectRaw("COUNT(*) as count, AVG(($scoreCol * 100.0 / $totalCol)) as avg")
            ->first();

        $count = $summary->count ?? 0;
        $avg = round($summary->avg ?? 0);
        $trend = 0;

        // Calculate Trend if at least 5 records exist
        if ($count >= 5) {
            $scores = DB::table($table)
                ->where('user_id', $userId)
                ->orderBy('id', 'asc')
                ->selectRaw("($scoreCol * 100.0 / $totalCol) as pct")
                ->pluck('pct')
                ->toArray();

            $first5 = array_slice($scores, 0, 5);
            $last5 = array_slice($scores, -5);

            $firstAvg = array_sum($first5) / 5;
            $lastAvg = array_sum($last5) / 5;
            
            $trend = round($lastAvg - $firstAvg);
        }

        return [
            'count' => $count, 
            'avg'   => $avg, 
            'trend' => $trend
        ];
    }
}