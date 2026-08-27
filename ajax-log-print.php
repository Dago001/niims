<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Forbidden');
}

require_once 'db/db.php';
require_once 'includes/log_print.php';

$serviceNo = $_POST['serviceNo'] ?? '';
// Whitelisted rather than stored as-is — this value ends up rendered
// unescaped in a few report pages, so anything other than these two
// literals would be a stored-XSS vector.
$print_method = (($_POST['print_method'] ?? '') === 'bulk') ? 'bulk' : 'single';
$batch_id = isset($_POST['batch_id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['batch_id']) : null;

$response = ['success' => false];

if (!empty($serviceNo)) {
    $success = logPrintedCard($conn, $serviceNo, $print_method, $batch_id);
    if ($success) {
        $response['success'] = true;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
