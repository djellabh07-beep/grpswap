<?php
/**
 * materials_manage_action.php
 *
 * Handles both the "edit title" and "delete" forms from
 * materials_manage.php (distinguished by the action field). Delete is a
 * soft-delete (materials.deleted_at), not a real row removal — matches
 * the schema's own deleted_at column design and keeps the file/content
 * around in case of an accidental delete.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /materials_manage.php');
    exit;
}

$teacherId  = current_user_id();
$action     = $_POST['action'] ?? '';
$materialId = (int) ($_POST['material_id'] ?? 0);
$courseId   = (int) ($_POST['course_id'] ?? 0);
$backTo     = '/materials_manage.php?course=' . $courseId;

// Ownership check FIRST — the material's course must belong to this
// teacher, checked via a JOIN so a crafted material_id from another
// teacher's course can't be edited/deleted just because course_id in
// the form happens to be one of THIS teacher's own courses.
$ownershipStmt = $pdo->prepare(
    'SELECT m.id
     FROM materials m
     JOIN courses c ON c.id = m.course_id
     WHERE m.id = ? AND c.teacher_id = ? AND m.deleted_at IS NULL'
);
$ownershipStmt->execute([$materialId, $teacherId]);
if (!$ownershipStmt->fetch()) {
    header('Location: ' . $backTo . '&error=' . urlencode(t('materials_manage.error_not_found')));
    exit;
}

if ($action === 'edit') {
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        header('Location: ' . $backTo . '&error=' . urlencode(t('materials.error_no_title')));
        exit;
    }
    $updateStmt = $pdo->prepare('UPDATE materials SET title = ? WHERE id = ?');
    $updateStmt->execute([$title, $materialId]);
    header('Location: ' . $backTo . '&done=edited');
    exit;
}

if ($action === 'delete') {
    $deleteStmt = $pdo->prepare('UPDATE materials SET deleted_at = NOW() WHERE id = ?');
    $deleteStmt->execute([$materialId]);
    header('Location: ' . $backTo . '&done=deleted');
    exit;
}

header('Location: ' . $backTo);
exit;
