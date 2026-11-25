<?php
/**
 * signup.php
 *
 * Plain server-rendered registration form, mirroring login.php's
 * conventions exactly (same head/layout/design-system classes). Does NOT
 * call require_login() (that would redirect-loop) — instead, if a
 * session already exists, it bounces the visitor straight to index.php,
 * same as login.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

// Surface a specific signup error passed back from signup_action.php via
// query string, and re-populate the name/email fields the visitor already
// typed (never the password) so a failed attempt doesn't force retyping
// everything.
$signupError = $_GET['error'] ?? null;
$oldName     = $_GET['name'] ?? '';
$oldUsername = $_GET['username'] ?? '';
$oldEmail    = $_GET['email'] ?? '';
$oldRole     = $_GET['role'] ?? 'student';

$errorMessages = [
    'missing_fields'     => t('auth.signup_error_missing_fields'),
    'username_taken'     => t('auth.signup_error_username_taken'),
    'invalid_username'   => t('auth.signup_error_invalid_username'),
    'invalid_email'      => t('auth.signup_error_invalid_email'),
    'email_taken'        => t('auth.signup_error_email_taken'),
    'password_short'     => t('auth.signup_error_password_short'),
    'password_mismatch'  => t('auth.signup_error_password_mismatch'),
    'invalid_role'       => t('auth.signup_error_invalid_role'),
    'generic'            => t('auth.signup_error_generic'),
];
?>
<!DOCTYPE html>
<html dir="<?= current_dir() ?>" lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('auth.signup_title')) ?></title>
    <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-background text-on-surface <?= current_lang() === 'ar' ? 'font-arabic-body' : 'font-body-md' ?> p-6">

    <main class="w-full max-w-sm bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm">
        <h1 class="text-2xl font-bold mb-2 text-center">
            <?= htmlspecialchars(t('app.name')) ?>
        </h1>
        <div class="flex justify-center gap-3 mb-6 text-sm" role="group" aria-label="Language">
            <?php foreach (['ar', 'fr', 'en'] as $langOption): ?>
                    <a
                        href="/set_public_language.php?lang=<?= $langOption ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                    class="px-3 py-1 rounded-full <?= current_lang() === $langOption ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant' ?>"
                ><?= htmlspecialchars(t('lang.' . $langOption)) ?></a>
            <?php endforeach; ?>
        </div>


        <?php if ($signupError && isset($errorMessages[$signupError])): ?>
            <div role="alert" class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">
                <?= htmlspecialchars($errorMessages[$signupError]) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/signup_action.php" class="space-y-4" novalidate>

            <div>
                <label for="name" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.name_label')) ?>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    autocomplete="name"
                    aria-required="true"
                    value="<?= htmlspecialchars($oldName) ?>"
                    class="w-full rounded border-outline-variant p-2"
                >
            </div>

            <div>
                <label for="username" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.username_label')) ?>
                </label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    autocomplete="username"
                    aria-required="true"
                    pattern="[a-z0-9_]{3,30}"
                    value="<?= htmlspecialchars($oldUsername) ?>"
                    class="w-full rounded border-outline-variant p-2"
                >
            </div>

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
                <label for="password" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.password_label')) ?>
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                        class="w-full rounded border-outline-variant p-2 pe-10"
                    >
                    <button
                        type="button"
                        onclick="bassiraTogglePassword('password', this)"
                        class="absolute inset-y-0 end-0 flex items-center px-3 text-on-surface-variant"
                        aria-label="<?= htmlspecialchars(t('auth.show_password')) ?>"
                        aria-pressed="false"
                        data-label-show="<?= htmlspecialchars(t('auth.show_password')) ?>"
                        data-label-hide="<?= htmlspecialchars(t('auth.hide_password')) ?>"
                    ><svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0112 19c-7 0-11-7-11-7a21.8 21.8 0 015.06-5.94M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.7 21.7 0 01-2.16 3.19M14.12 14.12a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
                </div>
            </div>

            <div>
                <label for="password_confirm" class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.confirm_password_label')) ?>
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                        class="w-full rounded border-outline-variant p-2 pe-10"
                    >
                    <button
                        type="button"
                        onclick="bassiraTogglePassword('password_confirm', this)"
                        class="absolute inset-y-0 end-0 flex items-center px-3 text-on-surface-variant"
                        aria-label="<?= htmlspecialchars(t('auth.show_password')) ?>"
                        aria-pressed="false"
                        data-label-show="<?= htmlspecialchars(t('auth.show_password')) ?>"
                        data-label-hide="<?= htmlspecialchars(t('auth.hide_password')) ?>"
                    ><svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0112 19c-7 0-11-7-11-7a21.8 21.8 0 015.06-5.94M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.7 21.7 0 01-2.16 3.19M14.12 14.12a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
                </div>
            </div>

            <fieldset>
                <legend class="block mb-1 font-medium">
                    <?= htmlspecialchars(t('auth.role_label')) ?>
                </legend>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input
                            type="radio"
                            name="role"
                            value="student"
                            <?= $oldRole === 'student' ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars(t('auth.role_student')) ?>
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            type="radio"
                            name="role"
                            value="teacher"
                            <?= $oldRole === 'teacher' ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars(t('auth.role_teacher')) ?>
                    </label>
                </div>
            </fieldset>

            <button
                type="submit"
                class="w-full rounded bg-primary text-on-primary font-semibold p-3"
            >
                <?= htmlspecialchars(t('auth.signup_button')) ?>
            </button>
        </form>

        <p class="text-center text-sm mt-4">
            <a href="/login.php" class="underline">
                <?= htmlspecialchars(t('auth.login_link')) ?>
            </a>
        </p>
    </main>


<script>
function bassiraTogglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.setAttribute('aria-pressed', String(!showing));
    btn.setAttribute('aria-label', showing ? btn.dataset.labelShow : btn.dataset.labelHide);
    btn.querySelector('.eye-open').classList.toggle('hidden', !showing);
    btn.querySelector('.eye-closed').classList.toggle('hidden', showing);
}
</script>
</body>
</html>
