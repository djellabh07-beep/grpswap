<?php
/**
 * verify_email_resend.php
 *
 * Handles the POST from verify_email.php's "resend code" button.
 * Generates a fresh code + expiry (invalidating the old one) and
 * re-sends the verification email.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/mail.php';

$pendingUserId = $_SESSION['pending_verification_user_id'] ?? null;

if (!$pendingUserId) {
    header('Location: /signup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /verify_email.php');
    exit;
}

$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_verification_user_id']);
    header('Location: /signup.php');
    exit;
}

$code          = bassira_generate_code();
$codeExpiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

$updateStmt = $pdo->prepare(
    'UPDATE users SET verification_code = ?, verification_code_expires_at = ? WHERE id = ?'
);
$updateStmt->execute([$code, $codeExpiresAt, $pendingUserId]);

$emailSent = bassira_send_email(
    $user['email'],
    $user['name'],
    t('verify.email_subject'),
    '<p>' . htmlspecialchars(t('verify.email_body_greeting', [$user['name']])) . '</p>'
    . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($code) . '</p>'
    . '<p>' . htmlspecialchars(t('verify.email_body_expiry')) . '</p>'
);

header('Location: /verify_email.php' . ($emailSent ? '?resent=1' : '?mail_failed=1'));
exit;
