<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['alumni_login_csrf'])) {
    $_SESSION['alumni_login_csrf'] = bin2hex(random_bytes(32));
}

$message = isset($_GET['registered'])
    ? 'Registration successful. You can sign in now.'
    : '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['alumni_login_csrf'], $submittedToken)
    ) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }

    $alumniId = trim(is_string($_POST['alumni_id'] ?? null) ? $_POST['alumni_id'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($alumniId === '' || $password === '') {
        $message = 'Enter your Alumni ID and password.';
        $messageType = 'error';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT alumni_db_id, alumni_id, name, email, password
             FROM alumni
             WHERE alumni_id = ?
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 's', $alumniId);
        mysqli_stmt_execute($stmt);
        $alumni = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$alumni || !password_verify($password, $alumni['password'])) {
            $message = 'Invalid Alumni ID or password.';
            $messageType = 'error';
        } else {
            session_regenerate_id(true);
            unset(
                $_SESSION['student_db_id'],
                $_SESSION['student_id'],
                $_SESSION['student_name'],
                $_SESSION['student_email'],
                $_SESSION['admin_id'],
                $_SESSION['admin_name']
            );
            $_SESSION['alumni_db_id'] = (int)$alumni['alumni_db_id'];
            $_SESSION['alumni_id'] = $alumni['alumni_id'];
            $_SESSION['alumni_name'] = $alumni['name'];
            $_SESSION['alumni_email'] = $alumni['email'];

            header('Location: /CampusMate/student/opportunities.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Login | CampusMate</title>
    <link rel="stylesheet" href="/CampusMate/assets/css/auth.css">
    <link rel="stylesheet" href="/CampusMate/assets/css/motion.css?v=20261124-calm-motion-1">
</head>
<body>
<div class="auth-page">
    <div class="auth-container login-container">
        <section class="auth-brand">
            <a href="/CampusMate/" class="brand-logo"><span class="logo-icon">🎓</span><span>Campus Mate</span></a>
            <div class="brand-content">
                <span class="brand-badge">CAMPUSMATE ALUMNI</span>
                <h1>Welcome back.</h1>
                <p>Sign in with your Alumni ID to share career opportunities with students.</p>
            </div>
        </section>
        <section class="auth-form-section">
            <div class="auth-form-wrapper login-form-wrapper">
                <div class="form-heading">
                    <span class="small-label">ALUMNI LOGIN</span>
                    <h2>Welcome back</h2>
                    <p>Sign in to continue to your Alumni account.</p>
                </div>
                <?php if ($message !== ''): ?>
                    <div class="alert <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['alumni_login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="alumni_id">Alumni ID</label>
                        <input id="alumni_id" name="alumni_id" required value="<?= htmlspecialchars(is_string($_POST['alumni_id'] ?? null) ? $_POST['alumni_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                    </div>
                    <button type="submit" class="auth-button">Sign in <span>→</span></button>
                </form>
                <p class="switch-auth">New to CampusMate? <a href="register.php">Register with Alumni ID</a></p>
                <p class="switch-auth"><a href="/CampusMate/student/login.php">Student login</a></p>
            </div>
        </section>
    </div>
</div>
</body>
</html>
