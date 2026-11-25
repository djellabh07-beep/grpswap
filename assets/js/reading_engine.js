/**
 * assets/js/reading_engine.js
 *
 * The generic page-by-line sequential reading engine shared by reader.php
 * (course materials) and homework.php (assignments/exams) — "reusing the
 * PDF reading engine from courses.php" per the homework.php spec. Neither
 * page-specific concept — course materials' "خذي ملاحظة" note-taking, or
 * homework's per-question answer dictation — lives in here; this module
 * only knows about pages/lines, autoplay, pause/resume, page-jump, and
 * repeat. Each page's own thin wrapper script (reader.js / homework.js)
 * composes its extra commands on top of what this returns.
 *
 * Usage:
 *   const engine = createBassiraReadingEngine({
 *     title: 'Chapter One',
 *     pages: [...],                 // [{ page_number, lines: [{line_number, text}] }]
 *     resume: { page: 2, line: 3 }, // or null — where to continue from
 *     finishedMessage: '...',
 *     resumePointMessage: '...',    // already formatted, e.g. via .replace('%s', n)
 *     onProgress: async (pageNumber, lineNumber) => { ... }, // optional
 *   });
 *   engine.pause(); engine.resume(); engine.gotoPage(3); engine.repeatPrevious();
 *
 * ---------------------------------------------------------------------
 * Cancellation design note (unchanged from the original reader.js)
 * ---------------------------------------------------------------------
 * A plain boolean "paused" flag isn't enough: pause/resume/gotoPage/
 * repeatPrevious can each be triggered by a wake-word command WHILE a
 * line is mid-speech, and speech-synthesis cancellation timing isn't
 * perfectly deterministic across browsers. So every state-changing action
 * bumps a `generation` counter; the autoplay loop captures its own
 * generation number when it starts and checks it after every await. If it
 * no longer matches, the loop stops immediately instead of possibly
 * reading one extra unwanted line. A line that was interrupted mid-speech
 * is intentionally re-spoken in full on the next resume/repeat rather
 * than silently skipped, since there's no reliable way to know how much
 * of it was actually heard.
 */

function createBassiraReadingEngine(config) {
    'use strict';

    const pages = config.pages || [];
    const onProgress = config.onProgress || (() => Promise.resolve());
    const finishedMessage = config.finishedMessage || '';
    const resumePointMessage = config.resumePointMessage || null;

    const flatLines = [];
    pages.forEach((page) => {
        (page.lines || []).forEach((line) => {
            flatLines.push({
                pageNumber: page.page_number,
                lineNumber: line.line_number,
                text: line.text,
            });
        });
    });

    let cursor = 0;
    let lastSpokenIndex = -1;
    let paused = false;
    let generation = 0;

    function findFlatIndex(pageNumber, lineNumber) {
        return flatLines.findIndex(
            (l) => l.pageNumber === pageNumber && (lineNumber == null || l.lineNumber === lineNumber)
        );
    }

    if (config.resume && config.resume.page) {
        const idx = findFlatIndex(config.resume.page, config.resume.line);
        cursor = idx >= 0 ? idx + 1 : 0;
    }

    function startAutoplay() {
        const myGen = ++generation;
        paused = false;

        (async () => {
            while (myGen === generation && cursor < flatLines.length) {
                const line = flatLines[cursor];
                await window.Bassira.speak(line.text);
                if (myGen !== generation) return;

                lastSpokenIndex = cursor;
                await onProgress(line.pageNumber, line.lineNumber);
                if (myGen !== generation) return;

                cursor++;
            }

            if (myGen === generation && cursor >= flatLines.length && flatLines.length > 0) {
                await window.Bassira.speak(finishedMessage);
            }
        })();
    }

    function pause() {
        generation++;
        paused = true;
    }

    function resume() {
        if (cursor >= flatLines.length) return;
        startAutoplay();
    }

    function gotoPage(pageNumber) {
        generation++;
        const idx = findFlatIndex(pageNumber, null);
        if (idx < 0) return;
        cursor = idx;
        startAutoplay();
    }

    async function repeatPrevious() {
        generation++;
        const wasPlaying = !paused;
        if (lastSpokenIndex < 0) {
            if (flatLines.length > 0) {
                await window.Bassira.speak(flatLines[0].text);
            }
        } else {
            await window.Bassira.speak(flatLines[lastSpokenIndex].text);
        }
        if (wasPlaying) startAutoplay();
    }

    async function announceAndStart() {
        await window.Bassira.speak(config.title || '');
        if (resumePointMessage) {
            await window.Bassira.speak(resumePointMessage);
        }
        startAutoplay();
    }

    if (config.autoStart !== false) {
        announceAndStart();
    }

    return { pause, resume, gotoPage, repeatPrevious, announceAndStart, isPaused: () => paused };
}
