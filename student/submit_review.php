
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Allow POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: notes.php');
    exit;
}

// Check whether the student is logged in.
$studentId = (int)($_SESSION['student_db_id'] ?? 0);

if ($studentId <= 0) {
    header('Location: ../login.php');
    exit;
}

// Validate the CSRF token.
$sessionToken = $_SESSION['review_csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($sessionToken) ||
    !is_string($submittedToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    exit('Invalid or expired form token. Please return to the notes page and try again.');
}

// Validate note ID.
$noteId = filter_input(
    INPUT_POST,
    'note_id',
    FILTER_VALIDATE_INT
);

if (!$noteId || $noteId <= 0) {
    exit('Invalid note ID.');
}

// Validate rating: must be an integer from 1 to 5.
$rating = filter_input(
    INPUT_POST,
    'rating',
    FILTER_VALIDATE_INT
);

if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
    exit('Please select a rating between 1 and 5.');
}

// Validate review text.
$reviewText = trim($_POST['review_text'] ?? '');

if (strlen($reviewText) > 2000) {
    exit('Your review must not exceed 2000 bytes.');
}

$reviewText = ($reviewText === '') ? null : $reviewText;

try {
    // Check that the note exists and get its uploader.
    $noteSql = "SELECT student_db_id
                FROM notes
                WHERE note_id = ?
                LIMIT 1";

    $noteStmt = mysqli_prepare($conn, $noteSql);
    mysqli_stmt_bind_param($noteStmt, "i", $noteId);
    mysqli_stmt_execute($noteStmt);

    $noteResult = mysqli_stmt_get_result($noteStmt);
    $note = mysqli_fetch_assoc($noteResult);

    mysqli_stmt_close($noteStmt);

    if (!$note) {
        exit('The selected note does not exist.');
    }

    // Students cannot review their own notes.
    if ((int)$note['student_db_id'] === $studentId) {
        exit('You cannot review your own uploaded note.');
    }

    /*
     * Insert a new review.
     * If this student has already reviewed the same note,
     * update the existing review instead.
     *
     * Requires UNIQUE(note_id, student_db_id)
     * in the note_reviews table.
     */
    $reviewSql = "INSERT INTO note_reviews
                    (note_id, student_db_id, rating, review_text)
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    review_text = VALUES(review_text),
                    created_at = CURRENT_TIMESTAMP";

    $reviewStmt = mysqli_prepare($conn, $reviewSql);

    mysqli_stmt_bind_param(
        $reviewStmt,
        "iiis",
        $noteId,
        $studentId,
        $rating,
        $reviewText
    );

    mysqli_stmt_execute($reviewStmt);
    mysqli_stmt_close($reviewStmt);

    header(
        'Location: notes.php?review_status=success#note-' . $noteId
    );
    exit;

} catch (mysqli_sql_exception $exception) {
    error_log('Review submission error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to save your review right now. Please try again later.');
}
