<?php
/**
 * signup_action.php
 *
 * Handles the POST from signup.php. Never rendered directly / has no GET
 * form of its own. On failure, redirects back to signup.php with a
 * specific error code + the non-sensitive fields the visitor already
 * typed. On success, does NOT auto-login — instead sends a 6-digit
 * verification code by email and redirects to verify_email.php, since
 * the account is now real and used by real university students, not
 * just demo/test data.
 */

require_once __DIR__ . '/includes/auth.php'; // session_start()
require_once __DIR__ . '/includes/db.php';   // $pdo
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/mail.php';

function signup_fail(string $errorCode, string $name = '', string $email = '', string $role = 'student'): void
{
    $query = http_build_query([
        'error' => $errorCode,
        'name'  => $name,
        'email' => $email,
        'role'  => $role,
    ]);
    header('Location: /signup.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /signup.php');
    exit;
}

$name            = trim($_POST['name'] ?? '');
$username        = strtolower(trim($_POST['username'] ?? ''));
$email           = trim($_POST['email'] ?? '');
$password        = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$role            = $_POST['role'] ?? '';

if ($name === '' || $username === '' || $email === '' || $password === '' || $passwordConfirm === '' || $role === '') {
    signup_fail('missing_fields', $name, $email, $role);
}

if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
    signup_fail('invalid_username', $name, $email, $role);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    signup_fail('invalid_email', $name, $email, $role);
}

if (!in_array($role, ['student', 'teacher'], true)) {
    signup_fail('invalid_role', $name, $email, $role);
}

// 8 characters minimum — a reasonable floor without being so strict it
// becomes another accessibility obstacle (no forced special-character/
// digit rules, which are painful to satisfy by voice/dictation anyway).
if (strlen($password) < 8) {
    signup_fail('password_short', $name, $email, $role);
}

if ($password !== $passwordConfirm) {
    signup_fail('password_mismatch', $name, $email, $role);
}

$existingEmailStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$existingEmailStmt->execute([$email]);
if ($existingEmailStmt->fetch()) {
    signup_fail('email_taken', $name, $email, $role);
}

$existingUsernameStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$existingUsernameStmt->execute([$username]);
if ($existingUsernameStmt->fetch()) {
    signup_fail('username_taken', $name, $email, $role);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$code         = bassira_generate_code();
// 15-minute validity window for the code.
$codeExpiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

try {
    $insertStmt = $pdo->prepare(
        'INSERT INTO users
            (role, name, username, email, password_hash, preferred_language, speech_rate,
             email_verified_at, verification_code, verification_code_expires_at)
         VALUES (?, ?, ?, ?, ?, ?, 1.00, NULL, ?, ?)'
    );
    // New account's language defaults to whatever locale they were
    // signing up in (current_lang()), not hardcoded 'ar' — so a visitor
    // who switched to French/English before signing up doesn't get
    // flipped back to Arabic, including in the verification email below.
    $insertStmt->execute([$role, $name, $username, $email, $passwordHash, current_lang(), $code, $codeExpiresAt]);
} catch (\Throwable $e) {
    // Covers a race condition on the email uniqueness check above (two
    // signups for the same email within the same instant) and any other
    // unexpected DB failure — never leak DB internals to the visitor.
    signup_fail('generic', $name, $email, $role);
}

$newUserId = (int) $pdo->lastInsertId();

$emailSent = bassira_send_email(
    $email,
    $name,
    t('verify.email_subject'),
    '<p>' . htmlspecialchars(t('verify.email_body_greeting', [$name])) . '</p>'
    . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($code) . '</p>'
    . '<p>' . htmlspecialchars(t('verify.email_body_expiry')) . '</p>'
);

// Not logged in yet — only a lightweight "which account is pending
// verification" marker, checked by verify_email.php. Deliberately NOT
// the same as a real authenticated session (no role/name/etc set) so a
// pending-verification visitor can't reach any protected page by
// guessing a URL before completing verification.
session_regenerate_id(true);
$_SESSION['pending_verification_user_id'] = $newUserId;

header('Location: /verify_email.php' . ($emailSent ? '' : '?mail_failed=1'));
exit;
