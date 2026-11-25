<?php
/**
 * notes_save.php
 *
 * Small JSON endpoint called by assets/js/reader.js for the "بصيرة خذي
 * ملاحظة" dictation command. POST: course_id, content. The note is
 * auto-titled with the course's name and marked source='voice', per
 * schema.sql. Returns {ok:true, id} on success.
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

$courseId = (int) ($_POST['course_id'] ?? 0);
$content  = trim((string) ($_POST['content'] ?? ''));

if ($courseId <= 0 || $content === '') {
    json_fail(400, 'invalid_input');
}

// The course must be one the student is actually enrolled in.
$courseStmt = $pdo->prepare(
    'SELECT c.name
     FROM courses c
     JOIN enrollments e ON e.course_id = c.id
     WHERE c.id = ? AND e.student_id = ?'
);
$courseStmt->execute([$courseId, current_user_id()]);
$course = $courseStmt->fetch();

if (!$course) {
    json_fail(403, 'not_enrolled');
}

$insertStmt = $pdo->prepare(
    "INSERT INTO notes (course_id, student_id, title, content, source, created_at)
     VALUES (?, ?, ?, ?, 'voice', NOW())"
);
$insertStmt->execute([$courseId, current_user_id(), $course['name'], $content]);

echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
