<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$email = trim($data['email']);
$password = $data['password'];

try {
    // Get user by email
    $stmt = $pdo->prepare("SELECT id, username, email, password, profile_picture, theme FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        die(json_encode(['success' => false, 'message' => 'Invalid email or password']));
    }

    // Remove password from user array before sending to client
    unset($user['password']);

    // Start session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    // Update last login time (optional)
    $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ]);

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Login failed']));
}
?>
