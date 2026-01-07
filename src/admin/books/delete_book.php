<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['deleteerror'] = "Ungültige Anfrage.";
    header("Location: view_books.php");
    exit();
}

if (!csrf_validate($_POST['csrf'] ?? null)) {
    $_SESSION['deleteerror'] = "CSRF-Prüfung fehlgeschlagen.";
    header("Location: view_books.php");
    exit();
}

$bookId = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
if ($bookId <= 0) {
    $_SESSION['deleteerror'] = "Fehlende Buch-ID.";
    header("Location: view_books.php");
    exit();
}

$sql = "DELETE FROM books WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookId);
if ($stmt->execute()) {
    $_SESSION['deletesuccess'] = "Buch erfolgreich gelöscht.";
} else {
    $_SESSION['deleteerror'] = "Fehler beim Löschen des Buches: " . $conn->error;
}
$stmt->close();

header("Location: view_books.php");
exit();
