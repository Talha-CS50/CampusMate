<?php

session_start();

require "../config/db.php";

$message = "";
$message_type = "";


// Registration success message
if (isset($_GET["registered"])) {
    $message = "Registration successful! Please login.";
    $message_type = "success";
}


// Login processing
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = trim($_POST["student_id"]);
    $password = $_POST["password"];


    if (empty($student_id) || empty($password)) {

        $message = "Please enter your Student ID and password.";
        $message_type = "error";

    } else {

        $sql = "
            SELECT
                student_db_id,
                student_id,
                name,
                email,
                password,
                department_id,
                batch,
                semester
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $student_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 1) {

            $student = mysqli_fetch_assoc($result);


            // Verify hashed password
            if (password_verify($password, $student["password"])) {

                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION["student_db_id"] = $student["student_db_id"];
                $_SESSION["student_id"] = $student["student_id"];
                $_SESSION["student_name"] = $student["name"];
                $_SESSION["student_email"] = $student["email"];

                header("Location: dashboard.php");
                exit;

            } else {

                $message = "Incorrect Student ID or password.";
                $message_type = "error";
            }

        } else {

            $message = "Incorrect Student ID or password.";
            $message_type = "error";
        }

        mysqli_stmt_close($stmt);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Login - Campus Mate</title>

    <link rel="stylesheet"
          href="/CampusMate/assets/css/auth.css">

</head>


<body>

<div class="auth-page">

    <div class="auth-container login-container">


        <!-- Left -->

        <div class="auth-brand">

            <a href="/CampusMate/" class="brand-logo">
                <span class="logo-icon">🎓</span>
                <span>Campus Mate</span>
            </a>


            <div class="brand-content">

                <span class="brand-badge">
                    WELCOME BACK
                </span>

                <h1>
                    Everything you need,
                    all in one place.
                </h1>

                <p>
                    Sign in to access your academic resources,
                    campus updates, opportunities and more.
                </p>


                <div class="brand-points">

                    <div>
                        <span>✓</span>
                        Access your student profile
                    </div>

                    <div>
                        <span>✓</span>
                        Explore shared notes
                    </div>

                    <div>
                        <span>✓</span>
                        Stay updated with campus life
                    </div>

                </div>

            </div>

        </div>


        <!-- Right -->

        <div class="auth-form-section">

            <div class="auth-form-wrapper login-form-wrapper">


                <div class="mobile-logo">
                    🎓 Campus Mate
                </div>


                <div class="form-heading">

                    <span class="small-label">
                        STUDENT LOGIN
                    </span>

                    <h2>Welcome back</h2>

                    <p>
                        Sign in to continue to your account.
                    </p>

                </div>


                <?php if (!empty($message)): ?>

                    <div class="alert <?= $message_type ?>">

                        <?= htmlspecialchars($message) ?>

                    </div>

                <?php endif; ?>


                <form method="POST"
                      action=""
                      class="auth-form">


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

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >

                    </div>


                    <button type="submit"
                            class="auth-button">

                        Sign In
                        <span>→</span>

                    </button>

                </form>


                <p class="switch-auth">

                    Don't have an account?

                    <a href="register.php">
                        Create account
                    </a>

                </p>


            </div>

        </div>

    </div>

</div>

</body>
</html>