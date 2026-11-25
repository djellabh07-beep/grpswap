<?php
/**
 * submission_answer_save.php
 *
 * "بصيرة الإجابة على السؤال [رقم] هي: ..." — the entire question number
 * and answer text arrive together (parsed client-side from one spoken
 * utterance, see assets/js/homework.js), already confirmed by the voice
 * engine's standard destructive flow before this endpoint is ever called.
 * POST: homework_id, question_number, answer_text.
 *
 * Rejects with a specific 'already_submitted' error if the submission has
 * already been finalized — per spec, further answer edits after
 * submission must be refused, not silently allowed.
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
$answerText     = trim((string) ($_POST['answer_text'] ?? ''));

if ($homeworkId <= 0 || $questionNumber <= 0 || $answerText === '') {
    json_fail(400, 'invalid_input');
}

$submission = bassira_get_or_create_submission($pdo, $homeworkId, current_user_id());
if (!$submission) {
    json_fail(403, 'not_enrolled');
}

if ($submission['status'] === 'submitted') {
    json_fail(409, 'already_submitted');
}

$upsertStmt = $pdo->prepare(
    'INSERT INTO submission_answers (submission_id, question_number, answer_text, updated_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), updated_at = NOW()'
);
$upsertStmt->execute([$submission['id'], $questionNumber, $answerText]);

echo json_encode(['ok' => true]);
