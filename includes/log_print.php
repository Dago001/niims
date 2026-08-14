<?php
/**
 * Log printed card to database
 * Include this file in card.php and bulk-print.php
 */

function logPrintedCard($conn, $serviceNo, $print_method = 'single', $batch_id = null) {
    // Get personnel details
    $sql = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.middleName, emp2.currentRank 
            FROM tbl_emppersonal emp 
            INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo 
            WHERE emp.serviceNo = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, 's', $serviceNo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $surname = $row['surname'];
        $firstName = $row['firstName'];
        $middleName = $row['middleName'];
        $currentRank = $row['currentRank']; // Using currentRank
        
        // Get current user info
        $printed_by = $_SESSION['username'] ?? 'unknown';
        $printed_by_role = $_SESSION['role'] ?? 'unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // If batch_id not provided, generate one for bulk prints
        if ($print_method === 'bulk' && $batch_id === null) {
            $batch_id = 'BATCH_' . date('Ymd_His') . '_' . uniqid();
        }
        
        // Using currentRank column (no reserved words issue)
        $insert_sql = "INSERT INTO tbl_printed_cards 
                       (serviceNo, surname, firstName, middleName, currentRank, printed_by, printed_by_role, 
                        print_date, print_method, batch_id, ip_address, user_agent) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        if (!$insert_stmt) {
            error_log("Insert prepare failed: " . mysqli_error($conn));
            mysqli_stmt_close($stmt);
            return false;
        }
        
        mysqli_stmt_bind_param($insert_stmt, 'sssssssssss', 
            $serviceNo, $surname, $firstName, $middleName, $currentRank, 
            $printed_by, $printed_by_role, $print_method, $batch_id, 
            $ip_address, $user_agent
        );
        
        $result = mysqli_stmt_execute($insert_stmt);
        
        if (!$result) {
            error_log("Insert execute failed: " . mysqli_stmt_error($insert_stmt));
        }
        
        mysqli_stmt_close($insert_stmt);
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

// Function to generate batch ID for bulk prints
function generateBatchId() {
    return 'BATCH_' . date('Ymd_His') . '_' . uniqid();
}
?>