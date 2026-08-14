<?php
// ====================================================================
// POST-DEADLINE SYSTEM - VERIFICATION CLOSED
// ====================================================================

// Start session with non-blocking option if available
if (version_compare(PHP_VERSION, '7.2.0', '>=')) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cache_limiter', 'nocache');
    
    ini_set('session.gc_maxlifetime', 900);
    ini_set('session.cookie_lifetime', 0);
    
    if (extension_loaded('redis')) {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', 'tcp://127.0.0.1:6379?timeout=2&read_timeout=2');
    } elseif (extension_loaded('memcached')) {
        ini_set('session.save_handler', 'memcached');
        ini_set('session.save_path', '127.0.0.1:11211');
    }
}

session_start();

function releaseSessionLock() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

// ====================================================================
// PHOTO LOADING OPTIMIZATIONS
// ====================================================================

function validateFilePath($path) {
    $path = str_replace(['../', './', '..\\', '.\\'], '', $path);
    
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

function getEmployeePhoto($serviceNo, $maxRetries = 3) {
    $imageFormats = ['.png', '.jpg', '.jpeg', '.gif', '.webp'];
    $photoPath = null;
    $validatedPath = false;
    
    foreach ($imageFormats as $format) {
        $testPath = "upload/" . $serviceNo . $format;
        $testedPath = validateFilePath($testPath);
        
        if ($testedPath && file_exists($testedPath)) {
            $photoPath = $testedPath;
            $validatedPath = $testedPath;
            break;
        }
    }
    
    if (!$photoPath) {
        return false;
    }
    
    $attempts = 0;
    while ($attempts < $maxRetries) {
        try {
            $handle = @fopen($validatedPath, 'rb');
            if ($handle) {
                if (flock($handle, LOCK_SH | LOCK_NB)) {
                    $imageData = stream_get_contents($handle);
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    
                    if ($imageData !== false && strlen($imageData) > 0) {
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
                    fclose($handle);
                }
            }
            
            $attempts++;
            if ($attempts < $maxRetries) {
                usleep(50000);
            }
            
        } catch (Exception $e) {
            error_log("Photo read attempt $attempts failed for $serviceNo: " . $e->getMessage());
            $attempts++;
            if ($attempts < $maxRetries) {
                usleep(100000);
            }
        }
    }
    
    return getCachedPhoto($serviceNo, $validatedPath);
}

function getCachedPhoto($serviceNo, $originalPath) {
    $cacheDir = sys_get_temp_dir() . '/nis_photo_cache/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($serviceNo . $originalPath) . '.cache';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
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
    
    $retryCount = 0;
    while ($retryCount < 2) {
        try {
            $imageData = @file_get_contents($originalPath);
            if ($imageData !== false && strlen($imageData) > 100) {
                $imageInfo = @getimagesizefromstring($imageData);
                if ($imageInfo === false) {
                    return false;
                }
                
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
            // Silent fail
        }
        
        $retryCount++;
        if ($retryCount < 2) {
            usleep(100000);
        }
    }
    
    return false;
}

function servePhoto($photoData) {
    if (!$photoData || !isset($photoData['path']) || !file_exists($photoData['path'])) {
        return false;
    }
    
    $photoPath = $photoData['path'];
    $mimeType = $photoData['mime'] ?? 'image/png';
    
    $fileSize = filesize($photoPath);
    if ($fileSize < 100 || $fileSize > 5242880) {
        return false;
    }
    
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=3600, immutable');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    header('Pragma: cache');
    header('Content-Length: ' . $fileSize);
    
    readfile($photoPath);
    return true;
}

if (isset($_GET['get_photo']) && isset($_GET['serviceNo'])) {
    releaseSessionLock();
    
    $serviceNo = preg_replace('/[^0-9]/', '', $_GET['serviceNo']);
    if (strlen($serviceNo) === 5) {
        $photoData = getEmployeePhoto($serviceNo);
        if ($photoData && servePhoto($photoData)) {
            exit;
        }
    }
    
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=300');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="120" height="140" xmlns="http://www.w3.org/2000/svg">
        <rect width="100%" height="100%" fill="#f8f9fa"/>
        <rect x="5" y="5" width="110" height="130" stroke="#bdc3c7" stroke-width="2" fill="white"/>
        <text x="60" y="60" font-family="Arial" font-size="14" text-anchor="middle" fill="#7f8c8d">Photo</text>
        <text x="60" y="80" font-family="Arial" font-size="12" text-anchor="middle" fill="#7f8c8d">Not Available</text>
    </svg>';
    exit;
}

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);
ini_set('max_input_time', 30);
ini_set('mysqli.reconnect', 1);
ini_set('default_socket_timeout', 30);

// ====================================================================
// RATE LIMITING
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
                        return false;
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

if (!applyRateLimit(15, 60)) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit exceeded',
        'message' => 'Please try again in a few minutes.'
    ]);
    exit;
}

