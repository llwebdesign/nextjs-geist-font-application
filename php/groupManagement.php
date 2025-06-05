<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action']) || !isset($data['group_id'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$action = $data['action'];
$group_id = $data['group_id'];
$admin_id = $_SESSION['user_id'];

try {
    // Verify the user is an admin of the group
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM group_members 
        WHERE chat_id = ? AND user_id = ? AND role = 'admin'
    ");
    $stmt->execute([$group_id, $admin_id]);
    
    if ($stmt->rowCount() === 0) {
        die(json_encode(['success' => false, 'message' => 'Only group admins can perform this action']));
    }

    switch ($action) {
        case 'add_members':
            if (!isset($data['members']) || !is_array($data['members'])) {
                die(json_encode(['success' => false, 'message' => 'Members list is required']));
            }

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO group_members (chat_id, user_id, role)
                VALUES (?, ?, 'member')
            ");

            foreach ($data['members'] as $member_id) {
                $stmt->execute([$group_id, $member_id]);
            }
            $message = 'Members added successfully';
            break;

        case 'remove_member':
            if (!isset($data['user_id'])) {
                die(json_encode(['success' => false, 'message' => 'User ID is required']));
            }

            // Prevent removing the last admin
            if ($data['user_id'] == $admin_id) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as admin_count 
                    FROM group_members 
                    WHERE chat_id = ? AND role = 'admin'
                ");
                $stmt->execute([$group_id]);
                $admin_count = $stmt->fetch()['admin_count'];

                if ($admin_count <= 1) {
                    die(json_encode(['success' => false, 'message' => 'Cannot remove the last admin']));
                }
            }

            $stmt = $pdo->prepare("
                DELETE FROM group_members 
                WHERE chat_id = ? AND user_id = ?
            ");
            $stmt->execute([$group_id, $data['user_id']]);
            $message = 'Member removed successfully';
            break;

        case 'promote_admin':
            if (!isset($data['user_id'])) {
                die(json_encode(['success' => false, 'message' => 'User ID is required']));
            }

            $stmt = $pdo->prepare("
                UPDATE group_members 
                SET role = 'admin' 
                WHERE chat_id = ? AND user_id = ?
            ");
            $stmt->execute([$group_id, $data['user_id']]);
            $message = 'Member promoted to admin';
            break;

        case 'demote_admin':
            if (!isset($data['user_id'])) {
                die(json_encode(['success' => false, 'message' => 'User ID is required']));
            }

            // Prevent demoting the last admin
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as admin_count 
                FROM group_members 
                WHERE chat_id = ? AND role = 'admin'
            ");
            $stmt->execute([$group_id]);
            $admin_count = $stmt->fetch()['admin_count'];

            if ($admin_count <= 1) {
                die(json_encode(['success' => false, 'message' => 'Cannot demote the last admin']));
            }

            $stmt = $pdo->prepare("
                UPDATE group_members 
                SET role = 'member' 
                WHERE chat_id = ? AND user_id = ?
            ");
            $stmt->execute([$group_id, $data['user_id']]);
            $message = 'Admin demoted to member';
            break;

        case 'update_group':
            $updates = [];
            $params = [$group_id];

            if (isset($data['group_name'])) {
                $group_name = trim($data['group_name']);
                if (strlen($group_name) < 3 || strlen($group_name) > 100) {
                    die(json_encode(['success' => false, 'message' => 'Group name must be between 3 and 100 characters']));
                }
                $updates[] = "group_name = ?";
                $params[] = $group_name;
            }

            if (!empty($updates)) {
                $sql = "UPDATE chats SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = 'Group updated successfully';
            } else {
                die(json_encode(['success' => false, 'message' => 'No updates provided']));
            }
            break;

        default:
            die(json_encode(['success' => false, 'message' => 'Invalid action']));
    }

    // Get updated group details
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.group_name,
            c.group_picture,
            c.created_at,
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'user_id', u.id,
                    'username', u.username,
                    'profile_picture', u.profile_picture,
                    'role', gm.role
                )
            ) as members
        FROM chats c
        JOIN group_members gm ON c.id = gm.chat_id
        JOIN users u ON gm.user_id = u.id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$group_id]);
    $group_data = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $group_data
    ]);

} catch (PDOException $e) {
    error_log("Group Management Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Failed to perform group action']));
}
?>
