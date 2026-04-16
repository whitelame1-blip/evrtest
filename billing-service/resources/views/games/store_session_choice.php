<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['question_index']) && isset($data['choice'])) {
    // Store in an array inside the session
    $_SESSION['temp_match_choices'][$data['question_index']] = $data['choice'];
}
echo json_encode(['status' => 'stored']);