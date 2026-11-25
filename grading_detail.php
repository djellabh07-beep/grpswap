<?php
/**
 * grading_detail.php — view a single submission's answers and grade it.
 *
 * Ownership is checked via a chain: submission -> homework -> course ->
 * teacher_id, so a teacher can only ever see/grade submissions for their
 * own courses' homework.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$teacherId     = current_user_id();
$submissionId  = isset($_GET['submission']) ? (int) $_GET['submission'] : 0;

$subStmt = $pdo->prepare(
    "SELECT s.id, s.grade, s.feedback, s.submitted_at, u.name AS student_name,
            h.id AS homework_id, h.title AS homework_title, h.course_id
     FROM submissions s
     JOIN users u ON u.id = s.student_id
     JOIN homework h ON h.id = s.homework_id
     JOIN courses c ON c.id = h.course_id
     WHERE s.id = ? AND c.teacher_id = ? AND s.status = 'submitted'"
);
$subStmt->execute([$submissionId, $teacherId]);
$submission = $subStmt->fetch();

if (!$submission) {
    http_response_code(404);
    die(htmlspecialchars(t('grading.submission_not_found')));
}

$answersStmt = $pdo->prepare(
    'SELECT question_number, answer_text FROM submission_answers WHERE submission_id = ? ORDER BY question_number'
);
$answersStmt->execute([$submissionId]);
$answers = $answersStmt->fetchAll();

$flashSuccess = isset($_GET['graded']);
$flashError   = $_GET['error'] ?? null;

$page      = 'grading';
$pageTitle = t('grading.detail_title');
require __DIR__ . '/includes/header.php';
?>
<script>window.pageVoiceCommands = {};</script>

<div class="max-w-2xl mx-auto p-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-primary mb-2">
        <?= htmlspecialchars($submission['homework_title']) ?>
    </h1>
    <p class="text-on-surface-variant mb-8">
        <?= htmlspecialchars(t('grading.student_label')) ?> <?= htmlspecialchars($submission['student_name']) ?>
    </p>

    <?php if ($flashSuccess): ?>
        <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
            <?= htmlspecialchars(t('grading.save_success')) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <div class="space-y-4 mb-10">
        <?php if (!$answers): ?>
            <p class="text-on-surface-variant"><?= htmlspecialchars(t('grading.no_answers')) ?></p>
        <?php else: ?>
            <?php foreach ($answers as $answer): ?>
                <div class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/10 shadow-sm">
                    <p class="font-medium mb-1">
                        <?= htmlspecialchars(t('grading.question_label', [$answer['question_number']])) ?>
                    </p>
                    <p class="text-on-surface-variant whitespace-pre-wrap"><?= htmlspecialchars($answer['answer_text']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form method="post" action="/grading_action.php" class="space-y-4">
        <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">

        <div>
            <label for="grade" class="block mb-2 font-medium"><?= htmlspecialchars(t('grading.grade_label')) ?></label>
            <input
                type="number"
                id="grade"
                name="grade"
                step="0.01"
                min="0"
                max="100"
                value="<?= $submission['grade'] !== null ? htmlspecialchars((string) $submission['grade']) : '' ?>"
                required
                class="w-full rounded border-outline-variant p-3"
            >
        </div>

        <div>
            <label for="feedback" class="block mb-2 font-medium"><?= htmlspecialchars(t('grading.feedback_label')) ?></label>
            <textarea id="feedback" name="feedback" rows="4" class="w-full rounded border-outline-variant p-3"><?= htmlspecialchars((string) ($submission['feedback'] ?? '')) ?></textarea>
        </div>

        <button type="submit" class="rounded bg-primary text-on-primary font-semibold px-6 py-3">
            <?= htmlspecialchars(t('grading.save_button')) ?>
        </button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
