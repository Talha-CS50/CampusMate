<?php
require_once __DIR__ . '/../includes/student_auth.php';
campusMateRequireAlumni();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

function alumniOpportunityEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$alumniId = (int)$_SESSION['alumni_db_id'];
$accountStmt = mysqli_prepare(
    $conn,
    'SELECT alumni_db_id FROM alumni WHERE alumni_db_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($accountStmt, 'i', $alumniId);
mysqli_stmt_execute($accountStmt);
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($accountStmt));
mysqli_stmt_close($accountStmt);

if (!$account) {
    unset(
        $_SESSION['alumni_db_id'],
        $_SESSION['alumni_id'],
        $_SESSION['alumni_name'],
        $_SESSION['alumni_email']
    );
    header('Location: /CampusMate/alumni/login.php');
    exit;
}

if (empty($_SESSION['alumni_opportunity_csrf'])) {
    $_SESSION['alumni_opportunity_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$flash = $_SESSION['alumni_opportunity_flash'] ?? null;
unset($_SESSION['alumni_opportunity_flash']);
$values = [
    'title' => '',
    'type' => '',
    'organization' => '',
    'description' => '',
    'application_link' => '',
    'deadline' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['alumni_opportunity_csrf'], $submittedToken)
    ) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }

    foreach (array_keys($values) as $field) {
        $values[$field] = trim(
            is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
        );
    }

    $deadline = null;
    if ($values['deadline'] !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $values['deadline']);
        $deadline = $date && $date->format('Y-m-d') === $values['deadline']
            ? $values['deadline']
            : false;
    }

    $applicationScheme = strtolower(
        (string)parse_url($values['application_link'], PHP_URL_SCHEME)
    );

    if ($values['title'] === '' || strlen($values['title']) > 200) {
        $error = 'Title is required and must be 200 characters or fewer.';
    } elseif (!in_array($values['type'], ['Internship', 'Job'], true)) {
        $error = 'Select Internship or Job.';
    } elseif (
        $values['organization'] === ''
        || strlen($values['organization']) > 150
    ) {
        $error = 'Organization is required and must be 150 characters or fewer.';
    } elseif (strlen($values['application_link']) > 255) {
        $error = 'Application link must be 255 characters or fewer.';
    } elseif (
        $values['application_link'] !== ''
        && (
            filter_var($values['application_link'], FILTER_VALIDATE_URL) === false
            || !in_array($applicationScheme, ['http', 'https'], true)
        )
    ) {
        $error = 'Enter a valid http or https application link.';
    } elseif ($deadline === false) {
        $error = 'Enter a valid deadline.';
    } else {
        $description = $values['description'] === '' ? null : $values['description'];
        $applicationLink = $values['application_link'] === ''
            ? null
            : $values['application_link'];
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO opportunities
                (title, `type`, organization, description, application_link,
                 deadline, admin_id, alumni_db_id, approval_status)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 'Pending')"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'ssssssi',
            $values['title'],
            $values['type'],
            $values['organization'],
            $description,
            $applicationLink,
            $deadline,
            $alumniId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['alumni_opportunity_flash'] =
            'Your opportunity was submitted and is waiting for admin review.';
        header('Location: post_opportunity.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<main class="alumni-opportunity-page">
    <header>
        <span>ALUMNI NETWORK</span>
        <h1>Share a job or internship</h1>
        <p>Submissions are reviewed by an admin before they appear to students.</p>
    </header>

    <?php if ($flash): ?>
        <div class="alumni-message" role="status"><?= alumniOpportunityEscape($flash) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alumni-message alumni-error" role="alert"><?= alumniOpportunityEscape($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="alumni-opportunity-form">
        <input type="hidden" name="csrf_token" value="<?= alumniOpportunityEscape($_SESSION['alumni_opportunity_csrf']) ?>">
        <div class="alumni-form-grid">
            <label>
                Opportunity title *
                <input name="title" maxlength="200" required value="<?= alumniOpportunityEscape($values['title']) ?>">
            </label>
            <label>
                Type *
                <select name="type" required>
                    <option value="">Select type</option>
                    <?php foreach (['Internship', 'Job'] as $type): ?>
                        <option value="<?= alumniOpportunityEscape($type) ?>" <?= $values['type'] === $type ? 'selected' : '' ?>>
                            <?= alumniOpportunityEscape($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Company / organization *
                <input name="organization" maxlength="150" required value="<?= alumniOpportunityEscape($values['organization']) ?>">
            </label>
            <label>
                Deadline (optional)
                <input type="date" name="deadline" value="<?= alumniOpportunityEscape($values['deadline']) ?>">
            </label>
            <label class="alumni-field-full">
                Application link (optional)
                <input type="url" name="application_link" maxlength="255" placeholder="https://example.com/apply" value="<?= alumniOpportunityEscape($values['application_link']) ?>">
            </label>
            <label class="alumni-field-full">
                Description (optional)
                <textarea name="description" rows="6"><?= alumniOpportunityEscape($values['description']) ?></textarea>
            </label>
        </div>
        <button type="submit">Submit for admin review</button>
    </form>
</main>
<style>
    .alumni-opportunity-page{width:min(850px,92%);min-height:55vh;margin:48px auto 60px}
    .alumni-opportunity-page header{text-align:center;margin-bottom:24px}
    .alumni-opportunity-page header span{color:#4f46e5;font-size:11px;font-weight:800;letter-spacing:1.3px}
    .alumni-opportunity-page h1{margin:8px 0;color:#172554}
    .alumni-opportunity-page header p{color:#64748b}
    .alumni-opportunity-form{padding:24px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(31,41,55,.05)}
    .alumni-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
    .alumni-form-grid label{display:grid;gap:6px;color:#334155;font-size:13px;font-weight:650}
    .alumni-form-grid input,.alumni-form-grid select,.alumni-form-grid textarea{width:100%;padding:10px 11px;border:1px solid #cbd5e1;border-radius:7px;color:#243047;font:inherit}
    .alumni-field-full{grid-column:1/-1}
    .alumni-opportunity-form button{margin-top:16px;padding:11px 16px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font:inherit;font-weight:700;cursor:pointer}
    .alumni-message{margin-bottom:16px;padding:12px 15px;border-radius:9px;background:#dcfce7;color:#166534}
    .alumni-error{background:#fee2e2;color:#991b1b}
    @media(max-width:600px){.alumni-form-grid{grid-template-columns:1fr}.alumni-field-full{grid-column:auto}}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
