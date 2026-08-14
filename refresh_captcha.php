<?php
// refresh_captcha.php
session_start();

function generateCaptchaCode($length = 6) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$_SESSION['captcha_code'] = generateCaptchaCode();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'captcha' => $_SESSION['captcha_code']
]);
?>