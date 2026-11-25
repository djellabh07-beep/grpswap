/**
 * assets/js/voice_assistant.js
 *
 * Bassira's shared, page-independent voice engine. Loaded on every page via
 * includes/header.php with `defer`. This file knows nothing about courses,
 * homework, notes, etc. — it only knows how to: listen for the wake word,
 * capture one utterance at a time, speak/echo things back, run the
 * echo -> confirm -> act flow for destructive actions, and hand recognized
 * command text off to whoever registered a handler for it (the page, or
 * this file's own built-in global commands).
 *
 * ---------------------------------------------------------------------
 * USAGE NOTE — how a page registers page-specific commands
 * ---------------------------------------------------------------------
 * Before this script runs (it's loaded with `defer` in <head>, so it
 * always executes after the page's own inline <script> tags, which run
 * in document order during HTML parsing — see header.php), a page sets:
 *
 *   <script>
 *   window.pageVoiceCommands = [
 *     {
 *       patterns: ['التالي', 'اقرأ التالي'],   // string = substring match,
 *       destructive: false,                      // RegExp also allowed
 *       description: <?= json_encode(t('reader.cmd_next')) ?>, // spoken by "ماذا يمكنني أن أقول؟"
 *       handler: () => { Reader.next(); }
 *     },
 *     {
 *       // Use match() instead of patterns for commands that need to pull
 *       // data out of the utterance itself (page numbers, names, etc).
 *       match: (text) => {
 *         const m = text.match(/صفحة\s*(?:رقم)?\s*(\d+)/);
 *         return m ? { page: parseInt(m[1], 10) } : null;
 *       },
 *       destructive: false,
 *       description: <?= json_encode(t('reader.cmd_goto_page')) ?>,
 *       handler: (args) => { Reader.gotoPage(args.page); }
 *     },
 *     {
 *       patterns: ['احذفي هذه الملاحظة'],
 *       destructive: true,   // triggers echo -> "قولي 'نعم' للتأكيد" -> act
 *       description: <?= json_encode(t('notes.cmd_delete')) ?>,
 *       handler: async () => { await Notes.deleteCurrent(); }
 *     }
 *   ];
 *   // A page with nothing page-specific to add (e.g. index.php) just sets:
 *   // window.pageVoiceCommands = {};
 *   </script>
 *   <script src="/assets/js/voice_assistant.js" defer></script>
 *
 * A command's `handler` may return a Promise; the engine awaits it before
 * resuming wake-word listening. `handler` receives whatever `match()`
 * returned (or `null` for plain `patterns` commands) as its first argument.
 *
 * Pages that need free-form dictation (e.g. "خذي ملاحظة" capturing the
 * next utterance verbatim, unmatched against any command list) call the
 * exposed primitive directly from inside their own handler:
 *
 *   handler: async () => {
 *     await Bassira.speak(bassiraStrings.dictationStart);
 *     const text = await Bassira.captureUtterance(8000); // ms timeout
 *     if (text) { await Notes.save(text); }
 *   }
 *
 * ---------------------------------------------------------------------
 */

