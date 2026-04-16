<?php
// 1. Initialization & Security Headers (Laravel Style)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Security Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

if (session_status() === PHP_SESSION_NONE) {
    // Secure session cookie settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// --- CORE HELPERS (Laravel Patterns) ---

// Centralized Exception Handler
function abort_json($code, $message, $details = null) {
    http_response_code($code);
    $response = ["success" => false, "error" => $message];
    if ($details) $response["details"] = $details;
    echo json_encode($response);
    exit;
}

// Rate Limiter
function rate_limit($action, $cooldown_seconds) {
    $last_time = $_SESSION["last_run_{$action}"] ?? 0;
    if (time() - $last_time < $cooldown_seconds) {
        abort_json(429, "Too many requests. Please wait a moment before trying again.");
    }
    $_SESSION["last_run_{$action}"] = time();
}

// Database Helpers (Eloquent Pattern)
function get_user_balance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT balance FROM credits WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;
    $stmt->close();
    return (int)$balance;
}

function is_user_pro($conn, $user_id) {
    $stmt = $conn->prepare("SELECT plan_type FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $isPro = ($stmt->get_result()->fetch_assoc()['plan_type'] === 'pro');
    $stmt->close();
    return $isPro;
}
function get_user_solo_attempts($conn, $user_id) {
    $stmt = $conn->prepare("SELECT game_solo_attempts FROM credits WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $attempts = $result['game_solo_attempts'] ?? 0;
    $stmt->close();
    return (int)$attempts;
}

// --- END HELPERS ---

// 2. Global Authentication Check (Middleware)
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    abort_json(401, "Unauthorized. Please log in.");
}

// Hide included output
ob_start();
require __DIR__ . '/../vendor/autoload.php'; // Add ../ to go up one level

// Ensure this matches where your .env file is:
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) {
    ob_end_clean();
    abort_json(500, "Database connection failed.");
}
ob_end_clean();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
// Load Flask base URL from .env, fallback to localhost
$flask_base = $_ENV['FLASK_BASE_URL'] ?? "http://127.0.0.1:5000"; 
$flask_internal_key = $_ENV['FLASK_INTERNAL_KEY'] ?? ''; 

// 3. GLOBAL CSRF FIREWALL (For ALL POST Requests)
if ($method === 'POST') {
    $headers = apache_request_headers();
    $client_csrf = $headers['X-CSRF-TOKEN'] ?? '';
    
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_csrf)) {
        // Audit log trigger could go here
        error_log("CSRF Attack Blocked for User ID: $user_id | IP: " . $_SERVER['REMOTE_ADDR']);
        abort_json(403, "Security verification failed. Invalid CSRF token.");
    }
}

// Variables for cURL execution
$endpoint = '';
$payload = null;
$timeout = 30;
$use_json_content_type = true;

