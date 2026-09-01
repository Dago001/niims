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

// Turn off automatic mysqli exception throwing to prevent HTTP 500 errors on connection failures
mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
} catch (Throwable $e) {
    $conn = false;
    error_log("Database connection exception: " . $e->getMessage());
}

if (!$conn) {
    // Log the real error server-side
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(503);
    die("
    <div style='font-family: Arial, sans-serif; background-color: #f8d7da; color: #721c24; padding: 30px; margin: 50px auto; max-width: 600px; border: 1px solid #f5c6cb; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
        <h2 style='margin-top:0;'>⚠️ Database Connection Required</h2>
        <p>NIIMS could not connect to the MySQL database on the live server.</p>
        <hr style='border-top: 1px solid #f5c6cb;'>
        <p><strong>To resolve this issue:</strong></p>
        <ol style='text-align: left; line-height: 1.6;'>
            <li>Open <code>db/db.php</code> on your live server.</li>
            <li>Set <code>DB_SERVER</code> (usually <code>localhost</code>).</li>
            <li>Set <code>DB_USERNAME</code> (your live cPanel DB User).</li>
            <li>Set <code>DB_PASSWORD</code> (your live cPanel DB Password).</li>
            <li>Set <code>DB_DATABASE</code> (your live cPanel Database Name).</li>
        </ol>
    </div>");
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

// Self-healing: ensure tbl_audit_logs table exists
$check_audit_table = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_audit_logs'");
if ($check_audit_table && mysqli_num_rows($check_audit_table) == 0) {
    $create_audit_table_sql = "CREATE TABLE IF NOT EXISTS tbl_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(100) NOT NULL,
        username VARCHAR(100) NOT NULL,
        user_role VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (action),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $create_audit_table_sql);
}

// Self-healing: ensure card_status column exists in tbl_emppersonal and tbl_printed_cards
$check_card_status_emp = mysqli_query($conn, "SHOW COLUMNS FROM tbl_emppersonal LIKE 'card_status'");
if ($check_card_status_emp && mysqli_num_rows($check_card_status_emp) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_emppersonal ADD card_status VARCHAR(50) NOT NULL DEFAULT 'Pending Printing'");
}

$check_card_status_print = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_printed_cards'");
if ($check_card_status_print && mysqli_num_rows($check_card_status_print) > 0) {
    $check_cs = mysqli_query($conn, "SHOW COLUMNS FROM tbl_printed_cards LIKE 'card_status'");
    if ($check_cs && mysqli_num_rows($check_cs) == 0) {
        mysqli_query($conn, "ALTER TABLE tbl_printed_cards ADD card_status VARCHAR(50) NOT NULL DEFAULT 'Printed'");
    }
}


// Global enforcement: a user flagged for a mandatory password change is
// redirected to changePassword on every request until they comply, no
// matter which page they try to load. Guarded on an active session so
// scripts that never call session_start() (ajax helpers, api.php) aren't
// touched.
// Global enforcement: a user flagged for a mandatory password change is
// redirected to changePassword on every request until they comply, no
// matter which page they try to load. Guarded on an active session so
// scripts that never call session_start() (ajax helpers, api.php) aren't
// touched.
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['username'])) {
    // 20-minute (1200 seconds) inactivity auto-logout
    $inactivity_timeout = 1200;
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_timeout)) {
        // Log auto-logout event
        $username = $_SESSION['username'] ?? 'User';
        if (function_exists('log_audit_event') && isset($conn) && $conn) {
            log_audit_event($conn, 'AUTO_LOGOUT_INACTIVITY', "User {$username} logged out after 20 minutes of inactivity");
        }

        // Destroy session
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();

        if (!headers_sent()) {
            header('Location: index.php?reason=inactivity');
            exit();
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();

    if (!empty($_SESSION['must_change_password'])) {
        $current_script = basename($_SERVER['SCRIPT_NAME']);
        $exempt_scripts = ['changePassword.php', 'updates.php', 'logout.php', 'index.php'];
        if (!in_array($current_script, $exempt_scripts, true)) {
            header('Location: changePassword');
            exit();
        }
    }
}

// Automatically load audit logger helper
require_once __DIR__ . '/../functions/audit_logger.php';
?>