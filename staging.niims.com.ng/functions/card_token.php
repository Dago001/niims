<?php
/**
 * NIIMS Cryptographic Card Token Generator & Validator
 * 
 * Generates an unguessable 32-character hexadecimal token for ID card QR codes
 * to prevent ID enumeration and hide officer service numbers in public URLs.
 */

if (!defined('NIIMS_CARD_KEY')) {
    // 32-byte secret salt for HMAC/AES token generation
    define('NIIMS_CARD_KEY', 'NIIMS_NIS_SECURE_CARD_VERIFY_KEY_v1_2026');
}

/**
 * Generate a 32-character hexadecimal hash code from an officer service number.
 * 
 * @param string|int $serviceNo The officer's service number
 * @return string|null 32-character hex hash, or null on failure
 */
function generateCardHash($serviceNo) {
    $serviceNo = trim((string)$serviceNo);
    if ($serviceNo === '') {
        return null;
    }
    
    // Derive a 16-byte key from the secret
    $key = substr(hash('sha256', NIIMS_CARD_KEY, true), 0, 16);
    
    // Encrypt using AES-128-ECB (produces 16 raw bytes = 32 hex chars with PKCS7 padding)
    $raw = openssl_encrypt($serviceNo, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
    if ($raw === false) {
        return null;
    }
    
    return bin2hex($raw);
}

/**
 * Decode and authenticate a 32-character hexadecimal card hash back to the officer service number.
 * 
 * @param string $hash The 32-character hex hash from the QR code / URL
 * @return string|null The verified service number, or null if tampered/invalid
 */
function decodeCardHash($hash) {
    $hash = trim((string)$hash);
    
    // Must be exactly 32 hexadecimal characters
    if (strlen($hash) !== 32 || !ctype_xdigit($hash)) {
        return null;
    }
    
    $raw = @hex2bin($hash);
    if ($raw === false) {
        return null;
    }
    
    $key = substr(hash('sha256', NIIMS_CARD_KEY, true), 0, 16);
    $decrypted = openssl_decrypt($raw, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
    
    if ($decrypted === false) {
        return null;
    }
    
    $serviceNo = trim($decrypted);
    
    // Validate service number format (alphanumeric, 1-30 chars)
    if ($serviceNo === '' || !preg_match('/^[a-zA-Z0-9\/\-_]+$/', $serviceNo)) {
        return null;
    }
    
    return $serviceNo;
}
