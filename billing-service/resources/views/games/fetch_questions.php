<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "aireadinggenerator");

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed"]));
}

$match_id = $_GET['match_id'] ?? '';

// Fetch questions for this specific match
$query = "SELECT question_text, option_a, option_b, option_c, option_d, correct_option 
          FROM arena_match_questions 
          WHERE match_id = ? 
          ORDER BY id ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $match_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

echo json_encode($questions);