<?php
session_start();
include '../localhost.php';

// 1. Security Check
$logged_in_user = filter_var($_SESSION['user_id'] ?? 0, FILTER_VALIDATE_INT);
$mission_id = filter_input(INPUT_GET, 'mission_id', FILTER_VALIDATE_INT);
$token = $_GET['token'] ?? '';

if (!$logged_in_user || !$mission_id || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    die("Uplink Error: Unauthorized Abort Request.");
}

// 2. Database Update
// We set status to 'completed' and score to 0 because they quit.
$stmt = $conn->prepare("
    UPDATE game_missions 
    SET status = 'completed', 
        score = 0, 
        completed_at = NOW(),
        last_answers = 'ABORTED' 
    WHERE mission_id = ? AND user_id = ? AND status = 'active'
");

$stmt->bind_param("ii", $mission_id, $logged_in_user);

if ($stmt->execute()) {
    // 3. Redirect back to the selection screen
    header("Location: single_play.php?message=mission_aborted");
} else {
    echo "System Error: Could not terminate mission link.";
}

$stmt->close();
$conn->close();