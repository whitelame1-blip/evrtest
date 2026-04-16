<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CompletedTestsController extends Controller
{
    public function review(Request $request, $id)
    {
        $userId = Auth::id();
        $type = strtoupper($request->query('type', 'READING'));
        
        try {
            if ($type === 'LISTENING') {
                return $this->handleListeningReview($id, $userId);
            }

            return $this->handleReadingReview($id, $userId);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Neural Link Sync Failure'
            ], 500);
        }
    }

    private function handleListeningReview($id, $userId)
    {
        $res = DB::table('practice_results')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$res) {
            return response()->json(['success' => false, 'error' => 'Listening record not found.'], 404);
        }

        $rawList = json_decode($res->user_answers, true) ?: [];
        $review = collect($rawList)->map(function ($item) {
            return [
                'number'    => $item['num'] ?? '?',
                'user'      => !empty($item['u']) ? $item['u'] : 'Skipped',
                'correct'   => $item['c'] ?? 'N/A',
                'isCorrect' => (bool)($item['correct'] ?? false)
            ];
        });

        return $this->formatResponse($review, $res->score, $res->total_questions, 'LISTENING');
    }

    private function handleReadingReview($id, $userId)
    {
        $res = DB::table('user_test_responses_all')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$res) {
            return response()->json(['success' => false, 'error' => 'Reading record not found.'], 404);
        }

        $rawList = json_decode($res->answers_json, true) ?: [];
        $review = collect($rawList)->map(function ($item) {
            return [
                'number'    => $item['number'] ?? '?',
                'user'      => !empty($item['user']) ? $item['user'] : 'Skipped',
                'correct'   => $item['correct'] ?? 'N/A',
                'isCorrect' => (bool)($item['isCorrect'] ?? false)
            ];
        });

        return $this->formatResponse($review, $res->score, $res->total, 'READING');
    }

    private function formatResponse($review, $score, $total, $type)
    {
        return response()->json([
            'success' => true,
            'review'  => $review,
            'score'   => $score,
            'total'   => $total,
            'type'    => $type
        ]);
    }
}