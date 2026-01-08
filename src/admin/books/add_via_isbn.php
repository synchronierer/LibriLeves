<?php
// src/admin/books/add_via_isbn.php
session_start();
include '../../db.php';

// Optional: gemeinsame Helfer laden (falls vorhanden)
@require_once __DIR__ . '/../../includes/isbn.php';
@require_once __DIR__ . '/../../includes/covers.php';
@require_once __DIR__ . '/../../includes/age.php';

/* --------------------------
   Fallbacks, wenn includes/… fehlen
   -------------------------- */

// ISBN reinigen (nur Ziffern/X) – einfacher Fallback
if (!function_exists('isbn_clean')) {
    function isbn_clean($s) { return strtoupper(preg_replace('/[^0-9Xx]/', '', (string)$s)); }
}

// Google Books Cover
if (!function_exists('cover_from_google_books')) {
    function cover_from_google_books($isbn) {
        $key = getenv('GOOGLE_BOOKS_API_KEY') ?: '';
        $u = "https://www.googleapis.com/books/v1/volumes?q=isbn=" . urlencode($isbn);
        if ($key) $u .= "&key={$key}";
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $json = @file_get_contents($u, false, $ctx);
        if (!$json) return null;
        $data = @json_decode($json, true);
        $vi = $data['items'][0]['volumeInfo'] ?? null;
        if (!$vi) return null;
        $img = $vi['imageLinks']['thumbnail'] ?? ($vi['imageLinks']['smallThumbnail'] ?? null);
        return $img ?: null;
    }
}

// Open Library Cover per ISBN
if (!function_exists('cover_from_openlibrary_isbn')) {
    function cover_from_openlibrary_isbn($isbn) {
        $u = "https://covers.openlibrary.org/b/isbn/" . urlencode($isbn) . "-L.jpg?default=false";
        $h = @get_headers($u, 1);
        if (!$h || strpos($h[0] ?? '', '200') === false) return null;
        return $u;
    }
}

// Kandidaten via ISBN
if (!function_exists('cover_candidates_for_isbn')) {
    function cover_candidates_for_isbn($isbn, $limit = 12) {
        $cands = [];
        $g = cover_from_google_books($isbn);
        if ($g) $cands[] = ['url' => $g, 'source' => 'Google Books'];
        $ol = cover_from_openlibrary_isbn($isbn);
        if ($ol) $cands[] = ['url' => $ol, 'source' => 'Open Library'];
        // Deduplizieren/Limit
        $out = []; $seen = [];
        foreach ($cands as $c) {
            if (!isset($seen[$c['url']])) { $seen[$c['url']] = true; $out[] = $c; }
            if (count($out) >= $limit) break;
        }
        return $out;
    }
}

// Kandidaten via Titel/Autor (Fallback)
if (!function_exists('cover_candidates_for_meta')) {
    function cover_candidates_for_meta($title, $author = '', $limit = 12) {
        $out = [];
        // Grober OpenLibrary-Search-Fallback
        $q = urlencode(trim($title . ' ' . $author));
        $u = "https://openlibrary.org/search.json?q={$q}&limit=5";
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $json = @file_get_contents($u, false, $ctx);
        if ($json) {
            $data = @json_decode($json, true);
            if (!empty($data['docs'])) {
                foreach ($data['docs'] as $doc) {
                    if (!empty($doc['isbn'][0])) {
                        $cand = cover_from_openlibrary_isbn($doc['isbn'][0]);
                        if ($cand) $out[] = ['url' => $cand, 'source' => 'Open Library (search)'];
                    }
                    if (count($out) >= $limit) break;
                }
            }
        }
        // Deduplizieren/Limit
        $seen = []; $res = [];
        foreach ($out as $c) {
            if (!isset($seen[$c['url']])) { $seen[$c['url']] = true; $res[] = $c; }
            if (count($res) >= $limit) break;
        }
        return $res;
    }
}

// Cover lokal speichern
if (!function_exists('download_cover_locally')) {
    function download_cover_locally($url, $nameBase = 'book') {
        $dirFs = realpath(__DIR__ . '/../../') . '/media/covers'; // FS-Pfad
        if (!is_dir($dirFs)) @mkdir($dirFs, 0775, true);
        $ext = '.jpg';
        $pathPart = parse_url($url, PHP_URL_PATH) ?: '';
        if (preg_match('/\.(png|jpe?g|webp)(\?|$)/i', $pathPart, $m)) $ext = '.' . strtolower($m[1]);
        $safe = preg_replace('/[^a-z0-9_\-]+/i', '_', $nameBase);
        $target = $dirFs . '/' . $safe . $ext;
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) return null;
        if (@file_put_contents($target, $data) === false) return null;
        // Web-Pfad (ab DocRoot)
        return "/src/media/covers/" . basename($target);
    }
}

