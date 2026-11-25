-- =====================================================================
-- Bassira Hall (قاعة بصيرة) — Database Schema
-- Voice-first accessible classroom platform for blind/visually impaired
-- university students.
--
-- Engine: InnoDB everywhere (foreign keys required).
-- Charset: utf8mb4 / utf8mb4_unicode_ci everywhere (Arabic + French + English
-- text, plus diacritics on Qur'anic text seen in course material, need full
-- Unicode — utf8mb4, not utf8, or diacritics/emoji silently corrupt).
-- Access from PHP is exclusively via PDO prepared statements — no raw
-- string interpolation into SQL anywhere in the app layer.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
-- Single table for both students and teachers, distinguished by `role`.
-- preferred_language drives UI text (t()), STT recognition.lang, and the
-- TTS voice Bassira uses — set at signup/settings, read every page load.
-- speech_rate is the multiplier applied to speechSynthesis.rate client-side
-- (e.g. 1.2 as seen in the existing frontend's "السرعة: 1.2x" readout).
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role                ENUM('student', 'teacher') NOT NULL,
    name                VARCHAR(150) NOT NULL,
    email               VARCHAR(190) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,          -- password_hash() output (bcrypt/argon2i)
    preferred_language  ENUM('ar', 'fr', 'en') NOT NULL DEFAULT 'ar',
    speech_rate         DECIMAL(3,2) NOT NULL DEFAULT 1.00, -- e.g. 0.75–2.00
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- courses
-- ---------------------------------------------------------------------
-- Owned by exactly one teacher. No dashboard/progress rollup fields here
-- by design — this project has no dashboard page.
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT UNSIGNED NOT NULL,
    name        VARCHAR(200) NOT NULL,
    description TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)
        ON DELETE CASCADE,                     -- a teacher account being removed removes their courses
    KEY idx_courses_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- enrollments
