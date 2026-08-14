<?php
require_once __DIR__ . '/config.php';

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('loginUser')) {
    function loginUser($username, $password) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.password, u.full_name, r.name as role_name 
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('logoutUser')) {
    function logoutUser() {
        session_start();
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(),'',0,'/');
    }
}

if (!function_exists('createUser')) {
    function createUser($username, $password, $fullName, $roleId) {
        global $pdo;
        
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role_id)
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([$username, $hashedPassword, $fullName, $roleId]);
        } catch (PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
}

// createUser('newuser', 'password123', 'New User Full Name', 1); // 1 is admin role_id
?>