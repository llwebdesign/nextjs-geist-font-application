<?php
// Create directories for file uploads
$directories = [
    '../uploads',
    '../uploads/profile_pictures',
    '../uploads/group_pictures',
    '../uploads/chat_media'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

echo "Upload directories created successfully.";
?>
