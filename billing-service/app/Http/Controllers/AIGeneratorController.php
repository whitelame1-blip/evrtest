<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File; // Ensure this is at the top of your file

class AIGeneratorController extends Controller
{
   private function dispatchToFlask($endpoint, $payload, $method = 'POST', $files = [])
{
    $url = config('services.flask.base_url') . $endpoint;
    $request = Http::withHeaders(['X-Internal-Key' => config('services.flask.key')]);

    if (!empty($files)) {
        // 1. Only use attach() for actual files
        foreach ($files as $name => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $request->attach(
                    $name, 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                );
            }
        }

        // 2. Prepare the text payload safely
        $cleanPayload = [];
        foreach ($payload as $key => $value) {
            // Remove the 'file' object from the text payload to prevent conflicts
            if ($key === 'file') continue; 
            
            // Convert arrays (like mixed_types) to JSON strings
            $cleanPayload[$key] = is_array($value) ? json_encode($value) : $value;
        }

        // 3. Pass the clean payload directly to post(). 
        // Laravel automatically handles formatting this alongside the attached files!
        return $request->post($url, $cleanPayload);
    }

    // Standard JSON request if no files
    return $method === 'POST' 
        ? $request->post($url, $payload) 
        : $request->get($url, $payload);
}
   private function logTask($taskId, $type)
{
    $now = now();

    // 1. Insert the new task
    DB::table('user_tasks')->insert([
        'user_id'    => Auth::id(),
        'task_id'    => $taskId,
        'task_type'  => $type,
        'status'     => 'pending',
        'created_at' => $now,
        'updated_at' => $now, // Keep timestamps synced
    ]);

    // 2. Determine the "Lane" for this task type
    $laneTypes = match($type) {
        'generate_listening', 'generate_reading' => ['generate_listening', 'generate_reading'],
        'analyze_audio', 'pronunciation_tips' => ['analyze_audio', 'pronunciation_tips'],
        default => [$type],
    };

    // 3. Calculate the initial position
    // We count how many tasks are in this specific lane
    return DB::table('user_tasks')
        ->where('status', 'pending')
        ->whereIn('task_type', $laneTypes)
        ->where('created_at', '<=', $now)
        ->count();
}

    public function getVoices()
{
    $data = $this->dispatchToFlask('/voices', [], 'GET')->json();
    return response()->json($data); // Wrap it in response()->json()
}



