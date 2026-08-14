<?php
session_start();

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['Admin', 'Supervisor'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized");
}

require_once('db/db.php');

if (isset($_POST["editRank"])){
    
    $NISNo = $_POST['serviceNo'];
    $currentRank = $_POST['currentRank'];
    $redirect_serviceNo = isset($_POST['redirect_serviceNo']) ? $_POST['redirect_serviceNo'] : $NISNo;
    
    $insertion = "UPDATE tbl_employment
                 SET currentRank = ?
                 WHERE serviceNo = ?";
    
    $stmt = mysqli_prepare($conn, $insertion);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $currentRank, $NISNo);
        $insert = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($insert){   
            // Store success message in session
            $_SESSION['success_message'] = "Rank updated successfully!";
            $_SESSION['redirect_url'] = "update_rank.php?search=" . urlencode($redirect_serviceNo);
            
            // Redirect to a popup page
            header("Location: rank_success_popup");
            exit();
        } else {
            $msg = "Failed to update record.";
            echo $msg;
        }
    } else {
        echo "Error preparing statement.";
    }
}
?>