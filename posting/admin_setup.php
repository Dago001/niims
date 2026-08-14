<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = 1");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn() > 0;

    if (!$adminExists) {
        // Create admin account with secure password
        $adminUsername = 'admin';
        $adminPassword = 'admin123'; // Change this!
        $adminFullName = 'System Administrator';
        $adminRoleId = 1;

        if (createUser($adminUsername, $adminPassword, $adminFullName, $adminRoleId)) {
            echo "Admin account created successfully";
        } else {
            echo "Failed to create admin account";
        }
    } else {
        echo "Admin account already exists";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>