
<?php
session_start();
include 'localhost.php';

$user_id = $_SESSION['user_id'];

// Get the last time the user checked notifications
$seenQuery = $conn->prepare("SELECT last_seen_at FROM user_test_notifications WHERE user_id = ?");
$seenQuery->bind_param("i", $user_id);
$seenQuery->execute();
$last_seen = $seenQuery->get_result()->fetch_assoc()['last_seen_at'] ?? null;

// --- 1. COUNT SAVED TESTS (reading + listening + full) ---
if ($last_seen === null) {
    $q_saved = $conn->prepare("
        SELECT (SELECT COUNT(*) FROM reading_tests WHERE user_id = ?) + 
               (SELECT COUNT(*) FROM practice_sessions WHERE user_id = ?) + 
               (SELECT COUNT(*) FROM full_test WHERE user_id = ?) AS total
    ");
    $q_saved->bind_param("iii", $user_id, $user_id, $user_id);
} else {
    $q_saved = $conn->prepare("
        SELECT (SELECT COUNT(*) FROM reading_tests WHERE user_id = ? AND created_at > ?) + 
               (SELECT COUNT(*) FROM practice_sessions WHERE user_id = ? AND created_at > ?) + 
               (SELECT COUNT(*) FROM full_test WHERE user_id = ? AND created_at > ?) AS total
    ");
    $q_saved->bind_param("isisis", $user_id, $last_seen, $user_id, $last_seen, $user_id, $last_seen);
}
$q_saved->execute();
$saved_total = $q_saved->get_result()->fetch_assoc()['total'];

// --- 2. COUNT COMPLETED TESTS (all response tables + mock results) ---
if ($last_seen === null) {
    $q_completed = $conn->prepare("
        SELECT (SELECT COUNT(*) FROM user_test_responses_all WHERE user_id = ?) + 
               (SELECT COUNT(*) FROM user_mock_results WHERE user_id = ?) AS total
    ");
    $q_completed->bind_param("ii", $user_id, $user_id);
} else {
    // Note: If listening completions are stored in a different table, add it here like the saved tests above.
    $q_completed = $conn->prepare("
        SELECT (SELECT COUNT(*) FROM user_test_responses_all WHERE user_id = ? AND submission_time > ?) + 
               (SELECT COUNT(*) FROM user_mock_results WHERE user_id = ? AND submission_time > ?) AS total
    ");
    $q_completed->bind_param("isis", $user_id, $last_seen, $user_id, $last_seen);
}
$q_completed->execute();
$completed_total = $q_completed->get_result()->fetch_assoc()['total'];

// Return both counts to JavaScript
echo json_encode([
    "new_tests" => intval($saved_total),
    "new_completed" => intval($completed_total)
]);
?>