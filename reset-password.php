<?php
// reset-password.php - COMPLETE FIXED VERSION USING MD5
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    
    // Store current page for pagination
    if (isset($_POST['current_page'])) {
        $_SESSION['datatable_page'] = $_POST['current_page'];
    }
    
    $default_password = 'password123';
    $bcrypt_hash = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Try different column names
    $columns = ['password', 'user_password', 'pass', 'user_pass', 'pwd'];
    $success = false;
    
    foreach ($columns as $column) {
        // Check if column exists
        $column_escaped = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE '$column_escaped'");
        if (mysqli_num_rows($check) > 0) {
            // Update password with Bcrypt hash using prepared statement
            $sql = "UPDATE tbl_users SET $column_escaped = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $bcrypt_hash, $_POST['user_id']);
                $exec = mysqli_stmt_execute($stmt);
                if ($exec && mysqli_stmt_affected_rows($stmt) > 0) {
                    $success = true;
                }
                mysqli_stmt_close($stmt);
                if ($success) {
                    break;
                }
            }
        }
    }
    
    if ($success) {
        $_SESSION['reset_success'] = true;
        $_SESSION['reset_message'] = "Password reset successfully!<br>User can now login with: <strong>password123</strong>";
    } else {
        $_SESSION['reset_error'] = true;
        $_SESSION['reset_message'] = "Failed to reset password. Could not find password column.";
    }
    
    // Redirect back
    header("Location: edit-user");
    exit();
}

header("Location: edit-user");
exit();
?>