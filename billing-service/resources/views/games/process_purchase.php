<?php
// 1. Initialization & Security Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

include '../localhost.php'; // Ensure $conn is available

// --- HELPERS (Consistent with your Bridge) ---
function abort_json($code, $message) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

// 2. Authentication & CSRF Firewall
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    abort_json(401, "Unauthorized access. Please log in.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_json(405, "Method Not Allowed");
}

// CSRF check: Matches the logic in your bridge.php
$headers = apache_request_headers();
$client_csrf = $headers['X-CSRF-TOKEN'] ?? $_POST['csrf_token'] ?? '';

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_csrf)) {
    error_log("CSRF Attack Blocked on Credit Exchange for User ID: $user_id");
    abort_json(403, "Security verification failed. Invalid CSRF token.");
}

// 3. Input Validation
$credits_to_spend = isset($_POST['credits']) ? intval($_POST['credits']) : 0;
$attempts_to_grant = isset($_POST['attempts']) ? intval($_POST['attempts']) : 0;

if ($credits_to_spend <= 0 || $attempts_to_grant <= 0) {
    abort_json(400, "Invalid transaction amounts.");
}

// 4. Secure Transaction Logic
$conn->begin_transaction();

try {
    // A. Check Balance (FOR UPDATE locks the row to prevent race conditions)
    $checkSql = "SELECT balance FROM credits WHERE user_id = ? FOR UPDATE";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();

    if (!$wallet || $wallet['balance'] < $credits_to_spend) {
        throw new Exception("Insufficient credits balance.");
    }

    // B. Deduct Credits
    $deductSql = "UPDATE credits SET balance = balance - ? WHERE user_id = ?";
    $stmtDeduct = $conn->prepare($deductSql);
    $stmtDeduct->bind_param("ii", $credits_to_spend, $user_id);
    if (!$stmtDeduct->execute()) throw new Exception("Failed to deduct credits.");

    // C. Grant Attempts
    $grantSql = "UPDATE credits SET game_solo_attempts = game_solo_attempts + ? WHERE user_id = ?";
    $stmtGrant = $conn->prepare($grantSql);
    $stmtGrant->bind_param("ii", $attempts_to_grant, $user_id);
    if (!$stmtGrant->execute()) throw new Exception("Failed to grant attempts.");

    // D. Finalize
    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Transaction Complete',
        'new_balance' => ($wallet['balance'] - $credits_to_spend)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Credit Exchange Error: " . $e->getMessage());
    abort_json(500, $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($stmtDeduct)) $stmtDeduct->close();
    if (isset($stmtGrant)) $stmtGrant->close();
    $conn->close();
}