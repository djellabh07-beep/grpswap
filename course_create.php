<?php
/**
 * course_create.php — teacher-facing course creation form.
 *
 * Mirrors material_upload.php's conventions exactly (same require_role
 * pattern, same header.php/footer.php usage, same flash-message style
 * via query string). Teacher-only.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/i18n.php';

$flashSuccess = isset($_GET['created']);
$flashError   = $_GET['error'] ?? null; // pre-translated message string

$page      = 'courses';
$pageTitle = t('courses.create_title');
require __DIR__ . '/includes/header.php';
?>
<script>window.pageVoiceCommands = {};</script>

<div class="max-w-2xl mx-auto p-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-primary mb-8">
        <?= htmlspecialchars(t('courses.create_title')) ?>
    </h1>

    <?php if ($flashSuccess): ?>
        <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
            <?= htmlspecialchars(t('courses.create_success')) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/course_create_action.php" class="space-y-6">
        <div>
            <label for="name" class="block mb-2 font-medium"><?= htmlspecialchars(t('courses.name_label')) ?></label>
            <input type="text" id="name" name="name" required maxlength="200" class="w-full rounded border-outline-variant p-3">
        </div>

        <div>
            <label for="description" class="block mb-2 font-medium"><?= htmlspecialchars(t('courses.description_label')) ?></label>
            <textarea id="description" name="description" rows="4" class="w-full rounded border-outline-variant p-3"></textarea>
        </div>

        <button type="submit" class="rounded bg-primary text-on-primary font-semibold px-6 py-3">
            <?= htmlspecialchars(t('courses.create_button')) ?>
        </button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
