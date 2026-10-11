<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["student_db_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/db.php";

$student_db_id = (int) $_SESSION["student_db_id"];

if (empty($_SESSION["profile_csrf_token"])) {
    $_SESSION["profile_csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "success";

// Handle deleting the student's own note
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_note_id"])) {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token)
        || !hash_equals($_SESSION["profile_csrf_token"], $token)) {

        $message = "Invalid request. Please refresh the page.";
        $message_type = "error";

    } else {

        $note_id = filter_var(
            $_POST["delete_note_id"],
            FILTER_VALIDATE_INT
        );

        if (!$note_id || $note_id < 1) {

            $message = "Invalid note selected.";
            $message_type = "error";

        } else {

            $find_note = mysqli_prepare(
                $conn,
                "SELECT file_path
                 FROM notes
                 WHERE note_id = ? AND student_db_id = ?"
            );

            mysqli_stmt_bind_param(
                $find_note,
                "ii",
                $note_id,
                $student_db_id
            );

            mysqli_stmt_execute($find_note);
            $found_note = mysqli_stmt_get_result($find_note);
            $note_to_delete = mysqli_fetch_assoc($found_note);
            mysqli_stmt_close($find_note);

            if (!$note_to_delete) {

                $message = "Note not found or you cannot delete it.";
                $message_type = "error";

            } else {

                $delete_query = mysqli_prepare(
                    $conn,
                    "DELETE FROM notes
                     WHERE note_id = ? AND student_db_id = ?"
                );

                mysqli_stmt_bind_param(
                    $delete_query,
                    "ii",
                    $note_id,
                    $student_db_id
                );

                if (mysqli_stmt_execute($delete_query)
                    && mysqli_stmt_affected_rows($delete_query) === 1) {

                    // Delete the physical file only from uploads/notes.
                    $stored_path = $note_to_delete["file_path"];
                    $filename = basename($stored_path);
                    $upload_dir = realpath(
                        dirname(__DIR__) . "/uploads/notes"
                    );

                    if (
                        $upload_dir !== false
                        && $filename !== ""
                        && $stored_path === "uploads/notes/" . $filename
                    ) {
                        $physical_file = $upload_dir . DIRECTORY_SEPARATOR
                            . $filename;

                        if (is_file($physical_file)) {
                            unlink($physical_file);
                        }
                    }

                    $message = "Your note has been deleted successfully.";

                } else {

                    $message = "Could not delete the note. Please try again.";
                    $message_type = "error";

                }

                mysqli_stmt_close($delete_query);
            }
        }
    }
}

// Fetch student profile
$profile_query = mysqli_prepare(
    $conn,
    "SELECT
        s.student_id,
        s.name,
        s.email,
        s.batch,
        s.semester,
        d.department_name
     FROM students s
     LEFT JOIN departments d
        ON s.department_id = d.department_id
     WHERE s.student_db_id = ?"
);

mysqli_stmt_bind_param(
    $profile_query,
    "i",
    $student_db_id
);

mysqli_stmt_execute($profile_query);

$profile_result = mysqli_stmt_get_result($profile_query);
$student = mysqli_fetch_assoc($profile_result);

mysqli_stmt_close($profile_query);

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Count this student's uploaded notes
$count_query = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notes
     WHERE student_db_id = ?"
);

mysqli_stmt_bind_param($count_query, "i", $student_db_id);
mysqli_stmt_execute($count_query);

$count_result = mysqli_stmt_get_result($count_query);
$count_data = mysqli_fetch_assoc($count_result);
$total_notes = (int) $count_data["total"];

mysqli_stmt_close($count_query);

// Fetch uploaded notes
$notes_query = mysqli_prepare(
    $conn,
    "SELECT
        n.note_id,
        n.title,
        n.faculty_name,
        n.description,
        n.file_path,
        n.created_at,
        c.course_code,
        c.course_name
     FROM notes n
     LEFT JOIN courses c
        ON n.course_id = c.course_id
     WHERE n.student_db_id = ?
     ORDER BY n.created_at DESC"
);

mysqli_stmt_bind_param($notes_query, "i", $student_db_id);
mysqli_stmt_execute($notes_query);

$notes_result = mysqli_stmt_get_result($notes_query);

include "../includes/header.php";

?>

<style>
.profile-page {
    max-width: 1120px;
    margin: 38px auto;
    padding: 0 20px 45px;
    color: #1e293b;
}

.profile-heading {
    margin-bottom: 25px;
}

.profile-heading h1 {
    margin: 0 0 8px;
    color: #172554;
    font-size: 30px;
}

.profile-heading p {
    margin: 0;
    color: #64748b;
    line-height: 1.7;
}

/* Profile hero */
.profile-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 23px;
    padding: 32px;
    margin-bottom: 24px;
    border-radius: 20px;
    color: white;
    background: linear-gradient(120deg, #1d4ed8, #4338ca);
}

.profile-hero::after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    right: -75px;
    top: -100px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
}

.profile-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 92px;
    height: 92px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,.55);
    background: rgba(255,255,255,.16);
    color: white;
    font-size: 34px;
    font-weight: 750;
    text-transform: uppercase;
}

