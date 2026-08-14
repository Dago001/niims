<?php
session_start();

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['Admin', 'Supervisor'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized");
}

require_once('db/db.php');

if (isset($_POST["editPosting"])){
    
    $NISNo = $_POST['serviceNo'];
    $presentPosting = $_POST['presentPosting'];
    
    $insertion = "UPDATE tbl_employment
                 SET presentPosting = ?
                 WHERE serviceNo = ?";
    
    $stmt = mysqli_prepare($conn, $insertion);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $presentPosting, $NISNo);
        $insert = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($insert){   
            require_once('success.php');    
        } else {
            $msg = "Failed to update record.";
            echo $msg;
        }
    } else {
        echo "Error preparing statement.";
    }
}
?>