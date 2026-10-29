<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

include("db/db.php");
require "vendors/autoload.php";
include "vendors/phpqrcode/qrlib.php";

// Include print logging function
include_once 'includes/log_print.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

$serial = "0108863";
$Bar = new Picqer\Barcode\BarcodeGeneratorHTML();
$code = $Bar->getBarcode($serial, $Bar::TYPE_CODE_128);

// Get parameters
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$isPopup = isset($_GET['popup']) && $_GET['popup'] == '1';
$autoReturn = isset($_GET['autoreturn']) && $_GET['autoreturn'] == '1';
$bulk = isset($_GET['bulk']) && $_GET['bulk'] == '1';
$view_only = isset($_GET['view_only']) && $_GET['view_only'] == '1'; // NEW: Check if view-only mode

if (empty($id)) {
    die("Invalid Service Number");
}

// ============ CHECK IF CARD WAS PRINTED BEFORE ============
$print_history = [];
$previous_prints = [];

$history_sql = "SELECT printed_by, printed_by_role, 
                DATE_FORMAT(print_date, '%d %b %Y at %h:%i %p') as formatted_date,
                print_date as raw_date,
                print_method, batch_id
                FROM tbl_printed_cards 
                WHERE serviceNo = ? 
                ORDER BY print_date DESC 
                LIMIT 5";

$history_stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($history_stmt, 's', $id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);

while ($row = mysqli_fetch_assoc($history_result)) {
    $previous_prints[] = $row;
}

$total_prints = count($previous_prints);
$last_print = $total_prints > 0 ? $previous_prints[0] : null;

mysqli_stmt_close($history_stmt);
// ============ END CHECK ============

// Print logging is now handled client-side via AJAX on window.afterprint event

$sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, 
          emp.signature, emp.middleName, emp.bloodType, emp2.currentRank
          FROM tbl_emppersonal emp 
          INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo 
          WHERE emp.serviceNo='$id'";
$retrieve = mysqli_query($conn, $sqluse);
$numb = mysqli_num_rows($retrieve); 

if ($numb == 0) {
    die("Personnel record not found");
}

while ($foundk = mysqli_fetch_array($retrieve)) {
    $profile = $foundk['serviceNo'];
    $surname = $foundk['surname'];
    $firstname = $foundk['firstName'];
    $middlename = $foundk['middleName'];
    $sign = $foundk['signature'];
    $rank = $foundk['currentRank'];
    $bloodType = $foundk['bloodType'];
}

require_once('functions/card_token.php');
$cardHash = generateCardHash($profile);
$staffData = 'https://niims.immigration.gov.ng/v/' . $cardHash;

// ============ QR CODE GENERATION ============
$matrix = QRcode::text($staffData);
$size = count($matrix);

$pixelSize = 4;
$padding = 2;
$width = ($size + 2 * $padding) * $pixelSize;
$height = $width;

$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">';
$svg .= '<rect width="100%" height="100%" fill="#ffffff" />';
$svg .= '<path d="';
for ($y = 0; $y < $size; $y++) {
    $row = $matrix[$y];
    for ($x = 0; $x < $size; $x++) {
        if ($row[$x] === '1') {
            $posX = ($x + $padding) * $pixelSize;
            $posY = ($y + $padding) * $pixelSize;
            $svg .= 'M' . $posX . ',' . $posY . 'h' . $pixelSize . 'v' . $pixelSize . 'h-' . $pixelSize . 'z ';
        }
    }
}
$svg .= '" fill="#000000" />';
$svg .= '</svg>';

$qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($svg);

$generator = new BarcodeGeneratorPNG();
$barcode = $generator->getBarcode($profile, $generator::TYPE_CODE_128, 3, 50, [0, 0, 0]);

// ============ PHOTO FETCHING ============
$photoFound = false;
$photoSrc = '';
$photoAlt = htmlspecialchars($firstname . ' ' . $surname);
$uploadDir = 'upload/';
$possibleExtensions = array('.png', '.jpg', '.jpeg', '.PNG', '.JPG', '.JPEG');

