/**
 * assets/js/homework.js
 *
 * Thin, assignment-specific wrapper around assets/js/reading_engine.js —
 * "reusing the PDF reading engine from courses.php" per the spec. Depends
 * on globals the page sets before this script runs:
 *   - window.Bassira                (assets/js/voice_assistant.js)
 *   - window.BASSIRA_HOMEWORK       { id, courseId, courseName, title, pages, submitted }
 *   - window.BASSIRA_HOMEWORK_STRINGS
 *   - createBassiraReadingEngine    (assets/js/reading_engine.js)
 *
 * Exposes window.Homework = { pause, resume, gotoPage, repeatPrevious,
 * answerQuestion, deleteAnswer, submitHomework } for homework.php's
 * window.pageVoiceCommands handlers to call.
 *
 * Unlike reader.php's "خذي ملاحظة" (a two-step trigger-then-dictate flow)
 * and notes.php's "عدّلي" (a manual dictate -> echo -> confirm sequence
 * built by hand), the answer/delete-answer/submit commands here use the
 * voice engine's standard `destructive: true` flag directly — because the
 * question number AND the full answer text both arrive in ONE spoken
 * utterance ("الإجابة على السؤال 3 هي كذا"), parsed via regex at match()
 * time in homework.php's own command registration. The engine's built-in
 * echo -> confirm -> act flow already echoes that whole utterance back
 * (which includes the dictated answer) before confirming, so there's
 * nothing extra to build here — this is the version of "destructive with
 * confirmation" that fits the standard flow perfectly, no custom
 * dictate-then-confirm choreography needed.
 */

(function () {
    'use strict';

    const homework = window.BASSIRA_HOMEWORK;
    if (!homework) {
        console.error('[Homework] window.BASSIRA_HOMEWORK is missing.');
        return;
    }

    const strings = window.BASSIRA_HOMEWORK_STRINGS || {};

    // Mirrors the server's authoritative check (every endpoint re-checks
    // submission status itself) — this client-side flag just avoids a
    // pointless round trip and gives an instant spoken response once the
    // student has already submitted in THIS session.
    let submitted = !!homework.submitted;

    const engine = createBassiraReadingEngine({
        title: homework.title,
        pages: homework.pages,
        resume: null, // homework has no persisted reading position (no reading_progress-equivalent table)
        finishedMessage: strings.finished || '',
    });

    async function answerQuestion(questionNumber, answerText) {
        if (submitted) {
            await window.Bassira.speak(strings.alreadySubmitted || '');
            return;
        }

        try {
            const res = await fetch('/submission_answer_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'homework_id=' + encodeURIComponent(homework.id) +
                      '&question_number=' + encodeURIComponent(questionNumber) +
                      '&answer_text=' + encodeURIComponent(answerText),
            });
            const data = await res.json();
            if (data.ok) {
                await window.Bassira.speak((strings.answerSaved || '').replace('%s', questionNumber));
            } else if (data.error === 'already_submitted') {
                submitted = true;
                await window.Bassira.speak(strings.alreadySubmitted || '');
            } else {
                await window.Bassira.speak(strings.answerSaveFailed || '');
            }
        } catch (e) {
            console.error('[Homework] failed to save answer:', e);
            await window.Bassira.speak(strings.answerSaveFailed || '');
        }
    }

    async function deleteAnswer(questionNumber) {
        if (submitted) {
            await window.Bassira.speak(strings.alreadySubmitted || '');
            return;
        }

        try {
            const res = await fetch('/submission_answer_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'homework_id=' + encodeURIComponent(homework.id) +
                      '&question_number=' + encodeURIComponent(questionNumber),
            });
            const data = await res.json();
            if (data.ok) {
                await window.Bassira.speak((strings.answerDeleted || '').replace('%s', questionNumber));
            } else if (data.error === 'already_submitted') {
                submitted = true;
                await window.Bassira.speak(strings.alreadySubmitted || '');
            } else if (data.error === 'no_answer') {
                await window.Bassira.speak(strings.noAnswerForQuestion || '');
            } else {
                await window.Bassira.speak(strings.answerDeleteFailed || '');
            }
        } catch (e) {
            console.error('[Homework] failed to delete answer:', e);
            await window.Bassira.speak(strings.answerDeleteFailed || '');
        }
    }

    async function submitHomework() {
        if (submitted) {
            await window.Bassira.speak(strings.alreadySubmitted || '');
            return;
        }

        try {
            const res = await fetch('/submission_submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'homework_id=' + encodeURIComponent(homework.id),
            });
            const data = await res.json();
            if (data.ok) {
                submitted = true;
                await window.Bassira.speak(
                    data.already ? (strings.alreadySubmitted || '') : (strings.homeworkSubmitted || '')
                );
            } else {
                await window.Bassira.speak(strings.answerSaveFailed || '');
            }
        } catch (e) {
            console.error('[Homework] failed to submit:', e);
            await window.Bassira.speak(strings.answerSaveFailed || '');
        }
    }

    window.Homework = {
        pause: engine.pause,
        resume: engine.resume,
        gotoPage: engine.gotoPage,
        repeatPrevious: engine.repeatPrevious,
        answerQuestion,
        deleteAnswer,
        submitHomework,
    };
})();
