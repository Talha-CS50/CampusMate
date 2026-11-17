<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['admin_dashboard_csrf'])) {
    $_SESSION['admin_dashboard_csrf'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectDashboard(string $anchor = ''): void
{
    header('Location: dashboard.php' . ($anchor !== '' ? '#' . $anchor : ''));
    exit;
}

function validDateOrNull(string $value)
{
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
}

$flash = $_SESSION['admin_dashboard_flash'] ?? null;
unset($_SESSION['admin_dashboard_flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['admin_dashboard_csrf'], $submittedToken)
    ) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_notice') {
        $noticeIdInput = $_POST['notice_id'] ?? '';
        $noticeId = $noticeIdInput === ''
            ? null
            : filter_var($noticeIdInput, FILTER_VALIDATE_INT);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $noticeType = trim($_POST['notice_type'] ?? '');
        $deadlineInput = trim($_POST['deadline'] ?? '');
        $deadline = validDateOrNull($deadlineInput);

        if ($title === '' || strlen($title) > 200) {
            $error = 'Notice title is required and must be 200 characters or fewer.';
        } elseif ($description === '') {
            $error = 'Notice description is required.';
        } elseif (strlen($noticeType) > 50) {
            $error = 'Notice type must be 50 characters or fewer.';
        } elseif ($deadline === false) {
            $error = 'Enter a valid notice deadline.';
        } elseif ($noticeId === false || ($noticeId !== null && $noticeId < 1)) {
            $error = 'Invalid notice selected.';
        } else {
            if ($noticeId) {
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE notices
                     SET title = ?, description = ?, notice_type = ?, deadline = ?
                     WHERE notice_id = ?'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssi',
                    $title,
                    $description,
                    $noticeType,
                    $deadline,
                    $noticeId
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['admin_dashboard_flash'] = 'Notice updated successfully.';
            } else {
                $adminId = (int)$_SESSION['admin_id'];
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO notices
                        (title, description, notice_type, deadline, admin_id)
                     VALUES (?, ?, ?, ?, ?)'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssi',
                    $title,
                    $description,
                    $noticeType,
                    $deadline,
                    $adminId
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['admin_dashboard_flash'] = 'Notice published successfully.';
            }
            redirectDashboard('notices');
        }
    } elseif ($action === 'delete_notice') {
        $noticeId = filter_var($_POST['notice_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$noticeId || $noticeId < 1) {
            $error = 'Invalid notice selected.';
        } else {
            $stmt = mysqli_prepare($conn, 'DELETE FROM notices WHERE notice_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $noticeId);
            mysqli_stmt_execute($stmt);
            $deleted = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
            $_SESSION['admin_dashboard_flash'] = $deleted
                ? 'Notice deleted.'
                : 'Notice was not found.';
            redirectDashboard('notices');
        }
    } elseif ($action === 'save_opportunity') {
        $opportunityIdInput = $_POST['opportunity_id'] ?? '';
        $opportunityId = $opportunityIdInput === ''
            ? null
            : filter_var($opportunityIdInput, FILTER_VALIDATE_INT);
        $title = trim(is_string($_POST['title'] ?? null) ? $_POST['title'] : '');
        $type = $_POST['opportunity_type'] ?? '';
        $type = is_string($type) ? $type : '';
        $organization = trim(
            is_string($_POST['organization'] ?? null) ? $_POST['organization'] : ''
        );
        $description = trim(
            is_string($_POST['description'] ?? null) ? $_POST['description'] : ''
        );
        $applicationLink = trim(
            is_string($_POST['application_link'] ?? null)
                ? $_POST['application_link']
                : ''
        );
        $deadlineInput = trim(
            is_string($_POST['deadline'] ?? null) ? $_POST['deadline'] : ''
        );
        $deadline = validDateOrNull($deadlineInput);
        $allowedOpportunityTypes = ['Internship', 'Job', 'Scholarship'];
        $applicationScheme = strtolower(
            (string)parse_url($applicationLink, PHP_URL_SCHEME)
        );

        if ($title === '' || strlen($title) > 200) {
            $error = 'Opportunity title is required and must be 200 characters or fewer.';
        } elseif (!in_array($type, $allowedOpportunityTypes, true)) {
            $error = 'Select a valid opportunity type.';
        } elseif ($organization === '' || strlen($organization) > 150) {
            $error = 'Organization is required and must be 150 characters or fewer.';
        } elseif (strlen($applicationLink) > 255) {
            $error = 'Application link must be 255 characters or fewer.';
        } elseif (
            $applicationLink !== ''
            && (
                filter_var($applicationLink, FILTER_VALIDATE_URL) === false
                || !in_array($applicationScheme, ['http', 'https'], true)
            )
        ) {
            $error = 'Enter a valid http or https application link.';
        } elseif ($deadline === false) {
            $error = 'Enter a valid opportunity deadline.';
        } elseif (
            $opportunityId === false
            || ($opportunityId !== null && $opportunityId < 1)
        ) {
            $error = 'Invalid opportunity selected.';
        } else {
            $description = $description === '' ? null : $description;
            $applicationLink = $applicationLink === '' ? null : $applicationLink;

            if ($opportunityId) {
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE opportunities
                     SET title = ?, `type` = ?, organization = ?, description = ?,
                         application_link = ?, deadline = ?
                     WHERE opportunity_id = ?'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssi',
                    $title,
                    $type,
                    $organization,
                    $description,
                    $applicationLink,
                    $deadline,
                    $opportunityId
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['admin_dashboard_flash'] =
                    'Opportunity updated successfully.';
            } else {
                $adminId = (int)$_SESSION['admin_id'];
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO opportunities
                        (title, `type`, organization, description, application_link,
                         deadline, admin_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssi',
                    $title,
                    $type,
                    $organization,
                    $description,
                    $applicationLink,
                    $deadline,
                    $adminId
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['admin_dashboard_flash'] =
                    'Opportunity published successfully.';
            }
            redirectDashboard('opportunities');
        }
    } elseif ($action === 'delete_opportunity') {
        $opportunityId = filter_var(
            $_POST['opportunity_id'] ?? '',
            FILTER_VALIDATE_INT
        );
        if (!$opportunityId || $opportunityId < 1) {
            $error = 'Invalid opportunity selected.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'DELETE FROM opportunities WHERE opportunity_id = ?'
            );
            mysqli_stmt_bind_param($stmt, 'i', $opportunityId);
            mysqli_stmt_execute($stmt);
            $deleted = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
            $_SESSION['admin_dashboard_flash'] = $deleted
                ? 'Opportunity deleted.'
                : 'Opportunity was not found.';
            redirectDashboard('opportunities');
        }
    } elseif ($action === 'review_campus_issue') {
        $issueId = filter_var($_POST['issue_id'] ?? '', FILTER_VALIDATE_INT);
        $approvalStatus = $_POST['approval_status'] ?? '';
        $approvalStatus = is_string($approvalStatus) ? $approvalStatus : '';
        $adminFeedback = trim(
            is_string($_POST['admin_feedback'] ?? null)
                ? $_POST['admin_feedback']
                : ''
        );

        if (!$issueId || $issueId < 1) {
            $error = 'Invalid campus issue selected.';
        } elseif (!in_array($approvalStatus, ['Approved', 'Rejected'], true)) {
            $error = 'Select a valid review decision.';
        } elseif (strlen($adminFeedback) > 5000) {
            $error = 'Admin feedback must be 5000 characters or fewer.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT issue_id FROM campus_issues WHERE issue_id = ? LIMIT 1'
            );
            mysqli_stmt_bind_param($stmt, 'i', $issueId);
            mysqli_stmt_execute($stmt);
            $issueExists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$issueExists) {
                $error = 'Campus issue was not found.';
            } else {
                $adminFeedback = $adminFeedback === '' ? null : $adminFeedback;
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE campus_issues
                     SET approval_status = ?, admin_feedback = ?,
                         status = CASE WHEN ? = 'Approved' THEN status ELSE 'Pending' END
                     WHERE issue_id = ?"
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'sssi',
                    $approvalStatus,
                    $adminFeedback,
                    $approvalStatus,
                    $issueId
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['admin_dashboard_flash'] =
                    'Campus issue review updated.';
                redirectDashboard('campus-issues');
            }
        }
    } elseif ($action === 'update_campus_issue_status') {
        $issueId = filter_var($_POST['issue_id'] ?? '', FILTER_VALIDATE_INT);
        $issueStatus = $_POST['status'] ?? '';
        $issueStatus = is_string($issueStatus) ? $issueStatus : '';

        if (!$issueId || $issueId < 1) {
            $error = 'Invalid campus issue selected.';
        } elseif (!in_array($issueStatus, ['Pending', 'In Progress', 'Resolved'], true)) {
            $error = 'Select a valid progress status.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT issue_id
                 FROM campus_issues
                 WHERE issue_id = ? AND approval_status = 'Approved'
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, 'i', $issueId);
            mysqli_stmt_execute($stmt);
            $issueExists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$issueExists) {
                $error = 'Only approved campus issues can have their progress updated.';
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE campus_issues
                     SET status = ?
                     WHERE issue_id = ? AND approval_status = 'Approved'"
                );
                mysqli_stmt_bind_param($stmt, 'si', $issueStatus, $issueId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['admin_dashboard_flash'] =
                    'Campus issue progress updated.';
                redirectDashboard('campus-issues');
            }
        }
    } elseif ($action === 'delete_note') {
        $noteId = filter_var($_POST['note_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$noteId || $noteId < 1) {
            $error = 'Invalid note selected.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT file_path FROM notes WHERE note_id = ? LIMIT 1'
            );
            mysqli_stmt_bind_param($stmt, 'i', $noteId);
            mysqli_stmt_execute($stmt);
            $noteResult = mysqli_stmt_get_result($stmt);
            $note = mysqli_fetch_assoc($noteResult);
            mysqli_stmt_close($stmt);

            if (!$note) {
                $error = 'Note was not found.';
            } else {
                $stmt = mysqli_prepare($conn, 'DELETE FROM notes WHERE note_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $noteId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $relativePath = (string)$note['file_path'];
                $expectedPrefix = 'uploads/notes/';
                if (str_starts_with($relativePath, $expectedPrefix)) {
                    $filename = substr($relativePath, strlen($expectedPrefix));
                    if ($filename !== '' && basename($filename) === $filename) {
                        $uploadDirectory = realpath(
                            __DIR__ . '/../student/uploads/notes'
                        );
                        $storedFile = $uploadDirectory !== false
                            ? realpath($uploadDirectory . DIRECTORY_SEPARATOR . $filename)
                            : false;

                        if (
                            $storedFile !== false
                            && dirname($storedFile) === $uploadDirectory
                            && !unlink($storedFile)
                        ) {
                            error_log('Could not remove deleted note file: ' . $storedFile);
                            $_SESSION['admin_dashboard_flash'] =
                                'Note record deleted, but its PDF could not be removed.';
                        }
                    }
                }

                if (!isset($_SESSION['admin_dashboard_flash'])) {
                    $_SESSION['admin_dashboard_flash'] =
                        'Note deleted. Its reviews and download history were also removed.';
                }
                redirectDashboard('notes');
            }
        }
    } else {
        $error = 'Unknown dashboard action.';
    }
}

