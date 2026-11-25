/**
 * assets/js/notes.js
 *
 * Page-specific module for notes.php. Depends on globals the page sets
 * before this script runs:
 *   - window.Bassira               (voice_assistant.js, loaded first)
 *   - window.BASSIRA_NOTES         { courses: [{ id, name, notes: [...] }] }
 *   - window.BASSIRA_NOTES_STRINGS (translated strings, see notes.php)
 *
 * Exposes window.Notes = { selectNote, readCurrent, editCurrent,
 * deleteCurrent, openCourse } for notes.php's window.pageVoiceCommands
 * handlers (and the visual click-to-select note items) to call.
 *
 * ---------------------------------------------------------------------
 * On the "عدّلي" (edit) confirmation flow
 * ---------------------------------------------------------------------
 * This does NOT use the engine's `destructive: true` flag. That flag
 * confirms the TRIGGER command itself, before anything else happens
 * ("قولي 'نعم' للتأكيد" gates running the handler at all) — appropriate
 * for a plain "delete this" command, but wrong here: the spec asks for
 * dictation to happen FIRST, and for the confirmation to be about the
 * newly-dictated REPLACEMENT content specifically ("echoes the new
 * content back and asks for confirmation before saving"). So editCurrent()
 * runs as a normal (non-destructive, at the engine level) handler that
 * manually replicates the same echo -> confirm -> act shape using the
 * same primitives (Bassira.speak / Bassira.captureUtterance /
 * Bassira.isAffirmative) the engine itself uses internally — just applied
 * to the dictated text instead of to the trigger phrase.
 * deleteCurrent(), by contrast, IS registered `destructive: true` in
 * notes.php, since a plain "confirm before deleting" is exactly what that
 * flag already does.
 */

(function () {
    'use strict';

    const data = window.BASSIRA_NOTES || { courses: [] };
    const strings = window.BASSIRA_NOTES_STRINGS || {};

    let currentNoteId = null;

    function findNote(noteId) {
        for (const course of data.courses) {
            const note = course.notes.find((n) => n.id === noteId);
            if (note) return note;
        }
        return null;
    }

    function findCourse(courseId) {
        return data.courses.find((c) => c.id === courseId) || null;
    }

    function highlightCurrent() {
        document.querySelectorAll('[data-note-id]').forEach((el) => {
            const isCurrent = Number(el.dataset.noteId) === currentNoteId;
            el.classList.toggle('bassira-current-note', isCurrent);
            el.setAttribute('aria-current', isCurrent ? 'true' : 'false');
        });
    }

    function updateNoteContentInDom(note) {
        const el = document.querySelector('[data-note-id="' + note.id + '"] [data-note-content]');
        if (el) el.textContent = note.content;
    }

    function removeNoteFromDomAndData(noteId) {
        const el = document.querySelector('[data-note-id="' + noteId + '"]');
        if (el) {
            const courseSection = el.closest('[data-course-section]');
            el.remove();
            // If that was the last note in the course, drop the whole
            // section rather than leaving an empty course card behind.
            if (courseSection && !courseSection.querySelector('[data-note-id]')) {
                courseSection.remove();
            }
        }
        for (const course of data.courses) {
            course.notes = course.notes.filter((n) => n.id !== noteId);
        }
    }

    async function saveEdit(noteId, content) {
        try {
            const res = await fetch('/notes_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'note_id=' + encodeURIComponent(noteId) + '&content=' + encodeURIComponent(content),
            });
            const json = await res.json();
            return !!json.ok;
        } catch (e) {
            console.error('[Notes] failed to save edit:', e);
            return false;
        }
    }

    async function deleteNoteRequest(noteId) {
        try {
            const res = await fetch('/notes_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'note_id=' + encodeURIComponent(noteId),
            });
            const json = await res.json();
            return !!json.ok;
        } catch (e) {
            console.error('[Notes] failed to delete:', e);
            return false;
        }
    }

    // ---------------------------------------------------------------
    // Public commands
    // ---------------------------------------------------------------
    function selectNote(noteId) {
        currentNoteId = noteId;
        highlightCurrent();
    }

    async function readCurrent() {
        const note = findNote(currentNoteId);
        if (!note) {
            await window.Bassira.speak(strings.noNoteSelected || '');
            return;
        }
        await window.Bassira.speak(note.content);
    }

    async function editCurrent() {
        const note = findNote(currentNoteId);
        if (!note) {
            await window.Bassira.speak(strings.noNoteSelected || '');
            return;
        }

        await window.Bassira.speak(strings.dictationStart || '');
        const dictated = await window.Bassira.captureUtterance(8000);
        if (!dictated) {
            await window.Bassira.speak(strings.noteNotCaptured || '');
            return;
        }

        // Echo the NEW content back, then confirm — per spec, this
        // confirmation is about the replacement text, not the "عدّلي"
        // trigger itself.
        await window.Bassira.speak((strings.editEcho || '%s').replace('%s', dictated));
        await window.Bassira.speak(strings.confirmPrompt || '');
        const reply = await window.Bassira.captureUtterance(5000);

        if (reply === null) {
            await window.Bassira.speak(strings.confirmTimeout || '');
            return;
        }
        if (!window.Bassira.isAffirmative(reply)) {
            await window.Bassira.speak(strings.confirmCancelled || '');
            return;
        }

        const ok = await saveEdit(note.id, dictated);
        if (ok) {
            note.content = dictated;
            updateNoteContentInDom(note);
            await window.Bassira.speak(strings.editSaved || '');
        } else {
            await window.Bassira.speak(strings.editFailed || '');
        }
    }

    async function deleteCurrent() {
        // Reached only after the engine's own destructive confirm flow
        // (registered via `destructive: true` in notes.php) already
        // succeeded — this just performs the already-confirmed delete.
        const note = findNote(currentNoteId);
        if (!note) {
            await window.Bassira.speak(strings.noNoteSelected || '');
            return;
        }

        const ok = await deleteNoteRequest(note.id);
        if (ok) {
            removeNoteFromDomAndData(note.id);
            if (currentNoteId === note.id) currentNoteId = null;
            await window.Bassira.speak(strings.deleteSaved || '');
        } else {
            await window.Bassira.speak(strings.deleteFailed || '');
        }
    }

    async function openCourse(courseId) {
        const course = findCourse(courseId);
        const section = document.getElementById('notes-course-' + courseId);
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (!course || course.notes.length === 0) {
            await window.Bassira.speak(strings.noNotesInCourse || '');
            return;
        }

        // Notes arrive from the server most-recent-first (see notes.php's
        // query) — with no per-note spoken identifier to disambiguate by
        // (unlike materials, which have distinct titles), the most recent
        // note in the course is the sensible default "current" note when
        // opening by course name alone.
        selectNote(course.notes[0].id);

        await window.Bassira.speak(
            (strings.courseOpenedCount || '')
                .replace('%1$s', String(course.notes.length))
                .replace('%2$s', course.name)
        );
        await readCurrent();
    }

    window.Notes = { selectNote, readCurrent, editCurrent, deleteCurrent, openCourse };
})();
