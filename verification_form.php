<?php
// ====================================================================
// ULTRA HIGH TRAFFIC OPTIMIZATIONS
// ====================================================================

// Start session with non-blocking option if available
if (version_compare(PHP_VERSION, '7.2.0', '>=')) {
    // Use non-blocking sessions for PHP 7.2+
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cache_limiter', 'nocache');
    
    // Set short session lifetime for high traffic
    ini_set('session.gc_maxlifetime', 900);
    ini_set('session.cookie_lifetime', 0);
    
    // Use redis or memcached for sessions if available
    if (extension_loaded('redis')) {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', 'tcp://127.0.0.1:6379?timeout=2&read_timeout=2');
    } elseif (extension_loaded('memcached')) {
        ini_set('session.save_handler', 'memcached');
        ini_set('session.save_path', '127.0.0.1:11211');
    }
}

// Start session
session_start();

// Immediately close session for non-critical operations
function releaseSessionLock() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

// ====================================================================
// PHOTO LOADING OPTIMIZATIONS - ALWAYS LOAD
// ====================================================================

// Function to validate file path
function validateFilePath($path) {
    // Remove any directory traversal attempts
    $path = str_replace(['../', './', '..\\', '.\\'], '', $path);
    
    // Ensure path is within allowed directories
    $allowedPaths = ['images/', 'upload/'];
    $isAllowed = false;
    
    foreach ($allowedPaths as $allowedPath) {
        if (strpos($path, $allowedPath) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    return $isAllowed ? $path : false;
}

// Function to get photo with caching and retry mechanism - UPDATED TO SUPPORT MULTIPLE FORMATS
function getEmployeePhoto($serviceNo, $maxRetries = 3) {
    // Define supported image formats in order of preference
    $imageFormats = ['.png', '.jpg', '.jpeg', '.gif', '.webp'];
    $photoPath = null;
    $validatedPath = false;
    
    // Try each format until we find one that exists
    foreach ($imageFormats as $format) {
        $testPath = "upload/" . $serviceNo . $format;
        $testedPath = validateFilePath($testPath);
        
        if ($testedPath && file_exists($testedPath)) {
            $photoPath = $testedPath;
            $validatedPath = $testedPath;
            break;
        }
    }
    
    // If no photo found in any format
    if (!$photoPath) {
        return false;
    }
    
    // Try multiple times if file is locked (high traffic)
    $attempts = 0;
    while ($attempts < $maxRetries) {
        try {
            // Try to read the file with lock timeout
            $handle = @fopen($validatedPath, 'rb');
            if ($handle) {
                // Try to get a shared lock (non-blocking)
                if (flock($handle, LOCK_SH | LOCK_NB)) {
                    $imageData = stream_get_contents($handle);
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    
                    if ($imageData !== false && strlen($imageData) > 0) {
                        // Quick check if it's a valid image
                        $imageInfo = @getimagesizefromstring($imageData);
                        if ($imageInfo !== false) {
                            return [
                                'path' => $validatedPath,
                                'mime' => $imageInfo['mime'],
                                'width' => $imageInfo[0],
                                'height' => $imageInfo[1]
                            ];
                        }
                    }
                } else {
                    // Couldn't get lock, close and retry
                    fclose($handle);
                }
            }
            
            // If failed, wait briefly and retry
            $attempts++;
            if ($attempts < $maxRetries) {
                usleep(50000); // 50ms delay between retries
            }
            
        } catch (Exception $e) {
            error_log("Photo read attempt $attempts failed for $serviceNo: " . $e->getMessage());
            $attempts++;
            if ($attempts < $maxRetries) {
                usleep(100000); // 100ms delay
            }
        }
    }
    
    // After all retries failed, use cached version
    return getCachedPhoto($serviceNo, $validatedPath);
}

// Function to cache photos in memory/disk to reduce file system load - UPDATED
function getCachedPhoto($serviceNo, $originalPath) {
    $cacheDir = sys_get_temp_dir() . '/nis_photo_cache/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($serviceNo . $originalPath) . '.cache';
    
    // Check cache first (30 minute TTL)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
        // Verify cache is valid
        $cachedData = @file_get_contents($cacheFile);
        if ($cachedData && strlen($cachedData) > 100) {
            $imageInfo = @getimagesizefromstring($cachedData);
            if ($imageInfo !== false) {
                return [
                    'path' => $cacheFile,
                    'mime' => $imageInfo['mime'],
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'cached' => true
                ];
            }
        }
    }
    
    // Try to read and cache the original with retry
    $retryCount = 0;
    while ($retryCount < 2) {
        try {
            $imageData = @file_get_contents($originalPath);
            if ($imageData !== false && strlen($imageData) > 100) {
                // Verify it's a valid image
                $imageInfo = @getimagesizefromstring($imageData);
                if ($imageInfo === false) {
                    return false;
                }
                
                // Create a cached copy
                if (file_put_contents($cacheFile, $imageData, LOCK_EX) !== false) {
                    chmod($cacheFile, 0644);
                    return [
                        'path' => $cacheFile,
                        'mime' => $imageInfo['mime'],
                        'width' => $imageInfo[0],
                        'height' => $imageInfo[1],
                        'cached' => true
                    ];
                }
            }
        } catch (Exception $e) {
            // Silent fail, we'll try again or use placeholder
        }
        
        $retryCount++;
        if ($retryCount < 2) {
            usleep(100000); // 100ms delay
        }
    }
    
    return false;
}

// Function to serve photo with proper headers - UPDATED
function servePhoto($photoData) {
    if (!$photoData || !isset($photoData['path']) || !file_exists($photoData['path'])) {
        return false;
    }
    
    $photoPath = $photoData['path'];
    $mimeType = $photoData['mime'] ?? 'image/png';
    
    // Get file info
    $fileSize = filesize($photoPath);
    if ($fileSize < 100 || $fileSize > 5242880) { // 5MB limit
        return false;
    }
    
    // Set cache headers for browser (1 hour cache)
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=3600, immutable');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    header('Pragma: cache');
    header('Content-Length: ' . $fileSize);
    
    // Output the image
    readfile($photoPath);
    return true;
}

// Handle photo requests immediately (before any other processing)
if (isset($_GET['get_photo']) && isset($_GET['serviceNo'])) {
    // Close session to prevent blocking
    releaseSessionLock();
    
    // Validate service number
    $serviceNo = preg_replace('/[^0-9]/', '', $_GET['serviceNo']);
    if (strlen($serviceNo) === 5) {
        $photoData = getEmployeePhoto($serviceNo);
        if ($photoData && servePhoto($photoData)) {
            exit;
        }
    }
    
    // If no photo, serve SVG placeholder (extremely lightweight)
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=300'); // 5 minutes for placeholder
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="120" height="140" xmlns="http://www.w3.org/2000/svg">
        <rect width="100%" height="100%" fill="#f8f9fa"/>
        <rect x="5" y="5" width="110" height="130" stroke="#bdc3c7" stroke-width="2" fill="white"/>
        <text x="60" y="60" font-family="Arial" font-size="14" text-anchor="middle" fill="#7f8c8d">Photo</text>
        <text x="60" y="80" font-family="Arial" font-size="12" text-anchor="middle" fill="#7f8c8d">Not Available</text>
    </svg>';
    exit;
}

// Performance and memory optimizations
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);
ini_set('max_input_time', 30);
ini_set('mysqli.reconnect', 1);
ini_set('default_socket_timeout', 30);

// ====================================================================
// RATE LIMITING IMPLEMENTATION
// ====================================================================

function applyRateLimit($limit = 15, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $cacheDir = sys_get_temp_dir() . '/nis_rate_limit/';
    
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $rateFile = $cacheDir . md5($ip);
    $data = ['count' => 1, 'time' => $now];
    
    if (file_exists($rateFile)) {
        $fileData = file_get_contents($rateFile);
        if ($fileData !== false) {
            $savedData = json_decode($fileData, true);
            if (is_array($savedData)) {
                if ($savedData['time'] + $window > $now) {
                    if ($savedData['count'] >= $limit) {
                        return false; // Rate limit exceeded
                    }
                    $data['count'] = $savedData['count'] + 1;
                    $data['time'] = $savedData['time'];
                }
            }
        }
    }
    
    file_put_contents($rateFile, json_encode($data), LOCK_EX);
    return true;
}

