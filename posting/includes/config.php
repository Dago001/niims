<?php
// config.php

// Check if session is already started before starting a new one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Credentials live outside version control — see db_credentials.php (gitignored).
// The values that used to be hardcoded here were committed to git and must be
// treated as compromised; rotate them on the server and update that file.
$db_credentials_file = __DIR__ . '/db_credentials.php';
if (!file_exists($db_credentials_file)) {
    die('Database configuration missing: ' . $db_credentials_file . ' not found.');
}
$db_credentials = require $db_credentials_file;

$db_host = $db_credentials['host'];
$db_user = $db_credentials['user'];
$db_pass = $db_credentials['pass'];
$db_name = $db_credentials['name'];

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}

// Create mysqli connection for backward compatibility if needed
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$conn) {
    throw new Exception(mysqli_connect_error());
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>