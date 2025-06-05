<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['chat_id']) || !isset($data['is_typing'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$chat_id = $data['chat_id'];
$user_id = $_SESSION['user_id'];
$is_typing = $data['is_typing'] ? 1 : 0;

try {
    // Verify user has access to this chat
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM chats c 
        LEFT JOIN group_members gm ON c.id = gm.chat_id 
        WHERE c.id = ? AND (
            (c.type = 'single' AND EXISTS (
                SELECT 1 FROM messages m 
                WHERE m.chat_id = c.id 
                AND (m.sender_id = ? OR m.sender_id IN (
                    SELECT sender_id FROM messages 
                    WHERE chat_id = c.id 
                    AND sender_id != ?
                ))
            ))
            OR 
            (c.type = 'group' AND gm.user_id = ?)
        )
    ");
    $stmt->execute([$chat_id, $user_id, $user_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        die(json_encode(['success' => false, 'message' => 'Access denied to this chat']));
    }

    // Update or insert typing status
    $stmt = $pdo->prepare("
        INSERT INTO typing_status (chat_id, user_id, is_typing, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
        is_typing = VALUES(is_typing),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$chat_id, $user_id, $is_typing]);

    // Get all users currently typing in this chat
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.id
        WHERE ts.chat_id = ?
        AND ts.user_id != ?
        AND ts.is_typing = 1
        AND ts.updated_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $stmt->execute([$chat_id, $user_id]);
    $typing_users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'typing_users' => $typing_users
        ]
    ]);

} catch (PDOException $e) {
    error_log("Typing Status Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Failed to update typing status']));
}
?>
