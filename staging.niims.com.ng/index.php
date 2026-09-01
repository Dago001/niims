<?php
// ============================================================================
// SECURITY HEADERS AND CONFIGURATION
// ============================================================================

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP: allows inline scripts/styles (required by Gentelella template), self-hosted assets,
// Chart.js CDN, and font CDNs. Blocks framing, object tags, and base URI hijacking.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; object-src 'none'; base-uri 'self';");

// Disable error reporting in production
error_reporting(0);
ini_set('display_errors', 0);

// ============================================================================
// SECURITY FUNCTIONS
// ============================================================================

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $data;
}

/**
 * Validate username format
 * Usernames are Service Numbers, which are numeric-only.
 */
function validate_username($username) {
    // Allow digits only
    if (!preg_match('/^[0-9]+$/', $username)) {
        return false;
    }

    // Length validation
    if (strlen($username) < 3 || strlen($username) > 20) {
        return false;
    }

    return true;
}

/**
 * Validate password strength
 */
function validate_password($password) {
    // Minimum 8 characters, at least one letter and one number
    if (strlen($password) < 8) {
        return false;
    }
    
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Log security events
 */
function log_security_event($event, $user_id = null, $ip = null) {
    $log_file = __DIR__ . '/security.log';
    
    $ip = $ip ?: $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = "[{$timestamp}] [IP: {$ip}] [User: {$user_id}] [Event: {$event}] [Agent: {$user_agent}]\n";
    
    // Write to log file (append mode)
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Check for brute force attempts.
 *
 * Tracked server-side in tbl_login_attempts (keyed by IP+username) rather
 * than in $_SESSION — a session-based counter is trivially bypassed by an
 * attacker simply dropping cookies between attempts.
 */
function check_brute_force($username) {
    global $conn;
    $max_attempts = 5;
    $lockout_time = 900; // 15 minutes in seconds

    $ip = $_SERVER['REMOTE_ADDR'];
    $identifier = $ip . '|' . $username;
    $cutoff = time() - $lockout_time;

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*), MIN(attempt_time) FROM tbl_login_attempts WHERE identifier = ? AND attempt_time >= ?");
    mysqli_stmt_bind_param($stmt, "si", $identifier, $cutoff);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $attempts, $first_attempt);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($attempts >= $max_attempts) {
        $time_elapsed = time() - (int) $first_attempt;
        $remaining_time = $lockout_time - $time_elapsed;
        log_security_event("BRUTE_FORCE_LOCKOUT", $username);
        return "Too many failed attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
    }

    return null;
}

/**
 * Record failed login attempt
 */
function record_failed_attempt($username) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $identifier = $ip . '|' . $username;
    $now = time();

    $stmt = mysqli_prepare($conn, "INSERT INTO tbl_login_attempts (identifier, attempt_time) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "si", $identifier, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Opportunistically purge old rows so the table doesn't grow forever.
    mysqli_query($conn, "DELETE FROM tbl_login_attempts WHERE attempt_time < " . (int) ($now - 86400));

    log_security_event("FAILED_LOGIN", $username);
}

/**
 * Clear failed attempts on successful login
 */
function clear_failed_attempts($username) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $identifier = $ip . '|' . $username;

    $stmt = mysqli_prepare($conn, "DELETE FROM tbl_login_attempts WHERE identifier = ?");
    mysqli_stmt_bind_param($stmt, "s", $identifier);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        log_security_event("CSRF_ATTEMPT");
        return false;
    }
    return true;
}

/**
 * Secure CAPTCHA generation
 */
function generateCaptchaCode($length = 6) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    // Use cryptographically secure random function
    if (function_exists('random_int')) {
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
    } else {
        // Fallback to rand() if random_int is not available
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
    }
    
    return $randomString;
}

// ============================================================================
// MAIN APPLICATION LOGIC
// ============================================================================

require_once(__DIR__ . '/db/db.php');
require_once(__DIR__ . '/db/GoogleAuthenticator.php');

