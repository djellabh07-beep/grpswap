<?php
/**
 * notes_delete.php
 *
 * Deletes a note. Used by assets/js/notes.js for the "بصيرة احذفي هذه
 * الملاحظة" command. This is a plain hard DELETE, not a soft delete —
 * per schema.sql's project-wide decision, single-row student-owned
 * content deleted via voice already passes through the engine's own
 * echo -> "قولي 'نعم' للتأكيد" -> confirm flow BEFORE this endpoint is
 * ever called, so that confirmation step is the safety net; there's no
 * need for a second recovery layer (deleted_at) on top of it.
 * POST: note_id.
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

$noteId = (int) ($_POST['note_id'] ?? 0);

if ($noteId <= 0) {
    json_fail(400, 'invalid_input');
}

$deleteStmt = $pdo->prepare('DELETE FROM notes WHERE id = ? AND student_id = ?');
$deleteStmt->execute([$noteId, current_user_id()]);

if ($deleteStmt->rowCount() === 0) {
    json_fail(403, 'not_owner_or_not_found');
}

echo json_encode(['ok' => true]);
