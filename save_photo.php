<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
error_reporting(E_ALL);
// save_photo.php - PHP script to handle image saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image'])) {
    // The upload target is derived from the authenticated session, never from
    // client input — otherwise any logged-in user could overwrite another
    // user's photo by just supplying a different "username" value.
    $username = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['username']);
    $imageData = $_POST['image'];

    // Decode the base64 image data
    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));

    // Verify the decoded payload is actually an image before writing it to disk.
    $tmpCheckFile = tempnam(sys_get_temp_dir(), 'photo_check_');
    file_put_contents($tmpCheckFile, $imageData);
    $imageInfo = @getimagesize($tmpCheckFile);
    unlink($tmpCheckFile);

    if ($username === '' || $imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF])) {
        echo json_encode(['success' => false, 'message' => 'Invalid image data.']);
        exit();
    }

    $filePath = 'uploads/' . $username . '.png'; // Save as PNG file

    // Create the uploads directory if it doesn't exist
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
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