// 4. THE ROUTER
switch ($action) {

    case 'get_voices':
        if ($method !== 'GET') abort_json(405, "GET required");
        $endpoint = "/voices";
        $timeout = 10;
        break;

    case 'check_status':
        if ($method !== 'GET') abort_json(405, "GET required");
        
        $taskId = $_GET['task_id'] ?? '';
        if (empty($taskId) || strlen($taskId) < 20) {
            abort_json(400, "Invalid Task ID");
        }

        // --- NEW IMPROVED PROTECTION ---
        // Check if this user actually owns this task in the DB
        $checkStmt = $conn->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1");
        $checkStmt->bind_param("is", $user_id, $taskId);
        $checkStmt->execute();
        $ownsTask = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$ownsTask) {
            // Fallback: Check session just in case it's a brand new task not yet written to DB
            $user_tasks = $_SESSION['active_tasks'] ?? [];
            if (!in_array($taskId, $user_tasks)) {
                error_log("IDOR Blocked - User $user_id tried Task $taskId");
                abort_json(403, "Access denied. You do not own this task.");
            }
        }

        $endpoint = "/status/" . urlencode($taskId);
        $timeout = 10;
        
        break;

  case 'preview_voice':
    if ($method !== 'POST') abort_json(405, "POST required");
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['voice_id'])) abort_json(422, "Voice ID is required.");

    // 1. Detect if this is a free "Original" preview request
    $isStatic = isset($input['is_static']) && $input['is_static'] === true;

    // 2. Fetch the actual attempts left from the database
    $stmt = $conn->prepare("SELECT listening_preview_attempts FROM credits WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $attemptsLeft = $row['listening_preview_attempts'] ?? 0;

    // 3. Logic for Rate Limiting and Tuning Permissions
    // We only rate limit and check for credits if it's NOT a static request
    if (!$isStatic) {
        rate_limit('preview_voice', 3);
    }

    // A user can "Tune" (use custom slider values) only if:
    // It's NOT a static request AND they have attempts/Pro status
    $canTune = !$isStatic && ($attemptsLeft > 0 || is_user_pro($conn, $user_id));

    $payload = [
        'user_id'          => (int)$user_id,
        'voice_id'         => htmlspecialchars($input['voice_id']),
        'is_static'        => $isStatic, // Pass this to Python!
        
        // If they can tune, use their input. 
        // If it's static or they lack credits, force safe defaults.
        'stability'        => $canTune ? (float)($input['stability'] ?? 0.5) : 0.5,
        'similarity_boost' => $canTune ? (float)($input['similarity_boost'] ?? 0.75) : 0.75,
        'style'            => $canTune ? (float)($input['style'] ?? 0.0) : 0.0,
        'speed'            => $canTune ? (float)($input['speed'] ?? 1.0) : 1.0
    ];

    $endpoint = "/preview-voice";
    break;

    case 'generate_listening':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('generate_listening', 10); // 10 Second Cooldown

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) abort_json(400, "Invalid request data.");

        $section = (string)($input['section'] ?? '1');
        $qType = $input['question_type'] ?? 'completion';
        
        // Pricing & Balance Check
        $section_costs = ["1" => 2, "2" => 3, "3" => 4, "4" => 5];
        $required_credits = $section_costs[$section] ?? 2;
        if ($qType === 'mixed') $required_credits += 1;

        $current_balance = get_user_balance($conn, $user_id);
        if ($current_balance < $required_credits) {
            abort_json(403, "Insufficient credits. Section $section requires $required_credits credits, but you have $current_balance.");
        }

        // Validation
        $topic = trim($input['topic'] ?? '');
        $wordCount = !empty($topic) ? str_word_count($topic) : 0;
        if ($wordCount === 0 || $wordCount > 50 || strlen($topic) > 300) {
            abort_json(422, "Topic must be between 1 and 50 words (max 300 characters).");
        }

        $payload = [
            'user_id'       => (int)$user_id,
            'topic'         => htmlspecialchars($topic, ENT_QUOTES, 'UTF-8'),
            'difficulty'    => htmlspecialchars($input['difficulty'] ?? 'medium'),
            'section'       => $section,
            'question_type' => htmlspecialchars($qType, ENT_QUOTES, 'UTF-8'),
            'mixed_subtype' => is_array($input['mixed_subtype'] ?? null) ? $input['mixed_subtype'] : [],
            'speakers'      => is_array($input['speakers'] ?? null) ? $input['speakers'] : [],
            'required_cost' => $required_credits 
        ];

        $endpoint = "/generate/section";
        $timeout = 10;
        break;

    case 'generate_reading':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('generate_reading', 10); // 10 Second Cooldown
        
        $is_file_upload = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
        $input = $is_file_upload ? $_POST : json_decode(file_get_contents('php://input'), true);
        
        // Credit Check
        $qType = $input['question_type'] ?? 'true_false_not_given';
        $currentCost = ($qType === 'mixed') ? 2 : 1;

        $balance = get_user_balance($conn, $user_id);
        if ($balance < $currentCost) {
            abort_json(403, "Insufficient credits.");
        }

        $payload = [
            'user_id'       => (int)$user_id,
            'topic'         => htmlspecialchars($input['topic'] ?? ''),
            'difficulty'    => htmlspecialchars($input['difficulty'] ?? 'Intermediate'),
            'num_questions' => (int)($input['num_questions'] ?? 5),
            'word_count'    => (int)($input['word_count'] ?? 1200),
            'question_type' => htmlspecialchars($qType),
        ];

        if (isset($input['mixed_types'])) {
            $payload['mixed_types'] = is_array($input['mixed_types']) 
                ? $input['mixed_types'] 
                : json_decode($input['mixed_types'], true);
        }

        // File handling
        if ($is_file_upload) {
            $payload['file'] = new CURLFile(
                $_FILES['file']['tmp_name'], 
                $_FILES['file']['type'], 
                $_FILES['file']['name']
            );
            $use_json_content_type = false; 
        }

        $endpoint = "/generate-reading"; 
        $timeout = 10; 
        break;

    
   case 'analyze_audio':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('analyze_audio', 2); // Prevent spam-clicking

        // 1. Determine Cost based on Mode
        $mode = $_POST['mode'] ?? 'basic';
        $required_credits = ($mode === 'deep') ? 3 : 2;

        // 2. Check User Balance
        $current_balance = get_user_balance($conn, $user_id);
        if ($current_balance < $required_credits) {
            abort_json(403, "Insufficient credits. $mode analysis requires $required_credits credits, but you have $current_balance.");
        }

        // 3. Validate File Presence
        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            abort_json(400, "Audio file is missing or corrupted.");
        }

        // 4. Prepare Payload
        $payload = [
            'user_id'       => (int)$user_id, 
            'expected_text' => $_POST['expected_text'] ?? '',
            'mode'          => $mode,
            'required_cost' => $required_credits, // Pass cost to Flask so it can deduct accurately
            'audio'         => new CURLFile(
                $_FILES['audio']['tmp_name'],
                $_FILES['audio']['type'],
                $_FILES['audio']['name']
            )
        ];

        $use_json_content_type = false; 
        $endpoint = "/analyze";
        $timeout = 30; 
        break;
    case 'pronunciation_tips':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('pronunciation_tips', 5);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) abort_json(400, "Invalid request data.");

        $payload = [
            'user_id'       => (int)$user_id,
            'uid'           => htmlspecialchars($input['uid'] ?? ''),
            'expected_text' => htmlspecialchars($input['expected_text'] ?? ''),
            'spoken_text'   => htmlspecialchars($input['spoken_text'] ?? ''),
            'scores'        => $input['scores'] ?? []
        ];

        $endpoint = "/pronunciation-tips";
        $timeout = 20;
        break;
    case 'analyze_script':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('analyze_script', 2); // Prevent rapid-fire clicks

        // 1. Get the Test ID from Session (as per your original logic)
        $test_id = $_SESSION['active_test_id'] ?? null;
        if (!$test_id) {
            abort_json(400, "No active test found in session. Please restart the test.");
        }

        // 2. Fetch reference data from practice_sessions
        $stmt = $conn->prepare("SELECT transcript, audio_url FROM practice_sessions WHERE id = ?");
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $test_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$test_row) {
            abort_json(404, "Test data not found in database.");
        }

        // 3. Construct local path for Flask (XAMPP specific)
        $localPath = "C:\\xampp\\htdocs\\ieltsaireadinggenerator\\" . ltrim($test_row['audio_url'], '/');
        if (!str_ends_with(strtolower($localPath), '.mp3')) {
            $localPath .= '.mp3';
        }

        // 4. Validate the user's recorded audio upload
        $payload = [
        'user_id'       => (int)$user_id,
        'test_id'       => (int)$test_id,
        'audio_url'     => $localPath, 
        'expected_text' => $test_row['transcript']
    ];

        // Set flags for the dispatcher
        $use_json_content_type = false; // Must be false for file uploads
        $endpoint = "/analyze-script";
        $timeout = 45; // Analysis can take longer than generation
        break;
    case 'generate_game':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('generate_game', 5);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) abort_json(400, "Invalid request data.");

        $current_attempts = get_user_solo_attempts($conn, $user_id);
        if ($current_attempts < 1) {
            abort_json(403, "You have no solo attempts remaining. Please top up your points.");
        }

        // FIX: Added '?? ""' to prevent PHP warnings if the key is missing
        $allowed_diffs = ['easy', 'medium', 'hard'];
        $difficulty = in_array($input['difficulty'] ?? '', $allowed_diffs) ? $input['difficulty'] : 'easy';
        $level = (int)($input['level'] ?? 1);
        $num_questions = (int)($input['num_questions'] ?? 5);
        $focus = htmlspecialchars($input['focus'] ?? 'general');

        $payload = [
            'user_id'       => (int)$user_id,
            'difficulty'    => $difficulty,
            'level'         => $level,
            'num_questions' => $num_questions,
            'focus'         => $focus
        ];

        $endpoint = "/generatesinglegame-test";
        $timeout = 20; 
        break;
    case 'ai_review':
        if ($method !== 'POST') abort_json(405, "POST required");
        rate_limit('ai_review', 5); // 5-second cooldown

        $input = json_decode(file_get_contents('php://input'), true);
        $result_id = (int)($input['result_id'] ?? 0);
        $test_id   = (int)($input['test_id'] ?? 0);
        $test_type = htmlspecialchars($input['type'] ?? '');

        if (!$result_id || !$test_id) {
            abort_json(422, "Missing required assessment data.");
        }

        // --- SECURITY: IDOR PROTECTION ---
        // Verify this result actually belongs to the logged-in user
        $table = ($test_type === 'LISTENING') ? 'practice_results' : 'user_test_responses_all';
        $checkStmt = $conn->prepare("SELECT id FROM $table WHERE id = ? AND user_id = ? LIMIT 1");
        $checkStmt->bind_param("ii", $result_id, $user_id);
        $checkStmt->execute();
        if (!$checkStmt->get_result()->fetch_assoc()) {
            $checkStmt->close();
            abort_json(403, "Access Denied. Ownership verification failed.");
        }
        $checkStmt->close();

        // --- PRICING & BALANCE CHECK ---
        $required_credits = 1; 
        $current_balance = get_user_balance($conn, $user_id);
        
        if ($current_balance < $required_credits) {
            abort_json(403, "Insufficient credits. AI Review requires $required_credits credit.");
        }

        // Prepare Payload for Flask
        $payload = [
            'user_id'   => (int)$user_id,
            'test_id'   => $test_id,
            'result_id' => $result_id,
            'test_type' => $test_type,
            'cost'      => $required_credits
        ];

        $endpoint = "/analyze-performance"; // Matches your Python route
        $timeout = 20; 
        break;
    default:
        abort_json(404, "Unknown API action");
}

