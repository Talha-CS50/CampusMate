<?php
session_start();

if (!isset($_SESSION['student_db_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$success = "";
$error = "";

// Fetch courses for dropdown
$courses_query = mysqli_query(
    $conn,
    "SELECT course_id, course_code, course_name
     FROM courses
     ORDER BY course_code ASC"
);

if (isset($_GET['uploaded']) && $_GET['uploaded'] == 1) {
    $success = "Your note was uploaded successfully!";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST['title'] ?? "");
    $faculty_name = trim($_POST['faculty_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $course_id = (int) ($_POST['course_id'] ?? 0);

    // Validate required fields
    if ($title === "" || $faculty_name === "" || $course_id <= 0) {
        $error = "Please fill in all required fields.";

    } elseif (strlen($faculty_name) > 150) {
        $error = "Faculty name cannot exceed 150 characters.";

    } elseif (
        !isset($_FILES['note_file']) ||
        $_FILES['note_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $error = "Please select a PDF file to upload.";

    } else {
        $file = $_FILES['note_file'];

        // Maximum file size: 5 MB
        if ($file['size'] > 5 * 1024 * 1024) {
            $error = "File size must not exceed 5 MB.";

        } else {
            // Check MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);

            // Check PDF signature
            $file_handle = fopen($file['tmp_name'], "rb");
            $signature = $file_handle
                ? fread($file_handle, 5)
                : "";

            if ($file_handle) {
                fclose($file_handle);
            }

            if (
                $mime_type !== "application/pdf" ||
                $signature !== "%PDF-"
            ) {
                $error = "Only valid PDF files are allowed.";

            } else {
                // Check whether selected course exists
                $course_check = mysqli_prepare(
                    $conn,
                    "SELECT course_id
                     FROM courses
                     WHERE course_id = ?"
                );

                if (!$course_check) {
                    $error = "Could not validate the selected course.";
                } else {
                    mysqli_stmt_bind_param(
                        $course_check,
                        "i",
                        $course_id
                    );

                    mysqli_stmt_execute($course_check);

                    $course_result = mysqli_stmt_get_result(
                        $course_check
                    );

                    $course_exists = mysqli_num_rows($course_result) > 0;

                    mysqli_stmt_close($course_check);

                    if (!$course_exists) {
                        $error = "Please select a valid course.";
                    }
                }

                if ($error === "") {

                    // Upload directory:
                    // CampusMate/student/uploads/notes/
                    
                    $upload_dir = __DIR__ . "/uploads/notes/";

                    // Create directory if it does not exist
                    if (!is_dir($upload_dir)) {
                        if (
                            !mkdir($upload_dir, 0755, true) &&
                            !is_dir($upload_dir)
                        ) {
                            $error = "Could not create the upload directory.";
                        }
                    }

                    // Check directory permissions
                    if (
                        $error === "" &&
                        !is_writable($upload_dir)
                    ) {
                        $error = "Upload directory is not writable.";
                    }

                    if ($error === "") {

                        // Generate a unique filename
                        $new_filename = bin2hex(random_bytes(16)) . ".pdf";

                        $destination = $upload_dir . $new_filename;

                        // Relative path used by notes.php
                        $relative_path = "uploads/notes/" . $new_filename;

                        if (
                            move_uploaded_file(
                                $file['tmp_name'],
                                $destination
                            )
                        ) {
                            $student_db_id = (int) $_SESSION['student_db_id'];

                            // Save note details in database
                            $stmt = mysqli_prepare(
                                $conn,
                                "INSERT INTO notes
                                (
                                    title,
                                    faculty_name,
                                    description,
                                    file_path,
                                    student_db_id,
                                    course_id
                                )
                                VALUES (?, ?, ?, ?, ?, ?)"
                            );

                            if (!$stmt) {
                                // Remove uploaded file if query preparation fails
                                if (is_file($destination)) {
                                    unlink($destination);
                                }

                                $error = "Could not prepare the note for saving.";
                            } else {
                                mysqli_stmt_bind_param(
                                    $stmt,
                                    "ssssii",
                                    $title,
                                    $faculty_name,
                                    $description,
                                    $relative_path,
                                    $student_db_id,
                                    $course_id
                                );

                                if (mysqli_stmt_execute($stmt)) {
                                    mysqli_stmt_close($stmt);

                                    header(
                                        "Location: upload_note.php?uploaded=1"
                                    );
                                    exit();

                                } else {
                                    // Remove uploaded file if DB save fails
                                    if (is_file($destination)) {
                                        unlink($destination);
                                    }

                                    $error = "Could not save the note. Please try again.";

                                    mysqli_stmt_close($stmt);
                                }
                            }

                        } else {
                            $error = "File upload failed. Please try again.";
                        }
                    }
                }
            }
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>

<style>
.upload-container {
    max-width: 720px;
    margin: 35px auto;
    padding: 30px;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
}

.upload-container h2 {
    margin-bottom: 8px;
    color: #172554;
}

.upload-subtitle {
    color: #64748b;
    margin-bottom: 25px;
}

.upload-form .form-group {
    margin-bottom: 18px;
}

.upload-form label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
    color: #334155;
}

.upload-form input,
.upload-form select,
.upload-form textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    box-sizing: border-box;
    background: #fff;
}

.upload-form input:focus,
.upload-form select:focus,
.upload-form textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.upload-form textarea {
    min-height: 110px;
    resize: vertical;
}

.upload-btn {
    width: 100%;
    padding: 13px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.upload-btn:hover {
    background: #1d4ed8;
}

.upload-message {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.upload-success {
    color: #166534;
    background: #dcfce7;
}

.upload-error {
    color: #991b1b;
    background: #fee2e2;
}

@media (max-width: 600px) {
    .upload-container {
        margin: 20px 12px;
        padding: 20px;
    }
}
</style>

<div class="upload-container">
    <h2>Upload Study Notes</h2>

    <p class="upload-subtitle">
        Share your study materials with other students.
    </p>

    <?php if ($success !== ""): ?>
        <div class="upload-message upload-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="upload-message upload-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form
        class="upload-form"
        action="upload_note.php"
        method="POST"
        enctype="multipart/form-data"
    >
        <div class="form-group">
            <label for="title">Note Title *</label>

            <input
                type="text"
                id="title"
                name="title"
                maxlength="200"
                placeholder="e.g. Database Normalization Notes"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="faculty_name">Faculty Name *</label>

            <input
                type="text"
                id="faculty_name"
                name="faculty_name"
                maxlength="150"
                placeholder="e.g. Dr. Rahman"
                value="<?= htmlspecialchars($_POST['faculty_name'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="course_id">Course *</label>

            <select id="course_id" name="course_id" required>
                <option value="">Select a course</option>

                <?php if ($courses_query): ?>
                    <?php while (
                        $course = mysqli_fetch_assoc($courses_query)
                    ): ?>
                        <option
                            value="<?= (int) $course['course_id'] ?>"
                            <?= (
                                isset($_POST['course_id']) &&
                                (int) $_POST['course_id'] ===
                                (int) $course['course_id']
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($course['course_code']) ?>
                            -
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                placeholder="Briefly describe these notes..."
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="note_file">
                Select PDF File * (Maximum 5 MB)
            </label>

            <input
                type="file"
                id="note_file"
                name="note_file"
                accept=".pdf,application/pdf"
                required
            >
        </div>

        <button type="submit" class="upload-btn">
            Upload Note
        </button>
    </form>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

