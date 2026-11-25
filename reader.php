<?php
/**
 * reader.php — the material reading experience.
 *
 * Reached from courses.php (click or the global "open course by name"
 * voice command). Not a nav.php item itself — $page is set to 'courses'
 * so the sidebar highlights where this page conceptually lives.
 *
 * Bassira announces the material name, mentions the resume point if one
 * exists, then reads sequentially by line (assets/js/reader.js handles
 * the actual autoplay/pause/resume/page-jump/repeat/dictation logic —
 * this file's job is just fetching + authorizing the material and
 * handing its data to that script).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$materialId = (int) ($_GET['material'] ?? 0);
$studentId  = current_user_id();

// The material must belong to a course the student is actually enrolled
// in, and must not be soft-deleted — never trust the material id in the
// URL blindly.
$materialStmt = $pdo->prepare(
    'SELECT m.id, m.title, m.content_json, m.course_id, c.name AS course_name
     FROM materials m
     JOIN courses c ON c.id = m.course_id
     JOIN enrollments e ON e.course_id = c.id
     WHERE m.id = ? AND e.student_id = ? AND m.deleted_at IS NULL'
);
$materialStmt->execute([$materialId, $studentId]);
$material = $materialStmt->fetch();

$page = 'courses';

if (!$material) {
    $pageTitle = t('reader.material_not_found');
    require __DIR__ . '/includes/header.php';
    ?>
    <script>window.pageVoiceCommands = {};</script>
    <div class="p-gutter py-12 max-w-2xl mx-auto text-center">
        <p role="alert" class="text-error mb-6"><?= htmlspecialchars(t('reader.material_not_found')) ?></p>
        <a href="/courses.php" class="text-primary font-semibold underline"><?= htmlspecialchars(t('courses.title')) ?></a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Existing resume point, if any.
$progressStmt = $pdo->prepare(
    'SELECT last_page, last_line FROM reading_progress WHERE student_id = ? AND material_id = ?'
);
$progressStmt->execute([$studentId, $materialId]);
$progress = $progressStmt->fetch();

$contentStructure = json_decode($material['content_json'], true) ?: ['pages' => []];

$materialForJs = [
    'id' => (int) $material['id'],
    'courseId' => (int) $material['course_id'],
    'courseName' => $material['course_name'],
    'title' => $material['title'],
    'pages' => $contentStructure['pages'],
    'resume' => $progress ? ['page' => (int) $progress['last_page'], 'line' => (int) $progress['last_line']] : null,
];

$readerStringsForJs = [
    'materialFinished' => t('reader.material_finished'),
    'dictationStart' => t('voice.dictation_start'),
    'noteNotCaptured' => t('reader.note_not_captured'),
    'noteSaved' => t('voice.note_saved'),
    'noteSaveFailed' => t('voice.note_save_failed'),
    'resumePoint' => t('reader.resume_point'),
];

$pageTitle = $material['title'];
require __DIR__ . '/includes/header.php';
?>

<script>
window.BASSIRA_MATERIAL = <?= json_encode($materialForJs, JSON_UNESCAPED_UNICODE) ?>;
window.BASSIRA_READER_STRINGS = <?= json_encode($readerStringsForJs, JSON_UNESCAPED_UNICODE) ?>;

window.pageVoiceCommands = [
    {
        patterns: ['توقف'],
        destructive: false,
        description: <?= json_encode(t('reader.cmd_pause')) ?>,
        handler: () => { Reader.pause(); },
    },
    {
        patterns: ['تابعي'],
        destructive: false,
        description: <?= json_encode(t('reader.cmd_resume')) ?>,
        handler: () => { Reader.resume(); },
    },
    {
        match: (text) => {
            const m = text.match(/صفحة\s*(?:رقم)?\s*(\d+)/);
            return m ? { page: parseInt(m[1], 10) } : null;
        },
        destructive: false,
        description: <?= json_encode(t('reader.cmd_goto_page')) ?>,
        handler: (args) => { Reader.gotoPage(args.page); },
    },
    {
        patterns: ['اقرئي السطر السابق', 'اقرأي السطر السابق'],
        destructive: false,
        description: <?= json_encode(t('reader.cmd_repeat')) ?>,
        handler: () => { Reader.repeatPrevious(); },
    },
    {
        patterns: ['خذي ملاحظة'],
        destructive: false, // creating a new note isn't destructive — nothing is overwritten
        description: <?= json_encode(t('reader.cmd_note')) ?>,
        handler: () => { Reader.dictateNote(); },
    },
];
</script>

<div class="p-gutter py-12 max-w-3xl mx-auto">
    <div class="mb-8 p-4 rounded-xl bg-primary text-white flex items-center gap-3">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
        <span class="font-arabic-body font-bold" role="status" aria-live="polite"><?= htmlspecialchars(t('reader.now_reading')) ?></span>
    </div>

    <h1 class="font-headline-md text-headline-md text-primary mb-2"><?= htmlspecialchars($material['title']) ?></h1>
    <p class="text-on-surface-variant mb-8"><?= htmlspecialchars($material['course_name']) ?></p>

    <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('reader.commands_title')) ?></h2>
        <ul class="space-y-2 text-on-surface-variant">
            <li><?= htmlspecialchars(t('reader.cmd_pause')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_resume')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_goto_page')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_repeat')) ?></li>
            <li><?= htmlspecialchars(t('reader.cmd_note')) ?></li>
        </ul>
        <p class="text-sm text-on-surface-variant mt-4"><?= htmlspecialchars(t('reader.tip')) ?></p>
    </div>
</div>

<script src="/assets/js/reader.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
