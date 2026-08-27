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
    
    // Personal Info Insertion
    $insertion = "INSERT INTO tbl_emppersonal VALUES(
        '$NISNo',
        '$surname',                                    
        '$firstName', 
        '$middleName',
        '$dob',
        '$maritalStatus',
        '$maidenName',                                    
        '$gender',
        '$state',
        '$town','$lga', 
        '$bloodType',
        '$genotype',
        '$email', 
        '$phone',
        '','', '', '$user_id',
        '$date_of_entry'
    )";

    // Execute all queries
    $queries = [];
    $queries[] = mysqli_query($conn, $insertion);
    // Named columns (rather than the previous bare VALUES(...) list) — tbl_employment
    // has grown extra columns (disciplinary_status, promotion_eligibility, etc.) since
    // this insert was written, and a positional VALUES list with too few values fails
    // outright ("Column count doesn't match value count"), so every enrolment was
    // silently failing before this fix. Columns not listed here just take their
    // table defaults.
    $queries[] = mysqli_query($conn, "INSERT INTO tbl_employment
        (serviceNo, dofa, dopa, presentPosting, currentRank, empStatus, date_of_entry, directorate, unit, posting_other)
        VALUES ('$NISNo', '$dofa', '$dopa', '$presentPosting', '$currentRank', 'Active', '$date_of_entry', '$directorate', '$unit', '$postingOther')");
    $queries[] = mysqli_query($conn, "INSERT INTO tbl_qualification VALUES('', '$NISNo', '$qualification','$institution', '$certificateNo','$dateObtained', '$date_of_entry')");
    $queries[] = mysqli_query($conn, "INSERT INTO tbl_nok VALUES('', '$NISNo', '$nok','$nok_relationship','$nok_phone', '$date_of_entry')");
    $queries[] = mysqli_query($conn, "INSERT INTO tbl_finance VALUES('', '$NISNo', '$ippis', '$bank','$account_number','$pfa', '$rsa','$nhf','$date_of_entry')");

    // Check for any query failures
    $failed = false;
    foreach ($queries as $query) {
        if (!$query) {
            $failed = true;
            break;
        }
    }
    
    if ($failed) {
        mysqli_rollback($conn);
        $response['message'] = 'Failed to save record: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
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