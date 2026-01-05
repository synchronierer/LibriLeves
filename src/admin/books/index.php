<?php
session_start();
include('../../db.php');

// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit();
}

include '../../menu.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Bücherverwaltung</title>
    <link rel="stylesheet" href="../../style.css">
</head>
<body>
    <h1>Bücherverwaltung</h1>
    
    <div class="button-container">
        <a href="add_via_isbn.php" class="button">Buch hinzufügen</a>
        <a href="view_books.php" class="button">Bestand pflegen</a>
		  <a href="search_books.php" class="button">Automatische Nachrecherche</a>
    </div>
</body>
</html>