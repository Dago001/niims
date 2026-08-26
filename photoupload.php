<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized.");
}

$target_dir = "upload/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$uploadOk = 1;

// Check the upload actually happened without transport errors.
if (!isset($_FILES["fileToUpload"]) || $_FILES["fileToUpload"]["error"] !== UPLOAD_ERR_OK) {
    echo "Sorry, no valid file was uploaded.";
    $uploadOk = 0;
}

// Check if image file is a genuine image (magic-byte level check, not just extension)
$detectedType = $uploadOk ? @exif_imagetype($_FILES["fileToUpload"]["tmp_name"]) : false;
$allowedTypes = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
];

if ($uploadOk && $detectedType === false) {
    echo "File is not a valid image.";
  $uploadOk = 0;
}

if ($uploadOk && !isset($allowedTypes[$detectedType])) {
  echo "Sorry, only JPG, PNG & GIF files are allowed.";
  $uploadOk = 0;
}

// Check file size
if ($uploadOk && $_FILES["fileToUpload"]["size"] > 500000) {
  echo "Sorry, your file is too large.";
  $uploadOk = 0;
}

// Generate a server-controlled filename — never trust the client-supplied
// name/extension, which can be used to smuggle in double extensions
// (e.g. "shell.php.jpg") or path traversal sequences.
if ($uploadOk) {
    $safeExt = $allowedTypes[$detectedType];
    $target_file = $target_dir . bin2hex(random_bytes(16)) . '.' . $safeExt;

  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        echo "The file has been uploaded as " . htmlspecialchars(basename($target_file)) . ".";
  } else {
    echo "Sorry, there was an error uploading your file.";
  }
} else {
  echo "Sorry, your file was not uploaded.";
}
?>