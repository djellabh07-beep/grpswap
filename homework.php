<?php
/**
 * homework.php
 *
 * Dual-mode, like courses.php/reader.php combined into one file since
 * this prompt only asked for a single homework.php:
 *   - No ?homework= param: lists the student's homework across enrolled
 *     courses, grouped by course, each linking to itself with the id.
 *   - ?homework=ID: the actual assignment reading + answering experience,
 *     reusing assets/js/reading_engine.js — the SAME engine reader.php
 *     uses for materials (stop/resume/go-to-page/previous-line) — plus
 *     assignment-specific commands layered on top by assets/js/homework.js.
 *
 * See assets/js/homework.js's top comment for why the answer/delete-
 * answer/submit commands use the engine's standard `destructive: true`
 * flag directly, unlike notes.php's "عدّلي" which needed a custom
 * dictate-then-confirm sequence.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$studentId  = current_user_id();
$homeworkId = (int) ($_GET['homework'] ?? 0);
$page       = 'homework';

// =====================================================================
// Mode 1: no ?homework= — listing across enrolled courses
// =====================================================================
if ($homeworkId <= 0) {
    $listStmt = $pdo->prepare(
        'SELECT h.id, h.course_id, c.name AS course_name, h.title, h.due_date, s.status
         FROM homework h
         JOIN courses c ON c.id = h.course_id
         JOIN enrollments e ON e.course_id = c.id
         LEFT JOIN submissions s ON s.homework_id = h.id AND s.student_id = ?
         WHERE e.student_id = ? AND h.deleted_at IS NULL
         ORDER BY h.due_date'
    );
    $listStmt->execute([$studentId, $studentId]);
    $allHomework = $listStmt->fetchAll();

    $byCourse = [];
    foreach ($allHomework as $hw) {
        $cid = (int) $hw['course_id'];
        if (!isset($byCourse[$cid])) {
            $byCourse[$cid] = ['id' => $cid, 'name' => $hw['course_name'], 'items' => []];
        }
        $byCourse[$cid]['items'][] = $hw;
    }
    $byCourse = array_values($byCourse);

    $pageTitle = t('homework.title');
    ?>
    <?php require __DIR__ . '/includes/header.php'; ?>
    <script>window.pageVoiceCommands = {};</script>

    <div class="p-gutter py-12 max-w-4xl mx-auto">
        <h1 class="font-headline-md text-headline-md text-primary mb-8"><?= htmlspecialchars(t('homework.title')) ?></h1>

        <div class="mb-8 p-4 rounded-xl bg-primary text-white flex items-center gap-3">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
            <span class="font-arabic-body font-bold" role="status" aria-live="polite"><?= htmlspecialchars(t('courses.assistant_listening')) ?></span>
        </div>

        <?php if (!$byCourse): ?>
            <p class="text-on-surface-variant"><?= htmlspecialchars(t('homework.no_homework')) ?></p>
        <?php endif; ?>

        <div class="space-y-6">
            <?php foreach ($byCourse as $course): ?>
                <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
                    <h2 class="font-headline-sm text-xl text-primary mb-4"><?= htmlspecialchars($course['name']) ?></h2>
                    <ul class="space-y-3">
                        <?php foreach ($course['items'] as $hw): ?>
                            <li class="flex items-center justify-between gap-4 flex-wrap">
                                <div>
                                    <span class="font-medium"><?= htmlspecialchars($hw['title']) ?></span>
                                    <span class="text-on-surface-variant text-sm ms-2">
                                        <?= htmlspecialchars(t('homework.due_label')) ?> <?= htmlspecialchars($hw['due_date']) ?>
                                    </span>
                                    <?php if ($hw['status'] === 'submitted'): ?>
                                        <span class="text-xs font-semibold px-2 py-1 rounded-full bg-secondary-container/40 text-on-secondary-container ms-2">
                                            <?= htmlspecialchars(t('homework.status_submitted')) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a
                                    href="/homework.php?homework=<?= (int) $hw['id'] ?>"
                                    class="inline-flex items-center gap-2 rounded-full bg-primary-container text-on-primary-container px-4 py-2 text-sm font-semibold hover:bg-primary-container/80 transition-colors"
                                >
                                    <span class="material-symbols-outlined text-lg" aria-hidden="true">edit_note</span>
                                    <?= htmlspecialchars(t('homework.open_button')) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php
    exit;
}

// =====================================================================
// Mode 2: ?homework=ID — the assignment reading + answering experience
// =====================================================================
$hwStmt = $pdo->prepare(
    'SELECT h.id, h.title, h.content_json, h.due_date, h.course_id, c.name AS course_name
     FROM homework h
     JOIN courses c ON c.id = h.course_id
     JOIN enrollments e ON e.course_id = c.id
     WHERE h.id = ? AND e.student_id = ? AND h.deleted_at IS NULL'
);
$hwStmt->execute([$homeworkId, $studentId]);
$homework = $hwStmt->fetch();

if (!$homework) {
    $pageTitle = t('homework.not_found');
    require __DIR__ . '/includes/header.php';
    ?>
    <script>window.pageVoiceCommands = {};</script>
    <div class="p-gutter py-12 max-w-2xl mx-auto text-center">
        <p role="alert" class="text-error mb-6"><?= htmlspecialchars(t('homework.not_found')) ?></p>
        <a href="/homework.php" class="text-primary font-semibold underline"><?= htmlspecialchars(t('homework.title')) ?></a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Existing submission + its answers, if any — shown visually so a
// student (or sighted helper) can see what's already answered, and used
// to seed the `submitted` flag that locks answer edits client-side too.
$submissionStmt = $pdo->prepare(
    'SELECT id, status FROM submissions WHERE homework_id = ? AND student_id = ?'
);
$submissionStmt->execute([$homeworkId, $studentId]);
$submission = $submissionStmt->fetch();

$existingAnswers = [];
if ($submission) {
    $answersStmt = $pdo->prepare(
        'SELECT question_number, answer_text FROM submission_answers WHERE submission_id = ? ORDER BY question_number'
    );
    $answersStmt->execute([$submission['id']]);
    foreach ($answersStmt->fetchAll() as $a) {
        $existingAnswers[(int) $a['question_number']] = $a['answer_text'];
    }
}

$contentStructure = json_decode($homework['content_json'], true) ?: ['pages' => []];
$isSubmitted = $submission && $submission['status'] === 'submitted';

$homeworkForJs = [
    'id' => (int) $homework['id'],
    'courseId' => (int) $homework['course_id'],
    'courseName' => $homework['course_name'],
    'title' => $homework['title'],
    'pages' => $contentStructure['pages'],
    'submitted' => $isSubmitted,
];

$homeworkStringsForJs = [
    'finished' => t('reader.material_finished'),
    'answerSaved' => t('voice.answer_saved'),
    'answerDeleted' => t('voice.answer_deleted'),
    'answerSaveFailed' => t('homework.answer_save_failed'),
    'answerDeleteFailed' => t('homework.answer_delete_failed'),
    'noAnswerForQuestion' => t('homework.no_answer_for_question'),
    'alreadySubmitted' => t('voice.homework_already_submitted'),
    'homeworkSubmitted' => t('voice.homework_submitted'),
];

$pageTitle = $homework['title'];
require __DIR__ . '/includes/header.php';
?>

<script>
window.BASSIRA_HOMEWORK = <?= json_encode($homeworkForJs, JSON_UNESCAPED_UNICODE) ?>;
window.BASSIRA_HOMEWORK_STRINGS = <?= json_encode($homeworkStringsForJs, JSON_UNESCAPED_UNICODE) ?>;

window.pageVoiceCommands = [
    // Same material-reading command set as reader.php (reusing the shared engine).
    {
        patterns: ['توقف'],
        destructive: false,
        description: <?= json_encode(t('reader.cmd_pause')) ?>,
        handler: () => { Homework.pause(); },
    },
    {
        patterns: ['تابعي'],
        destructive: false,
        description: <?= json_encode(t('reader.cmd_resume')) ?>,
        handler: () => { Homework.resume(); },
    },
    {
        match: (text) => {
            const m = text.match(/صفحة\s*(?:رقم)?\s*(\d+)/);
            return m ? { page: parseInt(m[1], 10) } : null;
        },
        destructive: false,
        description: <?= json_encode(t('reader.cmd_goto_page')) ?>,
        handler: (args) => { Homework.gotoPage(args.page); },
    },
    {
        patterns: ['اقرئي السطر السابق', 'اقرأي السطر السابق'],
        destructive: false,
        description: <?= json_encode(t('reader.cmd_repeat')) ?>,
        handler: () => { Homework.repeatPrevious(); },
    },

    // Assignment-specific.
    {
        // "same underlying mechanism as read previous line" — literally
        // the same repeatPrevious() call as above, just a second trigger
        // phrase that reads more naturally in an assignment context.
        patterns: ['اقرئي السؤال مرة أخرى', 'اقرأي السؤال مرة أخرى'],
        destructive: false,
        description: <?= json_encode(t('homework.cmd_repeat_question')) ?>,
        handler: () => { Homework.repeatPrevious(); },
    },
    {
        // "الإجابة على السؤال [رقم] هي: ..." — number AND answer text both
        // arrive in this single utterance, so the engine's standard
        // destructive echo (of the whole utterance, answer included) ->
        // confirm -> act flow is exactly the right fit here. See
        // assets/js/homework.js's top comment for the full reasoning.
        match: (text) => {
            const m = text.match(/الإجابة\s+(?:على\s+)?السؤال\s+(?:رقم\s+)?(\d+)\s+(?:هي|هو)\s*[:\-]?\s*(.+)/);
            if (!m) return null;
            const answer = m[2].trim();
            return answer ? { number: parseInt(m[1], 10), answer } : null;
        },
        destructive: true,
        description: <?= json_encode(t('homework.cmd_answer')) ?>,
        handler: (args) => { Homework.answerQuestion(args.number, args.answer); },
    },
    {
        match: (text) => {
            const m = text.match(/احذفي\s+إجابة\s+السؤال\s+(?:رقم\s+)?(\d+)/);
            return m ? { number: parseInt(m[1], 10) } : null;
        },
        destructive: true,
        description: <?= json_encode(t('homework.cmd_delete_answer')) ?>,
        handler: (args) => { Homework.deleteAnswer(args.number); },
    },
    {
        patterns: ['سلّمي الواجب', 'سلمي الواجب'],
        destructive: true, // irreversible — the confirmation gate matters most here
        description: <?= json_encode(t('homework.cmd_submit')) ?>,
        handler: () => { Homework.submitHomework(); },
    },
];
</script>

<div class="p-gutter py-12 max-w-3xl mx-auto">
    <div class="mb-8 p-4 rounded-xl bg-primary text-white flex items-center gap-3">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
        <span class="font-arabic-body font-bold" role="status" aria-live="polite"><?= htmlspecialchars(t('homework.mode_active')) ?></span>
    </div>

    <h1 class="font-headline-md text-headline-md text-primary mb-2"><?= htmlspecialchars($homework['title']) ?></h1>
    <p class="text-on-surface-variant mb-2"><?= htmlspecialchars($homework['course_name']) ?></p>
    <p class="text-on-surface-variant mb-8">
        <?= htmlspecialchars(t('homework.due_label')) ?> <?= htmlspecialchars($homework['due_date']) ?>
        <?php if ($isSubmitted): ?>
            <span class="text-xs font-semibold px-2 py-1 rounded-full bg-secondary-container/40 text-on-secondary-container ms-2">
                <?= htmlspecialchars(t('homework.status_submitted')) ?>
            </span>
        <?php endif; ?>
    </p>

    <?php if ($isSubmitted): ?>
        <div role="status" class="mb-8 p-4 rounded-xl bg-secondary-container/30 text-on-secondary-container">
            <?= htmlspecialchars(t('homework.submitted_locked')) ?>
        </div>
    <?php endif; ?>

    <?php if ($existingAnswers): ?>
        <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm mb-8">
            <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('homework.title')) ?></h2>
            <ul class="space-y-2">
                <?php foreach ($existingAnswers as $qNum => $answerText): ?>
                    <li>
                        <span class="font-semibold"><?= (int) $qNum ?>.</span>
                        <span class="text-on-surface"><?= htmlspecialchars($answerText) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('reader.commands_title')) ?></h2>
        <ul class="space-y-2 text-on-surface-variant">
            <li><?= htmlspecialchars(t('reader.cmd_pause')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_resume')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_goto_page')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_repeat')) ?></li>
            <li><?= htmlspecialchars(t('homework.cmd_repeat_question')) ?></li>
            <li><?= htmlspecialchars(t('homework.cmd_answer')) ?></li>
            <li><?= htmlspecialchars(t('homework.cmd_delete_answer')) ?></li>
            <li><?= htmlspecialchars(t('homework.cmd_submit')) ?></li>
        </ul>
        <p class="text-sm text-on-surface-variant mt-4"><?= htmlspecialchars(t('homework.speak_clearly_hint')) ?></p>
    </div>
</div>

<script src="/assets/js/reading_engine.js" defer></script>
<script src="/assets/js/homework.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