foreach ($possibleExtensions as $ext) {
    $photoPath = $uploadDir . $profile . $ext;
    if (file_exists($photoPath)) {
        $photoFound = true;
        $photoSrc = $photoPath;
        break;
    }
}

if (!$photoFound) {
    $photoSrc = $uploadDir . 'default.png';
    if (!file_exists($photoSrc)) {
        $photoSrc = 'data:image/svg+xml;base64,' . base64_encode('<svg width="143" height="143" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f0f0f0"/><text x="50%" y="50%" font-family="Arial" font-size="14" fill="#999" text-anchor="middle" dy=".3em">No Photo</text></svg>');
    }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <title>ID Card - <?php echo htmlspecialchars($firstname . ' ' . $surname); ?></title>
    <link rel="stylesheet" href="dist/css/style.css/">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Load your custom font -->
    <style>
    /* CORRECTED FONT FACE DECLARATION - ADD .ttf EXTENSION */
    @font-face {
        font-family: 'ImperatorSmallCaps';
        src: url('fonts/ImperatorSmallCaps_Bold.ttf') format('truetype');
        font-weight: bold;
        font-style: normal;
    }
    
    /* ONLY for Name and Rank - NO fallback fonts */
    .name-value-fixed,
    .rank-value-fixed,
    .serviceno-value-fixed {
        font-family: 'ImperatorSmallCaps' !important;
        font-weight: bold !important;
        text-transform: uppercase !important;
    }
    
    @media print {
        h4 {page-break-before: always;}
        
        /* Hide non-printable elements when printing */
        .no-print {
            display: none !important;
        }
        
        /* CRITICAL FIX: Hide modal and overlay when printing */
        .print-history-modal,
        .print-history-modal *,
        #printHistoryModal,
        #printHistoryModal *,
        .modal-backdrop,
        .modal-overlay {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        
        /* Ensure background images print */
        .id-card-front, .id-card-back {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        /* FORCE font in print - CRITICAL FIX */
        .name-value-fixed,
        .rank-value-fixed,
        .serviceno-value-fixed {
            font-family: 'ImperatorSmallCaps' !important;
            font-weight: bold !important;
            text-transform: uppercase !important;
        }
        
        /* Hide any additional popup elements */
        .print-success,
        .print-controls {
            display: none !important;
        }
    }
    
    /* Screen styles */
    @media screen {
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        
        .print-controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-print {
            background: #27ae60;
            color: white;
        }
        
        .btn-print:hover {
            background: #219a52;
        }
        
        .btn-close {
            background: #dc3545;
            color: white;
        }
        
        .btn-close:hover {
            background: #c82333;
        }
        
        .btn-back {
            background: #17a2b8;
            color: white;
        }
        
        .btn-back:hover {
            background: #138496;
        }
        
        .print-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        /* View-only badge */
        .view-only-badge {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #1976d2;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .view-only-badge i {
            font-size: 20px;
            color: #3498db;
        }
        
        /* Print success notification */
        .print-success {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
        }
        
        /* Print History Modal - OUTSIDE card container */
        .print-history-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            align-items: center;
            justify-content: center;
        }
        
        .print-history-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .print-history-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .print-history-header i {
            font-size: 40px;
            color: #f39c12;
        }
        
        .print-history-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
        }
        
        .print-history-header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .print-history-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .stat-item {
            flex: 1;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .print-history-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .print-history-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background 0.2s;
        }
        
        .print-history-item:hover {
            background: #f8f9fa;
        }
        
        .print-history-item:last-child {
            border-bottom: none;
        }
        
        .print-history-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #27ae60;
            font-size: 18px;
        }
        
        .print-history-details {
            flex: 1;
        }
        
        .print-history-user {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .print-history-date {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .print-history-method {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #e3f2fd;
            color: #1976d2;
            display: inline-block;
            margin-left: 10px;
        }
        
        .print-history-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .print-history-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .print-history-btn-primary {
            background: #27ae60;
            color: white;
        }
        
        .print-history-btn-primary:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .print-history-btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .print-history-btn-secondary:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }
        
        .print-history-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .print-history-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .print-history-checkbox label {
            font-size: 13px;
            color: #666;
            cursor: pointer;
        }
        
        /* First time badge */
        .first-print-badge {
            background: #e8f5e9;
            color: #27ae60;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    }
    
    /* FIXED LAYOUT - NO MOVEMENT */
    .id-card-front {
        width: 500px; 
        height: 290px; 
        image-resolution: 300dpi; 
        background-image: url('assets/images/landscape-front.png'); 
        background-size: cover; 
        position: relative;
        overflow: hidden;
        margin: 10px auto;
    }
    
    /* FIXED POSITION ELEMENTS WITH ABSOLUTE POSITIONING */
    .fixed-element {
        position: absolute;
    }
    
    /* PHOTO - FIXED POSITION */
    .photo-fixed {
        top: 58px;
        left: 24px;
        width: 143px;
        height: 143px;
        border: 5px solid #ddd;
        overflow: hidden;
    }
    
    .photo-fixed img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* NAME - CENTERED POSITION WITH CUSTOM FONT */
    .name-fixed {
        top: 85px;
        left: 160px;
        width: 280px;
        text-align: center;
    }

    .name-label-fixed {
        font-size: 12px;
        font-family: 'Segoe UI', Arial, sans-serif;
        margin-bottom: 2px;
        text-align: center;
        width: 100%;
    }

    .name-value-fixed {
        line-height: 1;
        font-size: 20px;
        white-space: nowrap;
        width: 100%;
        text-align: center;
        margin: 0 auto;
        display: block;
    }

    /* RANK - CENTERED POSITION WITH CUSTOM FONT */
    .rank-fixed {
        top: 125px;
        left: 160px;
        width: 280px;
        text-align: center;
    }

    .rank-label-fixed {
        font-size: 12px;
        font-family: 'Segoe UI', Arial, sans-serif;
        margin-bottom: 8px;
        text-align: center;
        width: 100%;
    }

    .rank-value-fixed {
        line-height: 0.8;
        font-size: 14px; 
        white-space: nowrap;
        width: 100%;
        text-align: center;
        margin: 0 auto;
        display: block;
    }
    
    /* SIGNATURE - CENTERED POSITION */
    .signature-fixed {
        top: 160px;
        left: 175px; 
        width: 280px;
        text-align: center;
    }
    
    .signature-label-fixed {
        font-size: 14px;
        font-family: 'Segoe UI', Arial, sans-serif;
        margin-bottom: 5px;
        text-align: center;
        width: 100%;
    }

    .signature-image-fixed {
        width: 270px;
        height: 80px;
        margin: 0 auto;
        overflow: visible;
        position: relative;
        top: -25px; 
        left: -30px; 
    }

    .signature-image-fixed img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        margin: 0 auto;
        display: block;
    }
    
    /* BLOOD GROUP - FIXED POSITION */
    .bloodgroup-fixed {
        bottom: 35px;
        left: 340px; 
    }
    
    .bloodgroup-inner-fixed {
        display: flex;
        align-items: center;
    }
    
    .bloodgroup-label-fixed {
        font-size: 12px;
        font-family: 'Segoe UI', Arial, sans-serif;
        margin-right: 5px;
    }
    
    .bloodgroup-value-fixed {
        font-size: 17px;
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    /* QR CODE - FIXED POSITION */
    .qrcode-fixed {
        bottom: 35px;
        right: 24px; 
    }
    
    .qrcode-fixed img {
        width: 85px;
        height: 85px;
    }
    
    /* SERVICE NUMBER - FIXED POSITION WITH CUSTOM FONT */
    .serviceno-fixed {
        bottom: 35px;
        left: 246px; 
    }
    
    .serviceno-value-fixed {
        font-size: 20px;
        letter-spacing: 2px;
    }
    
    /* BACK PAGE - FIXED POSITION */
    .id-card-back {
        width: 500px;
        height: 290px;
        background-image: url('assets/images/landscape-back.jpg');
        background-size: cover;
        margin: 10px auto;
    }
    
    .card-body-fixed {
        padding: 0;
    }
    
    .back-text-fixed {
        font-size: 16px;
        text-align: center;
        padding-right: 30px;
        padding-left: 30px;
        margin: 0;
    }
    
    .authority-signature-fixed {
        display: block;
        margin-left: auto;
        margin-right: auto;
        margin-top: -20px;
        width: 180px;
        height: 90px;
        filter: contrast(1.4) brightness(0.85) drop-shadow(0px 2px 3px rgba(0,0,0,0.3));
    }
    
    .barcode-center-fixed {
        margin-top: 15px;
        text-align: center;
        transform: translateX(160px); 
    }
    
    /* ADDITIONAL FIX FOR IPHONE QR SCANNING */
    .qrcode-fixed img {
        width: 85px;
        height: 85px;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
</style>
</head>
<body>

<?php if ($total_prints > 0 && !$bulk && !$view_only): ?>
<!-- Print History Modal - ONLY shown for previously printed cards, NOT in bulk mode, NOT in view-only mode -->
<div class="print-history-modal" id="printHistoryModal">
    <div class="print-history-content">
        <div class="print-history-header">
            <i class="fas fa-history"></i>
            <div>
                <h3>Print History Found</h3>
                <p>This card has been printed before</p>
            </div>
        </div>
        
        <div class="print-history-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $total_prints; ?></div>
                <div class="stat-label">Total Prints</div>
            </div>
            <?php if ($last_print && isset($last_print['raw_date'])): ?>
            <div class="stat-item">
                <div class="stat-value"><?php echo date('d M', strtotime($last_print['raw_date'])); ?></div>
                <div class="stat-label">Last Printed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo htmlspecialchars($last_print['printed_by'] ?? 'Unknown'); ?></div>
                <div class="stat-label">Printed By</div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($previous_prints)): ?>
        <div class="print-history-list">
            <?php foreach ($previous_prints as $index => $print): ?>
            <div class="print-history-item">
                <div class="print-history-icon">
                    <i class="fas fa-<?php echo $index == 0 ? 'clock' : 'print'; ?>"></i>
                </div>
                <div class="print-history-details">
                    <div class="print-history-user">
                        <strong><?php echo htmlspecialchars($print['printed_by'] ?? 'Unknown'); ?></strong> 
                        (<?php echo htmlspecialchars($print['printed_by_role'] ?? 'Unknown'); ?>)
                    </div>
                    <div class="print-history-date">
                        <i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($print['formatted_date'] ?? 'Date not available'); ?>
                        <span class="print-history-method"><?php echo htmlspecialchars(ucfirst($print['print_method'] ?? 'single')); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="print-history-checkbox">
            <input type="checkbox" id="dontShowAgain">
            <label for="dontShowAgain">Don't show this message again for this card</label>
        </div>
        
        <div class="print-history-actions">
            <button class="print-history-btn print-history-btn-secondary" onclick="closeHistoryModal()">
                <i class="fas fa-times"></i> Continue Anyway
            </button>
            <button class="print-history-btn print-history-btn-primary" onclick="proceedToPrint()">
                <i class="fas fa-print"></i> Proceed to Print
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Print Controls (Visible only on screen) -->
<div class="no-print print-controls">
    <?php if ($view_only): ?>
    <!-- VIEW-ONLY MODE: Show view badge instead of print button -->
    <div class="view-only-badge">
        <i class="fas fa-eye"></i>
        <span><strong>View Only Mode</strong> - This is a preview. Printing is disabled.</span>
    </div>
    <button class="btn btn-back" onclick="window.close()">
        <i class="fas fa-times"></i> Close Preview
    </button>
    <?php else: ?>
    <!-- PRINT MODE: Normal print button -->
    <button class="btn btn-print" onclick="handlePrintClick()">
        <i class="fas fa-print"></i> Print Card
    </button>
    
    <?php if ($total_prints > 0 && $last_print && !$bulk): ?>
    <div class="print-message" style="margin-left: 15px;">
        <i class="fas fa-info-circle"></i> 
        Previously printed <?php echo $total_prints; ?> time<?php echo $total_prints > 1 ? 's' : ''; ?>
        <?php if (isset($last_print['raw_date'])): ?>
        (Last: <?php echo date('d M Y', strtotime($last_print['raw_date'])); ?> by <?php echo htmlspecialchars($last_print['printed_by'] ?? 'Unknown'); ?>)
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($bulk): ?>
    <div class="print-message" style="margin-left: 15px; background: #e8f5e9; color: #2e7d32;">
        <i class="fas fa-layer-group"></i> 
        Bulk Print Mode - Processing <?php echo $total_prints > 0 ? 'reprint' : 'new'; ?> card
    </div>
    <?php endif; ?>
    
    <div style="flex: 1"></div>
    <?php endif; ?>
</div>

<!-- FRONT OF ID CARD - FIXED POSITIONS -->
<div class="id-card-front">
    
    <!-- PHOTO -->
    <div class="fixed-element photo-fixed">
        <img src="<?php echo htmlspecialchars($photoSrc); ?>" 
             alt="<?php echo $photoAlt; ?>"
             onerror="this.onerror=null; this.src='upload/default.png';">
    </div>
    
    <!-- NAME - WITH CUSTOM FONT -->
    <div class="fixed-element name-fixed">
        <div class="name-label-fixed"></div>
        <div class="name-value-fixed">
            <b>
                <?php 
                if (isset($firstname)) { 
                    $initials = substr($firstname,0,1) . '' . substr($middlename,0,1);
                    echo htmlspecialchars($initials . ' ' . strtoupper($surname)); 
                } 
                ?>
            </b>
        </div>
    </div>

    <!-- RANK - WITH CUSTOM FONT -->
    <div class="fixed-element rank-fixed">
        <div class="rank-label-fixed"></div>
        <div class="rank-value-fixed">
            <?php if (isset($rank)) { 
                echo htmlspecialchars($rank); 
            } ?>
        </div>
    </div>
    
    <!-- SIGNATURE -->
    <div class="fixed-element signature-fixed">
        <div class="signature-label-fixed"></div>
        <div class="signature-image-fixed">
            <?php
            // Check for signature in multiple formats
            $signatureFound = false;
            $signatureSrc = '';
            $signatureDir = 'signature/';
            
            $signatureExtensions = array('.png', '.jpg', '.jpeg', '.PNG', '.JPG', '.JPEG');
            
            foreach ($signatureExtensions as $ext) {
                $signaturePath = $signatureDir . $profile . $ext;
                if (file_exists($signaturePath)) {
                    $signatureFound = true;
                    $signatureSrc = $signaturePath;
                    break;
                }
            }
            
            // If no signature found, use default
            if (!$signatureFound) {
                $signatureSrc = $signatureDir . 'default.png';
                if (!file_exists($signatureSrc)) {
                    $signatureSrc = 'data:image/svg+xml;base64,' . base64_encode('<svg width="270" height="80" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f0f0f0"/><text x="50%" y="50%" font-family="Arial" font-size="12" fill="#999" text-anchor="middle" dy=".3em">No Signature</text></svg>');
                }
            }
            ?>
            <img src="<?php echo htmlspecialchars($signatureSrc); ?>" 
                 alt="Signature"
                 onerror="this.onerror=null; this.src='signature/default.png';">
        </div>
    </div>
    
    <!-- BLOOD GROUP -->
    <div class="fixed-element bloodgroup-fixed">
        <div class="bloodgroup-inner-fixed">
            <div class="bloodgroup-label-fixed">BG:</div>
            <b class="bloodgroup-value-fixed"><?php echo htmlspecialchars($bloodType); ?></b>
        </div>
    </div>
    
    <!-- QR CODE - FIXED WITH IPHONE COMPATIBLE METHOD -->
    <div class="fixed-element qrcode-fixed">
        <img src="<?php echo htmlspecialchars($qrCodeBase64); ?>" 
             alt="QR Code"
             crossorigin="anonymous"
             onload="this.style.opacity=1; this.style.backgroundColor='white';"
             style="opacity:1; background-color:white;">
    </div>
    
    <!-- SERVICE NUMBER - WITH CUSTOM FONT -->
    <div class="fixed-element serviceno-fixed">
        <b class="serviceno-value-fixed">
            <?php if (isset($profile)) { 
                echo htmlspecialchars($profile); 
            } ?>
        </b>
    </div>

</div>

<h4></h4>

<!-- BACK OF ID CARD - FIXED POSITION -->
<div class="id-card-back">
    <div class="card-body-fixed">
        <p class="back-text-fixed">
            <br><br>
            The bearer of this Identity Card is a Personnel of Nigeria Immigration Service and has the power and privileges ascribed to him or her by law.
            <br>
            This Identity Card is an Official Document and relates only to the identity of the person described.
            If found please contact the nearest Immigration Office or Police Station.
            
            <img src="signature/authority.jpg" class="authority-signature-fixed" alt="Authority Signature">
            
            <div class="barcode-center-fixed">
                <?php if (isset($code)) { echo $code; } ?>
            </div>
        </p>
    </div>
</div>   

<!-- Print success notification (hidden by default) -->
<div id="printSuccess" class="print-success no-print" style="display: none;">
    <i class="fas fa-check-circle"></i>
    <span>Print completed successfully! Closing window...</span>
</div>

<script>
// Disable right click globally in the entire application
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

// SIMPLE FONT FIX FOR PRINTING
(function() {
    const fontLoader = document.createElement('div');
    fontLoader.style.fontFamily = 'ImperatorSmallCaps';
    fontLoader.style.position = 'absolute';
    fontLoader.style.left = '-9999px';
    fontLoader.style.top = '-9999px';
    fontLoader.style.opacity = '0';
    fontLoader.style.fontSize = '1px';
    fontLoader.innerHTML = 'Font Loader';
    document.body.appendChild(fontLoader);
    
    setTimeout(() => {
        if (fontLoader.parentNode) {
            fontLoader.parentNode.removeChild(fontLoader);
        }
    }, 2000);
})();

// Get PHP parameters
const isPopup = <?php echo $isPopup ? 'true' : 'false'; ?>;
const autoReturn = <?php echo $autoReturn ? 'true' : 'false'; ?>;
const totalPrints = <?php echo $total_prints; ?>;
const serviceNo = '<?php echo addslashes($id); ?>';
const isBulk = <?php echo $bulk ? 'true' : 'false'; ?>;
const isViewOnly = <?php echo $view_only ? 'true' : 'false'; ?>; // NEW: Check if view-only

document.addEventListener('DOMContentLoaded', function() {
    // VIEW-ONLY MODE: Don't auto-print, just show the card preview
    if (isViewOnly) {
        console.log('View-only mode - card preview displayed');
        return; // Exit early - no auto-print, no modal
    }
    
    // FOR BULK PRINT MODE - Always auto-print, no modal ever
    if (isBulk) {
        setTimeout(function() {
            window.print();
            
            const printBtn = document.querySelector('.btn-print');
            if (printBtn) {
                printBtn.innerHTML = '<i class="fas fa-check"></i> Printed';
            }
        }, 800);
        return;
    }
    
    // SINGLE PRINT MODE - Only proceed if card has been printed before (totalPrints > 0)
    if (totalPrints > 0) {
        const dontShowKey = 'dontShow_' + serviceNo;
        const dontShow = localStorage.getItem(dontShowKey) === 'true';
        
        if (!dontShow) {
            setTimeout(function() {
                showHistoryModal();
            }, 1000);
        } else {
            setTimeout(function() {
                window.print();
                
                const printBtn = document.querySelector('.btn-print');
                if (printBtn) {
                    printBtn.innerHTML = '<i class="fas fa-check"></i> Print Again';
                }
            }, 1500);
        }
    } else {
        // New card - auto-print
        setTimeout(function() {
            window.print();
            
            const printBtn = document.querySelector('.btn-print');
            if (printBtn) {
                printBtn.innerHTML = '<i class="fas fa-check"></i> Print Again';
            }
        }, 1500);
    }
});

function showHistoryModal() {
    const modal = document.getElementById('printHistoryModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeHistoryModal() {
    const modal = document.getElementById('printHistoryModal');
    const checkbox = document.getElementById('dontShowAgain');
    
    if (checkbox && checkbox.checked) {
        localStorage.setItem('dontShow_' + serviceNo, 'true');
    }
    
    if (modal) {
        modal.style.display = 'none';
    }
}

function proceedToPrint() {
    closeHistoryModal();
    setTimeout(function() {
        window.print();
    }, 500);
}

function handlePrintClick() {
    // View-only mode - don't print
    if (isViewOnly) {
        return;
    }
    
    // Bulk mode - print immediately
    if (isBulk) {
        window.print();
        return;
    }
    
    // Single mode with history check
    if (totalPrints > 0) {
        const dontShowKey = 'dontShow_' + serviceNo;
        const dontShow = localStorage.getItem(dontShowKey) === 'true';
        
        if (!dontShow) {
            showHistoryModal();
            return;
        }
    }
    window.print();
}

function showPrintSuccess() {
    const successMsg = document.getElementById('printSuccess');
    if (successMsg) {
        successMsg.style.display = 'flex';
        
        setTimeout(() => {
            successMsg.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => {
                if (successMsg.parentNode) {
                    successMsg.parentNode.removeChild(successMsg);
                }
            }, 300);
        }, 3000);
    }
}

function closeWindow() {
    if (isPopup && window.opener && !window.opener.closed) {
        try {
            window.opener.postMessage('cardPrinted', '*');
        } catch (e) {
            // Ignore cross-origin errors
        }
        window.close();
    } else if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = 'print-tables';
    }
}

// Only set up afterprint listener if NOT view-only
if (!isViewOnly) {
    window.addEventListener('afterprint', function() {
        showPrintSuccess();
        
        const closeDelay = isBulk ? 800 : 1500;
        setTimeout(function() {
            if (isPopup || autoReturn) {
                closeWindow();
            }
        }, closeDelay);
    });
}

window.addEventListener('message', function(event) {
    if (event.data === 'closeCardWindow') {
        closeWindow();
    }
});

document.addEventListener('keydown', function(e) {
    // View-only mode: disable Ctrl+P
    if (isViewOnly && ((e.ctrlKey || e.metaKey) && e.key === 'p')) {
        e.preventDefault();
        return;
    }
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        handlePrintClick();
    }
    if (e.key === 'Escape') {
        const modal = document.getElementById('printHistoryModal');
        if (modal && modal.style.display === 'flex') {
            closeHistoryModal();
        } else {
            closeWindow();
        }
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'w') {
        e.preventDefault();
        closeWindow();
    }
});

document.addEventListener('visibilitychange', function() {
    if (document.hidden && (isPopup || autoReturn) && !isViewOnly) {
        setTimeout(function() {
            closeWindow();
        }, 2000);
    }
});

// Only show beforeunload warning if NOT view-only
if (!isViewOnly) {
    window.addEventListener('beforeunload', function(e) {
        if (!isPopup && !autoReturn && !isBulk) {
            e.preventDefault();
            e.returnValue = 'Are you sure you want to leave? Your ID card may not have been printed.';
        }
    });
}

// Log printing when user actually interacts with printer dialogue
window.addEventListener('afterprint', function() {
    if (!isViewOnly) {
        const urlParams = new URLSearchParams(window.location.search);
        const batchId = urlParams.get('batch_id') || '';
        
        fetch('ajax-log-print', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'serviceNo=' + encodeURIComponent(serviceNo) +
                  '&print_method=' + encodeURIComponent(isBulk ? 'bulk' : 'single') +
                  '&batch_id=' + encodeURIComponent(batchId)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Print logged successfully via AJAX:', data);
            showPrintSuccess();
        })
        .catch(err => {
            console.error('Failed to log print job via AJAX:', err);
        });
    }
});
</script>

</body>
</html>