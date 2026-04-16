<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TestResultController extends Controller
{
    public function submitListening(Request $request)
{
    $user_id = Auth::id();
    $test_id = $request->input('test_id');
    $user_submissions = $request->input('ans', []);

    // 1. Fetch Test Details
    $test = DB::table('practice_sessions')
        ->where('id', $test_id)
        ->where('user_id', $user_id)
        ->first();

    if (!$test) {
        return redirect()->route('dashboard'); 
    }

    // --- CHECK FOR PRE-EXISTING RESULT ---
    $existingResult = DB::table('practice_results')
        ->where('user_id', $user_id)
        ->where('test_id', $test_id)
        ->first();

    $correct_questions = json_decode($test->questions, true);

    // Extract Topic Name
    $topic_name = "Untitled Listening Test"; 
    if (!empty($correct_questions) && isset($correct_questions[0]['script_title'])) {
        $topic_name = $correct_questions[0]['script_title'];
    } elseif (!empty($test->topic)) {
        $topic_name = $test->topic;
    }

    // 2. Flatten and Grade Logic
    $flattened_answers = [];
    $global_q_num = 1;

    foreach ($correct_questions as $q) {
        $subtype = strtoupper($q['subtype'] ?? $q['question_type'] ?? '');
        
        $is_matching_type = in_array($subtype, ['MATCHING', 'CLASSIFICATION', 'FACT_MATCHING', 'OPINION', 'PROCEDURAL']);

        if ($is_matching_type && isset($q['items'])) {
            foreach ($q['items'] as $item) {
                $item_id = $item['id'];
                $flattened_answers[$global_q_num] = [
                    'text' => $item['text'], 
                    'correct' => $q['answer'][(string)$item_id] ?? ''
                ];
                $global_q_num++;
            }
        } else {
            $flattened_answers[$global_q_num] = [
                'text' => $q['question_text'] ?? ($q['instruction'] ?? "Question " . $global_q_num),
                'correct' => $q['answer']
            ];
            $global_q_num++;
        }
    }

    $score = 0;
    $total = count($flattened_answers);
    $results_comparison = [];

    foreach ($flattened_answers as $q_num => $q_data) {
        $correct_data = $q_data['correct'];
        $user_submission = $user_submissions[$q_num] ?? ""; 

        $u_comp = $this->formatForComparison($user_submission);
        $correct_comparison = $this->formatForComparison($correct_data);

        $is_correct = ($correct_comparison === $u_comp);
        if ($is_correct) $score++;

        $results_comparison[] = [
            'num' => $q_num,
            'text' => $q_data['text'],
            'u' => is_array($user_submission) ? implode(', ', $user_submission) : $user_submission,
            'c' => is_array($correct_data) ? implode(', ', $correct_data) : $correct_data,
            'correct' => $is_correct
        ];
    }

    // 3. Conditional Save/View logic
    if ($existingResult) {
        // SECOND ATTEMPT: Do NOT save, but show results directly
        $data = [
            'topic' => $topic_name,
            'score' => $score,
            'total' => $total,
            'percentage' => ($total > 0) ? round(($score / $total) * 100) : 0,
            'results_comparison' => $results_comparison
        ];
        // Return view directly instead of redirecting
        return view('taketest.process_results_listening', $data);

    } else {
        // FIRST ATTEMPT: Save result to practice_results
        $result_id = DB::table('practice_results')->insertGetId([
            'user_id' => $user_id,
            'test_id' => $test_id,
            'test_type' => 'LISTENING',
            'topic_name' => $topic_name,
            'score' => $score,
            'total_questions' => $total,
            'user_answers' => json_encode($results_comparison),
            'submitted_at' => now(),
        ]);

        return redirect()->route('test.results.show', $result_id);
    }
}
    public function show($id, $test_id)
    {
        $user_id = Auth::id();
    
    // Check if a result already exists in the listening results table
    $hasFinished = DB::table('practice_results')
        ->where('user_id', $user_id)
        ->where('test_id', $test_id)
        ->exists();

    if ($hasFinished) {
        return redirect()->route('dashboard');
    }
        $result = DB::table('practice_results')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$result) abort(404);

        $data = [
            'topic' => $result->topic_name,
            'score' => $result->score,
            'total' => $result->total_questions,
            'percentage' => ($result->total_questions > 0) ? round(($result->score / $result->total_questions) * 100) : 0,
            'results_comparison' => json_decode($result->user_answers, true)
        ];

        return view('taketest.process_results_listening', $data);
    }

    private function formatForComparison($data)
    {
        if (is_array($data)) {
            sort($data);
            return strtolower(implode(',', $data));
        }
        return trim(strtolower((string)$data));
    }




