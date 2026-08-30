<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');
require_once('functions/posting_helper.php');

if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "Report") {
    include 'sidebar-view.php';
}
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function field($value) {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || $value === '0000-00-00') {
        return '<span class="text-muted">N/A</span>';
    }
    return h($value);
}

function findExistingFile($dir, $baseName) {
    foreach (['.png', '.jpg', '.jpeg', '.PNG', '.JPG', '.JPEG'] as $ext) {
        $path = $dir . $baseName . $ext;
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
$record = null;

if ($id !== '' && ctype_digit($id)) {
    $sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.maiden_name, emp.signature,
                emp.middleName, emp.bloodType, emp.gender, emp.state_of_origin, emp2.presentPosting,
                emp2.directorate, emp2.unit, emp2.posting_other,
                emp.dob, nk.nok, nk.nok_phone, nk.nok_relationship, emp.lga, emp.town,
                emp2.dofa, emp2.dopa, emp.marital_status, qf.qualification, qf.date_obtained, emp2.currentRank, fn.ippis_No
                FROM tbl_emppersonal emp
                LEFT JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
                LEFT JOIN tbl_nok nk ON emp.serviceNo = nk.serviceNo
                LEFT JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
                LEFT JOIN tbl_finance fn ON emp.serviceNo = fn.serviceNo
                WHERE emp.serviceNo = ?
                LIMIT 1";
    $stmt = mysqli_prepare($conn, $sqluse);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $record = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if ($record) {
    $profile = $record['serviceNo'];
    $photoPath = findExistingFile('upload/', $profile);
    $signaturePath = findExistingFile('signature/', $profile);
    $fullName = trim($record['firstName'] . ' ' . $record['middleName'] . ' ' . $record['surname']);
    $formattedPosting = formatPresentPosting(
        $record['presentPosting'],
        $record['directorate'] ?? '',
        $record['unit'] ?? '',
        $record['posting_other'] ?? ''
    );
}
?>
<style>
    .profile-header-card {
        background: #f8f9fa;
        border: 1px solid #e6e9ed;
        border-radius: 10px;
        padding: 30px;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 25px;
        flex-wrap: wrap;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .profile-photo-wrap {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid #0e7a2e;
        overflow: hidden;
        flex-shrink: 0;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .profile-photo-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-header-info h2 {
        margin: 0 0 6px 0;
        font-weight: 700;
        color: #2c3e50;
    }
    .profile-header-info .badge-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .profile-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 20px;
        background: #eef2f0;
        border: 1px solid #dde5e0;
        color: #2c3e50;
        font-size: 13px;
        font-weight: 500;
    }
    .profile-actions {
        margin-left: auto;
        display: flex;
        gap: 10px;
        align-self: flex-start;
    }
    .profile-actions .btn {
        border-radius: 6px;
    }
    .info-card {
        background: #fff;
        border: 1px solid #e6e9ed;
        border-radius: 8px;
        padding: 20px 24px;
        margin-bottom: 22px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .info-card .info-card-title {
        font-size: 15px;
        font-weight: 700;
        color: #2c3e50;
        margin: 0 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #ecf0f1;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .info-card .info-card-title i {
        color: #0e7a2e;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f4f6f7;
        font-size: 14px;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-row .info-label {
        color: #7f8c8d;
        font-weight: 600;
    }
    .info-row .info-value {
        color: #2c3e50;
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    .signature-box {
        border: 1px dashed #d0d5d9;
        border-radius: 6px;
        padding: 10px;
        text-align: center;
        background: #fafbfc;
    }
    .signature-box img {
        max-height: 60px;
        max-width: 100%;
    }
    .not-found-card {
        background: #fff;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        color: #721c24;
    }
    .not-found-card i {
        font-size: 42px;
        margin-bottom: 15px;
        display: block;
        color: #e74c3c;
    }
    .print-only, .print-watermark {
        display: none;
    }
    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        body {
            font-size: 11px;
        }
        .profile-actions, .left_col, .top_nav, .page-title, footer, .not-found-card button {
            display: none !important;
        }
        .right_col {
            margin: 0 !important;
            padding: 0 !important;
        }
        /* Bootstrap's floated two-column grid does not paginate predictably
           when combined with break-inside:avoid (it was producing a large
           blank gap on page 1 before jumping the whole next card to page 2)
           — stacking to a single column for print avoids that entirely. */
        .row {
            display: block !important;
        }
        .col-md-6 {
            width: 100% !important;
            float: none !important;
        }
        .info-card, .profile-header-card {
            box-shadow: none !important;
            break-inside: avoid;
            padding: 10px 16px !important;
            margin-bottom: 10px !important;
        }
        .profile-header-card {
            gap: 14px !important;
        }
        .profile-photo-wrap {
            width: 64px !important;
            height: 64px !important;
            border-width: 3px !important;
        }
        .profile-header-info h2 {
            font-size: 17px !important;
        }
        .info-card .info-card-title {
            font-size: 13px !important;
            margin-bottom: 8px !important;
            padding-bottom: 6px !important;
        }
        .info-row {
            padding: 3px 0 !important;
            font-size: 11px !important;
        }
        .print-only {
            display: block;
        }
        .print-watermark {
            display: block;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 100px;
            font-weight: 800;
            letter-spacing: 16px;
            /* !important is required here: Bootstrap's own print stylesheet
               forces `*, *:before, *:after { color: #000 !important; }`,
               which otherwise beats this rule outright regardless of
               selector specificity or source order. */
            color: rgba(164, 22, 26, 0.16) !important;
            white-space: nowrap;
            z-index: 9999;
            pointer-events: none;
        }
        .print-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 4px;
        }
        .print-header img {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
        }
        .print-header-text {
            text-align: center;
        }
        .print-header-text h1 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #000;
            text-transform: uppercase;
        }
        .print-header-text p {
            margin: 2px 0 0;
            font-size: 10px;
            color: #333;
        }
        .print-meta {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #555;
            border-bottom: 1px solid #333;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .print-footer-note {
            margin-top: 10px;
            font-size: 9px;
            color: #555;
            text-align: center;
        }
    }
</style>
<!-- page content -->
<div class="right_col" role="main">
    <?php if ($record): ?>
    <div class="print-watermark">CLASSIFIED</div>
    <div class="print-only">
        <div class="print-header">
            <img src="images/logo.png" alt="Nigeria Immigration Service Logo">
            <div class="print-header-text">
                <h1>Nigeria Immigration Service</h1>
                <p>Identification Management System &mdash; Personnel Record</p>
            </div>
        </div>
        <div class="print-meta">
            <span>Service No: <?php echo h($profile); ?></span>
            <span>Printed: <?php echo date('d M Y, H:i'); ?></span>
            <span>By: <?php echo h($_SESSION['username'] ?? ''); ?></span>
        </div>
    </div>
    <?php endif; ?>
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Personnel Record</h3>
            </div>
        </div>
        <div class="clearfix"></div>

        <div class="col-md-12 col-sm-12 col-xs-12">
            <?php if (!$record): ?>
                <div class="not-found-card">
                    <i class="fa fa-user-times"></i>
                    <h4>No Personnel Record Found</h4>
                    <p>We couldn't find a record matching Service No "<?php echo h($id); ?>". Please check the service number and try again.</p>
                    <button class="btn btn-default" onclick="history.back()"><i class="fa fa-arrow-left"></i> Go Back</button>
                </div>
            <?php else: ?>

                <div class="profile-header-card">
                    <div class="profile-photo-wrap">
                        <img src="<?php echo $photoPath ? h($photoPath) : "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="100%" height="100%" fill="#95a5a6"/><text x="50%" y="55%" font-family="Arial" font-size="40" fill="#fff" text-anchor="middle">' . strtoupper(substr($record['firstName'] ?? '?', 0, 1)) . '</text></svg>'); ?>"
                             alt="Profile Photo"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;base64,<?php echo base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="100%" height="100%" fill="#95a5a6"/></svg>'); ?>';">
                    </div>
                    <div class="profile-header-info">
                        <h2><?php echo h($fullName ?: 'N/A'); ?></h2>
                        <div class="badge-row">
                            <span class="profile-badge"><i class="fa fa-id-badge"></i> Service No: <?php echo h($profile); ?></span>
                            <?php if (!empty($record['currentRank'])): ?>
                                <span class="profile-badge"><i class="fa fa-star"></i> <?php echo h($record['currentRank']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($record['ippis_No'])): ?>
                                <span class="profile-badge"><i class="fa fa-money"></i> IPPIS: <?php echo h($record['ippis_No']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($formattedPosting)): ?>
                                <span class="profile-badge"><i class="fa fa-building"></i> <?php echo h($formattedPosting); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="btn btn-default" onclick="history.back()"><i class="fa fa-arrow-left"></i> Back</button>
                        <button class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-sm-12">
                        <div class="info-card">
                            <h4 class="info-card-title"><i class="fa fa-user"></i> Personal Information</h4>
                            <div class="info-row"><span class="info-label">Surname</span><span class="info-value"><?php echo field($record['surname']); ?></span></div>
                            <div class="info-row"><span class="info-label">First Name</span><span class="info-value"><?php echo field($record['firstName']); ?></span></div>
                            <div class="info-row"><span class="info-label">Middle Name</span><span class="info-value"><?php echo field($record['middleName']); ?></span></div>
                            <div class="info-row"><span class="info-label">Maiden Name</span><span class="info-value"><?php echo field($record['maiden_name']); ?></span></div>
                            <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-value"><?php echo field($record['dob']); ?></span></div>
                            <div class="info-row"><span class="info-label">Gender</span><span class="info-value"><?php echo field($record['gender']); ?></span></div>
                            <div class="info-row"><span class="info-label">Marital Status</span><span class="info-value"><?php echo field($record['marital_status']); ?></span></div>
                            <div class="info-row"><span class="info-label">Blood Group</span><span class="info-value"><?php echo field($record['bloodType']); ?></span></div>
                            <div class="info-row"><span class="info-label">State of Origin</span><span class="info-value"><?php echo field($record['state_of_origin']); ?></span></div>
                            <div class="info-row"><span class="info-label">LGA</span><span class="info-value"><?php echo field($record['lga']); ?></span></div>
                            <div class="info-row"><span class="info-label">Town</span><span class="info-value"><?php echo field($record['town']); ?></span></div>
                        </div>

                        <div class="info-card">
                            <h4 class="info-card-title"><i class="fa fa-users"></i> Next of Kin</h4>
                            <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?php echo field($record['nok']); ?></span></div>
                            <div class="info-row"><span class="info-label">Relationship</span><span class="info-value"><?php echo field($record['nok_relationship']); ?></span></div>
                            <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?php echo field($record['nok_phone']); ?></span></div>
                        </div>
                    </div>

                    <div class="col-md-6 col-sm-12">
                        <div class="info-card">
                            <h4 class="info-card-title"><i class="fa fa-briefcase"></i> Employment Information</h4>
                            <div class="info-row"><span class="info-label">Current Rank</span><span class="info-value"><?php echo field($record['currentRank']); ?></span></div>
                            <div class="info-row"><span class="info-label">Current Formation</span><span class="info-value"><?php echo field($formattedPosting); ?></span></div>
                            <div class="info-row"><span class="info-label">Date of First Appointment</span><span class="info-value"><?php echo field($record['dofa']); ?></span></div>
                            <div class="info-row"><span class="info-label">Date of Present Appointment</span><span class="info-value"><?php echo field($record['dopa']); ?></span></div>
                            <div class="info-row"><span class="info-label">IPPIS No</span><span class="info-value"><?php echo field($record['ippis_No']); ?></span></div>
                        </div>

                        <div class="info-card">
                            <h4 class="info-card-title"><i class="fa fa-graduation-cap"></i> Educational Information</h4>
                            <div class="info-row"><span class="info-label">Qualification</span><span class="info-value"><?php echo field($record['qualification']); ?></span></div>
                            <div class="info-row"><span class="info-label">Date Obtained</span><span class="info-value"><?php echo field($record['date_obtained']); ?></span></div>
                        </div>

                        <div class="info-card">
                            <h4 class="info-card-title"><i class="fa fa-pencil-square-o"></i> Signature</h4>
                            <div class="signature-box">
                                <?php if ($signaturePath): ?>
                                    <img src="<?php echo h($signaturePath); ?>" alt="Signature">
                                <?php else: ?>
                                    <span class="text-muted">No signature on file</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="print-only">
                    <p class="print-footer-note">This document contains privileged personnel information belonging to the Nigeria Immigration Service and is intended solely for authorized official use.</p>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- footer content -->
<?php include 'footer-enrol.php'; ?>
