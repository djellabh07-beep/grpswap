<?php
/**
 * includes/submissions.php
 *
 * Shared by submission_answer_save.php, submission_answer_delete.php, and
 * submission_submit.php — all three need the same "find or create this
 * student's submission row for this homework, but only if they're
 * actually enrolled in its course" logic.
 */

/**
 * Returns the student's submission row for $homeworkId, creating a fresh
 * 'draft' one if none exists yet. Returns null if the homework doesn't
 * exist (or is soft-deleted) or the student isn't enrolled in its course
 * — callers should treat null as "reject the request", never create a
 * submission for a homework the student can't actually see.
 */
function bassira_get_or_create_submission(PDO $pdo, int $homeworkId, int $studentId): ?array
{
    $homeworkStmt = $pdo->prepare(
        'SELECT h.id
         FROM homework h
         JOIN enrollments e ON e.course_id = h.course_id
         WHERE h.id = ? AND e.student_id = ? AND h.deleted_at IS NULL'
    );
    $homeworkStmt->execute([$homeworkId, $studentId]);
    if (!$homeworkStmt->fetch()) {
        return null;
    }

    $selectStmt = $pdo->prepare(
        'SELECT id, status, submitted_at FROM submissions WHERE homework_id = ? AND student_id = ?'
    );
    $selectStmt->execute([$homeworkId, $studentId]);
    $submission = $selectStmt->fetch();

    if ($submission) {
        return $submission;
    }

    // INSERT ... ON DUPLICATE KEY UPDATE guards against a race where two
    // requests both miss the SELECT above at nearly the same time (e.g.
    // two answers dictated in quick succession) — the UNIQUE
    // (homework_id, student_id) key makes the second insert a no-op
    // update instead of a duplicate-row error.
    $insertStmt = $pdo->prepare(
        "INSERT INTO submissions (homework_id, student_id, status)
         VALUES (?, ?, 'draft')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $insertStmt->execute([$homeworkId, $studentId]);

    $selectStmt->execute([$homeworkId, $studentId]);
    return $selectStmt->fetch() ?: null;
}
