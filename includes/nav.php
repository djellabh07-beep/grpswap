<?php
/**
 * includes/nav.php
 *
 * Shared right-aligned (in RTL) / left-aligned (in LTR) icon sidebar,
 * lifted from the existing frontend's courses.html nav markup and made
 * data-driven + translated.
 *
 * Usage — set $page BEFORE including this file:
 *   $page = 'courses'; // one of: index, courses, notes, homework, messages, settings
 *   require __DIR__ . '/includes/nav.php';
 *
 * No dashboard link — per project decision, this build has no dashboard
 * page, so it's not in the item list below at all (not just hidden).
 *
 * Direction: the original frontend hardcoded physical sides ("right-0",
 * "border-l") because it only ever rendered RTL Arabic. Since this app
 * also serves LTR French/English sessions, the classes below use Tailwind's
 * logical-property utilities (start, end, border-s, border-e) instead, so
 * the exact same markup mirrors automatically based on the <html dir="">
 * set in header.php — no separate LTR stylesheet or JS flip needed.
 */

if (!isset($page)) {
    $page = '';
}

// icon: Material Symbols Outlined ligature name (unchanged from the
// existing frontend's icon set).
// label: translation key, resolved with t() below.
// href: real .php file — no "#" placeholders, per the "real navigation,
// no SPA router" requirement.
$bassiraNavItemsTop = [
    ['page' => 'index',    'href' => '/index.php',    'icon' => 'home',           'label' => 'nav.home'],
    ['page' => 'courses',  'href' => '/courses.php',  'icon' => 'library_books',  'label' => 'nav.courses'],
    ['page' => 'notes',    'href' => '/notes.php',    'icon' => 'description',    'label' => 'nav.notes'],
    ['page' => 'homework', 'href' => '/homework.php', 'icon' => 'assignment',     'label' => 'nav.homework'],
    ['page' => 'messages', 'href' => '/messages.php', 'icon' => 'chat',           'label' => 'nav.messages'],
];

if (current_user_role() === 'student') {
    $bassiraNavItemsTop[] = ['page' => 'enroll',  'href' => '/enroll.php',  'icon' => 'add_circle',  'label' => 'nav.browse_courses'];
}

// Teacher-only items — these pages don't exist for students at all, so
// they're only added to the nav when the logged-in user is a teacher
// (checked here, not just hidden with CSS, since require_role('teacher')
// on the target pages already enforces this server-side too).
if (current_user_role() === 'teacher') {
    $bassiraNavItemsTop[] = ['page' => 'course_create',    'href' => '/course_create.php',    'icon' => 'add_circle',   'label' => 'nav.create_course'];
    $bassiraNavItemsTop[] = ['page' => 'material_upload',  'href' => '/material_upload.php',  'icon' => 'upload_file',  'label' => 'nav.upload_material'];
    $bassiraNavItemsTop[] = ['page' => 'homework_create',  'href' => '/homework_create.php',  'icon' => 'post_add',     'label' => 'nav.create_homework'];
    $bassiraNavItemsTop[] = ['page' => 'course_students',  'href' => '/course_students.php',  'icon' => 'group',        'label' => 'nav.roster'];
    $bassiraNavItemsTop[] = ['page' => 'materials_manage',  'href' => '/materials_manage.php',  'icon' => 'edit_note',    'label' => 'nav.manage_materials'];
    $bassiraNavItemsTop[] = ['page' => 'homework_manage',  'href' => '/homework_manage.php',  'icon' => 'fact_check',   'label' => 'nav.manage_homework'];
    $bassiraNavItemsTop[] = ['page' => 'grading',  'href' => '/grading.php',  'icon' => 'grading',   'label' => 'nav.grading'];
}

$bassiraNavItemsBottom = [
    ['page' => 'settings', 'href' => '/settings.php', 'icon' => 'settings', 'label' => 'nav.settings'],
];

/**
 * Renders one nav link. Kept as a local function (not a separate file)
 * since it's only ever used here, twice.
 */
function bassira_render_nav_item(array $item, string $currentPage): void
{
    $isActive = ($item['page'] === $currentPage);

    $baseClasses = 'flex items-center justify-center h-12 w-full transition-colors';
    $classes = $isActive
        ? $baseClasses . ' text-primary border-e-4 border-tertiary-fixed-dim bg-tertiary-fixed/10'
        : $baseClasses . ' text-on-surface-variant hover:bg-primary-container/10';

    $iconFill = $isActive ? " style=\"font-variation-settings: 'FILL' 1;\"" : '';
    $ariaCurrent = $isActive ? ' aria-current="page"' : '';
    ?>
    <a
        class="<?= htmlspecialchars($classes) ?>"
        href="<?= htmlspecialchars($item['href']) ?>"
        aria-label="<?= htmlspecialchars(t($item['label'])) ?>"<?= $ariaCurrent ?>
    ><span class="material-symbols-outlined"<?= $iconFill ?>><?= htmlspecialchars($item['icon']) ?></span></a>
    <?php
}
?>
<nav class="fixed start-0 top-0 h-full w-20 bg-surface-container-lowest dark:bg-inverse-surface border-s border-outline-variant/20 flex flex-col py-8 z-40 shadow-sm transition-transform duration-200 overflow-y-auto">
    <div class="flex flex-col items-center gap-8 h-full">
        <div class="w-12 h-12 flex items-center justify-center">
            <span class="text-primary font-headline-sm text-headline-sm" aria-hidden="true">B</span>
        </div>

        <div class="flex flex-col gap-4 w-full flex-grow">
            <?php foreach ($bassiraNavItemsTop as $item): ?>
                <?php bassira_render_nav_item($item, $page); ?>
            <?php endforeach; ?>
        </div>

        <div class="mt-auto flex flex-col gap-4 w-full">
            <?php foreach ($bassiraNavItemsBottom as $item): ?>
                <?php bassira_render_nav_item($item, $page); ?>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
