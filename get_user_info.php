<?php
session_start();
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_GET['serviceNo'])) {
    echo json_encode(['error' => 'Service number is required']);
    exit;
}

$serviceNo = $_GET['serviceNo'];

// Query to get user information
$sql = "SELECT surname, firstName, middleName 
        FROM tbl_emppersonal 
        WHERE serviceNo = ?";
        
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $serviceNo);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $fullName = $row['surname'] . ', ' . $row['firstName'];
    if (!empty($row['middleName'])) {
        $fullName .= ' ' . $row['middleName'];
    }
    
    echo json_encode([
        'success' => true,
        'name' => $fullName,
        'surname' => $row['surname'],
        'firstName' => $row['firstName'],
        'middleName' => $row['middleName']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
}

mysqli_stmt_close($stmt);
?>