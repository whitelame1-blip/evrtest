<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File; // Ensure this is at the top of your file
class VoiceController extends Controller
{
   private function dispatchToFlask($endpoint, $payload, $method = 'POST', $files = [])
{
    $url = config('services.flask.base_url') . $endpoint;
    
    // Set a longer timeout (60s) because AI analysis is slow
    $request = Http::timeout(60)->withHeaders([
        'X-Internal-Key' => config('services.flask.key'),
        'Accept'         => 'application/json',
    ]);

    if (!empty($files)) {
        foreach ($files as $name => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $request->attach(
                    $name, 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                );
            }
        }

        $cleanPayload = [];
        foreach ($payload as $key => $value) {
            if ($key === 'file') continue; 
            $cleanPayload[$key] = is_array($value) ? json_encode($value) : $value;
        }

        return $request->post($url, $cleanPayload);
    }

    // CRITICAL FIX: Use ->asJson() for requests without files 
    // This ensures Flask's request.get_json() finds the 'user_id'
    return $method === 'POST' 
        ? $request->asJson()->post($url, $payload) 
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


    public function analyzeAudio(Request $request)
{
    // 1. Validation
    // Validates the file is present, is an audio type, and stays under 10MB
    $validator = Validator::make($request->all(), [
        'mode'          => 'nullable|string|in:basic,deep',
        'expected_text' => 'nullable|string',
        'audio' => [
    'required', 
    File::types([
        'audio/mpeg', 
        'audio/wav', 
        'audio/ogg', 
        'audio/x-m4a', 
        'audio/webm', // Add this
        'video/webm'  // Add this (Chrome often labels audio blobs as video/webm)
    ])->max(10240) // 10MB limit
],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error'   => 'Validation failed',
            'details' => $validator->errors()->first()
        ], 422);
    }

    // 2. Logic: Credit Calculation
    $mode = $request->input('mode', 'basic');
    $requiredCredits = ($mode === 'deep') ? 3 : 2;
    $userId = Auth::id();

    // 3. Credit Check
    $balance = DB::table('credits')->where('user_id', $userId)->value('balance');
    if ($balance < $requiredCredits) {
        return response()->json([
            'error' => "Insufficient credits. $mode analysis requires $requiredCredits credits."
        ], 403);
    }

    // 4. Prepare Payload
    // These values will be sent as standard form fields alongside the file
    $payload = [
        'user_id'       => $userId,
        'expected_text' => $request->input('expected_text', ''),
        'mode'          => $mode,
        'required_cost' => $requiredCredits,
    ];

    // 5. Prepare Files Array
    // This key 'audio' must match what Flask is looking for: request.files['audio']
    $files = [
        'audio' => $request->file('audio')
    ];

    // 6. Dispatch to Flask
    try {
    $data = DB::transaction(function () use ($userId, $requiredCredits, $request, $payload, $files) {
        // Double check balance inside transaction for safety
        $currentBalance = DB::table('credits')->where('user_id', $userId)->lockForUpdate()->value('balance');

        if ($currentBalance < $requiredCredits) {
            throw new \Exception("Insufficient credits.");
        }

        // Deduct the credits
        DB::table('credits')->where('user_id', $userId)->decrement('balance', $requiredCredits);
        $newBalance = $currentBalance - $requiredCredits;

        // 6. Dispatch to Flask (Inside the transaction or just after)
        $response = $this->dispatchToFlask('/analyze', $payload, 'POST', $files);
        
        if (!$response->successful()) {
            throw new \Exception("Flask server connection failed.");
        }

        $flaskData = $response->json();
        $flaskData['new_balance'] = $newBalance; // Add the new balance here

        return $flaskData;
    });

    // 7. Log Task and Return Response
    if (isset($data['task_id'])) {
        $initialPosition = $this->logTask($data['task_id'], 'analyze_audio');
        
        $data['initial_queue_position'] = $initialPosition;
        $data['in_top_three'] = ($initialPosition <= 3);

        return response()->json($data); 
    }

} catch (\Exception $e) {
    return response()->json([
        'error' => $e->getMessage()
    ], 403);
}
}