// Apply rate limiting (15 requests per minute)
if (!applyRateLimit(15, 60)) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit exceeded',
        'message' => 'Please try again in a few minutes.'
    ]);
    exit;
}

// ====================================================================
// YOUR ORIGINAL CODE STARTS HERE (WITH OPTIMIZATIONS)
// ====================================================================

// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Changed to 0 for production
ini_set('log_errors', 1);
ini_set('error_log', 'php-errors.log');

// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\' https://cdnjs.cloudflare.com;');

// Initialize variables
$employeeData = null;
$error = '';
$success = '';
$confirmationMessage = '';
$conn = null;

// CSRF Token Generation and Validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ====================================================================
// CAPTCHA FUNCTIONS
// ====================================================================

function generateNewCaptcha() {
    // Generate simple math problem (numbers 1-10)
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_answer'] = $num1 * $num2;
    $_SESSION['captcha'] = "$num1 x $num2 = ?";
    return $_SESSION['captcha'];
}

// Handle CAPTCHA refresh request via AJAX
if (isset($_GET['refresh_captcha']) && $_GET['refresh_captcha'] == 1) {
    // Verify this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        // Close session to prevent blocking
        releaseSessionLock();
        
        // Generate new CAPTCHA
        $newCaptcha = generateNewCaptcha();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'captcha' => $newCaptcha,
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
        exit;
    } else {
        // Not an AJAX request - redirect to home
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// Initialize CAPTCHA if not set
if (empty($_SESSION['captcha'])) {
    generateNewCaptcha();
}

// ====================================================================
// DATABASE CONNECTION WITH CONNECTION POOLING
// ====================================================================

// Include database connection with security checks
try {
    // Check if db.php exists and is readable
    if (!file_exists("db/db.php") || !is_readable("db/db.php")) {
        throw new Exception("Database configuration file not accessible");
    }
    
    include("db/db.php");
    
    // Verify connection is established
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Set connection charset to UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }
    
    // Test the connection
    if (!mysqli_ping($conn)) {
        throw new Exception("Database connection is not active");
    }
    
    // Set timeouts for better performance
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 30);
    
} catch (Exception $e) {
    // Log error but don't expose details to user
    error_log("Database initialization failed: " . $e->getMessage());
    $error = "System initialization error. Please try again later.";
}

// ====================================================================
// QUERY CACHING SYSTEM
// ====================================================================

function getCachedQuery($key, $ttl = 60) {
    $cacheDir = sys_get_temp_dir() . '/nis_query_cache/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($key) . '.cache';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $data = file_get_contents($cacheFile);
        return $data ? unserialize($data) : false;
    }
    
    return false;
}

function setCachedQuery($key, $data, $ttl = 60) {
    $cacheDir = sys_get_temp_dir() . '/nis_query_cache/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($key) . '.cache';
    $result = file_put_contents($cacheFile, serialize($data), LOCK_EX);
    
    // Clean old cache files occasionally
    if (rand(1, 100) === 1) {
        $files = glob($cacheDir . '*.cache');
        foreach ($files as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > $ttl * 2) {
                unlink($file);
            }
        }
    }
    
    return $result !== false;
}

function deleteCachedQuery($key) {
    $cacheDir = sys_get_temp_dir() . '/nis_query_cache/';
    $cacheFile = $cacheDir . md5($key) . '.cache';
    
    if (file_exists($cacheFile)) {
        return unlink($cacheFile);
    }
    
    return true;
}

// ====================================================================
// INPUT VALIDATION FUNCTIONS
// ====================================================================

// Function to validate and sanitize input
function validateInput($input, $type = 'string', $maxLength = 255) {
    if ($input === null || $input === '') {
        return false;
    }
    
    $input = trim($input);
    
    // Check length
    if (strlen($input) > $maxLength) {
        return false;
    }
    
    switch ($type) {
        case 'alphanumeric':
            // Allow only letters, numbers, and basic punctuation
            if (!preg_match('/^[a-zA-Z0-9\s\-\.\']+$/', $input)) {
                return false;
            }
            break;
            
        case 'name':
            // Basic safety check - prevent obvious injection attempts
            if (empty($input)) {
                return false;
            }
            // Basic safety check - prevent obvious injection attempts
            if (preg_match('/[<>\"\'%;()&]/', $input)) {
                return false;
            }
            break;
            
        case 'serviceNo':
            // Allow only numbers 0-9, exactly 5 digits
            if (!preg_match('/^\d{5}$/', $input)) {
                return false;
            }
            break;
            
        case 'email':
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            break;
            
        case 'phone':
            // Basic phone validation
            if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $input)) {
                return false;
            }
            break;
            
        case 'date':
            // Validate date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                return false;
            }
            $date = DateTime::createFromFormat('Y-m-d', $input);
            if (!$date || $date->format('Y-m-d') !== $input) {
                return false;
            }
            break;
            
        case 'captcha':
            // Validate captcha - numbers only
            if (!preg_match('/^\d+$/', $input)) {
                return false;
            }
            break;
    }
    
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

// Function to escape data for database (additional layer)
function escapeData($conn, $data) {
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return mysqli_real_escape_string($conn, $item);
        }, $data);
    }
    return mysqli_real_escape_string($conn, $data);
}

// ====================================================================
// OPTIMIZED fetchEmployeeData FUNCTION WITH CACHING
// ====================================================================

function fetchEmployeeData($conn, $serviceNo, $surname) {
    try {
        // Check cache first
        $cacheKey = "employee_{$serviceNo}_{$surname}";
        $cachedData = getCachedQuery($cacheKey, 120); // Cache for 2 minutes
        
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        // Use prepared statement for search
        $sql = "SELECT 
                -- From tbl_emppersonal (main personal info)
                ep.serviceNo, ep.surname, ep.firstName, ep.middleName, ep.dob, 
                ep.marital_status, ep.maiden_name, ep.gender, ep.state_of_origin, 
                ep.town, ep.lga, ep.bloodType, ep.genotype, ep.email, ep.phone, 
                ep.passport, ep.signature, ep.confirmed_details, ep.date_of_entry,
                
                -- From tbl_employment
                emp.dofa, emp.dopa, emp.presentPosting, emp.currentRank, emp.empStatus,
                
                -- From tbl_finance
                f.IPPIS_No, f.bank, f.accountNo, f.PFA, f.pensionNo, f.NHF_No,
                
                -- From tbl_nok
                n.nok, n.nok_relationship, n.nok_phone,
                
                -- From tbl_qualification
                q.qualification, q.institution, q.certificate_number, q.date_obtained
                
                FROM tbl_emppersonal ep
                LEFT JOIN tbl_employment emp ON ep.serviceNo = emp.serviceNo
                LEFT JOIN tbl_finance f ON ep.serviceNo = f.serviceNo
                LEFT JOIN tbl_nok n ON ep.serviceNo = n.serviceNo
                LEFT JOIN tbl_qualification q ON ep.serviceNo = q.serviceNo
                WHERE ep.serviceNo = ? AND ep.surname = ?
                LIMIT 1";
        
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log("Database query preparation failed: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $serviceNo, $surname);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Query execution failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        mysqli_stmt_close($stmt);
        return null;
    }
    
    if (mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        // Cache the result
        setCachedQuery($cacheKey, $data, 120);
        
        return $data;
    }
    
    mysqli_stmt_close($stmt);
    return null;
    
    } catch (Exception $e) {
        error_log("Fetch employee data error: " . $e->getMessage());
        return null;
    }
}

// ====================================================================
// PROCESS REQUESTS WITH OPTIMIZATIONS
// ====================================================================

