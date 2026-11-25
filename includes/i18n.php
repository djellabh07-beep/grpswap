<?php
/**
 * includes/i18n.php
 *
 * Language dictionary loader + t() lookup. Depends on the session already
 * being started (include auth.php or db.php first — auth.php calls
 * session_start()). If neither has run yet, this file starts the session
 * itself so it also works standalone.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/i18n.php';
 *   echo t('nav.home');
 *   echo t('reader.page_of', [2, 12]);              // "Page 2 of 12" via sprintf
 *   <html dir="<?= current_dir() ?>" lang="<?= $_SESSION['lang'] ?>">
 *   recognition.lang = '<?= current_speech_locale() ?>';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const BASSIRA_SUPPORTED_LANGS = ['ar', 'fr', 'en'];

/**
 * The dictionaries are loaded once per request and cached in a static
 * variable inside t() itself, rather than as a global, to avoid any
 * accidental clobbering from other code touching a global var of the
 * same name.
 */
function _bassira_load_dictionary(string $lang): array
{
    static $cache = [];

    if (!in_array($lang, BASSIRA_SUPPORTED_LANGS, true)) {
        $lang = 'ar';
    }

    if (!isset($cache[$lang])) {
        $path = __DIR__ . '/../lang/' . $lang . '.php';
        // Defensive: if a dictionary file is ever missing/corrupt, fall
        // back to an empty array rather than a fatal error — t() will
        // then fall back to echoing the raw key for every string, which
        // is ugly but never crashes a page for a blind user mid-session.
        $cache[$lang] = is_file($path) ? (require $path) : [];
    }

    return $cache[$lang];
}

/**
 * Current session language, defaulting to 'ar' (primary language) if
 * unset or set to something unsupported.
 */
function current_lang(): string
{
    $lang = $_SESSION['lang'] ?? 'ar';
    return in_array($lang, BASSIRA_SUPPORTED_LANGS, true) ? $lang : 'ar';
}

/**
 * Translate a key into the current session language.
 *
 * - Falls back to the key itself (never a fatal error / notice) if the
 *   key is missing from the dictionary — so a missing translation shows
 *   up as visible-but-harmless text like "voice.note_saved" instead of
 *   breaking the page or, worse, breaking Bassira's speech mid-sentence.
 * - Optional $args are passed through vsprintf() for strings with
 *   placeholders (%s, %1$s, etc.), e.g. t('reader.page_of', [2, 12]).
 */
function t(string $key, array $args = []): string
{
    $dict = _bassira_load_dictionary(current_lang());
    $value = $dict[$key] ?? $key;

    if (!empty($args)) {
        // vsprintf on a string with no placeholders just returns it
        // unchanged, so this is safe to call unconditionally too — kept
        // behind the empty check only to skip the extra function call
        // in the common no-args case.
        $formatted = @vsprintf($value, $args);
        return $formatted !== false ? $formatted : $value;
    }

    return $value;
}

/**
 * 'rtl' or 'ltr' for the current session language — used on
 * <html dir="..."> in header.php and any per-element direction overrides.
 */
function current_dir(): string
{
    return current_lang() === 'ar' ? 'rtl' : 'ltr';
}

/**
 * BCP-47 locale code for Web Speech API STT (recognition.lang) and for
 * picking a matching voice out of speechSynthesis.getVoices() for TTS.
 *
 * ar-DZ (Algerian Arabic) is used rather than a generic 'ar' or 'ar-SA'
 * since the student base is in Algeria and browser STT/TTS voice
 * selection quality can vary meaningfully by regional locale tag.
 */
function current_speech_locale(): string
{
    return match (current_lang()) {
        'fr'    => 'fr-FR',
        'en'    => 'en-US',
        default => 'ar-DZ',
    };
}
