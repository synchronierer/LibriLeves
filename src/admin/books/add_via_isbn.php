<?php
session_start();
include '../../db.php';
require_once __DIR__ . '/../../includes/isbn.php';
require_once __DIR__ . '/../../includes/covers.php';

$bookData = null;
$errorMessage = null;
$successMessage = null;
$isbnInput = '';
$barcodeInput = '';
$candidates = [];
$selectedCover = '';
$saveLocal = true;

// Buch suchen
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['add_book'])) {
    if (!empty($_POST['isbn'])) {
        $isbn = isbn_clean($_POST['isbn']);
        $isbnInput = $isbn;

        // Metadaten
        $bookData = searchBooksMeta($isbn);

        // 1) Kandidaten über ISBN
        $candidates = cover_candidates_for_isbn($isbn);

        // 2) Fallback: über Titel/Autor, falls ISBN nichts brachte
        if (empty($candidates) && !empty($bookData['titel'])) {
            $candidates = cover_candidates_for_meta($bookData['titel'] ?? '', $bookData['autor'] ?? '');
        }

        // Barcode-Vorschlag
        $barcodeInput = $isbn . '_001';
        if (checkBarcodeExists($conn, $barcodeInput)) {
            for ($i=2; $i<=999; $i++) {
                $nb = $isbn . '_' . str_pad($i, 3, '0', STR_PAD_LEFT);
                if (!checkBarcodeExists($conn, $nb)) { $barcodeInput = $nb; break; }
            }
            $errorMessage = "Der vorgeschlagene Barcode war bereits vergeben. Neuer Vorschlag: $barcodeInput";
        }
    }
}

// Buch speichern
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_book'])) {
    $isbn = isbn_clean($_POST['isbn']);
    $barcodeInput = trim($_POST['barcode']);
    $saveLocal = isset($_POST['save_local']);
    $selectedCover = trim($_POST['selected_cover'] ?? '');

    if (empty($bookData)) $bookData = searchBooksMeta($isbn);

    // finalen Bildlink bestimmen
    $bildlink = $bookData['bildlink'] ?? '';
    if ($selectedCover !== '') {
        if ($saveLocal) {
            $local = download_cover_locally($selectedCover, $isbn ?: ($bookData['titel'] ?? 'book'));
            if ($local) $bildlink = $local; else $bildlink = $selectedCover;
        } else $bildlink = $selectedCover;
    } else {
        // Fallback: nimm besten Kandidaten
        $c = cover_candidates_for_isbn($isbn);
        if (empty($c) && !empty($bookData['titel'])) {
            $c = cover_candidates_for_meta($bookData['titel'] ?? '', $bookData['autor'] ?? '');
        }
        if (!empty($c)) {
            $first = $c[0]['url'];
            if ($saveLocal) {
                $local = download_cover_locally($first, $isbn ?: ($bookData['titel'] ?? 'book'));
                $bildlink = $local ?: $first;
            } else $bildlink = $first;
        }
    }

    if (checkBarcodeExists($conn, $barcodeInput)) {
        $errorMessage = "Der Barcode ist bereits vergeben. Bitte einen anderen Barcode wählen.";
    } else {
        $stmt = $conn->prepare("INSERT INTO books (titel, erscheinungsjahr, verlag, autor, bildlink, mindestalter, ort, barcode, isbn) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss",
            $_POST['titel'], $_POST['erscheinungsjahr'], $_POST['verlag'], $_POST['autor'],
            $bildlink, $_POST['mindestalter'], $_POST['standort'], $barcodeInput, $isbn
        );
        if ($stmt->execute()) $successMessage = "Das Buch wurde erfolgreich hinzugefügt.";
        else $errorMessage = "Fehler beim Hinzufügen des Buches: " . $stmt->error;
        $stmt->close();
    }
}

// Helpers
function checkBarcodeExists($conn, $barcode) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE barcode = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch(); $stmt->close();
    return $count > 0;
}
function searchBooksMeta($isbn) {
    $apiKey = getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn);
    if ($apiKey) $url .= "&key=$apiKey";
    $response = @file_get_contents($url);
    if ($response === FALSE) return ['mindestalter' => 'Unbekannt'];
    $data = json_decode($response, true);
    if (!empty($data['items'][0]['volumeInfo'])) {
        $vi = $data['items'][0]['volumeInfo'];
        return [
            'titel' => $vi['title'] ?? 'Nicht verfügbar',
            'autor' => implode(', ', $vi['authors'] ?? ['Nicht verfügbar']),
            'erscheinungsjahr' => $vi['publishedDate'] ?? 'Nicht verfügbar',
            'verlag' => $vi['publisher'] ?? 'Nicht verfügbar',
            'bildlink' => $vi['imageLinks']['thumbnail'] ?? '',
            'mindestalter' => 'Unbekannt'
        ];
    }
    return ['mindestalter' => 'Unbekannt'];
}

include '../../menu.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buch hinzufügen via ISBN</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
      .cover-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap:12px; margin: 10px 0 6px; }
      .cover-card{ border:1px solid var(--border); border-radius:8px; padding:8px; text-align:center; background:#fff; }
      .cover-card img{ max-width:100%; max-height:160px; display:block; margin:0 auto 6px; }
      .muted{ color: var(--ink-muted); font-size: .9em; }
      .inline{ display:inline-flex; gap:8px; align-items:center; }
    </style>
</head>
<body>
<div class="container-card">
    <h1>Buch hinzufügen via ISBN</h1>

    <form method="POST" action="" onsubmit="showBusy('Suche Buchdaten…');">
        <div class="form-group">
            <label for="isbn">ISBN eingeben:</label>
            <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbnInput); ?>">
        </div>
        <input type="submit" class="button" value="Buch suchen">
    </form>

    <?php if ($errorMessage): ?>
        <div class="popup error" style="display:block;"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="popup success" style="display:block;"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($bookData): ?>
        <h2>Buchdetails</h2>
        <form method="POST" action="" onsubmit="showBusy('Speichere Buch…');">
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
                <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbnInput); ?>">
            </div>

            <h3>Cover-Vorschläge</h3>
            <?php if (!empty($candidates)): ?>
                <div class="cover-grid">
                  <?php foreach ($candidates as $i => $c): ?>
                    <label class="cover-card">
                      <img src="<?php echo htmlspecialchars($c['url']); ?>" alt="Cover">
                      <div class="muted"><?php echo htmlspecialchars($c['source']); ?></div>
                      <div class="inline">
                        <input type="radio" name="selected_cover" value="<?php echo htmlspecialchars($c['url']); ?>" <?php echo $i===0?'checked':''; ?>>
                        <span>verwenden</span>
                      </div>
                    </label>
                  <?php endforeach; ?>
                </div>
                <label class="inline"><input type="checkbox" name="save_local" <?php echo $saveLocal ? 'checked' : ''; ?>> Cover lokal speichern (empfohlen)</label>
            <?php else: ?>
                <p class="muted">Keine externen Cover gefunden. Das Buch kann trotzdem gespeichert werden.</p>
            <?php endif; ?>

            <div class="form-group">
                <label for="barcode">Barcode:</label>
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
  // Popup auto-hide
  setTimeout(()=>{ document.querySelectorAll('.popup').forEach(p=>p.style.display='none'); }, 6000);
</script>
</body>
</html>
