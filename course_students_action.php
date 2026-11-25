<?php
/**
 * course_students_action.php
 *
 * Handles the POST from course_students.php (teacher-adds-student
 * direction of enrollment). Never rendered directly. Redirects back to
 * course_students.php?course=ID either way, matching the flash-message
 * convention used by course_create_action.php etc.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /course_students.php');
    exit;
}

$teacherId  = current_user_id();
$courseId   = (int) ($_POST['course_id'] ?? 0);
$identifier = trim($_POST['identifier'] ?? '');

// Ownership check FIRST, before anything else — a teacher must not be
// able to add students to a course that isn't theirs, regardless of
// what identifier they submit.
$courseStmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
$courseStmt->execute([$courseId, $teacherId]);
if (!$courseStmt->fetch()) {
    http_response_code(403);
    die(t('roster.error_forbidden'));
}

$backTo = '/course_students.php?course=' . $courseId;

if ($identifier === '') {
    header('Location: ' . $backTo . '&error=' . urlencode(t('roster.error_no_identifier')));
    exit;
}

// Same email-or-username lookup shape as login_action.php, restricted to
// role = 'student' — a teacher typing another teacher's email here
// should fail with "no such student", not silently enroll a teacher.
$studentStmt = $pdo->prepare(
    "SELECT id, name FROM users WHERE (email = ? OR username = ?) AND role = 'student' LIMIT 1"
);
$studentStmt->execute([$identifier, strtolower($identifier)]);
$student = $studentStmt->fetch();

if (!$student) {
    header('Location: ' . $backTo . '&error=' . urlencode(t('roster.error_no_such_student')));
    exit;
}

try {
    $insertStmt = $pdo->prepare(
        'INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)'
    );
    $insertStmt->execute([$student['id'], $courseId]);
} catch (PDOException $e) {
    // uq_enroll_student_course -> this student is already on the roster.
    error_log('[Bassira] course_students_action insert failed: ' . $e->getMessage());
    header('Location: ' . $backTo . '&error=' . urlencode(t('roster.error_already_enrolled')));
    exit;
}

header('Location: ' . $backTo . '&added=1');
exit;
