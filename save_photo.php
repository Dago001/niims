<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
error_reporting(E_ALL);
// save_photo.php - PHP script to handle image saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['image'])) {
    $username = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['username']); // Sanitize username
    $imageData = $_POST['image'];

    // Decode the base64 image data
    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
    $filePath = 'uploads/' . $username . '.png'; // Save as PNG file

    // Create the uploads directory if it doesn't exist
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    // Save the image
    if (file_put_contents($filePath, $imageData)) {
        echo json_encode(['success' => true, 'message' => 'Image saved successfully!', 'file' => $filePath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save image.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
