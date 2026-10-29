<?php
/**
 * Automated Deployment Script for NIIMS (https://niims.immigration.gov.ng)
 * Triggers git pull when GitHub pushes to the 'main' branch.
 */

// Define a secret key - change this to your own strong passphrase
define('DEPLOY_SECRET', 'NiimsImmigrationDeploymentSecret2026!');

// Determine if request is authorized
$authorized = false;

// 1. Check GET token: https://niims.immigration.gov.ng/deploy.php?token=NiimsImmigrationDeploymentSecret2026!
if (isset($_GET['token']) && hash_equals(DEPLOY_SECRET, $_GET['token'])) {
    $authorized = true;
}

// 2. Check GitHub Webhook signature header
if (!$authorized && isset($_SERVER['HTTP_X_HUB_SIGNATURE_256'])) {
    $payload = file_get_contents('php://input');
    $hash = 'sha256=' . hash_hmac('sha256', $payload, DEPLOY_SECRET);
    if (hash_equals($hash, $_SERVER['HTTP_X_HUB_SIGNATURE_256'])) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access.']));
}

header('Content-Type: application/json');

// Ensure we stay strictly within this directory
$project_dir = __DIR__;
chdir($project_dir);

$output = [];
$return_var = 0;

// Protect db/db.php from being overwritten
exec('git config core.filemode false 2>&1');
exec('git update-index --assume-unchanged db/db.php 2>&1');

// Fetch and pull latest changes from main
exec('git fetch origin main 2>&1', $output, $return_var);
exec('git pull origin main 2>&1', $output, $return_var);

// Optional: Log the deployment
$log_entry = date('[Y-m-d H:i:s]') . " Deployment executed: \n" . implode("\n", $output) . "\n\n";
@file_put_contents(__DIR__ . '/deploy.log', $log_entry, FILE_APPEND);

echo json_encode([
    'status' => ($return_var === 0) ? 'success' : 'warning',
    'timestamp' => date('Y-m-d H:i:s'),
    'output' => $output
], JSON_PRETTY_PRINT);