// ====================================================================
// POST-DEADLINE CONFIGURATION
// ====================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php-errors.log');

header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\' https://cdnjs.cloudflare.com;');

$employeeData = null;
$error = '';
$success = '';
$confirmationMessage = '';
$conn = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ====================================================================
// DATABASE CONNECTION
// ====================================================================

try {
    if (!file_exists("db/db.php") || !is_readable("db/db.php")) {
        throw new Exception("Database configuration file not accessible");
    }
    
    include("db/db.php");
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }
    
    if (!mysqli_ping($conn)) {
        throw new Exception("Database connection is not active");
    }
    
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 30);
    
} catch (Exception $e) {
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

function validateInput($input, $type = 'string', $maxLength = 255) {
    if ($input === null || $input === '') {
        return false;
    }
    
    $input = trim($input);
    
    if (strlen($input) > $maxLength) {
        return false;
    }
    
    switch ($type) {
        case 'alphanumeric':
            if (!preg_match('/^[a-zA-Z0-9\s\-\.\']+$/', $input)) {
                return false;
            }
            break;
            
        case 'name':
            if (empty($input)) {
                return false;
            }
            if (preg_match('/[<>\"\'%;()&]/', $input)) {
                return false;
            }
            break;
            
        case 'serviceNo':
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
            if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $input)) {
                return false;
            }
            break;
            
        case 'date':
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                return false;
            }
            $date = DateTime::createFromFormat('Y-m-d', $input);
            if (!$date || $date->format('Y-m-d') !== $input) {
                return false;
            }
            break;
    }
    
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function escapeData($conn, $data) {
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return mysqli_real_escape_string($conn, $item);
        }, $data);
    }
    return mysqli_real_escape_string($conn, $data);
}

// ====================================================================
// fetchEmployeeData FUNCTION
// ====================================================================

function fetchEmployeeData($conn, $serviceNo, $surname) {
    try {
        $cacheKey = "employee_{$serviceNo}_{$surname}";
        $cachedData = getCachedQuery($cacheKey, 120);
        
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        $sql = "SELECT 
                ep.serviceNo, ep.surname, ep.firstName, ep.middleName, ep.dob, 
                ep.marital_status, ep.maiden_name, ep.gender, ep.state_of_origin, 
                ep.town, ep.lga, ep.bloodType, ep.genotype, ep.email, ep.phone, 
                ep.passport, ep.signature, ep.confirmed_details, ep.date_of_entry,
                
                emp.dofa, emp.dopa, emp.presentPosting, emp.currentRank, emp.empStatus,
                
                f.IPPIS_No, f.bank, f.accountNo, f.PFA, f.pensionNo, f.NHF_No,
                
                n.nok, n.nok_relationship, n.nok_phone,
                
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
// PROCESS SEARCH REQUEST (READ-ONLY MODE)
// ====================================================================

if ($conn && $_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Invalid security token");
        }
        
        if (empty($_POST['serviceNo']) || empty($_POST['surname'])) {
            throw new Exception("Please enter both Service Number and Surname.");
        }
        
        $serviceNo = validateInput($_POST['serviceNo'], 'serviceNo', 5);
        $surname = validateInput($_POST['surname'], 'name', 50);
        
        if (!$serviceNo || !$surname) {
            throw new Exception("Invalid input format detected. Service Number must be 5 digits (0-9 only).");
        }
        
        $_SESSION['search_data'] = [
            'serviceNo' => $serviceNo,
            'surname' => $surname
        ];
        
        $employeeData = fetchEmployeeData($conn, $serviceNo, $surname);
        
        if ($employeeData) {
            $success = "Employee record found successfully!";
            unset($_SESSION['search_data']);
        } else {
            error_log("Failed search attempt - ServiceNo: $serviceNo, Surname: $surname");
            throw new Exception("No employee found with Service No: <strong>" . htmlspecialchars($serviceNo) . "</strong> and Surname: <strong>" . htmlspecialchars($surname) . "</strong>.");
        }
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $error = $e->getMessage();
        
        $repopulateServiceNo = $_SESSION['search_data']['serviceNo'] ?? '';
        $repopulateSurname = $_SESSION['search_data']['surname'] ?? '';
    }
}