// 5. THE DISPATCHER (Unified cURL Execution)
$ch = curl_init($flask_base . $endpoint);

// Base Headers (Includes the Internal Security Key for Python)
$curl_headers = [
    'Accept: application/json',
    'X-Internal-Key: ' . $flask_internal_key
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);

    if ($use_json_content_type) {
        $json_payload = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        $curl_headers[] = 'Content-Type: application/json';
        $curl_headers[] = 'Content-Length: ' . strlen($json_payload);
    } else {
        // Multipart format (Files)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); 
    }
} else {
    curl_setopt($ch, CURLOPT_HTTPGET, true);
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 6. POST-PROCESSING & RESPONSE
if ($response === false) {
    abort_json(502, "AI Engine Offline or unreachable.", $curlError);
}

if ($httpCode !== 200 && $httpCode !== 202) {
    $errorData = json_decode($response, true);
    abort_json($httpCode, $errorData['error'] ?? "AI Generator Error ($httpCode)");
}

// Task Capture for Check_Status


$respData = json_decode($response, true);

// --- 1. HANDLE NEW GENERATION (INSERT) ---
// Add 'analyze_audio' to this list!
// --- 1. HANDLE NEW GENERATION (INSERT) ---
if (in_array($action, ['generate_listening', 'generate_reading', 'analyze_audio', 'pronunciation_tips','analyze_script', 'generate_game', 'ai_review']) && isset($respData['task_id'])) {
    $newTaskId = $respData['task_id'];
    
    // Update Session
    if (!isset($_SESSION['active_tasks'])) { $_SESSION['active_tasks'] = []; }
    $_SESSION['active_tasks'][] = $newTaskId;

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO user_tasks (user_id, task_id, task_type, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("iss", $user_id, $newTaskId, $action);
    $stmt->execute();
    $stmt->close();

    // FIX: Securely deduct 1 attempt ONLY if the AI successfully accepted the game generation task
    
}
// --- 2. HANDLE STATUS CHECKS (POLLING) ---
if ($action === 'check_status') {
    // Check if the task is still in progress (Real-time update)
    if (isset($respData['state']) && $respData['state'] === 'PROGRESS') {
        echo $response; 
        exit;
    }

    // Check if the task just finished successfully
    // Check if the task just finished successfully
    if (isset($respData['state']) && $respData['state'] === 'SUCCESS') {
        // FIX: Check for both test_id and mission_id
        $result_id = $respData['result']['mission_id'] ?? $respData['result']['test_id'] ?? null;
        
        $updateStmt = $conn->prepare("UPDATE user_tasks SET status = 'completed', result_id = ? WHERE task_id = ?");
        $updateStmt->bind_param("is", $result_id, $taskId); 
        $updateStmt->execute();
        $updateStmt->close();

        // Cleanup Session
        if (($key = array_search($taskId, $_SESSION['active_tasks'])) !== false) {
            unset($_SESSION['active_tasks'][$key]);
        }
    }
    
}

// 7. FINAL EXIT
if ($conn) $conn->close();
echo $response;
?>