<?php
session_start();
include("db/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index");
    exit;
}

$id = $_GET['id'];

$dateofRegistration = date("Y/m/d");

$sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.maiden_name,emp.signature, 
emp.middleName, emp.bloodType,emp.gender, emp.state_of_origin, emp2.presentPosting,
emp.dob, nk.nok, nk.nok_phone, nk.nok_relationship, emp.lga, emp.town,
emp2.dofa, emp2.dopa, emp.marital_status, qf.qualification, qf.date_obtained,emp2.currentRank, fn.ippis_No,
emp.email, emp.phone, emp.genotype, fn.bank, fn.accountNo, fn.pfa, fn.pensionNo, fn.nhf_no
FROM tbl_emppersonal emp 
INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
INNER JOIN tbl_nok nk ON emp2.serviceNo = nk.serviceNo
INNER JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
INNER JOIN tbl_finance fn ON emp.serviceNo = fn.serviceNo
WHERE emp.serviceNo=$id";
$retrieve = mysqli_query($conn, $sqluse);
$numb = mysqli_num_rows($retrieve); 

if ($numb > 0) {
    $foundk = mysqli_fetch_array($retrieve);
    $profile = $foundk['serviceNo'];
    $presentposting = $foundk['presentPosting'];
    $surname = $foundk['surname'];
    $firstname = $foundk['firstName'];
    $middlename = $foundk['middleName'];
    $sign = $foundk['signature'];
    $rank = $foundk['currentRank'];
    $bloodType = $foundk['bloodType'];
    $gender = $foundk['gender'];
    $state = $foundk['state_of_origin'];
    $nok = $foundk['nok'];
    $nok_relationship = $foundk['nok_relationship'];
    $nok_phone = $foundk['nok_phone'];
    $lga = $foundk['lga'];
    $dob = $foundk['dob'];
    $maidenName = $foundk['maiden_name'];
    $dofa = $foundk['dofa'];
    $dopa = $foundk['dopa'];
    $marital_status = $foundk['marital_status'];
    $qualification = $foundk['qualification'];
    $dateObtained = $foundk['date_obtained'];
    $town = $foundk['town'];
    $ippis = $foundk['ippis_No'];
    $email = $foundk['email'];
    $phone = $foundk['phone'];
    $genotype = $foundk['genotype'];
    $bank = $foundk['bank'];
    $accountNo = $foundk['accountNo'];
    $pfa = $foundk['pfa'];
    $pensionNo = $foundk['pensionNo'];
    $nhf_no = $foundk['nhf_no'];
    
    // Format dates
    $dob_formatted = date("F j, Y", strtotime($dob));
    $dofa_formatted = date("F j, Y", strtotime($dofa));
    $dopa_formatted = date("F j, Y", strtotime($dopa));
    $dateObtained_formatted = date("F j, Y", strtotime($dateObtained));
    $dateofRegistration_formatted = date("F j, Y", strtotime($dateofRegistration));
} else {
    die("Officer record not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Profile - <?php echo htmlspecialchars($surname . ' ' . $firstname); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
        }
        
        .header {
            background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
            color: white;
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 5px solid #007bff;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            height: 70px;
            width: auto;
        }
        
        .title h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .title p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .photo-container {
            position: relative;
        }
        
        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            object-fit: cover;
        }
        
        .profile-photo-frame {
            position: relative;
        }
        
        .profile-photo-frame::after {
            content: "";
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .content {
            padding: 30px 40px;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border-left: 4px solid #007bff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
        }
        
        .info-card.personal {
            border-left-color: #28a745;
        }
        
        .info-card.employment {
            border-left-color: #007bff;
        }
        
        .info-card.nok {
            border-left-color: #17a2b8;
        }
        
        .info-card.education {
            border-left-color: #9c27b0;
        }
        
        .info-card.finance {
            border-left-color: #ff9800;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0,0,0,0.1);
        }
        
        .card-header i {
            font-size: 22px;
            margin-right: 12px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,123,255,0.1);
            border-radius: 50%;
            color: #007bff;
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            min-width: 180px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 5px;
        }
        
        .badge.rank {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .badge.blood {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .badge.genotype {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #e1bee7;
        }
        
        .badge.status {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 25px 40px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .signature-section {
            flex: 1;
        }
        
        .signature-line {
            width: 200px;
            height: 1px;
            background: #333;
            margin: 10px 0 5px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
            color: white;
        }
        
        .btn-print:hover {
            background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        .service-no {
            position: absolute;
            top: 20px;
            right: 40px;
            background: rgba(255,255,255,0.1);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .section-divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: #2c3e50;
            font-weight: 600;
            font-size: 16px;
        }
        
        .section-divider::before,
        .section-divider::after {
            content: "";
            flex: 1;
            height: 2px;
            background: #dee2e6;
            margin: 0 15px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .marital-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 15px;
            border-radius: 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            font-size: 13px;
            font-weight: 500;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .btn-back, .btn-print {
                display: none;
            }
            
            .header {
                background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
                color: white;
                -webkit-print-color-adjust: exact;
            }
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-value {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <p>Loading profile...</p>
    </div>
    
    <div class="container">
        <div class="header">
            <div class="header-left">
                <img src="images/favicon.ico" class="logo" alt="NIS Logo">
                <div class="title">
                    <h1>NIGERIA IMMIGRATION SERVICE</h1>
                    <p>IDENTIFICATION MANAGEMENT SYSTEM - PERSONNEL PROFILE</p>
                </div>
            </div>
            
            <div class="photo-container">
                <div class="profile-photo-frame">
                    <?php
                    // Check for profile photo - ADDED MULTIPLE FORMATS
                    $photoPaths = [
                        "upload/" . $profile . ".png",
                        "upload/" . $profile . ".jpg",
                        "upload/" . $profile . ".jpeg",
                        "upload/" . $profile . ".PNG",
                        "upload/" . $profile . ".JPG",
                        "upload/" . $profile . ".JPEG",
                        "upload/" . $profile . ".gif",
                        "upload/" . $profile . ".GIF",
                        "upload/" . $profile . ".webp",
                        "upload/" . $profile . ".WEBP"
                    ];
                    
                    $photoSrc = "upload/default.png";
                    foreach ($photoPaths as $photoPath) {
                        if (file_exists($photoPath)) {
                            $photoSrc = $photoPath;
                            break;
                        }
                    }
                    ?>
                    <img src="<?php echo $photoSrc; ?>" class="profile-photo" 
                         alt="<?php echo htmlspecialchars($surname . ' ' . $firstname); ?>"
                         onerror="this.onerror=null; this.src='upload/default.png';">
                </div>
            </div>
            
            <div class="service-no">
                Service No: <strong><?php echo htmlspecialchars($profile); ?></strong>
            </div>
        </div>
        
        <div class="content">
            <div class="profile-grid">
                <!-- Personal Information -->
                <div class="info-card personal">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h3>Personal Information</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value">
                            <strong><?php echo htmlspecialchars($surname . ', ' . $firstname . ' ' . $middlename); ?></strong>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date of Birth:</div>
                        <div class="info-value"><?php echo htmlspecialchars($dob_formatted); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Gender:</div>
                        <div class="info-value"><?php echo htmlspecialchars($gender); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Marital Status:</div>
                        <div class="info-value">
                            <span class="marital-status">
                                <?php 
                                $statusIcons = [
                                    'Single' => 'fas fa-user',
                                    'Married' => 'fas fa-ring',
                                    'Divorced' => 'fas fa-heart-broken',
                                    'Widow' => 'fas fa-user-minus',
                                    'Widower' => 'fas fa-user-minus',
                                    'Seperated' => 'fas fa-user-slash'
                                ];
                                $icon = isset($statusIcons[$marital_status]) ? $statusIcons[$marital_status] : 'fas fa-user';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                                <?php echo htmlspecialchars($marital_status); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Maiden Name:</div>
                        <div class="info-value"><?php echo !empty($maidenName) ? htmlspecialchars($maidenName) : '<span class="empty-value">Not specified</span>'; ?></div>
                    </div>
                </div>
                
                <!-- Origin & Contact -->
                <div class="info-card personal">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Origin & Contact</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">State of Origin:</div>
                        <div class="info-value"><?php echo htmlspecialchars($state); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">LGA:</div>
                        <div class="info-value"><?php echo htmlspecialchars($lga); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Town:</div>
                        <div class="info-value"><?php echo htmlspecialchars($town); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div class="info-value"><?php echo !empty($phone) ? htmlspecialchars($phone) : '<span class="empty-value">Not provided</span>'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo !empty($email) ? htmlspecialchars($email) : '<span class="empty-value">Not provided</span>'; ?></div>
                    </div>
                </div>
                
                <!-- Medical Information -->
                <div class="info-card personal">
                    <div class="card-header">
                        <i class="fas fa-heartbeat"></i>
                        <h3>Medical Information</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Blood Group:</div>
                        <div class="info-value">
                            <span class="badge blood"><?php echo htmlspecialchars($bloodType); ?></span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Genotype:</div>
                        <div class="info-value">
                            <span class="badge genotype"><?php echo htmlspecialchars($genotype); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Employment Information -->
                <div class="info-card employment">
                    <div class="card-header">
                        <i class="fas fa-briefcase"></i>
                        <h3>Employment Information</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Current Rank:</div>
                        <div class="info-value">
                            <span class="badge rank"><?php echo htmlspecialchars($rank); ?></span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Present Posting:</div>
                        <div class="info-value"><?php echo htmlspecialchars($presentposting); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date of First Appointment:</div>
                        <div class="info-value"><?php echo htmlspecialchars($dofa_formatted); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date of Present Appointment:</div>
                        <div class="info-value"><?php echo htmlspecialchars($dopa_formatted); ?></div>
                    </div>
                </div>
                
                <!-- Next of Kin -->
                <div class="info-card nok">
                    <div class="card-header">
                        <i class="fas fa-users"></i>
                        <h3>Next of Kin Information</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($nok); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Relationship:</div>
                        <div class="info-value"><?php echo htmlspecialchars($nok_relationship); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($nok_phone); ?></div>
                    </div>
                </div>
                
                <!-- Education -->
                <div class="info-card education">
                    <div class="card-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Educational Qualification</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Qualification:</div>
                        <div class="info-value"><?php echo htmlspecialchars($qualification); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date Obtained:</div>
                        <div class="info-value"><?php echo htmlspecialchars($dateObtained_formatted); ?></div>
                    </div>
                </div>
                
                <!-- Finance Information -->
                <div class="info-card finance">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3>Finance Information</h3>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IPPIS Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($ippis); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Bank:</div>
                        <div class="info-value"><?php echo !empty($bank) ? htmlspecialchars($bank) : '<span class="empty-value">Not specified</span>'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Account Number:</div>
                        <div class="info-value"><?php echo !empty($accountNo) ? htmlspecialchars($accountNo) : '<span class="empty-value">Not specified</span>'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Pension Fund Admin:</div>
                        <div class="info-value"><?php echo !empty($pfa) ? htmlspecialchars($pfa) : '<span class="empty-value">Not specified</span>'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Pension Number:</div>
                        <div class="info-value"><?php echo !empty($pensionNo) ? htmlspecialchars($pensionNo) : '<span class="empty-value">Not specified</span>'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">NHF Number:</div>
                        <div class="info-value"><?php echo !empty($nhf_no) ? htmlspecialchars($nhf_no) : '<span class="empty-value">Not specified</span>'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div class="signature-section">
                <p><strong>Authorized Signature:</strong></p>
                <div class="signature-line"></div>
                <p>Deputy Comptroller General, (HRM)</p>
                <p>Nigeria Immigration Service</p>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back to List
                </button>
                <button class="btn btn-print" onclick="printProfile()">
                    <i class="fas fa-print"></i> Print Profile
                </button>
            </div>
        </div>
    </div>

    <script>
        // Show loading on page load
        document.getElementById('loading').style.display = 'flex';
        
        window.onload = function() {
            // Hide loading after page loads
            setTimeout(function() {
                document.getElementById('loading').style.display = 'none';
            }, 500);
        };
        
        function printProfile() {
            // Print immediately without showing loading overlay
            window.print();
        }
        
        function goBack() {
            // Show loading for going back
            document.getElementById('loading').style.display = 'flex';
            
            // Go back to enrolment-update.php with search parameter
            window.location.href = 'enrolment-update?search=<?php echo urlencode($profile); ?>';
        }
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printProfile();
            }
            // Escape to go back
            if (e.key === 'Escape') {
                goBack();
            }
        });
    </script>
</body>
</html>