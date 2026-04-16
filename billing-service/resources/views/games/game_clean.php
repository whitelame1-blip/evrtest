<?php
// 1. Initialization & Security Headers
ini_set('display_errors', 0); // Hide errors from users in production
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// --- CORE HELPERS ---
function abort_json($code, $message) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

// 2. Global Authentication Check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    abort_json(401, "Unauthorized access. Neural link severed.");
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 3. GLOBAL CSRF FIREWALL (For ALL POST Requests)
if ($method === 'POST') {
    $headers = apache_request_headers();
    $client_csrf = $headers['X-CSRF-TOKEN'] ?? '';
    
    // Fallback for FormData/URLSearchParams which sometimes don't send custom headers well
    if (empty($client_csrf) && isset($_POST['csrf_token'])) {
        $client_csrf = $_POST['csrf_token'];
    }

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_csrf)) {
        error_log("CSRF Attack Blocked for User ID: $user_id");
        abort_json(403, "Security verification failed. Invalid CSRF token.");
    }
}

// 4. Database Connection (PDO)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "aireadinggenerator";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    abort_json(500, "Database uplink failed.");
}

// 5. THE ROUTER
switch ($action) {

    // ---------------------------------------------------------
    // ACTION: EXCHANGE XP FOR CREDITS
    // ---------------------------------------------------------
    // ---------------------------------------------------------
    // ACTION: EXCHANGE XP FOR CREDITS
    // ---------------------------------------------------------
    case 'exchange_xp':
        if ($method !== 'POST') abort_json(405, "POST required");
        
        $input = json_decode(file_get_contents('php://input'), true);
        $xp_cost = isset($input['xp_cost']) ? (int)$input['xp_cost'] : 0;
        
        // --- SECURITY VALIDATION ---
        // 1. Fetch user plan to determine pricing
        $stmtUser = $pdo->prepare("SELECT plan_type FROM users WHERE id = ?"); // Adjust table/column names to match your DB
        $stmtUser->execute([$user_id]);
        $user_row = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $is_pro = ($user_row && $user_row['plan_type'] === 'pro');

        // 2. Define the ONLY allowed price points
        // Structure: [XP_COST => CREDITS_AWARDED]
        $valid_packs = $is_pro 
            ? [3200 => 5, 8000 => 10]   // Pro Pricing
            : [4000 => 5, 10000 => 10]; // Free Pricing

        // 3. Check if the requested XP cost exists in our allowed list
        if (!array_key_exists($xp_cost, $valid_packs)) {
            abort_json(403, "Invalid transaction request. Neural link interference detected.");
        }

        // 4. Set the credits based on our SERVER-SIDE list, NOT the user input
        $credits_to_add = $valid_packs[$xp_cost];

        try {
            $pdo->beginTransaction();

            // A. Check actual XP balance
            $stmt = $pdo->prepare("SELECT current_xp FROM game_users WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$user_id]);
            $user_game_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_game_data || (int)$user_game_data['current_xp'] < $xp_cost) {
                throw new Exception("Insufficient XP resources.");
            }

            // B. Deduct XP
            $stmtDeduct = $pdo->prepare("UPDATE game_users SET current_xp = current_xp - ? WHERE user_id = ?");
            $stmtDeduct->execute([$xp_cost, $user_id]);

            // C. Add to credits
            $stmtUpdate = $pdo->prepare("UPDATE credits SET balance = balance + ? WHERE user_id = ?");
            $stmtUpdate->execute([$credits_to_add, $user_id]);
            
            if ($stmtUpdate->rowCount() === 0) {
                $checkExist = $pdo->prepare("SELECT user_id FROM credits WHERE user_id = ?");
                $checkExist->execute([$user_id]);
                if (!$checkExist->fetch()) {
                    $stmtInsert = $pdo->prepare("INSERT INTO credits (user_id, balance) VALUES (?, ?)");
                    $stmtInsert->execute([$user_id, $credits_to_add]);
                }
            }

            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Transfer complete!',
                'added' => $credits_to_add
            ]);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            abort_json(500, $e->getMessage());
        }
        break;
    // ---------------------------------------------------------
    // ACTION: UPDATE AVATAR
    // ---------------------------------------------------------
    case 'update_avatar':
        if ($method !== 'POST') abort_json(405, "POST required");

        $avatar_url = $_POST['avatar_url'] ?? '';
        if (empty($avatar_url)) {
            $data = json_decode(file_get_contents('php://input'), true);
            $avatar_url = $data['avatar_url'] ?? '';
        }

        if (empty($avatar_url)) {
            abort_json(400, "Signal Lost: No avatar data received.");
        }

        // Security Check: Ensure the URL is actually a valid avatar source
        if (strpos($avatar_url, 'dicebear.com') === false && strpos($avatar_url, 'assets/avatars/') === false) {
            abort_json(403, "Unauthorized avatar source detected.");
        }

        try {
            $stmt = $pdo->prepare("UPDATE game_users SET avatar_url = ? WHERE user_id = ?");
            $stmt->execute([$avatar_url, $user_id]);

            echo json_encode([
                "success" => true,
                "message" => "Neural Link Synchronized",
                "new_url" => $avatar_url
            ]);
        } catch (Exception $e) {
            abort_json(500, "Failed to update operator profile.");
        }
        break;
    // ---------------------------------------------------------
    // ACTION: GET USER STATS (Manual Sync)
    // ---------------------------------------------------------
    case 'get_user_stats':
        // This is a GET request, so it skips the POST CSRF firewall
        if ($method !== 'GET') abort_json(405, "GET required for sync.");

        try {
            $stmt = $pdo->prepare("SELECT current_xp, current_xp_arena, total_xp_accumulated FROM game_users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats) {
                abort_json(404, "User data not found.");
            }

            echo json_encode($stats);
        } catch (Exception $e) {
            abort_json(500, "Failed to retrieve neural stats.");
        }
        break;
    // Add your other endpoints here later!
    // case 'process_purchase': ...
    // case 'get_user_stats': ...

    default:
        abort_json(404, "Unknown API action.");
}
?>
