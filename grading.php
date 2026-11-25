<?php
/**
 * grading.php — teacher-facing submission list, step 1 of grading.
 *
 * Course picker -> homework picker (for that course) -> list of
 * submitted submissions with student name, submission date, and current
 * grade (if any). Clicking a submission goes to grading_detail.php to
 * see the actual answers and assign a grade.
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
$courseHomework    = [];

if ($selectedCourseId > 0) {
    $courseStmt = $pdo->prepare('SELECT id, name FROM courses WHERE id = ? AND teacher_id = ?');
    $courseStmt->execute([$selectedCourseId, $teacherId]);
    $selectedCourse = $courseStmt->fetch();

    if ($selectedCourse) {
        $hwStmt = $pdo->prepare('SELECT id, title FROM homework WHERE course_id = ? AND deleted_at IS NULL ORDER BY due_date DESC');
        $hwStmt->execute([$selectedCourse['id']]);
        $courseHomework = $hwStmt->fetchAll();
    }
}

$selectedHomeworkId = isset($_GET['homework']) ? (int) $_GET['homework'] : 0;
$selectedHomework    = null;
$submissions         = [];

if ($selectedHomeworkId > 0 && $selectedCourse) {
    $hwCheckStmt = $pdo->prepare('SELECT id, title FROM homework WHERE id = ? AND course_id = ?');
    $hwCheckStmt->execute([$selectedHomeworkId, $selectedCourse['id']]);
    $selectedHomework = $hwCheckStmt->fetch();

    if ($selectedHomework) {
        $subStmt = $pdo->prepare(
            "SELECT s.id, s.submitted_at, s.grade, u.name AS student_name
             FROM submissions s
             JOIN users u ON u.id = s.student_id
             WHERE s.homework_id = ? AND s.status = 'submitted'
             ORDER BY s.submitted_at"
        );
        $subStmt->execute([$selectedHomeworkId]);
        $submissions = $subStmt->fetchAll();
    }
}

$page      = 'grading';
$pageTitle = t('grading.title');
require __DIR__ . '/includes/header.php';
?>
<script>window.pageVoiceCommands = {};</script>

<div class="max-w-3xl mx-auto p-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-primary mb-8">
        <?= htmlspecialchars(t('grading.title')) ?>
    </h1>

    <?php if (!$teacherCourses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('roster.no_courses')) ?></p>
    <?php else: ?>
        <form method="get" action="/grading.php" class="mb-8 space-y-4">
            <div>
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

            <?php if ($selectedCourse): ?>
                <div>
                    <label for="homework" class="block mb-2 font-medium"><?= htmlspecialchars(t('grading.select_homework_label')) ?></label>
                    <select id="homework" name="homework" class="w-full rounded border-outline-variant p-3" onchange="this.form.submit()">
                        <option value=""><?= htmlspecialchars(t('roster.select_course_placeholder')) ?></option>
                        <?php foreach ($courseHomework as $hw): ?>
                            <option value="<?= (int) $hw['id'] ?>" <?= $selectedHomeworkId === (int) $hw['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($hw['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($selectedHomeworkId > 0 && !$selectedHomework): ?>
            <p role="alert" class="text-on-surface-variant"><?= htmlspecialchars(t('grading.homework_not_found')) ?></p>
        <?php elseif ($selectedHomework): ?>
            <h2 class="font-headline-sm text-xl text-primary mb-4"><?= htmlspecialchars($selectedHomework['title']) ?></h2>

            <?php if (!$submissions): ?>
                <p class="text-on-surface-variant"><?= htmlspecialchars(t('grading.no_submissions')) ?></p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($submissions as $sub): ?>
                        
                            href="/grading_detail.php?submission=<?= (int) $sub['id'] ?>"
                            class="block bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/10 shadow-sm hover:bg-primary-container/10 transition-colors"
                        >
                            <div class="flex justify-between items-center">
                                <span class="font-medium"><?= htmlspecialchars($sub['student_name']) ?></span>
                                <?php if ($sub['grade'] !== null): ?>
                                    <span class="text-secondary font-semibold"><?= htmlspecialchars((string) $sub['grade']) ?></span>
                                <?php else: ?>
                                    <span class="text-on-surface-variant text-sm"><?= htmlspecialchars(t('grading.not_graded_yet')) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
