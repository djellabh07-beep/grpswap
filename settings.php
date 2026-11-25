<?php
/**
 * settings.php
 *
 * Language switching is ALSO voice-settable ("بصيرة غيّري اللغة إلى
 * الفرنسية") — but per spec that's implemented as a GLOBAL command in
 * voice_assistant.js (works from any page), not registered here as a
 * page-specific command. This page's own language buttons hit the exact
 * same endpoint (settings_update_language.php) the global voice command
 * uses, so the two paths can never drift out of sync with each other.
 *
 * Speech rate and the mic re-enable button have no voice-command
 * equivalent — nothing in the spec asks for those to be voice-settable,
 * and a "change my own speech rate" or "re-enable the mic that's
 * currently denied" voice command would be a strange, low-value edge
 * case anyway (if the mic is denied, Bassira isn't listening in the
 * first place).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$page      = 'settings';
$pageTitle = t('settings.title');

$currentLangCode = current_lang();
$currentSpeechRate = (float) ($_SESSION['speech_rate'] ?? 1.0);

$speechRatePresets = [0.75, 1.0, 1.25, 1.5, 2.0];

$currentUserStmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$currentUserStmt->execute([current_user_id()]);
$currentUserRow = $currentUserStmt->fetch();

$profileError   = $_GET['error'] ?? null;
$profileSuccess = isset($_GET['saved']);

$profileErrorMessages = [
    'current_password_required' => t('settings.error_current_password_required'),
    'current_password_wrong'    => t('settings.error_current_password_wrong'),
    'email_taken'                => t('auth.signup_error_email_taken'),
    'password_short'             => t('auth.signup_error_password_short'),
    'password_mismatch'          => t('auth.signup_error_password_mismatch'),
    'invalid_email'              => t('auth.signup_error_invalid_email'),
    'missing_name'                => t('auth.signup_error_missing_fields'),
];
?>
<?php require __DIR__ . '/includes/header.php'; ?>
<script>window.pageVoiceCommands = {};</script>

<div class="p-gutter py-12 max-w-2xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-primary mb-8"><?= htmlspecialchars(t('settings.title')) ?></h1>

    <div id="settings-status" role="status" aria-live="polite" class="sr-only"></div>

    <!-- Language -->
    <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm mb-6">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('settings.language_section')) ?></h2>
        <div class="flex flex-wrap gap-3" role="group" aria-label="<?= htmlspecialchars(t('settings.language_section')) ?>">
            <?php foreach (['ar' => 'lang.ar', 'fr' => 'lang.fr', 'en' => 'lang.en'] as $code => $labelKey): ?>
                <button
                    type="button"
                    onclick="bassiraChangeLanguage('<?= $code ?>')"
                    aria-pressed="<?= $currentLangCode === $code ? 'true' : 'false' ?>"
                    class="px-4 py-2 rounded-full font-semibold transition-colors <?= $currentLangCode === $code
                        ? 'bg-primary text-on-primary'
                        : 'bg-surface-container text-on-surface-variant hover:bg-primary-container/20' ?>"
                ><?= htmlspecialchars(t($labelKey)) ?></button>
            <?php endforeach; ?>
        </div>
        <p class="text-sm text-on-surface-variant mt-4"><?= htmlspecialchars(t('settings.language_hint')) ?></p>
    </section>

    <!-- Speech rate -->
    <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm mb-6">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('settings.speech_rate_section')) ?></h2>
        <div class="flex flex-wrap gap-3" role="group" aria-label="<?= htmlspecialchars(t('settings.speech_rate_section')) ?>">
            <?php foreach ($speechRatePresets as $rate): ?>
                <button
                    type="button"
                    data-rate="<?= htmlspecialchars((string) $rate) ?>"
                    onclick="bassiraChangeSpeechRate(<?= htmlspecialchars((string) $rate) ?>)"
                    aria-pressed="<?= abs($currentSpeechRate - $rate) < 0.001 ? 'true' : 'false' ?>"
                    class="bassira-rate-btn px-4 py-2 rounded-full font-semibold transition-colors <?= abs($currentSpeechRate - $rate) < 0.001
                        ? 'bg-primary text-on-primary'
                        : 'bg-surface-container text-on-surface-variant hover:bg-primary-container/20' ?>"
                ><?= htmlspecialchars((string) $rate) ?>x</button>
            <?php endforeach; ?>
        </div>
        <p id="settings-rate-current" class="text-sm text-on-surface-variant mt-4">
            <?= htmlspecialchars(t('settings.speech_rate_current', [$currentSpeechRate])) ?>
        </p>
    </section>

    <!-- Account -->
    <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm mb-6">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('settings.account_section')) ?></h2>

        <?php if ($profileSuccess): ?>
            <div role="status" class="mb-4 p-3 rounded bg-secondary-container/40 text-on-secondary-container text-sm">
                <?= htmlspecialchars(t('settings.account_updated_success')) ?>
            </div>
        <?php endif; ?>

        <?php if ($profileError && isset($profileErrorMessages[$profileError])): ?>
            <div role="alert" class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">
                <?= htmlspecialchars($profileErrorMessages[$profileError]) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/profile_update_action.php" class="space-y-4">
            <div>
                <label for="profile_name" class="block mb-1 font-medium"><?= htmlspecialchars(t('auth.name_label')) ?></label>
                <input type="text" id="profile_name" name="name" required maxlength="150"
                    value="<?= htmlspecialchars($currentUserRow['name']) ?>"
                    class="w-full rounded border-outline-variant p-2">
            </div>

            <div>
                <label for="profile_email" class="block mb-1 font-medium"><?= htmlspecialchars(t('auth.email_label')) ?></label>
                <input type="email" id="profile_email" name="email" required
                    value="<?= htmlspecialchars($currentUserRow['email']) ?>"
                    class="w-full rounded border-outline-variant p-2">
            </div>

            <p class="text-sm text-on-surface-variant"><?= htmlspecialchars(t('settings.password_change_hint')) ?></p>

            <div>
                <label for="new_password" class="block mb-1 font-medium"><?= htmlspecialchars(t('reset.new_password_label')) ?></label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                    class="w-full rounded border-outline-variant p-2">
            </div>

            <div>
                <label for="new_password_confirm" class="block mb-1 font-medium"><?= htmlspecialchars(t('auth.confirm_password_label')) ?></label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password"
                    class="w-full rounded border-outline-variant p-2">
            </div>

            <div>
                <label for="current_password" class="block mb-1 font-medium"><?= htmlspecialchars(t('settings.current_password_label')) ?></label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                    class="w-full rounded border-outline-variant p-2">
                <p class="text-xs text-on-surface-variant mt-1"><?= htmlspecialchars(t('settings.current_password_hint')) ?></p>
            </div>

            <button type="submit" class="rounded-full bg-primary text-on-primary font-semibold px-5 py-2.5">
                <?= htmlspecialchars(t('settings.save_account_button')) ?>
            </button>
        </form>
    </section>

    <!-- Microphone -->
    <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
        <h2 class="font-headline-sm text-lg text-primary mb-4"><?= htmlspecialchars(t('settings.mic_section')) ?></h2>
        <p id="settings-mic-status" class="text-on-surface-variant mb-4"><?= htmlspecialchars(t('settings.mic_status_ok')) ?></p>
        <button
            type="button"
            onclick="Bassira.reenableMicrophone()"
            class="rounded-full bg-primary-container text-on-primary-container font-semibold px-5 py-2.5 hover:bg-primary-container/80 transition-colors"
        ><?= htmlspecialchars(t('settings.mic_reenable_button')) ?></button>
    </section>
</div>

<script>
const BASSIRA_RATE_LABEL_TEMPLATE = <?= json_encode(t('settings.speech_rate_current')) ?>;
const BASSIRA_MIC_DENIED_TEXT = <?= json_encode(t('settings.mic_status_denied')) ?>;
const BASSIRA_MIC_OK_TEXT = <?= json_encode(t('settings.mic_status_ok')) ?>;
const BASSIRA_SAVED_TEXT = <?= json_encode(t('settings.saved')) ?>;

function bassiraSetStatus(text) {
    const el = document.getElementById('settings-status');
    if (el) el.textContent = text;
}

async function bassiraChangeLanguage(lang) {
    try {
        const res = await fetch('/settings_update_language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'lang=' + encodeURIComponent(lang),
        });
        const data = await res.json();
        if (data.ok) {
            window.location.reload(); // dir/nav/labels/STT locale/TTS voice all re-derive from the fresh render
        }
    } catch (e) {
        console.error('[Settings] failed to change language:', e);
    }
}

async function bassiraChangeSpeechRate(rate) {
    // Takes effect immediately for the rest of this session, per spec —
    // set the client-side value FIRST so it applies to Bassira's very
    // next utterance even if the network request is slow.
    window.BASSIRA_SPEECH_RATE = rate;

    document.querySelectorAll('.bassira-rate-btn').forEach((btn) => {
        const isActive = Math.abs(parseFloat(btn.dataset.rate) - rate) < 0.001;
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        btn.classList.toggle('bg-primary', isActive);
        btn.classList.toggle('text-on-primary', isActive);
        btn.classList.toggle('bg-surface-container', !isActive);
        btn.classList.toggle('text-on-surface-variant', !isActive);
    });
    const label = document.getElementById('settings-rate-current');
    if (label) label.textContent = BASSIRA_RATE_LABEL_TEMPLATE.replace('%s', rate);

    try {
        const res = await fetch('/settings_update_speech_rate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'rate=' + encodeURIComponent(rate),
        });
        const data = await res.json();
        if (data.ok) bassiraSetStatus(BASSIRA_SAVED_TEXT);
    } catch (e) {
        console.error('[Settings] failed to save speech rate:', e);
    }
}

// Mic status reflects live browser state, which only voice_assistant.js
// (loaded with defer) knows — checked on DOMContentLoaded, by which point
// every deferred script has already run.
document.addEventListener('DOMContentLoaded', () => {
    const statusEl = document.getElementById('settings-mic-status');
    if (statusEl && window.Bassira && window.Bassira.isMicPermissionDenied()) {
        statusEl.textContent = BASSIRA_MIC_DENIED_TEXT;
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
