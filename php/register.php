<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$username = trim($data['username']);
$email = trim($data['email']);
$password = $data['password'];

// Validate input
if (strlen($username) < 3 || strlen($username) > 50) {
    die(json_encode(['success' => false, 'message' => 'Username must be between 3 and 50 characters']));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['success' => false, 'message' => 'Invalid email format']));
}

if (strlen($password) < 6) {
    die(json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']));
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        die(json_encode(['success' => false, 'message' => 'Email already registered']));
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        die(json_encode(['success' => false, 'message' => 'Username already taken']));
    }

    // Hash password and insert user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword]);

    $userId = $pdo->lastInsertId();

    // Start session and return success
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email
        ]
    ]);

} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Registration failed']));
}
?>
