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
    // Log the real error server-side, never expose it to the browser
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(503);
    die("Service temporarily unavailable. Please try again later.");
}

// Set character set
if (!mysqli_set_charset($conn, "utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", mysqli_error($conn));
    exit();
}

// Self-healing table/column checks (wrapped safely to prevent exceptions on empty DBs)
try {
    // Check if tbl_users exists before inspecting its columns
    $check_tbl_users = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_users'");
    if ($check_tbl_users && mysqli_num_rows($check_tbl_users) > 0) {
        $check_mcp_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'must_change_password'");
        if ($check_mcp_col && mysqli_num_rows($check_mcp_col) == 0) {
            @mysqli_query($conn, "ALTER TABLE tbl_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    // Ensure tbl_audit_logs table exists
    $check_audit_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_audit_logs'");
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
        @mysqli_query($conn, $create_audit_table_sql);
    }

    // Check if tbl_emppersonal exists before inspecting card_status
    $check_tbl_emp = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_emppersonal'");
    if ($check_tbl_emp && mysqli_num_rows($check_tbl_emp) > 0) {
        $check_card_status_emp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_emppersonal LIKE 'card_status'");
        if ($check_card_status_emp && mysqli_num_rows($check_card_status_emp) == 0) {
            @mysqli_query($conn, "ALTER TABLE tbl_emppersonal ADD card_status VARCHAR(50) NOT NULL DEFAULT 'Pending Printing'");
        }
    }

    // Check if tbl_printed_cards exists
    $check_card_status_print = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_printed_cards'");
    if ($check_card_status_print && mysqli_num_rows($check_card_status_print) > 0) {
        $check_cs = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_printed_cards LIKE 'card_status'");
        if ($check_cs && mysqli_num_rows($check_cs) == 0) {
            @mysqli_query($conn, "ALTER TABLE tbl_printed_cards ADD card_status VARCHAR(50) NOT NULL DEFAULT 'Printed'");
        }
    }
} catch (Throwable $e) {
    // Silently ignore schema checks if tables do not exist yet
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

// Automatically load audit logger helper
require_once __DIR__ . '/../functions/audit_logger.php';
?>