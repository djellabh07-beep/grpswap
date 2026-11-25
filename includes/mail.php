<?php
/**
 * includes/mail.php
 *
 * Shared email-sending helper for the whole app (email verification codes,
 * password reset codes). Uses Gmail's SMTP relay via an App Password —
 * free, no card required, good enough volume for a university-scale app.
 * Requires PHPMailer (installed via `composer require phpmailer/phpmailer`).
 *
 * Usage from any page:
 *   require_once __DIR__ . '/mail.php';
 *   $sent = bassira_send_email($toAddress, $subject, $bodyHtml);
 *
 * Connection settings, same pattern as db.php: prefer environment
 * variables in production, fall back to local defaults for development.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ---------------------------------------------------------------------
// Gmail SMTP settings. BASSIRA_MAIL_PASS is an App Password (16
// characters, generated at myaccount.google.com/apppasswords) — NOT the
// normal Gmail account password. Requires 2-Step Verification to be
// enabled on the sending account.
// ---------------------------------------------------------------------
$BASSIRA_MAIL_HOST = getenv('BASSIRA_MAIL_HOST') ?: 'smtp.gmail.com';
$BASSIRA_MAIL_PORT = getenv('BASSIRA_MAIL_PORT') ?: 587;
$BASSIRA_MAIL_USER = getenv('BASSIRA_MAIL_USER') ?: 'djellabh07@gmail.com';
$BASSIRA_MAIL_PASS = getenv('BASSIRA_MAIL_PASS') ?: 'suufobbhbngkmjgr';

/**
 * Sends an HTML email via Gmail SMTP. Returns true on success, false on
 * failure — callers should treat false as "couldn't send" and respond
 * accordingly (e.g. show a generic "try again" error), never expose raw
 * SMTP error details to the visitor.
 */
function bassira_send_email(string $toAddress, string $toName, string $subject, string $bodyHtml): bool
{
    global $BASSIRA_MAIL_HOST, $BASSIRA_MAIL_PORT, $BASSIRA_MAIL_USER, $BASSIRA_MAIL_PASS;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $BASSIRA_MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $BASSIRA_MAIL_USER;
        $mail->Password   = $BASSIRA_MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $BASSIRA_MAIL_PORT;
        $mail->CharSet    = 'UTF-8'; // required for Arabic/French subject+body text

        $mail->setFrom($BASSIRA_MAIL_USER, 'Bassira Hall');
        $mail->addAddress($toAddress, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[Bassira] Mail send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generates a random 6-digit numeric verification code, as a
 * zero-padded string (e.g. "042917") — used for both email
 * verification and password reset. Zero-padded so it always reads as 6
 * spoken/typed digits, never a shorter number due to a leading zero
 * being dropped.
 */
function bassira_generate_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
