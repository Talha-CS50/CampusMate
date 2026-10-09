
<?php

session_start();

if (!isset($_SESSION["student_db_id"])) {
    header("Location: login.php");
    exit;
}

require "../config/db.php";

$student_db_id = $_SESSION["student_db_id"];

$sql = "
    SELECT
        students.student_id,
        students.name,
        students.email,
        students.batch,
        students.semester,
        departments.department_name
    FROM students
    LEFT JOIN departments
        ON students.department_id = departments.department_id
    WHERE students.student_db_id = ?
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $student_db_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$student) {
    exit("Student profile not found.");
}

$name = $student["name"] ?? "";
$initial = strtoupper(substr($name, 0, 1));

$department = $student["department_name"] ?? "";
$batch = $student["batch"] ?? "";
$semester = $student["semester"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Campus Mate</title>

    <link rel="stylesheet" href="/CampusMate/assets/css/auth.css">
    <link rel="stylesheet" href="/CampusMate/assets/css/profile.css">
</head>

<body class="profile-page">

    <nav class="dashboard-nav profile-nav">

        <a href="/CampusMate/" class="brand-logo">
            <span class="logo-icon">🎓</span>
            Campus Mate
        </a>

        <div class="profile-nav-actions">
            <a href="dashboard.php" class="profile-nav-link">
                Dashboard
            </a>

            <a href="logout.php" class="logout-button">
                Logout
            </a>
        </div>

    </nav>

    <main class="profile-main">

        <section class="profile-banner">
            <div class="profile-banner-content">
                <span class="profile-eyebrow">CAMPUS MATE / STUDENT</span>

                <h1>Your Profile</h1>

                <p>
                    Your academic identity, all in one place.
                </p>
            </div>

            <div class="profile-banner-decoration" aria-hidden="true">
                <span>✦</span>
                <span>✧</span>
                <span>✦</span>
            </div>
        </section>

        <section class="profile-layout">

            <div class="profile-summary-card">

                <div class="profile-avatar">
                    <?= htmlspecialchars($initial, ENT_QUOTES, "UTF-8") ?>
                </div>

                <span class="profile-status">
                    <span class="profile-status-dot"></span>
                    Student Account
                </span>

                <h2>
                    <?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p class="profile-email">
                    <?= htmlspecialchars($student["email"] ?? "", ENT_QUOTES, "UTF-8") ?>
                </p>

                <div class="profile-summary-divider"></div>

                <div class="profile-mini-info">
                    <span class="profile-mini-icon">🎓</span>

                    <div>
                        <span class="profile-label">Department</span>
                        <strong>
                            <?= htmlspecialchars($department ?: "Not assigned", ENT_QUOTES, "UTF-8") ?>
                        </strong>
                    </div>
                </div>

                <div class="profile-mini-info">
                    <span class="profile-mini-icon">🪪</span>

                    <div>
                        <span class="profile-label">Student ID</span>
                        <strong>
                            <?= htmlspecialchars($student["student_id"] ?? "", ENT_QUOTES, "UTF-8") ?>
                        </strong>
                    </div>
                </div>

                <a href="dashboard.php" class="profile-back-button">
                    <span>←</span> Back to Dashboard
                </a>
                
                <a href="edit_profile.php" class="profile-button">
                    Edit Profile
                 </a>

            </div>

            <div class="profile-details-section">

                <div class="profile-section-heading">
                    <div>
                        <span class="profile-section-kicker">PERSONAL SPACE</span>
                        <h2>Academic Information</h2>
                        <p>Review your registered student information.</p>
                    </div>

                    <span class="profile-heading-icon">✧</span>
                </div>

                <div class="profile-info-grid">

                    <article class="profile-info-card">
                        <div class="profile-info-icon icon-purple">🪪</div>
                        <span class="profile-label">Student ID</span>
                        <h3>
                            <?= htmlspecialchars($student["student_id"] ?? "", ENT_QUOTES, "UTF-8") ?>
                        </h3>
                        <p>Your university identification</p>
                    </article>

                    <article class="profile-info-card">
                        <div class="profile-info-icon icon-blue">✉️</div>
                        <span class="profile-label">Email Address</span>
                        <h3 class="profile-email-value">
                            <?= htmlspecialchars($student["email"] ?? "", ENT_QUOTES, "UTF-8") ?>
                        </h3>
                        <p>Your registered email</p>
                    </article>

                    <article class="profile-info-card">
                        <div class="profile-info-icon icon-green">🏛️</div>
                        <span class="profile-label">Department</span>
                        <h3>
                            <?= htmlspecialchars($department ?: "Not assigned", ENT_QUOTES, "UTF-8") ?>
                        </h3>
                        <p>Your academic department</p>
                    </article>

                    <article class="profile-info-card">
                        <div class="profile-info-icon icon-orange">📚</div>
                        <span class="profile-label">Batch</span>
                        <h3>
                            <?= htmlspecialchars($batch ?: "Not provided", ENT_QUOTES, "UTF-8") ?>
                        </h3>
                        <p>Your student batch</p>
                    </article>

                    <article class="profile-info-card profile-semester-card">
                        <div class="profile-info-icon icon-pink">📖</div>
                        <span class="profile-label">Current Semester</span>
                        <h3>
                            <?= htmlspecialchars($semester ?: "Not provided", ENT_QUOTES, "UTF-8") ?>
                        </h3>
                        <p>Your current academic semester</p>
                    </article>

                </div>

                <div class="profile-tip">
                    <span class="profile-tip-icon">💡</span>

                    <div>
                        <strong>Keep your information up to date</strong>
                        <p>
                            Accurate academic information helps you use Campus Mate more effectively.
                        </p>
                    </div>
                </div>

            </div>

        </section>

        <footer class="profile-footer">
            Made for students, built for campus life. <span>♥</span> Campus Mate
        </footer>

    </main>

</body>
</html>