<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
$last_message_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if (!$chat_id) {
    die(json_encode(['success' => false, 'message' => 'Chat ID is required']));
}

try {
    // Verify user has access to this chat
    $stmt = $pdo->prepare("
        SELECT c.type, c.group_name, c.group_picture 
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
    $stmt->execute([$chat_id, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        die(json_encode(['success' => false, 'message' => 'Access denied to this chat']));
    }

    $chatInfo = $stmt->fetch();

    // Get messages
    $params = [$chat_id];
    $whereClause = "";
    if ($last_message_id > 0) {
        $whereClause = "AND m.id > ?";
        $params[] = $last_message_id;
    }

    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.message,
            m.media,
            m.created_at,
            u.username as sender_name,
            u.profile_picture as sender_picture
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.chat_id = ? $whereClause
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Get typing status
    $stmt = $pdo->prepare("
        SELECT 
            ts.user_id,
            u.username
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.id
        WHERE ts.chat_id = ? 
        AND ts.user_id != ? 
        AND ts.is_typing = 1
        AND ts.updated_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $stmt->execute([$chat_id, $_SESSION['user_id']]);
    $typingUsers = $stmt->fetchAll();

    // If it's a group chat, get members
    $members = [];
    if ($chatInfo['type'] === 'group') {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.username,
                u.profile_picture,
                gm.role
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.chat_id = ?
        ");
        $stmt->execute([$chat_id]);
        $members = $stmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'chat_info' => $chatInfo,
            'messages' => array_reverse($messages), // Reverse to get chronological order
            'typing_users' => $typingUsers,
            'members' => $members
        ]
    ]);

} catch (PDOException $e) {
    error_log("Get Messages Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Failed to fetch messages']));
}
?>
