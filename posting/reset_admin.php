<?php
require 'includes/config.php';

// Delete any existing admin user
$pdo->exec("DELETE FROM users WHERE username = 'admin'");

// Create new admin with fresh credentials
$username = "admin";
$password = "admin123"; // Stronger default password
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->execute([$username, $hash]);

echo "Admin credentials reset successfully!<br>";
echo "Username: <strong>admin</strong><br>";
echo "Password: <strong>admin123</strong><br>";
echo "<a href='login'>Go to Login Page</a>";

// SECURITY: This file should be deleted immediately after use
?>