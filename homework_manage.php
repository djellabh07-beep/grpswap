<?php
/**
 * homework_manage.php — teacher-facing homework edit/delete page.
 *
 * Same course-picker + inline-edit pattern as materials_manage.php.
 * Edit here is limited to title and due date (not the PDF content —
 * re-processing an already-assigned homework's content would be
 * confusing for students mid-assignment; a title/date fix doesn't
 * change what they're answering). Delete is a soft-delete
 * (homework.deleted_at), matching the schema's own design and
 * homework.php's existing deleted_at IS NULL filtering.
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
$homeworkList     = [];

if ($selectedCourseId > 0) {
    $courseStmt = $pdo->prepare('SELECT id, name FROM courses WHERE id = ? AND teacher_id = ?');
    $courseStmt->execute([$selectedCourseId, $teacherId]);
    $selectedCourse = $courseStmt->fetch();

    if ($selectedCourse) {
        $hwStmt = $pdo->prepare(
            'SELECT id, title, due_date
             FROM homework
             WHERE course_id = ? AND deleted_at IS NULL
             ORDER BY due_date DESC'
        );
        $hwStmt->execute([$selectedCourse['id']]);
        $homeworkList = $hwStmt->fetchAll();
    }
}

$flashSuccess = $_GET['done'] ?? null; // 'edited' or 'deleted'
$flashError   = $_GET['error'] ?? null;

$page      = 'homework_manage';
$pageTitle = t('homework_manage.title');
require __DIR__ . '/includes/header.php';
?>
<script>window.pageVoiceCommands = {};</script>

<div class="max-w-3xl mx-auto p-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-primary mb-8">
        <?= htmlspecialchars(t('homework_manage.title')) ?>
    </h1>

    <?php if (!$teacherCourses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('roster.no_courses')) ?></p>
    <?php else: ?>
        <form method="get" action="/homework_manage.php" class="mb-10">
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
                <?= htmlspecialchars(t('homework_manage.edit_success')) ?>
            </div>
        <?php elseif ($flashSuccess === 'deleted'): ?>
            <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
                <?= htmlspecialchars(t('homework_manage.delete_success')) ?>
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

            <?php if (!$homeworkList): ?>
                <p class="text-on-surface-variant"><?= htmlspecialchars(t('homework_manage.no_homework')) ?></p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($homeworkList as $hw): ?>
                        <div class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/10 shadow-sm">
                            <form method="post" action="/homework_manage_action.php" class="flex items-center gap-3 flex-wrap">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="homework_id" value="<?= (int) $hw['id'] ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $selectedCourse['id'] ?>">
                                <input
                                    type="text"
                                    name="title"
                                    value="<?= htmlspecialchars($hw['title']) ?>"
                                    required
                                    maxlength="200"
                                    class="flex-grow min-w-[160px] rounded border-outline-variant p-2"
                                >
                                <input
                                    type="datetime-local"
                                    name="due_date"
                                    value="<?= htmlspecialchars((new DateTime($hw['due_date']))->format('Y-m-d\TH:i')) ?>"
                                    required
                                    class="rounded border-outline-variant p-2"
                                >
                                <button type="submit" class="rounded bg-primary text-on-primary text-sm font-semibold px-4 py-2">
                                    <?= htmlspecialchars(t('materials_manage.save_button')) ?>
                                </button>
                            </form>
                            <form method="post" action="/homework_manage_action.php" class="mt-2">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="homework_id" value="<?= (int) $hw['id'] ?>">
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
