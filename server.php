<?php
// PHP Development Server Router Script
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route all other requests to index.html
include_once 'public/index.html';
?>