public function previewVoice(Request $request)
    {
        // 1. Validation (Replaces your abort_json checks)
        $validated = $request->validate([
            'voice_id' => 'required|string',
            'is_static' => 'boolean',
            'stability' => 'numeric|between:0,1',
            'similarity_boost' => 'numeric|between:0,1',
            'style' => 'numeric|between:0,1',
            'speed' => 'numeric|between:0.5,2.0',
        ]);

        $userId = Auth::id(); // Get current logged-in user
        $isStatic = $request->boolean('is_static', false);

        // 2. Fetch credits using Eloquent or Query Builder
        $credits = DB::table('credits')
            ->where('user_id', $userId)
            ->first();

        $attemptsLeft = $credits->listening_preview_attempts ?? 0;

        // 3. Rate Limiting (Using Laravel's built-in Rate Limiter if needed)
        if (!$isStatic) {
            // You can use Laravel's 'Throttle' middleware or manual check here
        }

        // 4. Permission Logic
        // Assuming you have a 'is_pro' boolean on your User model or a helper
        $isPro = Auth::user()->is_pro ?? false; 
        $canTune = !$isStatic && ($attemptsLeft > 0 || $isPro);

        // 5. Construct Payload for Python Flask Backend
        $payload = [
            'user_id'          => $userId,
            'voice_id'         => $validated['voice_id'],
            'is_static'        => $isStatic,
            'stability'        => $canTune ? ($validated['stability'] ?? 0.5) : 0.5,
            'similarity_boost' => $canTune ? ($validated['similarity_boost'] ?? 0.75) : 0.75,
            'style'            => $canTune ? ($validated['style'] ?? 0.0) : 0.0,
            'speed'            => $canTune ? ($validated['speed'] ?? 1.0) : 1.0
        ];

        // 6. Send to Python Flask (Zero-Footprint Communication)
        try {
    $response = $this->dispatchToFlask('/preview-voice', $payload);

    if ($response->successful()) {
        if (!$isStatic && !$isPro && $attemptsLeft > 0) {
            DB::table('credits')->where('user_id', $userId)->decrement('listening_preview_attempts');
        }
        return response()->json($response->json());
    }

    // This helps you see the REAL error from Flask in your browser console
    return response()->json([
        'error' => 'Voice engine failed',
        'flask_response' => $response->json() 
    ], $response->status());

} catch (\Exception $e) {
    return response()->json(['error' => 'Flask backend unreachable'], 500);
}
    }

    public function generateListening(Request $request)
{
    // 1. Rigorous Validation (Keep your existing validation logic)
    $validator = Validator::make($request->all(), [
        'topic' => ['required', 'string', function ($attribute, $value, $fail) {
            if (str_word_count($value) > 50) { $fail("The {$attribute} must not exceed 50 words."); }
        }],
        'difficulty'    => 'required|string|in:easy,medium,hard',
        'section'       => 'required|integer|between:1,4',
        'question_type' => 'required|string|in:completion,multiple_choice,matching,mixed',
        'speakers'      => 'required|array|min:1|max:4',
        'speakers.*.voice_id' => 'required|string',
        'speakers.*.gender'   => 'required|string|in:male,female',
        'mixed_type'    => 'exclude_unless:question_type,mixed|required|array|size:2',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => 'Validation failed', 'details' => $validator->errors()->first()], 422);
    }

    // 2. Business Logic: Credit Calculation
    $user_id = Auth::id();
    $section = (int)$request->input('section');
    $qType = $request->input('question_type');
    
    $costs = [1 => 2, 2 => 3, 3 => 4, 4 => 5];
    $requiredCredits = $costs[$section] ?? 2;
    if ($qType === 'mixed') { $requiredCredits += 1; }

    // 3. Database Transaction for Credit Deduction
    // We do this BEFORE the API call to "reserve" the credits.
    try {
        $task_id = DB::transaction(function () use ($user_id, $requiredCredits, $request, $section, $qType) {
            
            // Check balance with a "Lock" to prevent race conditions
            $creditRecord = DB::table('credits')->where('user_id', $user_id)->lockForUpdate()->first();

            if (!$creditRecord || $creditRecord->balance < $requiredCredits) {
                throw new \Exception("Insufficient credits.");
            }

            // Deduct Credits
            DB::table('credits')->where('user_id', $user_id)->decrement('balance', $requiredCredits);
            $newBalance = $creditRecord->balance - $requiredCredits; // Calculate new balance for response
            // 4. Prepare Payload
            $payload = [
                'user_id'       => $user_id,
                'topic'         => strip_tags($request->input('topic')),
                'difficulty'    => $request->input('difficulty'),
                'section'       => $section,
                'question_type' => $qType,
                'speakers'      => $request->input('speakers'), 
                'mixed_type'    => ($qType === 'mixed') ? $request->input('mixed_type') : [],
                'cost'          => $requiredCredits
            ];

            // 5. Dispatch to Flask (Inside Transaction to ensure log + deduction happen together)
            $response = $this->dispatchToFlask('/generate/section', $payload);
           if (!$response->successful()) {
            throw new \Exception("Flask server connection failed.");
        }

        $flaskData = $response->json();
        $flaskData['new_balance'] = $newBalance; // Add the new balance here

        return $flaskData;

            if ($response->successful() && isset($data['task_id'])) {
                 $initialPosition = $this->logTask($data['task_id'], 'generate_listening', $requiredCredits);
               $data['initial_queue_position'] = $initialPosition;
        $data['in_top_three'] = ($initialPosition <= 3);
         return response()->json($data); 
            }
             

            // If Flask fails, throw exception to rollback the credit deduction
            throw new \Exception("AI_ENGINE_BUSY");
        });

        return response()->json($task_id);

    } catch (\Exception $e) {
        if ($e->getMessage() === "Insufficient credits.") {
            return response()->json(['error' => $e->getMessage()], 403);
        }
        
        if ($e->getMessage() === "AI_ENGINE_BUSY") {
            return response()->json(['error' => 'The AI engine is currently busy. Credits were not deducted.'], 500);
        }

        return response()->json(['error' => 'Connection to AI server failed. No credits taken.'], 500);
    }
}

