<?php
/**
 * materials_manage.php — teacher-facing material edit/delete page.
 *
 * Same course-picker pattern as course_students.php: pick one of the
 * teacher's own courses, see its materials, edit a title inline or
 * soft-delete (materials.deleted_at) a material. Every query is scoped
 * by teacher_id = current_user_id() via the course ownership check —
 * never trust ?course= alone.
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
$materials        = [];

if ($selectedCourseId > 0) {
    $courseStmt = $pdo->prepare('SELECT id, name FROM courses WHERE id = ? AND teacher_id = ?');
    $courseStmt->execute([$selectedCourseId, $teacherId]);
    $selectedCourse = $courseStmt->fetch();

    if ($selectedCourse) {
        $materialsStmt = $pdo->prepare(
            'SELECT id, title, uploaded_at
             FROM materials
             WHERE course_id = ? AND deleted_at IS NULL
             ORDER BY uploaded_at DESC'
        );
        $materialsStmt->execute([$selectedCourse['id']]);
        $materials = $materialsStmt->fetchAll();
    }
}

$flashSuccess = $_GET['done'] ?? null; // 'edited' or 'deleted'
$flashError   = $_GET['error'] ?? null;

$page      = 'materials_manage';
$pageTitle = t('materials_manage.title');
require __DIR__ . '/includes/header.php';
?>
<script>window.pageVoiceCommands = {};</script>

<div class="max-w-3xl mx-auto p-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-primary mb-8">
        <?= htmlspecialchars(t('materials_manage.title')) ?>
    </h1>

    <?php if (!$teacherCourses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('roster.no_courses')) ?></p>
    <?php else: ?>
        <form method="get" action="/materials_manage.php" class="mb-10">
            <label for="course" class="block mb-2 font-medium"><?= htmlspecialchars(t('roster.select_course_label')) ?></label>
            <select id="course" name="course" class="w-full rounded border-outline-variant p-3" onchange="this.form.submit()">
                <option value=""><?= htmlspecialchars(t('roster.select_course_placeholder')) ?></option>
                <?php foreach ($teacherCourses as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $selectedCourseId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($flashSuccess === 'edited'): ?>
            <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
                <?= htmlspecialchars(t('materials_manage.edit_success')) ?>
            </div>
        <?php elseif ($flashSuccess === 'deleted'): ?>
            <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
                <?= htmlspecialchars(t('materials_manage.delete_success')) ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
                <?= htmlspecialchars($flashError) ?>
            </div>
        <?php endif; ?>

        <?php if ($selectedCourseId > 0 && !$selectedCourse): ?>
            <p role="alert" class="text-on-surface-variant"><?= htmlspecialchars(t('roster.course_not_found')) ?></p>
        <?php elseif ($selectedCourse): ?>
            <h2 class="font-headline-sm text-xl text-primary mb-4"><?= htmlspecialchars($selectedCourse['name']) ?></h2>

            <?php if (!$materials): ?>
                <p class="text-on-surface-variant"><?= htmlspecialchars(t('courses.no_materials')) ?></p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($materials as $material): ?>
                        <div class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/10 shadow-sm">
                            <form method="post" action="/materials_manage_action.php" class="flex items-center gap-3 flex-wrap">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="material_id" value="<?= (int) $material['id'] ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $selectedCourse['id'] ?>">
                                <input
                                    type="text"
                                    name="title"
                                    value="<?= htmlspecialchars($material['title']) ?>"
                                    required
                                    maxlength="200"
                                    class="flex-grow min-w-[160px] rounded border-outline-variant p-2"
                                >
                                <button type="submit" class="rounded bg-primary text-on-primary text-sm font-semibold px-4 py-2">
                                    <?= htmlspecialchars(t('materials_manage.save_button')) ?>
                                </button>
                            </form>
                            <form method="post" action="/materials_manage_action.php" class="mt-2">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="material_id" value="<?= (int) $material['id'] ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $selectedCourse['id'] ?>">
                                <button type="submit" class="text-sm text-error underline">
                                    <?= htmlspecialchars(t('materials_manage.delete_button')) ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