// Process confirmation request with security checks
if ($conn && isset($_POST['confirm_details']) && isset($_POST['csrf_token'])) {
    try {
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Invalid security token");
        }
        
        // Validate inputs
        $serviceNo = validateInput($_POST['serviceNo'] ?? '', 'serviceNo', 5);
        $surname = validateInput($_POST['surname'] ?? '', 'name', 50);
        
        if (!$serviceNo || !$surname) {
            throw new Exception("Invalid service number or surname format");
        }
        
        // Use prepared statement for update
        $updateSql = "UPDATE tbl_emppersonal SET confirmed_details = 'confirmed', date_of_entry = NOW() WHERE serviceNo = ?";
        $stmt = mysqli_prepare($conn, $updateSql);
        
        if (!$stmt) {
            throw new Exception("Database prepare statement failed");
        }
        
        mysqli_stmt_bind_param($stmt, "s", $serviceNo);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update confirmation status");
        }
        
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affectedRows > 0) {
            $confirmationMessage = "";
            
            // Regenerate CSRF token after successful action
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Clear cache for this employee
            $cacheKey = "employee_{$serviceNo}_{$surname}";
            deleteCachedQuery($cacheKey);
            
            // Fetch the updated employee data to display
            $employeeData = fetchEmployeeData($conn, $serviceNo, $surname);
            
            if ($employeeData) {
                $success = "Employee record found successfully!";
                
                // Clear search data from session after successful confirmation
                unset($_SESSION['search_data']);
            } else {
                throw new Exception("Could not retrieve updated employee data");
            }
            
        } else {
            throw new Exception("No records were updated");
        }
        
    } catch (Exception $e) {
        error_log("Confirmation error: " . $e->getMessage());
        $confirmationMessage = "❌ Error confirming details. Please try again.";
        
        // If confirmation failed but we have serviceNo and surname, try to fetch data anyway
        if (isset($serviceNo) && isset($surname)) {
            $employeeData = fetchEmployeeData($conn, $serviceNo, $surname);
        }
    }
}

// Process search form with security checks
if ($conn && $_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['confirm_details'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Invalid security token");
        }
        
        // Validate CAPTCHA
        if (!isset($_POST['captcha']) || empty($_POST['captcha'])) {
            throw new Exception("Please solve the CAPTCHA verification.");
        }
        
        $captchaInput = validateInput($_POST['captcha'], 'captcha', 3);
        if (!$captchaInput) {
            throw new Exception("Invalid CAPTCHA format. Please enter numbers only.");
        }
        
        // Check CAPTCHA answer
        if (!isset($_SESSION['captcha_answer']) || (int)$captchaInput !== (int)$_SESSION['captcha_answer']) {
            // Generate new CAPTCHA on failed attempt
            generateNewCaptcha();
            
            throw new Exception("Incorrect CAPTCHA answer. Please try again.");
        }
        
        // Validate input
        if (empty($_POST['serviceNo']) || empty($_POST['surname'])) {
            throw new Exception("Please enter both Service Number and Surname.");
        }
        
        $serviceNo = validateInput($_POST['serviceNo'], 'serviceNo', 5);
        $surname = validateInput($_POST['surname'], 'name', 50);
        
        if (!$serviceNo || !$surname) {
            throw new Exception("Invalid input format detected. Service Number must be 5 digits (0-9 only).");
        }
        
        // Store sanitized data in session for repopulation if needed
        $_SESSION['search_data'] = [
            'serviceNo' => $serviceNo,
            'surname' => $surname
        ];
        
        // Fetch employee data (uses caching)
        $employeeData = fetchEmployeeData($conn, $serviceNo, $surname);
        
        if ($employeeData) {
            $success = "Employee record found successfully!";
            
            // Clear search data from session after successful search
            unset($_SESSION['search_data']);
            
            // Regenerate CAPTCHA after successful search
            generateNewCaptcha();
            
            // Regenerate session ID after successful sensitive operation
            session_regenerate_id(true);
            
        } else {
            // Log failed search attempts (for security monitoring)
            error_log("Failed search attempt - ServiceNo: $serviceNo, Surname: $surname");
            
            // Generate new CAPTCHA on failed search
            generateNewCaptcha();
            
            // Show specific error with the Service No and Surname used
            throw new Exception("No employee found with Service No: <strong>" . htmlspecialchars($serviceNo) . "</strong> and Surname: <strong>" . htmlspecialchars($surname) . "</strong>.");
        }
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $error = $e->getMessage();
        
        // Keep search data in session for repopulation on error
        $repopulateServiceNo = $_SESSION['search_data']['serviceNo'] ?? '';
        $repopulateSurname = $_SESSION['search_data']['surname'] ?? '';
    }
}

// Get repopulation data from session if available (only on errors)
$repopulateServiceNo = $_SESSION['search_data']['serviceNo'] ?? '';
$repopulateSurname = $_SESSION['search_data']['surname'] ?? '';

// Clear repopulation data if we have successful employee data
if ($employeeData) {
    $repopulateServiceNo = '';
    $repopulateSurname = '';
    unset($_SESSION['search_data']);
}

// If we have employee data but no success message (happens after confirmation)
if ($employeeData && empty($success) && empty($error)) {
    $success = "Employee record loaded successfully!";
}

// ====================================================================
// COUNTDOWN TIMER DEADLINE CHECK
// ====================================================================

// Check if current date is past the deadline (February 15, 2026 at 11:59 PM)
$deadline_timestamp = strtotime('2026-02-15 23:59:59');
$current_timestamp = time();
$system_closed = ($current_timestamp > $deadline_timestamp);

// ====================================================================
// OUTPUT BUFFERING AND COMPRESSION
// ====================================================================

// Check if client accepts gzip
if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