public function generateReading(Request $request)
{
    $user_id = Auth::id();

    // 1. Validation Security
    $validator = Validator::make($request->all(), [
        // This is your first gate (max:100)
        'topic'         => 'nullable|string|max:100', 
        'num_questions' => 'required|integer|between:1,13',
        'word_count'    => 'required_without:file|nullable|integer|between:0,1200', 
        'question_type' => 'required|string',
        'mixed_types'   => 'array|max:3',
        'file'          => [
            'nullable',
            File::types(['pdf', 'docx', 'doc'])
                    ->min(5)
                    ->max(5120),
        ],
    ]);

    // Check if basic validation fails
    if ($validator->fails()) {
        return response()->json([
            'error' => 'Validation failed',
            // Pick the first error message for a cleaner SweetAlert experience
            'details' => $validator->errors()->first() 
        ], 422);
    }

    // 2. Custom Business Logic: Topic & Mixed Types
    $topic = $request->input('topic');
    $qType = $request->input('question_type');
    $numQuestions = (int)$request->input('num_questions');
    $mixedTypes = $request->input('mixed_types', []);

    // Extra "Belt and Suspenders" check for Topic Length
    if (empty(trim($topic))) {
    return response()->json([
        'error' => "The topic cannot be empty. Please describe what you want to listen to."
    ], 422);
}

// 2. Check word count (Limit: 50 words)
$wordCount = str_word_count($topic);
if ($wordCount > 50) {
    return response()->json([
        'error' => "The topic is too descriptive ($wordCount words). Please keep it under 50 words for better AI generation."
    ], 422);
}

// 3. Optional: Safety Character Limit (Prevents extremely long single "nonsense" words)
if (mb_strlen($topic) > 300) {
    return response()->json([
        'error' => "The topic text is physically too long. Please shorten it."
    ], 422);
}

    if ($qType === 'mixed' && $numQuestions < count($mixedTypes)) {
        return response()->json([
            'error' => "Please select at least one question for each of your " . count($mixedTypes) . " chosen types."
        ], 422);
    }

    if ($qType === 'mixed' && empty($mixedTypes)) {
        return response()->json([
            'error' => "Please select at least one subtype (e.g., Matching, MCQ) for Mixed Mode."
        ], 422);
    }

    // 3. Credit Logic
    $currentCost = ($qType === 'mixed') ? 2 : 1;
    $balance = DB::table('credits')->where('user_id', $user_id)->value('balance');
    
    if ($balance < $currentCost) {
        return response()->json(['error' => 'Insufficient credits.'], 403);
    }

    // 4. Prepare Payload (Sanitized)
    $hasFile = $request->hasFile('file');

    $payload = [
        'user_id'       => $user_id,
        // Use htmlspecialchars or strip_tags to prevent XSS
        'topic'         => $topic ? strip_tags($topic) : 'Random IELTS Topic',
        'difficulty'    => strip_tags($request->input('difficulty', 'Intermediate')),
        'num_questions' => $numQuestions,
        'word_count'    => $hasFile ? 0 : (int)$request->input('word_count'),
        'question_type' => $qType,
    ];

    if ($qType === 'mixed') {
        $payload['mixed_types'] = $mixedTypes;
    }

    // 5. Handle File Upload & Dispatch
    $files = [];
    if ($request->hasFile('file')) {
        $files['file'] = $request->file('file');
    }

    $response = $this->dispatchToFlask('/generate-reading', $payload, 'POST', $files);
    $data = $response->json();

    // Catch any logical errors returned from Flask (like "Topic too complex")
    if (isset($data['error'])) {
        return response()->json([
            'error' => $data['error']
        ], 422); 
    }

    if ($response->successful() && isset($data['task_id'])) {
        $this->logTask($data['task_id'], 'generate_reading');
        return response()->json($data);
    }

    return response()->json([
        'error' => 'Failed to start reading generation.',
        'details' => $data
    ], 500);
}
    public function checkStatus($taskId)
{
    // 1. Ownership & Existence Check
    $task = DB::table('user_tasks')
        ->where('user_id', Auth::id())
        ->where('task_id', $taskId)
        ->first();

    if (!$task) return response()->json(['error' => 'Unauthorized'], 403);

    // 2. Return early if already completed in our DB
    if ($task->status === 'completed') {
        return response()->json([
            'state' => 'SUCCESS', 
            'status' => 'completed',
            'result_id' => $task->result_id
        ]);
    }

    // 3. Get real-time state from Flask (Celery)
    $response = $this->dispatchToFlask("/status/" . urlencode($taskId), [], 'GET');
    $data = $response->json();

    // 4. Handle Task Completion
    if (($data['state'] ?? '') === 'SUCCESS') {
        $resultId = $data['result']['mission_id'] ?? $data['result']['test_id'] ?? null;
        
        DB::table('user_tasks')->where('task_id', $taskId)->update([
            'status' => 'completed',
            'result_id' => $resultId,
            'updated_at' => now()
        ]);
        
        return $data; 
    }

    // 5. Categorize the Task Type into "Lanes"
    // This ensures users are only waiting on tasks that use the same worker
    $laneTypes = match($task->task_type) {
        'listening_gen', 'reading_gen' => ['listening_gen', 'reading_gen'], // Heavy Queue
        'voice_analysis', 'pronunciation_tips' => ['voice_analysis', 'pronunciation_tips'], // Medium Queue
        default => [$task->task_type], // Express Queue (Explanations/Transcripts)
    };

    // 6. Calculate Queue Position within that specific Lane
    $position = DB::table('user_tasks')
        ->where('status', 'pending')
        ->whereIn('task_type', $laneTypes) 
        ->where('created_at', '<=', $task->created_at)
        ->count();

    // 7. Enrich the response for the Frontend
    $data['queue_position'] = $position;
    $data['in_top_three'] = ($position <= 3);
    
    // Custom messages to keep the user engaged
    if ($position <= 3) {
        $data['queue_message'] = "You're up! The AI is processing your request...";
    } else {
        $data['queue_message'] = "You are #$position in line for this task.";
    }

    return $data;
}
    public function getSavedTests()
{
    $user = Auth::user();
    $user_id = $user->id;
    $plan_type = $user->plan_type ?? 'free';

    // 1. Fetch Download Attempts and Calculate Limits
    $current_downloads = $user->download_attempts ?? 0;
    $max_downloads = ($plan_type === 'pro') ? 100 : 4; // Matches your HomeController logic
    
    $remaining = max(0, $max_downloads - $current_downloads);
    $percentage = ($max_downloads > 0) ? ($current_downloads / $max_downloads) * 100 : 0;

    // Determine Bar Color based on usage
    if ($percentage >= 90) {
        $barColor = 'bg-rose-500';
    } elseif ($percentage >= 70) {
        $barColor = 'bg-amber-500';
    } else {
        $barColor = 'bg-indigo-600';
    }

    // 2. Fetch the Tests (Your existing Union Query)
    $results = DB::table(function ($query) use ($user_id) {
        $query->select('id', 'topic as display_name', 'difficulty', 'created_at', DB::raw("'single' as test_category"), 'questions')
              ->from('reading_tests')
              ->where('user_id', $user_id)
              ->unionAll(
                  DB::table('full_test')
                    ->select('id', 'test_name as display_name', DB::raw("'Mixed' as difficulty"), 'created_at', DB::raw("'full' as test_category"), DB::raw("NULL as questions"))
                    ->where('user_id', $user_id)
              )
              ->unionAll(
                  DB::table('practice_sessions')
                    ->select('id', 'topic as display_name', DB::raw("CONCAT('Section ', section_number) as difficulty"), 'created_at', DB::raw("'listening' as test_category"), 'questions')
                    ->where('user_id', $user_id)
              );
    }, 'combined')
    ->orderBy('created_at', 'DESC')
    ->get();

    // 3. Pass ALL required variables to the view
    return view('dashboard_saved_tests', [
        'result' => $results, 
        'plan_type' => $plan_type,
        'remaining' => $remaining,
        'percentage' => $percentage,
        'barColor' => $barColor,
        'current_downloads' => $current_downloads,
        'max_downloads' => $max_downloads,
    ]);
}
public function getNotificationCounts()
{
    $userId = Auth::id();

    // 1. Get the last time the user checked notifications
    $last_seen = DB::table('user_test_notifications')
                    ->where('user_id', $userId)
                    ->value('last_seen_at');

    // --- 2. COUNT SAVED TESTS (Reading + Listening + Full) ---
    $querySavedReading = DB::table('reading_tests')->where('user_id', $userId);
    $querySavedListening = DB::table('practice_sessions')->where('user_id', $userId);
    $querySavedFull = DB::table('full_test')->where('user_id', $userId);

    if ($last_seen) {
        $querySavedReading->where('created_at', '>', $last_seen);
        $querySavedListening->where('created_at', '>', $last_seen);
        $querySavedFull->where('created_at', '>', $last_seen);
    }

    $saved_total = $querySavedReading->count() + 
                   $querySavedListening->count() + 
                   $querySavedFull->count();

    // --- 3. COUNT COMPLETED TESTS (Responses + Mock Results) ---
    $queryResAll = DB::table('user_test_responses_all')->where('user_id', $userId);
    $queryMock = DB::table('user_mock_results')->where('user_id', $userId);

    if ($last_seen) {
        // Note: Using submission_time as per your original script
        $queryResAll->where('submission_time', '>', $last_seen);
        $queryMock->where('submission_time', '>', $last_seen);
    }

    $completed_total = $queryResAll->count() + $queryMock->count();

    // Return as JSON for your JavaScript badges
    return response()->json([
        "new_tests" => (int)$saved_total,
        "new_completed" => (int)$completed_total
    ]);
}

