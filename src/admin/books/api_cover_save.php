<?php
// src/admin/books/api_cover_save.php
// Speichert ausgewähltes Cover (optional lokal) in books.bildlink

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/isbn.php';
require_once __DIR__ . '/../../includes/covers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'Ungültige Methode']); exit;
}

$id = (int)($_POST['id'] ?? 0);
$url = trim($_POST['url'] ?? '');
$saveLocal = isset($_POST['save_local']) && $_POST['save_local'] !== 'false';

if ($id <= 0 || $url === '') {
    echo json_encode(['ok'=>false,'error'=>'id und url erforderlich']); exit;
}

// Buch/ISBN laden
$stmt = $conn->prepare("SELECT id, isbn FROM books WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$book) { echo json_encode(['ok'=>false,'error'=>'Buch nicht gefunden']); exit; }

$bildlink = $url;
if ($saveLocal) {
    $fnameIsbn = $book['isbn'] ?: ('book-' . $id);
    $local = download_cover_locally($url, $fnameIsbn);
    if ($local) $bildlink = $local;
}

$up = $conn->prepare("UPDATE books SET bildlink = ? WHERE id = ?");
$up->bind_param("si", $bildlink, $id);
$ok = $up->execute();
$up->close();

echo json_encode(['ok'=>$ok, 'bildlink'=>$bildlink]);
