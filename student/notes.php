
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../config/db.php';

$studentId = (int)($_SESSION['student_db_id'] ?? 0);
$isLoggedIn = $studentId > 0;

if ($isLoggedIn && empty($_SESSION['review_csrf_token'])) {
    $_SESSION['review_csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Fetch notes with course, uploader, and rating information.
$sql = "SELECT
            n.note_id,
            n.title,
            n.description,
            n.file_path,
            n.created_at,
            n.student_db_id AS uploader_id,
            c.course_name,
            c.course_code,
            s.name AS uploader_name,
            COALESCE(AVG(nr.rating), 0) AS avg_rating,
            COUNT(nr.review_id) AS review_count
        FROM notes n
        LEFT JOIN courses c
            ON n.course_id = c.course_id
        LEFT JOIN students s
            ON n.student_db_id = s.student_db_id
        LEFT JOIN note_reviews nr
            ON n.note_id = nr.note_id
        GROUP BY
            n.note_id,
            n.title,
            n.description,
            n.file_path,
            n.created_at,
            n.student_db_id,
            c.course_name,
            c.course_code,
            s.name
        ORDER BY n.created_at DESC";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Study Notes | CampusMate</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #243047;
        }

        .page {
            width: min(1250px, 94%);
            margin: 35px auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            color: #173b70;
            font-size: 30px;
        }

        .page-header p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
        }

        .notes-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            align-items: start;
        }

        .note-card {
            min-width: 0;
            padding: 18px;
            background: #fff;
            border: 1px solid #e0e7f0;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(20, 45, 80, 0.05);
        }

        .note-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .note-info {
            flex: 1;
            min-width: 0;
        }

        .note-info h2 {
            margin: 0 0 10px;
            color: #173b70;
            font-size: 18px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .course-info {
            color: #475569;
            font-size: 13px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .note-description {
            margin: 13px 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .note-meta {
            margin-top: 12px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .rating-box {
            flex-shrink: 0;
            min-width: 75px;
            padding: 9px 6px;
            background: #f0f6ff;
            border: 1px solid #d9e8ff;
            border-radius: 10px;
            text-align: center;
        }

        .rating-number {
            display: block;
            color: #173b70;
            font-size: 20px;
            font-weight: bold;
        }

        .rating-stars,
        .review-stars {
            color: #e5a000;
            letter-spacing: 1px;
        }

        .rating-stars {
            display: block;
            margin: 4px 0;
            font-size: 12px;
        }

        .rating-count {
            color: #64748b;
            font-size: 10px;
        }

        .note-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 10px 11px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #fff;
        }

        .btn-primary:hover {
            background: #173b70;
        }

        .btn-secondary {
            background: #e8eef7;
            color: #173b70;
        }

        .review-details {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #e6ebf2;
        }

        .review-details summary {
            cursor: pointer;
            padding: 6px 0;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 600;
        }

        .review-list {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .review-item {
            min-width: 0;
            padding: 11px;
            background: #f8fafc;
            border: 1px solid #e7edf5;
            border-radius: 9px;
        }

        .review-item-top {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }

        .reviewer-name {
            color: #334155;
            font-size: 12px;
            font-weight: 600;
        }

        .review-stars {
            font-size: 12px;
        }

        .review-text {
            margin: 8px 0 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .review-date {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 11px;
        }

        .review-form {
            display: grid;
            gap: 12px;
            margin-top: 13px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            font: inherit;
            font-size: 13px;
        }

        .form-group textarea {
            min-height: 85px;
            resize: vertical;
        }

        .form-note {
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .empty-message {
            grid-column: 1 / -1;
            padding: 30px;
            background: #fff;
            border: 1px solid #e0e7f0;
            border-radius: 12px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 950px) {
            .notes-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .notes-list {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .note-card {
                padding: 16px;
            }

            .note-info h2 {
                font-size: 19px;
            }

            .rating-box {
                min-width: 85px;
            }
        }
    </style>
</head>

<body>

<?php
$headerPath = __DIR__ . '/../includes/header.php';

if (file_exists($headerPath)) {
    require_once $headerPath;
}
?>

<main class="page">

    <header class="page-header">
        <h1>Study Notes</h1>
        <p>
            Explore study materials, view PDFs, and read reviews shared
            by other students.
        </p>
    </header>

    <section class="notes-list">

        <?php if (mysqli_num_rows($result) === 0): ?>

            <div class="empty-message">
                <h3>No notes available yet</h3>
                <p>Uploaded notes will appear here.</p>
            </div>

        <?php else: ?>

            <?php while ($note = mysqli_fetch_assoc($result)): ?>

                <?php
                $noteId = (int)$note['note_id'];
                $uploaderId = (int)$note['uploader_id'];
                $reviewCount = (int)$note['review_count'];
                $average = (float)$note['avg_rating'];

                // Fetch all reviews for this note.
                $reviewSql = "SELECT
                                  nr.rating,
                                  nr.review_text,
                                  nr.created_at,
                                  s.name AS reviewer_name
                              FROM note_reviews nr
                              LEFT JOIN students s
                                  ON nr.student_db_id = s.student_db_id
                              WHERE nr.note_id = ?
                              ORDER BY nr.created_at DESC";

                $reviewStmt = mysqli_prepare($conn, $reviewSql);
                mysqli_stmt_bind_param($reviewStmt, "i", $noteId);
                mysqli_stmt_execute($reviewStmt);
                $reviews = mysqli_stmt_get_result($reviewStmt);

                // Fetch the logged-in student's existing review only.
                $myReview = null;
                $myReviewStmt = null;

                if ($isLoggedIn && $uploaderId !== $studentId) {
                    $myReviewSql = "SELECT rating, review_text
                                    FROM note_reviews
                                    WHERE note_id = ?
                                      AND student_db_id = ?
                                    LIMIT 1";

                    $myReviewStmt = mysqli_prepare($conn, $myReviewSql);
                    mysqli_stmt_bind_param(
                        $myReviewStmt,
                        "ii",
                        $noteId,
                        $studentId
                    );
                    mysqli_stmt_execute($myReviewStmt);
                    $myReviewResult = mysqli_stmt_get_result($myReviewStmt);
                    $myReview = mysqli_fetch_assoc($myReviewResult) ?: null;
                }

                $pdfPath = (string)$note['file_path'];
                ?>

                <article class="note-card">

                    <div class="note-top">

                        <div class="note-info">
                            <h2><?= e($note['title']) ?></h2>

                            <div class="course-info">
                                <strong>Course:</strong>
                                <?= e($note['course_name'] ?? 'Not specified') ?>

                                <?php if (!empty($note['course_code'])): ?>
                                    (<?= e($note['course_code']) ?>)
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rating-box">
                            <span class="rating-number">
                                <?= $reviewCount > 0
                                    ? number_format($average, 1)
                                    : '—' ?>
                            </span>

                            <span class="rating-stars">
                                <?php
                                echo $reviewCount > 0
                                    ? str_repeat(
                                        '★',
                                        max(0, min(5, (int)round($average)))
                                    )
                                    : '☆☆☆☆☆';
                                ?>
                            </span>

                            <span class="rating-count">
                                <?= $reviewCount ?>
                                <?= $reviewCount === 1 ? 'review' : 'reviews' ?>
                            </span>
                        </div>

                    </div>

                    <?php if (!empty($note['description'])): ?>
                        <p class="note-description">
                            <?= nl2br(e($note['description'])) ?>
                        </p>
                    <?php endif; ?>

                    <div class="note-meta">
                        <div>
                            <strong>Uploaded by:</strong>
                            <?= e($note['uploader_name'] ?? 'Student') ?>
                        </div>

                        <div>
                            <strong>Uploaded on:</strong>
                            <?= e(date(
                                'd M Y',
                                strtotime($note['created_at'])
                            )) ?>
                        </div>
                    </div>

                    <div class="note-actions">
                        <a
                            class="btn btn-primary"
                            href="<?= e($pdfPath) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            View PDF
                        </a>

                        <a
                            class="btn btn-secondary"
                            href="<?= e($pdfPath) ?>"
                            download
                        >
                            Download PDF
                        </a>
                    </div>

                    <!-- Anyone can read reviews. -->
                    <details class="review-details">
                        <summary>
                            Read reviews (<?= $reviewCount ?>)
                        </summary>

                        <?php if (mysqli_num_rows($reviews) === 0): ?>

                            <p class="form-note">
                                No reviews yet. Be the first to review this note.
                            </p>

                        <?php else: ?>

                            <div class="review-list">

                                <?php while ($review = mysqli_fetch_assoc($reviews)): ?>

                                    <?php
                                    $rating = max(
                                        0,
                                        min(5, (int)$review['rating'])
                                    );
                                    ?>

                                    <div class="review-item">

                                        <div class="review-item-top">
                                            <span class="reviewer-name">
                                                <?= e($review['reviewer_name'] ?? 'Student') ?>
                                            </span>

                                            <span class="review-stars">
                                                <?= str_repeat('★', $rating) ?>
                                                <?= str_repeat('☆', 5 - $rating) ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($review['review_text'])): ?>
                                            <p class="review-text">
                                                <?= nl2br(e($review['review_text'])) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="review-date">
                                            <?= e(date(
                                                'd M Y',
                                                strtotime($review['created_at'])
                                            )) ?>
                                        </div>

                                    </div>

                                <?php endwhile; ?>

                            </div>

                        <?php endif; ?>
                    </details>

                    <!-- Only logged-in students can access the review form. -->
                    <?php if (!$isLoggedIn): ?>

                        <div class="review-details">
                            <p class="form-note">
                                Want to rate or review this note?
                                <a href="../login.php">Log in</a> first.
                            </p>
                        </div>

                    <?php elseif ($uploaderId === $studentId): ?>

                        <p class="form-note">
                            You uploaded this note, so you cannot review it.
                        </p>

                    <?php else: ?>

                        <details class="review-details">
                            <summary>
                                <?= $myReview
                                    ? 'Edit your review'
                                    : 'Write a review' ?>
                            </summary>

                            <form
                                class="review-form"
                                action="submit_review.php"
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['review_csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="note_id"
                                    value="<?= $noteId ?>"
                                >

                                <div class="form-group">
                                    <label for="rating-<?= $noteId ?>">
                                        Your rating
                                    </label>

                                    <select
                                        id="rating-<?= $noteId ?>"
                                        name="rating"
                                        required
                                    >
                                        <option value="">Select rating</option>

                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option
                                                value="<?= $i ?>"
                                                <?= $myReview &&
                                                    (int)$myReview['rating'] === $i
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= $i ?> / 5 stars
                                            </option>
                                        <?php endfor; ?>

                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="review-<?= $noteId ?>">
                                        Comment (optional)
                                    </label>

                                    <textarea
                                        id="review-<?= $noteId ?>"
                                        name="review_text"
                                        maxlength="2000"
                                        placeholder="Share your feedback about this note..."
                                    ><?= e($myReview['review_text'] ?? '') ?></textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    <?= $myReview
                                        ? 'Update Review'
                                        : 'Submit Review' ?>
                                </button>

                                <p class="form-note">
                                    You can update your review later.
                                </p>

                            </form>
                        </details>

                    <?php endif; ?>

                </article>

                <?php
                mysqli_stmt_close($reviewStmt);

                if ($myReviewStmt !== null) {
                    mysqli_stmt_close($myReviewStmt);
                }
                ?>

            <?php endwhile; ?>

        <?php endif; ?>

    </section>

</main>

<?php
$footerPath = __DIR__ . '/../includes/footer.php';

if (file_exists($footerPath)) {
    require_once $footerPath;
}
?>

</body>
</html>