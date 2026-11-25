<?php
/**
 * homework_manage_action.php
 *
 * Handles both the "edit" and "delete" forms from homework_manage.php
 * (distinguished by the action field). Delete is a soft-delete
 * (homework.deleted_at), matching materials_manage_action.php's
 * conventions exactly.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /homework_manage.php');
    exit;
}

$teacherId  = current_user_id();
$action     = $_POST['action'] ?? '';
$homeworkId = (int) ($_POST['homework_id'] ?? 0);
$courseId   = (int) ($_POST['course_id'] ?? 0);
$backTo     = '/homework_manage.php?course=' . $courseId;

// Ownership check FIRST — same JOIN-based pattern as
// materials_manage_action.php, never trust course_id from the form alone.
$ownershipStmt = $pdo->prepare(
    'SELECT h.id
     FROM homework h
     JOIN courses c ON c.id = h.course_id
     WHERE h.id = ? AND c.teacher_id = ? AND h.deleted_at IS NULL'
);
$ownershipStmt->execute([$homeworkId, $teacherId]);
if (!$ownershipStmt->fetch()) {
    header('Location: ' . $backTo . '&error=' . urlencode(t('homework_manage.error_not_found')));
    exit;
}

if ($action === 'edit') {
    $title   = trim($_POST['title'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');

    if ($title === '') {
        header('Location: ' . $backTo . '&error=' . urlencode(t('materials.error_no_title')));
        exit;
    }

    $dueDateObj = DateTime::createFromFormat('Y-m-d\TH:i', $dueDate);
    if (!$dueDateObj) {
        header('Location: ' . $backTo . '&error=' . urlencode(t('homework_create.error_invalid_due_date')));
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE homework SET title = ?, due_date = ? WHERE id = ?');
    $updateStmt->execute([$title, $dueDateObj->format('Y-m-d H:i:s'), $homeworkId]);
    header('Location: ' . $backTo . '&done=edited');
    exit;
}

if ($action === 'delete') {
    $deleteStmt = $pdo->prepare('UPDATE homework SET deleted_at = NOW() WHERE id = ?');
    $deleteStmt->execute([$homeworkId]);
    header('Location: ' . $backTo . '&done=deleted');
    exit;
}

header('Location: ' . $backTo);
exit;
