<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$counts = [];
foreach ([
    'Students' => 'students',
    'Study notes' => 'notes',
    'Notices' => 'notices',
    'Opportunities' => 'opportunities',
    'Campus issues' => 'campus_issues',
] as $label => $table) {
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");
    $counts[$label] = (int) mysqli_fetch_assoc($result)['total'];
}

$adminName = htmlspecialchars(
    (string)($_SESSION['admin_name'] ?? 'Admin'),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CampusMate</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f6fb;
            color: #243047;
            font-family: Arial, sans-serif;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px max(5%, calc((100% - 1120px) / 2));
            background: #fff;
            border-bottom: 1px solid #e0e7f0;
        }

        .brand {
            color: #173b70;
            font-size: 20px;
            font-weight: 700;
            text-decoration: none;
        }

        .topbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .topbar-actions a {
            padding: 10px 14px;
            border-radius: 8px;
            color: #1d4ed8;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .topbar-actions a.logout {
            background: #fee2e2;
            color: #b91c1c;
        }

        main {
            width: min(1120px, 90%);
            margin: 42px auto;
        }

        .welcome {
            padding: 30px;
            border-radius: 16px;
            background: linear-gradient(120deg, #1d4ed8, #4338ca);
            color: #fff;
        }

        .welcome h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .welcome p {
            margin: 0;
            color: #dbeafe;
            line-height: 1.6;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .stat-card {
            padding: 21px;
            border: 1px solid #e0e7f0;
            border-radius: 13px;
            background: #fff;
        }

        .stat-card span {
            display: block;
            color: #64748b;
            font-size: 14px;
        }

        .stat-card strong {
            display: block;
            margin-top: 9px;
            color: #173b70;
            font-size: 27px;
        }

        @media (max-width: 520px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            main {
                margin: 25px auto;
            }

            .welcome {
                padding: 23px;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="../index.php">CampusMate Admin</a>
        <nav class="topbar-actions" aria-label="Admin navigation">
            <a href="../index.php">View website</a>
            <a class="logout" href="logout.php">Logout</a>
        </nav>
    </header>

    <main>
        <section class="welcome">
            <h1>Welcome, <?= $adminName ?>!</h1>
            <p>You are signed in to the CampusMate admin dashboard.</p>
        </section>

        <section class="stats" aria-label="Platform totals">
            <?php foreach ($counts as $label => $count): ?>
                <article class="stat-card">
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= $count ?></strong>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>