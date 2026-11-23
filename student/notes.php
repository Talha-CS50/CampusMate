<?php
require_once __DIR__ . '/../includes/student_auth.php';
campusMateRequireAccess('notes');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../config/db.php';

$studentId = (int)($_SESSION['student_db_id'] ?? 0);
$isStudentLoggedIn = $studentId > 0;
$isAlumniLoggedIn = (int)($_SESSION['alumni_db_id'] ?? 0) > 0;
$isAdminLoggedIn = (int)($_SESSION['admin_id'] ?? 0) > 0;

if ($isStudentLoggedIn && empty($_SESSION['review_csrf_token'])) {
    $_SESSION['review_csrf_token'] = bin2hex(random_bytes(32));
}
if ($isStudentLoggedIn && empty($_SESSION['note_request_csrf_token'])) {
    $_SESSION['note_request_csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$requestError = '';
$requestSuccess = '';
$requestFormValues = [
    'course_id' => '',
    'requested_title' => '',
    'details' => '',
];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'create_note_request'
) {
    if (!$isStudentLoggedIn) {
        http_response_code(403);
        exit('Only student accounts can request notes.');
    }

    $sessionToken = $_SESSION['note_request_csrf_token'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($sessionToken)
        || !is_string($submittedToken)
        || $sessionToken === ''
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }

    $courseIdInput = $_POST['course_id'] ?? '';
    $requestFormValues['course_id'] = is_string($courseIdInput)
        ? $courseIdInput
        : '';
    $requestFormValues['requested_title'] = trim(
        is_string($_POST['requested_title'] ?? null)
            ? $_POST['requested_title']
            : ''
    );
    $requestFormValues['details'] = trim(
        is_string($_POST['details'] ?? null) ? $_POST['details'] : ''
    );
    $courseId = filter_var(
        $requestFormValues['course_id'],
        FILTER_VALIDATE_INT
    );

    if (!$courseId || $courseId < 1) {
        $requestError = 'Choose a valid course.';
    } elseif (
        $requestFormValues['requested_title'] === ''
        || strlen($requestFormValues['requested_title']) > 200
    ) {
        $requestError = 'Note title is required and must be 200 characters or fewer.';
    } elseif (strlen($requestFormValues['details']) > 2000) {
        $requestError = 'Details must be 2000 characters or fewer.';
    } else {
        $courseStmt = mysqli_prepare(
            $conn,
            'SELECT course_id FROM courses WHERE course_id = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($courseStmt, 'i', $courseId);
        mysqli_stmt_execute($courseStmt);
        $courseExists = mysqli_fetch_assoc(mysqli_stmt_get_result($courseStmt));
        mysqli_stmt_close($courseStmt);

        if (!$courseExists) {
            $requestError = 'The selected course was not found.';
        } else {
            $duplicateStmt = mysqli_prepare(
                $conn,
                "SELECT request_id
                 FROM note_requests
                 WHERE student_db_id = ?
                   AND course_id = ?
                   AND status = 'Open'
                   AND LOWER(TRIM(requested_title)) = LOWER(TRIM(?))
                 LIMIT 1"
            );
            mysqli_stmt_bind_param(
                $duplicateStmt,
                'iis',
                $studentId,
                $courseId,
                $requestFormValues['requested_title']
            );
            mysqli_stmt_execute($duplicateStmt);
            $duplicateRequest = mysqli_fetch_assoc(
                mysqli_stmt_get_result($duplicateStmt)
            );
            mysqli_stmt_close($duplicateStmt);

            if ($duplicateRequest) {
                $requestError = 'You already have an open request for this course and note title.';
            } else {
                $details = $requestFormValues['details'] === ''
                    ? null
                    : $requestFormValues['details'];
                $insertStmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO note_requests
                        (student_db_id, course_id, requested_title, details)
                     VALUES (?, ?, ?, ?)'
                );
                mysqli_stmt_bind_param(
                    $insertStmt,
                    'iiss',
                    $studentId,
                    $courseId,
                    $requestFormValues['requested_title'],
                    $details
                );
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);

                $requestSuccess = 'Your note request was posted.';
                $requestFormValues = [
                    'course_id' => '',
                    'requested_title' => '',
                    'details' => '',
                ];
            }
        }
    }
}

$requestCoursesResult = $isStudentLoggedIn
    ? mysqli_query(
        $conn,
        'SELECT course_id, course_code, course_name
         FROM courses
         ORDER BY course_code ASC'
    )
    : null;

$noteRequestsResult = mysqli_query(
    $conn,
    "SELECT r.request_id, r.requested_title, r.details, r.status, r.created_at,
            c.course_code, c.course_name, s.name AS requester_name
     FROM note_requests r
     INNER JOIN courses c ON r.course_id = c.course_id
     INNER JOIN students s ON r.student_db_id = s.student_db_id
     ORDER BY CASE r.status WHEN 'Open' THEN 0 ELSE 1 END,
              r.created_at DESC
     LIMIT 100"
);
$noteRequestCount = mysqli_num_rows($noteRequestsResult);