public function pronunciationTips(Request $request)
{
    // 1. Validation
    $validator = Validator::make($request->all(), [
        'uid'           => 'required|string',
        'expected_text' => 'required|string',
        'spoken_text'   => 'required|string',
        'scores'        => 'nullable|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Validation failed',
            'details' => $validator->errors()->first()
        ], 422);
    }

    // 2. Prepare Payload
    // Using $request->input() automatically handles json_decode logic for you
    $payload = [
        'user_id'       => Auth::id(),
        'uid'           => strip_tags($request->input('uid')),
        'expected_text' => strip_tags($request->input('expected_text')),
        'spoken_text'   => strip_tags($request->input('spoken_text')),
        'scores'        => $request->input('scores', [])
    ];

    // 3. Dispatch to Flask
    try {
        // Since there are no files, we pass an empty array for $files
        $response = $this->dispatchToFlask('/pronunciation-tips', $payload, 'POST');

         $data = $response->json();
        
        if ($response->successful() && isset($data['task_id'])) {
            $this->logTask($data['task_id'], 'pronunciation_tips');
            return response()->json($data); 
        }


        return response()->json([
            'error' => 'Could not generate tips. AI engine returned an error.',
            'debug' => $response->json()
        ], 500);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Connection to AI server failed.',
            'message' => $e->getMessage()
        ], 500);
    }
}




  public function aiReview(Request $request)
{
    // 1. Get User and check Plan Type
    $user = Auth::user();
    $userId = $user->id;

    // Check if user is PRO. If your column stores 'pro' in lowercase, use that.
    if ($user->plan_type !== 'pro') {
        return response()->json([
            'error' => 'Premium Feature',
            'message' => 'AI Script Review is only available for Pro members.',
            'is_pro' => false
        ], 403); // 403 Forbidden
    }

    // 2. Get test_id from request
    $testId = $request->input('test_id');

    if (!$testId) {
        return response()->json(['error' => 'Invalid Test ID.'], 400);
    }
    // Check if the user has completed/submitted this test at least once
    $hasSubmitted = DB::table('practice_results') // Ensure this matches your table name
        ->where('test_id', $testId)
        ->where('user_id', $userId)
        ->exists();

    if (!$hasSubmitted) {
        return response()->json([
            'error' => 'Test Completion Required',
            'message' => 'You must submit your answers for this test before you can unlock the AI Transcript.'
        ], 403); 
    }
    // --- END NEW VALIDATION ---
    // 3. Fetch the audio and transcript
    $test = DB::table('practice_sessions')
        ->where('id', $testId)
        ->where('user_id', $userId) 
        ->first();

    if (!$test) {
        return response()->json(['error' => 'Test not found.'], 404);
    }

    // 4. Construct local path
    $localPath = public_path(ltrim($test->audio_url, '/'));
    $localPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $localPath);

    if (!file_exists($localPath)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Audio file missing at: ' . $localPath
        ], 404);
    }
    
    if (!str_ends_with(strtolower($localPath), '.mp3')) {
        $localPath .= '.mp3';
    }

    // 5. Prepare Payload
    $payload = [
        'user_id'       => $userId,
        'test_id'       => (int)$testId,
        'audio_url'     => $localPath, 
        'expected_text' => $test->transcript 
    ];

    try {
        $response = $this->dispatchToFlask('/analyze-script', $payload, 'POST');

        if (!$response->successful()) {
            return response()->json([
                'error' => 'AI Engine failed to start.',
                'flask_status' => $response->status(),
                'flask_error' => $response->body()
            ], 500);
        }

        $data = $response->json();

        if (isset($data['task_id'])) {
            $this->logTask($data['task_id'], 'analyze_script');
        }

        return response()->json($data);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Connection to AI server failed.'], 500);
    }
}
}

