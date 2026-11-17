<?php
require_once __DIR__ . '/../includes/student_auth.php';
campusMateRequireAccess('campus-issues');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

function campusIssueEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$studentId = (int)($_SESSION['student_db_id'] ?? 0);
$isStudent = $studentId > 0;
$error = '';
$formValues = [
    'title' => '',
    'description' => '',
    'category' => '',
    'location' => '',
];

if ($isStudent && empty($_SESSION['campus_issue_csrf_token'])) {
    $_SESSION['campus_issue_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isStudent) {
        http_response_code(403);
        exit('Only student accounts can submit a campus issue.');
    }

    $sessionToken = $_SESSION['campus_issue_csrf_token'] ?? '';
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

    foreach (array_keys($formValues) as $field) {
        $formValues[$field] = trim(
            is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
        );
    }

    if ($formValues['title'] === '' || strlen($formValues['title']) > 200) {
        $error = 'Issue title is required and must be 200 characters or fewer.';
    } elseif ($formValues['description'] === '') {
        $error = 'Issue description is required.';
    } elseif (strlen($formValues['category']) > 50) {
        $error = 'Category must be 50 characters or fewer.';
    } elseif (strlen($formValues['location']) > 150) {
        $error = 'Location must be 150 characters or fewer.';
    } else {
        $category = $formValues['category'] === ''
            ? null
            : $formValues['category'];
        $location = $formValues['location'] === ''
            ? null
            : $formValues['location'];

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO campus_issues
                (student_db_id, title, description, category, location,
                 status, approval_status)
             VALUES (?, ?, ?, ?, ?, 'Pending', 'Pending')"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'issss',
            $studentId,
            $formValues['title'],
            $formValues['description'],
            $category,
            $location
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['campus_issue_flash'] =
            'Your report was submitted and is waiting for admin review.';
        header('Location: campus_issues.php');
        exit;
    }
}

$flash = $_SESSION['campus_issue_flash'] ?? null;
unset($_SESSION['campus_issue_flash']);

if ($isStudent) {
    $issuesStmt = mysqli_prepare(
        $conn,
        "SELECT issue_id, student_db_id, title, description, category, location,
                status, approval_status, admin_feedback, created_at, updated_at
         FROM campus_issues
         WHERE approval_status = 'Approved' OR student_db_id = ?
         ORDER BY created_at DESC"
    );
    mysqli_stmt_bind_param($issuesStmt, 'i', $studentId);
} else {
    $issuesStmt = mysqli_prepare(
        $conn,
        "SELECT issue_id, student_db_id, title, description, category, location,
                status, approval_status, admin_feedback, created_at, updated_at
         FROM campus_issues
         WHERE approval_status = 'Approved'
         ORDER BY created_at DESC"
    );
}
mysqli_stmt_execute($issuesStmt);
$issuesResult = mysqli_stmt_get_result($issuesStmt);
$issueCount = mysqli_num_rows($issuesResult);

include __DIR__ . '/../includes/header.php';
?>

