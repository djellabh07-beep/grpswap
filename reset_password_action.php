<?php
/**
 * reset_password_action.php
 *
 * Handles the POST from reset_password.php. Validates the email+code
 * pair (constant-time comparison, same as verify_email_action.php),
 * checks expiry, then updates password_hash and clears the reset code
 * fields so the same code can't be reused.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

function reset_fail(string $errorCode, string $email = ''): void
{
    header('Location: /reset_password.php?error=' . urlencode($errorCode) . '&email=' . urlencode($email));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot_password.php');
    exit;
}

$email           = trim($_POST['email'] ?? '');
$code            = trim($_POST['code'] ?? '');
$password        = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if ($email === '' || $code === '' || $password === '' || $passwordConfirm === '') {
    reset_fail('missing_fields', $email);
}

if (strlen($password) < 8) {
    reset_fail('password_short', $email);
}

if ($password !== $passwordConfirm) {
    reset_fail('password_mismatch', $email);
}

$stmt = $pdo->prepare(
    'SELECT id, password_reset_code, password_reset_code_expires_at
     FROM users
     WHERE email = ?
     LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// Deliberately the SAME error for "no such account" and "wrong code" —
// distinguishing them would let an attacker enumerate valid emails by
// trying random codes and watching which error comes back.
if (!$user || !$user['password_reset_code'] || !hash_equals((string) $user['password_reset_code'], $code)) {
    reset_fail('invalid_code', $email);
}

if (strtotime($user['password_reset_code_expires_at']) < time()) {
    reset_fail('expired_code', $email);
}

$newHash = password_hash($password, PASSWORD_DEFAULT);

$updateStmt = $pdo->prepare(
    'UPDATE users
     SET password_hash = ?, password_reset_code = NULL, password_reset_code_expires_at = NULL
     WHERE id = ?'
);
$updateStmt->execute([$newHash, $user['id']]);

header('Location: /login.php?reset=1');
exit;