public function submitReading(Request $request)
{
    $user_id = Auth::id();
    $test_id = $request->input('test_id');
    $user_answers = $request->input('answer', []);

    if (!$user_id || !$test_id) {
        return redirect()->route('dashboard');
    }

    // 1. Fetch correct answers and check for existing record
    $test_data = DB::table('reading_tests')->where('id', $test_id)->first();
    if (!$test_data) {
        return abort(404, "Test not found.");
    }

    $existing_record = DB::table('user_test_responses_all')
        ->where('user_id', $user_id)
        ->where('test_id', $test_id)
        ->first();

    // Prepare data for grading
    $test_data = (array) $test_data;
    $correct_answers = json_decode($test_data['answers'], true);
    $questions_meta = json_decode($test_data['questions'], true);

    // Extract Topic Name
    $topic_name = $test_data['topic'] ?? "Untitled Reading Test";
    if (!empty($questions_meta) && isset($questions_meta[0]['passage_title'])) {
        $topic_name = $questions_meta[0]['passage_title'];
    }

    // 2. Scoring & Comparison Logic (Always calculate this)
    $score = 0;
    $results_comparison = [];
    $display_number = 1;

    foreach ($correct_answers as $id => $correct_val) {
        $user_val = $user_answers[$id] ?? null;

        if (is_array($correct_val)) {
            foreach ($correct_val as $subKey => $subCorrect) {
                $subUser = is_array($user_val) ? ($user_val[$subKey] ?? null) : null;
                $u = strtolower(trim((string)$subUser));
                $c = strtolower(trim((string)$subCorrect));
                $isCorrect = ($u === $c);
                if ($isCorrect) $score++;

                $results_comparison[] = [
                    'number'    => $display_number,
                    'context'   => (is_numeric($subKey)) ? "Part " . ($subKey + 1) : "Item: " . $subKey,
                    'user'      => $subUser ?? "Not Answered",
                    'correct'   => $subCorrect,
                    'isCorrect' => $isCorrect
                ];
                $display_number++;
            }
        } else {
            if (is_array($user_val)) {
                $user_display = implode(', ', $user_val);
                $u_arr = array_map('trim', $user_val); sort($u_arr);
                $c_arr = array_map('trim', explode(',', (string)$correct_val)); sort($c_arr);
                $isCorrect = (strtolower(implode(',', $u_arr)) === strtolower(implode(',', $c_arr)));
            } else {
                $u = strtolower(trim((string)$user_val));
                $c = strtolower(trim((string)$correct_val));
                $isCorrect = ($u === $c);
                $user_display = $user_val ?? "Not Answered";
            }

            if ($isCorrect) $score++;

            $results_comparison[] = [
                'number'    => $display_number,
                'context'   => null,
                'user'      => $user_display,
                'correct'   => $correct_val,
                'isCorrect' => $isCorrect
            ];
            $display_number++;
        }
    }

    $total = count($results_comparison);
    $percentage = ($total > 0) ? round(($score / $total) * 100) : 0;
    $estimatedBand = $this->getBandScore($score);

    // 3. Logic Branch: Save vs Show Direct
    if ($existing_record) {
        // SECOND ATTEMPT: Do NOT save to DB. Return view directly with fresh calculations.
        // We pass the existing record as 'response' to satisfy the view's requirements
        $response = $existing_record;
        
        return view('taketest.submit_test', compact(
            'score', 
            'total', 
            'topic_name', 
            'results_comparison', 
            'percentage', 
            'estimatedBand', 
            'response'
        ));
    } else {
        // FIRST ATTEMPT: Save to database
        DB::table('user_test_responses_all')->insert([
            'user_id'         => $user_id,
            'test_id'         => $test_id,
            'test_type'       => 'READING',
            'topic_name'      => $topic_name,
            'answers_json'    => json_encode($results_comparison),
            'score'           => (int)$score,
            'total'           => (int)$total,
            'submission_time' => now()
        ]);

        return redirect()->route('test.results', ['test_id' => $test_id]);
    }
}
    public function showResults($test_id)
    {
        $user_id = Auth::id();
        $alreadySubmitted = DB::table('user_test_responses_all')
        ->where('user_id', $user_id)
        ->where('test_id', $test_id)
        ->exists();

    // If it exists, they shouldn't be here. Send them to the dashboard.
    if ($alreadySubmitted) {
        return redirect()->route('dashboard');
    }
        $response = DB::table('user_test_responses_all')
            ->where('user_id', $user_id)
            ->where('test_id', $test_id)
            ->first();

        if (!$response) return redirect()->route('dashboard');

        $score = $response->score;
        $total = $response->total;
        $results_comparison = json_decode($response->answers_json, true);
        $topic_name = $response->topic_name;
        $percentage = ($total > 0) ? round(($score / $total) * 100) : 0;
        $estimatedBand = $this->getBandScore($score);

        return view('taketest.submit_test', compact('score', 'total', 'topic_name', 'results_comparison', 'percentage', 'estimatedBand', 'response'));
    }

    private function getBandScore($score) {
        if ($score >= 39) return 9.0;
        if ($score >= 37) return 8.5;
        if ($score >= 35) return 8.0;
        if ($score >= 33) return 7.5;
        if ($score >= 30) return 7.0;
        if ($score >= 27) return 6.5;
        if ($score >= 23) return 6.0;
        if ($score >= 19) return 5.5;
        if ($score >= 15) return 5.0;
        return 4.0;
    }

   
}