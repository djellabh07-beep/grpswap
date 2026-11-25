<?php
/**
 * messages_send_action.php
 *
 * Handles the teacher's plain typed compose form on messages.php — no
 * voice involved, no JSON/fetch, just a standard POST + redirect like
 * login_action.php or material_upload_action.php. Kept separate from
 * messages_send.php (the student's voice-driven JSON endpoint) because
 * the eligibility check runs in the opposite direction here: a teacher
 * may only message a student enrolled in one of THEIR OWN courses, not
 * the other way around.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

function redirect_with_error(string $message): void
{
    header('Location: /messages.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /messages.php');
    exit;
}

$studentId = (int) ($_POST['student_id'] ?? 0);
$body      = trim((string) ($_POST['body'] ?? ''));

if ($studentId <= 0) {
    redirect_with_error(t('messages.error_no_recipient'));
}
if ($body === '') {
    redirect_with_error(t('messages.error_no_body'));
}

// The student must actually be enrolled in one of this teacher's courses.
$checkStmt = $pdo->prepare(
    'SELECT 1
     FROM courses c
     JOIN enrollments e ON e.course_id = c.id
     WHERE c.teacher_id = ? AND e.student_id = ?
     LIMIT 1'
);
$checkStmt->execute([current_user_id(), $studentId]);
if (!$checkStmt->fetch()) {
    redirect_with_error(t('messages.error_not_your_student'));
}

$insertStmt = $pdo->prepare(
    'INSERT INTO messages (sender_id, recipient_id, body, sent_at) VALUES (?, ?, ?, NOW())'
);
$insertStmt->execute([current_user_id(), $studentId, $body]);

header('Location: /messages.php?sent=1');
exit;
