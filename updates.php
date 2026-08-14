<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once('db/db.php');

// ======= USER PROFILE UPDATE =======
if (isset($_POST["updateProfile"])) {
    
    // Get and sanitize form data
    $user_id = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $firstname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lname']);
    $user_role = mysqli_real_escape_string($conn, $_POST['userRole']);
    
    // Build update query parts
    $updateParts = [];
    $updateParts[] = "firstname = '$firstname'";
    $updateParts[] = "lastname = '$lastname'";
    $updateParts[] = "user_role = '$user_role'";
    
    // Handle optional email
    if (!empty($_POST['email'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $updateParts[] = "email_address = '$email'";
    }
    
    // Handle optional password
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateParts[] = "password = '$hashedPassword'";
    }
    
    // Handle optional photo upload
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] == 0) {
        $target_dir = "upload/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $file_name = basename($_FILES["photo"]["name"]);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = $user_id . "." . $file_extension;
        $target_file = $target_dir . $new_file_name;
        
        $check = getimagesize($_FILES["photo"]["tmp_name"]);
        if ($check !== false) {
            if ($_FILES["photo"]["size"] <= 5000000) {
                if (in_array($file_extension, ["jpg", "jpeg", "png"])) {
                    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                        $updateParts[] = "photo = '$target_file'";
                    }
                }
            }
        }
    }
    
    // Build and execute query
    $updateQuery = "UPDATE tbl_users SET " . implode(", ", $updateParts) . " WHERE user_id = '$user_id'";
    
    $result = mysqli_query($conn, $updateQuery);
    
    if ($result) {
        $_SESSION['profile_update_success'] = true;
        $_SESSION['profile_update_message'] = "User profile updated successfully!";
        $_SESSION['profile_updated_user_id'] = $user_id;
        header("Location: edit-user");
        exit();
    } else {
        $_SESSION['profile_update_error'] = true;
        $_SESSION['profile_update_message'] = "Failed to update: " . mysqli_error($conn);
        header("Location: edit?id=" . urlencode($user_id));
        exit();
    }
}

// ======= UPDATE NOK =======
if (isset($_POST["updateNOK"])){
    $NISNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $nok = mysqli_real_escape_string($conn, $_POST['nok']);
    $nok_relationship = mysqli_real_escape_string($conn, $_POST['nok_relationship']);
    $nok_phone = mysqli_real_escape_string($conn, $_POST['nok_phone']);
    $date_of_entry = date("Y/m/d");
   
    $insertion = "UPDATE tbl_nok SET nok = '$nok', nok_relationship = '$nok_relationship', nok_phone = '$nok_phone', date_of_entry = '$date_of_entry' WHERE serviceNo = '$NISNo'";
    $insert = mysqli_query($conn, $insertion);
    
    if ($insert){   
        require_once('success.php');    
    } else {
        echo "Failed to update NOK: " . mysqli_error($conn);
    }
}

// ======= UPDATE PERSONAL INFO =======
if (isset($_POST["updatePersonal"])){         
    $NISNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $surname = mysqli_real_escape_string($conn, $_POST['lname']);
    $bloodType = mysqli_real_escape_string($conn, $_POST['bloodType']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $maritalStatus = mysqli_real_escape_string($conn, $_POST['marital_status']);
    $maidenName = mysqli_real_escape_string($conn, $_POST['maiden_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $genotype = mysqli_real_escape_string($conn, $_POST['genotype']);
    $gender = isset($_POST['gender']) ? mysqli_real_escape_string($conn, $_POST['gender']) : '';
    $date_of_entry = date("Y/m/d");
    
    if ($gender == 'Female'){
        $insertion = "UPDATE tbl_emppersonal SET surname = '$surname', marital_status = '$maritalStatus', maiden_name = '$maidenName', email = '$email', phone = '$phone', bloodType = '$bloodType', genotype = '$genotype', gender = '$gender', date_of_entry = '$date_of_entry' WHERE serviceNo = '$NISNo'";
    } else {
        $insertion = "UPDATE tbl_emppersonal SET surname = '$surname', marital_status = '$maritalStatus', email = '$email', phone = '$phone', bloodType = '$bloodType', genotype = '$genotype', gender = '$gender', date_of_entry = '$date_of_entry' WHERE serviceNo = '$NISNo'";
    }
                      
    $insert = mysqli_query($conn, $insertion);
    if ($insert){   
        require_once('success.php');    
    } else {
        echo "Failed to update personal info: " . mysqli_error($conn);
    }
}

// ======= UPDATE EMPLOYMENT =======
if (isset($_POST["updateEmployment"])){
    $NISNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $currentRank = mysqli_real_escape_string($conn, $_POST['currentRank']);
    $presentPosting = mysqli_real_escape_string($conn, $_POST['presentPosting']);
    $dopa = mysqli_real_escape_string($conn, $_POST['dopa']);
    $dofa = mysqli_real_escape_string($conn, $_POST['dofa']);
    
    $insertion = "UPDATE tbl_employment SET currentRank = '$currentRank', presentPosting = '$presentPosting', dopa = '$dopa', dofa = '$dofa' WHERE serviceNo = '$NISNo'";
    $insert = mysqli_query($conn, $insertion);
    
    if ($insert){   
        require_once('success.php');    
    } else {
        echo "Failed to update employment: " . mysqli_error($conn);
    }
}

// ======= PASSWORD UPDATE =======
if (isset($_POST["updatePassword"])) {
    $NISNo = $_POST['serviceNo'];
    $newPassword = $_POST['new_password'];

    if (empty($newPassword) || strlen($newPassword) < 6) {
        echo "Password must be at least 6 characters.";
        exit();
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Use prepared statement
    $update = "UPDATE tbl_users SET password = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $update);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, $NISNo);
        $insert = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($insert) {
            require_once('success.php');
        } else {
            echo "Error updating password.";
        }
    } else {
        echo "Error preparing statement.";
    }
}

// If no action matched
if (!isset($_POST["updateProfile"]) && 
    !isset($_POST["updateNOK"]) && 
    !isset($_POST["updatePersonal"]) && 
    !isset($_POST["updateEmployment"]) && 
    !isset($_POST["updatePassword"])) {
    
    echo "No valid action specified.";
}
?>