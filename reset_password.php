<?php
/**
 * reset_password.php — step 2: enter the emailed code + set a new
 * password.
 *
 * Asks for email again (not just the code) since forgot_password.php is
 * intentionally stateless — no session marker is set there, to avoid
 * leaking which email exists via session behavior differences.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$resetError = $_GET['error'] ?? null;
$oldEmail   = $_GET['email'] ?? '';

$errorMessages = [
    'invalid_code'        => t('reset.error_invalid_code'),
    'expired_code'        => t('reset.error_expired_code'),
    'password_short'      => t('auth.signup_error_password_short'),
    'password_mismatch'   => t('auth.signup_error_password_mismatch'),
    'missing_fields'      => t('auth.signup_error_missing_fields'),
];
?>
<!DOCTYPE html>
<html dir="<?= current_dir() ?>" lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('reset.reset_title')) ?></title>
    <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-background text-on-surface <?= current_lang() === 'ar' ? 'font-arabic-body' : 'font-body-md' ?> p-6">

    <main class="w-full max-w-sm bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm">
        <h1 class="text-2xl font-bold mb-6 text-center">
            <?= htmlspecialchars(t('reset.reset_title')) ?>
        </h1>

        <?php if ($resetError && isset($errorMessages[$resetError])): ?>
            <div role="alert" class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">
                <?= htmlspecialchars($errorMessages[$resetError]) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/reset_password_action.php" class="space-y-4" novalidate>
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
                    value="<?= htmlspecialchars($oldEmail) ?>"
                    class="w-full rounded border-outline-variant p-2"
                >
            </div>

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
                >
            </div>

            <div>
                <label for="password" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('reset.new_password_label')) ?>
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    aria-required="true"
                    class="w-full rounded border-outline-variant p-2"
                >
            </div>

            <div>
                <label for="password_confirm" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.confirm_password_label')) ?>
                </label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    autocomplete="new-password"
                    aria-required="true"
                    class="w-full rounded border-outline-variant p-2"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded bg-primary text-on-primary font-semibold p-3"
            >
                <?= htmlspecialchars(t('reset.reset_button')) ?>
            </button>
        </form>

        <p class="text-center text-sm mt-4">
            <a href="/forgot_password.php" class="underline">
                <?= htmlspecialchars(t('reset.resend_link')) ?>
            </a>
        </p>
    </main>

</body>
</html>
