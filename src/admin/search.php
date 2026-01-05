<?php
// Datenbankverbindung
$servername = "localhost"; // Dein Servername
$username = "root"; // Dein Datenbankbenutzername
$password = ""; // Dein Datenbankpasswort
$dbname = "leseecke"; // Dein Datenbankname

// Verbindung zur Datenbank herstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Überprüfen der Verbindung
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// Google Books API Schlüssel
$apiKey = 'xxx';

// IDs, die recherchiert werden sollen
$ids = [];

// Einzelne ID verarbeiten
if (isset($_GET['singleId']) && !empty($_GET['singleId'])) {
    $singleId = intval($_GET['singleId']);
    $ids[] = $singleId;
}

// ID-Bereich verarbeiten
if (isset($_GET['startId']) && isset($_GET['endId'])) {
    $startId = intval($_GET['startId']);
    $endId = intval($_GET['endId']);
    
    for ($id = $startId; $id <= $endId; $id++) {
        $ids[] = $id;
    }
}

// Wenn keine IDs angegeben sind, eine Fehlermeldung ausgeben
if (empty($ids)) {
    echo "Bitte geben Sie eine ID oder einen ID-Bereich ein.";
    exit;
}

// SQL-Abfrage zum Abrufen der Bücher
$idList = implode(',', array_map('intval', $ids)); // Sichere die IDs
$sql = "SELECT id, titel, autor, herausgeber FROM books WHERE id IN ($idList)";
$result = $conn->query($sql);

// HTML-Ausgabe beginnen
echo "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Bücher Recherche Ergebnisse</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Bücher Recherche Ergebnisse</h1>";

if ($result->num_rows > 0) {
    echo "<table>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Autor</th>
                <th>Herausgeber</th>
                <th>ISBN</th>
                <th>Thumbnail</th>
            </tr>";
    
    // Durchlaufe alle Bücher
    while ($row = $result->fetch_assoc()) {
        $bookId = $row['id'];
        $title = urlencode($row['titel']);
        $author = urlencode($row['autor']);
        $publisher = urlencode($row['herausgeber']);

        // Google Books API URL für die Suche
        $apiUrl = "https://www.googleapis.com/books/v1/volumes?q=intitle:$title+inauthor:$author+inpublisher:$publisher&key=$apiKey";

        // API-Anfrage
        $response = file_get_contents($apiUrl);
        $data = json_decode($response, true);

        // Initialisiere Variablen für die Ausgabe
        $isbn = "Nicht gefunden";
        $thumbnail = "Nicht verfügbar";

        // Überprüfen, ob die API ein Ergebnis zurückgegeben hat
        if (isset($data['items']) && count($data['items']) > 0) {
            // Die erste gefundene Buchinformation verwenden
            $item = $data['items'][0]['volumeInfo'];

            // ISBN und Thumbnail extrahieren
            if (isset($item['industryIdentifiers'])) {
                foreach ($item['industryIdentifiers'] as $identifier) {
                    if ($identifier['type'] === 'ISBN_13') {
                        $isbn = $identifier['identifier'];
                        break;
                    }
                }
            }

            $thumbnail = $item['imageLinks']['thumbnail'] ?? "Nicht verfügbar";
            
            // ISBN und Thumbnail in die Datenbank aktualisieren
            $updateSql = "UPDATE books SET isbn = '" . $conn->real_escape_string($isbn) . "', bildlink = '" . $conn->real_escape_string($thumbnail) . "' WHERE id = " . $bookId;
            if ($conn->query($updateSql) === TRUE) {
                echo "<tr class='success'>
                        <td>$bookId</td>
                        <td>" . htmlspecialchars($row['titel']) . "</td>
                        <td>" . htmlspecialchars($row['autor']) . "</td>
                        <td>" . htmlspecialchars($row['herausgeber']) . "</td>
                        <td>$isbn</td>
                        <td><img src='$thumbnail' alt='Thumbnail' width='50'></td>
                    </tr>";
            } else {
                echo "<tr class='error'>
                        <td>$bookId</td>
                        <td>" . htmlspecialchars($row['titel']) . "</td>
                        <td>" . htmlspecialchars($row['autor']) . "</td>
                        <td>" . htmlspecialchars($row['herausgeber']) . "</td>
                        <td colspan='2'>Fehler beim Aktualisieren: " . $conn->error . "</td>
                    </tr>";
            }
        } else {
            echo "<tr class='error'>
                    <td>$bookId</td>
                    <td>" . htmlspecialchars($row['titel']) . "</td>
                    <td>" . htmlspecialchars($row['autor']) . "</td>
                    <td>" . htmlspecialchars($row['herausgeber']) . "</td>
                    <td colspan='2'>Keine Ergebnisse gefunden</td>
                </tr>";
        }
    }

    echo "</table>";
} else {
    echo "<p>Keine Bücher gefunden.</p>";
}

// Link zur Katalogisierungsseite anbieten
echo "<p><a href='buecherverwaltung.php'>Zurück zur Katalogisierung</a></p>";

// Verbindung schließen
$conn->close();

echo "</body>
</html>";

?>
