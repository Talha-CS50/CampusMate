<?php

require "../config/db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = trim($_POST["student_id"]);
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $department_id = (int) $_POST["department_id"];
    $batch = trim($_POST["batch"]);
    $semester = trim($_POST["semester"]);

    // Basic validation
    if (
        empty($student_id) ||
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password) ||
        empty($department_id) ||
        empty($batch) ||
        empty($semester)
    ) {
        $message = "Please fill in all fields.";
        $message_type = "error";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    }

    elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
        $message_type = "error";
    }

    elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    }

    else {

        // Check duplicate Student ID or Email
        $check_sql = "
            SELECT student_db_id
            FROM students
            WHERE student_id = ? OR email = ?
        ";

        $check_stmt = mysqli_prepare($conn, $check_sql);

        mysqli_stmt_bind_param(
            $check_stmt,
            "ss",
            $student_id,
            $email
        );

        mysqli_stmt_execute($check_stmt);

        $result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($result) > 0) {

            $message = "Student ID or Email already exists.";
            $message_type = "error";

        } else {

            // Secure password hashing
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert student
            $insert_sql = "
                INSERT INTO students
                (
                    student_id,
                    name,
                    email,
                    password,
                    department_id,
                    batch,
                    semester
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);

            mysqli_stmt_bind_param(
                $insert_stmt,
                "ssssiss",
                $student_id,
                $name,
                $email,
                $hashed_password,
                $department_id,
                $batch,
                $semester
            );

            if (mysqli_stmt_execute($insert_stmt)) {

                header("Location: login.php?registered=1");
                exit;

            } else {

                $message = "Registration failed. Please try again.";
                $message_type = "error";
            }

            mysqli_stmt_close($insert_stmt);
        }

        mysqli_stmt_close($check_stmt);
    }
}


// Get departments
$departments = [];

$department_sql = "
    SELECT department_id, department_name
    FROM departments
    ORDER BY department_name
";

$department_result = mysqli_query($conn, $department_sql);

if ($department_result) {
    while ($row = mysqli_fetch_assoc($department_result)) {
        $departments[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Account - Campus Mate</title>

    <link rel="stylesheet"
          href="/CampusMate/assets/css/auth.css">

</head>

<body>

<div class="auth-page">

    <div class="auth-container">

        <!-- Left Side -->

        <div class="auth-brand">

            <a href="/CampusMate/" class="brand-logo">
                <span class="logo-icon">🎓</span>
                <span>Campus Mate</span>
            </a>

            <div class="brand-content">

                <span class="brand-badge">
                    YOUR CAMPUS. ONE PLATFORM.
                </span>

                <h1>
                    Your campus journey
                    starts here.
                </h1>

                <p>
                    Join Campus Mate and access academic
                    resources, important notices,
                    opportunities and campus services
                    from one place.
                </p>

                <div class="brand-points">

                    <div>
                        <span>✓</span>
                        Easy access to academic resources
                    </div>

                    <div>
                        <span>✓</span>
                        Stay updated with campus notices
                    </div>

                    <div>
                        <span>✓</span>
                        Discover career opportunities
                    </div>

                </div>

            </div>

        </div>


        <!-- Right Side -->

        <div class="auth-form-section">

            <div class="auth-form-wrapper">

                <div class="mobile-logo">
                    🎓 Campus Mate
                </div>

                <div class="form-heading">

                    <span class="small-label">
                        STUDENT ACCOUNT
                    </span>

                    <h2>Create your account</h2>

                    <p>
                        Register to get started with Campus Mate.
                    </p>

                </div>


                <?php if (!empty($message)): ?>

                    <div class="alert error">
                        <?= htmlspecialchars($message) ?>
                    </div>

                <?php endif; ?>


                <form method="POST"
                      action=""
                      class="auth-form">

                    <div class="form-row">

                        <div class="form-group">

                            <label for="student_id">
                                Student ID
                            </label>

                            <input
                                type="text"
                                id="student_id"
                                name="student_id"
                                placeholder="Enter your student ID"
                                value="<?= htmlspecialchars($_POST["student_id"] ?? "") ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                placeholder="Enter your full name"
                                value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="email">
                            University Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="department_id">
                            Department
                        </label>

                        <select
                            id="department_id"
                            name="department_id"
                            required
                        >

                            <option value="">
                                Select your department
                            </option>

                            <?php foreach ($departments as $department): ?>

                                <option
                                    value="<?= $department["department_id"] ?>"
                                    <?= (
                                        ($_POST["department_id"] ?? "") ==
                                        $department["department_id"]
                                    ) ? "selected" : "" ?>
                                >

                                    <?= htmlspecialchars(
                                        $department["department_name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-row">

                        <div class="form-group">

                            <label for="batch">
                                Batch
                            </label>

                            <input
                                type="text"
                                id="batch"
                                name="batch"
                                placeholder="e.g. 2024"
                                value="<?= htmlspecialchars($_POST["batch"] ?? "") ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="semester">
                                Semester
                            </label>

                            <input
                                type="text"
                                id="semester"
                                name="semester"
                                placeholder="e.g. 5th"
                                value="<?= htmlspecialchars($_POST["semester"] ?? "") ?>"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-row">

                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Minimum 8 characters"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="confirm_password">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Repeat your password"
                                required
                            >

                        </div>

                    </div>


                    <button type="submit"
                            class="auth-button">

                        Create Account
                        <span>→</span>

                    </button>

                </form>


                <p class="switch-auth">

                    Already have an account?

                    <a href="login.php">
                        Sign in
                    </a>

                </p>

            </div>

        </div>

    </div>

</div>

</body>
</html>