$repopulateServiceNo = $_SESSION['search_data']['serviceNo'] ?? '';
$repopulateSurname = $_SESSION['search_data']['surname'] ?? '';

if ($employeeData) {
    $repopulateServiceNo = '';
    $repopulateSurname = '';
    unset($_SESSION['search_data']);
}

if ($employeeData && empty($success) && empty($error)) {
    $success = "Employee record loaded successfully!";
}

// ====================================================================
// OUTPUT BUFFERING AND COMPRESSION
// ====================================================================

if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    
    <meta property="og:title" content="NIS Employee Verification System">
    <meta property="og:description" content="Nigeria Immigration Service - Official employee verification portal">
    <meta property="og:image" content="https://niims.com.ng/images/favicon.ico">
    <meta property="og:url" content="https://niims.com.ng/verification">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Nigeria Immigration Service">
    
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="NIS Employee Verification System">
    <meta name="twitter:description" content="Nigeria Immigration Service - Official employee verification portal">
    <meta name="twitter:image" content="https://niims.com.ng/images/favicon.ico">
    
    <meta name="description" content="Nigeria Immigration Service - Employee Verification and Identification Management System">
    <meta name="keywords" content="NIS, Nigeria Immigration Service, Employee Verification, Identification Management">
    <meta name="author" content="Nigeria Immigration Service">
    
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="images/favicon.ico">
    <title>NIS Employee Verification System</title>
    
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
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(39, 174, 96, 0.7);
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
        /* POST-DEADLINE CLOSED BANNER */
        /* ============================================ */
        .system-closed-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(231, 76, 60, 0.95);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 12px 25px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
            color: white;
            font-weight: 600;
            font-size: 16px;
            margin-top: 10px;
            margin-bottom: 5px;
            width: 100%;
            max-width: 100%;
            flex-wrap: wrap;
            text-align: center;
        }
        
        .system-closed-banner i {
            margin-right: 12px;
            font-size: 18px;
        }
        
        .system-closed-banner strong {
            font-weight: 700;
            margin: 0 5px;
        }
        
        @media (max-width: 600px) {
            .system-closed-banner {
                padding: 10px 15px;
                font-size: 14px;
                text-align: center;
                width: 100%;
                flex-direction: column;
                gap: 5px;
            }
            
            .system-closed-banner i {
                margin-right: 0;
                margin-bottom: 5px;
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
        
        .print-btn {
            background: #27ae60;
            color: white;
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
        
        .print-btn:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        .readonly-badge {
            background: #ff9800;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .readonly-badge i {
            font-size: 16px;
        }
        
        @media print {
            .important-notice,
            .confirmation-status,
            .system-closed-banner,
            .search-card,
            .readonly-badge,
            .action-buttons {
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
            .id-card {
                background: white;
                box-shadow: none;
            }
            button { display: none; }
        }
        
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
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
            
            .print-btn {
                width: 100%;
            }
            
            .nis-logo h2 {
                font-size: 1.5rem;
            }
        }
        
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
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
    
    <script>
        function disableRightClick(e) {
            e.preventDefault();
            showRightClickNotification();
        }
        
        function showRightClickNotification() {
            const existingNotification = document.querySelector('.right-click-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = 'right-click-notification';
            notification.textContent = '';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
        
        function handlePhotoLoad(imgElement) {
            imgElement.style.opacity = '1';
        }
        
        function handlePhotoError(imgElement) {
            console.log('Photo failed to load');
            imgElement.style.display = 'none';
            const placeholder = document.createElement('div');
            placeholder.className = 'photo-placeholder';
            placeholder.innerHTML = 'Photo<br>Not Available';
            imgElement.parentElement.appendChild(placeholder);
        }
        
        function initializePhotos() {
            const employeePhotos = document.querySelectorAll('.employee-photo img');
            
            employeePhotos.forEach(img => {
                img.addEventListener('load', function() {
                    handlePhotoLoad(this);
                });
                
                img.addEventListener('error', function() {
                    handlePhotoError(this);
                });
                
                if (img.complete) {
                    if (img.naturalHeight !== 0) {
                        handlePhotoLoad(img);
                    } else {
                        handlePhotoError(img);
                    }
                }
            });
        }
        
        function printIDCard() {
            const printContent = document.getElementById('idCard').innerHTML;
            const originalContent = document.body.innerHTML;
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = printContent;
            
            const elementsToRemove = tempDiv.querySelectorAll('.important-notice, .confirmation-status, .system-closed-banner, .action-buttons, .readonly-badge');
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
                        .system-closed-banner,
                        .action-buttons,
                        .readonly-badge { 
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
        
        window.addEventListener('load', function() {
            document.addEventListener('contextmenu', disableRightClick);
            document.addEventListener('dragstart', function(e) {
                if (e.target.tagName === 'IMG') {
                    e.preventDefault();
                }
            });
            
            const serviceNoField = document.getElementById('serviceNo');
            if (serviceNoField && !serviceNoField.disabled) {
                serviceNoField.focus();
            }
            
            const idCard = document.getElementById('idCard');
            if (idCard && !<?php echo $employeeData ? 'true' : 'false'; ?>) {
                idCard.style.display = 'none';
            }
            
            initializePhotos();
            scrollToResult();
        });
        
        function scrollToResult() {
            <?php if ($employeeData): ?>
                setTimeout(function() {
                    const idCard = document.getElementById('idCard');
                    if (idCard) {
                        idCard.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 300);
            <?php elseif (!empty($error)): ?>
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
        
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        window.addEventListener('beforeunload', function() {
            document.getElementById('serviceNo').value = '';
            document.getElementById('surname').value = '';
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-logo">
                <?php
                // LOGO INCLUDED - Path to favicon/logo with security validation
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
            
            <!-- POST-DEADLINE CLOSED BANNER - NO READ-ONLY MODE MESSAGE -->
            <div class="system-closed-banner">
                <i class="fas fa-lock"></i>
                <span><strong>⚠️ SYSTEM CLOSED</strong> - Verification deadline of February 15, 2026 has passed</span>
            </div>
        </div>
        
        <!-- Important Notice (Retained for reference) -->
        <div class="important-notice">
            <p>
                <strong>NOTE:</strong> This platform is now closed for verification and confirmation.
            </p>
            <h4></h4>
        </div>

        <!--<div class="search-card">-->
        <!--    <form method="POST" action="" class="search-form">-->
        <!--        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">-->
                
        <!--        <div class="form-group">-->
        <!--            <label for="serviceNo">Service Number</label>-->
        <!--            <input type="text" id="serviceNo" name="serviceNo" required -->
        <!--                   placeholder="Enter 5-digit Service Number"-->
        <!--                   value="<?php echo htmlspecialchars($repopulateServiceNo); ?>"-->
        <!--                   pattern="^\d{5}$"-->
        <!--                   maxlength="5"-->
        <!--                   title="Enter exactly 5 digits (numbers 0-9 only)">-->
        <!--        </div>-->
                
        <!--        <div class="form-group">-->
        <!--            <label for="surname">Surname</label>-->
        <!--            <input type="text" id="surname" name="surname" required -->
        <!--                   placeholder="Enter Surname"-->
        <!--                   value="<?php echo htmlspecialchars($repopulateSurname); ?>"-->
        <!--                   title="Enter your surname">-->
        <!--        </div>-->
                
        <!--        <div class="submit-container">-->
        <!--            <button type="submit" class="search-btn">-->
        <!--                <i class="fas fa-search"></i>-->
        <!--                Search Employee-->
        <!--            </button>-->
        <!--        </div>-->
        <!--    </form>-->
            
        <!--    <?php if (!empty($error)): ?>-->
        <!--        <div class="message error" id="errorMessage"><?php echo $error; ?></div>-->
        <!--    <?php endif; ?>-->
            
        <!--    <?php if (!empty($success)): ?>-->
        <!--        <div class="message success" id="successMessage"><?php echo htmlspecialchars($success); ?></div>-->
        <!--    <?php endif; ?>-->
        <!--</div>-->
        
        <?php if ($employeeData): ?>
            <div class="id-card" id="idCard">
                <!-- NO READ-ONLY MODE SECTION - Just status banner -->
                <div class="confirmation-status confirmed">
                    <strong>🔒 VIEW-ONLY MODE</strong>
                    <p>System is closed for confirmations. Information displayed for reference only.</p>
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
                    <button class="print-btn" onclick="printIDCard()">
                        <i class="fas fa-print"></i>
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

session_write_close();
ob_end_flush();

$executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
if ($executionTime > 1.0) {
    error_log("Slow request: " . round($executionTime, 3) . " seconds, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Optional cache cleanup
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
    
    $photoCacheDir = sys_get_temp_dir() . '/nis_photo_cache/';
    if (file_exists($photoCacheDir)) {
        $photoFiles = glob($photoCacheDir . '*.cache');
        foreach ($photoFiles as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > 7200) {
                unlink($file);
            }
        }
    }
}
?>