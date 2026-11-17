<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = (int)($_SESSION['student_db_id'] ?? 0);
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($studentId <= 0 && $adminId <= 0) {
    header('Location: /CampusMate/student/login.php?redirect=notes');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

$noteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$noteId || $noteId < 1) {
    http_response_code(404);
    exit('Note not found.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

$stmt = mysqli_prepare($conn, 'SELECT file_path FROM notes WHERE note_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $noteId);
mysqli_stmt_execute($stmt);
$note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$relativePath = (string)($note['file_path'] ?? '');
$expectedPrefix = 'uploads/notes/';
$filename = str_starts_with($relativePath, $expectedPrefix)
    ? substr($relativePath, strlen($expectedPrefix))
    : '';
$uploadDirectory = realpath(__DIR__ . '/uploads/notes');
$storedFile = $uploadDirectory !== false
    && $filename !== ''
    && basename($filename) === $filename
        ? realpath($uploadDirectory . DIRECTORY_SEPARATOR . $filename)
        : false;

if (
    $storedFile === false
    || dirname($storedFile) !== $uploadDirectory
    || !is_file($storedFile)
) {
    http_response_code(404);
    exit('Note not found.');
}

$disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="note-' . $noteId . '.pdf"');
header('Content-Length: ' . filesize($storedFile));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($storedFile);
exit;
