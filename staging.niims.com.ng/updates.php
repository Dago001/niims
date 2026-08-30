<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

// Every action below writes to the database (passwords, roles, personal
// records) — none of it may run for an unauthenticated request.
if (!isset($_SESSION['username'])) {
    header("Location: index");
    exit();
}

// ======= CSRF PROTECTION =======
// All POST operations in this file are state-changing (writes to DB).
// We validate a per-session CSRF token on every request to prevent
// cross-site request forgery. Pages that POST here must include the token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valid_tokens = [];
    // Collect all known CSRF tokens from session
    foreach ($_SESSION as $key => $val) {
        if (strpos($key, '_csrf_token') !== false) {
            $valid_tokens[] = $val;
        }
    }
    // Also check the generic updates CSRF token
    if (empty($_SESSION['updates_csrf_token'])) {
        $_SESSION['updates_csrf_token'] = bin2hex(random_bytes(32));
    }
    $valid_tokens[] = $_SESSION['updates_csrf_token'];

    $submitted_token = $_POST['csrf_token'] ?? '';
    $token_valid = false;
    foreach ($valid_tokens as $t) {
        if (hash_equals($t, $submitted_token)) {
            $token_valid = true;
            break;
        }
    }
    if (!$token_valid) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']));
    }
}

require_once('db/db.php');
require_once('functions/password_helper.php');

// ======= USER PROFILE UPDATE =======
// Touches tbl_users (role, password, account details) — same access bar as
// createuser.php / edit.php, not just "logged in".
if (isset($_POST["updateProfile"])) {

    $allowed_roles = ['Admin', 'SuperAdmin', 'Supervisor'];
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        die("Access Denied. You are not authorized to perform this action.");
    }

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
        $policyCheck = validatePasswordPolicy($password);
        if ($policyCheck !== true) {
            $_SESSION['profile_update_error'] = true;
            $_SESSION['profile_update_message'] = $policyCheck;
            header("Location: edit?id=" . urlencode($user_id));
            exit();
        }
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
        log_activity($conn, 'USER_UPDATE', "Updated account profile for user: $user_id ($firstname $lastname)");
        $_SESSION['profile_update_success'] = true;
        $_SESSION['profile_update_message'] = "User profile updated successfully!";
        $_SESSION['profile_updated_user_id'] = $user_id;
        header("Location: createuser?tab=manage");
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

    if (empty($newPassword)) {
        echo "Password is required.";
        exit();
    }
    $policyCheck = validatePasswordPolicy($newPassword);
    if ($policyCheck !== true) {
        echo $policyCheck;
        exit();
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Use prepared statement — also clears the mandatory-change flag so a
    // user who was forced here on first login can proceed into the app.
    $update = "UPDATE tbl_users SET password = ?, must_change_password = 0 WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $update);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, $NISNo);
        $insert = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($insert) {
            $_SESSION['must_change_password'] = 0;
            $redirect_target = (isset($_SESSION['role']) && $_SESSION['role'] == "Admin") ? "mainpage" : "landing";
            header("Location: " . $redirect_target);
            exit();
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