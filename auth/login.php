<?php
// 1. Connection and Configuration
include '../localhost.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    session_start();
}

// 2. Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// 3. CSRF Protection
$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security verification failed!']);
    exit();
}

// 4. Rate Limit Check (Check if currently locked out)
if (isset($_SESSION['lockout_until']) && time() < $_SESSION['lockout_until']) {
    $seconds_left = $_SESSION['lockout_until'] - time();
    echo json_encode(['status' => 'error', 'message' => "Too many attempts. Please wait $seconds_left seconds."]);
    exit();
}

// 5. Input Extraction & Sanitization
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => '⚠️ All fields are required!']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit();
}

try {
    // 6. Database Operation
    $stmt = $conn->prepare("SELECT id, fullname, password, verified FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) throw new Exception("Database error.");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // 7. Password Verification
        if (password_verify($password, $user['password'])) {
            // 8. Account Status Check
            if ((int)$user['verified'] !== 1) {
                echo json_encode(['status' => 'error', 'message' => 'Please verify your email before logging in.']);
                exit();
            }

            // 9. Success! Reset attempts and regenerate session
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            unset($_SESSION['lockout_until']);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $email;

            echo json_encode(['status' => 'success', 'message' => 'Logged in!']);
            exit();
        }
    }

    // 10. Progressive Lockout Logic (Incorrect login path)
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $attempts = $_SESSION['login_attempts'];
    $lockout_time = 0;

    // Defined Schedule: 1-5 (none), 6 (30s), 7 (60s), 8+ (300s)
    if ($attempts == 6) {
        $lockout_time = 30;
    } elseif ($attempts == 7) {
        $lockout_time = 60;
    } elseif ($attempts >= 8) {
        $lockout_time = 300;
    }

    if ($lockout_time > 0) {
        $_SESSION['lockout_until'] = time() + $lockout_time;
        echo json_encode(['status' => 'error', 'message' => "Too many attempts. Locked for $lockout_time seconds."]);
    } else {
        $remaining = 6 - $attempts;
        echo json_encode(['status' => 'error', 'message' => "❌ Incorrect password or email! ($remaining attempts until lockout)"]);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred.']);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}