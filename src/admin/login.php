<?php
session_start();
include('../db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // SQL-Abfrage zur Auswahl des Benutzers mit der angegebenen E-Mail
    $sql = "SELECT * FROM users WHERE email = ? AND benutzertyp = 'admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Überprüfen, ob das eingegebene Passwort mit dem in der Datenbank gespeicherten Hash übereinstimmt
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['benutzertyp']; // Benutzertyp speichern

            // Leite zurück zur vorherigen Seite oder zur Dashboard-Seite, wenn keine vorhanden ist
            $redirectUrl = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'dashboard.php';
            unset($_SESSION['redirect_after_login']); // Entferne die URL aus der Sitzung
            header("Location: $redirectUrl");
            exit();
        } else {
            echo "Ungültiges Passwort.";
        }
    } else {
        echo "Benutzer nicht gefunden.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Büchereiverwaltung</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../menu.php'; ?>

    <h2>Admin Login</h2>
    <form action="" method="POST">
        <input type="email" name="email" placeholder="E-Mail" required>
        <input type="password" name="password" placeholder="Passwort" required>
        <input type="submit" value="Einloggen">
    </form>
</body>
</html>

