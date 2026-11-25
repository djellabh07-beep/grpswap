/**
 * assets/js/messages.js
 *
 * Student-side voice module for messages.php. Depends on globals the
 * page sets before this script runs:
 *   - window.Bassira              (assets/js/voice_assistant.js)
 *   - window.BASSIRA_MESSAGES     { unread: [{id, senderName, body}] }
 *   - window.BASSIRA_MESSAGES_STRINGS
 *
 * Exposes window.Messages = { readUnread, sendMessage } for messages.php's
 * window.pageVoiceCommands handlers to call.
 *
 * ---------------------------------------------------------------------
 * On "أرسلي رسالة إلى [اسم الأستاذ]"
 * ---------------------------------------------------------------------
 * Like notes.php's "عدّلي", this does NOT use the engine's
 * `destructive: true` flag. Sending a message isn't overwriting anything
 * (so it isn't "destructive" in that sense at all) — but per spec it's
 * still a real-world action with a real recipient, so it gets the same
 * confirm treatment. The content to confirm (the message body) doesn't
 * exist yet when the trigger phrase is spoken — it only exists after
 * dictation — so exactly like notes.js's editCurrent(), this manually
 * replicates dictate -> echo -> confirm -> act using the same primitives
 * (Bassira.speak / captureUtterance / isAffirmative) the engine uses
 * internally, rather than confirming the trigger phrase itself.
 */

(function () {
    'use strict';

    const data = window.BASSIRA_MESSAGES || { unread: [] };
    const strings = window.BASSIRA_MESSAGES_STRINGS || {};

    async function markRead(messageId) {
        try {
            await fetch('/messages_mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'message_id=' + encodeURIComponent(messageId),
            });
        } catch (e) {
            // Non-fatal — losing one read-receipt due to a network blip
            // shouldn't interrupt reading the rest of the messages.
            console.error('[Messages] failed to mark read:', e);
        }
    }

    async function readUnread() {
        const unread = data.unread || [];

        if (unread.length === 0) {
            await window.Bassira.speak(strings.noUnread || '');
            return;
        }

        await window.Bassira.speak((strings.readingCount || '').replace('%s', String(unread.length)));

        // Oldest first — the server already provides them in that order.
        for (const message of unread) {
            const announcement = (strings.fromLabel || '') + ' ' + message.senderName + ': ' + message.body;
            await window.Bassira.speak(announcement);
            await markRead(message.id);
        }
    }

    async function sendMessage(teacherId, teacherName) {
        await window.Bassira.speak(strings.dictationStart || '');
        const body = await window.Bassira.captureUtterance(10000);

        if (!body) {
            await window.Bassira.speak(strings.bodyNotCaptured || '');
            return;
        }

        await window.Bassira.speak((strings.sendEcho || '%s').replace('%s', body));
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

        try {
            const res = await fetch('/messages_send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'recipient_id=' + encodeURIComponent(teacherId) + '&body=' + encodeURIComponent(body),
            });
            const json = await res.json();
            await window.Bassira.speak(json.ok ? (strings.sentSuccess || '') : (strings.sendFailed || ''));
        } catch (e) {
            console.error('[Messages] failed to send:', e);
            await window.Bassira.speak(strings.sendFailed || '');
        }
    }

    window.Messages = { readUnread, sendMessage };
})();
