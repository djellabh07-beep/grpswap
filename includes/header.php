<?php
/**
 * includes/header.php
 *
 * Opens the HTML document: <html>/<head>/<body>, the fixed top bar, the
 * sidebar nav, and the opening <main> tag that page content is written
 * into. Every protected page pairs this with includes/footer.php at the
 * end of the file.
 *
 * Expects the including page to have ALREADY called:
 *   require_once __DIR__ . '/includes/auth.php';   require_login();
 *   require_once __DIR__ . '/includes/db.php';     // if the page queries
 *                                                   // $pdo before this point
 *   require_once __DIR__ . '/includes/i18n.php';
 * and to have set, before requiring this file:
 *   $page      = 'courses';           // required — see nav.php for valid values
 *   $pageTitle = t('courses.title');  // optional — falls back to app name
 *
 * This file also requires includes/db.php itself (idempotent via
 * require_once) since it needs $pdo for the BASSIRA_COURSES directory
 * below — but any page that queries $pdo for its OWN content before
 * including this file (auth/ownership checks, fetching what to render)
 * must require db.php itself first; it can't rely on this file for that.
 *
 * Design system (colors, spacing, type scale, Material Symbols, the
 * pulse/soundwave/active-glow micro-animations used by the voice UI) is
 * copied as-is from the existing frontend's courses.html so nothing shifts
 * visually when pages move from static HTML to PHP includes.
 */

require_once __DIR__ . '/db.php'; // guarantees $pdo — needed below, and by
                                   // nearly every real page anyway, so this
                                   // is no longer left for each page to remember.

if (!isset($page)) {
    // Fail loud in development rather than silently rendering a sidebar
    // with no active item — every real page must set this.
    trigger_error('header.php included without $page being set', E_USER_WARNING);
    $page = '';
}

$pageTitle = $pageTitle ?? t('app.name');
$htmlDir   = current_dir();
$htmlLang  = current_lang();

// Arabic uses the Cairo-based "arabic-body" type scale from the design
// system; French/English use the Inter-based "body-md" scale. Both are
// already defined in the shared Tailwind config below — this just picks
// which one applies to <body> for the current session language.
$bodyFontClass = $htmlLang === 'ar' ? 'font-arabic-body' : 'font-body-md';

// First letter of the user's name for the avatar badge in the top bar —
// avoids relying on an external hosted photo for every account.
$avatarInitial = mb_strtoupper(mb_substr((string) (current_user_name() ?? '?'), 0, 1));

// ---------------------------------------------------------------------
// Enrolled-course directory for the voice engine's GLOBAL "open course by
// name" command ("بصيرة، افتحي [اسم المقرر]"). This is injected on EVERY
// page (not just courses.php) deliberately: it's registered as a global
// command in voice_assistant.js, consistent with every other navigation-
// type command in this app already being reachable from anywhere — a
// student shouldn't have to first go to courses.php just to open a course
// by name. Two plain queries (not a JOIN + JSON_ARRAYAGG) on purpose:
// JSON aggregation functions aren't reliably available across older
// MySQL/MariaDB versions on shared hosting, and two small indexed queries
// are cheap regardless.
//
// Wrapped in a function deliberately: `require` shares the including
// page's variable scope, so any local variable declared directly in this
// file's top level (as an earlier draft did — $materialsByCourse,
// $courseStmt, $studentId, etc.) can silently clobber an identically-
// named variable the including page already set, since PHP has no way to
// know the two were meant to be unrelated. That's exactly what happened
// here during testing: courses.php builds its OWN $materialsByCourse
// (carrying last_page/last_line for the visual resume label) BEFORE
// calling require on this file — and this file's later, differently-
// shaped $materialsByCourse (only id/title, for the JS payload) silently
// overwrote it, breaking the resume label. Confining all of this
// function's locals to its own scope makes that whole bug class
// impossible, regardless of what variable names any page happens to use.
// ---------------------------------------------------------------------
function bassira_build_course_directory(PDO $pdo, ?int $studentId): array
{
    if ($studentId === null) {
        return [];
    }

    $courseStmt = $pdo->prepare(
        'SELECT c.id, c.name
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.student_id = ?
         ORDER BY c.name'
    );
    $courseStmt->execute([$studentId]);
    $enrolledCourses = $courseStmt->fetchAll();

    if (!$enrolledCourses) {
        return [];
    }

    $courseIds = array_column($enrolledCourses, 'id');
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

    $materialStmt = $pdo->prepare(
        "SELECT id, course_id, title
         FROM materials
         WHERE course_id IN ($placeholders) AND deleted_at IS NULL
         ORDER BY uploaded_at"
    );
    $materialStmt->execute($courseIds);

    $materialsByCourse = [];
    foreach ($materialStmt->fetchAll() as $m) {
        $materialsByCourse[$m['course_id']][] = [
            'id' => (int) $m['id'],
            'title' => $m['title'],
        ];
    }

    $directory = [];
    foreach ($enrolledCourses as $c) {
        $directory[] = [
            'id' => (int) $c['id'],
            'name' => $c['name'],
            'materials' => $materialsByCourse[$c['id']] ?? [],
        ];
    }

    return $directory;
}

