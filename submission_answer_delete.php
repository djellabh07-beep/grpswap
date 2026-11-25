<?php
/**
 * submission_answer_delete.php
 *
 * "بصيرة احذفي إجابة السؤال [رقم]" — already confirmed by the voice
 * engine's standard destructive flow before this endpoint is called.
 * POST: homework_id, question_number.
 *
 * Rejects with 'already_submitted' if the submission has been finalized,
 * and with 'no_answer' if there was never an answer for that question
 * number to begin with (distinct error so the client can speak the right
 * message rather than a generic failure).
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

$homeworkId     = (int) ($_POST['homework_id'] ?? 0);
$questionNumber = (int) ($_POST['question_number'] ?? 0);

if ($homeworkId <= 0 || $questionNumber <= 0) {
    json_fail(400, 'invalid_input');
}

$submission = bassira_get_or_create_submission($pdo, $homeworkId, current_user_id());
if (!$submission) {
    json_fail(403, 'not_enrolled');
}

if ($submission['status'] === 'submitted') {
    json_fail(409, 'already_submitted');
}

$deleteStmt = $pdo->prepare(
    'DELETE FROM submission_answers WHERE submission_id = ? AND question_number = ?'
);
$deleteStmt->execute([$submission['id'], $questionNumber]);

if ($deleteStmt->rowCount() === 0) {
    json_fail(404, 'no_answer');
}

echo json_encode(['ok' => true]);
