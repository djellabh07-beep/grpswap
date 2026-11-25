<?php
/**
 * set_public_language.php
 *
 * Lets a visitor switch language BEFORE logging in — login.php and
 * signup.php had no way to do this at all (only settings.php's
 * logged-in-only switcher existed). Sets $_SESSION['lang'] the same way
 * settings_update_language.php does for authenticated users, but
 * deliberately does NOT call require_login() and does NOT touch
 * users.preferred_language (there's no account yet to persist it to —
 * that happens naturally once they do sign up, since signup_action.php
 * already reads current_lang() for the new account's initial preference).
 *
 * GET-based (not POST+JSON like settings_update_language.php) since this
 * is a plain link/button on a pre-auth page, not an authenticated AJAX
 * call — simplest is a direct navigation.
 */

require_once __DIR__ . '/includes/auth.php'; // session_start()
require_once __DIR__ . '/includes/i18n.php'; // BASSIRA_SUPPORTED_LANGS

$lang     = $_GET['lang'] ?? '';
$redirect = $_GET['redirect'] ?? '/login.php';

if (!in_array($lang, BASSIRA_SUPPORTED_LANGS, true)) {
    $lang = 'ar';
}

// Same open-redirect protection as login_action.php — only ever bounce
// back to a local path.
if (!preg_match('#^/(?!/)#', $redirect)) {
    $redirect = '/login.php';
}

$_SESSION['lang'] = $lang;

header('Location: ' . $redirect);
exit;
