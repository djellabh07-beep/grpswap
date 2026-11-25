<?php
/**
 * notes.php — lists the student's notes, grouped by course (both manual
 * and voice-dictated notes from the "خذي ملاحظة" command on reader.php).
 *
 * Voice-opening by course name reuses the SAME course-name-matching data
 * every page already gets (window.BASSIRA_COURSES, injected by
 * header.php) — "the same pattern as courses.php" — but is registered as
 * a PAGE-SPECIFIC command here, not the global material-open command
 * itself. The action differs: on courses.php (or anywhere else), saying a
 * course name opens that course's reading material; here, it should
 * surface that course's NOTES instead. Since page-specific commands are
 * checked before global ones, this local command simply takes priority
 * while the student is on notes.php, and the global command remains
 * exactly as useful as before on every other page. No engine changes
 * were needed for this — it's the page-first matching order already
 * built into voice_assistant.js working as intended.
 *
 * The three required commands ("اقرئي الملاحظة", "عدّلي", "احذفي هذه
 * الملاحظة") all act on a single "current note" — set either by clicking
 * a note, or automatically (most recent in that course) when a course is
 * voice-opened, mirroring how reader.php's commands act on "the current
 * material" rather than requiring the student to name an individual note
 * each time (notes have no distinct spoken identifier the way materials
 * have titles).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$studentId = current_user_id();

$notesStmt = $pdo->prepare(
    'SELECT n.id, n.course_id, c.name AS course_name, n.title, n.content,
            n.source, n.created_at, n.updated_at
     FROM notes n
     JOIN courses c ON c.id = n.course_id
     WHERE n.student_id = ?
     ORDER BY c.name, n.created_at DESC'
);
$notesStmt->execute([$studentId]);
$allNotes = $notesStmt->fetchAll();

// Group while preserving the course-name/most-recent-first order the
// query already provides.
$coursesWithNotes = [];
foreach ($allNotes as $n) {
    $cid = (int) $n['course_id'];
    if (!isset($coursesWithNotes[$cid])) {
        $coursesWithNotes[$cid] = [
            'id' => $cid,
            'name' => $n['course_name'],
            'notes' => [],
        ];
    }
    $coursesWithNotes[$cid]['notes'][] = $n;
}
$coursesWithNotes = array_values($coursesWithNotes);

$notesForJs = [
    'courses' => array_map(function ($course) {
        return [
            'id' => $course['id'],
            'name' => $course['name'],
            'notes' => array_map(function ($n) {
                return [
                    'id' => (int) $n['id'],
                    'title' => $n['title'],
                    'content' => $n['content'],
                    'source' => $n['source'],
                    'createdAt' => $n['created_at'],
                ];
            }, $course['notes']),
        ];
    }, $coursesWithNotes),
];

$notesStringsForJs = [
    'dictationStart' => t('voice.dictation_start'),
    'noteNotCaptured' => t('reader.note_not_captured'),
    'confirmPrompt' => t('voice.confirm_prompt'),
    'confirmCancelled' => t('voice.confirm_cancelled'),
    'confirmTimeout' => t('voice.confirm_timeout'),
    'editEcho' => t('notes.edit_echo'),
    'editSaved' => t('voice.note_updated'),
    'editFailed' => t('notes.edit_failed'),
    'deleteSaved' => t('voice.note_deleted'),
    'deleteFailed' => t('notes.delete_failed'),
    'noNoteSelected' => t('notes.no_note_selected'),
    'noNotesInCourse' => t('notes.no_notes_in_course'),
    'courseOpenedCount' => t('notes.course_opened_count'),
];

$page      = 'notes';
$pageTitle = t('notes.title');
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<script>
window.BASSIRA_NOTES = <?= json_encode($notesForJs, JSON_UNESCAPED_UNICODE) ?>;
window.BASSIRA_NOTES_STRINGS = <?= json_encode($notesStringsForJs, JSON_UNESCAPED_UNICODE) ?>;

window.pageVoiceCommands = [
    {
        patterns: ['اقرئي الملاحظة', 'اقرأي الملاحظة'],
        destructive: false,
        description: <?= json_encode(t('notes.cmd_read')) ?>,
        handler: () => { Notes.readCurrent(); },
    },
    {
        patterns: ['عدّلي', 'عدلي'],
        // NOT destructive at the engine level — see assets/js/notes.js's
        // top comment for why this command runs its own manual dictate
        // -> echo -> confirm sequence instead of the engine's built-in
        // destructive flag (which would confirm the wrong thing: the
        // trigger phrase, before any dictation has even happened).
        destructive: false,
        description: <?= json_encode(t('notes.cmd_edit')) ?>,
        handler: () => { Notes.editCurrent(); },
    },
    {
        patterns: ['احذفي هذه الملاحظة'],
        destructive: true, // plain confirm-then-delete — the engine's
                            // standard flow is exactly right here.
        description: <?= json_encode(t('notes.cmd_delete')) ?>,
        handler: () => { Notes.deleteCurrent(); },
    },
    {
        match: (text) => {
            const course = (window.BASSIRA_COURSES || []).find(
                (c) => c.name && text.indexOf(c.name) !== -1
            );
            return course ? { id: course.id } : null;
        },
        destructive: false,
        handler: (args) => { Notes.openCourse(args.id); },
    },
];
</script>

<div class="p-gutter py-12 max-w-4xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-primary mb-2"><?= htmlspecialchars(t('notes.title')) ?></h1>
    <p class="text-on-surface-variant mb-10"><?= htmlspecialchars(t('notes.subtitle')) ?></p>

    <div class="mb-8 p-4 rounded-xl bg-primary text-white flex items-center gap-3">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
        <span class="font-arabic-body font-bold" role="status" aria-live="polite"><?= htmlspecialchars(t('notes.assistant_listening')) ?></span>
    </div>

    <?php if (!$coursesWithNotes): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('notes.no_courses_with_notes')) ?></p>
    <?php endif; ?>

    <div class="space-y-6">
        <?php foreach ($coursesWithNotes as $course): ?>
            <div id="notes-course-<?= (int) $course['id'] ?>" data-course-section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
                <h2 class="font-headline-sm text-xl text-primary mb-4"><?= htmlspecialchars($course['name']) ?></h2>

                <ul class="space-y-3">
                    <?php foreach ($course['notes'] as $note): ?>
                        <li
                            data-note-id="<?= (int) $note['id'] ?>"
                            tabindex="0"
                            role="button"
                            aria-label="<?= htmlspecialchars(t('notes.select_hint')) ?>"
                            aria-current="false"
                            onclick="Notes.selectNote(<?= (int) $note['id'] ?>)"
                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();Notes.selectNote(<?= (int) $note['id'] ?>);}"
                            class="p-4 rounded-lg border border-outline-variant/20 cursor-pointer hover:bg-primary-container/10 transition-colors"
                        >
                            <div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
                                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-secondary-container/40 text-on-secondary-container">
                                    <?= $note['source'] === 'voice'
                                        ? htmlspecialchars(t('notes.source_voice'))
                                        : htmlspecialchars(t('notes.source_manual')) ?>
                                </span>
                                <span class="text-xs text-on-surface-variant"><?= htmlspecialchars($note['created_at']) ?></span>
                            </div>
                            <p data-note-content class="text-on-surface"><?= htmlspecialchars($note['content']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    /* Visual marker for the voice engine's "current note" — kept in a
       page-local stylesheet since it's specific to this page's own
       data-note-id elements, not part of the shared design system. */
    [data-note-id].bassira-current-note {
        border-color: var(--color-secondary, #006a60);
        border-width: 2px;
        background-color: rgba(0, 106, 96, 0.06);
    }
</style>

<script src="/assets/js/notes.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
