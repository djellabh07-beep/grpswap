/**
 * assets/js/reader.js
 *
 * Thin, material-specific wrapper around assets/js/reading_engine.js.
 * Depends on globals the page sets before this script runs:
 *   - window.Bassira            (assets/js/voice_assistant.js, loaded first)
 *   - window.BASSIRA_MATERIAL   { id, courseId, courseName, title, pages, resume }
 *   - window.BASSIRA_READER_STRINGS
 *   - createBassiraReadingEngine (assets/js/reading_engine.js, loaded first)
 *
 * Exposes window.Reader = { pause, resume, gotoPage, repeatPrevious,
 * dictateNote } for reader.php's window.pageVoiceCommands handlers to call.
 * pause/resume/gotoPage/repeatPrevious are the shared engine's, unchanged;
 * dictateNote ("خذي ملاحظة") is material-specific — it doesn't exist for
 * homework.php, which has its own different set of extra commands layered
 * on the very same shared engine instead.
 */

(function () {
    'use strict';

    const material = window.BASSIRA_MATERIAL;
    if (!material) {
        console.error('[Reader] window.BASSIRA_MATERIAL is missing.');
        return;
    }

    const strings = window.BASSIRA_READER_STRINGS || {};

    async function saveProgress(pageNumber, lineNumber) {
        try {
            await fetch('/reading_progress_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:
                    'material_id=' + encodeURIComponent(material.id) +
                    '&page=' + encodeURIComponent(pageNumber) +
                    '&line=' + encodeURIComponent(lineNumber),
            });
        } catch (e) {
            // Non-fatal — losing one progress checkpoint due to a network
            // blip shouldn't interrupt the reading experience itself.
            console.error('[Reader] failed to save progress:', e);
        }
    }

    async function saveNote(content) {
        try {
            const res = await fetch('/notes_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'course_id=' + encodeURIComponent(material.courseId) +
                      '&content=' + encodeURIComponent(content),
            });
            const data = await res.json();
            return !!data.ok;
        } catch (e) {
            console.error('[Reader] failed to save note:', e);
            return false;
        }
    }

    const engine = createBassiraReadingEngine({
        title: material.title,
        pages: material.pages,
        resume: material.resume,
        finishedMessage: strings.materialFinished || '',
        resumePointMessage: material.resume && material.resume.page
            ? (strings.resumePoint || '').replace('%s', material.resume.page)
            : null,
        onProgress: saveProgress,
    });

    async function dictateNote() {
        const wasPlaying = !engine.isPaused();
        engine.pause();

        await window.Bassira.speak(strings.dictationStart || '');
        const text = await window.Bassira.captureUtterance(8000);

        if (!text) {
            await window.Bassira.speak(strings.noteNotCaptured || '');
        } else {
            const ok = await saveNote(text);
            await window.Bassira.speak(
                ok
                    ? (strings.noteSaved || '').replace('%s', material.courseName)
                    : (strings.noteSaveFailed || '')
            );
        }

        if (wasPlaying) engine.resume();
    }

    window.Reader = {
        pause: engine.pause,
        resume: engine.resume,
        gotoPage: engine.gotoPage,
        repeatPrevious: engine.repeatPrevious,
        dictateNote,
    };
})();
