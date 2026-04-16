<?php

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include '../localhost.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Check CSRF
$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token expired. Refresh the page.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Final Server-Side Validation (The "Safety Net")
    if (strlen($fullname) < 2 || strlen($fullname) > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid name length.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    $hasComplexity = preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);
    if (strlen($password) < 8 || !$hasComplexity) {
        echo json_encode(['status' => 'error', 'message' => 'Password too weak.']);
        exit;
    }

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered.']);
        exit;
    }
    $v_code = bin2hex(random_bytes(32));
    // Begin Database Transaction
    $conn->begin_transaction();

    try {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $v_code = bin2hex(random_bytes(32));

        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, verification_code, verified) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $fullname, $email, $hashed, $v_code);
        $stmt->execute();
        $userId = $stmt->insert_id;

        // Credits & Setup
        $conn->query("INSERT INTO credits (user_id, balance) VALUES ($userId, 3)");
        $conn->query("INSERT INTO user_test_notifications (user_id) VALUES ($userId)");
        $conn->query("INSERT INTO credit_transactions (user_id, change_amount, reason) VALUES ($userId, 3, 'Signup Bonus')");

        // Email logic...
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'whitelame1@gmail.com'; 
        $mail->Password   = 'bwdc swqo jxqn xsvr';  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('whitelame1@gmail.com', 'Kolibri AI');
        $mail->addAddress($email, $fullname);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your email - evrtest.com';
        
        // Link points to your actual domain or localhost as needed
        // Change $verification_code to $v_code
        // Change this:

// To this:
        $verification_link = "http://localhost/ieltsaireadinggenerator/verify.php?email=" . urlencode($email) . "&token=$v_code";

        $mail->Body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
                <h2 style='color: #333;'>Welcome to Kolibri AI, $fullname!</h2>
                <p>Please click the button below to verify your email address and claim your 3 free credits:</p>
                <a href='$verification_link' style='display:inline-block; background:#2ecc71; color:#fff; padding:12px 25px; border-radius:5px; text-decoration:none; font-weight:bold;'>Verify My Account</a>
                <p style='font-size: 12px; color: #777; margin-top: 20px;'>If you did not create this account, please ignore this email.</p>
            </div>
        ";

       $mail->send();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Verification email sent!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'System error. Try again.']);
    }finally {
        if (isset($insertStmt)) $insertStmt->close();
        $conn->close();
    }
}