// Create Google Authenticator columns dynamically if they do not exist
$check_secret_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'google_auth_secret'");
if (mysqli_num_rows($check_secret_col) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_users ADD google_auth_secret VARCHAR(32) DEFAULT NULL");
}
$check_enabled_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'google_auth_enabled'");
if (mysqli_num_rows($check_enabled_col) == 0) {
    mysqli_query($conn, "ALTER TABLE tbl_users ADD google_auth_enabled TINYINT(1) DEFAULT 0");
}

// Server-side brute-force tracking table (see check_brute_force/record_failed_attempt)
$check_attempts_table = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_login_attempts'");
if (mysqli_num_rows($check_attempts_table) == 0) {
    mysqli_query($conn, "CREATE TABLE tbl_login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(191) NOT NULL,
        attempt_time INT NOT NULL,
        INDEX idx_identifier_time (identifier, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Initialize variables
$error_message = '';
$has_error = false;
$inactive_account = false;
$csrf_token = generate_csrf_token();

// Generate CAPTCHA code if not already set
if (!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = generateCaptchaCode();
}

// Handle 2FA Cancel via GET
if (isset($_GET['cancel'])) {
    unset($_SESSION['auth_step']);
    unset($_SESSION['auth_temp_user']);
    unset($_SESSION['auth_temp_secret']);
    header("Location: index");
    exit();
}

// Handle 2FA Verification
if (isset($_POST['verify_2fa'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = "Security token invalid. Please refresh the page and try again.";
        $has_error = true;
    } else {
        $code = isset($_POST['2fa_code']) ? trim($_POST['2fa_code']) : '';
        $temp_user = isset($_SESSION['auth_temp_user']) ? $_SESSION['auth_temp_user'] : '';
        
        if (empty($temp_user)) {
            // No user in session, reset
            unset($_SESSION['auth_step']);
            header("Location: index");
            exit();
        }
        
        // Fetch user from DB
        $sql = "SELECT * FROM tbl_users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $temp_user);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $is_new = true;
                $secret = '';
                
                if (!empty($row['google_auth_secret']) && $row['google_auth_enabled'] == 1) {
                    $is_new = false;
                    $secret = $row['google_auth_secret'];
                } else {
                    $secret = isset($_SESSION['auth_temp_secret']) ? $_SESSION['auth_temp_secret'] : '';
                }
                
                if (empty($secret)) {
                    $error_message = "2FA setup session expired. Please restart login.";
                    $has_error = true;
                    unset($_SESSION['auth_step']);
                    unset($_SESSION['auth_temp_user']);
                    unset($_SESSION['auth_temp_secret']);
                } elseif (GoogleAuthenticator::verifyCode($secret, $code)) {
                    // Success! Enable 2FA in DB if new user
                    if ($is_new) {
                        $update_sql = "UPDATE tbl_users SET google_auth_secret = ?, google_auth_enabled = 1 WHERE user_id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        if ($update_stmt) {
                            mysqli_stmt_bind_param($update_stmt, "ss", $secret, $temp_user);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                    
                    // Finalize session details
                    clear_failed_attempts($temp_user);
                    session_regenerate_id(true);
                    
                    $_SESSION['username'] = $row['lastname'];
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['email'] = $row['email_address'];
                    $_SESSION['role'] = $row['user_role'];
                    $_SESSION['photo'] = !empty($row['photo']) ? $row['photo'] : '';
                    $_SESSION['must_change_password'] = !empty($row['must_change_password']) ? 1 : 0;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    unset($_SESSION['auth_step']);
                    unset($_SESSION['auth_temp_user']);
                    unset($_SESSION['auth_temp_secret']);
                    
                    log_security_event("SUCCESSFUL_LOGIN", $temp_user);
                    log_activity($conn, 'LOGIN_SUCCESS', 'User logged in successfully');

                    $role_clean = strtolower(trim($_SESSION['role'] ?? ''));
                    if ($_SESSION['must_change_password']) {
                        header("Location: changePassword.php");
                        exit();
                    } elseif (in_array($role_clean, ['dataentry', 'data_entry', 'data entry'])) {
                        header("Location: landing.php");
                        exit();
                    } else {
                        header("Location: mainpage.php");
                        exit();
                    }

                } else {
                    $error_message = "Invalid 6-digit code. Please check your Google Authenticator and try again.";
                    $has_error = true;
                    record_failed_attempt($temp_user);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle standard Login credentials form submission
if (isset($_POST['login'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = "Security token invalid. Please refresh the page and try again.";
        $has_error = true;
    } else {
        $username = isset($_POST['username']) ? sanitize_input(trim($_POST['username'])) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $captcha_input = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
        
        $captcha_valid = false;
        if (isset($_SESSION['captcha_code']) && !empty($captcha_input)) {
            $captcha_valid = (strtoupper($captcha_input) === $_SESSION['captcha_code']);
        }
        
        // Reset CAPTCHA so it cannot be reused
        unset($_SESSION['captcha_code']);
        $_SESSION['captcha_code'] = generateCaptchaCode();
        
        $brute_force_check = check_brute_force($username);
        if ($brute_force_check) {
            $error_message = $brute_force_check;
            $has_error = true;
        } elseif (empty($username) || empty($password) || empty($captcha_input)) {
            $error_message = "All fields are required. Please fill in username, password, and CAPTCHA.";
            $has_error = true;
            record_failed_attempt($username);
        } elseif (!$captcha_valid) {
            $error_message = "Invalid CAPTCHA code. Please try again.";
            $has_error = true;
            record_failed_attempt($username);
        } elseif (!validate_username($username)) {
            $error_message = "Invalid username format.";
            $has_error = true;
            record_failed_attempt($username);
        } elseif (!validate_password($password)) {
            $error_message = "Password must be at least 8 characters long and contain both letters and numbers.";
            $has_error = true;
            record_failed_attempt($username);
        } else {
            // Check credentials
            $check_column_sql = "SHOW COLUMNS FROM tbl_users LIKE 'is_active'";
            $column_check = mysqli_query($conn, $check_column_sql);
            $has_is_active_column = mysqli_num_rows($column_check) > 0;
            
            $sql = "SELECT * FROM tbl_users WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    $stored_hash = $row['password'];
                    $password_valid = false;
                    $needs_rehash = false;
                    
                    if (password_verify($password, $stored_hash)) {
                        $password_valid = true;
                    } elseif (md5($password) === $stored_hash) {
                        $password_valid = true;
                        $needs_rehash = true;
                    }
                    
                    if ($password_valid) {
                        if ($needs_rehash) {
                            $new_bcrypt_hash = password_hash($password, PASSWORD_DEFAULT);
                            $migrate_sql = "UPDATE tbl_users SET password = ? WHERE user_id = ?";
                            $migrate_stmt = mysqli_prepare($conn, $migrate_sql);
                            if ($migrate_stmt) {
                                mysqli_stmt_bind_param($migrate_stmt, "ss", $new_bcrypt_hash, $username);
                                mysqli_stmt_execute($migrate_stmt);
                                mysqli_stmt_close($migrate_stmt);
                            }
                        }
                        
                        // Check if account is active
                        $is_active = 1;
                        if ($has_is_active_column) {
                            $is_active = isset($row['is_active']) ? $row['is_active'] : 1;
                        }
                        
                        if ($is_active == 0) {
                            $error_message = "Your account has been deactivated. Please contact your administrator.";
                            $has_error = true;
                            $inactive_account = true;
                            log_security_event("DEACTIVATED_ACCOUNT_ACCESS", $username);
                        } else {
                            // Credentials match! Transition to 2FA page
                            $_SESSION['auth_step'] = '2fa';
                            $_SESSION['auth_temp_user'] = $row['user_id'];
                            
                            // Generate a secret in advance for setup screen if not enabled
                            if (empty($row['google_auth_secret']) || $row['google_auth_enabled'] != 1) {
                                $_SESSION['auth_temp_secret'] = GoogleAuthenticator::generateSecret();
                            }
                            
                            // Redirect to show the 2FA UI (which uses exact same look and feel)
                            header("Location: index");
                            exit();
                        }
                    } else {
                        $error_message = "Invalid username or password. Please check your credentials and try again.";
                        $has_error = true;
                        record_failed_attempt($username);
                    }
                } else {
                    $error_message = "Invalid username or password. Please check your credentials and try again.";
                    $has_error = true;
                    record_failed_attempt($username);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Database error. Please try again later.";
                $has_error = true;
                log_security_event("DATABASE_ERROR", $username);
            }
        }
    }
}

// Generate new CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="manifest.json">

    <meta name="theme-color" content="#5A3E1B">

    <meta name="mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-title" content="NIIMS">

    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">

    <title>NIIMS</title>

    <!-- Bootstrap -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <!-- NProgress -->
    <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
    <!-- Animate.css -->
    <link href="vendors/animate.css/animate.min.css" rel="stylesheet">

    <!-- Custom Theme Style -->
    <link href="build/css/custom.min.css" rel="stylesheet">
    
    <!-- Captcha CSS -->
    <style>
        /* Previous CSS styles remain the same */
        .captcha-container {
            margin: 15px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .captcha-code {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 3px;
            padding: 8px;
            background: #fff;
            border-radius: 3px;
            text-align: center;
            user-select: none;
            margin-bottom: 10px;
            border: 1px dashed #ccc;
            color: #333;
            line-height: 1.2;
        }
        .captcha-char {
            display: inline-block;
            position: relative;
            margin: 0 2px;
        }
        .captcha-strike {
            position: absolute;
            height: 2px;
            background-color: #666;
            transform: rotate(var(--angle));
            width: 100%;
            top: 50%;
            left: 0;
        }
        .captcha-input {
            text-align: center;
            font-size: 16px;
            letter-spacing: 2px;
        }
        .captcha-controls {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .refresh-captcha {
            cursor: pointer;
            color: #007bff;
            font-size: 14px;
        }
        .refresh-captcha:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .captcha-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }
        .password-toggle:hover {
            color: #333;
        }
        /* Error alert styling */
        .error-alert {
            margin: 15px 0;
            padding: 12px 15px;
            border-radius: 4px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            font-size: 14px;
            display: flex;
            align-items: center;
            animation: fadeIn 0.3s ease-in;
        }
        .error-alert i {
            margin-right: 10px;
            font-size: 16px;
        }
        /* Inactive account alert styling */
        .inactive-alert {
            margin: 15px 0;
            padding: 12px 15px;
            border-radius: 4px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            font-size: 14px;
            display: flex;
            align-items: center;
            animation: fadeIn 0.3s ease-in;
        }
        .inactive-alert i {
            margin-right: 10px;
            font-size: 16px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Google Authenticator Custom Styles */
        .twofa-title {
            margin-top: 5px; 
            margin-bottom: 5px;
            color: #2c3e50; 
            font-weight: 700; 
            font-size: 17px;
            letter-spacing: -0.5px;
        }
        .twofa-desc {
            font-size: 12px; 
            color: #7f8c8d; 
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .twofa-qr-container {
            text-align: center; 
            margin: 10px 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .twofa-qr-wrapper {
            border: 1px solid #e2e8f0; 
            padding: 6px; 
            background: #ffffff; 
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.06);
            display: inline-block;
            transition: transform 0.2s;
        }
        .twofa-qr-wrapper:hover {
            transform: scale(1.02);
        }
        .twofa-secret-box {
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            padding: 8px; 
            border-radius: 6px; 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 12px; 
            margin-bottom: 12px; 
            text-align: center; 
            word-break: break-all;
            color: #475569;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
        }
        .twofa-secret-key {
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 1px;
            font-size: 13px;
        }
        .twofa-code-input {
            text-align: center; 
            font-size: 20px !important; 
            letter-spacing: 6px; 
            font-weight: 700;
            height: 36px !important;
            border-radius: 6px !important;
            border: 2px solid #cbd5e1 !important;
            color: #1e293b !important;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
        }
        .twofa-code-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
            outline: none;
        }
        .twofa-btn-verify {
            background: #10b981 !important; /* Emerald green */
            border-color: #10b981 !important;
            color: white !important;
            font-weight: bold;
            font-size: 13px !important;
            height: 34px !important;
            border-radius: 6px !important;
            transition: all 0.2s !important;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2) !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            line-height: normal !important;
            width: 100%;
        }
        .twofa-btn-verify:hover {
            background: #059669 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3) !important;
        }
        .twofa-btn-cancel {
            background: #ef4444 !important; /* Elegant Red */
            border-color: #ef4444 !important;
            color: white !important;
            font-weight: bold;
            font-size: 13px !important;
            height: 34px !important;
            border-radius: 6px !important;
            transition: all 0.2s !important;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2) !important;
            text-align: center;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-decoration: none !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            line-height: normal !important;
            width: 100%;
        }
        .twofa-btn-cancel:hover {
            background: #dc2626 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3) !important;
        }
        
        /* Disable text selection */
        .noselect {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* Hidden elements for screen readers */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            border: 0;
        }
    </style>
</head>

<script>
  // Service Worker Registration for Offline Mode and Light-Speed Asset Caching
  if ('serviceWorker' in navigator && (window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('sw.js').then(function(registration) {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      }).catch(function(err) {
        console.warn('ServiceWorker registration failed: ', err);
      });
    });

    // Offline / Online Status Detection Warning Banner
    function updateOnlineStatus() {
      let banner = document.getElementById('offline-indicator');
      if (!navigator.onLine) {
        if (!banner) {
          banner = document.createElement('div');
          banner.id = 'offline-indicator';
          banner.style.position = 'fixed';
          banner.style.top = '0';
          banner.style.left = '0';
          banner.style.width = '100%';
          banner.style.backgroundColor = '#fee2e2';
          banner.style.color = '#ef4444';
          banner.style.borderBottom = '1px solid #fca5a5';
          banner.style.textAlign = 'center';
          banner.style.padding = '8px 15px';
          banner.style.fontSize = '12px';
          banner.style.fontWeight = '600';
          banner.style.zIndex = '99999';
          banner.innerHTML = '<i class="fa fa-exclamation-triangle"></i> You are currently offline. Running in Offline Mode (Serving Cached Content)';
          document.body.appendChild(banner);
          document.body.style.paddingTop = '36px';
        }
      } else {
        if (banner) {
          banner.remove();
          document.body.style.paddingTop = '0';
        }
      }
    }
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();
  }
</script>

<body class="login noselect" style="background-image: url('images/nis.jpg'); background-size:cover; background-repeat: no-repeat; background-attachment: fixed;" oncontextmenu="return false;">
    <div>
        <a class="hiddenanchor" id="signup"></a>
        <a class="hiddenanchor" id="signin"></a>

        <div class="login_wrapper">
            <div class="animate form login_form" style="background-color: white;">
                <section class="login_content">
                    <?php if (isset($_SESSION['auth_step']) && $_SESSION['auth_step'] === '2fa'): 
                        $temp_user = $_SESSION['auth_temp_user'];
                        $sql = "SELECT * FROM tbl_users WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        $is_new = true;
                        $secret = '';
                        $qr_svg = '';
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "s", $temp_user);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            if ($row = mysqli_fetch_assoc($result)) {
                                if (!empty($row['google_auth_secret']) && $row['google_auth_enabled'] == 1) {
                                    $is_new = false;
                                    $secret = $row['google_auth_secret'];
                                } else {
                                    if (!isset($_SESSION['auth_temp_secret'])) {
                                        $_SESSION['auth_temp_secret'] = GoogleAuthenticator::generateSecret();
                                    }
                                    $secret = $_SESSION['auth_temp_secret'];
                                    
                                    // Generate QR Code as SVG locally (requires NO GD extension!)
                                    $qr_svg = GoogleAuthenticator::getQRCodeLocalSvg($temp_user, $secret, 'NIIMS Portal');
                                }
                            }
                            mysqli_stmt_close($stmt);
                        }
                    ?>
                        <!-- Google Authenticator 2FA Form -->
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="padding-left: 25px; padding-right: 25px;" method="post" id="loginForm" autocomplete="off">
                            <img src="assets/images/logo.png" width=75px height=75px alt="NIIMS Logo">
                            
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <!-- Error Message Display -->
                            <?php if ($has_error && !empty($error_message)): ?>
                            <div class="error-alert" id="errorAlert">
                                <i class="fa fa-exclamation-circle"></i>
                                <span><?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($is_new): ?>
                                <h4 class="twofa-title">Google Authenticator Setup</h4>
                                <p class="twofa-desc">Scan the QR code below using your Google Authenticator app, then enter the 6-digit code.</p>
                                
                                <div class="twofa-qr-container">
                                    <div class="twofa-qr-wrapper">
                                        <?php echo $qr_svg; ?>
                                    </div>
                                </div>
                                
                                <div class="twofa-secret-box">
                                    <strong>Secret Key</strong><br>
                                    <span class="twofa-secret-key"><?php echo chunk_split($secret, 4, ' '); ?></span>
                                </div>
                            <?php else: ?>
                                <h4 class="twofa-title">Google Authentication</h4>
                                <p class="twofa-desc">Enter the 6-digit verification code from your Google Authenticator app.</p>
                            <?php endif; ?>

                            <div style="margin-bottom: 12px;">
                                <input type="text" name="2fa_code" class="form-control twofa-code-input" placeholder="000000" required 
                                       maxlength="6" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                            </div>

                            <!-- Hidden input to guarantee verify_2fa is submitted even if button is disabled by JS -->
                            <input type="hidden" name="verify_2fa" value="verify_2fa">
                            
                            <div style="display: flex; gap: 8px; margin-top: 12px; padding-bottom: 5px;">
                                <button type="submit" class="btn btn-primary twofa-btn-verify" style="flex: 1; margin-bottom: 0;">
                                    <i class="fa fa-shield"></i> Verify
                                </button>
                                <a href="index?cancel=1" class="btn btn-default twofa-btn-cancel" style="flex: 1; margin-bottom: 0;">
                                    <i class="fa fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Standard Login Form -->
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="padding-left: 25px; padding-right: 25px;" method="post" id="loginForm" autocomplete="off">
                            <img src="assets/images/logo.png" width=75px height=75px alt="NIIMS Logo">
                            
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <!-- Inactivity Logout Alert Display -->
                            <?php if (isset($_GET['reason']) && $_GET['reason'] === 'inactivity'): ?>
                            <div class="inactive-alert" id="inactivityAlert" style="margin-bottom: 15px;">
                                <i class="fa fa-clock-o"></i>
                                <span>You were automatically logged out after 20 minutes of inactivity for security.</span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Error Message Display -->
                            <?php if ($has_error && !empty($error_message)): ?>
                            <div class="<?php echo $inactive_account ? 'inactive-alert' : 'error-alert'; ?>" id="errorAlert">
                                <i class="fa <?php echo $inactive_account ? 'fa-user-lock' : 'fa-exclamation-circle'; ?>"></i>
                                <span><?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <input type="text" name="username" class="form-control" placeholder="Service Number" required
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                       autocomplete="username" maxlength="20" inputmode="numeric" pattern="[0-9]*"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                            </div>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required 
                                       autocomplete="current-password" minlength="8" />
                                <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </div>
                            
                            <!-- CAPTCHA Section -->
                            <div class="captcha-container">
                                <div class="captcha-code" id="captchaDisplay">
                                    <img id="captchaImg" src="captcha_image.php?t=<?php echo time(); ?>"
                                         alt="CAPTCHA code" width="180" height="60"
                                         style="display:block;margin:0 auto;border-radius:4px;border:1px solid #ccc;">
                                </div>
                                <input type="text" name="captcha" class="form-control captcha-input" placeholder="Enter CAPTCHA code" required
                                       maxlength="6" autocomplete="off" oninput="this.value = this.value.toUpperCase()" />
                                <div class="captcha-controls">
                                    <a class="refresh-captcha" onclick="refreshCaptcha()" style="cursor: pointer;">
                                        <i class="fa fa-refresh"></i> Refresh CAPTCHA
                                    </a>
                                    <small>Case insensitive</small>
                                </div>
                                <div class="captcha-error" id="captchaError" style="color: #dc3545; font-size: 12px; margin-top: 5px; display: none;">
                                    Invalid CAPTCHA code. Please try again.
                                </div>
                            </div>
                            
                            <!-- Hidden input to guarantee login is submitted even if button is disabled by JS -->
                            <input type="hidden" name="login" value="login">
                            
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-lock"></i> Login
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for security and functionality -->
    <script>
        // Disable right-click, F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable keyboard shortcuts for developer tools
        document.addEventListener('keydown', function(e) {
            // Disable F12
            if (e.keyCode === 123) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+Shift+I
            if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+Shift+J
            if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+U
            if (e.ctrlKey && e.keyCode === 85) {
                e.preventDefault();
                return false;
            }
        });
        
        // Disable text selection
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable drag and drop
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Prevent copying
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Prevent cutting
        document.addEventListener('cut', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Prevent pasting
        document.addEventListener('paste', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Toggle password visibility
        const togglePasswordBtn = document.getElementById('togglePassword');
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function() {
                const passwordInput = document.getElementById('password');
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    this.setAttribute('aria-label', 'Show password');
                }
            });
        }
        
        // Refresh CAPTCHA — reloading the image itself generates a fresh
        // code server-side (see captcha_image.php); nothing is fetched
        // or read out of the page as text.
        function refreshCaptcha() {
            const img = document.getElementById('captchaImg');
            if (img) img.src = 'captcha_image.php?t=' + Date.now();
            const err = document.getElementById('captchaError');
            if (err) err.style.display = 'none';
            const input = document.querySelector('input[name="captcha"]');
            if (input) input.value = '';
        }

        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) {
                // Validate username format — Service Numbers are numeric only
                const usernameRegex = /^[0-9]+$/;
                if (!usernameRegex.test(usernameInput.value)) {
                    e.preventDefault();
                    alert('Username (Service Number) can only contain numbers.');
                    usernameInput.focus();
                    return false;
                }

                // Validate password length
                const passwordInput = document.getElementById('password');
                if (passwordInput.value.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    passwordInput.focus();
                    return false;
                }

                // CAPTCHA correctness is validated server-side against the
                // image that was actually rendered — there is nothing to
                // check client-side since the answer isn't present in the DOM.
            }

            // Disable submit button to prevent double submission
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';

            return true;
        });
        
        // Clear error messages when user starts typing
        const formInputs = document.querySelectorAll('#loginForm input');
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                const errorAlert = document.getElementById('errorAlert');
                if (errorAlert) {
                    errorAlert.style.display = 'none';
                }
                const captchaErr = document.getElementById('captchaError');
                if (captchaErr) {
                    captchaErr.style.display = 'none';
                }
            });
        });
        
        // Focus on username field on page load if empty
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput && !usernameInput.value) {
                usernameInput.focus();
            }
        });
        
        // Prevent form auto-complete (additional measure)
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.setAttribute('autocomplete', 'off');
        }
        
        // Add keyboard navigation for accessibility
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
            }
        });
    </script>
    
    <button id="installBtn" style="display:none;">
    Install NIIMS App
</button>

<script>
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();

    deferredPrompt = e;

    document.getElementById('installBtn').style.display = 'block';
});

document.getElementById('installBtn').addEventListener('click', async () => {

    deferredPrompt.prompt();

    const result = await deferredPrompt.userChoice;

    console.log(result.outcome);

    deferredPrompt = null;
});
</script>
</body>


</html>