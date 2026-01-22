<?php
// social_login_handler.php
session_start();
require __DIR__ . '/vendor/autoload.php'; // Load Composer libraries
include __DIR__ . '/db_connect.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

// 1. Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['idToken'])) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

$idTokenString = $data['idToken'];
$displayName = $data['displayName'] ?? 'User';

// 2. Initialize Firebase Admin (The PHP equivalent of your snippet)
try {
    $factory = (new Factory)
        ->withServiceAccount(__DIR__ . '/serviceAccountKey.json'); // path to your JSON key
        
    $auth = $factory->createAuth();

    // 3. Verify the Token
    // This securely checks with Google servers if the token is valid
    $verifiedIdToken = $auth->verifyIdToken($idTokenString);
    
    // Get the email and UID securely from the verified token
    $uid = $verifiedIdToken->claims()->get('sub');
    $email = $verifiedIdToken->claims()->get('email');

} catch (FailedToVerifyToken $e) {
    echo json_encode(['success' => false, 'message' => 'The token is invalid: ' . $e->getMessage()]);
    exit;
}

// 4. Database Login Logic (Same as before, but now secure)
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // --- LOGIN ---
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    echo json_encode(['success' => true, 'redirect' => 'index.php']);
} else {
    // --- REGISTER ---
    $random_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    
    try {
        $insertStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$displayName, $email, $random_password, '']);
        
        $newUserId = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['full_name'] = $displayName;
        
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    }
}
?>