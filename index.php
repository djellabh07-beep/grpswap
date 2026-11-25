<?php
/**
 * index.php — Bassira hub / landing page.
 *
 * Deliberately NOT a dashboard: no progress rollups, no widget grid tied
 * to backend data. Its only real jobs are (1) hosting the mic-permission
 * gesture button for first-time visitors, and (2) greeting the student by
 * name once Bassira is actually listening. Everything else is the
 * existing voice-prompt-card visual, preserved from index.html.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$page      = 'index';
$pageTitle = t('app.tagline');

// No page-specific voice commands — home only needs the global
// navigation/help/stop set already built into voice_assistant.js.
?>
<?php require __DIR__ . '/includes/header.php'; ?>
<script>window.pageVoiceCommands = {};</script>

<!-- Center Interactive Mic Area -->
<div class="flex flex-col items-center justify-center min-h-[calc(100vh-theme(spacing.touch-target))] p-gutter relative z-10">
    <div class="relative flex flex-col items-center">

        <!-- Visual pulsing rings (Sonar effect), purely decorative -->
        <div class="absolute top-1/2 left-1/2 w-48 h-48 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-secondary-container/40 pulse-animation" aria-hidden="true"></div>
        <div class="absolute top-1/2 left-1/2 w-48 h-48 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-secondary-container/20 pulse-animation" style="animation-delay: 0.5s" aria-hidden="true"></div>

        <!-- Main mic button — the one explicit user gesture that requests
             mic permission the very first time (see script below). On
             every later page load, the engine attempts a silent restart
             on its own, so this button mostly matters for first-time use
             or for manually re-triggering the greeting. -->
        <button
            id="bassira-activate-btn"
            aria-label="<?= htmlspecialchars(t('index.activate_voice_label')) ?>"
            class="relative z-20 w-32 h-32 rounded-full bg-primary text-white shadow-[0_0_50px_rgba(27,58,107,0.3)] flex items-center justify-center transition-all hover:scale-105 active:scale-95 group overflow-hidden"
        >
            <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <span class="material-symbols-outlined text-5xl" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">mic</span>
        </button>

        <div class="mt-12 text-center floating-ui">
            <h2 class="font-bold text-[36px] font-arabic-h1 text-primary mb-4">
                <?= htmlspecialchars(t('index.prompt_placeholder')) ?>
            </h2>
        </div>

        <!-- Animated soundwave bars, purely decorative -->
        <div class="mt-12 flex items-center justify-center gap-2 h-10" aria-hidden="true">
            <div class="soundwave-bar w-1 bg-secondary rounded-full" style="animation-delay: 0.1s"></div>
            <div class="soundwave-bar w-1 bg-secondary rounded-full" style="animation-delay: 0.3s; height: 30px;"></div>
            <div class="soundwave-bar w-1 bg-secondary rounded-full" style="animation-delay: 0.5s; height: 15px;"></div>
            <div class="soundwave-bar w-1 bg-secondary rounded-full" style="animation-delay: 0.2s; height: 25px;"></div>
            <div class="soundwave-bar w-1 bg-secondary rounded-full" style="animation-delay: 0.4s"></div>
        </div>
    </div>

    <!-- Hint cards — spoken examples of what to say, unchanged from the
         existing design; these are illustrative text, not clickable
         shortcuts, since every real action here happens by voice. -->
    <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-5xl">
        <div class="bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm flex flex-col items-center text-center">
            <span class="material-symbols-outlined text-secondary mb-4 text-3xl" aria-hidden="true">menu_book</span>
            <h3 class="font-headline-sm text-xl text-primary mb-2"><?= htmlspecialchars(t('index.suggestion_read')) ?></h3>
            <p class="font-arabic-body text-on-surface-variant text-base"><?= htmlspecialchars(t('index.suggestion_read_ex')) ?></p>
        </div>
        <div class="bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm flex flex-col items-center text-center">
            <span class="material-symbols-outlined text-secondary mb-4 text-3xl" aria-hidden="true">edit_note</span>
            <h3 class="font-headline-sm text-xl text-primary mb-2"><?= htmlspecialchars(t('index.suggestion_note')) ?></h3>
            <p class="font-arabic-body text-on-surface-variant text-base"><?= htmlspecialchars(t('index.suggestion_note_ex')) ?></p>
        </div>
        <div class="bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10 shadow-sm flex flex-col items-center text-center">
            <span class="material-symbols-outlined text-secondary mb-4 text-3xl" aria-hidden="true">schedule</span>
            <h3 class="font-headline-sm text-xl text-primary mb-2"><?= htmlspecialchars(t('index.suggestion_schedule')) ?></h3>
            <p class="font-arabic-body text-on-surface-variant text-base"><?= htmlspecialchars(t('index.suggestion_schedule_ex')) ?></p>
        </div>
    </div>
</div>

<!-- Persistent listener-status bar -->
<footer class="fixed bottom-0 start-0 w-full z-50 bg-primary text-white h-20 shadow-lg flex items-center px-margin-desktop">
    <div class="flex items-center gap-6">
        <div class="bg-secondary-container/20 p-3 rounded-full flex items-center justify-center animate-pulse" aria-hidden="true">
            <span class="material-symbols-outlined text-secondary-fixed text-2xl" style="font-variation-settings: 'FILL' 1;">mic</span>
        </div>
        <div>
            <!-- The shared aria-live status region every page must have —
                 voice_assistant.js writes every echoed/spoken utterance
                 here. -->
            <h4 class="font-arabic-body font-bold text-white text-[18px]" role="status" aria-live="polite">
                <?= htmlspecialchars(t('index.assistant_ready')) ?>
            </h4>
        </div>
    </div>
</footer>

<script>
(function () {
    var greetingText = <?= json_encode(t('voice.greeting', [current_user_name() ?? ''])) ?>;
    var btn = document.getElementById('bassira-activate-btn');
    var greeted = false;

    async function greetOnce() {
        if (greeted) return;
        if (Bassira.isMicPermissionDenied() || !Bassira.isStarted()) return;
        greeted = true;
        await Bassira.speak(greetingText);
    }

    // Explicit user gesture — the one required the very first time mic
    // permission hasn't been granted yet. On later visits (permission
    // already granted) this just re-starts/re-greets harmlessly.
    btn.addEventListener('click', async () => {
        await Bassira.init();
        await greetOnce();
    });

    // Opportunistic silent path for returning visitors: if the origin
    // already has mic permission, this succeeds with no prompt and we can
    // greet automatically without waiting for a click.
    document.addEventListener('DOMContentLoaded', async () => {
        await Bassira.init(); // no-ops harmlessly if the click handler (or
                               // the engine's own internal auto-start) already handled it
        await greetOnce();
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