/* --------------------------
   Year-Handling & Altersvorschlag
   -------------------------- */

// Sicher eine 4-stellige Jahreszahl extrahieren (oder null)
function extract_year_or_null($value) {
    if (!is_string($value)) return null;
    if (preg_match('/\b(\d{4})\b/', $value, $m)) {
        $y = (int)$m[1];
        // typischer YEAR-Bereich in MySQL: 1901–2155
        if ($y >= 1901 && $y <= 2155) return $y;
    }
    return null;
}

/* --------------------------
   Status-Variablen
   -------------------------- */

$bookData = null;
$errorMessage = null;
$successMessage = null;
$isbnInput = '';
$barcodeInput = '';
$candidates = [];
$selectedCover = '';
$saveLocal = true;

/* --------------------------
   Helpers
   -------------------------- */

function checkBarcodeExists($conn, $barcode) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE barcode = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// Metadaten via Google Books (Titel/Autor/Verlag/Jahr/Descr/Cats)
function searchBooksMeta($isbn) {
    $apiKey = getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn);
    if ($apiKey) $url .= "&key=$apiKey";

    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === FALSE) {
        return ['mindestalter' => 'Unbekannt'];
    }

    $data = json_decode($response, true);
    $vi = $data['items'][0]['volumeInfo'] ?? null;

    if ($vi) {
        // Altersvorschlag (falls Funktion vorhanden)
        $age = null;
        if (function_exists('propose_min_age')) {
            $metaForAge = [
                'titel'        => $vi['title']        ?? '',
                'untertitel'   => $vi['subtitle']     ?? '',
                'beschreibung' => $vi['description']  ?? '',
                'categories'   => $vi['categories']   ?? [],
                'publisher'    => $vi['publisher']    ?? '',
            ];
            $age = propose_min_age($metaForAge);
        }

        // Jahr bereinigen
        $pd = $vi['publishedDate'] ?? '';
        $yr = extract_year_or_null($pd);

        return [
            'titel'            => $vi['title'] ?? 'Nicht verfügbar',
            'autor'            => implode(', ', $vi['authors'] ?? ['Nicht verfügbar']),
            'erscheinungsjahr' => $yr ? (string)$yr : '',
            'verlag'           => $vi['publisher'] ?? 'Nicht verfügbar',
            'bildlink'         => $vi['imageLinks']['thumbnail'] ?? '',
            'mindestalter'     => $age !== null ? (string)$age : 'Unbekannt',
        ];
    }
    return ['mindestalter' => 'Unbekannt'];
}

