<?php
/**
 * notes_update.php
 *
 * Overwrites a note's content. Used by assets/js/notes.js for the
 * "بصيرة عدّلي" command — by the time this endpoint is called, the
 * student has already dictated the replacement, heard it echoed back,
 * and explicitly confirmed it (that whole flow happens client-side in
 * notes.js; this endpoint just performs the already-confirmed write).
 * POST: note_id, content.
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

$noteId  = (int) ($_POST['note_id'] ?? 0);
$content = trim((string) ($_POST['content'] ?? ''));

if ($noteId <= 0 || $content === '') {
    json_fail(400, 'invalid_input');
}

// Ownership check — a note can only be edited by the student it belongs
// to. Title is deliberately left untouched: the spec describes this as
// replacing the note's CONTENT ("dictation mode for a replacement"), not
// re-titling it.
$updateStmt = $pdo->prepare(
    'UPDATE notes SET content = ?, updated_at = NOW() WHERE id = ? AND student_id = ?'
);
$updateStmt->execute([$content, $noteId, current_user_id()]);

if ($updateStmt->rowCount() === 0) {
    json_fail(403, 'not_owner_or_not_found');
}

echo json_encode(['ok' => true]);
