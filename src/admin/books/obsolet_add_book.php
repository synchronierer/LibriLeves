<?php
session_start();
include('../../db.php');

// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_book'])) {
    $titel = $_POST['titel'];
    $autor = $_POST['autor'];
    $isbn = $_POST['isbn'];
    $bildlink = $_POST['bildlink'];
    $barcode = $_POST['barcode'];

    $sql = "INSERT INTO books (titel, autor, isbn, bildlink, barcode) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $titel, $autor, $isbn, $bildlink, $barcode);
    $stmt->execute();
    echo "Buch erfolgreich hinzugefügt.";
}

include '../../menu.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buch hinzufügen</title>
    <link rel="stylesheet" href="../../style.css">
</head>
<body>
    <h1>Buch hinzufügen</h1>
    <form action="" method="POST">
        <input type="text" name="titel" placeholder="Titel" required>
        <input type="text" name="autor" placeholder="Autor" required>
        <input type="text" name="isbn" placeholder="ISBN" required>
        <input type="text" name="bildlink" placeholder="Bildlink" required>
        <input type="text" name="barcode" placeholder="Barcode" required>
        <input type="submit" name="add_book" value="Buch hinzufügen">
    </form>
</body>
</html>