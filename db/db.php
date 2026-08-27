<?php
// Baseline security headers for every page that connects to the database
// (i.e. essentially the whole app) — defends against clickjacking and MIME
// sniffing even on pages that don't set their own headers. A page is free
// to set a stricter header afterward (e.g. its own CSP); this only fills
// in a safe default where nothing else would.
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'niimscom_idcard');

$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set character set
if (!mysqli_set_charset($conn, "utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", mysqli_error($conn));
    exit();
}

// Self-healing: ensure the forced-password-change flag column exists.
$check_mcp_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'must_change_password'");
if ($check_mcp_col && mysqli_num_rows($check_mcp_col) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0");
}

// Global enforcement: a user flagged for a mandatory password change is
// redirected to changePassword on every request until they comply, no
// matter which page they try to load. Guarded on an active session so
// scripts that never call session_start() (ajax helpers, api.php) aren't
// touched.
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['username']) && !empty($_SESSION['must_change_password'])) {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $exempt_scripts = ['changePassword.php', 'updates.php', 'logout.php', 'index.php'];
    if (!in_array($current_script, $exempt_scripts, true)) {
        header('Location: changePassword');
        exit();
    }
}
?>