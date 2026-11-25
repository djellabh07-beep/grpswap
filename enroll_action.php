<?php
/**
 * enroll_action.php
 *
 * Handles the POST from enroll.php (student self-enrollment direction).
 * Never rendered directly. Redirects back to enroll.php either way, with
 * a flash query param — same convention as course_create_action.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('student');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /enroll.php');
    exit;
}

$studentId = current_user_id();
$courseId  = (int) ($_POST['course_id'] ?? 0);

if ($courseId <= 0) {
    header('Location: /enroll.php?error=' . urlencode(t('enroll.error_invalid_course')));
    exit;
}

// Confirm the course actually exists before inserting — a raw INSERT
// alone would fail on the enrollments.course_id foreign key anyway, but
// checking first lets us show a translated message instead of a raw
// SQL error surfacing to the browser.
$courseStmt = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
$courseStmt->execute([$courseId]);
if (!$courseStmt->fetch()) {
    header('Location: /enroll.php?error=' . urlencode(t('enroll.error_invalid_course')));
    exit;
}

try {
    $insertStmt = $pdo->prepare(
        'INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)'
    );
    $insertStmt->execute([$studentId, $courseId]);
} catch (PDOException $e) {
    // uq_enroll_student_course means a duplicate enroll attempt (e.g. a
    // double-submitted form) throws here rather than silently
    // double-inserting. Any other DB error is a real failure — log it
    // and show a generic message either way, never the raw SQL error.
    error_log('[Bassira] enroll_action insert failed: ' . $e->getMessage());
    header('Location: /enroll.php?error=' . urlencode(t('enroll.error_already_enrolled')));
    exit;
}

header('Location: /enroll.php?enrolled=1');
exit;
