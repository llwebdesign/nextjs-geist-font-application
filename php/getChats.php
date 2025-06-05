<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];

try {
    // Get all chats (both single and group) that the user is part of
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            c.id,
            c.type,
            c.group_name,
            c.group_picture,
            CASE 
                WHEN c.type = 'single' THEN (
                    SELECT username 
                    FROM users 
                    WHERE id IN (
                        SELECT DISTINCT 
                            CASE 
                                WHEN sender_id = ? THEN receiver_id 
                                ELSE sender_id 
                            END
                        FROM messages 
                        WHERE chat_id = c.id
                    )
                    AND id != ?
                    LIMIT 1
                )
                ELSE c.group_name
            END as chat_name,
            CASE 
                WHEN c.type = 'single' THEN (
                    SELECT profile_picture 
                    FROM users 
                    WHERE id IN (
                        SELECT DISTINCT 
                            CASE 
                                WHEN sender_id = ? THEN receiver_id 
                                ELSE sender_id 
                            END
                        FROM messages 
                        WHERE chat_id = c.id
                    )
                    AND id != ?
                    LIMIT 1
                )
                ELSE c.group_picture
            END as chat_picture,
            (
                SELECT message 
                FROM messages 
                WHERE chat_id = c.id 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE chat_id = c.id 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time
        FROM chats c
        LEFT JOIN group_members gm ON c.id = gm.chat_id
        WHERE 
            (c.type = 'single' AND EXISTS (
                SELECT 1 
                FROM messages m 
                WHERE m.chat_id = c.id 
                AND (m.sender_id = ? OR m.sender_id IN (
                    SELECT sender_id 
                    FROM messages 
                    WHERE chat_id = c.id 
                    AND sender_id != ?
                ))
            ))
            OR 
            (c.type = 'group' AND gm.user_id = ?)
        ORDER BY last_message_time DESC NULLS LAST
    ");

    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $chats = $stmt->fetchAll();

    // Get unread message count for each chat
    foreach ($chats as &$chat) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM messages
            WHERE chat_id = ?
            AND sender_id != ?
            AND created_at > (
                SELECT COALESCE(last_read, '1970-01-01')
                FROM chat_users
                WHERE chat_id = ?
                AND user_id = ?
            )
        ");
        $stmt->execute([$chat['id'], $user_id, $chat['id'], $user_id]);
        $unread = $stmt->fetch();
        $chat['unread_count'] = $unread['unread_count'];

        // For group chats, get member count
        if ($chat['type'] === 'group') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as member_count
                FROM group_members
                WHERE chat_id = ?
            ");
            $stmt->execute([$chat['id']]);
            $members = $stmt->fetch();
            $chat['member_count'] = $members['member_count'];
        }
    }

    echo json_encode([
        'success' => true,
        'chats' => $chats
    ]);

} catch (PDOException $e) {
    error_log("Get Chats Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Failed to load chats']));
}
?>
