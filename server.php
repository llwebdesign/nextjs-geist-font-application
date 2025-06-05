<?php
// PHP Development Server Router Script
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    $extension = pathinfo(__DIR__ . '/public' . $uri, PATHINFO_EXTENSION);
    
    // Set correct content type for CSS and JavaScript files
    switch ($extension) {
        case 'css':
            header('Content-Type: text/css');
            break;
        case 'js':
            header('Content-Type: application/javascript');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
    }
    
    readfile(__DIR__ . '/public' . $uri);
    return true;
}

// Route all other requests to index.html
include_once __DIR__ . '/public/index.html';
?>
