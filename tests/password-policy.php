<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$validPasswords = [
    'abcd123@',
    'MixedCase9!',
    str_repeat('LongPass7#', 30),
    'éééééé7!',
];
$invalidPasswords = [
    '',
    'short1@',
    'password@',
    'password1',
    'abcdefgh',
    'Pass123_',
];

foreach ($validPasswords as $password) {
    if (!account_password_is_valid($password)) {
        throw new RuntimeException('A valid password was rejected.');
    }
}
foreach ($invalidPasswords as $password) {
    if (account_password_is_valid($password)) {
        throw new RuntimeException('An invalid password was accepted.');
    }
}

$password = str_repeat('VeryLongPassword9!', 20);
$newHash = hash_account_password($password);
if (!verify_account_password($password, $newHash)
    || verify_account_password($password . 'wrong', $newHash)
    || account_password_needs_rehash($newHash)) {
    throw new RuntimeException('New account password hashing/verification failed.');
}

$legacyPassword = 'legacy-password';
$legacyHash = password_hash($legacyPassword, PASSWORD_ALGO);
if (!verify_account_password($legacyPassword, $legacyHash)
    || !account_password_needs_rehash($legacyHash)) {
    throw new RuntimeException('Legacy password verification or rehash detection failed.');
}

echo "Password policy, unlimited-length hashing, and legacy verification passed.\n";
