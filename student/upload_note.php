<?php
session_start();

if (!isset($_SESSION['student_db_id'])) {
    header("Location: login.php");
    exit;
}

require_once "../config/db.php";

$student_db_id = (int) $_SESSION['student_db_id'];

$title = "";
$description = "";
$course_id = 0;
$errors = [];
$success = "";

// Fetch available courses
$courses = [];

$course_query = mysqli_query(
    $conn,
    "SELECT course_id, course_code, course_name
     FROM courses
     ORDER BY course_code ASC"
);

if ($course_query) {
    while ($course = mysqli_fetch_assoc($course_query)) {
        $courses[] = $course;
    }
} else {
    $errors[] = "Could not load courses from the database.";
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $course_id = (int) ($_POST["course_id"] ?? 0);

    // Validate title
    if ($title === "") {
        $errors[] = "Please enter a note title.";
    } elseif (strlen($title) > 200) {
        $errors[] = "Title cannot exceed 200 characters.";
    }

    // Validate course
    $valid_course = false;

    foreach ($courses as $course) {
        if ((int) $course["course_id"] === $course_id) {
            $valid_course = true;
            break;
        }
    }

    if (!$valid_course) {
        $errors[] = "Please select a valid course.";
    }

    // Validate uploaded file
    $uploaded_file = $_FILES["note_file"] ?? null;

    if ($uploaded_file === null) {

        $errors[] = "Please select a PDF file.";

    } elseif ($uploaded_file["error"] !== UPLOAD_ERR_OK) {

        if ($uploaded_file["error"] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Please select a PDF file.";
        } else {
            $errors[] = "File upload failed. Error code: "
                . (int) $uploaded_file["error"];
        }

    } else {

        // Maximum file size: 5 MB
        if ($uploaded_file["size"] > 5 * 1024 * 1024) {
            $errors[] = "PDF file must not exceed 5 MB.";
        }

        // Check actual MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($uploaded_file["tmp_name"]);

        if ($mime_type !== "application/pdf") {
            $errors[] = "Only PDF files are allowed.";
        }

        // Check PDF signature
        $file_handle = fopen($uploaded_file["tmp_name"], "rb");
        $signature = $file_handle
            ? fread($file_handle, 5)
            : "";

        if ($file_handle) {
            fclose($file_handle);
        }

        if ($signature !== "%PDF-") {
            $errors[] = "The uploaded file is not a valid PDF.";
        }
    }

    // Save file and database record
    if (empty($errors)) {

        // Absolute path to CampusMate/uploads/notes
        $upload_dir = dirname(__DIR__) . "/uploads/notes/";

        // Create folder only if it does not already exist
        if (!is_dir($upload_dir)) {

            if (!mkdir($upload_dir, 0755, true) &&
                !is_dir($upload_dir)) {

                $errors[] = "Could not create upload folder. "
                    . "Expected path: " . $upload_dir;
            }
        }

        // Check write permission
        if (empty($errors) && !is_writable($upload_dir)) {
            $errors[] = "Upload folder is not writable by PHP. "
                . "Folder: " . $upload_dir;
        }

        if (empty($errors)) {

            // Generate a random filename
            $filename = bin2hex(random_bytes(16)) . ".pdf";

            $file_path = "uploads/notes/" . $filename;
            $destination = $upload_dir . $filename;

            if (!move_uploaded_file(
                $uploaded_file["tmp_name"],
                $destination
            )) {

                $errors[] = "Could not move the uploaded PDF "
                    . "into the notes folder.";

            } else {

                // Insert note details using a prepared statement
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO notes
                        (title, description, file_path,
                         student_db_id, course_id)
                     VALUES (?, ?, ?, ?, ?)"
                );

                if (!$stmt) {

                    $errors[] = "Could not prepare database query.";

                    if (file_exists($destination)) {
                        unlink($destination);
                    }

                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssii",
                        $title,
                        $description,
                        $file_path,
                        $student_db_id,
                        $course_id
                    );

                    if (mysqli_stmt_execute($stmt)) {

                        mysqli_stmt_close($stmt);

                        header("Location: upload_note.php?uploaded=1");
                        exit;

                    } else {

                        $errors[] = "Could not save note in database: "
                            . mysqli_stmt_error($stmt);

                        mysqli_stmt_close($stmt);

                        // Remove file if database insert fails
                        if (file_exists($destination)) {
                            unlink($destination);
                        }
                    }
                }
            }
        }
    }
}

