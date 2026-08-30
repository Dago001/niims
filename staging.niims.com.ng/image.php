<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized");
}

// Add these lines at the top for better error handling and larger file support
ini_set('upload_max_filesize', '64M');
ini_set('post_max_size', '65M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');
error_reporting(0);
ini_set('display_errors', 0);

$target_dir = "upload/";

// Create upload directory if it doesn't exist
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// Check if file was actually uploaded
if (!isset($_FILES["file-upload"]) || $_FILES["file-upload"]["error"] != UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "Error: No file uploaded or upload error. Error code: " . $_FILES["file-upload"]["error"];
    header("Location: photocapture");
    exit();
}

// Get service number from form (make sure your form has this field) — strip
// anything but alphanumerics so this can't be used to traverse out of the
// upload directory via "../" or similar in the filename.
$serviceNo = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['serviceNo'] ?? '');

if (empty($serviceNo)) {
    $_SESSION['upload_error'] = "Upload Rejected: Invalid Service Number.";
    header("Location: photocapture");
    exit();
}

$uploadedBaseName = pathinfo($_FILES["file-upload"]["name"], PATHINFO_FILENAME);
$imageFileType = strtolower(pathinfo($_FILES["file-upload"]["name"], PATHINFO_EXTENSION));

// 1. Verify that the file basename matches the serviceNo exactly
if ($uploadedBaseName !== $serviceNo) {
    $_SESSION['upload_error'] = "Upload Rejected: The file must be named exactly '" . $serviceNo . "' matching the target Service Number (e.g. '" . $serviceNo . ".jpg'). Files named '" . basename($_FILES["file-upload"]["name"]) . "' are rejected.";
    header("Location: photocapture");
    exit();
}

// 2. Verify that the extension is strictly jpg, jpeg, or png
$allowed_types = array('jpg', 'jpeg', 'png');
if (!in_array($imageFileType, $allowed_types)) {
    $_SESSION['upload_error'] = "Upload Rejected: Only JPG, JPEG, and PNG files are allowed.";
    header("Location: photocapture");
    exit();
}

$target_file = $target_dir . $serviceNo . "." . $imageFileType;

// Security checks
$uploadOk = 1;

// Check file size (64MB limit)
if ($_FILES["file-upload"]["size"] > 64000000) {
    $_SESSION['upload_error'] = "Sorry, your file is too large.";
    $uploadOk = 0;
}

// Check if it's an image file
$allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
if (!in_array($imageFileType, $allowed_types)) {
    $_SESSION['upload_error'] = "Sorry, only JPG, JPEG, PNG, GIF & WEBP files are allowed.";
    $uploadOk = 0;
}

// Check if file is actually an image
$check = getimagesize($_FILES["file-upload"]["tmp_name"]);
if ($check === false) {
    $_SESSION['upload_error'] = "File is not a valid image.";
    $uploadOk = 0;
}

// Attempt to upload file if all checks pass
if ($uploadOk == 1) {
    // If using service number as filename, delete any existing files with same service number
    if (!empty($serviceNo)) {
        $old_files = glob($target_dir . $serviceNo . ".*");
        foreach ($old_files as $old_file) {
            if (is_file($old_file)) {
                unlink($old_file);
            }
        }
    }
    
    if (move_uploaded_file($_FILES["file-upload"]["tmp_name"], $target_file)) {
        // Success - set session message and redirect
        $_SESSION['upload_success'] = "Photo successfully uploaded!" . (!empty($serviceNo) ? " Old photo was replaced." : "");
        header("Location: photocapture");
        exit();
    } else {
        $_SESSION['upload_error'] = "Sorry, there was an error uploading your file. Error details: " . (error_get_last()['message'] ?? 'Unknown error');
        header("Location: photocapture");
        exit();
    }
} else {
    header("Location: photocapture");
    exit();
}
?>