$editingNotice = null;
$editNoticeId = filter_input(INPUT_GET, 'edit_notice', FILTER_VALIDATE_INT);
if ($editNoticeId && $editNoticeId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT notice_id, title, description, notice_type, deadline
         FROM notices
         WHERE notice_id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $editNoticeId);
    mysqli_stmt_execute($stmt);
    $editingNotice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

$editingOpportunity = null;
$editOpportunityId = filter_input(
    INPUT_GET,
    'edit_opportunity',
    FILTER_VALIDATE_INT
);
if ($editOpportunityId && $editOpportunityId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT opportunity_id, title, `type`, organization, description,
                application_link, deadline
         FROM opportunities
         WHERE opportunity_id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $editOpportunityId);
    mysqli_stmt_execute($stmt);
    $editingOpportunity = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

$stats = [];
foreach ([
    'Students' => 'students',
    'Study notes' => 'notes',
    'Notices' => 'notices',
    'Opportunities' => 'opportunities',
    'Campus issues' => 'campus_issues',
] as $label => $table) {
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");
    $stats[$label] = (int)mysqli_fetch_assoc($result)['total'];
}

$noticesResult = mysqli_query(
    $conn,
    'SELECT n.notice_id, n.title, n.description, n.notice_type, n.deadline,
            n.created_at, a.name AS admin_name
     FROM notices n
     LEFT JOIN admins a ON n.admin_id = a.admin_id
     ORDER BY n.created_at DESC'
);