// Success message after redirect
if (isset($_GET["uploaded"]) && $_GET["uploaded"] === "1") {
    $success = "Your note was uploaded successfully!";
}

// Escape output
function escape_html($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<?php include "../includes/header.php"; ?>

<style>
    .upload-page {
        max-width: 720px;
        margin: 50px auto;
        padding: 30px;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }

    .upload-page h1 {
        margin-bottom: 10px;
    }

    .upload-description {
        color: #666;
        margin-bottom: 25px;
    }

    .upload-form .form-group {
        margin-bottom: 20px;
    }

    .upload-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .upload-form input,
    .upload-form textarea,
    .upload-form select {
        width: 100%;
        padding: 12px;
        border: 1px solid #d5d5e0;
        border-radius: 8px;
        font: inherit;
        box-sizing: border-box;
    }

    .upload-form textarea {
        min-height: 120px;
        resize: vertical;
    }

    .upload-form input[type="file"] {
        background: #f8f8fc;
    }

    .upload-submit {
        padding: 12px 22px;
        border: none;
        border-radius: 8px;
        background: #5145cd;
        color: white;
        font-size: 16px;
        cursor: pointer;
    }

    .upload-submit:hover {
        background: #4035b5;
    }

    .upload-message {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        overflow-wrap: anywhere;
    }

    .upload-success {
        background: #e8f8ee;
        color: #176534;
    }

    .upload-error {
        background: #fff0f0;
        color: #a51f1f;
    }

    .upload-error ul {
        margin: 5px 0 0 20px;
    }

    .upload-hint {
        display: block;
        color: #777;
        font-size: 13px;
        margin-top: 6px;
    }
</style>

<main class="container">

    <section class="upload-page">

        <h1>Upload Study Notes</h1>

        <p class="upload-description">
            Share your study materials with other students.
        </p>

        <?php if ($success !== ""): ?>
            <div class="upload-message upload-success">
                <?= escape_html($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="upload-message upload-error">
                <strong>Please fix the following:</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape_html($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>

            <p>
                No courses are available yet. Please add courses
                to the database before uploading notes.
            </p>

        <?php else: ?>

            <form
                class="upload-form"
                method="POST"
                action="upload_note.php"
                enctype="multipart/form-data"
            >

                <div class="form-group">
                    <label for="title">Note Title</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="200"
                        value="<?= escape_html($title) ?>"
                        placeholder="e.g. Java Programming Notes"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe what these notes cover..."
                    ><?= escape_html($description) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="course_id">Select Course</label>

                    <select
                        id="course_id"
                        name="course_id"
                        required
                    >
                        <option value="">Choose a course</option>

                        <?php foreach ($courses as $course): ?>
                            <option
                                value="<?= (int) $course["course_id"] ?>"
                                <?= $course_id === (int) $course["course_id"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= escape_html(
                                    $course["course_code"] . " - " .
                                    $course["course_name"]
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="note_file">PDF File</label>

                    <input
                        type="file"
                        id="note_file"
                        name="note_file"
                        accept=".pdf,application/pdf"
                        required
                    >

                    <small class="upload-hint">
                        PDF only. Maximum file size: 5 MB.
                    </small>
                </div>

                <button type="submit" class="upload-submit">
                    Upload Note
                </button>

            </form>

        <?php endif; ?>

    </section>

</main>

<?php include "../includes/footer.php"; ?>