// ====================================================================
// HTML OUTPUT
// ====================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    
    <!-- Open Graph Meta Tags for Social Media Sharing -->
    <meta property="og:title" content="NIS Employee Verification System">
    <meta property="og:description" content="Nigeria Immigration Service - Official employee verification portal">
    <meta property="og:image" content="https://niims.immigration.gov.ng/images/favicon.ico">
    <meta property="og:url" content="https://niims.immigration.gov.ng/verification">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Nigeria Immigration Service">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="NIS Employee Verification System">
    <meta name="twitter:description" content="Nigeria Immigration Service - Official employee verification portal">
    <meta name="twitter:image" content="https://niims.immigration.gov.ng/images/favicon.ico">
    
    <!-- Additional Meta Tags -->
    <meta name="description" content="Nigeria Immigration Service - Employee Verification and Identification Management System">
    <meta name="keywords" content="NIS, Nigeria Immigration Service, Employee Verification, Identification Management">
    <meta name="author" content="Nigeria Immigration Service">
    
    <!-- Favicon fix -->
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="images/favicon.ico">
    <title>NIS Employee Verification System</title>
    
    <!-- Font Awesome for icons - Updated to ensure icons load -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('images/nis.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-position: center center;
            min-height: 100vh;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Add an overlay for better readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(39, 174, 96, 0.7); /* Semi-transparent green overlay */
            z-index: -1;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            word-break: break-word;
        }
        
        .header p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        /* ============================================ */
        /* PROFESSIONAL COUNTDOWN TIMER - FULLY RESPONSIVE */
        /* ============================================ */
        .countdown-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 5px;
            margin-bottom: 5px;
            width: 100%;
        }
        
        .countdown-timer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 6px 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            color: white;
            font-weight: 500;
            letter-spacing: 0.5px;
            width: auto;
            max-width: 100%;
        }
        
        .countdown-icon {
            margin-right: 6px;
            font-size: 14px;
            opacity: 0.9;
            display: inline-flex;
            align-items: center;
        }
        
        .countdown-numbers {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 2px;
            margin: 2px 0;
        }
        
        .time-segment {
            background: rgba(0, 0, 0, 0.35);
            border-radius: 4px;
            padding: 3px 5px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            min-width: 32px;
            text-align: center;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-block;
        }
        
        .time-separator {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            margin: 0 1px;
            font-size: 14px;
            display: inline-block;
        }
        
        .deadline-label {
            margin-left: 8px;
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 1px solid rgba(255, 255, 255, 0.3);
            padding-left: 8px;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
        
        /* Mobile-optimized countdown - applies on all small screens */
        @media (max-width: 600px) {
            .countdown-timer {
                flex-direction: column;
                padding: 10px 15px;
                border-radius: 20px;
                width: 100%;
            }
            
            .countdown-icon {
                margin-right: 0;
                margin-bottom: 4px;
            }
            
            .countdown-numbers {
                margin: 4px 0;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .time-segment {
                min-width: 38px;
                font-size: 16px;
                padding: 4px 6px;
                margin: 2px;
            }
            
            .deadline-label {
                border-left: none;
                padding-left: 0;
                margin-left: 0;
                margin-top: 4px;
                white-space: normal;
                text-align: center;
                font-size: 11px;
                border-top: 1px solid rgba(255, 255, 255, 0.3);
                padding-top: 6px;
                width: 100%;
                justify-content: center;
            }
            
            .time-separator {
                margin: 0 2px;
            }
        }
        
        /* Small phones (Samsung, iPhone SE, etc) */
        @media (max-width: 400px) {
            .countdown-timer {
                padding: 8px 12px;
            }
            
            .time-segment {
                min-width: 32px;
                font-size: 14px;
                padding: 3px 4px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header p {
                font-size: 0.9rem;
            }
        }
        
        /* System closed message */
        .system-closed-banner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(231, 76, 60, 0.9);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 8px 22px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 10px rgba(231, 76, 60, 0.3);
            color: white;
            font-weight: 600;
            font-size: 15px;
            margin-top: 5px;
            margin-bottom: 5px;
            width: auto;
            max-width: 100%;
            flex-wrap: wrap;
        }
        
        .system-closed-banner i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        @media (max-width: 600px) {
            .system-closed-banner {
                padding: 8px 16px;
                font-size: 14px;
                text-align: center;
                width: 100%;
            }
        }
        /* ============================================ */
        
        .search-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            backdrop-filter: blur(5px);
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            align-items: end;
        }
        
        /* UPDATED: COMPACT CAPTCHA CONTAINER for Desktop */
        .captcha-container {
            grid-column: span 2;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: center;
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            margin-top: 5px;
        }
        
        .captcha-display {
            display: flex;
            align-items: center;
            gap: 15px;
            white-space: nowrap;
        }
        
        .captcha-text {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            background: white;
            padding: 6px 12px;
            border-radius: 5px;
            border: 1px solid #ced4da;
            min-width: 100px;
            text-align: center;
        }
        
        .captcha-controls {
            display: flex;
            gap: 8px;
        }
        
        .captcha-btn {
            background: #6c757d;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 14px;
            font-family: 'Font Awesome 6 Free', 'Segoe UI', Tahoma, sans-serif;
            font-weight: 900;
        }
        
        .captcha-btn:hover {
            background: #5a6268;
            transform: scale(1.05);
        }
        
        .captcha-btn.audio {
            background: #17a2b8;
        }
        
        .captcha-btn.audio:hover {
            background: #138496;
        }
        
        .captcha-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        
        .icon-fallback {
            display: none;
            font-size: 14px;
        }
        
        .no-fa .captcha-btn .fas {
            display: none;
        }
        
        .no-fa .captcha-btn .icon-fallback {
            display: inline;
        }
        
        .captcha-input-container {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-self: end;
        }
        
        .captcha-label {
            font-weight: 600;
            color: #333;
            white-space: nowrap;
            font-size: 14px;
        }
        
        .captcha-input {
            padding: 8px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            width: 80px;
            min-width: 80px;
        }
        
        .captcha-input:focus {
            outline: none;
            border-color: #27ae60;
        }
        
        .captcha-hint {
            font-size: 12px;
            color: #6c757d;
            grid-column: span 3;
            text-align: center;
            margin-top: 5px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="text"] {
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .submit-container {
            grid-column: span 2;
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }
        
        .search-btn {
            background: linear-gradient(135deg, #27ae60 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .search-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-weight: 500;
        }
        
        .error {
            background: #ffe6e6;
            color: #d63031;
            border: 1px solid #ff7675;
        }
        
        .success {
            background: #e6f7e6;
            color: #27ae60;
            border: 1px solid #58d68d;
        }
        
        .confirmation {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #90caf9;
        }
        
        .id-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            display: <?php echo $employeeData ? 'block' : 'none'; ?>;
            backdrop-filter: blur(5px);
        }
        
        .important-notice {
            background: rgba(255, 243, 205, 0.95);
            border: 2px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            backdrop-filter: blur(5px);
        }
        
        .important-notice p {
            color: #856404;
            font-size: 16px;
            line-height: 1.6;
            margin: 0;
            font-weight: 500;
        }
        
        .important-notice strong {
            color: #e74c3c;
            font-size: 18px;
        }
        
        .important-notice h4 {
            margin-top: 10px;
            color: #2c3e50;
        }
        
        .confirmation-status {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .confirmation-status.confirmed {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .confirmation-status.pending {
            background: #fff3e0;
            border-color: #ff9800;
            color: #e65100;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #34495e;
        }
        
        .nis-logo {
            text-align: center;
        }
        
        .nis-logo h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .nis-logo p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* FIXED: Employee photo styles without blinking */
        .employee-photo {
            width: 120px;
            height: 140px;
            border: 2px solid #bdc3c7;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            overflow: hidden;
            position: relative;
        }
        
        .employee-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 1;
            transition: opacity 0.3s ease;
            position: relative;
            z-index: 2;
        }
        
        .photo-placeholder {
            text-align: center;
            color: #7f8c8d;
            font-size: 0.8rem;
            padding: 10px;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: #f8f9fa;
        }
        
        /* Remove loading indicator completely */
        .photo-loading {
            display: none;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #34495e;
        }
        
        .section-title {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
        }
        
        .info-value {
            color: #34495e;
            flex: 2;
            text-align: right;
            word-break: break-word;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .print-btn, .new-search-btn, .confirm-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .print-btn {
            background: #27ae60;
            color: white;
        }
        
        .print-btn:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        .new-search-btn {
            background: #27ae60;
            color: white;
        }

        .new-search-btn:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        .confirm-btn {
            background: #e67e22;
            color: white;
        }
        
        .confirm-btn:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        
        .confirm-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .completed-message {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            color: #2e7d32;
            font-weight: 600;
        }
        
        /* Hide important notice when printing */
        @media print {
            .important-notice,
            .confirmation-status,
            .confirm-btn,
            .completed-message,
            .countdown-timer,
            .system-closed-banner,
            .countdown-wrapper {
                display: none !important;
            }
            body { 
                padding: 0;
                background: white !important;
            }
            body::before {
                display: none !important;
            }
            .container {
                background: white;
            }
            .search-card, .id-card {
                background: white;
                box-shadow: none;
            }
            button { display: none; }
        }
        
        /* Mobile styles */
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .captcha-container {
                grid-column: span 1;
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .captcha-display {
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                white-space: normal;
            }
            
            .captcha-input-container {
                justify-content: center;
                width: 100%;
                margin-top: 10px;
            }
            
            .captcha-input {
                width: 100%;
                max-width: 120px;
            }
            
            .captcha-hint {
                grid-column: span 1;
            }
            
            .submit-container {
                grid-column: span 1;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .important-notice {
                padding: 15px;
            }
            
            .important-notice p {
                font-size: 14px;
            }
            
            .print-btn, .new-search-btn, .confirm-btn {
                width: 100%;
            }
            
            .search-btn {
                width: 100%;
            }
            
            .nis-logo h2 {
                font-size: 1.5rem;
            }
        }
        
        /* Header logo styling */
        .header-logo {
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .header-favicon {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            background: white;
            padding: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .header-favicon:hover {
            transform: scale(1.05);
        }

        .logo-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            background: white;
            color: #27ae60;
            font-size: 22px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-favicon, .logo-placeholder {
                width: 50px;
                height: 50px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header p {
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 400px) {
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header p {
                font-size: 0.85rem;
            }
            
            .header-favicon, .logo-placeholder {
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
        }
        
        /* Captcha accessibility styles */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        /* Unicode fallback for icons */
        .unicode-icon {
            display: none;
            font-size: 14px;
        }
        
        .no-fa .fas {
            display: none;
        }
        
        .no-fa .unicode-icon {
            display: inline;
        }
        
        /* Right-click notification styles */
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        .right-click-notification {
            position: fixed;
            top: 10px;
            right: 10px;
            background: transparent;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            z-index: 10000;
            font-size: 14px;
            animation: fadeOut 3s forwards;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            pointer-events: none;
        }
    </style>
    
    <script>
        // ====================================================================
        // PROFESSIONAL COUNTDOWN TIMER - FULLY RESPONSIVE
        // ====================================================================
        
        // Set the deadline: February 15, 2026 at 23:59:59
        const deadlineDate = new Date('February 15, 2026 23:59:59').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = deadlineDate - now;
            
            // Time calculations
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Format with leading zeros
            const formattedDays = days.toString().padStart(2, '0');
            const formattedHours = hours.toString().padStart(2, '0');
            const formattedMinutes = minutes.toString().padStart(2, '0');
            const formattedSeconds = seconds.toString().padStart(2, '0');
            
            // Update the DOM if timer exists
            const daysElement = document.getElementById('countdown-days');
            const hoursElement = document.getElementById('countdown-hours');
            const minutesElement = document.getElementById('countdown-minutes');
            const secondsElement = document.getElementById('countdown-seconds');
            const timerContainer = document.getElementById('countdown-timer');
            const closedBanner = document.getElementById('system-closed-banner');
            
            if (daysElement && hoursElement && minutesElement && secondsElement) {
                daysElement.textContent = formattedDays;
                hoursElement.textContent = formattedHours;
                minutesElement.textContent = formattedMinutes;
                secondsElement.textContent = formattedSeconds;
            }
            
            // If the countdown is over, show closed banner and hide timer
            if (distance < 0) {
                if (timerContainer) timerContainer.style.display = 'none';
                if (closedBanner) closedBanner.style.display = 'flex';
                
                // Disable all search functionality if system is closed
                const searchBtn = document.querySelector('.search-btn');
                const serviceNoInput = document.getElementById('serviceNo');
                const surnameInput = document.getElementById('surname');
                const captchaInput = document.getElementById('captchaInput');
                
                if (searchBtn) {
                    searchBtn.disabled = true;
                    searchBtn.style.opacity = '0.6';
                    searchBtn.title = 'System is closed after February 15, 2026';
                }
                
                if (serviceNoInput) serviceNoInput.disabled = true;
                if (surnameInput) surnameInput.disabled = true;
                if (captchaInput) captchaInput.disabled = true;
                
                return;
            }
        }
        
        // Function to check if system is closed (PHP fallback)
        function checkSystemStatus() {
            const isSystemClosed = <?php echo $system_closed ? 'true' : 'false'; ?>;
            
            if (isSystemClosed) {
                const timerContainer = document.getElementById('countdown-timer');
                const closedBanner = document.getElementById('system-closed-banner');
                
                if (timerContainer) timerContainer.style.display = 'none';
                if (closedBanner) closedBanner.style.display = 'flex';
                
                // Disable search functionality
                const searchBtn = document.querySelector('.search-btn');
                const serviceNoInput = document.getElementById('serviceNo');
                const surnameInput = document.getElementById('surname');
                const captchaInput = document.getElementById('captchaInput');
                
                if (searchBtn) {
                    searchBtn.disabled = true;
                    searchBtn.style.opacity = '0.6';
                    searchBtn.title = 'System is closed after February 15, 2026';
                }
                
                if (serviceNoInput) serviceNoInput.disabled = true;
                if (surnameInput) surnameInput.disabled = true;
                if (captchaInput) captchaInput.disabled = true;
            }
        }
        
        // ====================================================================
        // EXISTING JAVASCRIPT FUNCTIONS (ALL PRESERVED)
        // ====================================================================
        
        // Function to refresh CAPTCHA via AJAX
        async function refreshCaptcha() {
            try {
                const refreshBtn = document.querySelector('.captcha-btn:first-child');
                const captchaText = document.querySelector('.captcha-text');
                const audioBtn = document.querySelector('.captcha-btn.audio');
                const originalText = captchaText.textContent;
                const originalRefreshHTML = refreshBtn.innerHTML;
                const originalAudioHTML = audioBtn.innerHTML;
                
                // Disable buttons and show loading
                refreshBtn.disabled = true;
                audioBtn.disabled = true;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                audioBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                captchaText.textContent = 'Loading...';
                captchaText.style.color = '#6c757d';
                
                // Make AJAX request to refresh CAPTCHA
                const response = await fetch('?refresh_captcha=1', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Update CAPTCHA text
                    captchaText.textContent = data.captcha;
                    captchaText.style.color = '#2c3e50';
                    
                    // Clear CAPTCHA input field
                    document.getElementById('captchaInput').value = '';
                    
                    // Focus on CAPTCHA input
                    document.getElementById('captchaInput').focus();
                    
                    // Show success message briefly
                    const originalHint = document.querySelector('.captcha-hint').textContent;
                    document.querySelector('.captcha-hint').textContent = 'CAPTCHA refreshed successfully!';
                    document.querySelector('.captcha-hint').style.color = '#27ae60';
                    
                    setTimeout(() => {
                        document.querySelector('.captcha-hint').textContent = originalHint;
                        document.querySelector('.captcha-hint').style.color = '#6c757d';
                    }, 2000);
                    
                } else {
                    throw new Error('Failed to refresh CAPTCHA');
                }
                
            } catch (error) {
                console.error('Error refreshing CAPTCHA:', error);
                
                // Restore original text
                const captchaText = document.querySelector('.captcha-text');
                captchaText.textContent = '<?php echo htmlspecialchars($_SESSION['captcha'] ?? 'Error'); ?>';
                captchaText.style.color = '#dc3545';
                
                // Show error message
                document.querySelector('.captcha-hint').textContent = 'Failed to refresh CAPTCHA. Please try again.';
                document.querySelector('.captcha-hint').style.color = '#dc3545';
                
                setTimeout(() => {
                    document.querySelector('.captcha-hint').textContent = 'Solve the multiplication problem';
                    document.querySelector('.captcha-hint').style.color = '#6c757d';
                    captchaText.style.color = '#2c3e50';
                }, 3000);
                
            } finally {
                // Re-enable buttons
                const refreshBtn = document.querySelector('.captcha-btn:first-child');
                const audioBtn = document.querySelector('.captcha-btn.audio');
                
                refreshBtn.disabled = false;
                audioBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-redo"></i><span class="unicode-icon" aria-hidden="true">↻</span>';
                audioBtn.innerHTML = '<i class="fas fa-volume-up"></i><span class="unicode-icon" aria-hidden="true">🔊</span>';
            }
        }
        
        // Function to speak CAPTCHA (text-to-speech)
        function speakCaptcha() {
            try {
                const captchaText = document.querySelector('.captcha-text').textContent;
                if (!captchaText || captchaText === 'Loading...') {
                    return;
                }
                
                // Speak the CAPTCHA
                const utterance = new SpeechSynthesisUtterance(captchaText.replace('x', 'multiplied by'));
                utterance.lang = 'en-US';
                utterance.rate = 0.8;
                utterance.pitch = 1;
                utterance.volume = 1;
                
                // Stop any ongoing speech
                speechSynthesis.cancel();
                
                // Speak the text
                speechSynthesis.speak(utterance);
                
                // Visual feedback
                const audioBtn = document.querySelector('.captcha-btn.audio');
                const originalHTML = audioBtn.innerHTML;
                audioBtn.innerHTML = '<i class="fas fa-volume-up fa-beat"></i>';
                audioBtn.style.background = '#138496';
                
                // Restore button after speech
                utterance.onend = function() {
                    setTimeout(() => {
                        audioBtn.innerHTML = originalHTML;
                        audioBtn.style.background = '';
                    }, 500);
                };
                
            } catch (error) {
                console.error('Error speaking CAPTCHA:', error);
            }
        }
        
        // Function to validate CAPTCHA on client side
        function validateCaptcha() {
            const captchaInput = document.getElementById('captchaInput').value;
            
            // Basic validation - must be a number
            if (!captchaInput || !/^\d+$/.test(captchaInput)) {
                alert('Please enter a valid number for the CAPTCHA.');
                return false;
            }
            
            return true;
        }
        
        // Function to handle form submission
        function handleFormSubmit() {
            // Check if system is closed (PHP check)
            <?php if ($system_closed): ?>
                alert('This system is now closed. The verification deadline of February 15, 2026 has passed.');
                return false;
            <?php endif; ?>
            
            if (!validateCaptcha()) {
                return false;
            }
            
            // Show loading state on submit button
            const submitBtn = document.querySelector('.search-btn');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Clear search fields immediately on form submission
            setTimeout(() => {
                document.getElementById('serviceNo').value = '';
                document.getElementById('surname').value = '';
                document.getElementById('captchaInput').value = '';
                
                // Restore submit button after 2 seconds
                setTimeout(() => {
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                }, 2000);
            }, 50);
            
            return true;
        }
        
        // Function to confirm submission
        function confirmSubmission() {
            // Show confirmation dialog
            const isConfirmed = confirm('Are you sure you want to confirm these details as accurate? This action cannot be undone.');
            
            if (!isConfirmed) {
                return false; // User cancelled
            }
            
            // Show loading state on confirm button
            const confirmBtn = document.querySelector('.confirm-btn');
            if (confirmBtn) {
                const originalHTML = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming...';
                confirmBtn.disabled = true;
                
                // Restore button after 3 seconds (in case submission fails)
                setTimeout(() => {
                    confirmBtn.innerHTML = originalHTML;
                    confirmBtn.disabled = false;
                }, 3000);
            }
            
            return true; // Allow form submission
        }
        
        // Function to attach confirmation handler to the confirm button
        function attachConfirmationHandler() {
            const confirmBtn = document.querySelector('.confirm-btn');
            if (confirmBtn) {
                // Remove any existing click handlers
                confirmBtn.onclick = null;
                
                // The form will handle the submission, we just need to attach the onsubmit handler
                const form = confirmBtn.closest('form');
                if (form) {
                    form.onsubmit = confirmSubmission;
                }
            }
        }
        
        // Add input validation on client side
        function validateSearchInput() {
            const serviceNo = document.getElementById('serviceNo').value;
            const surname = document.getElementById('surname').value;
            const captcha = document.getElementById('captchaInput').value;
            
            // Basic validation
            if (!serviceNo || !surname || !captcha) {
                alert('Please enter all required fields including CAPTCHA.');
                return false;
            }
            
            // Validate service number format (only numbers 0-9, exactly 5 digits)
            const serviceNoRegex = /^\d{5}$/;
            if (!serviceNoRegex.test(serviceNo)) {
                alert('Service Number must be exactly 5 digits (numbers 0-9 only).');
                return false;
            }
            
            // Validate CAPTCHA is a number
            const captchaRegex = /^\d+$/;
            if (!captchaRegex.test(captcha)) {
                alert('CAPTCHA must contain numbers only.');
                return false;
            }
            
            return true;
        }
        
        // ====================================================================
        // SIMPLIFIED PHOTO LOADING - NO BLINKING
        // ====================================================================
        
        function handlePhotoLoad(imgElement) {
            // Image loaded successfully - ensure it's visible
            imgElement.style.opacity = '1';
            console.log('Photo loaded successfully');
        }
        
        function handlePhotoError(imgElement) {
            console.log('Photo failed to load');
            
            // Hide the broken image
            imgElement.style.display = 'none';
            
            // Show placeholder
            const placeholder = document.createElement('div');
            placeholder.className = 'photo-placeholder';
            placeholder.innerHTML = 'Photo<br>Not Available';
            imgElement.parentElement.appendChild(placeholder);
        }
        
        // ====================================================================
        // RIGHT-CLICK DISABLING FUNCTIONALITY
        // ====================================================================
        
        function disableRightClick(e) {
            // Prevent default right-click behavior
            e.preventDefault();
            
            // Show notification
            showRightClickNotification();
        }
        
        function showRightClickNotification() {
            // Remove any existing notification
            const existingNotification = document.querySelector('.right-click-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = 'right-click-notification';
            notification.textContent = '';
            document.body.appendChild(notification);
            
            // Remove notification after animation
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
        
        // ====================================================================
        // INITIALIZATION FUNCTIONS
        // ====================================================================
        
        // Initialize photo loading when page loads
        function initializePhotos() {
            const employeePhotos = document.querySelectorAll('.employee-photo img');
            
            employeePhotos.forEach(img => {
                // Add load and error handlers
                img.addEventListener('load', function() {
                    handlePhotoLoad(this);
                });
                
                img.addEventListener('error', function() {
                    handlePhotoError(this);
                });
                
                // If image is already loaded (cached), trigger load event
                if (img.complete) {
                    if (img.naturalHeight !== 0) {
                        handlePhotoLoad(img);
                    } else {
                        handlePhotoError(img);
                    }
                }
            });
        }
        
        // Clear form and show/hide sections on page load
        window.addEventListener('load', function() {
            // Check if Font Awesome loaded
            checkFontAwesome();
            
            // DISABLE RIGHT-CLICK ON ENTIRE PAGE
            document.addEventListener('contextmenu', disableRightClick);
            
            // Prevent drag and drop of images
            document.addEventListener('dragstart', function(e) {
                if (e.target.tagName === 'IMG') {
                    e.preventDefault();
                }
            });
            
            // Focus on service number field (if not disabled)
            const serviceNoField = document.getElementById('serviceNo');
            if (serviceNoField && !serviceNoField.disabled) {
                serviceNoField.focus();
            }
            
            // Add auto-advance for CAPTCHA input
            const captchaInput = document.getElementById('captchaInput');
            if (captchaInput) {
                captchaInput.addEventListener('input', function(e) {
                    // Auto-advance to submit button on Enter
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.querySelector('.search-btn').click();
                    }
                });
            }
            
            // Hide ID card if no employee data
            const idCard = document.getElementById('idCard');
            if (idCard && !<?php echo $employeeData ? 'true' : 'false'; ?>) {
                idCard.style.display = 'none';
            }
            
            // Scroll to result if we have search results
            scrollToResult();
            
            // Add hover effects for CAPTCHA buttons
            const captchaButtons = document.querySelectorAll('.captcha-btn');
            captchaButtons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    if (!this.disabled) {
                        this.style.transform = 'scale(1.05)';
                    }
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Attach confirmation handler
            attachConfirmationHandler();
            
            // Initialize photo loading
            initializePhotos();
            
            // Initialize countdown timer
            updateCountdown();
            setInterval(updateCountdown, 1000);
            
            // Check system status
            checkSystemStatus();
        });
        
        function checkFontAwesome() {
            const testIcon = document.createElement('i');
            testIcon.className = 'fas fa-font-awesome';
            testIcon.style.position = 'absolute';
            testIcon.style.left = '-9999px';
            document.body.appendChild(testIcon);
            
            const computedStyle = window.getComputedStyle(testIcon, '::before');
            const content = computedStyle.content;
            document.body.removeChild(testIcon);
            
            // If Font Awesome didn't load, add fallback class
            if (content === 'none' || content === '' || content === 'normal') {
                document.documentElement.classList.add('no-fa');
            }
        }
        
        function scrollToResult() {
            <?php if ($employeeData): ?>
                // If employee data found, scroll to confirmation status
                setTimeout(function() {
                    const confirmationStatus = document.querySelector('.confirmation-status');
                    if (confirmationStatus) {
                        confirmationStatus.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 300);
            <?php elseif (!empty($error)): ?>
                // If error message, scroll to error
                setTimeout(function() {
                    const errorMessage = document.querySelector('.message.error');
                    if (errorMessage) {
                        errorMessage.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 300);
            <?php endif; ?>
        }
        
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Print function
        function printIDCard() {
            const printContent = document.getElementById('idCard').innerHTML;
            const originalContent = document.body.innerHTML;
            
            // Sanitize content before printing
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = printContent;
            
            // Remove sensitive buttons and messages
            const elementsToRemove = tempDiv.querySelectorAll('.important-notice, .confirmation-status, .confirm-btn, .completed-message, .security-notice');
            elementsToRemove.forEach(el => el.remove());
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Employee ID Card - <?php echo htmlspecialchars($employeeData['serviceNo'] ?? ''); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; background: white; }
                        .id-card { max-width: 800px; margin: 0 auto; border: 2px solid #333; padding: 20px; }
                        .important-notice, 
                        .confirmation-status,
                        .confirm-btn,
                        .completed-message,
                        .security-notice,
                        .action-buttons { 
                            display: none !important;
                        }
                        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
                        .info-section { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #ddd; }
                        .section-title { font-weight: bold; margin-bottom: 8px; color: #2c3e50; font-size: 14px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
                        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }
                        .info-label { font-weight: bold; }
                        @media print {
                            @page { margin: 0.5cm; }
                            body { padding: 0; }
                            button, .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="id-card">${tempDiv.innerHTML}</div>
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload();
        }
        
        // Add security: Clear sensitive data from form if user navigates away
        window.addEventListener('beforeunload', function() {
            document.getElementById('serviceNo').value = '';
            document.getElementById('surname').value = '';
            document.getElementById('captchaInput').value = '';
        });
        
        // Auto-focus on CAPTCHA input after filling other fields
        const serviceNoField = document.getElementById('serviceNo');
        const surnameField = document.getElementById('surname');
        
        if (serviceNoField) {
            serviceNoField.addEventListener('blur', function() {
                if (this.value && surnameField && surnameField.value) {
                    const captchaInput = document.getElementById('captchaInput');
                    if (captchaInput && !captchaInput.disabled) {
                        captchaInput.focus();
                    }
                }
            });
        }
        
        if (surnameField) {
            surnameField.addEventListener('blur', function() {
                if (this.value && serviceNoField && serviceNoField.value) {
                    const captchaInput = document.getElementById('captchaInput');
                    if (captchaInput && !captchaInput.disabled) {
                        captchaInput.focus();
                    }
                }
            });
        }
        
        // Auto-submit CAPTCHA on Enter key
        const captchaInputField = document.getElementById('captchaInput');
        if (captchaInputField) {
            captchaInputField.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (validateSearchInput()) {
                        document.querySelector('.search-btn').click();
                    }
                }
            });
        }
        
        // Initialize tooltips for CAPTCHA buttons
        document.querySelectorAll('.captcha-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                if (this.disabled) return;
                this.style.transform = 'scale(1.05)';
                this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-logo">
                <?php
                // Path to your favicon/logo with security validation
                $logoPath = "images/favicon.ico";
                $validatedPath = validateFilePath($logoPath);
                
                if ($validatedPath && file_exists($validatedPath)):
                ?>
                    <img src="<?php echo htmlspecialchars($validatedPath); ?>" alt="NIS Logo" class="header-favicon">
                <?php else: ?>
                    <div class="logo-placeholder">NIS</div>
                <?php endif; ?>
            </div>
            
            <h1>Nigeria Immigration Service</h1>
            <p>Identification Management System</p>
            
            <!-- ============================================ -->
            <!-- PROFESSIONAL COUNTDOWN TIMER - FULLY RESPONSIVE -->
            <!-- ============================================ -->
            <div class="countdown-wrapper">
                <?php if ($system_closed): ?>
                    <div id="system-closed-banner" class="system-closed-banner">
                        <i class="fas fa-lock"></i>
                        <span>SYSTEM CLOSED - Deadline: 15/02/2026</span>
                    </div>
                <?php else: ?>
                    <div id="countdown-timer" class="countdown-timer">
                        <span class="countdown-icon"><i class="fas fa-clock"></i><span class="unicode-icon">⏰</span></span>
                        <span class="deadline-label">Verification Closes</span>
                        <div class="countdown-numbers">
                            <span class="time-segment" id="countdown-days">00</span><span class="time-separator">d</span>
                            <span class="time-segment" id="countdown-hours">00</span><span class="time-separator">h</span>
                            <span class="time-segment" id="countdown-minutes">00</span><span class="time-separator">m</span>
                            <span class="time-segment" id="countdown-seconds">00</span><span class="time-separator">s</span>
                        </div>
                        <span class="deadline-label">15/02/2026</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Important Notice at the Top - Hidden when printing -->
        <div class="important-notice">
            <p>
                <strong>NOTE:</strong> If all information displayed below is confirmed accurate, 
                please click the "CONFIRMED" button to confirm your information in the system.
                If you notice any discrepancies or inaccuracies in the information, 
                please report them using the "UPDATE INFO" button below. For issues relating to
                No record found; contact:
            </p>
            <h4>08096412228 or 09057366263</h4>
        </div>

        <div class="search-card">
            <form method="POST" action="" class="search-form" onsubmit="return validateSearchInput() && handleFormSubmit()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="serviceNo">Service Number</label>
                    <input type="text" id="serviceNo" name="serviceNo" required 
                           placeholder="Enter 5-digit Service Number"
                           value="<?php echo htmlspecialchars($repopulateServiceNo); ?>"
                           pattern="^\d{5}$"
                           maxlength="5"
                           title="Enter exactly 5 digits (numbers 0-9 only)"
                           <?php echo $system_closed ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="surname">Surname</label>
                    <input type="text" id="surname" name="surname" required 
                           placeholder="Enter Surname"
                           value="<?php echo htmlspecialchars($repopulateSurname); ?>"
                           title="Enter your surname"
                           <?php echo $system_closed ? 'disabled' : ''; ?>>
                </div>
                
                <!-- CAPTCHA Section -->
                <div class="captcha-container">
                    <div class="captcha-display">
                        <span class="captcha-text"><?php echo htmlspecialchars($_SESSION['captcha'] ?? 'Loading...'); ?></span>
                        <div class="captcha-controls">
                            <button type="button" class="captcha-btn" onclick="refreshCaptcha()" title="Refresh CAPTCHA" <?php echo $system_closed ? 'disabled' : ''; ?>>
                                <i class="fas fa-redo"></i>
                                <span class="unicode-icon" aria-hidden="true">↻</span>
                            </button>
                            <button type="button" class="captcha-btn audio" onclick="speakCaptcha()" title="Listen to CAPTCHA" <?php echo $system_closed ? 'disabled' : ''; ?>>
                                <i class="fas fa-volume-up"></i>
                                <span class="unicode-icon" aria-hidden="true">🔊</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="captcha-input-container">
                        <span class="captcha-label">Answer:</span>
                        <input type="text" id="captchaInput" name="captcha" required 
                               placeholder="?"
                               class="captcha-input"
                               maxlength="3"
                               pattern="\d+"
                               title="Enter the product"
                               <?php echo $system_closed ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="captcha-hint">Solve the multiplication problem</div>
                </div>
                
                <div class="submit-container">
                    <button type="submit" class="search-btn" <?php echo $system_closed ? 'disabled' : ''; ?>>
                        <i class="fas fa-search"></i>
                        <span class="unicode-icon" aria-hidden="true">🔍</span>
                        Search Employee
                    </button>
                </div>
            </form>
            
            <?php if ($system_closed): ?>
                <div class="message error" style="margin-top: 20px; background: #ffebee; color: #c62828; border-color: #ef5350;">
                    <strong>⚠️ SYSTEM CLOSED</strong> - The verification deadline of February 15, 2026 has passed. 
                    This portal is no longer accepting confirmations.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error) && !$system_closed): ?>
                <div class="message error" id="errorMessage"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success) && !$system_closed): ?>
                <div class="message success" id="successMessage"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($confirmationMessage) && !$system_closed): ?>
                <div class="message confirmation" id="confirmationMessage"><?php echo htmlspecialchars($confirmationMessage); ?></div>
            <?php endif; ?>
        </div>
        
        <?php if ($employeeData && !$system_closed): ?>
            <div class="id-card" id="idCard">
                <!-- Confirmation Status -->
                <?php if ($employeeData['confirmed_details'] === 'confirmed'): ?>
                    <div class="confirmation-status confirmed" id="confirmationStatus">
                        <strong>✅ DETAILS CONFIRMED</strong>
                        <p>These details were confirmed on: <?php echo htmlspecialchars($employeeData['date_of_entry'] ?? 'Unknown date'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="confirmation-status pending" id="confirmationStatus">
                        <strong>⚠️ AWAITING CONFIRMATION</strong>
                        <p>Please review the information below and click "Confirm Details" if everything is accurate.</p>
                    </div>
                <?php endif; ?>
                
                <div class="card-header">
                    <div class="nis-logo">
                        <h2>NIGERIA IMMIGRATION SERVICE</h2>
                        <p>IDENTIFICATION MANAGEMENT SYSTEM</p>
                    </div>
                    <div class="employee-photo">
                        <?php 
                        $empServiceNo = htmlspecialchars($employeeData['serviceNo'] ?? '');
                        if ($empServiceNo): 
                        ?>
                            <img src="?get_photo=1&serviceNo=<?php echo $empServiceNo; ?>&t=<?php echo time(); ?>" 
                                 alt="Employee Photo" 
                                 data-service-no="<?php echo $empServiceNo; ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="photo-placeholder">
                                Photo<br>Not Available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-grid">
                    <!-- Personal Information -->
                    <div class="info-section">
                        <h3 class="section-title">Personal Information</h3>
                        <div class="info-row">
                            <span class="info-label">Service No:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['serviceNo'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($employeeData['surname'] ?? '') . ' ' . 
                                      htmlspecialchars($employeeData['firstName'] ?? '') . ' ' . 
                                      htmlspecialchars($employeeData['middleName'] ?? ''); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Gender:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['gender'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['dob'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Marital Status:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['marital_status'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Maiden Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['maiden_name'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Contact & Location -->
                    <div class="info-section">
                        <h3 class="section-title">Contact & Location</h3>
                        <div class="info-row">
                            <span class="info-label">State of Origin:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['state_of_origin'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">LGA:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['lga'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Town:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['town'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Employment Information -->
                    <div class="info-section">
                        <h3 class="section-title">Employment Information</h3>
                        <div class="info-row">
                            <span class="info-label">Current Rank:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['currentRank'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Present Posting:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['presentPosting'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of First Appointment:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['dofa'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Present Appointment:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['dopa'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Employment Status:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['empStatus'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Next of Kin -->
                    <div class="info-section">
                        <h3 class="section-title">Next of Kin</h3>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['nok'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Relationship:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['nok_relationship'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['nok_phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Educational Information -->
                    <div class="info-section">
                        <h3 class="section-title">Educational Information</h3>
                        <div class="info-row">
                            <span class="info-label">Qualification:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['qualification'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Institution:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['institution'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Certificate Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['certificate_number'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date Obtained:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['date_obtained'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Financial Information -->
                    <div class="info-section">
                        <h3 class="section-title">Financial Information</h3>
                        <div class="info-row">
                            <span class="info-label">IPPIS Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['IPPIS_No'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Bank:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['bank'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Number:</span>
                            <span class="info-value">***<?php echo substr(htmlspecialchars($employeeData['accountNo'] ?? ''), -4); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">PFA:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['PFA'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pension Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['pensionNo'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">NHF Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['NHF_No'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Medical Information -->
                    <div class="info-section">
                        <h3 class="section-title">Medical Information</h3>
                        <div class="info-row">
                            <span class="info-label">Blood Type:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['bloodType'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Genotype:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['genotype'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <!-- Always show Print Slip button -->
                    <button class="print-btn" onclick="printIDCard()">
                        <i class="fas fa-print"></i>
                        <span class="unicode-icon" aria-hidden="true">🖨️</span>
                        Print Slip
                    </button>
                    
                    <?php if ($employeeData['confirmed_details'] !== 'confirmed'): ?>
                        <!-- Show Confirm button and Update Info button if NOT confirmed -->
                        <form method="POST" action="" style="display: inline;" onsubmit="return confirmSubmission()">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="serviceNo" value="<?php echo htmlspecialchars($employeeData['serviceNo']); ?>">
                            <input type="hidden" name="surname" value="<?php echo htmlspecialchars($employeeData['surname']); ?>">
                            <input type="hidden" name="confirm_details" value="1">
                            <button type="submit" class="confirm-btn">
                                <i class="fas fa-check-circle"></i>
                                <span class="unicode-icon" aria-hidden="true">✓</span>
                                Confirmed
                            </button>
                        </form>
                        
                        <a href="https://forms.gle/f8Mt6f8rhJPkfAgX8" target="_blank" rel="noopener noreferrer">
                            <button type="button" class="new-search-btn">
                                <i class="fas fa-edit"></i>
                                <span class="unicode-icon" aria-hidden="true">✎</span>
                                Update Info
                            </button>
                        </a>
                    <?php else: ?>
                        <div class="completed-message">
                            ✅ Your details have been confirmed. You can still preview and print your information.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($employeeData && $system_closed): ?>
            <!-- Show read-only view when system is closed -->
            <div class="id-card" id="idCard">
                <div class="confirmation-status confirmed" style="background: #ffebee; border-color: #ef5350; color: #c62828;">
                    <strong>🔒 SYSTEM CLOSED</strong>
                    <p>This portal is no longer accepting confirmations as of February 15, 2026.</p>
                </div>
                
                <div class="card-header">
                    <div class="nis-logo">
                        <h2>NIGERIA IMMIGRATION SERVICE</h2>
                        <p>IDENTIFICATION MANAGEMENT SYSTEM</p>
                    </div>
                    <div class="employee-photo">
                        <?php 
                        $empServiceNo = htmlspecialchars($employeeData['serviceNo'] ?? '');
                        if ($empServiceNo): 
                        ?>
                            <img src="?get_photo=1&serviceNo=<?php echo $empServiceNo; ?>&t=<?php echo time(); ?>" 
                                 alt="Employee Photo" 
                                 data-service-no="<?php echo $empServiceNo; ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="photo-placeholder">
                                Photo<br>Not Available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-grid">
                    <!-- Personal Information (Read-only) -->
                    <div class="info-section">
                        <h3 class="section-title">Personal Information</h3>
                        <div class="info-row">
                            <span class="info-label">Service No:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['serviceNo'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($employeeData['surname'] ?? '') . ' ' . 
                                      htmlspecialchars($employeeData['firstName'] ?? '') . ' ' . 
                                      htmlspecialchars($employeeData['middleName'] ?? ''); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Gender:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['gender'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['dob'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <!-- Other sections similarly read-only - simplified for brevity -->
                    <div class="info-section">
                        <h3 class="section-title">Employment Information</h3>
                        <div class="info-row">
                            <span class="info-label">Current Rank:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['currentRank'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Present Posting:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['presentPosting'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="info-section">
                        <h3 class="section-title">Contact Information</h3>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($employeeData['phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="print-btn" onclick="printIDCard()">
                        <i class="fas fa-print"></i>
                        <span class="unicode-icon" aria-hidden="true">🖨️</span>
                        Print Slip
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// ====================================================================
// CLEANUP AND FINAL OPTIMIZATIONS
// ====================================================================

// Close session for main request to release lock
session_write_close();

// End output buffering and send to browser
ob_end_flush();

// Log performance data (optional)
$executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
if ($executionTime > 1.0) { // Log slow requests
    error_log("Slow request: " . round($executionTime, 3) . " seconds, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Optional: Clean old cache files (run rarely)
if (rand(1, 100) === 1) {
    $cacheDir = sys_get_temp_dir() . '/nis_query_cache/';
    if (file_exists($cacheDir)) {
        $files = glob($cacheDir . '*.cache');
        foreach ($files as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }
    
    // Clean old photo cache
    $photoCacheDir = sys_get_temp_dir() . '/nis_photo_cache/';
    if (file_exists($photoCacheDir)) {
        $photoFiles = glob($photoCacheDir . '*.cache');
        foreach ($photoFiles as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > 7200) { // 2 hours
                unlink($file);
            }
        }
    }
}
?>