$opportunitiesResult = mysqli_query(
    $conn,
    'SELECT o.opportunity_id, o.title, o.`type`, o.organization, o.description,
            o.application_link, o.deadline, o.created_at, a.name AS admin_name
     FROM opportunities o
     LEFT JOIN admins a ON o.admin_id = a.admin_id
     ORDER BY o.created_at DESC'
);

$campusIssuesResult = mysqli_query(
    $conn,
    "SELECT i.issue_id, i.title, i.description, i.category, i.location, i.status,
            i.approval_status, i.admin_feedback, i.created_at, i.updated_at,
            s.name AS student_name, s.student_id
     FROM campus_issues i
     LEFT JOIN students s ON i.student_db_id = s.student_db_id
     ORDER BY CASE i.approval_status
                  WHEN 'Pending' THEN 0
                  WHEN 'Approved' THEN 1
                  ELSE 2
              END,
              i.created_at DESC"
);

$noteSearch = trim(is_string($_GET['note_search'] ?? null) ? $_GET['note_search'] : '');
$noteSearchPattern = '%' . $noteSearch . '%';
$notesStmt = mysqli_prepare(
    $conn,
    'SELECT n.note_id, n.title, n.description, n.file_path, n.created_at,
            c.course_name, c.course_code, s.name AS student_name
     FROM notes n
     LEFT JOIN courses c ON n.course_id = c.course_id
     LEFT JOIN students s ON n.student_db_id = s.student_db_id
     WHERE (? = "" OR n.title LIKE ? OR n.description LIKE ?
            OR c.course_name LIKE ? OR c.course_code LIKE ? OR s.name LIKE ?)
     ORDER BY n.created_at DESC
     LIMIT 100'
);
mysqli_stmt_bind_param(
    $notesStmt,
    'ssssss',
    $noteSearch,
    $noteSearchPattern,
    $noteSearchPattern,
    $noteSearchPattern,
    $noteSearchPattern,
    $noteSearchPattern
);
mysqli_stmt_execute($notesStmt);
$notesResult = mysqli_stmt_get_result($notesStmt);