$bassiraCoursesForJs = current_user_role() === 'student'
    ? bassira_build_course_directory($pdo, current_user_id())
    : [];
?>
<!DOCTYPE html>
<html dir="<?= htmlspecialchars($htmlDir) ?>" lang="<?= htmlspecialchars($htmlLang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(t('app.name')) ?></title>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@600;700&family=Inter:wght@400;600&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Poppins:wght@100..900&display=swap">

<script>window.BASSIRA_COURSES = <?= json_encode($bassiraCoursesForJs, JSON_UNESCAPED_UNICODE) ?>;</script>
<script>window.BASSIRA_SPEECH_RATE = <?= json_encode((float) ($_SESSION['speech_rate'] ?? 1.0)) ?>;</script>

<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "tertiary-container": "#4c3706",
                    "on-tertiary-fixed-variant": "#594312",
                    "inverse-surface": "#2f2e43",
                    "on-tertiary-container": "#bfa066",
                    "on-secondary-fixed": "#00201c",
                    "surface-tint": "#425e91",
                    "on-error": "#ffffff",
                    "outline-variant": "#c4c6d0",
                    "primary-container": "#1b3a6b",
                    "secondary-fixed-dim": "#6fd8c8",
                    "on-tertiary-fixed": "#261900",
                    "on-tertiary": "#ffffff",
                    "tertiary": "#322200",
                    "primary": "#002452",
                    "secondary-container": "#8cf5e4",
                    "on-secondary": "#ffffff",
                    "on-surface": "#1a1a2e",
                    "on-secondary-fixed-variant": "#005048",
                    "error": "#ba1a1a",
                    "secondary-fixed": "#8cf5e4",
                    "on-error-container": "#93000a",
                    "on-primary-container": "#89a5dd",
                    "background": "#fcf8ff",
                    "inverse-on-surface": "#f2efff",
                    "outline": "#747780",
                    "primary-fixed": "#d7e2ff",
                    "on-primary-fixed-variant": "#294678",
                    "on-primary-fixed": "#001a40",
                    "on-secondary-container": "#007166",
                    "surface-container-highest": "#e2e0fc",
                    "on-surface-variant": "#44474f",
                    "surface-container-low": "#f5f2ff",
                    "surface": "#fcf8ff",
                    "error-container": "#ffdad6",
                    "tertiary-fixed-dim": "#e3c285",
                    "tertiary-fixed": "#ffdea3",
                    "surface-variant": "#e2e0fc",
                    "surface-container-high": "#e8e5ff",
                    "surface-container": "#efecff",
                    "surface-container-lowest": "#ffffff",
                    "on-background": "#1a1a2e",
                    "surface-dim": "#dad7f3",
                    "surface-bright": "#fcf8ff",
                    "primary-fixed-dim": "#acc7ff",
                    "secondary": "#006a60",
                    "on-primary": "#ffffff",
                    "inverse-primary": "#acc7ff",
                    "brand-teal": "#2A9D8F",
                    "brand-teal-light": "#E8F7F5"
                },
                "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
                },
                "spacing": {
                    "margin-desktop": "64px",
                    "container-max": "1280px",
                    "touch-target": "48px",
                    "margin-mobile": "16px",
                    "gutter": "24px",
                    "unit": "8px"
                },
                "fontFamily": {
                    "headline-sm": ["Poppins"],
                    "headline-md": ["Poppins"],
                    "arabic-h1": ["Cairo"],
                    "body-md": ["Inter"],
                    "display-lg": ["Poppins"],
                    "display-lg-mobile": ["Poppins"],
                    "body-lg": ["Inter"],
                    "label-md": ["Inter"],
                    "arabic-body": ["Cairo"]
                },
                "fontSize": {
                    "headline-sm": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                    "headline-md": ["32px", {"lineHeight": "1.3", "fontWeight": "600"}],
                    "arabic-h1": ["36px", {"lineHeight": "1.4", "fontWeight": "700"}],
                    "body-md": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}],
                    "display-lg": ["48px", {"lineHeight": "1.2", "fontWeight": "700"}],
                    "display-lg-mobile": ["36px", {"lineHeight": "1.2", "fontWeight": "700"}],
                    "body-lg": ["20px", {"lineHeight": "1.6", "fontWeight": "400"}],
                    "label-md": ["16px", {"lineHeight": "1.4", "letterSpacing": "0.02em", "fontWeight": "600"}],
                    "arabic-body": ["20px", {"lineHeight": "1.8", "fontWeight": "400"}]
                }
            }
        }
    }
