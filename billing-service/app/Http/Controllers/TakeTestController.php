<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TakeTestController extends Controller
{
    public function showListeningTest(Request $request, $id)
    {
        $user_id = Auth::id();
        $test_id = (int)$id;

        $test_row = DB::table('practice_sessions as ps')
            ->join('users as u', 'ps.user_id', '=', 'u.id')
            ->select('ps.*', 'u.plan_type')
            ->where('ps.id', $test_id)
            ->where('ps.user_id', $user_id)
            ->first();

        if (!$test_row) {
            abort(404, "Not found");
        }

        $test = (array)$test_row;
        session(['active_test_id' => $test_id]);

        $userPlan = $test['plan_type']; 
        $isPro = ($userPlan === 'pro');
        $questions = json_decode($test['questions'], true) ?? [];
        $layout_json = json_decode($test['layout_frontent'], true) ?? [];

        // --- NEW LOGIC: Check both tables ---
        
        // 1. Check if user already clicked "Start" (The Seal is Broken)
        // 1. Check if user already clicked "Start" (The Seal is Broken)
// --- NEW LOGIC: Check both tables ---
        
        // 1. Fetch the attempt record to see if they started AND if the warning was shown
        $attemptRecord = DB::table('test_attempts')
            ->where('user_id', $user_id)
            ->where('test_id', $test_id)
            ->first();

        $hasStartedBefore = !is_null($attemptRecord);
        $warningAlreadyShown = $hasStartedBefore ? (bool)$attemptRecord->is_warning_shown : false;

        // 2. Check if the user has already SUBMITTED this test successfully
        $hasSubmittedBefore = DB::table('practice_results')
            ->where('user_id', $user_id)
            ->where('test_id', $test_id)
            ->exists();

        // 3. Logic for Interruption
        // Interrupted ONLY if: Started + Not Submitted + Warning hasn't been shown yet
        $isInterrupted = $hasStartedBefore && !$hasSubmittedBefore && !$warningAlreadyShown;

        // 4. Logic for Strict Mode
        $isStrictAttempt = !$hasStartedBefore && !$hasSubmittedBefore;
        $global_test_title = "Listening Practice"; 
        foreach ($questions as $q) {
            if (!empty($q['script_title'])) {
                $global_test_title = $q['script_title'];
                break; 
            }
        }

        $layouts = json_decode($test['layout_frontent'], true) ?? [];
        if (!empty($layouts) && !isset($layouts[0])) {
            $layouts = [$layouts];
        }

        $blocks = [];
        $current_subtype = null;
        $current_block = [];
        foreach ($questions as $q) {
            $subtype = strtoupper($q['subtype'] ?? $q['completion_type'] ?? 'UNKNOWN');
            if ($subtype !== $current_subtype && $current_subtype !== null) {
                $blocks[] = [
                    'question_type' => $current_block[0]['question_type'] ?? '',
                    'subtype' => $current_subtype,
                    'questions' => $current_block
                ];
                $current_block = [];
            }
            $current_subtype = $subtype;
            $current_block[] = $q;
        }

        if (!empty($current_block)) {
            $blocks[] = [
                'question_type' => $current_block[0]['question_type'] ?? '',
                'subtype' => $current_subtype,
                'questions' => $current_block
            ];
        }

        $total_question_dots = 0;
        foreach ($questions as $q) {
            if (isset($q['items']) && is_array($q['items'])) {
                $total_question_dots += count($q['items']);
            } else {
                $total_question_dots++;
            }
        }

        $saved_word_data = !empty($test['word_data_json']) ? $test['word_data_json'] : 'null';

        return view('taketest.take_single_listening_test', compact(
    'test', 'isPro', 'questions', 'layouts', 'blocks', 
    'total_question_dots', 'global_test_title', 'saved_word_data', 
    'hasSubmittedBefore', 'isStrictAttempt', 'isInterrupted' // Added isInterrupted
));
    }

    /**
     * API Endpoint to log the start of the test
     */
    public function registerStart(Request $request, $id)
    {
        // Use insertOrIgnore to prevent errors on double-clicking or refreshes
        DB::table('test_attempts')->insertOrIgnore([
            'user_id' => Auth::id(),
            'test_id' => (int)$id,
            'started_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

   
    /**
     * API Endpoint to log that the interrupted warning was shown
     */
    public function acknowledgeInterruption(Request $request, $id)
    {
        DB::table('test_attempts')
            ->where('user_id', Auth::id())
            ->where('test_id', (int)$id)
            ->update(['is_warning_shown' => 1]);

        return response()->json(['success' => true]);
    }




    public function setLanguage(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|string'
        ]);

        session(['language' => $validated['language']]);

        return response()->json([
            'success' => true, 
            'language' => session('language')
        ]);
    }

    public function showReadingTest(Request $request, $id)
    {
        $user_id = Auth::id();
        $test_id = (int)$id;

        // Store active test in session
        session(['active_reading_test_id' => $test_id]);

        $selected_language = session('language', 'English');

        // Fetch test from database
        $test = DB::table('reading_tests')
            ->where('id', $test_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$test) {
            abort(404, "Test not found or you do not have permission to access it.");
        }

        // Process translated fields based on language
        $isEnglish = ($selected_language === 'English');

        $passage_to_display = (!$isEnglish && !empty($test->translated_passage)) 
            ? $test->translated_passage 
            : $test->passage;

        $questions_raw = (!$isEnglish && !empty($test->translated_questions))
            ? json_decode($test->translated_questions, true)
            : json_decode($test->questions, true);

        $answers_raw = (!$isEnglish && !empty($test->translated_answers))
            ? json_decode($test->translated_answers, true)
            : json_decode($test->answers, true);

        $questions_raw = is_array($questions_raw) ? $questions_raw : [];

        $passage_title = $questions_raw[0]['passage_title'] ?? $test->topic ?? 'IELTS Reading Test';

        // Clean up passage text
        $clean_passage = preg_replace('/```json.*?```/s', '', $passage_to_display);
        $clean_passage = preg_replace('/^---$/m', '', $clean_passage);
        $clean_passage = trim($clean_passage);

        // Group questions logically
        $groups = [];
        $qNum = 1;

        if (!empty($questions_raw)) {
            foreach ($questions_raw as $q) {
                $type = strtoupper($q['subtype'] ?? $q['type'] ?? 'STANDARD');
                
                if (empty($groups) || end($groups)['type'] !== $type) {
                    $groups[] = [
                        'type' => $type,
                        'format_type' => $this->formatPracticeType($type, $selected_language),
                        'instruction' => $this->getIeltsInstructions($type),
                        'start' => $qNum,
                        'questions' => []
                    ];
                }
                
                $currentIdx = count($groups) - 1;
                $groups[$currentIdx]['questions'][] = $q;

                // Advance question numbers based on type
                if ($type === 'SUMMARY-BANK' && isset($q['questions_options'])) {
                    $qNum += count($q['questions_options']);
                } elseif ($type === 'MATCHING-HEADINGS' && isset($q['answer']) && is_array($q['answer'])) {
                    $qNum += count($q['answer']);
                } else {
                    $qNum++;
                }
                
                $groups[$currentIdx]['end'] = $qNum - 1;
            }
        }

        return view('taketest.take_test', compact(
            'test_id',
            'passage_title',
            'clean_passage',
            'groups',
            'qNum',
            'selected_language'
        ));
    }

    /**
     * Helper: Get IELTS Instructions
     */
    private function getIeltsInstructions($type)
    {
        $instructions = [
            'MCQ-SINGLE'            => "Choose the correct letter, A, B, C or D.",
            'MCQ-MULTI'             => "Choose the correct letters from the options provided.",
            'MCQ-GLOBAL'            => "Choose the correct letter, A, B, C or D.",
            'TFNG'                  => "Do the following statements agree with the information given in the reading passage?",
            'YNNG'                  => "Do the following statements agree with the claims of the writer in the reading passage?",
            'MATCHING-HEADINGS'     => "Choose the correct heading for each paragraph from the list of headings below.",
            'MATCHING-INFO'         => "Which paragraph contains the following information?",
            'MATCHING-FEATURES'     => "Match each statement with the correct person/place/feature.",
            'SUMMARY-BANK'          => "Complete the summary using the list of words, A-G, below.",
            'STANDARD'              => "Write the correct answer in the box provided.",
            'sentence_completion'   => "Complete the sentences below. Choose NO MORE THAN TWO WORDS from the passage for each answer.",
            'note_completion'       => "Complete the notes below. Choose NO MORE THAN TWO WORDS from the passage for each answer."
        ];
        
        return $instructions[strtoupper($type)] ?? $instructions[$type] ?? "Answer the questions below based on the text.";
    }

    /**
     * Helper: Format Practice Type Name
     */
    private function formatPracticeType($type, $lang)
    {
        $map = [
            'MCQ-SINGLE'            => ['en' => 'Multiple Choice'],
            'MCQ-MULTI'             => ['en' => 'Multiple Choice (Multiple)'],
            'MCQ-GLOBAL'            => ['en' => 'Global Multiple Choice'],
            'TFNG'                  => ['en' => 'True / False / Not Given'],
            'YNNG'                  => ['en' => 'Yes / No / Not Given'],
            'MATCHING-HEADINGS'     => ['en' => 'Matching Headings'],
            'MATCHING-INFO'         => ['en' => 'Matching-Info'],
            'MATCHING-FEATURES'     => ['en' => 'Matching Features'],
            'SUMMARY-BANK'          => ['en' => 'Summary Completion (with Bank)'],
            'STANDARD'              => ['en' => 'Short Answer Questions']
        ];
        
        $key = ($lang === 'English') ? 'en' : 'other';
        return $map[strtoupper($type)][$key] ?? strtoupper(str_replace('_', ' ', $type));
    }
}