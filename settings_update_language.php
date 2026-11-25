<?php
/**
 * settings_update_language.php
 *
 * Shared by TWO callers:
 *   1. voice_assistant.js's global "غيّري اللغة إلى ..." command (from
 *      any page)
 *   2. settings.php's own visual language buttons (small inline fetch,
 *      see that file)
 *
 * Updates both users.preferred_language (persists across future logins)
 * and $_SESSION['lang'] (takes effect on this session immediately,
 * without needing a fresh login — the caller reloads the page afterward,
 * which is enough since every page's dir/nav labels/STT locale/TTS voice
 * are all derived from $_SESSION['lang'] via includes/i18n.php).
 * POST: lang (ar|fr|en).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'method_not_allowed');
}

$lang = (string) ($_POST['lang'] ?? '');

if (!in_array($lang, ['ar', 'fr', 'en'], true)) {
    json_fail(400, 'invalid_language');
}

$updateStmt = $pdo->prepare('UPDATE users SET preferred_language = ? WHERE id = ?');
$updateStmt->execute([$lang, current_user_id()]);

$_SESSION['lang'] = $lang;

echo json_encode(['ok' => true, 'lang' => $lang]);
