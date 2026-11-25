<?php
/**
 * includes/auth.php
 *
 * Session bootstrap + auth guards. Include this at the very top of every
 * protected page, before any HTML output:
 *
 *   require_once __DIR__ . '/includes/auth.php';
 *   require_login();               // any logged-in user
 *   // or, for teacher-only pages (material/homework upload, teacher inbox):
 *   require_role('teacher');       // implies require_login()
 *
 * login.php itself should NOT call require_login() (that would redirect-
 * loop), but it does include this file for session_start() + helpers like
 * is_logged_in() to redirect an already-logged-in visitor away from the
 * login form.
 */

// session_start() must run before any output. This file is meant to be
// the very first require in every page for exactly that reason.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * True if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login.php (with a return-to hint) unless a user is logged
 * in. Call at the top of every protected page.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? '/index.php');
        header('Location: /login.php?redirect=' . $return);
        exit;
    }
}

/**
 * Require both login AND a specific role (e.g. 'teacher'). Used to gate
 * teacher-only pages/actions: material upload, homework upload, the
 * teacher-side typed message inbox.
 *
 * On a logged-in user with the wrong role, this does NOT bounce them to
 * login.php (they're already authenticated) — it responds 403, since
 * sending them back into the login form would be confusing and wrong.
 */
function require_role(string $role): void
{
    require_login();

    if (($_SESSION['role'] ?? null) !== $role) {
        http_response_code(403);
        die('عذراً، لا تملك صلاحية الوصول إلى هذه الصفحة.');
    }
}

/**
 * Convenience getters used throughout the app (nav.php greeting, page
 * logic that needs to know who's asking, etc.)
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_name(): ?string
{
    return $_SESSION['name'] ?? null;
}
