<?php
// trigger_task.php
session_start();
include './localhost.php'; // Your MySQL connection

$user_id = $_SESSION['user_id'];
$type = $_GET['type']; // 'listening', 'reading', etc.

// 1. Check if the user already has a pending task (Safety First!)
$stmt = $pdo->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'You already have a task in progress.']);
    exit;
}

// 2. Call Celery (Using a simple Redis/AMQP library or a shell command for now)
// Replace this with your actual Celery calling logic
$task_id = shell_exec("python3 trigger_celery.py $type $user_id"); 
$task_id = trim($task_id);

// 3. Insert into your new table
$stmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id, task_type, status) VALUES (?, ?, ?, 'pending')");
$stmt->execute([$user_id, $task_id, $type]);

echo json_encode(['task_id' => $task_id]);