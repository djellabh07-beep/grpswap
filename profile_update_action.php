<?php
/**
 * profile_update_action.php
 *
 * Handles the POST from settings.php's Account section. Current
 * password is required whenever email OR password is being changed
 * (not required for a name-only change) — a real security boundary,
 * not just a UX nag, since email/password are the account's actual
 * access credentials.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

function profile_fail(string $errorCode): void
{
    header('Location: /settings.php?error=' . urlencode($errorCode));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /settings.php');
    exit;
}

$userId = current_user_id();

$name                = trim($_POST['name'] ?? '');
$email               = trim($_POST['email'] ?? '');
$newPassword         = (string) ($_POST['new_password'] ?? '');
$newPasswordConfirm  = (string) ($_POST['new_password_confirm'] ?? '');
$currentPassword     = (string) ($_POST['current_password'] ?? '');

if ($name === '') {
    profile_fail('missing_name');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    profile_fail('invalid_email');
}

$stmt = $pdo->prepare('SELECT email, password_hash FROM users WHERE id = ?');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$emailChanging    = ($email !== $currentUser['email']);
$passwordChanging = ($newPassword !== '' || $newPasswordConfirm !== '');

if ($emailChanging || $passwordChanging) {
    if ($currentPassword === '') {
        profile_fail('current_password_required');
    }
    if (!password_verify($currentPassword, $currentUser['password_hash'])) {
        profile_fail('current_password_wrong');
    }
}

if ($emailChanging) {
    $existingStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $existingStmt->execute([$email, $userId]);
    if ($existingStmt->fetch()) {
        profile_fail('email_taken');
    }
}

if ($passwordChanging) {
    if (strlen($newPassword) < 8) {
        profile_fail('password_short');
    }
    if ($newPassword !== $newPasswordConfirm) {
        profile_fail('password_mismatch');
    }
}

if ($passwordChanging) {
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?');
    $updateStmt->execute([$name, $email, $newHash, $userId]);
} else {
    $updateStmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $updateStmt->execute([$name, $email, $userId]);
}

$_SESSION['name'] = $name;

header('Location: /settings.php?saved=1');
exit;
