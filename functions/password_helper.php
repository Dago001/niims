<?php
/**
 * Validates a password against the app-wide policy: at least 8 characters,
 * with at least one uppercase letter, one lowercase letter, one number, and
 * one special character.
 *
 * Returns true if the password passes, or a human-readable error string
 * naming the first unmet requirement.
 */
function validatePasswordPolicy($password) {
    $password = (string) $password;

    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least one special character.";
    }

    return true;
}

/**
 * Generates a random password that is guaranteed to satisfy
 * validatePasswordPolicy() — one of each required character class, with the
 * rest filled randomly and the whole thing shuffled.
 */
function generateCompliantPassword($length = 10) {
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // no I/O — avoids look-alike chars
    $lower = 'abcdefghijkmnpqrstuvwxyz'; // no l
    $digits = '23456789'; // no 0/1
    $special = '!@#$%^&*-_=+';

    $password = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];

    $allChars = $upper . $lower . $digits . $special;
    for ($i = count($password); $i < $length; $i++) {
        $password[] = $allChars[random_int(0, strlen($allChars) - 1)];
    }

    // Fisher-Yates shuffle using random_int (shuffle() itself is not
    // cryptographically random, but that's fine here — this only affects
    // character ORDER, not which characters were chosen).
    for ($i = count($password) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
    }

    return implode('', $password);
}
