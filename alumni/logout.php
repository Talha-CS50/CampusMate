<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['alumni_db_id'],
    $_SESSION['alumni_id'],
    $_SESSION['alumni_name'],
    $_SESSION['alumni_email']
);

header('Location: /CampusMate/');
exit;
