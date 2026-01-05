<?php
session_start();
include('../../db.php');

// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

if (isset($_GET['book_id'])) {
    $bookId = intval($_GET['book_id']);
    $sql = "DELETE FROM books WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookId);
    if ($stmt->execute()) {
        $_SESSION['deletesuccess'] = "Buch erfolgreich gelöscht.";
    } else {
        $_SESSION['deleteerror'] = "Fehler beim Löschen des Buches: " . $conn->error;
    }
    $stmt->close();
}

header("Location: view_books.php");
exit();
?>