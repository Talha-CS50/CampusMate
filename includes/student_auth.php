<?php

function campusMateStudentRedirects(): array
{
    return [
        'dashboard' => 'dashboard.php',
        'notes' => 'notes.php',
        'notices' => 'notices.php',
        'upload-note' => 'upload_note.php',
        'profile' => 'profile.php',
        'opportunities' => '../index.php#opportunities',
        'campus-issues' => '../index.php#features',
    ];
}

function campusMateStudentLoginUrl(string $destination = 'dashboard'): string
{
    $destinations = campusMateStudentRedirects();
    if (!array_key_exists($destination, $destinations)) {
        $destination = 'dashboard';
    }

    return '/CampusMate/student/login.php?redirect=' . rawurlencode($destination);
}

function campusMateStudentRedirectTarget(string $destination): string
{
    $destinations = campusMateStudentRedirects();
    return $destinations[$destination] ?? $destinations['dashboard'];
}

function campusMateHasAccess(): bool
{
    return (int)($_SESSION['student_db_id'] ?? 0) > 0
        || (int)($_SESSION['admin_id'] ?? 0) > 0;
}

function campusMateRequireAccess(string $destination): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!campusMateHasAccess()) {
        header('Location: ' . campusMateStudentLoginUrl($destination));
        exit;
    }
}

function campusMateRequireStudent(string $destination): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ((int)($_SESSION['student_db_id'] ?? 0) <= 0) {
        if ((int)($_SESSION['admin_id'] ?? 0) > 0) {
            header('Location: /CampusMate/admin/dashboard.php');
            exit;
        }

        header('Location: ' . campusMateStudentLoginUrl($destination));
        exit;
    }
}
