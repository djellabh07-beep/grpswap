<?php
/**
 * messages_send.php
 *
 * "بصيرة أرسلي رسالة إلى [اسم الأستاذ]" — by the time this endpoint is
 * called, assets/js/messages.js has already dictated the body, echoed it
 * back, and gotten an explicit spoken "نعم" confirmation (see that file's
 * top comment for why this uses a manual confirm sequence rather than the
 * engine's `destructive: true` flag). This endpoint just performs the
 * already-confirmed send.
 * POST: recipient_id, body.
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

$recipientId = (int) ($_POST['recipient_id'] ?? 0);
$body        = trim((string) ($_POST['body'] ?? ''));

if ($recipientId <= 0 || $body === '') {
    json_fail(400, 'invalid_input');
}

// A student may only message a teacher of a course they're actually
// enrolled in — never trust recipient_id blindly.
$checkStmt = $pdo->prepare(
    'SELECT 1
     FROM courses c
     JOIN enrollments e ON e.course_id = c.id
     WHERE c.teacher_id = ? AND e.student_id = ?
     LIMIT 1'
);
$checkStmt->execute([$recipientId, current_user_id()]);
if (!$checkStmt->fetch()) {
    json_fail(403, 'not_your_teacher');
}

$insertStmt = $pdo->prepare(
    'INSERT INTO messages (sender_id, recipient_id, body, sent_at) VALUES (?, ?, ?, NOW())'
);
$insertStmt->execute([current_user_id(), $recipientId, $body]);

echo json_encode(['ok' => true]);
