<?php
/**
 * messages_mark_read.php
 *
 * Called once per message by assets/js/messages.js as it reads each
 * unread message aloud in order, per "marking each read_at as it's
 * read". POST: message_id.
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

$messageId = (int) ($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    json_fail(400, 'invalid_input');
}

// A student can only mark their OWN received messages as read.
$updateStmt = $pdo->prepare(
    'UPDATE messages SET read_at = NOW() WHERE id = ? AND recipient_id = ? AND read_at IS NULL'
);
$updateStmt->execute([$messageId, current_user_id()]);

echo json_encode(['ok' => true]);
