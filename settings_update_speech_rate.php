<?php
/**
 * settings_update_speech_rate.php
 *
 * Called from settings.php's speech rate control. Persists to
 * users.speech_rate (so it's remembered on future logins) and updates
 * $_SESSION['speech_rate'] (so header.php's window.BASSIRA_SPEECH_RATE
 * picks it up correctly on any page load for the rest of this session,
 * without needing a fresh login) — but the CALLER is responsible for
 * also setting window.BASSIRA_SPEECH_RATE directly, client-side, since
 * "affects speechSynthesis rate for all future Bassira speech this
 * session" means it must take effect immediately, not just after the
 * next page load.
 * POST: rate (a number, clamped to a sane 0.5–2.0 range).
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

$rate = filter_var($_POST['rate'] ?? null, FILTER_VALIDATE_FLOAT);

if ($rate === false || $rate === null || $rate < 0.5 || $rate > 2.0) {
    json_fail(400, 'invalid_rate');
}

$updateStmt = $pdo->prepare('UPDATE users SET speech_rate = ? WHERE id = ?');
$updateStmt->execute([$rate, current_user_id()]);

$_SESSION['speech_rate'] = $rate;

echo json_encode(['ok' => true, 'rate' => $rate]);