-- ---------------------------------------------------------------------
-- Pure student<->course join. Deliberately no progress-bar / percent-
-- complete fields — that belonged to the dashboard concept, which this
-- build does not have. Per-material progress lives in reading_progress
-- instead, keyed at the material level, which is what voice resume needs.
-- ---------------------------------------------------------------------
CREATE TABLE enrollments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED NOT NULL,
    course_id   INT UNSIGNED NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,                     -- course deleted -> enrollment records are meaningless, cascade
    UNIQUE KEY uq_enroll_student_course (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- materials
-- ---------------------------------------------------------------------
-- PDF-backed course content uploaded by a teacher.
--
-- content_json stores the structured, page-and-line-segmented extraction
-- of the PDF, because Bassira's voice navigation ("go to page 5", "read
-- the previous line") needs exact, stable, addressable units to read from
-- and seek within — re-parsing the raw PDF on every voice command would be
-- slow and non-deterministic (line boundaries could shift). Extraction
-- happens once at upload time; voice commands only ever index into this
-- JSON afterwards.
--
-- Shape of content_json:
-- {
--   "pages": [
--     {
--       "page_number": 1,
--       "lines": [
--         { "line_number": 1, "text": "..." },
--         { "line_number": 2, "text": "..." }
--       ]
--     },
--     ...
--   ]
-- }
-- "lines" are sentence-level segments produced from the page's extracted
-- text (split on sentence boundaries, not raw PDF text-line breaks, since
-- PDF line breaks are a layout artifact and often mid-sentence — sentence
-- segments are what actually make sense read aloud one at a time).
-- reading_progress.last_line and submission_answers question numbers are
-- both "addressed" against this structure (page_number + line_number),
-- never against a raw byte/char offset into the original PDF.
--
-- deleted_at: see note above submission_answers for the project-wide soft-
-- delete decision. Materials use soft delete (not hard delete) because a
-- deletion here can invalidate reading_progress and notes for potentially
-- many enrolled students at once — a much wider blast radius than a
-- single student's own note or answer, and it is not gated by the same
-- spoken double-confirmation flow (teachers manage materials through a
-- typed web form, not voice). Soft delete keeps that action recoverable
-- by an admin/teacher without needing a separate "trash" mechanism.
-- ---------------------------------------------------------------------
CREATE TABLE materials (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id     INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    content_json  JSON NOT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    CONSTRAINT fk_materials_course FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,
    KEY idx_materials_course (course_id),
    KEY idx_materials_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reading_progress
-- ---------------------------------------------------------------------
-- One row per (student, material). Tracks the last page + line read so
-- Bassira can resume ("توقفنا عند الصفحة ٣") instead of restarting from
-- page 1 every time the material is reopened.
-- ---------------------------------------------------------------------
CREATE TABLE reading_progress (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED NOT NULL,
    material_id  INT UNSIGNED NOT NULL,
    last_page    INT UNSIGNED NOT NULL DEFAULT 1,
    last_line    INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_progress_student FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progress_material FOREIGN KEY (material_id) REFERENCES materials(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_progress_student_material (student_id, material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notes
-- ---------------------------------------------------------------------
-- Tied to a course (not to a specific material) because the voice command
-- "خذي ملاحظة" during a reading session auto-titles the note after the
-- COURSE name, per spec, regardless of which material triggered it.
-- source distinguishes notes typed/created manually on notes.php from
-- ones dictated mid-reading via the voice command.
--
-- deleted_at: NOT used here — see submission_answers comment below for
-- the project-wide rationale. Notes are deleted via a voice command that
-- already passes through echo -> spoken "نعم" confirmation before the
-- DELETE statement ever runs, so the confirmation step itself is where
-- "recoverability" lives; a hard delete afterwards is fine and keeps the
-- data model simple.
-- ---------------------------------------------------------------------
CREATE TABLE notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    content     TEXT NOT NULL,
    source      ENUM('manual', 'voice') NOT NULL DEFAULT 'manual',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notes_course FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notes_student FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE,
    KEY idx_notes_student_course (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- homework
-- ---------------------------------------------------------------------
-- PDF-backed exactly like materials (same content_json shape/rationale —
-- see materials above), tied to a course, with a due date. Soft-deleted
-- for the same wide-blast-radius reason as materials (affects every
-- enrolled student's submissions).
-- ---------------------------------------------------------------------
CREATE TABLE homework (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id     INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    content_json  JSON NOT NULL,
    due_date      DATETIME NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    CONSTRAINT fk_homework_course FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,
    KEY idx_homework_course (course_id),
    KEY idx_homework_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- submissions
-- ---------------------------------------------------------------------
-- One per (student, homework). status starts 'draft' and flips to
-- 'submitted' only via the fully-confirmed "بصيرة سلّمي الواجب" voice
-- flow, at which point submitted_at is stamped. Once 'submitted', the
-- application layer must reject further writes to this submission's
-- answers (enforced in PHP, not by a DB trigger, so the rejection message
-- can be a spoken, translated sentence rather than a raw SQL error).
-- ---------------------------------------------------------------------
CREATE TABLE submissions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id   INT UNSIGNED NOT NULL,
    student_id    INT UNSIGNED NOT NULL,
    status        ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
    submitted_at  DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_submissions_homework FOREIGN KEY (homework_id) REFERENCES homework(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_submissions_homework_student (homework_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- submission_answers
-- ---------------------------------------------------------------------
-- One row per submission per question NUMBER — a plain integer the
-- student states out loud ("الإجابة على السؤال ٣ هي..."), deliberately
-- NOT a foreign key to any parsed question entity. The homework PDF's
-- question boundaries live only inside content_json as page/line text;
-- reliably auto-detecting "this line is the start of question 3" from
-- arbitrary PDF layout is not attempted anywhere in this system (too
-- unreliable — inconsistent numbering styles, multi-line questions,
-- OCR/extraction noise). Keying by the spoken integer instead means the
-- student's own statement of which question they're answering is the
-- single source of truth, and answering/re-answering/deleting question N
-- is just an upsert/delete against (submission_id, question_number) —
-- simple, predictable, and matches exactly what the voice command says.
--
-- deleted_at is NOT used here (project-wide decision, see below).
-- ---------------------------------------------------------------------
CREATE TABLE submission_answers (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id    INT UNSIGNED NOT NULL,
    question_number  INT UNSIGNED NOT NULL,
    answer_text      TEXT NOT NULL,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_answers_submission FOREIGN KEY (submission_id) REFERENCES submissions(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_answers_submission_question (submission_id, question_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- messages
-- ---------------------------------------------------------------------
-- Simple sender/recipient message, both referencing users (student<->
-- teacher in practice, but not constrained by role at the DB level so a
-- teacher-to-teacher or admin broadcast isn't structurally ruled out).
-- read_at is set the moment Bassira actually speaks the message aloud to
-- the student ("بصيرة اقرئي رسائلي" marks each one read_at = NOW() as it
-- is read, oldest first), not merely when the messages.php page loads.
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id     INT UNSIGNED NOT NULL,
    recipient_id  INT UNSIGNED NOT NULL,
    body          TEXT NOT NULL,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at       DATETIME NULL,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users(id)
        ON DELETE CASCADE,
    KEY idx_messages_recipient_unread (recipient_id, read_at),
    KEY idx_messages_sender (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Project-wide soft-delete decision (referenced above by several tables)
-- =====================================================================
-- Two different deletion paths exist in this app, so two different
-- safety mechanisms are used, deliberately not the same one everywhere:
--
-- 1. Teacher-authored content with wide blast radius (materials,
--    homework) — deleting one row can orphan data (reading_progress,
--    notes, submissions) belonging to many different students, and
--    teachers act through typed web forms rather than the spoken
--    double-confirmation flow. These tables get deleted_at (soft
--    delete): the row is hidden from normal queries (WHERE deleted_at
--    IS NULL) but recoverable, and nothing downstream is silently
--    destroyed by an accidental click.
--
-- 2. A single student's own single-row content, deleted by voice
--    (notes, submission_answers) — every destructive voice command in
--    this system already runs echo -> "قولي 'نعم' للتأكيد" -> wait for
--    yes -> only then execute, enforced in voice_assistant.js /
--    included on every page before this action ever reaches the
--    database. The confirmation IS the safety net, so these tables use
--    a plain hard DELETE — no deleted_at column, no extra recovery
--    layer, since one already exists one step earlier in the flow.
--
-- messages are never deleted by either the spec or the UI, so no
-- deleted_at column is added there either.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 1;
