<?php
/**
 * verify_email_action.php
 *
 * Handles the POST from verify_email.php's code form. On success,
 * marks the account verified and completes the login (same session
 * fields as login_action.php/signup_action.php's auto-login used to
 * set) — this IS the moment a newly-signed-up user becomes fully
 * logged in for the first time.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$pendingUserId = $_SESSION['pending_verification_user_id'] ?? null;

if (!$pendingUserId) {
    header('Location: /signup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /verify_email.php');
    exit;
}

$submittedCode = trim($_POST['code'] ?? '');

if ($submittedCode === '') {
    header('Location: /verify_email.php?error=missing_code');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name, role, preferred_language, speech_rate,
            verification_code, verification_code_expires_at
     FROM users
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_verification_user_id']);
    header('Location: /signup.php');
    exit;
}

// Constant-time comparison — a verification code is a short-lived secret
// the same way a password is, even though it's only 6 digits.
if (!hash_equals((string) $user['verification_code'], $submittedCode)) {
    header('Location: /verify_email.php?error=invalid_code');
    exit;
}

if (strtotime($user['verification_code_expires_at']) < time()) {
    header('Location: /verify_email.php?error=expired_code');
    exit;
}

$updateStmt = $pdo->prepare(
    'UPDATE users
     SET email_verified_at = NOW(), verification_code = NULL, verification_code_expires_at = NULL
     WHERE id = ?'
);
$updateStmt->execute([$pendingUserId]);

unset($_SESSION['pending_verification_user_id']);

// Regenerate the session ID on privilege change (pending → fully
// authenticated), same as login_action.php.
session_regenerate_id(true);

$_SESSION['user_id']     = (int) $user['id'];
$_SESSION['name']        = $user['name'];
$_SESSION['role']        = $user['role'];
$_SESSION['lang']        = $user['preferred_language'];
$_SESSION['speech_rate'] = (float) $user['speech_rate'];

header('Location: /index.php');
exit;
