<?php 
require_once('db/db.php');
include 'menu-footer-buttons.php';

// ============ SECURITY HEADERS ============
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https:; frame-ancestors 'none';");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ============ RATE LIMITING ============
session_start();
$rate_limit_key = 'verify_attempts_' . $_SERVER['REMOTE_ADDR'];
$rate_limit_time = 60;
$rate_limit_max = 10;

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'first_request' => time()];
}

$rate_data = $_SESSION[$rate_limit_key];

if (time() - $rate_data['first_request'] > $rate_limit_time) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'first_request' => time()];
    $rate_data = $_SESSION[$rate_limit_key];
}

$rate_data['count']++;
$_SESSION[$rate_limit_key] = $rate_data;

if ($rate_data['count'] > $rate_limit_max) {
    http_response_code(429);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Too Many Requests</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-container { max-width: 90%; width: 450px; text-align: center; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
            .error-icon { font-size: 50px; color: #f39c12; margin-bottom: 15px; }
            .error-container h2 { color: #e67e22; margin-bottom: 10px; font-size: 20px; }
            .error-container p { color: #666; margin-bottom: 15px; font-size: 14px; line-height: 1.5; }
            .countdown { font-size: 24px; font-weight: bold; color: #e67e22; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-shield-alt"></i></div>
            <h2>Too Many Requests</h2>
            <p>For security purposes, you have exceeded the maximum number of verification attempts.</p>
            <p>Please wait <span class="countdown" id="countdown">60</span> seconds before trying again.</p>
        </div>
        <script>
            let seconds = <?php echo $rate_limit_time - (time() - $rate_data['first_request']); ?>;
            const countdownEl = document.getElementById('countdown');
            const interval = setInterval(() => {
                seconds--;
                countdownEl.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(interval);
                    location.reload();
                }
            }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ============ INPUT VALIDATION ============
$id = isset($_GET['id']) ? $_GET['id'] : '';

if (!empty($id)) {
    $id = preg_replace('/[^a-zA-Z0-9\/\-_]/', '', $id);
    $id = substr($id, 0, 50);
}

$id = mysqli_real_escape_string($conn, $id);

// Verification details
date_default_timezone_set('Africa/Lagos');
$verification_time = date("d M Y, h:i:s A");
$verification_id = strtoupper(substr(hash('sha256', $id . date('Ymd') . $_SERVER['REMOTE_ADDR']), 0, 10));

// Redirect if no ID
if (empty($id)) {
    header("Location: personnel-list?error=invalid_id");
    exit();
}

$dateofRegistration = date("Y/m/d");

// ============ DATABASE QUERY ============
$sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.maiden_name, emp.signature, 
           emp.middleName, emp.bloodType, emp.gender, emp.state_of_origin, emp2.presentPosting, emp.phone,
           emp.dob, nk.nok, nk.nok_phone, nk.nok_relationship, emp.lga, emp.town,
           emp2.dofa, emp2.dopa, emp.marital_status, qf.qualification, qf.date_obtained, 
           emp2.currentRank, fn.ippis_No
           FROM tbl_emppersonal emp 
           INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
           INNER JOIN tbl_nok nk ON emp2.serviceNo = nk.serviceNo
           INNER JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
           INNER JOIN tbl_finance fn ON emp.serviceNo = fn.serviceNo
           WHERE emp.serviceNo = ?";

$stmt = mysqli_prepare($conn, $sqluse);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $id);
    mysqli_stmt_execute($stmt);
    $retrieve = mysqli_stmt_get_result($stmt);
} else {
    error_log("Database error: " . mysqli_error($conn));
    $retrieve = false;
}

// ============ ERROR HANDLING ============
if (!$retrieve) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Verification Error</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-container { max-width: 90%; width: 450px; text-align: center; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
            .error-icon { font-size: 50px; color: #dc3545; margin-bottom: 15px; }
            .error-container h2 { color: #dc3545; margin-bottom: 10px; font-size: 20px; }
            .error-container p { color: #666; margin-bottom: 15px; font-size: 14px; }
            .btn { display: inline-block; padding: 10px 20px; background: #003366; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
            .btn:hover { background: #004080; }
            .error-code { font-size: 11px; color: #999; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h2>System Error</h2>
            <p>A temporary system error occurred. Please try again later.</p>
            <a href="personnel-list" class="btn"><i class="fas fa-arrow-left"></i> Return</a>
            <div class="error-code">Ref: <?php echo htmlspecialchars($verification_id); ?></div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$numb = mysqli_num_rows($retrieve);

if ($numb == 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Verification Failed</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-container { max-width: 90%; width: 450px; text-align: center; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
            .error-icon { font-size: 50px; color: #dc3545; margin-bottom: 15px; }
            .error-container h2 { color: #dc3545; margin-bottom: 10px; font-size: 20px; }
            .error-container p { color: #666; margin-bottom: 10px; font-size: 14px; }
            .service-no-display { background: #f8d7da; color: #721c24; padding: 8px 15px; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block; margin: 10px 0; border: 1px solid #f5c6cb; word-break: break-all; }
            .btn { display: inline-block; padding: 10px 20px; background: #003366; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 15px; }
            .btn:hover { background: #004080; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-times-circle"></i></div>
            <h2>Verification Failed</h2>
            <p>No valid record found.</p>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">This ID card may be invalid or the personnel record does not exist.</p>
            <a href="personnel-list" class="btn"><i class="fas fa-arrow-left"></i> Return</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Initialize variables
$profile = $surname = $firstname = $middlename = $sign = $rank = $bloodType = $gender = "";
$state = $lga = $dob = $town = $phone = "";

while ($foundk = mysqli_fetch_array($retrieve)) {
    $profile = $foundk['serviceNo'] ?? '';
    $surname = $foundk['surname'] ?? '';
    $firstname = $foundk['firstName'] ?? '';
    $middlename = $foundk['middleName'] ?? '';
    $sign = $foundk['signature'] ?? '';
    $rank = $foundk['currentRank'] ?? '';
    $bloodType = $foundk['bloodType'] ?? '';
    $gender = $foundk['gender'] ?? '';
    $state = $foundk['state_of_origin'] ?? '';
    $lga = $foundk['lga'] ?? '';
    $dob = $foundk['dob'] ?? '';
    $town = $foundk['town'] ?? '';
    $phone = $foundk['phone'] ?? '';
}

// ============ PHOTO PATH ============
function getStaffPhoto($profile, $uploadDir = 'upload/') {
    $profile = basename($profile);
    $profile = preg_replace('/[^a-zA-Z0-9\/\-_]/', '', $profile);
    
    $defaultPhoto = $uploadDir . 'default.png';
    
    if (empty($profile)) {
        return file_exists($defaultPhoto) ? $defaultPhoto : 'https://via.placeholder.com/85x85?text=No+Photo';
    }
    
    $extensions = ['.png', '.jpg', '.jpeg', '.PNG', '.JPG', '.JPEG'];
    
    foreach ($extensions as $ext) {
        $photoPath = $uploadDir . $profile . $ext;
        $realPath = realpath($photoPath);
        $realUploadDir = realpath($uploadDir);
        
        if ($realPath && $realUploadDir && strpos($realPath, $realUploadDir) === 0 && file_exists($realPath)) {
            $imageInfo = @getimagesize($realPath);
            if ($imageInfo !== false) {
                return $photoPath;
            }
        }
    }
    
    return file_exists($defaultPhoto) ? $defaultPhoto : 'https://via.placeholder.com/85x85?text=No+Photo';
}

$staffPhoto = getStaffPhoto($profile);
$fullName = trim($surname . ' ' . $firstname . ' ' . $middlename);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ID Verification - <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
        .required { color: red; }
        .is-invalid { border: 1px solid red !important; }
        .verification-container { max-width: 700px; margin: 10px auto; padding: 10px; position: relative; }
        .verified-header { background: linear-gradient(135deg, #27ae60, #219a52); color: white; padding: 12px 15px; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .verified-badge { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; }
        .verified-badge i { font-size: 22px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .verified-shield { background: white; color: #27ae60; padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .verified-shield i { font-size: 14px; animation: none; }
        .verified-content { background: white; border: 2px solid #27ae60; border-top: none; border-radius: 0 0 10px 10px; padding: 15px; box-shadow: 0 5px 30px rgba(39, 174, 96, 0.15); position: relative; overflow: hidden; }
        .verification-stamp { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); border: 3px solid rgba(39, 174, 96, 0.3); border-radius: 10px; padding: 12px 80px; color: rgba(39, 174, 96, 0.3); font-weight: bold; font-size: 14px; pointer-events: none; z-index: 10; text-align: center; text-transform: uppercase; letter-spacing: 2px; white-space: nowrap; user-select: none; }
        .verification-stamp .stamp-icon { font-size: 35px; display: block; margin-bottom: 4px; }
        .verification-info-bar { background: #f0faf4; border: 1px solid #c8e6c9; border-radius: 8px; padding: 10px 12px; margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 8px; font-size: 10px; color: #2e7d32; }
        .verification-info-bar .info-item { display: flex; align-items: center; gap: 5px; white-space: nowrap; }
        .verification-info-bar i { color: #27ae60; font-size: 12px; }
        .verified-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 60px; color: rgba(39, 174, 96, 0.04); pointer-events: none; z-index: 0; white-space: nowrap; font-weight: bold; user-select: none; }
        .content-wrapper { position: relative; z-index: 1; }
        .photo-section { text-align: center; margin-bottom: 15px; user-select: none; }
        .staff-photo { width: 85px; height: 85px; object-fit: cover; border-radius: 6px; border: 2px solid #e0e0e0; pointer-events: none; }
        .photo-verified-badge { display: inline-block; background: #27ae60; color: white; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; margin-top: 5px; }
        .info-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .info-table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; background: white; min-width: 280px; }
        .info-table td { padding: 8px 10px; border: 1px solid #ddd; font-size: 13px; color: #333; vertical-align: middle; }
        .info-table td.label-cell { font-weight: bold; color: #2c3e50; background: #f8f9fa; width: 35%; font-size: 12px; }
        .info-table td.value-cell { width: 65%; word-break: break-word; }
        .qr-verified-section { text-align: center; margin-top: 15px; padding-top: 15px; border-top: 2px dashed #e0e0e0; }
        .qr-verified-section .qr-badge { display: inline-block; background: linear-gradient(135deg, #27ae60, #219a52); color: white; padding: 6px 15px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .qr-verified-section .qr-badge i { margin-right: 6px; }
        .verification-footer-text { font-size: 10px; color: #999; margin-top: 8px; line-height: 1.4; }
        .verification-footer-text i { color: #27ae60; }
        .verification-stamp, .verified-watermark, .verified-header { user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; }
        
        @media screen and (max-width: 600px) {
            .verification-container { margin: 0; padding: 0; }
            .verified-header { border-radius: 0; padding: 10px 12px; }
            .verified-badge { font-size: 13px; gap: 6px; }
            .verified-badge i { font-size: 18px; }
            .verified-shield { font-size: 10px; padding: 4px 10px; }
            .verified-content { border-radius: 0; padding: 12px 10px; }
            .verification-stamp { font-size: 11px; padding: 8px 80px; border-radius: 8px; }
            .verification-stamp .stamp-icon { font-size: 25px; }
            .verified-watermark { font-size: 40px; }
            .verification-info-bar { flex-direction: column; gap: 5px; padding: 8px 10px; font-size: 10px; }
            .info-table td { padding: 6px 8px; font-size: 11px; }
            .info-table td.label-cell { font-size: 10px; width: 40%; }
            .staff-photo { width: 70px; height: 70px; }
            .photo-verified-badge { font-size: 8px; }
            .qr-verified-section { margin-top: 12px; padding-top: 12px; }
            .qr-verified-section .qr-badge { font-size: 10px; padding: 5px 12px; }
            .verification-footer-text { font-size: 9px; }
        }
        
        @media screen and (max-width: 400px) {
            .verified-header { flex-direction: column; align-items: flex-start; gap: 6px; }
            .verified-badge { font-size: 12px; }
            .verification-stamp { font-size: 10px; padding: 6px 70px; top: 45%; }
            .verification-stamp .stamp-icon { font-size: 50px; }
            .info-table td { padding: 5px 6px; font-size: 10px; }
            .info-table td.label-cell { font-size: 9px; }
            .staff-photo { width: 60px; height: 60px; }
        }
        
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .verification-container { max-width: 100%; margin: 0; padding: 0; }
            .verified-header { background: #27ae60 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-radius: 0; }
            .verification-stamp { opacity: 0.4 !important; }
            .verified-content { border: 2px solid #27ae60 !important; border-radius: 0; box-shadow: none; padding: 10px; }
            .info-table { border: 1px solid #000; }
            .info-table td { border: 1px solid #000; padding: 5px 8px; font-size: 11px; }
            .info-table td.label-cell { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .verification-info-bar { background: #f0faf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body oncontextmenu="return false;">
    <div class="right_col" role="main" style="padding: 0; margin: 0;">
        <div class="verification-container">
            <div class="verified-header no-print">
                <div class="verified-badge">
                    <i class="fas fa-shield-check"></i>
                    <span>ID Card Verified | Authentic</span>
                </div>
                <div class="verified-shield">
                    <i class="fas fa-qrcode"></i>
                    QR Verification
                </div>
            </div>
            
            <div class="verified-content">
                <div class="verification-stamp">
                    <span class="stamp-icon"><i class="fas fa-check-circle"></i></span>
                    Verified
                </div>
                
                <div class="verified-watermark">VERIFIED</div>
                
                <div class="content-wrapper">
                    <div class="verification-info-bar">
                        <div class="info-item">
                            <i class="fas fa-calendar-check"></i>
                            <strong>Verified:</strong> <?php echo htmlspecialchars($verification_time, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-fingerprint"></i>
                            <strong>Ref:</strong> <?php echo htmlspecialchars($verification_id, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-server"></i>
                            <strong>Status:</strong> 
                            <span style="color: #27ae60; font-weight: bold;">
                                <i class="fas fa-circle" style="font-size: 7px;"></i> Active
                            </span>
                        </div>
                    </div>
                    
                    <div class="photo-section">
                        <img src="<?php echo htmlspecialchars($staffPhoto, ENT_QUOTES, 'UTF-8'); ?>" 
                             class="staff-photo" 
                             alt="Staff Photo"
                             onerror="this.src='upload/default.png';"
                             draggable="false">
                        <br>
                        <span class="photo-verified-badge">
                            <i class="fas fa-check"></i> VERIFIED
                        </span>
                    </div>
                    
                    <div class="info-table-wrapper">
                        <table class="info-table">
                            <tr>
                                <td class="label-cell">Service No:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($profile, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <!--<tr>-->
                            <!--    <td class="label-cell">Full Name:</td>-->
                            <!--    <td class="value-cell"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></td>-->
                            <!--</tr>-->
                            <tr>
                                <td class="label-cell">Surname:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($surname, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <td class="label-cell">First Name:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <td class="label-cell">Middle Name:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($middlename, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <td class="label-cell">Rank:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($rank, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <td class="label-cell">Gender:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($gender, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <td class="label-cell">Blood Group:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($bloodType, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <td class="label-cell">Phone No:</td>
                                <td class="value-cell"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="qr-verified-section">
                        <div class="qr-badge">
                            <i class="fas fa-shield-check"></i>
                            Verified via QR Code Scan
                        </div>
                        <p class="verification-footer-text">
                            <i class="fas fa-lock"></i>
                            This verification is authentic and was generated by the NIIMS Portal
                        </p>
                        <p class="verification-footer-text" style="color: #bbb; font-size: 9px;">
                            Verification ID: <?php echo htmlspecialchars($verification_id, ENT_QUOTES, 'UTF-8'); ?> | 
                            Timestamp: <?php echo htmlspecialchars($verification_time, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ANTI-TAMPERING SCRIPT ============ -->
    <script>
        // Disable right-click context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable common keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Disable F12 (Developer Tools)
            if (e.keyCode === 123) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+Shift+I (Dev Tools)
            if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+Shift+J (Console)
            if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+U (View Source)
            if (e.ctrlKey && e.keyCode === 85) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+S (Save Page)
            if (e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+P (Print) - Optional, remove if you want to allow printing
            // if (e.ctrlKey && e.keyCode === 80) {
            //     e.preventDefault();
            //     return false;
            // }
            
            // Disable Ctrl+Shift+C (Inspect Element)
            if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+C (Copy) - Optional
            if (e.ctrlKey && e.keyCode === 67) {
                e.preventDefault();
                return false;
            }
        });

        // Disable drag and drop
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable text selection
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });

        // Developer Tools Detection (Advanced)
        (function() {
            const threshold = 160;
            let devtoolsOpen = false;
            
            const checkDevTools = setInterval(function() {
                const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                const heightThreshold = window.outerHeight - window.innerHeight > threshold;
                
                if (widthThreshold || heightThreshold) {
                    if (!devtoolsOpen) {
                        devtoolsOpen = true;
                        // Optional: Redirect or show warning
                        // window.location.href = 'about:blank';
                    }
                } else {
                    devtoolsOpen = false;
                }
            }, 1000);
        })();

        // Console protection
        (function() {
            const consoleMethods = ['log', 'warn', 'error', 'info', 'debug', 'clear', 'table', 'dir'];
            consoleMethods.forEach(function(method) {
                console[method] = function() {};
            });
        })();

        // Prevent iframe embedding (additional clickjacking protection)
        if (window.top !== window.self) {
            window.top.location = window.self.location;
        }
    </script>

    <?php 
    // Clean up
    if (isset($stmt)) mysqli_stmt_close($stmt);
    mysqli_close($conn);
    ?>
</body>
</html>