<?php
/**
 * verify_email.php
 *
 * Shown right after signup (and re-shown to an unverified user who tries
 * to log in — see login_action.php). Requires
 * $_SESSION['pending_verification_user_id'] to be set by signup_action.php
 * or login_action.php; a visitor arriving here with neither that nor a
 * full session is sent back to signup.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$pendingUserId = $_SESSION['pending_verification_user_id'] ?? null;

if (!$pendingUserId) {
    header('Location: /signup.php');
    exit;
}

$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$pendingUserId]);
$pendingUser = $stmt->fetch();

if (!$pendingUser) {
    // The pending account no longer exists (shouldn't normally happen) —
    // clear the stale marker rather than showing a dead-end form.
    unset($_SESSION['pending_verification_user_id']);
    header('Location: /signup.php');
    exit;
}

$verifyError = $_GET['error'] ?? null;
$mailFailed  = isset($_GET['mail_failed']);
$resent      = isset($_GET['resent']);

$errorMessages = [
    'invalid_code' => t('verify.error_invalid_code'),
    'expired_code' => t('verify.error_expired_code'),
    'missing_code' => t('verify.error_missing_code'),
];
?>
<!DOCTYPE html>
<html dir="<?= current_dir() ?>" lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('verify.page_title')) ?></title>
    <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-background text-on-surface <?= current_lang() === 'ar' ? 'font-arabic-body' : 'font-body-md' ?> p-6">

    <main class="w-full max-w-sm bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm">
        <h1 class="text-2xl font-bold mb-4 text-center">
            <?= htmlspecialchars(t('verify.page_title')) ?>
        </h1>

        <p class="text-center text-sm mb-6 text-on-surface-variant">
            <?= htmlspecialchars(t('verify.instructions', [$pendingUser['email']])) ?>
        </p>

        <?php if ($mailFailed): ?>
            <div role="alert" class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">
                <?= htmlspecialchars(t('verify.mail_failed_notice')) ?>
            </div>
        <?php endif; ?>

        <?php if ($resent): ?>
            <div role="status" class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">
                <?= htmlspecialchars(t('verify.code_resent_notice')) ?>
            </div>
        <?php endif; ?>

        <?php if ($verifyError && isset($errorMessages[$verifyError])): ?>
            <div role="alert" class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">
                <?= htmlspecialchars($errorMessages[$verifyError]) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/verify_email_action.php" class="space-y-4" novalidate>
            <div>
                <label for="code" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('verify.code_label')) ?>
                </label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    required
                    aria-required="true"
                    class="w-full rounded border-outline-variant p-3 text-center text-2xl tracking-widest"
                    autofocus
                >
            </div>

            <button
                type="submit"
                class="w-full rounded bg-primary text-on-primary font-semibold p-3"
            >
                <?= htmlspecialchars(t('verify.confirm_button')) ?>
            </button>
        </form>

        <form method="post" action="/verify_email_resend.php" class="mt-3">
            <button type="submit" class="w-full text-sm underline text-on-surface-variant">
                <?= htmlspecialchars(t('verify.resend_link')) ?>
            </button>
        </form>
    </main>

</body>
</html>
