<?php
// src/admin/loans/api_search_books.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$like = '%' . $q . '%';
$sql = "SELECT b.id, b.titel, b.autor, b.isbn, b.barcode,
               CASE WHEN l.book_id IS NOT NULL THEN 1 ELSE 0 END AS loaned
        FROM books b
        LEFT JOIN loans l ON l.book_id = b.id
        WHERE b.titel LIKE ? OR b.autor LIKE ? OR b.isbn LIKE ? OR b.barcode LIKE ?
        ORDER BY b.titel
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $like, $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'id'      => (int)$row['id'],
        'titel'   => $row['titel'],
        'autor'   => $row['autor'],
        'isbn'    => $row['isbn'],
        'barcode' => $row['barcode'],
        'loaned'  => (bool)$row['loaned'],
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'results' => $results]);
