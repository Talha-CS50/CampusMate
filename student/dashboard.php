<?php

session_start();

if (!isset($_SESSION["student_db_id"])) {
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard - Campus Mate</title>

    <link rel="stylesheet"
          href="/CampusMate/assets/css/auth.css">

</head>

<body>

<div class="dashboard-page">

    <nav class="dashboard-nav">

        <a href="/CampusMate/" class="brand-logo">
            <span class="logo-icon">🎓</span>
            Campus Mate
        </a>

        <a href="logout.php" class="logout-button">
            Logout
        </a>

    </nav>


    <main class="dashboard-content">

        <span class="brand-badge">
            STUDENT DASHBOARD
        </span>

        <h1>
            Welcome,
            <?= htmlspecialchars($_SESSION["student_name"]) ?>! 👋
        </h1>

        <p>
            You are successfully logged in to Campus Mate.
        </p>


        <div class="dashboard-card">

            <div>
                <span>Student ID</span>
                <strong>
                    <?= htmlspecialchars($_SESSION["student_id"]) ?>
                </strong>
            </div>


            <div>
                <span>Email</span>
                <strong>
                    <?= htmlspecialchars($_SESSION["student_email"]) ?>
                </strong>
            </div>

        </div>


<a href="profile.php" class="profile-button">
    My Profile
</a>

    </main>

</div>

</body>
</html>