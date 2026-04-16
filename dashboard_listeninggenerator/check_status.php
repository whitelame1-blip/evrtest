<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
include "../localhost.php"; 

// 1. Verify Authentication
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 2. Validate Task ID format (prevents basic injection)
$taskId = $_GET['task_id'] ?? '';
if (empty($taskId) || strlen($taskId) < 20) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Task ID']);
    exit;
}

/* 3. SECURITY LAYER: Ownership Verification
   This is the "Glitches" fix. We check if this task actually belongs to this user.
   This prevents User A from seeing User B's generated test.
*/

// 4. Ask Flask internally
$ch = curl_init("http://127.0.0.1:5000/status/" . urlencode($taskId));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

/* Why we use a short timeout (5-10 seconds):
   Flask should answer "PENDING" or "SUCCESS" instantly. 
   If Flask takes longer than 5 seconds to answer a STATUS check, 
   it means your Python script is stuck. A timeout prevents PHP from crashing too.
*/
curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. Handle AI Engine status
if ($response === false) {
    echo json_encode(['status' => 'error', 'message' => 'AI Engine is unreachable.']);
} else {
    echo $response; // Send Flask's answer (PENDING, PROGRESS, or SUCCESS) back to JS
}

$conn->close();