<main class="campus-issues-page">
    <header class="campus-issues-heading">
        <span class="campus-issues-eyebrow">MAKE CAMPUS BETTER</span>
        <h1>Campus <span>Issues</span></h1>
        <p>Report a campus problem and follow its review and resolution progress.</p>
    </header>

    <?php if ($flash): ?>
        <div class="issue-message issue-success" role="status">
            <?= campusIssueEscape($flash) ?>
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="issue-message issue-error" role="alert">
            <?= campusIssueEscape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($isStudent): ?>
        <section class="issue-submit-card">
            <h2>Submit a campus issue</h2>
            <p>Your report will be reviewed by an admin before it appears publicly.</p>

            <form method="POST" action="campus_issues.php" class="issue-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= campusIssueEscape($_SESSION['campus_issue_csrf_token']) ?>"
                >

                <div class="issue-form-grid">
                    <div class="issue-field">
                        <label for="issue-title">Title *</label>
                        <input
                            id="issue-title"
                            name="title"
                            maxlength="200"
                            required
                            value="<?= campusIssueEscape($formValues['title']) ?>"
                        >
                    </div>
                    <div class="issue-field">
                        <label for="issue-category">Category (optional)</label>
                        <input
                            id="issue-category"
                            name="category"
                            maxlength="50"
                            placeholder="Facilities, internet, safety..."
                            value="<?= campusIssueEscape($formValues['category']) ?>"
                        >
                    </div>
                    <div class="issue-field">
                        <label for="issue-location">Location (optional)</label>
                        <input
                            id="issue-location"
                            name="location"
                            maxlength="150"
                            placeholder="Building, floor, or area"
                            value="<?= campusIssueEscape($formValues['location']) ?>"
                        >
                    </div>
                    <div class="issue-field issue-field-full">
                        <label for="issue-description">Description *</label>
                        <textarea
                            id="issue-description"
                            name="description"
                            required
                            placeholder="Describe the issue and any useful details..."
                        ><?= campusIssueEscape($formValues['description']) ?></textarea>
                    </div>
                </div>

                <button type="submit" class="issue-submit-button">Submit for review</button>
            </form>
        </section>
    <?php elseif ((int)($_SESSION['admin_id'] ?? 0) > 0): ?>
        <p class="issue-admin-link">
            Review new reports from the <a href="/CampusMate/admin/dashboard.php#campus-issues">Admin Dashboard</a>.
        </p>
    <?php endif; ?>

    <section class="campus-issues-list-section">
        <div class="campus-issues-list-heading">
            <div>
                <h2><?= $isStudent ? 'Approved issues and your reports' : 'Approved campus issues' ?></h2>
                <p><?= $issueCount ?> <?= $issueCount === 1 ? 'report' : 'reports' ?></p>
            </div>
        </div>

        <div class="campus-issues-list">
            <?php if ($issueCount === 0): ?>
                <div class="campus-issues-empty">
                    <span aria-hidden="true">🛠️</span>
                    <h3>No campus issues to show</h3>
                    <p>Approved issues and your submitted reports will appear here.</p>
                </div>
            <?php else: ?>
                <?php while ($issue = mysqli_fetch_assoc($issuesResult)): ?>
                    <?php
                    $isOwnIssue = $isStudent
                        && (int)$issue['student_db_id'] === $studentId;
                    $approvalClass = strtolower($issue['approval_status']);
                    $statusClass = strtolower(str_replace(' ', '-', $issue['status']));
                    ?>
                    <article class="campus-issue-card">
                        <div class="campus-issue-card-top">
                            <span class="campus-issue-category">
                                <?= campusIssueEscape($issue['category'] ?: 'Campus issue') ?>
                            </span>
                            <?php if ($isOwnIssue): ?>
                                <span class="campus-issue-owner">Your report</span>
                            <?php endif; ?>
                            <span class="campus-issue-date">
                                <?= campusIssueEscape(date('M d, Y', strtotime($issue['created_at']))) ?>
                            </span>
                        </div>

                        <h3><?= campusIssueEscape($issue['title']) ?></h3>
                        <?php if (!empty($issue['location'])): ?>
                            <p class="campus-issue-location">
                                Location: <?= campusIssueEscape($issue['location']) ?>
                            </p>
                        <?php endif; ?>
                        <div class="campus-issue-description">
                            <?= nl2br(campusIssueEscape($issue['description'])) ?>
                        </div>

                        <div class="campus-issue-statuses">
                            <span class="issue-status <?= campusIssueEscape($approvalClass) ?>">
                                Review: <?= campusIssueEscape($issue['approval_status']) ?>
                            </span>
                            <?php if ($issue['approval_status'] === 'Approved'): ?>
                                <span class="issue-status <?= campusIssueEscape($statusClass) ?>">
                                    Progress: <?= campusIssueEscape($issue['status']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($isOwnIssue && !empty($issue['admin_feedback'])): ?>
                            <p class="campus-issue-feedback">
                                <strong>Admin feedback:</strong>
                                <?= nl2br(campusIssueEscape($issue['admin_feedback'])) ?>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<style>
    .campus-issues-page {
        width: min(950px, 92%);
        min-height: 55vh;
        margin: 45px auto 60px;
    }

    .campus-issues-heading {
        margin-bottom: 25px;
        text-align: center;
    }

    .campus-issues-eyebrow {
        color: #6366f1;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.4px;
    }

    .campus-issues-heading h1 {
        margin: 9px 0;
        color: #172033;
        font-size: clamp(30px, 5vw, 42px);
    }

    .campus-issues-heading h1 span {
        color: #6366f1;
    }

    .campus-issues-heading p,
    .issue-submit-card > p,
    .campus-issues-list-heading p {
        margin: 0;
        color: #64748b;
        line-height: 1.7;
    }

    .issue-message {
        padding: 12px 15px;
        margin: 0 0 18px;
        border-radius: 9px;
        line-height: 1.5;
    }

    .issue-success {
        background: #dcfce7;
        color: #166534;
    }

    .issue-error {
        background: #fee2e2;
        color: #991b1b;
    }

    .issue-submit-card,
    .campus-issue-card,
    .campus-issues-empty {
        padding: 23px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(31, 41, 55, .05);
    }

    .issue-submit-card {
        margin-bottom: 30px;
    }

    .issue-submit-card h2,
    .campus-issues-list-heading h2 {
        margin: 0 0 6px;
        color: #172554;
        font-size: 21px;
    }

    .issue-form {
        margin-top: 20px;
    }

    .issue-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .issue-field {
        display: grid;
        gap: 6px;
    }

    .issue-field-full {
        grid-column: 1 / -1;
    }

    .issue-field label {
        color: #334155;
        font-size: 13px;
        font-weight: 650;
    }

    .issue-field input,
    .issue-field textarea {
        width: 100%;
        padding: 10px 11px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #fff;
        color: #243047;
        font: inherit;
    }

    .issue-field textarea {
        min-height: 110px;
        resize: vertical;
    }

    .issue-submit-button,
    .opportunity-admin-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 15px;
        border: 0;
        border-radius: 8px;
        background: #4f46e5;
        color: #fff;
        font: inherit;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .issue-submit-button {
        margin-top: 15px;
    }

    .issue-admin-link {
        margin: 0 0 25px;
        color: #475569;
    }

    .issue-admin-link a {
        color: #4f46e5;
        font-weight: 700;
    }

    .campus-issues-list-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 13px;
    }

    .campus-issues-list-heading p {
        font-size: 13px;
    }

    .campus-issues-list {
        display: grid;
        gap: 13px;
    }

    .campus-issue-card-top,
    .campus-issue-statuses {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .campus-issue-card-top {
        justify-content: space-between;
    }

    .campus-issue-category,
    .campus-issue-owner,
    .issue-status {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 30px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 11px;
        font-weight: 700;
    }

    .campus-issue-owner {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .campus-issue-date {
        color: #64748b;
        font-size: 12px;
    }

    .campus-issue-card h3 {
        margin: 13px 0 3px;
        color: #172554;
        font-size: 19px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .campus-issue-location {
        margin: 0 0 8px;
        color: #64748b;
        font-size: 13px;
    }

    .campus-issue-description {
        color: #475569;
        font-size: 14px;
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .campus-issue-statuses {
        margin-top: 15px;
    }

    .issue-status.approved,
    .issue-status.resolved {
        background: #dcfce7;
        color: #166534;
    }

    .issue-status.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .issue-status.in-progress {
        background: #fff7ed;
        color: #9a3412;
    }

    .campus-issue-feedback {
        padding-top: 13px;
        margin: 14px 0 0;
        border-top: 1px solid #e5e7eb;
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    .campus-issues-empty {
        padding: 36px 20px;
        border-style: dashed;
        text-align: center;
        box-shadow: none;
    }

    .campus-issues-empty > span {
        font-size: 36px;
    }

    .campus-issues-empty h3 {
        margin: 9px 0 5px;
        color: #172554;
    }

    .campus-issues-empty p {
        margin: 0;
        color: #64748b;
    }

    @media (max-width: 600px) {
        .campus-issues-page {
            margin: 28px auto 40px;
        }

        .issue-submit-card,
        .campus-issue-card {
            padding: 17px;
        }

        .issue-form-grid {
            grid-template-columns: 1fr;
        }

        .issue-field-full {
            grid-column: auto;
        }
    }
</style>

<?php
mysqli_stmt_close($issuesStmt);
include __DIR__ . '/../includes/footer.php';
?>
