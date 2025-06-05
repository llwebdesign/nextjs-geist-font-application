<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['chat_id']) || !isset($data['message'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$chat_id = $data['chat_id'];
$sender_id = $_SESSION['user_id'];
$message = trim($data['message']);
$media = isset($data['media']) ? $data['media'] : null;

try {
    // Verify user is part of this chat
    $stmt = $pdo->prepare("
        SELECT c.type 
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
    $stmt->execute([$chat_id, $sender_id, $sender_id, $sender_id]);

    if ($stmt->rowCount() === 0) {
        die(json_encode(['success' => false, 'message' => 'Access denied to this chat']));
    }

    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (chat_id, sender_id, message, media) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$chat_id, $sender_id, $message, $media]);

    $messageId = $pdo->lastInsertId();

    // Get the inserted message with sender info
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.message,
            m.media,
            m.created_at,
            u.username as sender_name,
            u.profile_picture as sender_picture
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $messageData = $stmt->fetch();

    // Clear typing status for this user
    $stmt = $pdo->prepare("
        DELETE FROM typing_status 
        WHERE chat_id = ? AND user_id = ?
    ");
    $stmt->execute([$chat_id, $sender_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $messageData
    ]);

} catch (PDOException $e) {
    error_log("Send Message Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Failed to send message']));
}
?>
