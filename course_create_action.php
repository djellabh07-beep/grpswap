<?php
/**
 * course_create_action.php
 *
 * Handles the POST from course_create.php. Never rendered directly.
 * Redirects back to course_create.php on failure (with a pre-translated
 * error message) or to courses.php on success.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /course_create.php');
    exit;
}

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($name === '') {
    header('Location: /course_create.php?error=' . urlencode(t('courses.error_no_name')));
    exit;
}

$insertStmt = $pdo->prepare(
    'INSERT INTO courses (teacher_id, name, description) VALUES (?, ?, ?)'
);
$insertStmt->execute([
    current_user_id(),
    $name,
    $description !== '' ? $description : null,
]);

header('Location: /course_create.php?created=1');
exit;
