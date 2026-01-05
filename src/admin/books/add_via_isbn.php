<?php
session_start();
include '../../db.php'; // Verbindung zur Datenbank herstellen

// Initialisierung der Variablen
$bookData = null;
$errorMessage = null;
$successMessage = null;
$isbnInput = '';   // Variable für das ISBN-Feld
$barcodeInput = ''; // Variable für das Barcode-Feld

// Flow: Buch suchen (wenn "Buch suchen" gesendet wurde)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['add_book'])) {
    if (!empty($_POST['isbn'])) {
        $isbn = trim($_POST['isbn']);
        $bookData = searchBooks($isbn);
        $isbnInput = $isbn; // Setze die ISBN für das Formular

        // Generiere den ersten Barcode
        $barcodeInput = $isbn . '_001';
        
        // Überprüfe, ob der Barcode bereits vorhanden ist
        if (checkBarcodeExists($conn, $barcodeInput)) {
            for ($i = 2; $i <= 999; $i++) {
                $newBarcode = $isbn . '_' . str_pad($i, 3, '0', STR_PAD_LEFT);
                if (!checkBarcodeExists($conn, $newBarcode)) {
                    $barcodeInput = $newBarcode; // Setze den ersten verfügbaren Barcode
                    break;
                }
            }
            $errorMessage = "Der Barcode ist bereits vorhanden. Vorschlag: $barcodeInput";
        }
    }
}

// Flow: Buch hinzufügen (wenn "Dem Katalog hinzufügen" gesendet wurde)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_book'])) {
    // Nehme die Werte aus dem Formular (auch falls der Nutzer den Barcode manuell geändert hat)
    $isbn = trim($_POST['isbn']);
    $barcodeInput = trim($_POST['barcode']);

    // Optional: Du kannst hier auch nochmal den Buch-Scrape versuchen,
    // falls $bookData noch nicht gesetzt – z.B. durch:
    if (empty($bookData)) {
        $bookData = searchBooks($isbn);
    }
    
    // Überprüfe erneut, ob der Barcode bereits vorhanden ist, bevor das Buch hinzugefügt wird
    if (checkBarcodeExists($conn, $barcodeInput)) {
        $errorMessage = "Der Barcode ist bereits vergeben. Bitte einen anderen Barcode wählen.";
    } else {
        $stmt = $conn->prepare("INSERT INTO books (titel, erscheinungsjahr, verlag, autor, bildlink, mindestalter, ort, barcode, isbn) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", 
            $_POST['titel'], 
            $_POST['erscheinungsjahr'], 
            $_POST['verlag'], 
            $_POST['autor'], 
            $bookData['bildlink'], 
            $_POST['mindestalter'], 
            $_POST['standort'], 
            $barcodeInput, 
            $isbn
        );

        if ($stmt->execute()) {
            $successMessage = "Das Buch wurde erfolgreich hinzugefügt.";
        } else {
            $errorMessage = "Fehler beim Hinzufügen des Buches: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Funktion zur Überprüfung, ob ein Barcode bereits existiert
function checkBarcodeExists($conn, $barcode) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE barcode = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// Funktionen zum Scraping
function searchBooks($isbn) {
    $googleBooksData = searchGoogleBooks($isbn);
    return $googleBooksData; // Nur die Google Books Abfrage
}

function searchGoogleBooks($isbn) {
    $apiKey = 'AIzaSyCAaVhRLCH9PdFOiKmDZhkWZ2PmpYgDVmo'; // Dein Google API-Schlüssel
    $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn) . "&key=$apiKey";
    $response = file_get_contents($url);

    if ($response === FALSE) {
        return null; // Fehler bei der Anfrage
    }

    $data = json_decode($response, true);

    if (isset($data['items']) && count($data['items']) > 0) {
        $bookInfo = $data['items'][0]['volumeInfo'];
        return [
            'titel' => $bookInfo['title'] ?? 'Nicht verfügbar',
            'autor' => implode(', ', $bookInfo['authors'] ?? ['Nicht verfügbar']),
            'erscheinungsjahr' => $bookInfo['publishedDate'] ?? 'Nicht verfügbar',
            'verlag' => $bookInfo['publisher'] ?? 'Nicht verfügbar',
            'bildlink' => $bookInfo['imageLinks']['thumbnail'] ?? 'Nicht verfügbar',
            'mindestalter' => 'Unbekannt' // Hier kannst du eine Logik hinzufügen, um das Mindestalter abzuleiten
        ];
    }

    return null;
}

include '../../menu.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buch hinzufügen via ISBN</title>
    <link rel="stylesheet" href="../../style.css"> <!-- Verlinkung zur CSS-Datei -->
</head>
<body>

<div class="container mt-5">
    <h1>Buch hinzufügen via ISBN</h1>
    <form method="POST" action="">
        <div class="form-group">
            <label for="isbn">ISBN eingeben:</label>
            <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbnInput); ?>">
        </div>
        <button type="submit" class="button">Buch suchen</button>
    </form>

    <?php if ($errorMessage): ?>
        <div class="popup error">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="popup success">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($bookData): ?>
        <!-- Buchdetails anzeigen -->
        <h2>Buchdetails</h2>
        <div align="center">
            <img src="<?php echo htmlspecialchars($bookData['bildlink'] ?? ''); ?>" alt="Buchcover" width="10%" style="align-items:center;">
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="titel">Titel:</label>
                <input type="text" class="form-control" id="titel" name="titel" value="<?php echo htmlspecialchars($bookData['titel'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" class="form-control" id="autor" name="autor" value="<?php echo htmlspecialchars($bookData['autor'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="mindestalter">Lesealter:</label>
                <input type="text" class="form-control" id="mindestalter" name="mindestalter" value="<?php echo htmlspecialchars($bookData['mindestalter'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="erscheinungsjahr">Erscheinungsjahr:</label>
                <input type="text" class="form-control" id="erscheinungsjahr" name="erscheinungsjahr" value="<?php echo htmlspecialchars($bookData['erscheinungsjahr'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="verlag">Verlag:</label>
                <input type="text" class="form-control" id="verlag" name="verlag" value="<?php echo htmlspecialchars($bookData['verlag'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="isbn">ISBN:</label>
                <!-- Bei der Buchbearbeitung wird der ISBN-Wert aus dem Formular übernommen -->
                <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbn); ?>">
            </div>
            <div class="form-group">
                <label for="barcode">Barcode:</label>
                <!-- Der Barcode kann auch vom Nutzer angepasst werden -->
                <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo htmlspecialchars($barcodeInput); ?>">
            </div>
            <div class="form-group">
                <label for="standort">Standort:</label>
                <input type="text" class="form-control" id="standort" name="standort">
            </div>
            <button type="submit" name="add_book" class="button">Dem Katalog hinzufügen</button>
        </form>
    <?php endif; ?>
</div>

<script>
    // Zeige das Popup (error oder success) wenn vorhanden, und blende es nach 6 Sekunden aus.
    const popup = document.querySelector('.popup');
    if (popup) {
        popup.style.display = 'block';
        setTimeout(() => {
            popup.style.display = 'none';
            <?php if ($successMessage): ?>
                // Nach erfolgreichem Speichern: Weiterleitung (falls gewünscht)
                window.location.href = 'view_books.php';
            <?php endif; ?>
        }, 6000);
    }
</script>

</body>
</html>