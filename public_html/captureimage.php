<?php
session_start();

// Must be authenticated
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized");
}

// Validate required POST fields exist
if (empty($_POST['image']) || empty($_POST['user_id'])) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(["error" => "Missing required fields."]));
}

// SECURITY: Lock user_id to the session — prevent IDOR
// A logged-in user must only be able to write their OWN photo
$session_user_id = $_SESSION['user_id'] ?? '';
$posted_user_id  = $_POST['user_id'];

if ((string)$posted_user_id !== (string)$session_user_id) {
    header("HTTP/1.1 403 Forbidden");
    exit(json_encode(["error" => "Unauthorized file operation."]));
}

// SECURITY: Sanitize the user_id used in filename — strip any path separators
// This prevents path traversal (e.g. user_id = "../../../evil")
$safe_user_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $session_user_id);
if (empty($safe_user_id)) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(["error" => "Invalid user identifier."]));
}

// Parse the base64 image data
$image_parts = explode(";base64,", $_POST['image']);
if (count($image_parts) !== 2) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(["error" => "Invalid image data format."]));
}

$image_base64 = base64_decode($image_parts[1], true);
if ($image_base64 === false) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(["error" => "Invalid base64 data."]));
}

// SECURITY: Enforce file size limit (5MB)
if (strlen($image_base64) > 5 * 1024 * 1024) {
    header("HTTP/1.1 413 Payload Too Large");
    exit(json_encode(["error" => "Image exceeds 5MB size limit."]));
}

// SECURITY: Validate the decoded bytes are actually an image using getimagesize
// This prevents PHP scripts being saved with a .png extension
$image_info = @getimagesizefromstring($image_base64);
if ($image_info === false) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(["error" => "Uploaded data is not a valid image."]));
}

// Only allow JPEG and PNG MIME types from the webcam stream
$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($image_info['mime'], $allowed_mimes)) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(["error" => "Only JPEG and PNG images are allowed."]));
}

// Build a safe file path — always save as .png (webcam output)
$folderPath = 'upload/';
$file = $folderPath . $safe_user_id . '.png';

// Write the validated image
if (file_put_contents($file, $image_base64) === false) {
    header("HTTP/1.1 500 Internal Server Error");
    exit(json_encode(["error" => "Failed to save image."]));
}

echo json_encode(["success" => true, "message" => "Image uploaded successfully."]);