<?php
// games/api/get_match_status.php
header('Content-Type: application/json');

// 1. Connection
$conn = new mysqli("localhost", "root", "", "aireadinggenerator");

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$match_id = $_GET['match_id'] ?? '';

// 2. Check the match status in your database
// Note: Ensure your 'matches' table has a 'status' column (e.g., 'waiting', 'ready')
$stmt = $conn->prepare("SELECT status FROM matches WHERE match_id = ?");
$stmt->bind_param("s", $match_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// 3. Return the status to JavaScript
if ($result) {
    echo json_encode(['status' => $result['status']]);
} else {
    echo json_encode(['status' => 'not_found']);
}