$adminName = e($_SESSION['admin_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CampusMate</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f6fb;
            color: #243047;
            font-family: Arial, sans-serif;
        }
        a { color: #1d4ed8; }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px max(5%, calc((100% - 1180px) / 2));
            background: #fff;
            border-bottom: 1px solid #e0e7f0;
        }
        .brand { color: #173b70; font-size: 20px; font-weight: 700; text-decoration: none; }
        .topbar nav { display: flex; flex-wrap: wrap; gap: 8px; }
        .topbar nav a { padding: 9px 12px; border-radius: 7px; text-decoration: none; font-size: 14px; }
        .logout { background: #fee2e2; color: #b91c1c; }
        main { width: min(1180px, 92%); margin: 30px auto 55px; }
        .welcome {
            padding: 26px 30px;
            border-radius: 15px;
            background: linear-gradient(120deg, #1d4ed8, #4338ca);
            color: #fff;
        }
        .welcome h1 { margin: 0 0 7px; font-size: 27px; }
        .welcome p { margin: 0; color: #dbeafe; line-height: 1.6; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 14px; margin: 18px 0 28px; }
        .stat-card, .panel {
            border: 1px solid #e0e7f0;
            border-radius: 12px;
            background: #fff;
        }
        .stat-card { padding: 18px; }
        .stat-card span { color: #64748b; font-size: 13px; }
        .stat-card strong { display: block; margin-top: 8px; color: #173b70; font-size: 26px; }
        .panel { padding: 22px; margin-top: 20px; }
        .panel h2 { margin: 0 0 7px; color: #173b70; font-size: 21px; }
        .panel-intro { margin: 0 0 18px; color: #64748b; font-size: 14px; line-height: 1.6; }
        .flash, .error {
            padding: 12px 15px;
            margin: 0 0 18px;
            border-radius: 8px;
            line-height: 1.5;
        }
        .flash { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field { display: grid; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        label { color: #334155; font-size: 13px; font-weight: 650; }
        input, select, textarea {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #fff;
            color: #243047;
            font: inherit;
        }
        textarea { min-height: 95px; resize: vertical; }
        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 12px;
            border: 0;
            border-radius: 7px;
            background: #1d4ed8;
            color: #fff;
            font: inherit;
            font-size: 13px;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }
        .button-muted { background: #e8eef7; color: #173b70; }
        .button-danger { background: #fee2e2; color: #b91c1c; }
        .issue-approve { background: #dcfce7; color: #166534; }
        .issue-reject { background: #fee2e2; color: #b91c1c; }
        .form-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e7edf5; text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f8fafc; color: #475569; white-space: nowrap; }
        td p { margin: 5px 0 0; color: #64748b; line-height: 1.5; }
        .row-actions { display: flex; flex-wrap: wrap; gap: 7px; }
        .row-actions form { margin: 0; }
        .search-form { display: flex; gap: 8px; margin-bottom: 14px; }
        .search-form input { max-width: 430px; }
        .note-title { color: #173b70; font-weight: 700; }
        .muted { color: #64748b; }
        @media (max-width: 650px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            main { width: 94%; margin-top: 20px; }
            .welcome { padding: 22px; }
            .panel { padding: 17px; }
            .form-grid { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .search-form { flex-direction: column; }
            .search-form input { max-width: none; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="../index.php">CampusMate Admin</a>
        <nav aria-label="Admin navigation">
            <a href="#overview">Overview</a>
            <a href="#notices">Notices</a>
            <a href="#opportunities">Opportunities</a>
            <a href="#campus-issues">Campus issues</a>
            <a href="#notes">Notes</a>
            <a href="../index.php">View website</a>
            <a class="logout" href="logout.php">Logout</a>
        </nav>
    </header>

    <main>
        <section class="welcome" id="overview">
            <h1>Welcome, <?= $adminName ?>!</h1>
            <p>Manage notices and opportunities, moderate campus issue reports, review study notes, and monitor platform activity.</p>
        </section>

        <?php if ($flash): ?>
            <div class="flash" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="stats" aria-label="Platform totals">
            <?php foreach ($stats as $label => $count): ?>
                <article class="stat-card">
                    <span><?= e($label) ?></span>
                    <strong><?= $count ?></strong>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="panel" id="notices">
            <h2><?= $editingNotice ? 'Edit notice' : 'Publish a notice' ?></h2>
            <p class="panel-intro">Create or update announcements that students can see.</p>

            <?php
            $formNotice = $editingNotice ?? [
                'notice_id' => '',
                'title' => '',
                'description' => '',
                'notice_type' => '',
                'deadline' => '',
            ];
            if ($error !== '' && ($_POST['action'] ?? '') === 'save_notice') {
                $formNotice = [
                    'notice_id' => $_POST['notice_id'] ?? '',
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'notice_type' => $_POST['notice_type'] ?? '',
                    'deadline' => $_POST['deadline'] ?? '',
                ];
            }
            ?>
            <form method="POST" action="dashboard.php#notices">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                <input type="hidden" name="action" value="save_notice">
                <input type="hidden" name="notice_id" value="<?= e($formNotice['notice_id']) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="notice-title">Title *</label>
                        <input id="notice-title" name="title" maxlength="200" required value="<?= e($formNotice['title']) ?>">
                    </div>
                    <div class="field">
                        <label for="notice-type">Type</label>
                        <input id="notice-type" name="notice_type" maxlength="50" placeholder="Academic, General..." value="<?= e($formNotice['notice_type']) ?>">
                    </div>
                    <div class="field">
                        <label for="notice-deadline">Deadline (optional)</label>
                        <input id="notice-deadline" type="date" name="deadline" value="<?= e($formNotice['deadline']) ?>">
                    </div>
                    <div class="field full">
                        <label for="notice-description">Description *</label>
                        <textarea id="notice-description" name="description" required><?= e($formNotice['description']) ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit"><?= !empty($formNotice['notice_id']) ? 'Save changes' : 'Publish notice' ?></button>
                    <?php if ($editingNotice): ?>
                        <a class="button button-muted" href="dashboard.php#notices">Cancel edit</a>
                    <?php endif; ?>
                </div>
            </form>

            <h2 style="margin-top:28px">Published notices</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Notice</th><th>Type</th><th>Deadline</th><th>Published</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($noticesResult) === 0): ?>
                        <tr><td colspan="5" class="muted">No notices published yet.</td></tr>
                    <?php else: ?>
                        <?php while ($notice = mysqli_fetch_assoc($noticesResult)): ?>
                            <tr>
                                <td>
                                    <span class="note-title"><?= e($notice['title']) ?></span>
                                    <p><?= nl2br(e($notice['description'])) ?></p>
                                </td>
                                <td><?= e($notice['notice_type'] ?: 'General') ?></td>
                                <td><?= e($notice['deadline'] ?: '—') ?></td>
                                <td><?= e(date('d M Y', strtotime($notice['created_at']))) ?><p>By <?= e($notice['admin_name'] ?: 'Admin') ?></p></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="button button-muted" href="dashboard.php?edit_notice=<?= (int)$notice['notice_id'] ?>#notices">Edit</a>
                                        <form method="POST" action="dashboard.php#notices" onsubmit="return confirm('Delete this notice?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                                            <input type="hidden" name="action" value="delete_notice">
                                            <input type="hidden" name="notice_id" value="<?= (int)$notice['notice_id'] ?>">
                                            <button class="button-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="opportunities">
            <h2><?= $editingOpportunity ? 'Edit opportunity' : 'Share an opportunity' ?></h2>
            <p class="panel-intro">Publish internships, jobs, or scholarships for students.</p>

            <?php
            $formOpportunity = $editingOpportunity ?? [
                'opportunity_id' => '',
                'title' => '',
                'type' => '',
                'organization' => '',
                'description' => '',
                'application_link' => '',
                'deadline' => '',
            ];
            if ($error !== '' && ($_POST['action'] ?? '') === 'save_opportunity') {
                $postedOpportunity = [];
                foreach ([
                    'opportunity_id',
                    'title',
                    'opportunity_type',
                    'organization',
                    'description',
                    'application_link',
                    'deadline',
                ] as $field) {
                    $postedOpportunity[$field] = is_string($_POST[$field] ?? null)
                        ? $_POST[$field]
                        : '';
                }
                $formOpportunity = [
                    'opportunity_id' => $postedOpportunity['opportunity_id'],
                    'title' => $postedOpportunity['title'],
                    'type' => $postedOpportunity['opportunity_type'],
                    'organization' => $postedOpportunity['organization'],
                    'description' => $postedOpportunity['description'],
                    'application_link' => $postedOpportunity['application_link'],
                    'deadline' => $postedOpportunity['deadline'],
                ];
            }
            ?>
            <form method="POST" action="dashboard.php#opportunities">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                <input type="hidden" name="action" value="save_opportunity">
                <input type="hidden" name="opportunity_id" value="<?= e($formOpportunity['opportunity_id']) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="opportunity-title">Title *</label>
                        <input
                            id="opportunity-title"
                            name="title"
                            maxlength="200"
                            required
                            value="<?= e($formOpportunity['title']) ?>"
                        >
                    </div>
                    <div class="field">
                        <label for="opportunity-type">Type *</label>
                        <select id="opportunity-type" name="opportunity_type" required>
                            <option value="">Select a type</option>
                            <?php foreach (['Internship', 'Job', 'Scholarship'] as $type): ?>
                                <option
                                    value="<?= e($type) ?>"
                                    <?= $formOpportunity['type'] === $type ? 'selected' : '' ?>
                                >
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="opportunity-organization">Organization *</label>
                        <input
                            id="opportunity-organization"
                            name="organization"
                            maxlength="150"
                            required
                            value="<?= e($formOpportunity['organization']) ?>"
                        >
                    </div>
                    <div class="field">
                        <label for="opportunity-deadline">Deadline (optional)</label>
                        <input
                            id="opportunity-deadline"
                            type="date"
                            name="deadline"
                            value="<?= e($formOpportunity['deadline']) ?>"
                        >
                    </div>
                    <div class="field full">
                        <label for="opportunity-application-link">Application link (optional)</label>
                        <input
                            id="opportunity-application-link"
                            type="url"
                            name="application_link"
                            maxlength="255"
                            placeholder="https://example.com/apply"
                            value="<?= e($formOpportunity['application_link']) ?>"
                        >
                    </div>
                    <div class="field full">
                        <label for="opportunity-description">Description (optional)</label>
                        <textarea
                            id="opportunity-description"
                            name="description"
                        ><?= e($formOpportunity['description']) ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit">
                        <?= !empty($formOpportunity['opportunity_id'])
                            ? 'Save changes'
                            : 'Publish opportunity' ?>
                    </button>
                    <?php if ($editingOpportunity): ?>
                        <a class="button button-muted" href="dashboard.php#opportunities">Cancel edit</a>
                    <?php endif; ?>
                </div>
            </form>

            <h2 style="margin-top:28px">Published opportunities</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Opportunity</th>
                            <th>Type</th>
                            <th>Deadline</th>
                            <th>Published</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($opportunitiesResult) === 0): ?>
                        <tr><td colspan="5" class="muted">No opportunities published yet.</td></tr>
                    <?php else: ?>
                        <?php while ($opportunity = mysqli_fetch_assoc($opportunitiesResult)): ?>
                            <tr>
                                <td>
                                    <span class="note-title"><?= e($opportunity['title']) ?></span>
                                    <p><?= e($opportunity['organization']) ?></p>
                                    <?php if (!empty($opportunity['description'])): ?>
                                        <p><?= nl2br(e($opportunity['description'])) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($opportunity['type']) ?></td>
                                <td><?= e($opportunity['deadline'] ?: '—') ?></td>
                                <td>
                                    <?= e(date('d M Y', strtotime($opportunity['created_at']))) ?>
                                    <p>By <?= e($opportunity['admin_name'] ?: 'Admin') ?></p>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a
                                            class="button button-muted"
                                            href="dashboard.php?edit_opportunity=<?= (int)$opportunity['opportunity_id'] ?>#opportunities"
                                        >Edit</a>
                                        <form
                                            method="POST"
                                            action="dashboard.php#opportunities"
                                            onsubmit="return confirm('Delete this opportunity?');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                                            <input type="hidden" name="action" value="delete_opportunity">
                                            <input type="hidden" name="opportunity_id" value="<?= (int)$opportunity['opportunity_id'] ?>">
                                            <button class="button-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="campus-issues">
            <h2>Campus issue reports</h2>
            <p class="panel-intro">
                New reports stay private until approved. Approved reports appear to signed-in users.
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Submitted by</th>
                            <th>Submitted</th>
                            <th>Review decision</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($campusIssuesResult) === 0): ?>
                        <tr><td colspan="5" class="muted">No campus issue reports yet.</td></tr>
                    <?php else: ?>
                        <?php while ($issue = mysqli_fetch_assoc($campusIssuesResult)): ?>
                            <tr>
                                <td>
                                    <span class="note-title"><?= e($issue['title']) ?></span>
                                    <p>
                                        <?= e($issue['category'] ?: 'Uncategorized') ?>
                                        <?php if (!empty($issue['location'])): ?>
                                            · <?= e($issue['location']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <p><?= nl2br(e($issue['description'])) ?></p>
                                </td>
                                <td>
                                    <?= e($issue['student_name'] ?: 'Student') ?>
                                    <p><?= e($issue['student_id'] ?: '') ?></p>
                                </td>
                                <td><?= e(date('d M Y', strtotime($issue['created_at']))) ?></td>
                                <td>
                                    <form method="POST" action="dashboard.php#campus-issues">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                                        <input type="hidden" name="action" value="review_campus_issue">
                                        <input type="hidden" name="issue_id" value="<?= (int)$issue['issue_id'] ?>">
                                        <label for="issue-feedback-<?= (int)$issue['issue_id'] ?>">Feedback for student (optional)</label>
                                        <textarea
                                            id="issue-feedback-<?= (int)$issue['issue_id'] ?>"
                                            name="admin_feedback"
                                            maxlength="5000"
                                            placeholder="Reason or update for the student"
                                        ><?= e($issue['admin_feedback']) ?></textarea>
                                        <div class="row-actions">
                                            <button
                                                class="issue-approve"
                                                type="submit"
                                                name="approval_status"
                                                value="Approved"
                                            >Approve</button>
                                            <button
                                                class="issue-reject"
                                                type="submit"
                                                name="approval_status"
                                                value="Rejected"
                                            >Reject</button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($issue['approval_status'] === 'Approved'): ?>
                                        <form method="POST" action="dashboard.php#campus-issues">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                                            <input type="hidden" name="action" value="update_campus_issue_status">
                                            <input type="hidden" name="issue_id" value="<?= (int)$issue['issue_id'] ?>">
                                            <label for="issue-status-<?= (int)$issue['issue_id'] ?>">Progress</label>
                                            <select
                                                id="issue-status-<?= (int)$issue['issue_id'] ?>"
                                                name="status"
                                            >
                                                <?php foreach (['Pending', 'In Progress', 'Resolved'] as $status): ?>
                                                    <option
                                                        value="<?= e($status) ?>"
                                                        <?= $issue['status'] === $status ? 'selected' : '' ?>
                                                    >
                                                        <?= e($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit">Update status</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Available after approval</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="notes">
            <h2>Study notes</h2>
            <p class="panel-intro">Search student uploads, open PDFs, or remove content. Deleting a note also deletes its reviews and download history.</p>

            <form class="search-form" method="GET" action="dashboard.php#notes">
                <input type="search" name="note_search" value="<?= e($noteSearch) ?>" placeholder="Search title, course, uploader...">
                <button type="submit">Search</button>
                <?php if ($noteSearch !== ''): ?>
                    <a class="button button-muted" href="dashboard.php#notes">Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Note</th><th>Course</th><th>Uploaded by</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($notesResult) === 0): ?>
                        <tr><td colspan="5" class="muted">No matching notes found.</td></tr>
                    <?php else: ?>
                        <?php while ($note = mysqli_fetch_assoc($notesResult)): ?>
                            <tr>
                                <td>
                                    <span class="note-title"><?= e($note['title']) ?></span>
                                    <?php if (!empty($note['description'])): ?>
                                        <p><?= nl2br(e($note['description'])) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($note['course_code'] ?: 'N/A') ?><p><?= e($note['course_name'] ?: '') ?></p></td>
                                <td><?= e($note['student_name'] ?: 'Student') ?></td>
                                <td><?= e(date('d M Y', strtotime($note['created_at']))) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="button button-muted" href="../student/view_note.php?id=<?= (int)$note['note_id'] ?>" target="_blank" rel="noopener">View PDF</a>
                                        <form method="POST" action="dashboard.php#notes" onsubmit="return confirm('Delete this note and its review/download history?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_dashboard_csrf']) ?>">
                                            <input type="hidden" name="action" value="delete_note">
                                            <input type="hidden" name="note_id" value="<?= (int)$note['note_id'] ?>">
                                            <button class="button-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="panel-intro" style="margin:12px 0 0">Showing up to 100 most recent matching notes.</p>
        </section>
    </main>
</body>
</html>
<?php
mysqli_stmt_close($notesStmt);
?>
