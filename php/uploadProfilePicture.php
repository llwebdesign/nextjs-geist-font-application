<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['success' => false, 'message' => 'No file uploaded or upload error']));
}

$file = $_FILES['profile_picture'];
$user_id = $_SESSION['user_id'];

try {
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
    $upload_dir = '../uploads/profile_pictures/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $filename = 'profile_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }

    // Get the old profile picture path
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $old_picture = $stmt->fetch()['profile_picture'];

    // Update database with new profile picture path
    $relative_path = 'uploads/profile_pictures/' . $filename;
    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->execute([$relative_path, $user_id]);

    // Delete old profile picture if it exists and isn't the default
    if ($old_picture && $old_picture !== 'default.png' && file_exists('../' . $old_picture)) {
        unlink('../' . $old_picture);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'data' => [
            'profile_picture' => $relative_path
        ]
    ]);

} catch (Exception $e) {
    error_log("Profile Picture Upload Error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
?>
