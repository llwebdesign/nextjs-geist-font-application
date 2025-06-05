<?php
// Database configuration
$host = 'localhost';
$dbname = 'messaging_app';
$username = 'root';
$password = '';

try {
    // Create database if it doesn't exist
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "Database created successfully\n";
    
    // Select the database
    $pdo->exec("USE `$dbname`");
    
    // Read and execute SQL from db.sql
    $sql = file_get_contents('database/db.sql');
    $pdo->exec($sql);
    echo "Database tables created successfully\n";

    // Create upload directories
    require_once 'php/createDirectories.php';
    
    echo "\nSetup completed successfully!\n";
    echo "\nYou can now:\n";
    echo "1. Access the chat application at: http://localhost:8000\n";
    echo "2. Register a new account\n";
    echo "3. Start chatting!\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
