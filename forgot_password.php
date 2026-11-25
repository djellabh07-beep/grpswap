<?php
/**
 * forgot_password.php — step 1: request a reset code by email.
 *
 * Deliberately always shows the same "if that email exists, a code was
 * sent" message regardless of whether the email actually matches an
 * account — never reveals whether a given email is registered
 * (a classic account-enumeration protection).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$submitted = isset($_GET['sent']);
$submittedEmail = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html dir="<?= current_dir() ?>" lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('reset.request_title')) ?></title>
    <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-background text-on-surface <?= current_lang() === 'ar' ? 'font-arabic-body' : 'font-body-md' ?> p-6">

    <main class="w-full max-w-sm bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm">
        <h1 class="text-2xl font-bold mb-6 text-center">
            <?= htmlspecialchars(t('reset.request_title')) ?>
        </h1>

        <?php if ($submitted): ?>
            <div role="status" class="mb-4 p-3 rounded bg-secondary-container/40 text-on-secondary-container text-sm">
                <?= htmlspecialchars(t('reset.request_sent_notice')) ?>
            </div>
<a
                href="/reset_password.php?email=<?= urlencode($submittedEmail) ?>"
                class="block text-center w-full rounded bg-primary text-on-primary font-semibold p-3 mb-4"
            >
                <?= htmlspecialchars(t('reset.enter_code_button')) ?>
            </a>
        <?php else: ?>
            <p class="text-sm text-on-surface-variant mb-6"><?= htmlspecialchars(t('reset.request_instructions')) ?></p>
        <?php endif; ?>

        <form method="post" action="/forgot_password_action.php" class="space-y-4" novalidate>
            <div>
                <label for="email" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.email_label')) ?>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    aria-required="true"
                    class="w-full rounded border-outline-variant p-2"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded bg-primary text-on-primary font-semibold p-3"
            >
                <?= htmlspecialchars(t('reset.send_code_button')) ?>
            </button>
        </form>

        <p class="text-center text-sm mt-4">
            <a href="/login.php" class="underline">
                <?= htmlspecialchars(t('auth.login_link')) ?>
            </a>
        </p>
    </main>

</body>
</html>
