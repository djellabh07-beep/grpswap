<?php
/**
 * login_action.php
 *
 * Handles the POST from login.php. Never rendered directly / has no GET
 * form of its own — always redirects back to either login.php (on
 * failure), verify_email.php (on correct credentials but an unverified
 * account), or the intended page (on success).
 */

require_once __DIR__ . '/includes/auth.php'; // session_start()
require_once __DIR__ . '/includes/db.php';   // $pdo

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password   = (string) ($_POST['password'] ?? '');
$redirect = $_POST['redirect'] ?? '/index.php';

// Only ever redirect to a local path — never let an attacker-controlled
// redirect value send a successfully-authenticated user off-site (open
// redirect). A leading single slash (not "//", which browsers treat as
// protocol-relative to another host) is the only accepted shape.
if (!preg_match('#^/(?!/)#', $redirect)) {
    $redirect = '/index.php';
}

if ($identifier === '' || $password === '') {
    header('Location: /login.php?error=1&redirect=' . urlencode($redirect));
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name, role, password_hash, preferred_language, speech_rate, email_verified_at
     FROM users
     WHERE email = ? OR username = ?
     LIMIT 1'
);
$stmt->execute([$identifier, strtolower($identifier)]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: /login.php?error=1&redirect=' . urlencode($redirect));
    exit;
}

// Correct credentials, but the account was never verified (e.g. they
// closed the tab after signing up instead of entering the code) — send
// them to finish verification rather than logging in, same pending-state
// pattern signup_action.php uses.
if ($user['email_verified_at'] === null) {
    session_regenerate_id(true);
    $_SESSION['pending_verification_user_id'] = (int) $user['id'];
    header('Location: /verify_email.php');
    exit;
}

// Regenerate the session ID on privilege change (login) to prevent
// session fixation — the old session ID becomes invalid, a new one is
// issued, and session data carries over.
session_regenerate_id(true);

$_SESSION['user_id']     = (int) $user['id'];
$_SESSION['name']        = $user['name'];
$_SESSION['role']        = $user['role'];
$_SESSION['lang']        = $user['preferred_language'];
$_SESSION['speech_rate'] = (float) $user['speech_rate'];

header('Location: ' . $redirect);
exit;
