<?php
session_start();
require_once('db/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !isset($_POST['check_signature'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['serviceNo'])) {
    echo json_encode(['success' => false, 'message' => 'Service number required']);
    exit;
}

$serviceNo = trim($_POST['serviceNo']);
$signature_folder = 'signature'; // Your signature folder path
$signature_file = $signature_folder . '/' . $serviceNo . '.png';

if (file_exists($signature_file)) {
    $file_time = filemtime($signature_file);
    $date_captured = date('d/m/Y H:i', $file_time);
    
    echo json_encode([
        'success' => true,
        'hasSignature' => true,
        'timestamp' => $date_captured
    ]);
} else {
    echo json_encode([
        'success' => true,
        'hasSignature' => false
    ]);
}
?>