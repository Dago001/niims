<?php
session_start();
require_once('db/db.php');

if (!empty($_SESSION['username'])) {
    log_activity($conn, 'USER_LOGOUT', 'User logged out');
}

// Clear all session variables
$_SESSION = [];

// Destroy the server-side session
session_destroy();

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

header('Location: index');
exit();
?>