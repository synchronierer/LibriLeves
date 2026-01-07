<?php
// src/tools/bootstrap_admin.php
// Einmaliges Bootstrap-Skript zum Anlegen eines Admin-Accounts.

require_once __DIR__ . '/../db.php';

$email = $_GET['email'] ?? 'admin@example.com';
$passwordPlain = $_GET['password'] ?? 'admin123';
$name = $_GET['name'] ?? 'Admin';
$vorname = $_GET['vorname'] ?? 'Admin';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Ungültige E-Mail.";
    exit;
}

// Prüfe, ob Admin bereits existiert
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Benutzer mit E-Mail $email existiert bereits.";
    exit;
}
$stmt->close();

$hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
$benutzertyp = 'admin';

$stmt = $conn->prepare("INSERT INTO users (name, vorname, email, password, benutzertyp) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $vorname, $email, $hash, $benutzertyp);
if ($stmt->execute()) {
    echo "Admin angelegt: $email (Passwort: $passwordPlain). Bitte diese Datei danach löschen.";
} else {
    echo "Fehler: " . $stmt->error;
}
$stmt->close();
