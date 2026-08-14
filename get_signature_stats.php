<?php
session_start();
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get total count
$sql = "SELECT COUNT(DISTINCT emp.serviceNo) as total 
        FROM tbl_emppersonal emp 
        INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_array($result);
$total_personnel = $row['total'];

// Count signatures
$signature_folder = 'signature';
$with_signature = 0;
$without_signature = 0;

// Get all service numbers
$sql = "SELECT DISTINCT emp.serviceNo FROM tbl_emppersonal emp 
        INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_array($result)) {
        $serviceNo = $row['serviceNo'];
        $signature_file = $signature_folder . '/' . $serviceNo . '.png';
        
        if (file_exists($signature_file)) {
            $with_signature++;
        } else {
            $without_signature++;
        }
    }
}

// Calculate completion rate
$completion_rate = $total_personnel > 0 ? round(($with_signature / $total_personnel) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'total_personnel' => $total_personnel,
    'with_signature' => $with_signature,
    'without_signature' => $without_signature,
    'completion_rate' => $completion_rate
]);
?>