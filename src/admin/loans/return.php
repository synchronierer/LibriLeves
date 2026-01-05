<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
include('../../db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loan_id = $_POST['loan_id'];

    // Daten aus der loans-Tabelle abrufen
    $sql = "SELECT user_id, book_id, loan_date FROM loans WHERE loan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $loan = $result->fetch_assoc();

        // Daten in die historie-Tabelle einfügen
        $insertSql = "INSERT INTO historie (loan_id, user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("iiis", $loan_id, $loan['user_id'], $loan['book_id'], $loan['loan_date']);
        $insertStmt->execute();

        // Zeile aus der loans-Tabelle löschen
        $deleteSql = "DELETE FROM loans WHERE loan_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $loan_id);
        $deleteStmt->execute();

        // Erfolgreiche Rückgabe-Meldung in der Session speichern
        $_SESSION['message'] = "Ausleihe erfolgreich beendet und archiviert.";
    } else {
        $_SESSION['message'] = "Keine Ausleihe mit dieser ID gefunden.";
    }

    // Weiterleitung zur Übersicht
    header("Location: ausleihe.php");
    exit();
}
?>