/* --------------------------
   Flow: Buch suchen
   -------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['add_book'])) {
    if (!empty($_POST['isbn'])) {
        $isbn = isbn_clean($_POST['isbn']);
        $isbnInput = $isbn;

        // Metadaten laden
        $bookData = searchBooksMeta($isbn);

        // Cover-Kandidaten
        $candidates = cover_candidates_for_isbn($isbn);
        if (empty($candidates) && !empty($bookData['titel'])) {
            $candidates = cover_candidates_for_meta($bookData['titel'] ?? '', $bookData['autor'] ?? '');
        }

        // Barcode-Vorschlag erzeugen (ISBN_001 … _999)
        $barcodeInput = $isbn . '_001';
        if (checkBarcodeExists($conn, $barcodeInput)) {
            for ($i = 2; $i <= 999; $i++) {
                $nb = $isbn . '_' . str_pad($i, 3, '0', STR_PAD_LEFT);
                if (!checkBarcodeExists($conn, $nb)) { $barcodeInput = $nb; break; }
            }
            $errorMessage = "Der vorgeschlagene Barcode war bereits vergeben. Neuer Vorschlag: $barcodeInput";
        }
    }
}

/* --------------------------
   Flow: Buch speichern
   -------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_book'])) {
    $isbn = isbn_clean($_POST['isbn']);
    $barcodeInput = trim($_POST['barcode']);
    $saveLocal = isset($_POST['save_local']);
    $selectedCover = trim($_POST['selected_cover'] ?? '');

    // ggf. Metadaten nachladen (falls direkter POST ohne vorherige Suche)
    if (empty($bookData)) $bookData = searchBooksMeta($isbn);

    // Erscheinungsjahr sicher bestimmen (int oder null)
    $jahrRaw = $_POST['erscheinungsjahr'] ?? '';
    $jahr = extract_year_or_null($jahrRaw);

    // finalen Bildlink bestimmen (Auswahl > Fallback Kandidaten > Meta)
    $bildlink = $bookData['bildlink'] ?? '';
    if ($selectedCover !== '') {
        if ($saveLocal) {
            $local = download_cover_locally($selectedCover, $isbn ?: ($bookData['titel'] ?? 'book'));
            $bildlink = $local ?: $selectedCover;
        } else {
            $bildlink = $selectedCover;
        }
    } else {
        $c = cover_candidates_for_isbn($isbn);
        if (empty($c) && !empty($bookData['titel'])) {
            $c = cover_candidates_for_meta($bookData['titel'] ?? '', $bookData['autor'] ?? '');
        }
        if (!empty($c)) {
            $first = $c[0]['url'];
            $bildlink = $saveLocal
                ? (download_cover_locally($first, $isbn ?: ($bookData['titel'] ?? 'book')) ?: $first)
                : $first;
        }
    }

    if (checkBarcodeExists($conn, $barcodeInput)) {
        $errorMessage = "Der Barcode ist bereits vergeben. Bitte einen anderen Barcode wählen.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO books
             (titel, erscheinungsjahr, verlag, autor, bildlink, mindestalter, ort, barcode, isbn)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // Bind: s i s s s s s s s
        $titel        = $_POST['titel']         ?? '';
        $verlag       = $_POST['verlag']        ?? '';
        $autor        = $_POST['autor']         ?? '';
        $mindestalter = $_POST['mindestalter']  ?? '';
        $standort     = $_POST['standort']      ?? '';
        $stmt->bind_param(
            "sisssssss",
            $titel,          // s
            $jahr,           // i (NULL erlaubt -> SQL NULL)
            $verlag,         // s
            $autor,          // s
            $bildlink,       // s
            $mindestalter,   // s
            $standort,       // s
            $barcodeInput,   // s
            $isbn            // s
        );

        if ($stmt->execute()) {
            $successMessage = "Das Buch wurde erfolgreich hinzugefügt.";
        } else {
            $errorMessage = "Fehler beim Hinzufügen des Buches: " . $stmt->error;
        }
        $stmt->close();
    }
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
      .container-card { max-width: 900px; margin: 20px auto; background:#fff; border:1px solid #ddd; border-radius:12px; padding:16px; box-shadow:0 2px 10px rgba(0,0,0,.08); }
      .cover-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap:12px; margin: 10px 0 6px; }
      .cover-card{ border:1px solid #ddd; border-radius:8px; padding:8px; text-align:center; background:#fff; }
      .cover-card img{ max-width:100%; max-height:160px; display:block; margin:0 auto 6px; }
      .muted{ color:#666; font-size:.9em; }
      .inline{ display:inline-flex; gap:8px; align-items:center; }
      .form-group{ margin-bottom: 10px; text-align:left; }
      .form-group label{ display:block; margin-bottom:4px; }
      .form-control{ width:100%; padding:10px; border:2px solid #d32f2f; border-radius:6px; }
    </style>
</head>
<body>
<div class="container-card">
    <h1>Buch hinzufügen via ISBN</h1>

    <form method="POST" action="">
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
      <script>
        setTimeout(()=>{ window.location.href = 'view_books.php'; }, 1800);
      </script>
    <?php endif; ?>

    <?php if ($bookData): ?>
        <h2>Buchdetails</h2>
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
                <label for="mindestalter">Lesealter (Vorschlag):</label>
                <input type="text" class="form-control" id="mindestalter" name="mindestalter" value="<?php echo htmlspecialchars($bookData['mindestalter'] ?? 'Unbekannt'); ?>">
            </div>
            <div class="form-group">
                <label for="erscheinungsjahr">Erscheinungsjahr:</label>
                <input type="number" class="form-control" id="erscheinungsjahr" name="erscheinungsjahr" min="1901" max="2155" value="<?php echo htmlspecialchars($bookData['erscheinungsjahr'] ?? ''); ?>">
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
                      <div class="muted"><?php echo htmlspecialchars($c['source'] ?? ''); ?></div>
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
  // Popups automatisch ausblenden
  setTimeout(()=>{ document.querySelectorAll('.popup').forEach(p=>p.style.display='none'); }, 6000);
</script>
</body>
</html>
