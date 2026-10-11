<?php

session_start();

if (!isset($_SESSION["student_db_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/db.php";

$student_db_id = (int) $_SESSION["student_db_id"];

// Fetch student name
$student_query = mysqli_prepare(
    $conn,
    "SELECT name
     FROM students
     WHERE student_db_id = ?"
);

mysqli_stmt_bind_param($student_query, "i", $student_db_id);
mysqli_stmt_execute($student_query);

$student_result = mysqli_stmt_get_result($student_query);
$student = mysqli_fetch_assoc($student_result);

mysqli_stmt_close($student_query);

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Count uploaded notes
$notes_query = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_notes
     FROM notes
     WHERE student_db_id = ?"
);

mysqli_stmt_bind_param($notes_query, "i", $student_db_id);
mysqli_stmt_execute($notes_query);

$notes_result = mysqli_stmt_get_result($notes_query);
$notes_data = mysqli_fetch_assoc($notes_result);
$total_notes = (int) $notes_data["total_notes"];

mysqli_stmt_close($notes_query);

// Count all available notes
$all_notes_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total_notes FROM notes"
);

$all_notes_data = mysqli_fetch_assoc($all_notes_result);
$all_notes_count = (int) $all_notes_data["total_notes"];

// Fetch recent notes uploaded by this student
$recent_query = mysqli_prepare(
    $conn,
    "SELECT
        n.title,
        n.faculty_name,
        n.file_path,
        n.created_at,
        c.course_code
     FROM notes n
     LEFT JOIN courses c
        ON n.course_id = c.course_id
     WHERE n.student_db_id = ?
     ORDER BY n.created_at DESC
     LIMIT 5"
);

mysqli_stmt_bind_param($recent_query, "i", $student_db_id);
mysqli_stmt_execute($recent_query);

$recent_result = mysqli_stmt_get_result($recent_query);

include "../includes/header.php";

?>

<style>
.dashboard-wrapper {
    max-width: 1150px;
    margin: 35px auto;
    padding: 0 20px 40px;
    color: #1e293b;
}

/* Welcome banner */
.dashboard-welcome {
    position: relative;
    overflow: hidden;
    padding: 35px;
    margin-bottom: 25px;
    border-radius: 18px;
    background: linear-gradient(125deg, #1d4ed8, #4338ca);
    color: white;
}

.dashboard-welcome::after {
    content: "🎓";
    position: absolute;
    right: 45px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 75px;
    opacity: .18;
}

.welcome-label {
    display: inline-block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    opacity: .85;
}

.dashboard-welcome h1 {
    margin: 12px 0;
    font-size: 30px;
}

.dashboard-welcome p {
    max-width: 580px;
    margin: 0;
    line-height: 1.7;
    opacity: .92;
}

/* Statistics */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 32px;
}

.dashboard-stat {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 23px;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 13px;
    background: #dbeafe;
    font-size: 23px;
}

.stat-icon.purple {
    background: #ede9fe;
}

.stat-icon.green {
    background: #dcfce7;
}

.stat-label {
    display: block;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.stat-number {
    display: block;
    margin-top: 5px;
    color: #172554;
    font-size: 27px;
    font-weight: 750;
}

/* Sections */
.dashboard-section {
    margin-bottom: 32px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 17px;
}

.section-heading h2 {
    margin: 0;
    font-size: 22px;
    color: #172554;
}

.section-link {
    color: #2563eb;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

/* Quick actions */
.dashboard-shortcuts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.shortcut-card {
    display: block;
    padding: 23px;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    text-decoration: none;
    background: #fff;
    color: #1e293b;
    transition: transform .2s, box-shadow .2s, border-color .2s;
}

.shortcut-card:hover {
    border-color: #93c5fd;
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(15, 23, 42, .07);
}

.shortcut-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 47px;
    height: 47px;
    border-radius: 12px;
    background: #eff6ff;
    font-size: 24px;
}

.shortcut-card h3 {
    margin: 15px 0 8px;
    font-size: 17px;
}

.shortcut-card p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
}

/* Recent notes table */
.dashboard-table-wrap {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: white;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.dashboard-table th,
.dashboard-table td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

.dashboard-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 650;
}

.dashboard-table tr:last-child td {
    border-bottom: none;
}

.dashboard-table a {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
}

.dashboard-table a:hover,
.section-link:hover {
    text-decoration: underline;
}

.dashboard-empty {
    padding: 32px 20px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    color: #64748b;
    text-align: center;
}

/* Study resources banner */
.study-banner {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 30px;
    border: 1px solid #dbeafe;
    border-radius: 17px;
    background: linear-gradient(115deg, #eff6ff, #f5f3ff);
}

.study-banner-content {
    max-width: 650px;
}

.study-banner-label {
    color: #4338ca;
    font-size: 12px;
    font-weight: 750;
    letter-spacing: 1px;
}

.study-banner h2 {
    margin: 10px 0;
    color: #172554;
    font-size: 24px;
    line-height: 1.4;
}

.study-banner p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.8;
}

.study-banner-button {
    display: inline-block;
    flex-shrink: 0;
    padding: 13px 19px;
    border-radius: 9px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-size: 14px;
    font-weight: 650;
    transition: background .2s;
}

.study-banner-button:hover {
    background: #1d4ed8;
}

/* Responsive layout */
@media (max-width: 800px) {
    .dashboard-stats,
    .dashboard-shortcuts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .study-banner {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 550px) {
    .dashboard-wrapper {
        margin-top: 22px;
        padding: 0 14px 30px;
    }

    .dashboard-welcome {
        padding: 25px 22px;
    }

    .dashboard-welcome::after {
        right: 15px;
        font-size: 48px;
    }

    .dashboard-welcome h1 {
        font-size: 24px;
    }

    .dashboard-welcome p {
        max-width: 90%;
        font-size: 14px;
    }

    .dashboard-stats,
    .dashboard-shortcuts {
        grid-template-columns: 1fr;
    }

    .dashboard-stat {
        padding: 18px;
    }

    .section-heading h2 {
        font-size: 19px;
    }

    .study-banner {
        padding: 23px;
    }

    .study-banner h2 {
        font-size: 21px;
    }

    .study-banner-button {
        width: 100%;
        box-sizing: border-box;
        text-align: center;
    }
}
</style>

<div class="dashboard-wrapper">

    <!-- Welcome -->
    <section class="dashboard-welcome">

        <span class="welcome-label">STUDENT DASHBOARD</span>

        <h1>
            Welcome,
            <?= htmlspecialchars($student["name"]) ?>! 👋
        </h1>

        <p>
            Your campus learning space is ready.
            Discover study materials, share your notes
            and learn together with Campus Mate.
        </p>

    </section>

    <!-- Statistics -->
    <section class="dashboard-stats">

        <div class="dashboard-stat">
            <div class="stat-icon">📚</div>

            <div>
                <span class="stat-label">My Uploaded Notes</span>
                <strong class="stat-number">
                    <?= $total_notes ?>
                </strong>
            </div>
        </div>

        <div class="dashboard-stat">
            <div class="stat-icon purple">🗂️</div>

            <div>
                <span class="stat-label">Available Notes</span>
                <strong class="stat-number">
                    <?= $all_notes_count ?>
                </strong>
            </div>
        </div>

        <div class="dashboard-stat">
            <div class="stat-icon green">🎯</div>

            <div>
                <span class="stat-label">Your Contribution</span>
                <strong class="stat-number">
                    <?= $total_notes ?>
                </strong>
            </div>
        </div>

    </section>

    <!-- Quick Actions -->
    <section class="dashboard-section">

        <div class="section-heading">
            <h2>Quick Actions</h2>
        </div>

        <div class="dashboard-shortcuts">

            <a href="upload_note.php" class="shortcut-card">
                <div class="shortcut-icon">📤</div>

                <h3>Upload Notes</h3>

                <p>
                    Share your study materials and help
                    other students learn.
                </p>
            </a>

            <a href="notes.php" class="shortcut-card">
                <div class="shortcut-icon">📖</div>

                <h3>Explore Notes</h3>

                <p>
                    Browse study materials shared
                    by students.
                </p>
            </a>

            <a href="profile.php" class="shortcut-card">
                <div class="shortcut-icon">👤</div>

                <h3>My Profile</h3>

                <p>
                    View your student profile
                    and personal information.
                </p>
            </a>

        </div>

    </section>

    <!-- Recent Notes -->
    <section class="dashboard-section">

        <div class="section-heading">
            <h2>Recently Uploaded Notes</h2>

            <a href="notes.php" class="section-link">
                Explore all notes →
            </a>
        </div>

        <?php if (mysqli_num_rows($recent_result) > 0): ?>

            <div class="dashboard-table-wrap">

                <table class="dashboard-table">

                    <thead>
                        <tr>
                            <th>Note Title</th>
                            <th>Course</th>
                            <th>Faculty</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($note = mysqli_fetch_assoc($recent_result)): ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars($note["title"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $note["course_code"] ?? "N/A"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $note["faculty_name"] ?: "Not specified"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime($note["created_at"])
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <a
                                        href="../<?= htmlspecialchars(
                                            ltrim($note["file_path"], "/")
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        View PDF
                                    </a>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="dashboard-empty">
                <h3>No notes uploaded yet</h3>

                <p>
                    Start building your study library
                    by uploading your first note.
                </p>

                <a href="upload_note.php" class="section-link">
                    Upload your first note →
                </a>
            </div>

        <?php endif; ?>

    </section>

    <!-- Study Resources Banner -->
    <section class="dashboard-section">

        <div class="study-banner">

            <div class="study-banner-content">

                <span class="study-banner-label">
                    KEEP LEARNING
                </span>

                <h2>
                    Your next study resource is waiting.
                </h2>

                <p>
                    Explore notes shared by your fellow students,
                    find useful course materials and make
                    your study sessions more productive.
                </p>

            </div>

            <a href="notes.php" class="study-banner-button">
                Explore Study Notes →
            </a>

        </div>

    </section>

</div>

<?php

mysqli_stmt_close($recent_query);

include "../includes/footer.php";

?>
