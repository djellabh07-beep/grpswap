<?php
/**
 * homework_create_action.php
 *
 * Validates the upload, runs PDF text extraction (same
 * includes/pdf_extract.php pipeline material_upload_action.php uses),
 * and only inserts a `homework` row if extraction produced readable
 * content. Mirrors material_upload_action.php's conventions exactly,
 * with an added due_date field and a separate storage subfolder.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/pdf_extract.php';

const BASSIRA_HW_MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB
const BASSIRA_HOMEWORK_DIR = __DIR__ . '/storage/homework';

function hw_redirect_with_error(string $message): void
{
    header('Location: /homework_create.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /homework_create.php');
    exit;
}

$courseId = (int) ($_POST['course_id'] ?? 0);
$title    = trim((string) ($_POST['title'] ?? ''));
$dueDate  = trim((string) ($_POST['due_date'] ?? ''));

if ($courseId <= 0) {
    hw_redirect_with_error(t('materials.error_no_course'));
}
if ($title === '') {
    hw_redirect_with_error(t('materials.error_no_title'));
}
if ($dueDate === '') {
    hw_redirect_with_error(t('homework_create.error_no_due_date'));
}

$dueDateObj = DateTime::createFromFormat('Y-m-d\TH:i', $dueDate);
if (!$dueDateObj) {
    hw_redirect_with_error(t('homework_create.error_invalid_due_date'));
}
$dueDateSql = $dueDateObj->format('Y-m-d H:i:s');

$courseStmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
$courseStmt->execute([$courseId, current_user_id()]);
if (!$courseStmt->fetch()) {
    hw_redirect_with_error(t('materials.error_not_your_course'));
}

$file = $_FILES['pdf_file'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    hw_redirect_with_error(t('materials.error_no_file'));
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    hw_redirect_with_error(t('materials.error_upload_failed'));
}
if ($file['size'] > BASSIRA_HW_MAX_UPLOAD_BYTES) {
    hw_redirect_with_error(t('materials.error_too_large'));
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$actualMimeType = $finfo->file($file['tmp_name']);
if ($actualMimeType !== 'application/pdf') {
    hw_redirect_with_error(t('materials.error_invalid_type'));
}

if (!is_dir(BASSIRA_HOMEWORK_DIR) && !mkdir(BASSIRA_HOMEWORK_DIR, 0755, true) && !is_dir(BASSIRA_HOMEWORK_DIR)) {
    hw_redirect_with_error(t('materials.error_upload_failed'));
}

$storedFilename = bin2hex(random_bytes(16)) . '.pdf';
$destinationPath = BASSIRA_HOMEWORK_DIR . '/' . $storedFilename;

if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
    hw_redirect_with_error(t('materials.error_upload_failed'));
}

try {
    $contentStructure = extract_pdf_structure($destinationPath);
} catch (PdfExtractionException $e) {
    @unlink($destinationPath);
    hw_redirect_with_error($e->getMessage());
}

$insertStmt = $pdo->prepare(
    'INSERT INTO homework (course_id, title, file_path, content_json, due_date)
     VALUES (?, ?, ?, ?, ?)'
);
$insertStmt->execute([
    $courseId,
    $title,
    'storage/homework/' . $storedFilename,
    json_encode($contentStructure, JSON_UNESCAPED_UNICODE),
    $dueDateSql,
]);

header('Location: /homework_create.php?created=1');
exit;
