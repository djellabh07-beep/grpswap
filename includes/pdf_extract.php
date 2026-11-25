<?php
/**
 * includes/pdf_extract.php
 *
 * Turns an uploaded PDF into the structured page-and-line JSON shape
 * described in schema.sql's comments on `materials.content_json` /
 * `homework.content_json`:
 *
 *   { "pages": [ { "page_number": 1, "lines": [ {"line_number":1,"text":"..."} ] } ] }
 *
 * -----------------------------------------------------------------
 * Library choice: smalot/pdfparser
 * -----------------------------------------------------------------
 * Recommended (and what this file is built against) because:
 *   - Pure PHP, zero system/shell dependencies. Extracting text via
 *     `pdftotext` (poppler-utils) or Ghostscript is faster/more robust in
 *     absolute terms, but both require a compiled binary the host controls
 *     — not something a teacher's shared/budget hosting plan can usually
 *     install, and shelling out (`exec`/`shell_exec`) is often disabled
 *     outright on shared hosts for security reasons anyway.
 *   - Only needs ext-zlib and ext-iconv, which are effectively always
 *     present in any standard PHP install (both are in this project's
 *     stack already).
 *   - Actively maintained, MIT/LGPL-licensed, handles the vast majority of
 *     "normal" text-layer PDFs (Word/LibreOffice exports, LaTeX, etc.)
 *     that teachers are expected to upload.
 *
 * Shipped VENDORED, not via Composer: the library's source is bundled
 * directly at lib/pdfparser/Smalot/PdfParser/ (its LGPL license file sits
 * alongside it), with a small PSR-0 autoloader registered below. This is
 * deliberate for the same "shared/simple hosting" reasoning as choosing a
 * pure-PHP library in the first place — some budget hosts don't give
 * shell/SSH access to run `composer install`, and this way the project
 * runs with zero build step, just upload-and-go. If your host DOES have
 * Composer available and you'd rather manage this as a real dependency
 * (recommended for easier updates), delete lib/pdfparser/, run
 * `composer require smalot/pdfparser`, and replace the autoloader block
 * below with `require __DIR__ . '/../vendor/autoload.php';`.
 *
 * Known limitation (by design, not a bug): smalot/pdfparser reads the
 * PDF's embedded text layer. A scanned page or a PDF exported from a flat
 * image has no text layer at all — there is nothing to extract, and OCR
 * is a fundamentally different (and much heavier) pipeline that is out of
 * scope here. See extract_pdf_structure()'s return contract below for how
 * that case is surfaced rather than silently swallowed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @throws PdfExtractionException on a corrupt/unreadable file OR a PDF
 *         with no extractable text on ANY page (scanned/image-only).
 *         Callers (material_upload_action.php, homework_upload_action.php)
 *         MUST catch this and show the teacher a clear, specific error —
 *         never silently store a material/homework row with empty content.
 */
class PdfExtractionException extends \Exception
{
}

/**
 * @param string $filePath absolute path to the uploaded PDF on disk.
 * @return array{pages: array<int, array{page_number:int, lines: array<int, array{line_number:int, text:string}>}>}
 */
function extract_pdf_structure(string $filePath): array
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        throw new PdfExtractionException('الملف غير موجود أو لا يمكن قراءته.');
    }

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $document = $parser->parseFile($filePath);
        $pdfPages = $document->getPages();
    } catch (\Throwable $e) {
        // Corrupt PDF, encrypted with no accessible text, unsupported
        // internal structure, etc. — smalot/pdfparser throws a range of
        // exception types here; we don't need to distinguish them for the
        // teacher, just report that this file couldn't be read.
        throw new PdfExtractionException(
            'تعذّرت قراءة هذا الملف كملف PDF صالح. تأكد من أن الملف غير تالف وحاول مرة أخرى.'
        );
    }

    if (count($pdfPages) === 0) {
        throw new PdfExtractionException('هذا الملف لا يحتوي على أي صفحات.');
    }

    $pages = [];
    $totalTextLength = 0;

    foreach ($pdfPages as $index => $pdfPage) {
        $rawText = (string) $pdfPage->getText();
        $lines = segment_page_into_lines($rawText);

        $lineObjects = [];
        foreach ($lines as $lineIndex => $lineText) {
            $lineObjects[] = [
                'line_number' => $lineIndex + 1,
                'text' => $lineText,
            ];
        }

        $pages[] = [
            'page_number' => $index + 1,
            'lines' => $lineObjects,
        ];

        $totalTextLength += strlen(trim($rawText));
    }

    // The core "no extractable text" guard. A PDF with a text layer will
    // have SOME non-whitespace character total across its pages even if
    // one or two pages happen to be blank; a fully scanned/image-only PDF
    // will have effectively nothing on every single page. Rather than
    // storing a material with empty `lines` arrays (which would make
    // Bassira "read" a silent nothing to the student — confusing and
    // undetectable until a student hits it live), this is treated as a
    // hard failure the teacher must see immediately at upload time.
    if ($totalTextLength === 0) {
        throw new PdfExtractionException(
            'لم يتم العثور على أي نص قابل للقراءة في هذا الملف. '
            . 'يبدو أن هذا الملف عبارة عن صفحات ممسوحة ضوئياً (صور) وليس نصاً رقمياً. '
            . 'يرجى رفع نسخة نصية من المستند (مثلاً بتصديرها مباشرة من Word)، '
            . 'حيث لا يدعم هذا النظام حالياً استخراج النص من الصور الممسوحة ضوئياً (OCR).'
        );
    }

    return ['pages' => $pages];
}

/**
 * Splits one page's raw extracted text into sentence-level lines.
 *
 * PDF text extraction line breaks are a layout artifact — wherever the
 * original document happened to wrap a line or place a new text-drawing
 * instruction — not sentence boundaries. A sentence can easily span two
 * "lines" as PdfParser sees them, and a single line can contain multiple
 * short sentences. Since Bassira's voice navigation needs to read and
 * seek by actual sentence ("read the previous line" should mean the
 * previous sentence, not an arbitrary mid-sentence wrap point), this
 * collapses the page to one text blob first and re-splits on real
 * sentence-ending punctuation.
 *
 * Covers Latin `. ! ?` and Arabic `؟` (the only Arabic-specific
 * sentence-final mark in common use; Arabic otherwise reuses `.` and `!`).
 */
function segment_page_into_lines(string $rawText): array
{
    $flat = preg_replace('/\s+/u', ' ', $rawText);
    $flat = trim((string) $flat);

    if ($flat === '') {
        return [];
    }

    $parts = preg_split('/(?<=[.!?؟])\s+/u', $flat, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        // preg_split failing (e.g. on invalid UTF-8) — fall back to
        // treating the whole page as a single line rather than losing
        // the content entirely.
        return [$flat];
    }

    $lines = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $lines[] = $part;
        }
    }

    // Guard against pages where no punctuation was found at all (e.g. a
    // title-only page, or a page using unusual sentence-final marks) —
    // still return the whole flattened blob as one line rather than an
    // empty array, so nothing gets silently dropped.
    return $lines ?: [$flat];
}
