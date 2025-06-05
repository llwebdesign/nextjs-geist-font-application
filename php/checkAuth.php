<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

try {
    $stmt = $pdo->prepare("
        SELECT id, username, email, profile_picture, theme 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        die(json_encode(['success' => false, 'message' => 'User not found']));
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (PDOException $e) {
    error_log("Check Auth Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Authentication check failed']));
}
?>
