<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'localhost.php';

// Get both parameters for better security
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!empty($token) && !empty($email)) {
    
    // 1. Select user by BOTH email and token
    $stmt = $conn->prepare("SELECT id, verified FROM users WHERE email = ? AND verification_code = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ((int)$user['verified'] === 1) {
            $_SESSION['message'] = "Your email is already verified. Please log in.";
            $_SESSION['type'] = "info";
        } else {
            // 2. Verify the account and clear the code
            $update = $conn->prepare("UPDATE users SET verified = 1, verification_code = NULL WHERE id = ?");
            $update->bind_param("i", $user['id']);
            
            if ($update->execute()) {
                $_SESSION['message'] = "✅ Email verified successfully! You can now log in.";
                $_SESSION['type'] = "success";
            } else {
                $_SESSION['message'] = "❌ System error during verification. Please contact support.";
                $_SESSION['type'] = "error";
            }
            $update->close();
        }
    } else {
        // Log this attempt if you want to track malicious scanning
        $_SESSION['message'] = "❌ Invalid or expired verification link.";
        $_SESSION['type'] = "error";
    }
    $stmt->close();
} else {
    $_SESSION['message'] = "⚠️ Missing verification details.";
    $_SESSION['type'] = "error";
}

$conn->close();
header("Location: message.php");
exit;