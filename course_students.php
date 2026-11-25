<?php
/**
 * course_students.php — teacher-facing course roster + add-student page.
 *
 * Covers two build-order items at once because they're the same screen
 * in practice: "student enrollment (teacher-adds-student direction)" and
 * "course roster viewing". A teacher lands here without ?course= set and
 * picks one of their own courses first (mirrors material_upload.php's
 * course-dropdown pattern), then sees the roster for that course plus a
 * form to add a student by email or username.
 *
 * Teacher-only, and every query below is additionally scoped by
 * teacher_id = current_user_id() — never trust the ?course= query param
 * alone, since a teacher could otherwise type another teacher's course
 * id into the URL and see/modify their roster.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$teacherId = current_user_id();

$teacherCourseStmt = $pdo->prepare('SELECT id, name FROM courses WHERE teacher_id = ? ORDER BY name');
$teacherCourseStmt->execute([$teacherId]);
$teacherCourses = $teacherCourseStmt->fetchAll();

$selectedCourseId = isset($_GET['course']) ? (int) $_GET['course'] : 0;
$selectedCourse   = null;
$roster           = [];

if ($selectedCourseId > 0) {
    $courseStmt = $pdo->prepare('SELECT id, name FROM courses WHERE id = ? AND teacher_id = ?');
    $courseStmt->execute([$selectedCourseId, $teacherId]);
    $selectedCourse = $courseStmt->fetch();

    if ($selectedCourse) {
        $rosterStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, e.enrolled_at
             FROM enrollments e
             JOIN users u ON u.id = e.student_id
             WHERE e.course_id = ?
             ORDER BY u.name'
        );
        $rosterStmt->execute([$selectedCourse['id']]);
        $roster = $rosterStmt->fetchAll();
    }
}

$flashSuccess = isset($_GET['added']);
$flashError   = $_GET['error'] ?? null; // pre-translated message string

$page      = 'course_students';
$pageTitle = t('roster.title');
?>
<script>window.pageVoiceCommands = {};</script>
<?php require __DIR__ . '/includes/header.php'; ?>

<div class="p-gutter py-12 max-w-3xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-primary mb-8"><?= htmlspecialchars(t('roster.title')) ?></h1>

    <?php if (!$teacherCourses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('roster.no_courses')) ?></p>
    <?php else: ?>
        <form method="get" action="/course_students.php" class="mb-10 flex items-end gap-4 flex-wrap">
            <div class="flex-grow min-w-[200px]">
                <label for="course" class="block mb-2 font-medium"><?= htmlspecialchars(t('roster.select_course_label')) ?></label>
                <select id="course" name="course" class="w-full rounded border-outline-variant p-3" onchange="this.form.submit()">
                    <option value=""><?= htmlspecialchars(t('roster.select_course_placeholder')) ?></option>
                    <?php foreach ($teacherCourses as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $selectedCourseId === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript>
                <button type="submit" class="rounded bg-primary text-on-primary font-semibold px-6 py-3">
                    <?= htmlspecialchars(t('roster.view_button')) ?>
                </button>
            </noscript>
        </form>

        <?php if ($selectedCourseId > 0 && !$selectedCourse): ?>
            <p role="alert" class="text-on-surface-variant"><?= htmlspecialchars(t('roster.course_not_found')) ?></p>
        <?php elseif ($selectedCourse): ?>

            <?php if ($flashSuccess): ?>
                <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
                    <?= htmlspecialchars(t('roster.add_success')) ?>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
                    <?= htmlspecialchars($flashError) ?>
                </div>
            <?php endif; ?>

            <h2 class="font-headline-sm text-xl text-primary mb-4"><?= htmlspecialchars($selectedCourse['name']) ?></h2>

            <?php if (!$roster): ?>
                <p class="text-on-surface-variant mb-8"><?= htmlspecialchars(t('roster.no_students')) ?></p>
            <?php else: ?>
                <ul class="space-y-3 mb-8">
                    <?php foreach ($roster as $student): ?>
                        <li class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/10 flex items-center gap-3">
                            <span class="material-symbols-outlined text-secondary" aria-hidden="true">person</span>
                            <div>
                                <span class="font-medium"><?= htmlspecialchars($student['name']) ?></span>
                                <span class="text-on-surface-variant text-sm ms-2">&lt;<?= htmlspecialchars($student['email']) ?>&gt;</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="/course_students_action.php" class="space-y-4 max-w-md">
                <input type="hidden" name="course_id" value="<?= (int) $selectedCourse['id'] ?>">
                <div>
                    <label for="identifier" class="block mb-2 font-medium"><?= htmlspecialchars(t('roster.add_identifier_label')) ?></label>
                    <input type="text" id="identifier" name="identifier" required class="w-full rounded border-outline-variant p-3">
                </div>
                <button type="submit" class="rounded bg-primary text-on-primary font-semibold px-6 py-3">
                    <?= htmlspecialchars(t('roster.add_button')) ?>
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
