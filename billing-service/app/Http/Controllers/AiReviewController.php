<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AiReviewController extends Controller
{
    public function show(Request $request)
    {
        // 1. Validate incoming parameters
        $request->validate([
            'result_id' => 'required|integer',
            'test_type' => 'required|string|in:READING,LISTENING'
        ]);

        $resultId = $request->input('result_id');
        $testType = strtoupper($request->input('test_type'));

        // 2. Fetch AI Feedback
        $aiRecord = DB::table('ai_review_results')
            ->where('result_id', $resultId)
            ->where('test_type', $testType)
            ->first();

        if (!$aiRecord) {
            // Redirect back to dashboard with an error message
            return redirect('/dashboard')->with('error', 'Analysis not found or still processing.');
        }

        $feedback = json_decode($aiRecord->review_json, true);

        // 3. Fetch Source Content
        $sourceContent = "";
        $testTitle = "";

        if ($testType === 'READING') {
            $data = DB::table('user_test_responses_all as r')
                ->join('reading_tests as t', 'r.test_id', '=', 't.id')
                ->where('r.id', $resultId)
                // Add security check so users can't view others' tests
                ->where('r.user_id', Auth::id()) 
                ->select('r.topic_name', 't.passage')
                ->first();

            if (!$data) abort(403, 'Unauthorized or record not found.');

            $sourceContent = $data->passage ?? 'Passage content unavailable.';
            $testTitle = $data->topic_name ?? 'Reading Test';

        } else if ($testType === 'LISTENING') {
            $data = DB::table('practice_results as r')
                ->join('practice_sessions as s', 'r.test_id', '=', 's.id')
                ->where('r.id', $resultId)
                ->where('r.user_id', Auth::id())
                ->select('s.transcript', 'r.topic_name')
                ->first();

            if (!$data) abort(403, 'Unauthorized or record not found.');

            $sourceContent = $data->transcript ?? 'Transcript unavailable.';
            $testTitle = $data->topic_name ?? 'Listening Test';
        }

        // 4. Pass data to the Blade view
        return view('ai_review_feedback', [
            'feedback'      => $feedback,
            'sourceContent' => $sourceContent,
            'testTitle'     => $testTitle,
            'testType'      => $testType
        ]);
    }
}