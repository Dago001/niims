<?php
// captcha_image.php
// Renders the login CAPTCHA as an actual raster image. Previously the code
// was sent to the browser as plain text (in the page HTML and again via
// refresh_captcha.php's JSON), which meant a script could read the answer
// directly with no image-recognition step at all. Rendering it as pixels
// forces an attacker to actually solve an OCR problem instead of a
// string-parsing one.
session_start();

function niims_generate_captcha_code($length = 6) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

$code = niims_generate_captcha_code();
$_SESSION['captcha_code'] = $code;

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$width = 180;
$height = 60;
$im = imagecreatetruecolor($width, $height);

$bgColor = imagecolorallocate($im, 248, 249, 250);
imagefilledrectangle($im, 0, 0, $width, $height, $bgColor);

// Background noise lines
for ($i = 0; $i < 8; $i++) {
    $lineColor = imagecolorallocate($im, random_int(180, 220), random_int(180, 220), random_int(180, 220));
    imageline($im, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
}

$fontPath = __DIR__ . '/fonts/ImperatorSmallCaps_Bold.ttf';
$useTtf = function_exists('imagettftext') && is_file($fontPath);

$charCount = strlen($code);
$slot = intdiv($width - 20, $charCount);

for ($i = 0; $i < $charCount; $i++) {
    $char = $code[$i];
    $textColor = imagecolorallocate($im, random_int(20, 90), random_int(20, 90), random_int(20, 90));
    $x = 10 + $i * $slot + random_int(0, 4);
    $y = random_int(38, 46);

    if ($useTtf) {
        $angle = random_int(-20, 20);
        $size = random_int(18, 22);
        $result = @imagettftext($im, $size, $angle, $x, $y, $textColor, $fontPath, $char);
        if ($result === false) {
            $useTtf = false; // font failed to render; fall back for remaining chars
        }
    }

    if (!$useTtf) {
        imagestring($im, 5, $x, random_int(15, 25), $char, $textColor);
    }
}

// Foreground noise dots
for ($i = 0; $i < 60; $i++) {
    $dotColor = imagecolorallocate($im, random_int(120, 180), random_int(120, 180), random_int(120, 180));
    imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
}

imagepng($im);
imagedestroy($im);
exit();
