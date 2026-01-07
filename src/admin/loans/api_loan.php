<?php
// src/admin/loans/api_loan.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Ungültige Methode']);
    exit;
}
if (!csrf_validate($_POST['csrf'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'CSRF-Prüfung fehlgeschlagen']);
    exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
$barcode = trim($_POST['barcode'] ?? '');
$loan_weeks = (int)($_POST['loan_weeks'] ?? 4);
if (!in_array($loan_weeks, [1,2,3,4], true)) $loan_weeks = 4;

if ($user_id <= 0 || $barcode === '') {
    echo json_encode(['ok' => false, 'error' => 'Benutzer und Barcode benötigt']);
    exit;
}

// Buch via Barcode finden
$stmt = $conn->prepare("SELECT id, titel FROM books WHERE barcode = ?");
$stmt->bind_param("s", $barcode);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    echo json_encode(['ok' => false, 'error' => 'Kein Buch mit diesem Barcode gefunden']);
    exit;
}

// Bereits ausgeliehen?
$check = $conn->prepare("SELECT 1 FROM loans WHERE book_id = ?");
$check->bind_param("i", $book['id']);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    echo json_encode(['ok' => false, 'error' => 'Das Buch ist bereits ausgeliehen']);
    exit;
}

// Ausleihe anlegen
$loan_date = date('Y-m-d H:i:s');
$return_date = date('Y-m-d H:i:s', strtotime('+' . $loan_weeks . ' weeks'));

$ins = $conn->prepare("INSERT INTO loans (user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?)");
$ins->bind_param("iiss", $user_id, $book['id'], $loan_date, $return_date);

if ($ins->execute()) {
    echo json_encode([
        'ok' => true,
        'message' => 'Ausleihe erfasst',
        'book' => ['id' => $book['id'], 'titel' => $book['titel'], 'barcode' => $barcode],
        'return_date' => $return_date
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => 'DB-Fehler beim Ausleihen']);
}
$ins->close();
