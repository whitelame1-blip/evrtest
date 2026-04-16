<?php
// api/update_live_score.php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Pusher\Pusher;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$data = json_decode(file_get_contents('php://input'), true);

$match_id  = $data['match_id'] ?? null;
$user_id   = $data['user_id'] ?? null;
$current_q = $data['current_q'] ?? 1; // Default to question 1

if (!$match_id || !$user_id) {
    exit(json_encode(['success' => false, 'message' => 'Missing data']));
}

$options = ['cluster' => 'ap3', 'useTLS' => true];
$pusher = new Pusher(
    "0ebdc1f9053f6e8f9965", 
    "8f8f9545a60efc26b30e", 
    "2123863", 
    $options
);

// We trigger 'score-update' (or you can rename it to 'progress-update')
// We only send the user_id and the question they are currently on.
$pusher->trigger("match-$match_id", 'score-update', [
    'user_id'   => $user_id,
    'current_q' => $current_q
]);

echo json_encode(['success' => true]);