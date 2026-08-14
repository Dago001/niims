<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Forbidden');
}

require_once 'db/db.php';
require_once 'includes/log_print.php';

$serviceNo = $_POST['serviceNo'] ?? '';
$print_method = $_POST['print_method'] ?? 'single';
$batch_id = $_POST['batch_id'] ?? null;

$response = ['success' => false];

if (!empty($serviceNo)) {
    $success = logPrintedCard($conn, $serviceNo, $print_method, $batch_id);
    if ($success) {
        $response['success'] = true;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
