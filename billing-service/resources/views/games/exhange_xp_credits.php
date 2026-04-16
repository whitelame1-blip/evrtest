<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "aireadinggenerator";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $xp_cost = (int)$data['xp_cost'];
    $credits_to_add = (int)$data['credits_awarded'];
    $user_id = $_SESSION['user_id'];

    $pdo->beginTransaction();

    // 1. Check if user has enough XP in game_users table
    $stmt = $pdo->prepare("SELECT current_xp FROM game_users WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || $user['current_xp'] < $xp_cost) {
        throw new Exception("Insufficient XP balance.");
    }

    // 2. Deduct XP from game_users
    $deductXp = $pdo->prepare("UPDATE game_users SET current_xp = current_xp - ? WHERE user_id = ?");
    $deductXp->execute([$xp_cost, $user_id]);

    // 3. Add to balance in credits table
    // Using an UPDATE statement assuming the row already exists for the user
    $addCredits = $pdo->prepare("UPDATE credits SET balance = balance + ? WHERE user_id = ?");
    $addCredits->execute([$credits_to_add, $user_id]);

    // Safety check: if the user doesn't have a row in 'credits' yet, insert it
    if ($addCredits->rowCount() == 0) {
        $insertCredits = $pdo->prepare("INSERT INTO credits (user_id, balance) VALUES (?, ?)");
        $insertCredits->execute([$user_id, $credits_to_add]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>