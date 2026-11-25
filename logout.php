<?php
/**
 * logout.php
 *
 * Destroys the session entirely and sends the visitor back to the login
 * form. Accessible via a simple link/button — no confirmation needed here
 * at the HTTP layer (Bassira's own spoken logout command, if added later,
 * would carry its own voice confirmation before hitting this endpoint).
 */

require_once __DIR__ . '/includes/auth.php'; // session_start()

// Clear all session data.
$_SESSION = [];

// Also remove the session cookie itself, not just the server-side data —
// belt-and-suspenders so a stale cookie can't be reused to resurrect
// $_SESSION contents if session storage is ever misconfigured.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /login.php');
exit;
