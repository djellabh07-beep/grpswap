<?php
/**
 * material_upload_action.php
 *
 * Validates the upload, runs PDF text extraction (includes/pdf_extract.php),
 * and only inserts a `materials` row if extraction actually produced
 * readable content — see extract_pdf_structure()'s contract. On any
 * failure (bad input, wrong file type, extraction failure), the teacher is
 * redirected back to the form with a specific, translated error message
 * and nothing is written to the database or left on disk.
 */

require_once __DIR__ . '/includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/pdf_extract.php';

const BASSIRA_MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB
const BASSIRA_MATERIALS_DIR = __DIR__ . '/storage/materials';

function redirect_with_error(string $message): void
{
    header('Location: /material_upload.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /material_upload.php');
    exit;
}

$courseId = (int) ($_POST['course_id'] ?? 0);
$title    = trim((string) ($_POST['title'] ?? ''));

if ($courseId <= 0) {
    redirect_with_error(t('materials.error_no_course'));
}
if ($title === '') {
    redirect_with_error(t('materials.error_no_title'));
}

// The course must actually belong to this teacher — never trust the
// posted course_id blindly, a teacher could otherwise attach a file to
// someone else's course by editing the form.
$courseStmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
$courseStmt->execute([$courseId, current_user_id()]);
if (!$courseStmt->fetch()) {
    redirect_with_error(t('materials.error_not_your_course'));
}

// ---- File validation ----
$file = $_FILES['pdf_file'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    redirect_with_error(t('materials.error_no_file'));
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    // Covers UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_CANT_WRITE, etc.
    redirect_with_error(t('materials.error_upload_failed'));
}
if ($file['size'] > BASSIRA_MAX_UPLOAD_BYTES) {
    redirect_with_error(t('materials.error_too_large'));
}

// Check the ACTUAL file content type, not just the client-supplied
// filename/extension or the browser-supplied MIME type in $_FILES (both
// are trivially spoofable) — finfo inspects the real file bytes.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$actualMimeType = $finfo->file($file['tmp_name']);
if ($actualMimeType !== 'application/pdf') {
    redirect_with_error(t('materials.error_invalid_type'));
}

// ---- Move to permanent storage under a non-guessable name ----
// The original filename is never trusted/reused (path traversal, weird
// characters, collisions) — a fresh random name plus a fixed .pdf
// extension is used instead.
if (!is_dir(BASSIRA_MATERIALS_DIR) && !mkdir(BASSIRA_MATERIALS_DIR, 0755, true) && !is_dir(BASSIRA_MATERIALS_DIR)) {
    redirect_with_error(t('materials.error_upload_failed'));
}

$storedFilename = bin2hex(random_bytes(16)) . '.pdf';
$destinationPath = BASSIRA_MATERIALS_DIR . '/' . $storedFilename;

if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
    redirect_with_error(t('materials.error_upload_failed'));
}

// ---- Extract structured content ----
try {
    $contentStructure = extract_pdf_structure($destinationPath);
} catch (PdfExtractionException $e) {
    // Extraction failed (corrupt file, or — most commonly — a scanned/
    // image-only PDF with no text layer at all). Per the spec: never
    // silently store a material with empty content. Clean up the file
    // that was just saved and report the SPECIFIC reason back to the
    // teacher, not a generic failure.
    @unlink($destinationPath);
    redirect_with_error($e->getMessage());
}

// ---- Persist ----
$insertStmt = $pdo->prepare(
    'INSERT INTO materials (course_id, title, file_path, content_json, uploaded_at)
     VALUES (?, ?, ?, ?, NOW())'
);
$insertStmt->execute([
    $courseId,
    $title,
    'storage/materials/' . $storedFilename,
    json_encode($contentStructure, JSON_UNESCAPED_UNICODE),
]);

header('Location: /material_upload.php?uploaded=1');
exit;
