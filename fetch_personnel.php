<?php
session_start();
if (!isset($_SESSION['username'])) {
    die('Unauthorized');
}

require_once ('db/db.php');

// Get parameters from DataTables
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Active personnel condition: not deceased, age < 60, service < 35 years, status not retired
$active_cond = "emp2.empStatus != 'Deceased' AND TIMESTAMPDIFF(YEAR, emp.dob, CURDATE()) < 60 AND TIMESTAMPDIFF(YEAR, emp2.dofa, CURDATE()) < 35 AND emp2.empStatus != 'Retired'";

// Build the WHERE clause for search
$whereClause = " WHERE $active_cond";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $whereClause .= " AND (emp.serviceNo LIKE '%$search%' 
                    OR emp.surname LIKE '%$search%' 
                    OR emp.firstName LIKE '%$search%' 
                    OR emp.middleName LIKE '%$search%' 
                    OR emp2.currentRank LIKE '%$search%'
                    OR ff.ippis_no LIKE '%$search%')";
}

// Get total records count
$totalSql = "SELECT COUNT(*) as total FROM tbl_emppersonal emp 
             INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
             INNER JOIN tbl_nok nk ON emp.serviceNo = nk.serviceNo
             INNER JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
             INNER JOIN tbl_finance ff ON emp.serviceNo = ff.serviceNo
             WHERE $active_cond";
$totalResult = mysqli_query($conn, $totalSql);
$totalRecords = mysqli_fetch_assoc($totalResult)['total'];

// Get filtered records count
$filteredSql = "SELECT COUNT(*) as total FROM tbl_emppersonal emp 
                INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
                INNER JOIN tbl_nok nk ON emp.serviceNo = nk.serviceNo
                INNER JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
                INNER JOIN tbl_finance ff ON emp.serviceNo = ff.serviceNo
                $whereClause";
$filteredResult = mysqli_query($conn, $filteredSql);
$filteredRecords = mysqli_fetch_assoc($filteredResult)['total'];

// Fetch data with pagination
$sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.middleName, 
           emp.bloodType, emp.gender, emp.state_of_origin,
           emp.dob, emp.lga, emp.town, emp.marital_status,
           emp2.dofa, emp2.dopa, emp2.currentRank, emp2.presentPosting,
           nk.nok, nk.nok_phone, nk.nok_relationship, 
           qf.qualification, qf.date_obtained,
           ff.ippis_no, ff.bank, ff.accountNo
           FROM tbl_emppersonal emp 
           INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
           INNER JOIN tbl_nok nk ON emp.serviceNo = nk.serviceNo
           INNER JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
           INNER JOIN tbl_finance ff ON emp.serviceNo = ff.serviceNo
           $whereClause
           ORDER BY emp.serviceNo ASC
           LIMIT $start, $length";

$retrieve = mysqli_query($conn, $sqluse);
$data = array();

while ($row = mysqli_fetch_assoc($retrieve)) {
    // Calculate DOR
    $dob = strtotime($row['dob'] . ' +60 years');
    $dofa = strtotime($row['dofa'] . ' +35 years');
    $dor = ($dob < $dofa) ? date('Y-m-d', $dob) : date('Y-m-d', $dofa);
    
    $data[] = array(
        $row['serviceNo'],
        $row['ippis_no'],
        $row['surname'],
        $row['firstName'],
        $row['middleName'],
        $row['currentRank'],
        $row['gender'],
        $row['state_of_origin'],
        $row['lga'],
        $row['town'],
        $row['dob'],
        $row['dofa'],
        $row['dopa'],
        $dor,
        $row['marital_status'],
        $row['bank'],
        $row['accountNo'],
        $row['nok'],
        $row['bloodType']
    );
}

// Prepare response for DataTables
$response = array(
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
);

header('Content-Type: application/json');
echo json_encode($response);
?>