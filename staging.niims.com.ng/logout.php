<?php
session_start();
require_once('db/db.php');

$reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';

if (!empty($_SESSION['username'])) {
    if ($reason === 'inactivity') {
        if (function_exists('log_activity')) {
            log_activity($conn, 'AUTO_LOGOUT_INACTIVITY', 'User logged out automatically after 20 minutes of inactivity');
        }
    } else {
        if (function_exists('log_activity')) {
            log_activity($conn, 'USER_LOGOUT', 'User logged out');
        }
    }
}

// Clear all session variables
$_SESSION = [];

// Destroy the server-side session
@session_destroy();

// Expire the session cookie on the client side
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

$redirect_target = ($reason === 'inactivity') ? 'index.php?reason=inactivity' : 'index.php';
header('Location: ' . $redirect_target);
exit();
?>