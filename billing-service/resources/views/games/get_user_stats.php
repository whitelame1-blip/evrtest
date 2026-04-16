<?php
// games/get_user_stats.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Database Connection
$pdo = new PDO("mysql:host=localhost;dbname=aireadinggenerator", "root", "");

$stmt = $pdo->prepare("SELECT current_xp, current_xp_arena, total_xp_accumulated FROM game_users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($stats);