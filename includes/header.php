<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['student_db_id']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/CampusMate/assets/css/style.css">

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

            <a href="/CampusMate/#notices">
                Notices
            </a>

            <a href="/CampusMate/#opportunities">
                Opportunities
            </a>

        </nav>

        <!-- Buttons -->
        <div class="nav-buttons">

            <?php if ($isLoggedIn): ?>

                <a href="/CampusMate/student/dashboard.php" class="login-btn">
                    Dashboard
                </a>

                <a href="/CampusMate/student/profile.php" class="login-btn">
                    My Profile
                </a>

                <a href="/CampusMate/student/logout.php" class="login-btn">
                    Logout
                </a>

            <?php else: ?>

                <a href="/CampusMate/student/login.php" class="login-btn">
                    Login
                </a>

                <a href="/CampusMate/student/register.php" class="register-btn">
                    Get Started
                </a>

            <?php endif; ?>

        </div>

    </div>

</header>
