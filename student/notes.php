<?php
session_start();

if (!isset($_SESSION['student_db_id'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/db.php";

$query = "
    SELECT
        notes.note_id,
        notes.title,
        notes.faculty_name,
        notes.description,
        notes.file_path,
        notes.created_at,
        students.name AS student_name,
        courses.course_name,
        courses.course_code
    FROM notes
    JOIN students
        ON notes.student_db_id = students.student_db_id
    JOIN courses
        ON notes.course_id = courses.course_id
    ORDER BY notes.created_at DESC
";

$result = mysqli_query($conn, $query);

include "../includes/header.php";
?>

<style>
.notes-page {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 20px;
}

.notes-heading {
    margin-bottom: 25px;
}

.notes-heading h2 {
    font-size: 30px;
    color: #172554;
    margin-bottom: 8px;
}

.notes-heading p {
    color: #64748b;
    margin: 0;
}

.notes-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
}

.note-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 22px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}

.note-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.10);
}

.note-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin-bottom: 15px;
    border-radius: 12px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 24px;
}

.note-title {
    color: #1e293b;
    font-size: 19px;
    line-height: 1.4;
    overflow-wrap: anywhere;
    margin: 0 0 12px;
}

.course-badge {
    display: inline-block;
    align-self: flex-start;
    padding: 5px 10px;
    margin-bottom: 15px;
    border-radius: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
}

.note-details {
    display: grid;
    gap: 10px;
    margin-bottom: 15px;
    font-size: 14px;
}

.note-detail {
    color: #475569;
    overflow-wrap: anywhere;
}

.note-detail strong {
    color: #334155;
}

.note-description {
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
    margin: 0 0 18px;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.note-uploader {
    padding-top: 13px;
    margin-top: auto;
    border-top: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 13px;
}

.note-date {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #94a3b8;
}

.note-actions {
    display: flex;
    gap: 10px;
    margin-top: 17px;
}

.note-btn {
    flex: 1;
    min-width: 0;
    padding: 10px 8px;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.view-btn {
    color: #fff;
    background: #2563eb;
}

.view-btn:hover {
    background: #1d4ed8;
}

.download-btn {
    color: #1d4ed8;
    background: #dbeafe;
}

.download-btn:hover {
    background: #bfdbfe;
}

.empty-notes {
    padding: 40px 20px;
    text-align: center;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    color: #64748b;
}

@media (max-width: 900px) {
    .notes-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .notes-page {
        padding: 0 14px;
        margin: 25px auto;
    }

    .notes-heading h2 {
        font-size: 25px;
    }

    .notes-grid {
        grid-template-columns: 1fr;
    }

    .note-card {
        padding: 18px;
    }
}
</style>

<div class="notes-page">

    <div class="notes-heading">
        <h2>Explore Study Notes</h2>
        <p>Find and download study materials shared by students.</p>
    </div>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>

        <div class="notes-grid">

            <?php while ($note = mysqli_fetch_assoc($result)): ?>

                <?php
                $file_path = "../" . ltrim($note['file_path'], "/");
                ?>

                <div class="note-card">

                    <div class="note-icon">📄</div>

                    <h3 class="note-title">
                        <?= htmlspecialchars($note['title']) ?>
                    </h3>

                    <span class="course-badge">
                        <?= htmlspecialchars($note['course_code']) ?>
                    </span>

                    <div class="note-details">

                        <div class="note-detail">
                            <strong>Course:</strong>
                            <?= htmlspecialchars($note['course_name']) ?>
                        </div>

                        <div class="note-detail">
                            <strong>Faculty:</strong>
                            <?= htmlspecialchars(
                                $note['faculty_name'] ?: 'Not specified'
                            ) ?>
                        </div>

                    </div>

                    <p class="note-description"><?=
                        htmlspecialchars(
                            $note['description'] ?: 'No description provided.'
                        )
                    ?></p>

                    <div class="note-uploader">
                        Uploaded by
                        <strong><?= htmlspecialchars($note['student_name']) ?></strong>

                        <span class="note-date">
                            <?= htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime($note['created_at'])
                                )
                            ) ?>
                        </span>
                    </div>

                    <div class="note-actions">
                        <a
                            class="note-btn view-btn"
                            href="<?= htmlspecialchars($file_path) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            View PDF
                        </a>

                        <a
                            class="note-btn download-btn"
                            href="<?= htmlspecialchars($file_path) ?>"
                            download
                        >
                            Download
                        </a>
                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php elseif ($result): ?>

        <div class="empty-notes">
            <h3>No notes available yet</h3>
            <p>Be the first to share your study materials!</p>
        </div>

    <?php else: ?>

        <div class="empty-notes">
            Could not load notes. Please try again later.
        </div>

    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>

