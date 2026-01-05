<?php
// update.php

include '../../db.php'; // Verbindung zur Datenbank herstellen

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["id"]) && isset($_POST["bildlink"])) {
        $id       = $_POST["id"];
        $bildlink = $_POST["bildlink"];
        
        
        
        $conn = new mysqli($host, $user, $password, $dbname);
        if($conn->connect_error) {
            die("Verbindung fehlgeschlagen: " . $conn->connect_error);
        }
        
        // Prepared Statement, um den Coverlink zu speichern
        $stmt = $conn->prepare("UPDATE books SET bildlink = ? WHERE id = ?");
        $stmt->bind_param("si", $bildlink, $id);
        if ($stmt->execute()) {
            echo "OK";
        } else {
            echo "Fehler beim Update.";
        }
        $stmt->close();
        $conn->close();
    } else {
        echo "Ungültige Eingabe.";
    }
} else {
    echo "Ungültige Anfrage.";
}
?>
