<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect them away or show a 403 error
    http_response_code(403);
    echo "Direct access is forbidden.";
    exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
include "../localhost.php"; 

$user_id = $_SESSION['user_id'] ?? null; // Move this up
$headers = apache_request_headers(); // Add this
$csrf_token = $headers['X-CSRF-TOKEN'] ?? '';

// 1. Verify Session AND CSRF Token
if (!$user_id || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(["error" => "Security verification failed."]);
    exit;
}


$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// --- NEW: RATE LIMITING (Cooldown) ---
// Prevents the same user from spamming the AI every 1 second
$last_request = $_SESSION['last_preview_time'] ?? 0;
if (time() - $last_request < 3) { // 3-second cooldown
    echo json_encode(["error" => "Please wait a few seconds between previews."]);
    exit;
}
$_SESSION['last_preview_time'] = time();

$inputData = json_decode(file_get_contents('php://input'), true);

// Get plan from DB
$stmt = $conn->prepare("SELECT plan_type FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc()['plan_type'];

$isPro = ($plan === 'pro');

$sanitized = [
    'user_id'          => (int)$user_id,
    'voice_id'         => htmlspecialchars($inputData['voice_id'] ?? ''),
    // FORCE defaults if not Pro, even if they bypassed the UI sliders
    'stability'        => $isPro ? (float)($inputData['stability'] ?? 0.5) : 0.5,
    'similarity_boost' => $isPro ? (float)($inputData['similarity_boost'] ?? 0.75) : 0.75,
    'style'            => $isPro ? (float)($inputData['style'] ?? 0.0) : 0.0,
    'speed'            => $isPro ? (float)($inputData['speed'] ?? 1.0) : 1.0
];

// Forward the CLEAN, SANITIZED data to Python
$ch = curl_init('http://127.0.0.1:5000/preview-voice');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sanitized));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$pythonResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($pythonResponse === false || $httpCode !== 200) {
    echo json_encode(["error" => "AI Engine busy."]);
    exit;
}

echo $pythonResponse;