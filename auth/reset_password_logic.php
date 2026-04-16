<?php
session_start();
require_once '../localhost.php'; 
date_default_timezone_set('Asia/Seoul');

// Helper function to return JSON and exit
function sendJsonResponse($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ==========================================
// 1. HANDLE FORM SUBMISSION (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $token = $_POST['token'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Verify CSRF Token
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        sendJsonResponse('error', 'Security session expired. Please refresh the page.');
    }

    if (empty($token) || empty($new_password)) {
        sendJsonResponse('error', 'Missing required fields.');
    }

    if ($new_password !== $confirm_password) {
        sendJsonResponse('error', 'Passwords do not match.');
    }

    // Validate password strength (Server-side safety net)
    if (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        sendJsonResponse('error', 'Password does not meet security requirements.');
    }

    $token_hash = hash('sha256', $token);

    // Verify token exists and is valid
    $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        sendJsonResponse('error', 'This link is invalid or has already been used.');
    }

    if (strtotime($user['reset_expires']) < time()) {
        sendJsonResponse('error', 'This link has expired.');
    }

    // UPDATE LOGIC: We don't check if it's the same as the old one. 
    // We just hash and update. This satisfies your requirement to allow "old" passwords.
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $update->bind_param("si", $hashedPassword, $user['id']);
    
    if ($update->execute()) {
        $_SESSION['message'] = "Your password has been updated successfully. You can now log in with your new credentials.";
    $_SESSION['type'] = 'success';
    $_SESSION['redirect'] = 'login.php'; // Where the "Back" button should go

    unset($_SESSION['csrf_token']); 
    sendJsonResponse('success', 'Redirecting...');
    } else {
        sendJsonResponse('error', 'Database error. Please try again later.');
    }
}

// ==========================================
// 2. HANDLE INITIAL PAGE LOAD (GET)
// ==========================================
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Access Denied: No token provided.");
}

$token_hash = hash('sha256', $token);
$stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If the link is bad, we show the "Access Denied" screen immediately
if (!$user || strtotime($user['reset_expires']) < time()) {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h2>Link Invalid or Expired</h2>
            <p>Please request a new password reset link.</p>
            <a href='forgot-password.php'>Go Back</a>
         </div>");
}

// If we got here, the link is valid. Prepare the form:
$secondsRemaining = strtotime($user['reset_expires']) - time();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>