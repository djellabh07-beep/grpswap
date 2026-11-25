<?php
/**
 * messages.php
 *
 * Two completely different interfaces behind one role check, per spec:
 *   - Student: voice-driven. "بصيرة اقرئي رسائلي" reads unread messages
 *     aloud oldest-first, marking each read as it goes. "بصيرة أرسلي
 *     رسالة إلى [اسم الأستاذ]" dictates a message to one of the
 *     student's own teachers, with an echo -> confirm step before send
 *     (see assets/js/messages.js's top comment for why that's a manual
 *     confirm sequence rather than the engine's destructive flag).
 *   - Teacher: plain typed inbox + compose form, no voice at all —
 *     teachers are sighted, per spec, so there's nothing here that needs
 *     to go through Bassira.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$page      = 'messages';
$pageTitle = t('messages.title');
$userId    = current_user_id();

// =====================================================================
// Teacher branch — plain typed interface, no voice
// =====================================================================
if (current_user_role() === 'teacher') {
    // Fetch the inbox BEFORE marking anything read, so the page can still
    // visually flag which messages were unread at the moment it loaded
    // (familiar inbox behavior — like opening Gmail: the bold/unread
    // styling reflects what was true when you opened it, even though the
    // messages are now marked read server-side).
    $inboxStmt = $pdo->prepare(
        'SELECT m.id, m.body, m.sent_at, m.read_at, u.name AS sender_name
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.recipient_id = ?
         ORDER BY m.sent_at DESC'
    );
    $inboxStmt->execute([$userId]);
    $inbox = $inboxStmt->fetchAll();

    $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE recipient_id = ? AND read_at IS NULL')
        ->execute([$userId]);

    $studentsStmt = $pdo->prepare(
        'SELECT DISTINCT u.id, u.name
         FROM courses c
         JOIN enrollments e ON e.course_id = c.id
         JOIN users u ON u.id = e.student_id
         WHERE c.teacher_id = ?
         ORDER BY u.name'
    );
    $studentsStmt->execute([$userId]);
    $students = $studentsStmt->fetchAll();

    $flashSent  = isset($_GET['sent']);
    $flashError = $_GET['error'] ?? null;
    ?>
    <?php require __DIR__ . '/includes/header.php'; ?>
    <script>window.pageVoiceCommands = {};</script>

    <div class="p-gutter py-12 max-w-3xl mx-auto">
        <h1 class="font-headline-md text-headline-md text-primary mb-8"><?= htmlspecialchars(t('messages.title')) ?></h1>

        <?php if ($flashSent): ?>
            <div role="status" class="mb-6 p-4 rounded bg-secondary-container/40 text-on-secondary-container">
                <?= htmlspecialchars(t('messages.sent_success')) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div role="alert" class="mb-6 p-4 rounded bg-error-container text-on-error-container">
                <?= htmlspecialchars($flashError) ?>
            </div>
        <?php endif; ?>

        <!-- Compose -->
        <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm mb-10">
            <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('messages.compose_title')) ?></h2>

            <?php if (!$students): ?>
                <p class="text-on-surface-variant"><?= htmlspecialchars(t('courses.no_courses')) ?></p>
            <?php else: ?>
                <form method="post" action="/messages_send_action.php" class="space-y-4">
                    <div>
                        <label for="student_id" class="block mb-2 font-medium"><?= htmlspecialchars(t('messages.recipient_label')) ?></label>
                        <select id="student_id" name="student_id" required class="w-full rounded border-outline-variant p-3">
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="body" class="block mb-2 font-medium"><?= htmlspecialchars(t('messages.body_label')) ?></label>
                        <textarea id="body" name="body" required rows="4" class="w-full rounded border-outline-variant p-3"></textarea>
                    </div>
                    <button type="submit" class="rounded bg-primary text-on-primary font-semibold px-6 py-3">
                        <?= htmlspecialchars(t('messages.send_button')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Inbox -->
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('messages.title')) ?></h2>
        <?php if (!$inbox): ?>
            <p class="text-on-surface-variant"><?= htmlspecialchars(t('messages.no_messages')) ?></p>
        <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($inbox as $m): ?>
                    <li class="p-4 rounded-lg border border-outline-variant/20 <?= !$m['read_at'] ? 'bg-primary-container/10' : '' ?>">
                        <div class="flex items-center justify-between gap-3 mb-1 flex-wrap">
                            <span class="font-semibold"><?= htmlspecialchars(t('messages.from_label')) ?> <?= htmlspecialchars($m['sender_name']) ?></span>
                            <span class="flex items-center gap-2">
                                <?php if (!$m['read_at']): ?>
                                    <span class="text-xs font-semibold px-2 py-1 rounded-full bg-primary text-white"><?= htmlspecialchars(t('messages.unread_badge')) ?></span>
                                <?php endif; ?>
                                <span class="text-xs text-on-surface-variant"><?= htmlspecialchars($m['sent_at']) ?></span>
                            </span>
                        </div>
                        <p class="text-on-surface"><?= htmlspecialchars($m['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php
    exit;
}

// =====================================================================
// Student branch — voice-driven
// =====================================================================
$unreadStmt = $pdo->prepare(
    'SELECT m.id, m.body, u.name AS sender_name
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.recipient_id = ? AND m.read_at IS NULL
     ORDER BY m.sent_at ASC'
);
$unreadStmt->execute([$userId]);
$unreadMessages = $unreadStmt->fetchAll();

// The student's own teachers — teachers of courses they're enrolled in —
// are who "أرسلي رسالة إلى [اسم الأستاذ]" is allowed to name.
$teachersStmt = $pdo->prepare(
    'SELECT DISTINCT u.id, u.name
     FROM courses c
     JOIN enrollments e ON e.course_id = c.id
     JOIN users u ON u.id = c.teacher_id
     WHERE e.student_id = ?
     ORDER BY u.name'
);
$teachersStmt->execute([$userId]);
$teachers = $teachersStmt->fetchAll();

$messagesForJs = [
    'unread' => array_map(function ($m) {
        return ['id' => (int) $m['id'], 'senderName' => $m['sender_name'], 'body' => $m['body']];
    }, $unreadMessages),
];

$teachersForJs = array_map(function ($t) {
    return ['id' => (int) $t['id'], 'name' => $t['name']];
}, $teachers);

$messagesStringsForJs = [
    'readingCount' => t('voice.reading_unread_messages'),
    'noUnread' => t('voice.no_unread_messages'),
    'fromLabel' => t('messages.from_label'),
    'dictationStart' => t('voice.dictation_start'),
    'bodyNotCaptured' => t('messages.body_not_captured'),
    'sendEcho' => t('messages.send_echo'),
    'confirmPrompt' => t('voice.confirm_prompt'),
    'confirmCancelled' => t('voice.confirm_cancelled'),
    'confirmTimeout' => t('voice.confirm_timeout'),
    'sentSuccess' => t('voice.message_sent'),
    'sendFailed' => t('messages.send_failed'),
];
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<script>
window.BASSIRA_MESSAGES = <?= json_encode($messagesForJs, JSON_UNESCAPED_UNICODE) ?>;
window.BASSIRA_TEACHERS = <?= json_encode($teachersForJs, JSON_UNESCAPED_UNICODE) ?>;
window.BASSIRA_MESSAGES_STRINGS = <?= json_encode($messagesStringsForJs, JSON_UNESCAPED_UNICODE) ?>;

window.pageVoiceCommands = [
    {
        patterns: ['اقرئي رسائلي', 'اقرأي رسائلي'],
        destructive: false,
        description: <?= json_encode(t('messages.cmd_read')) ?>,
        handler: () => { Messages.readUnread(); },
    },
    {
        // Recipient is named right in the trigger utterance itself
        // ("أرسلي رسالة إلى فلان") — matched against this student's own
        // teachers (window.BASSIRA_TEACHERS), same course-name-matching
        // pattern used elsewhere in the app (courses.php's global open
        // command, notes.php's course-open command).
        match: (text) => {
            if (text.indexOf('أرسل') === -1) return null;
            const teacher = (window.BASSIRA_TEACHERS || []).find(
                (t) => t.name && text.indexOf(t.name) !== -1
            );
            return teacher ? { teacherId: teacher.id, teacherName: teacher.name } : null;
        },
        destructive: false, // manual dictate -> echo -> confirm sequence lives in messages.js instead
        description: <?= json_encode(t('messages.cmd_send')) ?>,
        handler: (args) => { Messages.sendMessage(args.teacherId, args.teacherName); },
    },
];
</script>

<div class="p-gutter py-12 max-w-3xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-primary mb-2"><?= htmlspecialchars(t('messages.title')) ?></h1>

    <div class="mb-8 p-4 rounded-xl bg-primary text-white flex items-center gap-3">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
        <span class="font-arabic-body font-bold" role="status" aria-live="polite"><?= htmlspecialchars(t('messages.assistant_listening')) ?></span>
    </div>

    <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm mb-8">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('messages.my_teachers')) ?></h2>
        <?php if (!$teachers): ?>
            <p class="text-on-surface-variant"><?= htmlspecialchars(t('messages.no_teachers')) ?></p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($teachers as $t): ?>
                    <li class="text-on-surface"><?= htmlspecialchars($t['name']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
        <h2 class="font-headline-sm text-lg text-primary mb-4">
            <?= htmlspecialchars(t('messages.title')) ?>
            <?php if ($unreadMessages): ?>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-primary text-white ms-2"><?= count($unreadMessages) ?></span>
            <?php endif; ?>
        </h2>
        <?php if (!$unreadMessages): ?>
            <p class="text-on-surface-variant"><?= htmlspecialchars(t('messages.no_unread')) ?></p>
        <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($unreadMessages as $m): ?>
                    <li class="p-4 rounded-lg border border-outline-variant/20 bg-primary-container/10">
                        <span class="font-semibold"><?= htmlspecialchars(t('messages.from_label')) ?> <?= htmlspecialchars($m['sender_name']) ?></span>
                        <p class="text-on-surface"><?= htmlspecialchars($m['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/messages.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
