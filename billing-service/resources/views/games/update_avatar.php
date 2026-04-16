<?php
session_start();
header('Content-Type: application/json');

// 1. Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "aireadinggenerator";

try {
    // 2. Security Check: Is the user logged in?
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized Access: Session Expired");
    }

    // 3. Data Check: Did we get a URL?
    if (!isset($_POST['avatar_url']) || empty($_POST['avatar_url'])) {
        throw new Exception("Signal Lost: No avatar data received");
    }

    $user_id = $_SESSION['user_id'];
    $avatar_url = $_POST['avatar_url'];

    // 4. Connect and Execute
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE game_users SET avatar_url = ? WHERE user_id = ?");
    $result = $stmt->execute([$avatar_url, $user_id]);

    if ($result) {
        echo json_encode([
            "success" => true,
            "message" => "Neural Link Synchronized",
            "new_url" => $avatar_url
        ]);
    }

} catch (Exception $e) {
    // Return error in JSON format so the JavaScript can catch it
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>