.profile-hero-content {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.profile-hero-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    opacity: .8;
}

.profile-hero h2 {
    margin: 8px 0;
    font-size: 27px;
    overflow-wrap: anywhere;
}

.profile-hero p {
    margin: 0;
    color: #dbeafe;
    font-size: 14px;
    overflow-wrap: anywhere;
}

/* Stats */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 32px;
}

.profile-stat {
    padding: 22px;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    background: white;
    box-shadow: 0 4px 14px rgba(15,23,42,.035);
}

.profile-stat-label {
    color: #64748b;
    font-size: 13px;
}

.profile-stat-value {
    display: block;
    margin-top: 9px;
    color: #172554;
    font-size: 23px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.profile-stat-icon {
    margin-bottom: 12px;
    font-size: 22px;
}

/* General cards */
.profile-card {
    padding: 26px;
    margin-bottom: 30px;
    border: 1px solid #e2e8f0;
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 4px 15px rgba(15,23,42,.03);
}

.profile-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 22px;
}

.profile-section-heading h2 {
    margin: 0;
    color: #172554;
    font-size: 21px;
}

.profile-section-heading p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

/* Personal information */
.profile-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.profile-info-item {
    min-width: 0;
    padding: 17px;
    border: 1px solid #e8edf5;
    border-radius: 12px;
    background: #f8fafc;
}

.profile-info-label {
    display: block;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 12px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.profile-info-value {
    display: block;
    color: #1e293b;
    font-size: 15px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

/* Notes */
.profile-notes-list {
    display: grid;
    gap: 14px;
}

.profile-note {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
    transition: border-color .2s, box-shadow .2s;
}

.profile-note:hover {
    border-color: #bfdbfe;
    box-shadow: 0 5px 16px rgba(15,23,42,.04);
}

.profile-note-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: #fee2e2;
    font-size: 21px;
}

.profile-note-content {
    flex: 1;
    min-width: 0;
}

.profile-note-title {
    margin: 0 0 7px;
    color: #172554;
    font-size: 16px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.profile-note-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    margin-bottom: 9px;
    color: #64748b;
    font-size: 12px;
}

.profile-note-description {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.profile-note-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 9px;
    margin-top: 13px;
}

.profile-button {
    display: inline-block;
    padding: 9px 13px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-family: inherit;
    font-size: 12px;
    font-weight: 650;
    cursor: pointer;
}

.profile-button:hover {
    background: #1d4ed8;
}

.profile-delete-button {
    padding: 8px 11px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff;
    color: #b91c1c;
    font-family: inherit;
    font-size: 12px;
    font-weight: 650;
    cursor: pointer;
}

.profile-delete-button:hover {
    background: #fef2f2;
}

.profile-empty {
    padding: 35px 20px;
    border: 1px dashed #cbd5e1;
    border-radius: 13px;
    background: #f8fafc;
    text-align: center;
}

.profile-empty-icon {
    margin-bottom: 10px;
    font-size: 35px;
}

.profile-empty h3 {
    margin: 0 0 8px;
    color: #172554;
}

.profile-empty p {
    margin: 0 0 18px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
}

.profile-message {
    padding: 13px 16px;
    margin-bottom: 22px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.6;
}

.profile-message.success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.profile-message.error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

.profile-bottom-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 26px;
    border-radius: 16px;
    background: linear-gradient(120deg, #eff6ff, #f5f3ff);
}

.profile-bottom-banner h3 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 19px;
}

.profile-bottom-banner p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

