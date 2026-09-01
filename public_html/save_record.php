<?php
// Start output buffering to prevent any output before headers
ob_start();

// Enable error reporting but don't display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session
session_start();

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized.', 'type' => 'error']);
    exit;
}

// Include database connection
require_once('db/db.php');

// Set JSON header
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'type' => 'error'
];

try {
    // Check if form was submitted
    if (!isset($_POST['regEmployee']) && empty($_POST['serviceNo'])) {
        $response['message'] = 'Invalid request. No data received.';
        echo json_encode($response);
        exit;
    }
    
    // Sanitize all inputs
    $NISNo = mysqli_real_escape_string($conn, $_POST['serviceNo'] ?? '');
    $currentRank = mysqli_real_escape_string($conn, $_POST['currentRank'] ?? '');
    $firstName = mysqli_real_escape_string($conn, $_POST['fname'] ?? '');
    $middleName = mysqli_real_escape_string($conn, $_POST['mname'] ?? '');
    $surname = mysqli_real_escape_string($conn, $_POST['lname'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $bloodType = mysqli_real_escape_string($conn, $_POST['bloodType'] ?? '');
    $presentPosting = mysqli_real_escape_string($conn, $_POST['presentPosting'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $dob = mysqli_real_escape_string($conn, $_POST['dob'] ?? '');
    $town = mysqli_real_escape_string($conn, $_POST['town'] ?? '');
    $maritalStatus = mysqli_real_escape_string($conn, $_POST['marital_status'] ?? '');
    $maidenName = mysqli_real_escape_string($conn, $_POST['maiden_name'] ?? '');
    $state = mysqli_real_escape_string($conn, $_POST['state_of_origin'] ?? '');
    $lga = mysqli_real_escape_string($conn, $_POST['lga'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $genotype = mysqli_real_escape_string($conn, $_POST['genotype'] ?? '');
    $nok = mysqli_real_escape_string($conn, $_POST['nok'] ?? '');
    $nok_relationship = mysqli_real_escape_string($conn, $_POST['nok_relationship'] ?? '');
    $nok_phone = mysqli_real_escape_string($conn, $_POST['nok_phone'] ?? '');
    $qualification = mysqli_real_escape_string($conn, $_POST['qualification'] ?? '');
    $institution = mysqli_real_escape_string($conn, $_POST['institution'] ?? '');
    $dateObtained = mysqli_real_escape_string($conn, $_POST['date_obtained'] ?? '');
    $certificateNo = mysqli_real_escape_string($conn, $_POST['certificate_number'] ?? '');
    $dopa = mysqli_real_escape_string($conn, $_POST['dopa'] ?? '');
    $dofa = mysqli_real_escape_string($conn, $_POST['dofa'] ?? '');
    $ippis = mysqli_real_escape_string($conn, $_POST['ippis'] ?? '');
    $bank = mysqli_real_escape_string($conn, $_POST['bank'] ?? '');
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
    $pfa = mysqli_real_escape_string($conn, $_POST['pfa'] ?? '');
    $rsa = mysqli_real_escape_string($conn, $_POST['rsa'] ?? '');
    $nhf = mysqli_real_escape_string($conn, $_POST['nhf'] ?? '');
    $directorate = mysqli_real_escape_string($conn, $_POST['directorate'] ?? '');
    $unit = mysqli_real_escape_string($conn, $_POST['unit'] ?? '');
    $postingOther = mysqli_real_escape_string($conn, $_POST['posting_other'] ?? '');
    $user_id = mysqli_real_escape_string($conn, $_POST['logged_in'] ?? $_SESSION['user_id'] ?? '');
    $date_of_entry = date("Y/m/d");

    // Validate required fields
    if (empty($NISNo) || empty($firstName) || empty($surname)) {
        $response['message'] = 'Required fields are missing. Please check Service No, First Name, and Surname.';
        echo json_encode($response);
        exit;
    }

    // Check for duplicate service number
    $check_sql = "SELECT serviceNo FROM tbl_emppersonal WHERE serviceNo = '$NISNo'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (!$check_result) {
        $response['message'] = 'Database query error: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    
    if (mysqli_num_rows($check_result) > 0) {
        $response['message'] = 'Duplicate record found! Service Number ' . $NISNo . ' already exists.';
        $response['type'] = 'warning';
        echo json_encode($response);
        exit;
    }

    // Ensure the SHQ directorate/unit/other columns exist (self-healing schema,
    // consistent with the pattern used elsewhere in this app).
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_employment LIKE 'directorate'");
    if (mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, "ALTER TABLE tbl_employment ADD directorate VARCHAR(150) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE tbl_employment ADD unit VARCHAR(150) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE tbl_employment ADD posting_other VARCHAR(100) DEFAULT NULL");
    }

    // Start transaction
    mysqli_begin_transaction($conn);
    
    // 1. Personal Info Insertion
    $q1 = mysqli_query($conn, "INSERT INTO tbl_emppersonal (
        serviceNo, surname, firstName, middleName, dob, marital_status, maiden_name,
        gender, state_of_origin, town, lga, bloodType, genotype, email, phone,
        user_id, date_of_entry, card_status
    ) VALUES (
        '$NISNo', '$surname', '$firstName', '$middleName', '$dob', '$maritalStatus', '$maidenName',
        '$gender', '$state', '$town', '$lga', '$bloodType', '$genotype', '$email', '$phone',
        '$user_id', '$date_of_entry', 'Pending Printing'
    )");
    if (!$q1) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        error_log("Enrolment Error (tbl_emppersonal): " . $err);
        echo json_encode(['success' => false, 'message' => 'Personal Info Save Failed: ' . $err, 'type' => 'error']);
        exit;
    }

    // 2. Employment Info Insertion (Explicit named columns)
    $q2 = mysqli_query($conn, "INSERT INTO tbl_employment (
        serviceNo, dofa, dopa, presentPosting, currentRank, empStatus, date_of_entry, directorate, unit, posting_other
    ) VALUES (
        '$NISNo', '$dofa', '$dopa', '$presentPosting', '$currentRank', 'Active', '$date_of_entry', '$directorate', '$unit', '$postingOther'
    )");
    if (!$q2) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        error_log("Enrolment Error (tbl_employment): " . $err);
        echo json_encode(['success' => false, 'message' => 'Employment Info Save Failed: ' . $err, 'type' => 'error']);
        exit;
    }

    // 3. Qualification Info Insertion (Self-healing column detection for certificate_number/certificateNo & date_obtained/dateObtained)
    $cert_col = 'certificate_number';
    $q_cert = mysqli_query($conn, "SHOW COLUMNS FROM tbl_qualification LIKE 'certificate_number'");
    if (!$q_cert || mysqli_num_rows($q_cert) == 0) {
        $cert_col = 'certificateNo';
    }

    $date_col = 'date_obtained';
    $q_date = mysqli_query($conn, "SHOW COLUMNS FROM tbl_qualification LIKE 'date_obtained'");
    if (!$q_date || mysqli_num_rows($q_date) == 0) {
        $date_col = 'dateObtained';
    }

    $q3 = mysqli_query($conn, "INSERT INTO tbl_qualification (
        serviceNo, qualification, institution, `$cert_col`, `$date_col`, date_of_entry
    ) VALUES (
        '$NISNo', '$qualification', '$institution', '$certificateNo', '$dateObtained', '$date_of_entry'
    )");
    if (!$q3) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        error_log("Enrolment Error (tbl_qualification): " . $err);
        echo json_encode(['success' => false, 'message' => 'Qualification Info Save Failed: ' . $err, 'type' => 'error']);
        exit;
    }

    // 4. Next of Kin Insertion (Explicit named columns - omitting auto-increment id)
    $q4 = mysqli_query($conn, "INSERT INTO tbl_nok (
        serviceNo, nok, nok_relationship, nok_phone, date_of_entry
    ) VALUES (
        '$NISNo', '$nok', '$nok_relationship', '$nok_phone', '$date_of_entry'
    )");
    if (!$q4) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        error_log("Enrolment Error (tbl_nok): " . $err);
        echo json_encode(['success' => false, 'message' => 'Next of Kin Save Failed: ' . $err, 'type' => 'error']);
        exit;
    }

    // 5. Finance Details Insertion (Self-healing column detection for IPPIS_No/ippis, accountNo/account_number, PFA/pfa, pensionNo/rsa, NHF_No/nhf)
    $ippis_col = 'IPPIS_No';
    $q_ippis = mysqli_query($conn, "SHOW COLUMNS FROM tbl_finance LIKE 'IPPIS_No'");
    if (!$q_ippis || mysqli_num_rows($q_ippis) == 0) {
        $ippis_col = 'ippis';
    }

    $acc_col = 'accountNo';
    $q_acc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_finance LIKE 'accountNo'");
    if (!$q_acc || mysqli_num_rows($q_acc) == 0) {
        $acc_col = 'account_number';
    }

    $pfa_col = 'PFA';
    $q_pfa = mysqli_query($conn, "SHOW COLUMNS FROM tbl_finance LIKE 'PFA'");
    if (!$q_pfa || mysqli_num_rows($q_pfa) == 0) {
        $pfa_col = 'pfa';
    }

    $pension_col = 'pensionNo';
    $q_pen = mysqli_query($conn, "SHOW COLUMNS FROM tbl_finance LIKE 'pensionNo'");
    if (!$q_pen || mysqli_num_rows($q_pen) == 0) {
        $pension_col = 'rsa';
    }

    $nhf_col = 'NHF_No';
    $q_nhf = mysqli_query($conn, "SHOW COLUMNS FROM tbl_finance LIKE 'NHF_No'");
    if (!$q_nhf || mysqli_num_rows($q_nhf) == 0) {
        $nhf_col = 'nhf';
    }

    $q5 = mysqli_query($conn, "INSERT INTO tbl_finance (
        serviceNo, `$ippis_col`, bank, `$acc_col`, `$pfa_col`, `$pension_col`, `$nhf_col`, date_of_entry
    ) VALUES (
        '$NISNo', '$ippis', '$bank', '$account_number', '$pfa', '$rsa', '$nhf', '$date_of_entry'
    )");
    if (!$q5) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        error_log("Enrolment Error (tbl_finance): " . $err);
        echo json_encode(['success' => false, 'message' => 'Banking/Finance Save Failed: ' . $err, 'type' => 'error']);
        exit;
    }

    // Commit transaction when all queries pass
    mysqli_commit($conn);
    
    // Audit Log Entry
    log_activity($conn, 'NEW_ENROLMENT', "Enrolled new employee Service No: $NISNo ($surname $firstName)");
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Record saved successfully!';
    $response['type'] = 'success';
    $response['serviceNo'] = $NISNo;
    $response['redirect'] = 'myenrolments.php'; // Add redirect URL
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $response['message'] = 'System error: ' . $e->getMessage();
    echo json_encode($response);
} finally {
    // Close connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
    ob_end_flush();
}
?>