public function dismissNotifications()
{
    $userId = Auth::id();

    // Replaces your UPDATE user_test_notifications SET last_seen_at = NOW()
    DB::table('user_test_notifications')->updateOrInsert(
        ['user_id' => $userId],
        ['last_seen_at' => now()]
    );

    return response()->json(['status' => 'OK']);
}
public function generateGame(Request $request)
{
    $userId = Auth::id();

    // 1. Validation (Replaces manual sanitization and ?? checks)
    $validator = Validator::make($request->all(), [
        'difficulty'    => 'required|string|in:easy,medium,hard',
        'level'         => 'required|integer|min:1',
        'num_questions' => 'required|integer|between:1,20',
        'focus'         => 'required|string|max:50',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Validation failed',
            'details' => $validator->errors()->first()
        ], 422);
    }

    // 2. Business Logic: Check Solo Attempts
    // Based on your previous code, we check the credits table
    $userCredits = DB::table('credits')->where('user_id', $userId)->first();
    $currentAttempts = $userCredits->game_solo_attempts ?? 0;

    if ($currentAttempts < 1) {
        return response()->json([
            'error' => "You have no solo attempts remaining. Please top up your points."
        ], 403);
    }

    // 3. Prepare Payload (Clean and Sanitized)
    $payload = [
        'user_id'       => (int)$userId,
        'difficulty'    => $request->input('difficulty'),
        'level'         => (int)$request->input('level'),
        'num_questions' => (int)$request->input('num_questions'),
        'focus'         => strip_tags($request->input('focus'))
    ];

    // 4. Dispatch to Flask
    try {
        // We use your internal dispatch helper
        $response = $this->dispatchToFlask('/generatesinglegame-test', $payload);
        $data = $response->json();

        if ($response->successful() && isset($data['task_id'])) {
            // 5. Deduct Attempt & Log the Task
            DB::table('credits')->where('user_id', $userId)->decrement('game_solo_attempts');
            
            $position = $this->logTask($data['task_id'], 'game_gen');

            return response()->json([
                'task_id' => $data['task_id'],
                'queue_position' => $position,
                'message' => 'Mission generation initialized.'
            ]);
        }

        return response()->json([
            'error' => 'The game engine is currently recalibrating. Try again shortly.',
            'debug' => $data
        ], 500);

    } catch (\Exception $e) {
    return response()->json([
        'error' => 'Uplink to AI server lost.',
        'debug_message' => $e->getMessage() // This will tell you if it's a "Connection Refused" or "Timeout"
    ], 500);
}
}


