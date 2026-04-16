<?php
// save_final_results.php
session_start();
require __DIR__ . '/../vendor/autoload.php';

// Use the correct namespace
use Pusher\Pusher;

$data = json_decode(file_get_contents('php://input'), true);
$match_id = $data['match_id'];
$score = $data['score'];
$user_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "aireadinggenerator");

// 1. Check if user is Player 1 or Player 2
$stmt = $conn->prepare("SELECT p1_id, p2_id FROM matches WHERE match_id = ?");
$stmt->bind_param("s", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();

if ($match) {
    $is_p1 = ($match['p1_id'] == $user_id);
    $finished_col = $is_p1 ? "p1_finished" : "p2_finished";
    $score_col = $is_p1 ? "p1_score" : "p2_score";

    // 2. Mark as finished in 'matches' table
    $update = $conn->prepare("UPDATE matches SET $finished_col = 1, $score_col = ? WHERE match_id = ?");
    $update->bind_param("is", $score, $match_id);
    $update->execute();

    // 3. Save to match_results table
    $res_stmt = $conn->prepare("INSERT INTO match_results (match_id, user_id, score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE score = ?");
    $res_stmt->bind_param("siii", $match_id, $user_id, $score, $score);
    $res_stmt->execute();

    // 4. Notify the Rival via Pusher
    try {
        $options = ['cluster' => 'ap3', 'useTLS' => true];
        // Notice the backslash or the "use" statement above
        $pusher = new Pusher(
            "0ebdc1f9053f6e8f9965", 
            "8f8f9545a60efc26b30e", 
            "2123863", 
            $options
        );

        $pusher->trigger("match-$match_id", 'rival-finished', ['finished_user_id' => $user_id]);
    } catch (Exception $e) {
        // Log error if needed: error_log($e->getMessage());
    }

    echo json_encode(['success' => true]);
}