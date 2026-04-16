<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(["error" => "Direct access is forbidden."]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

include "../localhost.php"; 

// --- SECURITY LAYER 1: Session & CSRF Verification ---
$user_id = $_SESSION['user_id'] ?? null;
$headers = apache_request_headers(); 
$client_csrf_token = $headers['X-CSRF-TOKEN'] ?? '';

// Check if user is logged in AND if the tokens match perfectly
if (!$user_id || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_csrf_token)) {
    http_response_code(403);
    echo json_encode(["error" => "Security verification failed. Invalid or missing CSRF token."]);
    exit;
}

// --- SECURITY LAYER 2: Cooldown Guard ---
$last_gen = $_SESSION['last_gen_time'] ?? 0;
if (time() - $last_gen < 10) {
    echo json_encode(["error" => "Please wait a moment before generating another test."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(["error" => "Invalid request data."]);
    exit;
}

// --- SECURITY LAYER 3: Credit Check ---
$stmt = $conn->prepare("SELECT balance FROM credits WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user || $user['balance'] < 1) {
    echo json_encode(["error" => "Insufficient credits."]);
    exit;
}

// Force the user ID from the secure session, ignoring anything sent by the frontend
$input['user_id'] = (int)$user_id;

// --- Forward to Flask ---
$ch = curl_init('http://127.0.0.1:5000/generate/section');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));

// Fast timeout because Flask should answer immediately
curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$pythonResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($pythonResponse === false) {
    echo json_encode(["error" => "AI Engine Offline: " . $curlError]);
    exit;
}

// ACCEPT 200 or 202 (Accepted for processing)
if ($httpCode !== 200 && $httpCode !== 202) {
    $errorData = json_decode($pythonResponse, true);
    echo json_encode([
        "error" => $errorData['error'] ?? "AI Generator Error ($httpCode)"
    ]);
    exit;
}

// Success: Update cooldown and return task_id
$_SESSION['last_gen_time'] = time();
echo $pythonResponse; 

$conn->close();
?>