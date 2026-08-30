<?php
/**
 * Audit Logging Helper for NIIMS
 * Records system actions, user authentication, and data modifications into tbl_audit_logs.
 */

if (!function_exists('log_activity')) {
    function log_activity($conn, $action, $details = '') {
        if (!$conn) {
            return false;
        }

        // Fetch session user details if available
        $user_id   = $_SESSION['user_id'] ?? 'N/A';
        $username  = $_SESSION['username'] ?? 'Anonymous/Guest';
        $user_role = $_SESSION['role'] ?? 'Unauthenticated';

        // Retrieve client IP address securely
        $ip_address = '0.0.0.0';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($ip_list[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }

        // Sanitize and limit lengths
        $user_id    = substr((string)$user_id, 0, 100);
        $username   = substr((string)$username, 0, 100);
        $user_role  = substr((string)$user_role, 0, 50);
        $action     = substr((string)$action, 0, 100);
        $ip_address = substr((string)$ip_address, 0, 45);

        $sql = "INSERT INTO tbl_audit_logs (user_id, username, user_role, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssss", $user_id, $username, $user_role, $action, $details, $ip_address);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }
}
?>
