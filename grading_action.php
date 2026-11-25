<?php
/**
 * grading_action.php
 *
 * Handles the POST from grading_detail.php's grade form. Same
 * ownership-chain check as grading_detail.php (submission -> homework
 * -> course -> teacher_id).
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /grading.php');
    exit;
}

$teacherId    = current_user_id();
$submissionId = (int) ($_POST['submission_id'] ?? 0);
$backTo       = '/grading_detail.php?submission=' . $submissionId;

$ownershipStmt = $pdo->prepare(
    "SELECT s.id
     FROM submissions s
     JOIN homework h ON h.id = s.homework_id
     JOIN courses c ON c.id = h.course_id
     WHERE s.id = ? AND c.teacher_id = ? AND s.status = 'submitted'"
);
$ownershipStmt->execute([$submissionId, $teacherId]);
if (!$ownershipStmt->fetch()) {
    http_response_code(404);
    die(htmlspecialchars(t('grading.submission_not_found')));
}

$gradeRaw = $_POST['grade'] ?? '';
$feedback = trim($_POST['feedback'] ?? '');

$grade = filter_var($gradeRaw, FILTER_VALIDATE_FLOAT);
if ($grade === false || $grade < 0 || $grade > 100) {
    header('Location: ' . $backTo . '&error=' . urlencode(t('grading.error_invalid_grade')));
    exit;
}

$updateStmt = $pdo->prepare(
    'UPDATE submissions SET grade = ?, feedback = ?, graded_at = NOW() WHERE id = ?'
);
$updateStmt->execute([$grade, $feedback !== '' ? $feedback : null, $submissionId]);

header('Location: ' . $backTo . '&graded=1');
exit;
