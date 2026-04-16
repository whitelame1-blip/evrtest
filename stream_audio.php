<?php
include "localhost.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION['user_id'] ?? null;
$token = $_GET['token'] ?? null; // Use the token for maximum security

if (!$user_id || !$token) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// 1. Verify access in DB
$stmt = $conn->prepare("SELECT audio_url FROM practice_sessions WHERE access_token = ? AND user_id = ?");
$stmt->bind_param("si", $token, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$file_path = $result['audio_url']; // e.g., "static/myaudio.mp3"

// 2. Stream the file
if (file_exists($file_path)) {
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . filesize($file_path));
    header('Accept-Ranges: bytes'); // Crucial for skipping/seeking in the player
    readfile($file_path);
    exit;
} else {
    header("HTTP/1.1 404 Not Found");
}