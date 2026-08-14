<?php
require_once('db/db.php');
header('Content-Type: application/json');

$serviceNo = mysqli_real_escape_string($conn, $_POST['serviceNo'] ?? '');

$response = ['exists' => false];

if (!empty($serviceNo)) {
    // Check if the service number exists in the personal information table
    $sql = "SELECT serviceNo FROM tbl_emppersonal WHERE serviceNo = '$serviceNo'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $response['exists'] = true;
    }
}

echo json_encode($response);
