<?php
// config.php

// Check if session is already started before starting a new one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = 'localhost';
$db_user = 'niimscom';
$db_pass = '#qiWco[8GY585I';
$db_name = 'niimscom_personnel_db';

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