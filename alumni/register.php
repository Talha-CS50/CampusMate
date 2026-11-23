<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['alumni_registration_csrf'])) {
    $_SESSION['alumni_registration_csrf'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['alumni_registration_csrf'], $submittedToken)
    ) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }

    $alumniId = trim(is_string($_POST['alumni_id'] ?? null) ? $_POST['alumni_id'] : '');
    $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password']
        : '';

    if (
        $alumniId === ''
        || strlen($alumniId) > 50
        || $name === ''
        || strlen($name) > 100
        || $email === ''
        || strlen($email) > 100
        || $password === ''
        || $confirmPassword === ''
    ) {
        $message = 'Please fill in all fields. Alumni ID, name, or email is too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT alumni_db_id FROM alumni WHERE alumni_id = ? OR email = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'ss', $alumniId, $email);
        mysqli_stmt_execute($stmt);
        $existingAccount = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existingAccount) {
            $message = 'That Alumni ID or email is already registered.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO alumni
                    (alumni_id, name, email, password, approval_status)
                 VALUES (?, ?, ?, ?, 'Approved')"
            );
            mysqli_stmt_bind_param($stmt, 'ssss', $alumniId, $name, $email, $passwordHash);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: login.php?registered=1');
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
    <title>Alumni Registration | CampusMate</title>
    <link rel="stylesheet" href="/CampusMate/assets/css/auth.css">
    <link rel="stylesheet" href="/CampusMate/assets/css/motion.css?v=20261124-calm-motion-1">
</head>
<body>
<div class="auth-page">
    <div class="auth-container">
        <section class="auth-brand">
            <a href="/CampusMate/" class="brand-logo"><span class="logo-icon">🎓</span><span>Campus Mate</span></a>
            <div class="brand-content">
                <span class="brand-badge">CAMPUSMATE ALUMNI</span>
                <h1>Share your experience.</h1>
                <p>Register with your Alumni ID to share job and internship opportunities with current students.</p>
            </div>
        </section>
        <section class="auth-form-section">
            <div class="auth-form-wrapper">
                <div class="form-heading">
                    <span class="small-label">ALUMNI ACCOUNT</span>
                    <h2>Create your account</h2>
                    <p>Register with your Alumni ID and sign in right away.</p>
                </div>
                <?php if ($message !== ''): ?>
                    <div class="alert error" role="alert">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['alumni_registration_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="alumni_id">Alumni ID</label>
                        <input id="alumni_id" name="alumni_id" maxlength="50" required value="<?= htmlspecialchars(is_string($_POST['alumni_id'] ?? null) ? $_POST['alumni_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="name">Alumni Name</label>
                        <input id="name" name="name" maxlength="100" placeholder="Enter your full name" required value="<?= htmlspecialchars(is_string($_POST['name'] ?? null) ? $_POST['name'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" maxlength="100" required value="<?= htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="password" minlength="8" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm password</label>
                            <input id="confirm_password" type="password" name="confirm_password" minlength="8" required>
                        </div>
                    </div>
                    <button type="submit" class="auth-button">Register for review <span>→</span></button>
                </form>
                <p class="switch-auth">Already registered? <a href="login.php">Alumni login</a></p>
                <p class="switch-auth"><a href="/CampusMate/student/login.php">Student login</a></p>
            </div>
        </section>
    </div>
</div>
</body>
</html>
