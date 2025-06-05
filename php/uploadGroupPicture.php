<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

if (!isset($_FILES['group_picture']) || $_FILES['group_picture']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['success' => false, 'message' => 'No file uploaded or upload error']));
}

if (!isset($_POST['group_id'])) {
    die(json_encode(['success' => false, 'message' => 'Group ID is required']));
}

$file = $_FILES['group_picture'];
$group_id = $_POST['group_id'];
$user_id = $_SESSION['user_id'];

try {
    // Verify user is an admin of the group
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM group_members 
        WHERE chat_id = ? AND user_id = ? AND role = 'admin'
    ");
    $stmt->execute([$group_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Only group admins can change the group picture');
    }

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPEG, PNG and GIF are allowed.');
    }

    // Validate file size (5MB max)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/group_pictures/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $filename = 'group_' . $group_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }

    // Get the old group picture path
    $stmt = $pdo->prepare("SELECT group_picture FROM chats WHERE id = ?");
    $stmt->execute([$group_id]);
    $old_picture = $stmt->fetch()['group_picture'];

    // Update database with new group picture path
    $relative_path = 'uploads/group_pictures/' . $filename;
    $stmt = $pdo->prepare("UPDATE chats SET group_picture = ? WHERE id = ?");
    $stmt->execute([$relative_path, $group_id]);

    // Delete old group picture if it exists and isn't the default
    if ($old_picture && $old_picture !== 'default_group.png' && file_exists('../' . $old_picture)) {
        unlink('../' . $old_picture);
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
        'message' => 'Group picture updated successfully',
        'data' => $group_data
    ]);

} catch (Exception $e) {
    error_log("Group Picture Upload Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
?>
