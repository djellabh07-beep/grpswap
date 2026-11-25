<?php
/**
 * reading_progress_update.php
 *
 * Small JSON endpoint called by assets/js/reader.js after each line is
 * spoken, so reopening the same material later resumes instead of
 * restarting. POST: material_id, page, line (all integers).
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

$materialId = (int) ($_POST['material_id'] ?? 0);
$page       = (int) ($_POST['page'] ?? 0);
$line       = (int) ($_POST['line'] ?? 0);

if ($materialId <= 0 || $page <= 0 || $line <= 0) {
    json_fail(400, 'invalid_input');
}

// The material must belong to a course the current student is actually
// enrolled in — never trust material_id blindly from the client.
$checkStmt = $pdo->prepare(
    'SELECT m.id
     FROM materials m
     JOIN enrollments e ON e.course_id = m.course_id
     WHERE m.id = ? AND e.student_id = ? AND m.deleted_at IS NULL'
);
$checkStmt->execute([$materialId, current_user_id()]);
if (!$checkStmt->fetch()) {
    json_fail(403, 'not_enrolled');
}

$upsertStmt = $pdo->prepare(
    'INSERT INTO reading_progress (student_id, material_id, last_page, last_line)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE last_page = VALUES(last_page), last_line = VALUES(last_line)'
);
$upsertStmt->execute([current_user_id(), $materialId, $page, $line]);

echo json_encode(['ok' => true]);
