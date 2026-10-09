
<?php
session_start();

// Check whether the student is logged in
if (!isset($_SESSION["student_db_id"])) {
    header("Location: login.php");
    exit;
}

require "../config/db.php";

$student_db_id = $_SESSION["student_db_id"];
$error = "";

// Fetch current student information
$sql = "
    SELECT
        student_id,
        name,
        email,
        department_id,
        batch,
        semester
    FROM students
    WHERE student_db_id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_db_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$student) {
    exit("Student not found.");
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_profile"])) {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department_input = $_POST["department_id"] ?? "";
    $batch = trim($_POST["batch"] ?? "");
    $semester = trim($_POST["semester"] ?? "");

    // Validate name and email
    if ($name === "" || $email === "") {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (
        $department_input !== ""
        && (
            !ctype_digit((string) $department_input)
            || (int) $department_input < 1
        )
    ) {
        $error = "Please select a valid department.";
    }

    // Validate department selection against the database
    $department_id = null;

    if ($error === "" && $department_input !== "") {
        $department_id = (int) $department_input;

        $dept_stmt = mysqli_prepare(
            $conn,
            "SELECT department_id FROM departments WHERE department_id = ?"
        );

        mysqli_stmt_bind_param($dept_stmt, "i", $department_id);
        mysqli_stmt_execute($dept_stmt);

        $dept_result = mysqli_stmt_get_result($dept_stmt);

        if (mysqli_num_rows($dept_result) === 0) {
            $error = "Selected department does not exist.";
        }

        mysqli_stmt_close($dept_stmt);
    }

    // Check whether another student uses this email
    if ($error === "") {
        $check_sql = "
            SELECT student_db_id
            FROM students
            WHERE email = ? AND student_db_id != ?
        ";

        $check_stmt = mysqli_prepare($conn, $check_sql);

        mysqli_stmt_bind_param(
            $check_stmt,
            "si",
            $email,
            $student_db_id
        );

        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "This email is already used by another student.";
        }

        mysqli_stmt_close($check_stmt);
    }

    // Update student information
    if ($error === "") {
        $update_sql = "
            UPDATE students
            SET
                name = ?,
                email = ?,
                department_id = ?,
                batch = ?,
                semester = ?
            WHERE student_db_id = ?
        ";

        $update_stmt = mysqli_prepare($conn, $update_sql);

        mysqli_stmt_bind_param(
            $update_stmt,
            "ssissi",
            $name,
            $email,
            $department_id,
            $batch,
            $semester,
            $student_db_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION["student_name"] = $name;
            $_SESSION["student_email"] = $email;

            mysqli_stmt_close($update_stmt);
            mysqli_close($conn);

            header("Location: profile.php?updated=1");
            exit;
        } else {
            $error = "Could not update profile. Please try again.";
        }

        mysqli_stmt_close($update_stmt);
    }

    // Preserve submitted values if validation fails
    $student["name"] = $name;
    $student["email"] = $email;
    $student["department_id"] = $department_input;
    $student["batch"] = $batch;
    $student["semester"] = $semester;
}

// Fetch departments for the dropdown
$departments = mysqli_query(
    $conn,
    "SELECT department_id, department_name
     FROM departments
     ORDER BY department_name"
);

if (!$departments) {
    exit("Could not load departments.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile | CampusMate</title>

    <link rel="stylesheet" href="/CampusMate/assets/css/profile.css">
    <link rel="stylesheet" href="/CampusMate/assets/css/auth.css">

    <style>
        .form-error {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 8px;
            background: #fff0f0;
            color: #c62828;
            font-size: 14px;
        }

        .edit-profile-container input[readonly] {
            background: #f3f3f8;
            color: #777;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

    <div class="edit-profile-container">

        <h1>Edit Profile</h1>
        <p>Update your personal and academic information.</p>

        <?php if ($error !== ""): ?>
            <div class="form-error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form action="edit_profile.php" method="POST">

            <label for="student_id">Student ID</label>
            <input
                type="text"
                id="student_id"
                value="<?= htmlspecialchars($student["student_id"], ENT_QUOTES, "UTF-8") ?>"
                readonly
            >

            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($student["email"], ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="name">Full Name</label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="100"
                value="<?= htmlspecialchars($student["name"], ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="department_id">Department</label>
            <select id="department_id" name="department_id">

                <option value="">Select Department</option>

                <?php while ($department = mysqli_fetch_assoc($departments)): ?>
                    <option
                        value="<?= (int) $department["department_id"] ?>"
                        <?= ((string) $student["department_id"] ===
                            (string) $department["department_id"]) ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars(
                            $department["department_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </option>
                <?php endwhile; ?>

            </select>

            <label for="batch">Batch</label>
            <input
                type="text"
                id="batch"
                name="batch"
                maxlength="20"
                value="<?= htmlspecialchars($student["batch"] ?? "", ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="semester">Semester</label>
            <input
                type="text"
                id="semester"
                name="semester"
                maxlength="20"
                value="<?= htmlspecialchars($student["semester"] ?? "", ENT_QUOTES, "UTF-8") ?>"
            >

            <button type="submit" name="update_profile">
                Save Changes
            </button>

            <a href="profile.php" class="profile-back-button">
                Cancel and Go Back
            </a>

        </form>

    </div>

</body>
</html>