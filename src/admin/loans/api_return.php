<?php
// src/admin/loans/api_return.php
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

$barcode = trim($_POST['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['ok' => false, 'error' => 'Barcode benötigt']);
    exit;
}

// Loan über Barcode finden
$sql = "SELECT l.loan_id, l.user_id, l.book_id, l.loan_date, b.titel
        FROM loans l
        JOIN books b ON b.id = l.book_id
        WHERE b.barcode = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $barcode);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$loan) {
    echo json_encode(['ok' => false, 'error' => 'Kein aktiver Leihvorgang zu diesem Barcode gefunden']);
    exit;
}

// In Historie schreiben
$ins = $conn->prepare("INSERT INTO historie (loan_id, user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?, NOW())");
$ins->bind_param("iiis", $loan['loan_id'], $loan['user_id'], $loan['book_id'], $loan['loan_date']);
$ins->execute();
$ins->close();

// Loan löschen
$del = $conn->prepare("DELETE FROM loans WHERE loan_id = ?");
$del->bind_param("i", $loan['loan_id']);
$del->execute();
$del->close();

echo json_encode(['ok' => true, 'message' => 'Rückgabe erfasst', 'book' => ['titel' => $loan['titel'], 'barcode' => $barcode]]);
