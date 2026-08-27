<?php
// reset-2fa.php - Clears a user's Google Authenticator enrollment so they
// are prompted to scan a fresh QR code on their next login (e.g. after
// losing the phone their authenticator app was on).
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    // Store current page for pagination
    if (isset($_POST['current_page'])) {
        $_SESSION['datatable_page'] = $_POST['current_page'];
    }

    // Self-healing: these columns are normally created by index.php on first
    // login, but guard here too in case this is ever hit before that.
    $check_secret_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'google_auth_secret'");
    if (mysqli_num_rows($check_secret_col) == 0) {
        mysqli_query($conn, "ALTER TABLE tbl_users ADD google_auth_secret VARCHAR(32) DEFAULT NULL");
    }
    $check_enabled_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'google_auth_enabled'");
    if (mysqli_num_rows($check_enabled_col) == 0) {
        mysqli_query($conn, "ALTER TABLE tbl_users ADD google_auth_enabled TINYINT(1) DEFAULT 0");
    }

    // Confirm the user actually exists first — affected_rows would read 0
    // both for "no such user" and "already reset", so it can't tell those
    // apart on its own.
    $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM tbl_users WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, "s", $user_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    $userExists = mysqli_stmt_num_rows($check_stmt) > 0;
    mysqli_stmt_close($check_stmt);

    $success = false;
    if ($userExists) {
        $stmt = mysqli_prepare($conn, "UPDATE tbl_users SET google_auth_secret = NULL, google_auth_enabled = 0 WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $user_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if ($success) {
        $_SESSION['reset_success'] = true;
        $_SESSION['reset_message'] = "Google Authenticator reset successfully!<br>The user will be shown a new QR code to scan the next time they log in.";
    } else {
        $_SESSION['reset_error'] = true;
        $_SESSION['reset_message'] = "Failed to reset Google Authenticator. User not found.";
    }

    header("Location: createuser?tab=manage");
    exit();
}

header("Location: createuser?tab=manage");
exit();