public function generateAiReview(Request $request)
    {
        $userId = Auth::id();

        // 1. Validation Security
        $validator = Validator::make($request->all(), [
            'result_id' => 'required|integer',
            'test_id'   => 'required|integer',
            'type'      => 'required|string', // e.g., 'READING' or 'LISTENING'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Missing required assessment data.',
                'details' => $validator->errors()->first()
            ], 422);
        }

        $resultId = (int)$request->input('result_id');
        $testId   = (int)$request->input('test_id');
        $testType = strtoupper(trim($request->input('type')));

        // 2. SECURITY: IDOR PROTECTION (Insecure Direct Object Reference)
        // Verify this result actually belongs to the logged-in user
        $table = ($testType === 'LISTENING') ? 'practice_results' : 'user_test_responses_all';
        
        $ownsResult = DB::table($table)
            ->where('id', $resultId)
            ->where('user_id', $userId)
            ->exists();

        if (!$ownsResult) {
            return response()->json([
                'error' => 'Access Denied. Ownership verification failed.'
            ], 403);
        }

        // 3. PRICING & BALANCE CHECK
        $requiredCredits = 1; 
        $balance = DB::table('credits')->where('user_id', $userId)->value('balance');
        
        if ($balance < $requiredCredits) {
            return response()->json([
                'error' => "Insufficient credits. AI Review requires {$requiredCredits} credit."
            ], 403);
        }

        // 4. Prepare Payload for Flask
        $payload = [
            'user_id'   => $userId,
            'test_id'   => $testId,
            'result_id' => $resultId,
            'test_type' => $testType,
            'cost'      => $requiredCredits
        ];

        // 5. Dispatch to Flask Backend
        try {
            // Note: The previous PHP script used a 20-second timeout. 
            // In Laravel, the Http facade handles basic timeouts automatically, 
            // but if Celery/Flask is slow, Celery handles the heavy lifting anyway.
            $response = $this->dispatchToFlask('/analyze-performance', $payload);
            $data = $response->json();

            // Check if Flask returned a specific error (e.g., "Review already generated")
            if (isset($data['error'])) {
                return response()->json(['error' => $data['error']], 422);
            }

            if ($response->successful() && isset($data['task_id'])) {
                // Log the task using your existing helper so checkStatus can track it
                $position = $this->logTask($data['task_id'], 'ai_review');
                
                return response()->json([
                    'task_id' => $data['task_id'],
                    'queue_position' => $position,
                    'message' => 'AI Analysis initialized.'
                ]);
            }

            return response()->json([
                'error' => 'Failed to initialize AI Review.',
                'debug' => $data
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Uplink to AI server lost.',
                'debug_message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

}
