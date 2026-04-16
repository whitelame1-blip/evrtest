<?php
// MUST start session before using $_SESSION
session_start(); 

// Tell the browser and your JavaScript fetch() that we are returning JSON
header('Content-Type: application/json');

include '../localhost.php';
date_default_timezone_set('Asia/Seoul');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Safely grab the email
    $email = trim($_POST['email'] ?? '');
    
    // The generic success message prevents User Enumeration.
    // Hackers won't know if the email was real or not.
    $generic_success_msg = 'If an account with that email exists and is verified, we have sent reset instructions. (Valid for 15 minutes)';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, fullname, verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // If email doesn't exist OR isn't verified, silently stop but show success message
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'success', 'message' => $generic_success_msg]);
        exit;
    }

    $user = $result->fetch_assoc();

    if ($user['verified'] == 0) {
        // Optional: You could send them an email reminding them to verify instead
        echo json_encode(['status' => 'success', 'message' => $generic_success_msg]);
        exit;
    }

    // 1. Generate a secure random token (Plaintext sent in email)
    $reset_token = bin2hex(random_bytes(32)); 
    
    // 2. Hash the token (Saved in database)
    $token_hash = hash('sha256', $reset_token); 
    $reset_expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));

    $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    // Store the HASHED token
    $update->bind_param("sss", $token_hash, $reset_expires, $email);
    $update->execute();

    // Development Domain Link (Change back to evrtest.com if pushing to production)
    $reset_link = "http://localhost/ieltsaireadinggenerator/auth/reset_password.php?token=" . urlencode($reset_token);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'whitelame1@gmail.com';
        $mail->Password = 'bwdc swqo jxqn xsvr'; // Recommendation: Move this to a .env file later
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('whitelame1@gmail.com', 'Evrtest');
        $mail->addAddress($email, $user['fullname']);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your password - Evrtest.com';
        $mail->Body = "
    <div style='background-color: #f4f7f6; padding: 50px 0; font-family: Arial, sans-serif; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
            
            <div style='background: #2c3e50; padding: 20px; text-align: center;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px;'>Evrtest</h1>
            </div>

            <div style='padding: 40px; line-height: 1.6;'>
                <h2 style='color: #2c3e50; margin-top: 0;'>Password Reset Request</h2>
                <p>Hello, <strong>" . htmlspecialchars($user['fullname']) . "</strong>,</p>
                <p>We received a request to reset the password for your account. No changes have been made yet.</p>
                
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='$reset_link' style='background-color: #2ecc71; color: #ffffff; padding: 14px 28px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 2px 5px rgba(46,204,113,0.3);'>Reset My Password</a>
                </div>

                <p style='font-size: 14px; color: #e74c3c; text-align: center;'><strong>Note: This link will expire in 15 minutes.</strong></p>
                
                <hr style='border: none; border-top: 1px solid #eeeeee; margin: 30px 0;'>
                
                <p style='font-size: 13px; color: #7f8c8d;'>If you did not request this password reset, please ignore this email or contact support if you have concerns. Your account remains secure.</p>
            </div>

            <div style='background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #bdc3c7;'>
                &copy; " . date('Y') . " Evrtest Lab. All rights reserved.<br>
                High-stakes exam preparation made simple.
            </div>
        </div>
    </div>
";

        $mail->send();
        
        // Return success to the Javascript handler
        echo json_encode(['status' => 'success', 'message' => $generic_success_msg]);
        exit;

    } catch (Exception $e) {
        // Log $mail->ErrorInfo to a file in production, don't show to user
        echo json_encode(['status' => 'error', 'message' => 'Could not send reset email. Please try again later.']);
        exit;
    }
} else {
    // Failsafe for incorrect request methods (e.g., someone visits the URL directly via GET)
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
?>