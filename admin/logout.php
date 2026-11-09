<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie['path'],
        $cookie['domain'],
        $cookie['secure'],
        $cookie['httponly']
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out | CampusMate Admin</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: 22px;
            place-items: center;
            background:
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, .14), transparent 32%),
                radial-gradient(circle at 85% 85%, rgba(14, 165, 233, .12), transparent 30%),
                #f3f6fb;
            color: #243047;
            font-family: Arial, sans-serif;
        }

        .logout-card {
            width: min(100%, 460px);
            padding: 42px 34px;
            border: 1px solid #e0e7f0;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 20px 55px rgba(23, 59, 112, .12);
            text-align: center;
        }

        .logout-icon {
            display: grid;
            width: 68px;
            height: 68px;
            margin: 0 auto 20px;
            place-items: center;
            border-radius: 20px;
            background: #dcfce7;
            color: #15803d;
            font-size: 32px;
        }

        h1 {
            margin: 0 0 10px;
            color: #173b70;
            font-size: 27px;
        }

        p {
            margin: 0;
            color: #64748b;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 26px;
        }

        .button {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            padding: 0 17px;
            border: 1px solid transparent;
            border-radius: 9px;
            background: linear-gradient(120deg, #4f46e5, #7c3aed);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(79, 70, 229, .22);
        }

        .button-secondary {
            border-color: #dbe3ef;
            background: #fff;
            color: #334155;
        }

        @media (max-width: 420px) {
            .logout-card {
                padding: 34px 22px;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <main class="logout-card">
        <div class="logout-icon" aria-hidden="true">✓</div>
        <h1>Logged out successfully</h1>
        <p>Your admin session has ended. You can return to CampusMate or sign in again.</p>
        <div class="actions">
            <a class="button" href="../index.php">Go to Home</a>
            <a class="button button-secondary" href="login.php">Admin Login</a>
        </div>
    </main>
</body>
</html>