(function () {
    'use strict';

    // -------------------------------------------------------------
    // Feature detection
    // -------------------------------------------------------------
    const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
    const speechRecognitionSupported = !!SpeechRecognitionCtor;
    const speechSynthesisSupported = 'speechSynthesis' in window;

    // -------------------------------------------------------------
    // Built-in strings, per session language. This engine ships as a
    // static asset (no server templating), so it keeps its own small
    // phrase dictionary rather than reading lang/*.php directly. Some
    // overlap with those files is expected and fine — this is the set of
    // phrases the ENGINE itself needs to recognize/speak for wake-word
    // handling, navigation, help, and confirmation; everything page-
    // specific comes from the page's own t()-sourced `description`/
    // `echo` strings instead.
    //
    // fr/en wake-word variants are a first pass — Prompt 11 (trilingual
    // pass) is where these get tested against real recognizers per
    // locale and expanded with whatever phonetic misses show up.
    // -------------------------------------------------------------
    const VOICE_STRINGS = {
        ar: {
            wakeVariants: ['بصيرة', 'بصيره', 'بصير', 'بصيرا', 'يا بصيرة', 'بصيرتي'],
            vocativePrefixes: ['يا '],
            listeningStatus: 'أستمع...',
            noCommandHeard: 'لم أسمع أمرًا.',
            commandNotUnderstood: 'عذراً، لم أفهم هذا الأمر.',
            confirmPrompt: "قولي 'نعم' للتأكيد",
            confirmCancelled: 'تم الإلغاء.',
            confirmTimeout: 'لم يتم التأكيد، تم إلغاء العملية.',
            micDenied: 'يرجى السماح باستخدام الميكروفون لتفعيل المساعد الصوتي.',
            notSupported: 'المساعد الصوتي غير مدعوم في هذا المتصفح.',
            yesWords: ['نعم', 'ايوه', 'أيوه', 'اي', 'أجل', 'تمام'],
            stopWords: ['توقف', 'اسكت', 'قف'],
            helpTriggers: ['ماذا يمكنني أن أقول', 'ما هي الأوامر', 'ما هي الأوامر المتاحة'],
            helpIntro: 'يمكنك أن تقول...',
            helpOutro: "يمكنك أيضاً قول اسم أي صفحة للانتقال إليها، أو 'توقف' للإلغاء.",
            listSeparator: '، ',
            courseMultipleMaterials: 'يحتوي هذا المقرر على عدة ملفات، قل اسم الملف الذي تريدينه.',
            courseNoMaterials: 'لا توجد ملفات في هذا المقرر بعد.',
            languageChange: {
                triggers: ['غيّري اللغة', 'غيري اللغة', 'بدّلي اللغة', 'بدلي اللغة'],
                targets: {
                    ar: ['العربية', 'عربي'],
                    fr: ['الفرنسية', 'فرنسي'],
                    en: ['الإنجليزية', 'الانجليزية', 'إنجليزي', 'انجليزي'],
                },
            },
            languageChanged: {
                ar: 'تم تغيير اللغة إلى العربية.',
                fr: 'تم تغيير اللغة إلى الفرنسية.',
                en: 'تم تغيير اللغة إلى الإنجليزية.',
            },
            languageChangeFailed: 'تعذّر تغيير اللغة، حاول مرة أخرى.',
            nav: {
                index:    ['الرئيسية', 'اذهبي للرئيسية', 'افتحي الرئيسية'],
                courses:  ['مقرراتي', 'افتحي المقررات', 'اذهبي للمقررات'],
                notes:    ['ملاحظاتي', 'افتحي الملاحظات', 'اذهبي للملاحظات'],
                homework: ['واجباتي', 'افتحي الواجبات', 'اذهبي للواجبات'],
                messages: ['رسائلي', 'افتحي الرسائل', 'اذهبي للرسائل'],
                settings: ['الإعدادات', 'افتحي الإعدادات', 'اذهبي للإعدادات'],
            },
            pageNames: {
                index: 'الرئيسية', courses: 'مقرراتي', notes: 'ملاحظاتي',
                homework: 'واجباتي', messages: 'رسائلي', settings: 'الإعدادات',
            },
            pageOpened: (name) => `تم فتح ${name}.`,
        },
        fr: {
            // See this file's closing comment block for the full note on
            // wake-word variant coverage confidence per language.
            wakeVariants: ['bassira', 'bassirah', 'basira', 'bassera', 'bassiera', 'bashira', 'basera'],
            vocativePrefixes: [],
            listeningStatus: "J'écoute...",
            noCommandHeard: "Je n'ai entendu aucune commande.",
            commandNotUnderstood: "Désolée, je n'ai pas compris cette commande.",
            confirmPrompt: 'Dites « oui » pour confirmer',
            confirmCancelled: 'Annulé.',
            confirmTimeout: "Pas de confirmation reçue, l'action a été annulée.",
            micDenied: "Veuillez autoriser l'accès au microphone pour activer l'assistant vocal.",
            notSupported: "L'assistant vocal n'est pas pris en charge par ce navigateur.",
            yesWords: ['oui', 'ouais', "d'accord"],
            stopWords: ['arrête', 'stop', 'silence'],
            helpTriggers: ['que puis-je dire', 'quelles sont les commandes'],
            helpIntro: 'Vous pouvez dire...',
            helpOutro: "Vous pouvez aussi dire le nom de n'importe quelle page pour y accéder, ou « stop » pour annuler.",
            listSeparator: ', ',
            courseMultipleMaterials: 'Ce cours contient plusieurs fichiers, dites le nom du fichier que vous voulez.',
            courseNoMaterials: "Il n'y a pas encore de fichiers dans ce cours.",
            languageChange: {
                triggers: ['change la langue', 'changer la langue', 'changez la langue'],
                targets: {
                    ar: ['arabe'],
                    fr: ['français', 'francais'],
                    en: ['anglais'],
                },
            },
            languageChanged: {
                ar: 'La langue a été changée en arabe.',
                fr: 'La langue a été changée en français.',
                en: 'La langue a été changée en anglais.',
            },
            languageChangeFailed: 'Impossible de changer la langue, veuillez réessayer.',
            nav: {
                index:    ['accueil', "ouvre l'accueil", "va à l'accueil"],
                courses:  ['mes cours', 'ouvre mes cours', 'va à mes cours'],
                notes:    ['mes notes', 'ouvre mes notes', 'va à mes notes'],
                homework: ['mes devoirs', 'ouvre mes devoirs', 'va à mes devoirs'],
                messages: ['mes messages', 'ouvre mes messages', 'va à mes messages'],
                settings: ['paramètres', 'ouvre les paramètres', 'va aux paramètres'],
            },
            pageNames: {
                index: 'Accueil', courses: 'Mes cours', notes: 'Mes notes',
                homework: 'Mes devoirs', messages: 'Mes messages', settings: 'Paramètres',
            },
            pageOpened: (name) => `${name} ouvert.`,
        },
        en: {
            // See this file's closing comment block for the full note on
            // wake-word variant coverage confidence per language.
            wakeVariants: ['bassira', 'bassirah', 'basira', 'bassera', 'baseera', 'bashira'],
            vocativePrefixes: [],
            listeningStatus: 'Listening...',
            noCommandHeard: "I didn't hear a command.",
            commandNotUnderstood: "Sorry, I didn't understand that command.",
            confirmPrompt: 'Say "yes" to confirm',
            confirmCancelled: 'Cancelled.',
            confirmTimeout: 'No confirmation received, the action was cancelled.',
            micDenied: 'Please allow microphone access to activate the voice assistant.',
            notSupported: 'The voice assistant is not supported in this browser.',
            yesWords: ['yes', 'yeah', 'yep', 'sure'],
            stopWords: ['stop', 'quiet', 'cancel'],
            helpTriggers: ['what can i say', 'what are the commands'],
            helpIntro: 'You can say...',
            helpOutro: 'You can also say the name of any page to open it, or "stop" to cancel.',
            listSeparator: ', ',
            courseMultipleMaterials: 'This course has several files — say the name of the one you want.',
            courseNoMaterials: 'There are no files in this course yet.',
            languageChange: {
                triggers: ['change the language', 'switch the language'],
                targets: {
                    ar: ['arabic'],
                    fr: ['french'],
                    en: ['english'],
                },
            },
            languageChanged: {
                ar: 'Language changed to Arabic.',
                fr: 'Language changed to French.',
                en: 'Language changed to English.',
            },
            languageChangeFailed: 'Could not change the language, please try again.',
            nav: {
                index:    ['home', 'open home', 'go home'],
                courses:  ['my courses', 'open my courses', 'go to my courses'],
                notes:    ['my notes', 'open my notes', 'go to my notes'],
                homework: ['my homework', 'open my homework', 'go to my homework'],
                messages: ['my messages', 'open my messages', 'go to my messages'],
                settings: ['settings', 'open settings', 'go to settings'],
            },
            pageNames: {
                index: 'Home', courses: 'My Courses', notes: 'My Notes',
                homework: 'My Homework', messages: 'My Messages', settings: 'Settings',
            },
            pageOpened: (name) => `${name} opened.`,
        },
    };

    // -------------------------------------------------------------
    // NOTE — wake-word variant coverage confidence per language
    // (Prompt 11 / trilingual pass)
    // -------------------------------------------------------------
    // Arabic ('بصيرة' + 5 mis-transcription variants, ar-DZ locale): this
    // is the ONE language variant list that reflects real usage — Bassira
    // is an Arabic word to begin with, "بصيرة" has no ambiguous spelling,
    // and the mis-transcription variants (بصيره / بصير / بصيرا / بصيرتي)
    // are the kind of thing that shows up from genuine Arabic ASR
    // confusion around final-letter vowel marks and the tāʾ marbūṭa. This
    // list can reasonably be trusted.
    //
    // French (fr-FR) and English (en-US): "Bassira" isn't a French or
    // English word — these lists are LINGUISTIC BEST GUESSES about how
    // each locale's speech recognizer might spell an unfamiliar proper
    // noun it's hearing for the first time, reasoned out as follows:
    //   - 'bassira' / 'bassirah' / 'basira': straightforward phonetic
    //     spellings, including the Arabic-transliteration convention of a
    //     trailing silent 'h'.
    //   - 'bassera' / 'bassiera': plausible vowel-quality mishearings —
    //     unstressed vowels in a novel word are exactly where ASR systems
    //     tend to guess wrong.
    //   - 'bashira': the Arabic ص (emphatic/pharyngealized s) has no
    //     equivalent phoneme in French or English — a recognizer with no
    //     training exposure to that sound could plausibly render it
    //     closer to 'sh' than to a plain 's'.
    //   - French specifically tends to spell a voiceless /s/ heard
    //     between two vowels with a double 's' (single intervocalic 's'
    //     conventionally implies /z/ in French orthography, e.g.
    //     "maison"), which is part of why 'bassira' (double s) rather
    //     than 'basira' (single s) is likely the MORE probable French
    //     transcription, not just an alternate spelling.
    //   - None of this has been validated against a real browser's
    //     SpeechRecognition with real speakers — it can't be, in a
    //     sandboxed, browser-less environment. Treat the fr/en wake-word
    //     experience as the single least-validated piece of this entire
    //     trilingual build, and budget real-device testing with both
    //     native and Algerian-accented French/English speakers before
    //     relying on it. A practical way to gather real data: temporarily
    //     log every idle-loop transcript (even non-matches) to the
    //     console for a test session in each language, then fold
    //     whatever Bassira actually gets misheard as back into the
    //     matching wakeVariants array above.
    // -------------------------------------------------------------

    const PAGE_HREFS = {
        index: '/index.php',
        courses: '/courses.php',
        notes: '/notes.php',
        homework: '/homework.php',
        messages: '/messages.php',
        settings: '/settings.php',
    };

    // -------------------------------------------------------------
    // Locale helpers — driven by <html lang="..."> (set server-side by
    // header.php from the session language), so there is exactly one
    // source of truth shared between PHP's current_speech_locale() and
    // this file. No separate global to keep in sync.
    // -------------------------------------------------------------
    function currentLang() {
        const lang = (document.documentElement.lang || 'ar').toLowerCase();
        return VOICE_STRINGS[lang] ? lang : 'ar';
    }

    function currentSpeechLocale() {
        switch (currentLang()) {
            case 'fr': return 'fr-FR';
            case 'en': return 'en-US';
            default:   return 'ar-DZ';
        }
    }

    function strings() {
        return VOICE_STRINGS[currentLang()];
    }

    // Optional per-user speech rate (settings.php / users.speech_rate).
    // Pages may set window.BASSIRA_SPEECH_RATE before this script loads;
    // defaults to 1.0 otherwise.
    function currentSpeechRate() {
        const rate = Number(window.BASSIRA_SPEECH_RATE);
        return Number.isFinite(rate) && rate > 0 ? rate : 1.0;
    }

    // -------------------------------------------------------------
    // aria-live status region — every page already has one
    // ([aria-live="polite"], usually role="status"). Every echoed/spoken
    // utterance is mirrored here as text, so there's a visible transcript
    // alongside the audio, not just audio.
    // -------------------------------------------------------------
    function writeStatus(text) {
        const region = document.querySelector('[aria-live="polite"]');
        if (region) {
            region.textContent = text;
        }
    }

    // -------------------------------------------------------------
    // Text-to-speech. Resolves once speech finishes (or immediately if
    // synthesis isn't supported), so callers can `await` it before
    // capturing the next utterance — avoids Bassira listening to herself.
    // -------------------------------------------------------------
    let cachedVoices = [];
    if (speechSynthesisSupported) {
        cachedVoices = window.speechSynthesis.getVoices();
        window.speechSynthesis.onvoiceschanged = () => {
            cachedVoices = window.speechSynthesis.getVoices();
        };
    }

    function pickVoice(locale) {
        if (!cachedVoices.length) return null;
        const exact = cachedVoices.find((v) => v.lang === locale);
        if (exact) return exact;
        const prefix = locale.split('-')[0];
        const partial = cachedVoices.find((v) => v.lang && v.lang.toLowerCase().startsWith(prefix));
        return partial || null;
    }

    function speak(text) {
        writeStatus(text);

        if (!speechSynthesisSupported || !text) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            // Cancel anything currently queued/playing first — this is
            // also how the "توقف/stop" global command interrupts speech.
            window.speechSynthesis.cancel();

            const utter = new SpeechSynthesisUtterance(text);
            const locale = currentSpeechLocale();
            utter.lang = locale;
            utter.rate = currentSpeechRate();
            const voice = pickVoice(locale);
            if (voice) utter.voice = voice;

            utter.onend = () => resolve();
            utter.onerror = () => resolve(); // never let a TTS glitch hang the flow

            window.speechSynthesis.speak(utter);
        });
    }

    // -------------------------------------------------------------
    // Earcon — a short synthesized tone marking wake-word activation, so
    // there's a non-verbal cue in addition to the aria-live text change.
    // Generated via Web Audio (no external asset). The AudioContext is
    // created lazily on the first user gesture (Bassira.init()) to
    // satisfy autoplay-unlock requirements, then reused.
    // -------------------------------------------------------------
    let audioCtx = null;

    function playEarcon() {
        if (!audioCtx) return;
        try {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.15);
        } catch (e) {
            // Non-critical — a failed earcon should never block the voice flow.
        }
    }

    // -------------------------------------------------------------
    // Core state
    // -------------------------------------------------------------
    const STATE = { IDLE: 'idle', ACTIVE: 'active' };
    let currentState = STATE.IDLE;
    let micPermissionDenied = false;
    let idleRecognition = null;
    let suppressIdleRestart = false;

    function setState(s) {
        currentState = s;
    }

    // -------------------------------------------------------------
    // Idle loop — continuous listening for the wake word only. Restarts
    // itself on `onend` (browsers time out continuous recognition after
    // a while even with no errors) unless permission was denied or a
    // focused capture (command/confirm/dictation) is about to take over.
    // -------------------------------------------------------------
    function startIdleListening() {
        if (!speechRecognitionSupported || micPermissionDenied) return;

        idleRecognition = new SpeechRecognitionCtor();
        idleRecognition.lang = currentSpeechLocale();
        idleRecognition.continuous = true;
        idleRecognition.interimResults = false;

        idleRecognition.onresult = (event) => {
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                if (!result.isFinal) continue;
                const transcript = normalize(result[0].transcript);
                handleIdleTranscript(transcript);
            }
        };

        idleRecognition.onerror = (event) => {
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                micPermissionDenied = true;
                writeStatus(strings().micDenied);
                return; // do not restart — nothing to restart into
            }
            // 'no-speech' / 'network' / anything else: let onend's
            // restart logic handle it, nothing special to do here.
        };

        idleRecognition.onend = () => {
            if (micPermissionDenied || suppressIdleRestart) return;
            if (currentState !== STATE.IDLE) return; // a focused capture owns the mic right now
            startIdleListening();
        };

        try {
            idleRecognition.start();
        } catch (e) {
            // start() throws if called while already running (rare
            // double-invocation race) — safe to ignore, the existing
            // instance is already listening.
        }
    }

    function pauseIdleListening() {
        suppressIdleRestart = true;
        if (idleRecognition) {
            try { idleRecognition.abort(); } catch (e) { /* already stopped */ }
        }
    }

    function resumeIdleListening() {
        suppressIdleRestart = false;
        setState(STATE.IDLE);
        if (!micPermissionDenied) startIdleListening();
    }

    function normalize(text) {
        return (text || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }

    function levenshteinDistance(a, b) {
        const m = a.length, n = b.length;
        const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));
        for (let i = 0; i <= m; i++) dp[i][0] = i;
        for (let j = 0; j <= n; j++) dp[0][j] = j;
        for (let i = 1; i <= m; i++) {
            for (let j = 1; j <= n; j++) {
                dp[i][j] = a[i - 1] === b[j - 1]
                    ? dp[i - 1][j - 1]
                    : 1 + Math.min(dp[i - 1][j - 1], dp[i - 1][j], dp[i][j - 1]);
            }
        }
        return dp[m][n];
    }

    function fuzzyMatchesWakeWord(word) {
        const coreWakeWords = { ar: 'بصيرة', fr: 'bassira', en: 'bassira' };
        const core = coreWakeWords[currentLang()] || 'bassira';
        const maxDistance = word.length <= 4 ? 1 : 2;
        return levenshteinDistance(word, core) <= maxDistance;
    }

    // -------------------------------------------------------------
    // Wake word detection against the idle stream. Handles both:
    //   - wake word spoken alone -> activate, then capture the command
    //     as a separate utterance
    //   - wake word + command spoken together in one breath -> treat the
    //     remainder after the wake word as the command directly
    // -------------------------------------------------------------
    function matchWakeWord(transcript) {
        const variants = strings().wakeVariants
            .slice()
            .sort((a, b) => b.length - a.length); // longest first, avoid a short variant matching inside a longer one

        // Optional per-language vocative/address prefixes that might
        // precede the wake word (e.g. Arabic "يا بصيرة..."). French and
        // English have no comparably productive equivalent — a French or
        // English speaker doesn't reliably prefix a name with a fixed
        // particle the way Arabic's vocative "يا" is commonly used before
        // a request — so those languages simply have an empty list here
        // rather than a hardcoded guess. This list itself is exactly the
        // kind of thing worth expanding once real usage data from fr/en
        // sessions is available (see this file's wakeVariants comment).
        const vocativePrefixes = strings().vocativePrefixes || [];

        for (const variant of variants) {
            if (transcript === variant) {
                return { remainder: '' };
            }
            if (transcript.indexOf(variant) === 0) {
                return { remainder: transcript.slice(variant.length).trim() };
            }
            for (const prefix of vocativePrefixes) {
                const withPrefix = prefix + variant;
                if (transcript.indexOf(withPrefix) === 0) {
                    return { remainder: transcript.slice(withPrefix.length).trim() };
                }
            }
        }

        const firstWord = transcript.split(' ')[0] || '';
        if (firstWord && fuzzyMatchesWakeWord(firstWord)) {
            return { remainder: transcript.slice(firstWord.length).trim() };
        }
        return null;
    }

    async function handleIdleTranscript(transcript) {
        if (currentState !== STATE.IDLE) return; // ignore stray results while busy
        const wake = matchWakeWord(transcript);
        if (!wake) return;

        pauseIdleListening();
        setState(STATE.ACTIVE);
        playEarcon();
        writeStatus(strings().listeningStatus);

        if (wake.remainder) {
            await runCommand(wake.remainder);
        } else {
            const command = await captureUtterance(4000);
            if (!command) {
                await speak(strings().noCommandHeard);
            } else {
                await runCommand(command);
            }
        }

        resumeIdleListening();
    }

    // -------------------------------------------------------------
    // Generic single-utterance capture primitive. Used internally for
    // command capture and yes/no confirmation, and exposed publicly
    // (Bassira.captureUtterance) so a page's own command handler can use
    // it for free-form dictation (e.g. "خذي ملاحظة"). Resolves the
    // recognized text, or null on timeout/error/no-speech.
    // -------------------------------------------------------------
    function captureUtterance(timeoutMs) {
        return new Promise((resolve) => {
            if (!speechRecognitionSupported || micPermissionDenied) {
                resolve(null);
                return;
            }

            const rec = new SpeechRecognitionCtor();
            rec.lang = currentSpeechLocale();
            rec.continuous = false;
            rec.interimResults = false;
            rec.maxAlternatives = 1;

            let settled = false;
            const timer = setTimeout(() => finish(null), timeoutMs);

            function finish(value) {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                rec.onresult = null;
                rec.onerror = null;
                rec.onend = null;
                try { rec.abort(); } catch (e) { /* already stopped */ }
                resolve(value);
            }

            rec.onresult = (event) => {
                const last = event.results[event.results.length - 1];
                finish(normalize(last[0].transcript));
            };

            rec.onerror = (event) => {
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    micPermissionDenied = true;
                    writeStatus(strings().micDenied);
                }
                finish(null);
            };

            rec.onend = () => finish(null);

            try {
                rec.start();
            } catch (e) {
                finish(null);
            }
        });
    }

    // -------------------------------------------------------------
    // Command matching + dispatch. Page-specific commands are checked
    // first, then the built-in global registry.
    // -------------------------------------------------------------
    function getPageCommands() {
        const raw = window.pageVoiceCommands;
        if (!raw) return [];
        return Array.isArray(raw) ? raw : Object.values(raw);
    }

    // Fuzzy phrase match: checks whether a contiguous run of words in
    // `text` matches `pattern`'s words, tolerating small speech-to-text
    // mishearings (e.g. "homework" heard as "homworks") word-by-word via
    // edit distance, rather than requiring an exact substring.
    function wordEditTolerance(word) {
        if (word.length <= 4) return 1;
        if (word.length <= 7) return 2;
        return 3;
    }

    function fuzzyContainsPhrase(text, pattern) {
        const textWords = text.split(' ').filter(Boolean);
        const patternWords = pattern.split(' ').filter(Boolean);
        if (!patternWords.length) return false;

        for (let i = 0; i + patternWords.length <= textWords.length; i++) {
            let allMatch = true;
            for (let j = 0; j < patternWords.length; j++) {
                const tw = textWords[i + j];
                const pw = patternWords[j];
                if (levenshteinDistance(tw, pw) > wordEditTolerance(pw)) {
                    allMatch = false;
                    break;
                }
            }
            if (allMatch) return true;
        }
        return false;
    }

    function matchCommand(cmd, text) {
        if (typeof cmd.match === 'function') {
            return cmd.match(text) || null;
        }
        if (Array.isArray(cmd.patterns)) {
            for (const p of cmd.patterns) {
                if (p instanceof RegExp) {
                    if (p.test(text)) return {};
                } else if (typeof p === 'string') {
                    if (fuzzyContainsPhrase(text, p)) return {};
                }
            }
        }
        return null;
    }

    function buildGlobalCommands() {
        const s = strings();
        const commands = [];

        // Navigate to any page.
        Object.keys(PAGE_HREFS).forEach((pageKey) => {
            commands.push({
                patterns: s.nav[pageKey],
                destructive: false,
                handler: async () => {
                    await speak(s.pageOpened(s.pageNames[pageKey]));
                    window.location.href = PAGE_HREFS[pageKey];
                },
            });
        });

        // "What can I say?" — recite page-specific command descriptions
        // (supplied by the page itself, already localized via t()), plus
        // a short closing note about navigation/stop always being available.
        commands.push({
            patterns: s.helpTriggers,
            destructive: false,
            handler: async () => {
                const descriptions = getPageCommands()
                    .map((c) => c.description)
                    .filter(Boolean);
                const body = descriptions.length
                    ? s.helpIntro + ' ' + descriptions.join(s.listSeparator) + '. '
                    : '';
                await speak(body + s.helpOutro);
            },
        });

        // Stop / cancel current speech.
        commands.push({
            patterns: s.stopWords,
            destructive: false,
            handler: () => {
                if (speechSynthesisSupported) window.speechSynthesis.cancel();
            },
        });

        // Change session language — "بصيرة غيّري اللغة إلى الفرنسية" and
        // equivalents. Registered globally (not page-specific to
        // settings.php) per spec: language is app-wide state, so this
        // should work from wherever the student happens to be, exactly
        // like the nav/course-open commands already do. Persists to the
        // server (both users.preferred_language and $_SESSION['lang'] —
        // see settings_update_language.php) then reloads the page, since
        // dir/nav labels/STT locale/TTS voice are all driven server-side
        // by the session language and re-rendered header.php output —
        // there's no client-only shortcut that would keep every one of
        // those in sync without a fresh render.
        commands.push({
            match: (text) => {
                const lc = s.languageChange;
                if (!lc) return null;
                const hasTrigger = lc.triggers.some((t) => text.indexOf(t) !== -1);
                if (!hasTrigger) return null;
                for (const code of Object.keys(lc.targets)) {
                    if (lc.targets[code].some((name) => text.indexOf(name) !== -1)) {
                        return { targetLang: code };
                    }
                }
                return null;
            },
            destructive: false,
            handler: async (args) => {
                await speak(s.languageChanged[args.targetLang] || '');
                try {
                    const res = await fetch('/settings_update_language.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'lang=' + encodeURIComponent(args.targetLang),
                    });
                    const data = await res.json();
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        await speak(s.languageChangeFailed || '');
                    }
                } catch (e) {
                    await speak(s.languageChangeFailed || '');
                }
            },
        });

        // Open an enrolled course by spoken name — data-driven from
        // window.BASSIRA_COURSES (injected per-request by header.php for
        // student sessions; see that file's comments). Registered as a
        // GLOBAL command rather than page-specific to courses.php: every
        // other navigation-type command in this app already works from
        // any page, so gating "open a course by name" to only work while
        // already on courses.php would be an inconsistent exception to
        // that rule. A course with exactly one material jumps straight
        // into reading it; with several, Bassira asks which file, since
        // there's nothing sensible to auto-select.
        if (Array.isArray(window.BASSIRA_COURSES) && window.BASSIRA_COURSES.length) {
            commands.push({
                match: (text) => {
                    const course = window.BASSIRA_COURSES.find(
                        (c) => c.name && text.indexOf(c.name) !== -1
                    );
                    return course || null;
                },
                destructive: false,
                handler: async (course) => {
                    const materials = course.materials || [];
                    if (materials.length === 1) {
                        window.location.href = '/reader.php?material=' + materials[0].id;
                    } else if (materials.length > 1) {
                        await speak(s.courseMultipleMaterials);
                        window.location.href = '/courses.php#course-' + course.id;
                    } else {
                        await speak(s.courseNoMaterials);
                    }
                },
            });
        }

        return commands;
    }

    async function runConfirmation() {
        await speak(strings().confirmPrompt);
        const reply = await captureUtterance(5000);
        if (reply === null) {
            await speak(strings().confirmTimeout);
            return false;
        }
        const isYes = strings().yesWords.some((w) => reply.indexOf(w) !== -1);
        if (!isYes) {
            await speak(strings().confirmCancelled);
            return false;
        }
        return true;
    }

    async function runCommand(rawText) {
        const text = normalize(rawText);
        const pool = getPageCommands().concat(buildGlobalCommands());

        for (const cmd of pool) {
            const args = matchCommand(cmd, text);
            if (!args) continue;

            // Echo what was heard, always, before acting.
            await speak(rawText);

            if (cmd.destructive) {
                const confirmed = await runConfirmation();
                if (!confirmed) return;
            }

            try {
                await cmd.handler(args, rawText);
            } catch (e) {
                // A page-command handler throwing should never crash the
                // voice loop — log for developers, stay silent to the user
                // beyond what the handler itself already spoke, if anything.
                console.error('[Bassira] command handler error:', e);
            }
            return;
        }

        await speak(strings().commandNotUnderstood);
    }

    // -------------------------------------------------------------
    // Mic permission + startup. `init()` is the explicit entry point
    // pages call from a single user gesture (a button's onclick) to
    // request microphone access the first time. On later page loads,
    // the same underlying flow is re-used automatically on
    // DOMContentLoaded: if permission was already granted, the browser
    // does not re-prompt and listening starts silently; if it wasn't,
    // this attempt simply fails quietly and the page's "activate" button
    // (calling init() from a real click) remains the way in.
    // -------------------------------------------------------------
    let started = false;

    async function requestMicAndStart() {
        if (started || micPermissionDenied) return;

        if (!speechRecognitionSupported) {
            writeStatus(strings().notSupported);
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            // Very old browser without getUserMedia — attempt to start
            // recognition directly; it will surface its own permission
            // prompt (or a 'not-allowed' error we already handle).
            started = true;
            setState(STATE.IDLE);
            startIdleListening();
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            // We only needed this to (a) trigger/confirm the permission
            // grant and (b) warm up the AudioContext for earcons — actual
            // recognition audio capture is handled internally by the
            // SpeechRecognition API, not this stream, so release it.
            stream.getTracks().forEach((t) => t.stop());

            if (!audioCtx) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) audioCtx = new AudioCtx();
            }

            started = true;
            setState(STATE.IDLE);
            startIdleListening();
        } catch (e) {
            micPermissionDenied = true;
            writeStatus(strings().micDenied);
        }
    }

    function reenableMicrophone() {
        // Used by settings.php's "re-enable microphone" button after a
        // user manually re-grants permission in browser settings —
        // resets our internal flags so init() can try again.
        micPermissionDenied = false;
        started = false;
        return requestMicAndStart();
    }

    // Opportunistic silent attempt on every page load: if the origin
    // already has mic permission from a previous visit, this starts
    // listening with no prompt at all, per the requirement that only the
    // very first grant needs an explicit gesture.
    document.addEventListener('DOMContentLoaded', () => {
        requestMicAndStart();
    });

    // -------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------
    window.Bassira = {
        init: requestMicAndStart,
        reenableMicrophone,
        speak,
        captureUtterance,
        isAffirmative: (text) => !!text && strings().yesWords.some((w) => text.indexOf(w) !== -1),
        getState: () => currentState,
        isMicPermissionDenied: () => micPermissionDenied,
        isStarted: () => started,
        currentSpeechLocale,
    };
})();
