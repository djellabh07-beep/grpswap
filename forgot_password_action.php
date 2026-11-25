<?php
/**
 * forgot_password_action.php
 *
 * Handles the POST from forgot_password.php. Always redirects to the
 * same "sent" state regardless of whether the email matched an account
 * — see forgot_password.php's comment on account-enumeration protection.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $code          = bassira_generate_code();
        $codeExpiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

        $updateStmt = $pdo->prepare(
            'UPDATE users SET password_reset_code = ?, password_reset_code_expires_at = ? WHERE id = ?'
        );
        $updateStmt->execute([$code, $codeExpiresAt, $user['id']]);

        bassira_send_email(
            $email,
            $user['name'],
            t('reset.email_subject'),
            '<p>' . htmlspecialchars(t('reset.email_body_greeting', [$user['name']])) . '</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($code) . '</p>'
            . '<p>' . htmlspecialchars(t('reset.email_body_expiry')) . '</p>'
        );
    }
    // No account matched: do nothing, but still show the same "sent"
    // page — this is intentional (see file header comment).
}

header('Location: /forgot_password.php?sent=1&email=' . urlencode($email));
exit;
