<?php
require __DIR__ . '/../vendor/autoload.php';
use Pusher\Pusher; 

$conn = new mysqli("localhost", "root", "", "aireadinggenerator");

$options = ['cluster' => 'ap3', 'useTLS' => true];
$pusher = new Pusher("0ebdc1f9053f6e8f9965", "8f8f9545a60efc26b30e", "2123863", $options);

$data = json_decode(file_get_contents('php://input'), true);
$my_id = $data['user_id'];

// 1. Check for an opponent
$query = "SELECT user_id FROM arena_queue WHERE status = 'searching' AND user_id != ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $opponent_id = $row['user_id'];
    $match_id = "match_" . bin2hex(random_bytes(8));

    // 2. Fetch Stats & Settings for both players
    $u_query = "SELECT u.id, u.plan_type, s.rank_points, s.pref_topic, s.pref_type, s.pref_diff 
                FROM users u 
                LEFT JOIN user_arena_settings s ON u.id = s.user_id 
                WHERE u.id IN ($my_id, $opponent_id)";
    $u_res = $conn->query($u_query);
    
    $players = [];
    while($u = $u_res->fetch_assoc()) {
        $players[$u['id']] = $u;
    }

    $p1 = $players[$my_id];
    $p2 = $players[$opponent_id];

    // 3. Resolve Parameters & Identify "Deadlock"
    $is_deadlock = false;
    $final = ['topic' => 'random', 'type' => 'mixed', 'diff' => 'adaptive'];

    if ($p1['plan_type'] === 'pro' && $p2['plan_type'] !== 'pro') {
        $final = ['topic' => $p1['pref_topic'], 'type' => $p1['pref_type'], 'diff' => $p1['pref_diff']];
    } elseif ($p2['plan_type'] === 'pro' && $p1['plan_type'] !== 'pro') {
        $final = ['topic' => $p2['pref_topic'], 'type' => $p2['pref_type'], 'diff' => $p2['pref_diff']];
    } else {
        // Both are Pro or both are Free
        if ($p1['rank_points'] == $p2['rank_points'] && $p1['plan_type'] === 'pro') {
            $is_deadlock = true; 
        }
        $winner = ($p1['rank_points'] >= $p2['rank_points']) ? $p1 : $p2;
        $final = ['topic' => 'random', 'type' => 'mixed', 'diff' => 'adaptive'];
    }

    // 4. Create Match Record
    $m_stmt = $conn->prepare("INSERT INTO matches (match_id, p1_id, p2_id, topic, type, difficulty, status) VALUES (?, ?, ?, ?, ?, ?, 'processing')");
    $m_stmt->bind_param("siisss", $match_id, $my_id, $opponent_id, $final['topic'], $final['type'], $final['diff']);
    $m_stmt->execute();

    // 5. Update Queue
    $conn->query("UPDATE arena_queue SET status = 'matched', match_id = '$match_id' WHERE user_id IN ($my_id, $opponent_id)");

    // 6. Ping Python (arena.py) - Skip if deadlock to let them decide first, or ping now as default
    if (!$is_deadlock) {
        $py_url = "http://127.0.0.1:5000/generate_arena_questions";
        $py_payload = json_encode(['match_id' => $match_id, 'topic' => $final['topic'], 'type' => $final['type'], 'diff' => $final['diff']]);
        
        $ch = curl_init($py_url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $py_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_exec($ch);
        curl_close($ch);
    }

    // 7. Notify Rival via Pusher
    $pusher->trigger("user-notification-$opponent_id", 'match-found', [
        'match_id' => $match_id,
        'is_deadlock' => $is_deadlock
    ]);

    echo json_encode(['status' => 'matched', 'match_id' => $match_id, 'is_deadlock' => $is_deadlock]);

} else {
    // Join Queue logic
    $stmt = $conn->prepare("INSERT INTO arena_queue (user_id, status) VALUES (?, 'searching') ON DUPLICATE KEY UPDATE status='searching'");
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    echo json_encode(['status' => 'waiting']);
}