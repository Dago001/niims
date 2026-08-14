<?php
// check_users.php - Temporary diagnostic script
require_once __DIR__ . '/includes/config.php';

try {
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h3>Users in database:</h3>";
    echo "<pre>";
    if (empty($users)) {
        echo "No users found in database!\n";
    } else {
        foreach ($users as $user) {
            print_r($user);
        }
    }
    echo "</pre>";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    echo "<h3>Admin user:</h3>";
    echo "<pre>";
    if ($admin) {
        print_r($admin);
    } else {
        echo "No admin user found!\n";
    }
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>