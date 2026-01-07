<?php
// src/admin/books/api_cover_candidates.php
// Liefert Cover-Kandidaten (Google Books + Open Library) für ein Buch (per id oder isbn)

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/isbn.php';
require_once __DIR__ . '/../../includes/covers.php';

header('Content-Type: application/json; charset=utf-8');

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isbn = trim($_GET['isbn'] ?? '');
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));

$book = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, titel, autor, isbn FROM books WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($book) { $isbn = $isbn ?: ($book['isbn'] ?? ''); }
}

function candidates_by_meta(string $titel, string $autor): array {
    $out = [];

    // Google Books: Suche nach Titel/Autor
    $q = "https://www.googleapis.com/books/v1/volumes?q=intitle:" . rawurlencode($titel);
    if ($autor !== '') $q .= "+inauthor:" . rawurlencode($autor);
    $key = getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    if ($key) $q .= "&key=" . rawurlencode($key);

    $json = @file_get_contents($q);
    if ($json !== false) {
        $data = json_decode($json, true);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $imgs = $it['volumeInfo']['imageLinks'] ?? [];
                foreach (['extraLarge','large','medium','small','thumbnail','smallThumbnail'] as $k) {
                    if (!empty($imgs[$k])) $out[] = ['source'=>'google-meta','url'=>$imgs[$k]];
                }
            }
        }
    }

    // Open Library: Suche, dann cover_i in echte URLs umwandeln
    $ol = "https://openlibrary.org/search.json?title=" . rawurlencode($titel);
    if ($autor !== '') $ol .= "&author=" . rawurlencode($autor);
    $json2 = @file_get_contents($ol);
    if ($json2 !== false) {
        $data2 = json_decode($json2, true);
        if (!empty($data2['docs'])) {
            foreach ($data2['docs'] as $doc) {
                if (!empty($doc['cover_i'])) {
                    $cid = (int)$doc['cover_i'];
                    foreach (['L','M','S'] as $sz) {
                        $out[] = ['source'=>'openlib-meta', 'url'=>"https://covers.openlibrary.org/b/id/{$cid}-{$sz}.jpg"];
                    }
                }
            }
        }
    }

    // Deduplizieren
    $seen = []; $final = [];
    foreach ($out as $c) {
        if (!isset($seen[$c['url']])) { $seen[$c['url']] = 1; $final[] = $c; }
    }
    // Validieren
    $valid = [];
    foreach ($final as $c) {
        if (http_head_ok($c['url'])) $valid[] = $c;
    }
    return $valid;
}

$candidates = [];
if ($isbn !== '') {
    $candidates = cover_candidates_for_isbn($isbn);
}
if (empty($candidates) && $book) {
    $candidates = candidates_by_meta($book['titel'] ?? '', $book['autor'] ?? '');
}

// begrenzen
$candidates = array_slice($candidates, 0, $limit);

echo json_encode([
    'ok' => true,
    'book' => $book,
    'isbn' => $isbn,
    'count' => count($candidates),
    'candidates' => $candidates
]);
