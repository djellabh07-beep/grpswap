<?php
/**
 * courses.php — student-facing course listing.
 *
 * Voice-opening by course name ("بصيرة، افتحي [اسم المقرر]") is
 * implemented as a GLOBAL command in voice_assistant.js (see that file's
 * buildGlobalCommands(), and header.php's window.BASSIRA_COURSES
 * injection) rather than a page-specific command registered only here.
 *
 * Why global, not page-specific: every other navigation-type command in
 * this app ("مقرراتي", "ملاحظاتي", "واجباتي", ...) already works from any
 * page, not just from within some other page first. Making "open course
 * X by name" page-specific-to-courses.php would mean a student has to
 * first say "مقرراتي" to get here, THEN say the course name — an
 * inconsistent extra step that the rest of the command design doesn't
 * require anywhere else. A global, data-driven command (reading
 * window.BASSIRA_COURSES, injected on every page) keeps the whole command
 * surface consistent: anything that names a destination gets you there
 * directly, regardless of where you're currently standing.
 *
 * This page therefore has no page-specific voice commands of its own —
 * it's purely a visual/click-through fallback and a place for the
 * dynamic global command to land when a course has more than one file.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$studentId = current_user_id();

$courseStmt = $pdo->prepare(
    'SELECT c.id AS course_id, c.name AS course_name, u.name AS teacher_name
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     JOIN users u ON u.id = c.teacher_id
     WHERE e.student_id = ?
     ORDER BY c.name'
);
$courseStmt->execute([$studentId]);
$courses = $courseStmt->fetchAll();

$materialsByCourse = [];
if ($courses) {
    $courseIds = array_column($courses, 'course_id');
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

    $materialStmt = $pdo->prepare(
        "SELECT m.id, m.course_id, m.title, rp.last_page, rp.last_line
         FROM materials m
         LEFT JOIN reading_progress rp ON rp.material_id = m.id AND rp.student_id = ?
         WHERE m.course_id IN ($placeholders) AND m.deleted_at IS NULL
         ORDER BY m.uploaded_at"
    );
    $materialStmt->execute(array_merge([$studentId], $courseIds));

    foreach ($materialStmt->fetchAll() as $m) {
        $materialsByCourse[$m['course_id']][] = $m;
    }
}

$page      = 'courses';
$pageTitle = t('courses.title');
?>
<script>window.pageVoiceCommands = {};</script>
<?php require __DIR__ . '/includes/header.php'; ?>

<div class="p-gutter py-12 max-w-5xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-primary mb-2"><?= htmlspecialchars(t('courses.title')) ?></h1>
    <p class="text-on-surface-variant mb-10"><?= htmlspecialchars(t('courses.subtitle')) ?></p>

    <!-- aria-live status region — every page needs one, per the voice
         engine's contract (it writes every echoed/spoken utterance here). -->
    <div class="mb-8 p-4 rounded-xl bg-primary text-white flex items-center gap-3">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
        <span class="font-arabic-body font-bold" role="status" aria-live="polite"><?= htmlspecialchars(t('courses.assistant_listening')) ?></span>
    </div>

    <?php if (!$courses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('courses.no_courses')) ?></p>
    <?php endif; ?>

    <div class="space-y-6">
        <?php foreach ($courses as $course): ?>
            <?php $materials = $materialsByCourse[$course['course_id']] ?? []; ?>
            <div id="course-<?= (int) $course['course_id'] ?>" class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
                <div class="flex items-start gap-4 mb-4">
                    <span class="material-symbols-outlined text-secondary text-3xl mt-1" aria-hidden="true">auto_stories</span>
                    <div>
                        <h2 class="font-headline-sm text-xl text-primary"><?= htmlspecialchars($course['course_name']) ?></h2>
                        <p class="text-on-surface-variant text-sm">
                            <?= htmlspecialchars(t('courses.teacher_label')) ?> <?= htmlspecialchars($course['teacher_name']) ?>
                        </p>
                    </div>
                </div>

                <?php if (!$materials): ?>
                    <p class="text-on-surface-variant text-sm ps-12"><?= htmlspecialchars(t('courses.no_materials')) ?></p>
                <?php else: ?>
                    <ul class="ps-12 space-y-3">
                        <?php foreach ($materials as $m): ?>
                            <li class="flex items-center justify-between gap-4 flex-wrap">
                                <div>
                                    <span class="font-medium"><?= htmlspecialchars($m['title']) ?></span>
                                    <?php if ($m['last_page']): ?>
                                        <span class="text-on-surface-variant text-sm ms-2">
                                            (<?= htmlspecialchars(t('courses.resume_label', [(int) $m['last_page']])) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a
                                    href="/reader.php?material=<?= (int) $m['id'] ?>"
                                    class="inline-flex items-center gap-2 rounded-full bg-primary-container text-on-primary-container px-4 py-2 text-sm font-semibold hover:bg-primary-container/80 transition-colors"
                                >
                                    <span class="material-symbols-outlined text-lg" aria-hidden="true">play_circle</span>
                                    <?= htmlspecialchars(t('courses.open_material_button')) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