// Search notes by their content or related course/uploader details.
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$searchPattern = '%' . $search . '%';

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
        LEFT JOIN departments d
            ON c.department_id = d.department_id
        LEFT JOIN students s
            ON n.student_db_id = s.student_db_id
        LEFT JOIN note_reviews nr
            ON n.note_id = nr.note_id
        WHERE (? = '' OR n.title LIKE ? OR n.description LIKE ?
            OR c.course_name LIKE ? OR c.course_code LIKE ?
            OR d.department_name LIKE ? OR c.semester LIKE ?
            OR s.name LIKE ?)
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

$notesStmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $notesStmt,
    'ssssssss',
    $search,
    $searchPattern,
    $searchPattern,
    $searchPattern,
    $searchPattern,
    $searchPattern,
    $searchPattern,
    $searchPattern
);
mysqli_stmt_execute($notesStmt);
$result = mysqli_stmt_get_result($notesStmt);
$noteCount = mysqli_num_rows($result);
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

        /* Heading and Upload button */
        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            flex-shrink: 0;
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

        .notes-search {
            display: flex;
            gap: 10px;
            margin: 22px 0;
        }

        .notes-search input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
        }

        .notes-search input:focus {
            border-color: #1d4ed8;
            outline: 3px solid rgba(29, 78, 216, 0.15);
        }

        .notes-search button,
        .notes-search-clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #1d4ed8;
            color: #fff;
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .notes-search-clear {
            background: #e8eef7;
            color: #173b70;
        }

        .notes-result-count {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 13px;
        }

        .note-requests-section {
            margin: 28px 0;
            padding: 22px;
            border: 1px solid #e0e7f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(20, 45, 80, 0.05);
        }

        .note-requests-heading {
            margin-bottom: 16px;
        }

        .note-requests-heading h2 {
            margin: 0 0 5px;
            color: #173b70;
            font-size: 21px;
        }

        .note-requests-heading p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .note-request-message {
            padding: 11px 13px;
            margin-bottom: 14px;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
        }

        .note-request-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .note-requests-content {
            display: grid;
            grid-template-columns: minmax(240px, 0.8fr) minmax(0, 1.8fr);
            gap: 16px;
            align-items: start;
        }

        .note-requests-content.request-list-only {
            grid-template-columns: 1fr;
        }

        .note-request-form-panel,
        .note-request-list-panel {
            min-width: 0;
            padding: 15px;
            border: 1px solid #e0e7f0;
            border-radius: 10px;
            background: #f8fafc;
        }

        .note-request-form-panel > h3,
        .note-request-list-panel > h3 {
            margin: 0 0 12px;
            color: #173b70;
            font-size: 15px;
        }

        .note-request-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin: 0;
        }

        .note-request-form label {
            display: grid;
            gap: 5px;
            color: #334155;
            font-size: 12px;
            font-weight: 650;
        }

        .note-request-form input,
        .note-request-form select,
        .note-request-form textarea {
            width: 100%;
            padding: 8px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #fff;
            color: #243047;
            font: inherit;
            font-size: 13px;
        }

        .note-request-form textarea {
            resize: vertical;
        }

        .note-request-form button {
            justify-self: start;
            padding: 9px 13px;
            border: 0;
            border-radius: 8px;
            background: #1d4ed8;
            color: #fff;
            font: inherit;
            font-weight: 650;
            cursor: pointer;
        }

        .note-request-list {
            display: grid;
            gap: 8px;
        }

        .note-request-item {
            padding: 11px;
            border: 1px solid #e0e7f0;
            border-radius: 9px;
            background: #f8fafc;
        }

        .note-request-item-top,
        .note-request-item-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .note-request-course {
            color: #4338ca;
            font-size: 11px;
            font-weight: 700;
        }

        .note-request-status {
            padding: 4px 8px;
            border-radius: 20px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 11px;
            font-weight: 700;
        }

        .note-request-status.fulfilled {
            background: #dcfce7;
            color: #166534;
        }

        .note-request-item h3 {
            margin: 8px 0 4px;
            color: #173b70;
            font-size: 16px;
        }

        .note-request-item-details {
            margin: 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .note-request-item-meta {
            margin-top: 9px;
            color: #64748b;
            font-size: 11px;
        }

        .note-request-empty {
            padding: 18px;
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
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

        /* Upload Note button */
        .upload-btn {
            flex-shrink: 0;
            padding: 12px 18px;
            font-size: 14px;
            white-space: nowrap;
        }

        .page-header-actions .upload-btn {
            border: 1px solid #1d4ed8;
            background: #fff;
            color: #1d4ed8;
            text-decoration: none;
        }

        .page-header-actions .upload-btn:hover {
            background: #1d4ed8;
            color: #fff;
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
            .notes-search {
                flex-wrap: wrap;
            }

            .notes-search input {
                flex-basis: 100%;
            }

            .notes-list {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .page-header-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .note-requests-section {
                padding: 16px;
            }

            .note-requests-content {
                grid-template-columns: 1fr;
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
        <div class="page-header-top">

            <div>
                <h1>Study Notes</h1>
                <p>
                    Explore study materials, view PDFs, and read reviews shared
                    by other students.
                </p>
            </div>

            <div class="page-header-actions">
                <a href="upload_note.php" class="btn upload-btn">
                    + Upload Note
                </a>
                <?php if ($isStudentLoggedIn): ?>
                    <a href="#note-requests" class="btn upload-btn">
                        Request a Note
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <form class="notes-search" method="GET" action="notes.php" role="search">
        <label class="visually-hidden" for="notes-search-input">Search study notes</label>
        <input
            id="notes-search-input"
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search title, course, department, semester, or uploader..."
        >
        <button type="submit">Search</button>
        <?php if ($search !== ''): ?>
            <a class="notes-search-clear" href="notes.php">Clear</a>
        <?php endif; ?>
    </form>

    <p class="notes-result-count" aria-live="polite">
        <?= $noteCount ?> <?= $noteCount === 1 ? 'note' : 'notes' ?>
        <?= $search !== '' ? 'found' : 'available' ?>
    </p>

    <section class="notes-list">

        <?php if ($noteCount === 0): ?>

            <div class="empty-message">
                <h3><?= $search !== '' ? 'No matching notes found' : 'No notes available yet' ?></h3>
                <p>
                    <?= $search !== ''
                        ? 'Try another title, course, department, semester, or uploader name.'
                        : 'Uploaded notes will appear here.' ?>
                </p>
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

                if ($isStudentLoggedIn && $uploaderId !== $studentId) {
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
                            href="view_note.php?id=<?= $noteId ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            View PDF
                        </a>

                        <a
                            class="btn btn-secondary"
                            href="view_note.php?id=<?= $noteId ?>&amp;download=1"
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
                    <?php if (!$isStudentLoggedIn): ?>

                        <div class="review-details">
                            <p class="form-note">
                                <?= $isAdminLoggedIn || $isAlumniLoggedIn
                                    ? 'Admin and Alumni accounts can browse notes but cannot submit student reviews.'
                                    : 'Want to rate or review this note? <a href="../login.php">Log in</a> first.' ?>
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

    <section class="note-requests-section" id="note-requests">
        <div class="note-requests-heading">
            <h2>Note Requests</h2>
            <p>
                Everyone visiting Study Notes can see requests. Uploading a note
                with the same course and title automatically marks a request fulfilled.
            </p>
        </div>

        <?php if ($requestSuccess !== ''): ?>
            <div class="note-request-message" role="status">
                <?= e($requestSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if ($requestError !== ''): ?>
            <div class="note-request-message note-request-error" role="alert">
                <?= e($requestError) ?>
            </div>
        <?php endif; ?>

        <div class="note-requests-content <?= $isStudentLoggedIn ? '' : 'request-list-only' ?>">
            <?php if ($isStudentLoggedIn): ?>
                <div class="note-request-form-panel">
                    <h3>Request a Note</h3>
                    <form method="POST" action="notes.php#note-requests" class="note-request-form">
                        <input type="hidden" name="action" value="create_note_request">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['note_request_csrf_token']) ?>"
                        >
                        <label>
                            Course *
                            <select name="course_id" required>
                                <option value="">Select a course</option>
                                <?php while ($course = mysqli_fetch_assoc($requestCoursesResult)): ?>
                                    <option
                                        value="<?= (int)$course['course_id'] ?>"
                                        <?= $requestFormValues['course_id'] === (string)$course['course_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($course['course_code'] . ' - ' . $course['course_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </label>
                        <label>
                            Note title / topic *
                            <input
                                name="requested_title"
                                maxlength="200"
                                required
                                placeholder="Use the same title when uploading"
                                value="<?= e($requestFormValues['requested_title']) ?>"
                            >
                        </label>
                        <label>
                            Details (optional)
                            <textarea
                                name="details"
                                maxlength="2000"
                                rows="3"
                                placeholder="Chapter, topic, or other helpful details"
                            ><?= e($requestFormValues['details']) ?></textarea>
                        </label>
                        <button type="submit">Post Request</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="note-request-list-panel">
                <h3>Requests</h3>
                <div class="note-request-list">
                    <?php if ($noteRequestCount === 0): ?>
                        <div class="note-request-empty">No note requests yet.</div>
                    <?php else: ?>
                        <?php while ($request = mysqli_fetch_assoc($noteRequestsResult)): ?>
                            <article class="note-request-item">
                                <div class="note-request-item-top">
                                    <span class="note-request-course">
                                        <?= e($request['course_code'] . ' - ' . $request['course_name']) ?>
                                    </span>
                                    <span class="note-request-status <?= strtolower($request['status']) ?>">
                                        <?= e($request['status']) ?>
                                    </span>
                                </div>
                                <h3><?= e($request['requested_title']) ?></h3>
                                <?php if (!empty($request['details'])): ?>
                                    <p class="note-request-item-details">
                                        <?= nl2br(e($request['details'])) ?>
                                    </p>
                                <?php endif; ?>
                                <div class="note-request-item-meta">
                                    <span>Requested by <?= e($request['requester_name']) ?></span>
                                    <span><?= e(date('M d, Y', strtotime($request['created_at']))) ?></span>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
