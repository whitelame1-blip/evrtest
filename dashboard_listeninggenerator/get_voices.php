<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

// FIX: Ensure this matches the session variable you set at login (e.g., 'user_id' or 'email')
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized - Please log in"]);
    exit;
}

$ch = curl_init('http://127.0.0.1:5000/voices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode(["error" => "Voice service offline. Check if Python is running."]);
    exit;
}

echo $response;
?>