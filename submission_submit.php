<?php
/**
 * submission_submit.php
 *
 * "بصيرة سلّمي الواجب" — already confirmed by the voice engine's standard
 * destructive flow before this endpoint is called. Flips the submission's
 * status to 'submitted' and stamps submitted_at. From this point on,
 * submission_answer_save.php / submission_answer_delete.php both reject
 * further writes to this submission with 'already_submitted'.
 *
 * Idempotent on purpose: calling this again on an already-submitted
 * homework does not error — it returns {ok:true, already:true} so the
 * client can speak the "already submitted" message rather than a
 * confusing failure, in case of a double-tap/double-say or a stale page.
 * POST: homework_id.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/submissions.php';

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

$homeworkId = (int) ($_POST['homework_id'] ?? 0);

if ($homeworkId <= 0) {
    json_fail(400, 'invalid_input');
}

$submission = bassira_get_or_create_submission($pdo, $homeworkId, current_user_id());
if (!$submission) {
    json_fail(403, 'not_enrolled');
}

if ($submission['status'] === 'submitted') {
    echo json_encode(['ok' => true, 'already' => true]);
    exit;
}

$updateStmt = $pdo->prepare(
    "UPDATE submissions SET status = 'submitted', submitted_at = NOW() WHERE id = ?"
);
$updateStmt->execute([$submission['id']]);

echo json_encode(['ok' => true, 'already' => false]);
