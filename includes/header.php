<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/student_auth.php';

$isStudentLoggedIn = (int)($_SESSION['student_db_id'] ?? 0) > 0;
$isAlumniLoggedIn = (int)($_SESSION['alumni_db_id'] ?? 0) > 0;
$isAdminLoggedIn = (int)($_SESSION['admin_id'] ?? 0) > 0;
$isLoggedIn = campusMateHasAccess();
$noticesUrl = $isLoggedIn
    ? '/CampusMate/student/notices.php'
    : campusMateStudentLoginUrl('notices');
$campusIssuesUrl = $isLoggedIn
    ? '/CampusMate/student/campus_issues.php'
    : campusMateStudentLoginUrl('campus-issues');
$opportunitiesUrl = $isLoggedIn
    ? '/CampusMate/student/opportunities.php'
    : campusMateStudentLoginUrl('opportunities');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/CampusMate/assets/css/style.css?v=20261110-notice-layout-4">

    <title>Campus Mate</title>
</head>

<body>

<header class="navbar">

    <div class="container nav-container">

        <!-- Logo -->
        <a href="/CampusMate/" class="logo">

            <span class="logo-mark">
                <span class="logo-cap"></span>
                <span class="logo-base"></span>
            </span>

            <span class="logo-text">
                Campus<span>Mate</span>
            </span>

        </a>

        <!-- Navigation -->
        <nav class="nav-links">

            <a href="/CampusMate/" class="active">
                Home
            </a>

            <a href="/CampusMate/#features">
                Features
            </a>

            <?php if (!$isAlumniLoggedIn): ?>
                <a href="<?= htmlspecialchars($noticesUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Notices
                </a>

                <a href="<?= htmlspecialchars($campusIssuesUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Campus Issues
                </a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($opportunitiesUrl, ENT_QUOTES, 'UTF-8') ?>">
                Opportunities
            </a>

        </nav>

        <!-- Buttons -->
        <div class="nav-buttons">

            <?php if ($isAdminLoggedIn): ?>

                <a href="/CampusMate/admin/dashboard.php" class="login-btn">
                    Admin Dashboard
                </a>

                <a href="/CampusMate/admin/logout.php" class="login-btn">
                    Logout
                </a>

            <?php elseif ($isStudentLoggedIn): ?>

                <a href="/CampusMate/student/dashboard.php" class="login-btn">
                    Dashboard
                </a>

                <a href="/CampusMate/student/profile.php" class="login-btn">
                    My Profile
                </a>

                <a href="/CampusMate/student/logout.php" class="login-btn">
                    Logout
                </a>

            <?php elseif ($isAlumniLoggedIn): ?>

                <a href="/CampusMate/alumni/post_opportunity.php" class="login-btn">
                    Post Opportunity
                </a>

                <a href="/CampusMate/alumni/logout.php" class="login-btn">
                    Alumni Logout
                </a>

            <?php else: ?>

                <a href="/CampusMate/student/login.php" class="login-btn">
                    Login
                </a>

                <a href="/CampusMate/student/register.php" class="register-btn">
                    Get Started
                </a>

                <a href="/CampusMate/alumni/login.php" class="login-btn">
                    Alumni
                </a>

            <?php endif; ?>

        </div>

    </div>

</header>
