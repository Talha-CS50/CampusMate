<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../config/db.php';


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = "";
$email = "";


/*
|--------------------------------------------------------------------------
| Handle Login Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    /*
    |----------------------------------------------------------------------
    | Basic Validation
    |----------------------------------------------------------------------
    */

    if ($email === '' || $password === '') {

        $error = "Email and password are required.";

    } else {

        /*
        |------------------------------------------------------------------
        | Find Admin by Email
        |------------------------------------------------------------------
        */

        $sql = "SELECT
                    admin_id,
                    name,
                    email,
                    password
                FROM admins
                WHERE email = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $email
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $admin = mysqli_fetch_assoc($result);


        /*
        |------------------------------------------------------------------
        | Verify Password
        |------------------------------------------------------------------
        */

        if (
            $admin &&
            password_verify($password, $admin['password'])
        ) {

            /*
            |--------------------------------------------------------------
            | Create Admin Session
            |--------------------------------------------------------------
            */

            session_regenerate_id(true);

            $_SESSION['admin_id'] = (int)$admin['admin_id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];


            /*
            |--------------------------------------------------------------
            | Redirect to Dashboard
            |--------------------------------------------------------------
            */

            header("Location: dashboard.php");
            exit();

        } else {

            $error = "Invalid email or password.";
        }

        mysqli_stmt_close($stmt);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Login | CampusMate</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #243047;
        }

        .login-container {
            width: 100%;
            max-width: 430px;
        }

        .brand {
            text-align: center;
            margin-bottom: 22px;
        }

        .brand h1 {
            margin: 0 0 8px;
            color: #173b70;
            font-size: 32px;
        }

        .brand p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .login-card {
            padding: 30px;
            background: #ffffff;
            border: 1px solid #e0e7f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(20, 45, 80, 0.08);
        }

        .login-card h2 {
            margin: 0 0 8px;
            color: #173b70;
        }

        .login-card > p {
            margin: 0 0 22px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
            outline: none;
        }

        input:focus {
            border-color: #1d4ed8;
        }

        .btn-login {
            width: 100%;
            margin-top: 5px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #1d4ed8;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-login:hover {
            background: #173b70;
        }

        .error-message {
            margin-bottom: 16px;
            padding: 11px 13px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: #b91c1c;
            font-size: 13px;
        }

        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #1d4ed8;
            font-size: 14px;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="brand">
        <h1>🎓 CampusMate</h1>
        <p>Administrator Portal</p>
    </div>


    <div class="login-card">

        <h2>Admin Login</h2>

        <p>
            Sign in to manage notices, opportunities,
            and campus issues.
        </p>


        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars(
                        $email,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                    required
                >

            </div>


            <button
                type="submit"
                class="btn-login"
            >
                Login
            </button>

        </form>


        <a
            href="../index.php"
            class="back-link"
        >
            ← Back to CampusMate
        </a>

    </div>

</div>

</body>
</html>