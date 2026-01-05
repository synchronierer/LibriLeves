<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $book_data = json_decode($_POST['book_data'], true);
    $mediennummer = $_POST['mediennummer'];
    $isbn = $_POST['isbn'];

    // Datenbankverbindung herstellen
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "leseecke";

    // Verbindung erstellen
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verbindung prüfen
    if ($conn->connect_error) {
        die("Verbindung fehlgeschlagen: " . $conn->connect_error);
    }

    // Überprüfen, ob die Mediennummer bereits existiert
    $check_sql = "SELECT * FROM books WHERE mediennummer = '$mediennummer'";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        echo "Fehler: Die Mediennummer ist bereits vergeben. Bitte wählen Sie eine andere.";
    } else {
        // SQL-Abfrage zum Einfügen der Buchdaten
        $titel = $conn->real_escape_string($book_data['title']);
        $autor = implode(", ", $book_data['authors']);
        $bildlink = $conn->real_escape_string($book_data['bildlink']);

        $sql = "INSERT INTO books (titel, autor, isbn, bildlink, mediennummer)
                VALUES ('$titel', '$autor', '$isbn', '$bildlink', '$mediennummer')";

        if ($conn->query($sql) === TRUE) {
            echo "Buch erfolgreich hinzugefügt.";
        } else {
            echo "Fehler beim Hinzufügen des Buches: " . $conn->error;
        }
    }

    // Verbindung schließen
    $conn->close();
}
?>