</script>

<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .pulse-animation {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(0.98); }
    }
    .soundwave-bar {
        animation: wave 1.2s ease-in-out infinite;
    }
    @keyframes wave {
        0%, 100% { height: 8px; }
        50% { height: 24px; }
    }
    .soundwave-bar:nth-child(2) { animation-delay: 0.1s; }
    .soundwave-bar:nth-child(3) { animation-delay: 0.2s; }
    .soundwave-bar:nth-child(4) { animation-delay: 0.3s; }
    .active-glow {
        box-shadow: 0 0 15px rgba(0, 106, 96, 0.1);
    }
</style>

<?php
/**
 * Loaded with `defer` so it downloads in parallel with parsing but always
 * executes after the DOM (and therefore after any page-specific inline
 * script that sets window.pageVoiceCommands, since those run in document
 * order during parsing) — see assets/js/voice_assistant.js's usage note
 * for the ordering contract pages must follow.
 */
?>
<script src="/assets/js/voice_assistant.js" defer></script>
</head>
<body class="bg-background text-on-surface <?= htmlspecialchars($bodyFontClass) ?> selection:bg-secondary-container">

<!-- Watermark background layer, preserved from the original design -->
<div class="fixed inset-0 pointer-events-none z-0 opacity-[0.06]" aria-hidden="true"></div>

<?php require __DIR__ . '/nav.php'; ?>

<!-- Top bar: language shortcut + account -->
<header class="fixed top-0 start-0 end-0 h-touch-target bg-background/80 backdrop-blur-md z-50 flex justify-between items-center px-gutter md:pe-24">
    <div class="flex items-center gap-4">
        <a
            href="/settings.php"
            class="flex items-center gap-2 px-3 py-1 rounded-full hover:bg-surface-container-low transition-colors text-on-surface-variant"
            aria-label="<?= htmlspecialchars(t('lang.switch_label')) ?>: <?= htmlspecialchars(t('lang.' . $htmlLang)) ?>"
        >
            <span class="material-symbols-outlined" aria-hidden="true">translate</span>
            <span class="font-label-md text-label-md"><?= htmlspecialchars(t('lang.' . $htmlLang)) ?></span>
        </a>
    </div>

    <div class="flex items-center gap-3">
        <span class="font-label-md text-label-md text-on-surface-variant hidden sm:inline">
            <?= htmlspecialchars((string) (current_user_name() ?? '')) ?>
        </span>
        <div
            class="w-10 h-10 rounded-full overflow-hidden border-2 border-outline-variant flex items-center justify-center bg-primary-container text-on-primary-container font-headline-sm"
            aria-hidden="true"
        ><?= htmlspecialchars($avatarInitial) ?></div>
        <a
            href="/logout.php"
            class="flex items-center justify-center h-10 w-10 rounded-full text-on-surface-variant hover:bg-surface-container-low transition-colors"
            aria-label="<?= htmlspecialchars(t('auth.logout_button')) ?>"
        ><span class="material-symbols-outlined" aria-hidden="true">logout</span></a>
    </div>
</header>

<!--
    Main content region. Offset from the fixed top bar (pt-touch-target)
    and from the fixed sidebar (me-20, logical "margin-inline-end" so it
    sits opposite the nav correctly in both RTL and LTR). Pages write
    their content directly after including this file and close it off
    with includes/footer.php.
-->
<main id="main-content" class="relative z-10 pt-touch-target me-20 min-h-screen">
