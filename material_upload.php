<?php
/**
 * material_upload.php — teacher-facing upload form.
 *
 * Course + PDF in, materials row + extracted content_json out (handled by
 * material_upload_action.php). Teacher-only.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

// Only the teacher's own courses are offered — enforced again server-side
// in material_upload_action.php, this is just what's shown in the form.
$courseStmt = $pdo->prepare('SELECT id, name FROM courses WHERE teacher_id = ? ORDER BY name');
$courseStmt->execute([current_user_id()]);
$teacherCourses = $courseStmt->fetchAll();

$flashSuccess = isset($_GET['uploaded']);
$flashError   = $_GET['error'] ?? null; // pre-translated message string, urlencoded

$page      = 'courses';
$pageTitle = t('materials.upload_title');
require __DIR__ . '/includes/header.php';
?>
<script>window.pageVoiceCommands = {};</script>

<div class="max-w-2xl mx-auto p-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-primary mb-8">
        <?= htmlspecialchars(t('materials.upload_title')) ?>
    </h1>

    <?php if ($flashSuccess): ?>
        <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
            <?= htmlspecialchars(t('materials.upload_success')) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <?php if (!$teacherCourses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('courses.no_courses')) ?></p>
    <?php else: ?>
        <form method="post" action="/material_upload_action.php" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label for="course_id" class="block mb-2 font-medium"><?= htmlspecialchars(t('materials.course_label')) ?></label>
                <select id="course_id" name="course_id" required class="w-full rounded border-outline-variant p-3">
                    <?php foreach ($teacherCourses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="title" class="block mb-2 font-medium"><?= htmlspecialchars(t('materials.title_label')) ?></label>
                <input type="text" id="title" name="title" required maxlength="200" class="w-full rounded border-outline-variant p-3">
            </div>

            <div>
                <label for="pdf_file" class="block mb-2 font-medium"><?= htmlspecialchars(t('materials.file_label')) ?></label>
                <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" required class="w-full">
            </div>

            <button type="submit" class="rounded bg-primary text-on-primary font-semibold px-6 py-3">
                <?= htmlspecialchars(t('materials.upload_button')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
