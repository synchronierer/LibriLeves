<?php
$servername = "db";
$username   = "bibadmin";
$password   = "bibadmin";
$dbname     = "leseecke";

// Verbindung zur Datenbank herstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// Zeichensatz setzen (wichtig für Umlaute/Emojis)
if (!$conn->set_charset("utf8mb4")) {
    // kein harter Abbruch, aber Hinweis
    error_log("Konnte Zeichensatz nicht setzen: " . $conn->error);
}
