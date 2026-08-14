<?php
// create_admin.php - Run this once to create admin user
require_once __DIR__ . '/includes/config.php';

try {
    // First, check if roles table has admin role
    $stmt = $pdo->query("SELECT * FROM roles WHERE name = 'admin'");
    $adminRole = $stmt->fetch();
    
    if (!$adminRole) {
        // Create admin role if it doesn't exist
        $pdo->exec("INSERT INTO roles (name, description) VALUES ('admin', 'Administrator')");
        $adminRoleId = $pdo->lastInsertId();
        echo "Created admin role with ID: $adminRoleId<br>";
    } else {
        $adminRoleId = $adminRole['id'];
        echo "Admin role exists with ID: $adminRoleId<br>";
    }
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $existingAdmin = $stmt->fetch();
    
    if ($existingAdmin) {
        echo "Admin user already exists!<br>";
        echo "Updating password...<br>";
        
        // Update password
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$hashedPassword]);
        echo "Password updated to: admin123<br>";
    } else {
        // Create admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role_id, email) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, 'System Administrator', $adminRoleId, 'admin@nis.gov.ng']);
        echo "Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>