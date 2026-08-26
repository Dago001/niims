<?php
class GoogleAuthenticator {
    private static $_base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$_base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getQRCodeGoogleUrl($name, $secret, $title = null) {
        $url = 'otpauth://totp/' . rawurlencode($name) . '?secret=' . rawurlencode($secret);
        if (!empty($title)) {
            $url .= '&issuer=' . rawurlencode($title);
        }
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
    }

    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, (string) $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $secretKey = self::_base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    private static function _base32Decode($secret) {
        if (empty($secret)) return '';
        
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $secret = strtoupper($secret);
        $secret = preg_replace('/[^A-Z2-7]/', '', $secret);
        
        $binaryString = "";
        
        $bin = "";
        foreach (str_split($secret) as $char) {
            if (!isset($base32charsFlipped[$char])) continue;
            $bin .= str_pad(decbin($base32charsFlipped[$char]), 5, '0', STR_PAD_LEFT);
        }
        
        $fiveBytes = str_split($bin, 8);
        foreach ($fiveBytes as $byte) {
            if (strlen($byte) == 8) {
                $binaryString .= chr(bindec($byte));
            }
        }
        return $binaryString;
    }

    public static function getQRCodeLocalSvg($name, $secret, $title = null) {
        require_once(__DIR__ . '/../vendors/phpqrcode/qrlib.php');
        $otpauth_url = 'otpauth://totp/' . rawurlencode($name) . '?secret=' . rawurlencode($secret);
        if (!empty($title)) {
            $otpauth_url .= '&issuer=' . rawurlencode($title);
        }
        
        $matrix = QRcode::text($otpauth_url);
        $size = count($matrix);
        
        $pixelSize = 6;
        $padding = 4;
        $width = ($size + 2 * $padding) * $pixelSize;
        $height = $width;
        
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="140" height="140" style="display:block;margin:0 auto;border-radius:8px;">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff" />';
        $svg .= '<path d="';
        for ($y = 0; $y < $size; $y++) {
            $row = $matrix[$y];
            for ($x = 0; $x < $size; $x++) {
                if ($row[$x] === '1') {
                    $posX = ($x + $padding) * $pixelSize;
                    $posY = ($y + $padding) * $pixelSize;
                    $svg .= 'M' . $posX . ',' . $posY . 'h' . $pixelSize . 'v' . $pixelSize . 'h-' . $pixelSize . 'z ';
                }
            }
        }
        $svg .= '" fill="#000000" />';
        $svg .= '</svg>';
        
        return $svg;
    }
}
?>
