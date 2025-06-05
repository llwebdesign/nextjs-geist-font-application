<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

// Handle both form data and JSON input
$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents('php://input'), true);
}

if (!isset($data['group_name']) || !isset($data['members'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$group_name = trim($data['group_name']);
$members = json_decode($data['members'], true);
$creator_id = $_SESSION['user_id'];

// Validate group name
if (strlen($group_name) < 3 || strlen($group_name) > 100) {
    die(json_encode(['success' => false, 'message' => 'Group name must be between 3 and 100 characters']));
}

// Validate members array
if (!is_array($members) || empty($members)) {
    die(json_encode(['success' => false, 'message' => 'At least one member is required']));
}

// Add creator to members if not already included
if (!in_array($creator_id, $members)) {
    $members[] = $creator_id;
}

try {
    $pdo->beginTransaction();

    // Create the group chat
    $stmt = $pdo->prepare("
        INSERT INTO chats (type, group_name) 
        VALUES ('group', ?)
    ");
    $stmt->execute([$group_name]);
    $group_id = $pdo->lastInsertId();

    // Handle group picture upload if provided
    $group_picture = null;
    if (isset($_FILES['group_picture']) && $_FILES['group_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['group_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPEG, PNG and GIF are allowed.');
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            throw new Exception('File size too large. Maximum size is 5MB.');
        }

        $upload_dir = '../uploads/group_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid('group_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $group_picture = 'uploads/group_pictures/' . $filename;
            
            // Update group picture in database
            $stmt = $pdo->prepare("UPDATE chats SET group_picture = ? WHERE id = ?");
            $stmt->execute([$group_picture, $group_id]);
        }
    }

    // Add members to the group
    $stmt = $pdo->prepare("
        INSERT INTO group_members (chat_id, user_id, role) 
        VALUES (?, ?, ?)
    ");

    foreach ($members as $member_id) {
        // Creator gets admin role, others get member role
        $role = ($member_id == $creator_id) ? 'admin' : 'member';
        $stmt->execute([$group_id, $member_id, $role]);
    }

    // Get group details including members
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

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Group created successfully',
        'data' => $group_data
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Create Group Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
?>