@media (max-width: 700px) {
    .profile-stats {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .profile-stat {
        padding: 17px;
    }

    .profile-info-grid {
        grid-template-columns: 1fr;
    }

    .profile-hero {
        align-items: flex-start;
        padding: 25px 21px;
        gap: 16px;
    }

    .profile-avatar {
        width: 65px;
        height: 65px;
        font-size: 25px;
    }

    .profile-hero h2 {
        font-size: 21px;
    }

    .profile-card {
        padding: 19px;
    }

    .profile-note {
        flex-wrap: wrap;
        padding: 15px;
    }

    .profile-note-actions {
        align-items: stretch;
    }

    .profile-bottom-banner {
        align-items: flex-start;
        flex-direction: column;
        padding: 21px;
    }
}

@media (max-width: 430px) {
    .profile-page {
        margin-top: 24px;
        padding: 0 13px 30px;
    }

    .profile-heading h1 {
        font-size: 25px;
    }

    .profile-hero {
        flex-direction: column;
    }

    .profile-section-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="profile-page">

    <div class="profile-heading">
        <h1>My Profile</h1>
        <p>Manage your student information and study contributions.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="profile-message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Profile overview -->
    <section class="profile-hero">

        <div class="profile-avatar">
            <?= htmlspecialchars(
                strtoupper(substr(trim($student["name"]), 0, 1))
            ) ?>
        </div>

        <div class="profile-hero-content">
            <span class="profile-hero-label">STUDENT PROFILE</span>

            <h2><?= htmlspecialchars($student["name"]) ?></h2>

            <p>
                <?= htmlspecialchars($student["email"]) ?>
            </p>
        </div>

    </section>

    <!-- Statistics -->
    <section class="profile-stats">

        <div class="profile-stat">
            <div class="profile-stat-icon">📚</div>
            <span class="profile-stat-label">Notes Uploaded</span>
            <strong class="profile-stat-value">
                <?= $total_notes ?>
            </strong>
        </div>

        <div class="profile-stat">
            <div class="profile-stat-icon">🎓</div>
            <span class="profile-stat-label">Department</span>
            <strong class="profile-stat-value">
                <?= htmlspecialchars(
                    $student["department_name"] ?: "Not specified"
                ) ?>
            </strong>
        </div>

        <div class="profile-stat">
            <div class="profile-stat-icon">📅</div>
            <span class="profile-stat-label">Current Semester</span>
            <strong class="profile-stat-value">
                <?= htmlspecialchars(
                    (string) ($student["semester"] ?? "Not specified")
                ) ?>
            </strong>
        </div>

    </section>

    <!-- Student information -->
    <section class="profile-card">

        <div class="profile-section-heading">
            <div>
                <h2>Personal Information</h2>
                <p>Your registered student details.</p>
            </div>
        </div>

        <div class="profile-info-grid">

            <div class="profile-info-item">
                <span class="profile-info-label">Full Name</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars($student["name"]) ?>
                </span>
            </div>

            <div class="profile-info-item">
                <span class="profile-info-label">Student ID</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars($student["student_id"]) ?>
                </span>
            </div>

            <div class="profile-info-item">
                <span class="profile-info-label">Email Address</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars($student["email"]) ?>
                </span>
            </div>

            <div class="profile-info-item">
                <span class="profile-info-label">Department</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars(
                        $student["department_name"] ?: "Not specified"
                    ) ?>
                </span>
            </div>

            <div class="profile-info-item">
                <span class="profile-info-label">Batch</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars(
                        (string) ($student["batch"] ?? "Not specified")
                    ) ?>
                </span>
            </div>

            <div class="profile-info-item">
                <span class="profile-info-label">Semester</span>
                <span class="profile-info-value">
                    <?= htmlspecialchars(
                        (string) ($student["semester"] ?? "Not specified")
                    ) ?>
                </span>
            </div>

        </div>

    </section>

    <!-- Uploaded notes -->
    <section class="profile-card">

        <div class="profile-section-heading">
            <div>
                <h2>My Uploaded Notes</h2>
                <p>View and manage the notes you have shared.</p>
            </div>

            <a href="upload_note.php" class="profile-button">
                + Upload Note
            </a>
        </div>

        <?php if (mysqli_num_rows($notes_result) > 0): ?>

            <div class="profile-notes-list">

                <?php while ($note = mysqli_fetch_assoc($notes_result)): ?>

                    <article class="profile-note">

                        <div class="profile-note-icon">📄</div>

                        <div class="profile-note-content">

                            <h3 class="profile-note-title">
                                <?= htmlspecialchars($note["title"]) ?>
                            </h3>

                            <div class="profile-note-meta">
                                <span>
                                    📘
                                    <?= htmlspecialchars(
                                        $note["course_code"] ?? "N/A"
                                    ) ?>
                                </span>

                                <span>
                                    👨‍🏫
                                    <?= htmlspecialchars(
                                        $note["faculty_name"] ?: "Not specified"
                                    ) ?>
                                </span>

                                <span>
                                    📅
                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime($note["created_at"])
                                        )
                                    ) ?>
                                </span>
                            </div>

                            <?php if (!empty($note["description"])): ?>
                                <p class="profile-note-description">
                                    <?= htmlspecialchars($note["description"]) ?>
                                </p>
                            <?php endif; ?>

                            <div class="profile-note-actions">

                                <a
                                    class="profile-button"
                                    href="../<?= htmlspecialchars(
                                        ltrim($note["file_path"], "/")
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    View PDF ↗
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this note? This action cannot be undone.');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $_SESSION["profile_csrf_token"]
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="delete_note_id"
                                        value="<?= (int) $note["note_id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="profile-delete-button"
                                    >
                                        Delete Note
                                    </button>
                                </form>

                            </div>

                        </div>

                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="profile-empty">
                <div class="profile-empty-icon">📚</div>

                <h3>No notes uploaded yet</h3>

                <p>
                    Your uploaded study materials will appear here.
                    Share your first note with the CampusMate community!
                </p>

                <a href="upload_note.php" class="profile-button">
                    Upload Your First Note
                </a>
            </div>

        <?php endif; ?>

    </section>

    <!-- Bottom banner -->
    <section class="profile-bottom-banner">

        <div>
            <h3>Keep learning, keep sharing! ✨</h3>

            <p>
                Explore more study materials from your fellow students.
            </p>
        </div>

        <a href="notes.php" class="profile-button">
            Explore Notes →
        </a>

    </section>

</div>

<?php

mysqli_stmt_close($notes_query);

include "../includes/footer.php";

?>