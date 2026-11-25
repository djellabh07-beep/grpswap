<?php
/**
 * enroll.php — student-facing "browse & enroll" page.
 *
 * Self-enrollment direction of the enrollment feature (the other
 * direction — a teacher adding a student directly — lives in
 * course_students.php). Lists every course the student is NOT already
 * enrolled in, with a one-click enroll form per course. Once enrolled,
 * the course moves from this list to courses.php ("My Courses").
 *
 * Student-only: a teacher enrolling as a "student" in their own or
 * another teacher's course isn't a supported flow, so this is gated the
 * same way material_upload.php etc. are gated the other way.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('student');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$studentId = current_user_id();

$flashSuccess = isset($_GET['enrolled']);
$flashError   = $_GET['error'] ?? null; // pre-translated message string

// Every course NOT already in this student's enrollments. A NOT IN
// subquery (rather than a LEFT JOIN ... WHERE NULL) reads more directly
// here and the enrollments table is small enough per-student that the
// subquery cost is a non-issue.
$courseStmt = $pdo->prepare(
    'SELECT c.id AS course_id, c.name AS course_name, c.description, u.name AS teacher_name
     FROM courses c
     JOIN users u ON u.id = c.teacher_id
     WHERE c.id NOT IN (
         SELECT course_id FROM enrollments WHERE student_id = ?
     )
     ORDER BY c.name'
);
$courseStmt->execute([$studentId]);
$courses = $courseStmt->fetchAll();

$page      = 'enroll';
$pageTitle = t('enroll.title');
?>
<script>window.pageVoiceCommands = {};</script>
<?php require __DIR__ . '/includes/header.php'; ?>

<div class="p-gutter py-12 max-w-5xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-primary mb-2"><?= htmlspecialchars(t('enroll.title')) ?></h1>
    <p class="text-on-surface-variant mb-10"><?= htmlspecialchars(t('enroll.subtitle')) ?></p>

    <?php if ($flashSuccess): ?>
        <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
            <?= htmlspecialchars(t('enroll.success')) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <?php if (!$courses): ?>
        <p class="text-on-surface-variant"><?= htmlspecialchars(t('enroll.no_courses_available')) ?></p>
    <?php endif; ?>

    <div class="space-y-6">
        <?php foreach ($courses as $course): ?>
            <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm flex items-start justify-between gap-4 flex-wrap">
                <div class="flex items-start gap-4">
                    <span class="material-symbols-outlined text-secondary text-3xl mt-1" aria-hidden="true">auto_stories</span>
                    <div>
                        <h2 class="font-headline-sm text-xl text-primary"><?= htmlspecialchars($course['course_name']) ?></h2>
                        <p class="text-on-surface-variant text-sm">
                            <?= htmlspecialchars(t('courses.teacher_label')) ?> <?= htmlspecialchars($course['teacher_name']) ?>
                        </p>
                        <?php if ($course['description']): ?>
                            <p class="text-on-surface-variant text-sm mt-2"><?= htmlspecialchars($course['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="/enroll_action.php">
                    <input type="hidden" name="course_id" value="<?= (int) $course['course_id'] ?>">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-full bg-primary text-on-primary px-4 py-2 text-sm font-semibold hover:bg-primary/90 transition-colors"
                    >
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">add_circle</span>
                        <?= htmlspecialchars(t('enroll.enroll_button')) ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
