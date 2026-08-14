<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

// Check if user has permission to create users
$allowed_roles = ['Admin', 'SuperAdmin', 'Supervisor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die("Access Denied. You are not authorized to access this area.");
}

require_once('db/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if required fields are set
    if (empty($_POST['serviceNo']) || empty($_POST['userRole']) || empty($_POST['fname']) || empty($_POST['lname'])) {
        $_SESSION['error'] = "Required fields are missing!";
        $_SESSION['form_data'] = $_POST;
        header("Location: createuser");
        exit;
    }
    
    // Get form data
    $user_id = trim($_POST['serviceNo']);
    $user_role = trim($_POST['userRole']);
    $firstname = trim($_POST['fname']);
    $lastname = trim($_POST['lname']);
    
    // Email is required based on your database structure
    $email_address = !empty(trim($_POST['email'])) ? trim($_POST['email']) : '';
    
    // If email is empty, create a default one
    if (empty($email_address)) {
        $email_address = strtolower($firstname . '.' . $lastname . '@niims.gov.ng');
    }
    
    // Password handling
    $generated_password = '';
    if (!empty($_POST['password'])) {
        $raw_password = trim($_POST['password']);
        if (strlen($raw_password) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters long.";
            $_SESSION['form_data'] = $_POST;
            header("Location: createuser");
            exit;
        }
    } else {
        $raw_password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
        $generated_password = $raw_password;
    }
    
    // Hash password
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    
    $photo = '';
    
    // Handle photo upload
    if (isset($_FILES["photo"]) && $_FILES["photo"]['error'] == UPLOAD_ERR_OK) {
        $imageName = $_FILES["photo"]['name'];
        $imageTmp = $_FILES["photo"]['tmp_name'];
        $imageSize = $_FILES["photo"]['size'];
        $imageExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        
        if (in_array($imageExt, ['jpg', 'jpeg', 'png']) && $imageSize <= 5000000) {
            $photo = $user_id . "_" . time() . "." . $imageExt;
            $targetPath = "images/" . $photo;
            
            if (!is_dir('images')) {
                mkdir('images', 0755, true);
            }
            
            if (!move_uploaded_file($imageTmp, $targetPath)) {
                $photo = '';
                $_SESSION['warning'] = "Photo upload failed. User created without photo.";
            }
        } else {
            $_SESSION['warning'] = "Invalid photo file. User created without photo.";
        }
    }
    
    try {
        if (!$conn) {
            throw new Exception("Database connection failed.");
        }
        
        // First, let's check the actual table structure
        $checkTable = mysqli_query($conn, "DESCRIBE tbl_users");
        $columns = [];
        while ($row = mysqli_fetch_assoc($checkTable)) {
            $columns[$row['Field']] = $row;
        }
        
        // Check for duplicate user_id
        $check = mysqli_query($conn, "SELECT * FROM tbl_users WHERE user_id = '" . mysqli_real_escape_string($conn, $user_id) . "'");
        if (mysqli_num_rows($check) > 0) {
            $_SESSION['error'] = "Service Number '$user_id' already exists!";
            $_SESSION['form_data'] = $_POST;
            header("Location: createuser");
            exit;
        }
        
        // Check for duplicate email if email column exists
        if (isset($columns['email_address']) && !empty($email_address)) {
            $check = mysqli_query($conn, "SELECT * FROM tbl_users WHERE email_address = '" . mysqli_real_escape_string($conn, $email_address) . "'");
            if (mysqli_num_rows($check) > 0) {
                $_SESSION['error'] = "Email '$email_address' already exists!";
                $_SESSION['form_data'] = $_POST;
                header("Location: createuser");
                exit;
            }
        }
        
        // Escape all inputs
        $user_id = mysqli_real_escape_string($conn, $user_id);
        $email_address = mysqli_real_escape_string($conn, $email_address);
        $password = mysqli_real_escape_string($conn, $password);
        $user_role = mysqli_real_escape_string($conn, $user_role);
        $lastname = mysqli_real_escape_string($conn, $lastname);
        $firstname = mysqli_real_escape_string($conn, $firstname);
        $photo = mysqli_real_escape_string($conn, $photo);
        
        // Build the INSERT query based on actual columns
        $field_names = [];
        $field_values = [];
        
        // Always include these required fields
        $field_names[] = 'user_id';
        $field_values[] = "'$user_id'";
        
        $field_names[] = 'user_role';
        $field_values[] = "'$user_role'";
        
        $field_names[] = 'lastname';
        $field_values[] = "'$lastname'";
        
        $field_names[] = 'firstname';
        $field_values[] = "'$firstname'";
        
        $field_names[] = 'password';
        $field_values[] = "'$password'";
        
        // Include email_address if column exists
        if (isset($columns['email_address'])) {
            $field_names[] = 'email_address';
            $field_values[] = "'$email_address'";
        }
        
        // Include photo if column exists and we have a value
        if (isset($columns['photo'])) {
            $field_names[] = 'photo';
            $field_values[] = $photo ? "'$photo'" : "''";
        }
        
        // Include is_active if column exists
        if (isset($columns['is_active'])) {
            $field_names[] = 'is_active';
            $field_values[] = "1";
        }
        
        // Build the final query
        $field_names_str = implode(', ', $field_names);
        $field_values_str = implode(', ', $field_values);
        
        $insertQuery = "INSERT INTO tbl_users ($field_names_str) VALUES ($field_values_str)";
        
        // Debug: Log the query
        error_log("INSERT Query: " . $insertQuery);
        
        if (mysqli_query($conn, $insertQuery)) {
            $newUserId = mysqli_insert_id($conn);
            
            $_SESSION['user_created'] = true;
            $_SESSION['new_user'] = [
                'service_no' => $user_id,
                'first_name' => $firstname,
                'surname' => $lastname,
                'full_name' => $firstname . ' ' . $lastname,
                'role' => $user_role,
                'email' => $email_address ?: 'Not provided',
                'user_id' => $newUserId,
                'password' => !empty($generated_password) ? $generated_password : 'User-provided',
                'photo' => $photo ?: 'No photo'
            ];
            
            if (isset($_SESSION['form_data'])) {
                unset($_SESSION['form_data']);
            }
            
            header("Location: createuser");
            exit;
            
        } else {
            throw new Exception("Insert failed: " . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header("Location: createuser");
        exit;
    }
    
} else {
    header("Location: createuser");
    exit;
}
?>