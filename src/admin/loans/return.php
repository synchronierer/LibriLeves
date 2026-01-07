<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate($_POST['csrf'] ?? null)) {
    $_SESSION['message'] = "Ungültige Anfrage (CSRF).";
    header("Location: ausleihe.php");
    exit();
}

$loan_id = (int)($_POST['loan_id'] ?? 0);
if ($loan_id <= 0) {
    $_SESSION['message'] = "Ungültige Loan-ID.";
    header("Location: ausleihe.php");
    exit();
}

// Daten aus loans holen
$sql = "SELECT user_id, book_id, loan_date FROM loans WHERE loan_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $loan = $result->fetch_assoc();

    // In Historie schreiben
    $insertSql = "INSERT INTO historie (loan_id, user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?, NOW())";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("iiis", $loan_id, $loan['user_id'], $loan['book_id'], $loan['loan_date']);
    $insertStmt->execute();

    // Aus loans löschen
    $deleteSql = "DELETE FROM loans WHERE loan_id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("i", $loan_id);
    $deleteStmt->execute();

    $_SESSION['message'] = "Ausleihe erfolgreich beendet und archiviert.";
} else {
    $_SESSION['message'] = "Keine Ausleihe mit dieser ID gefunden.";
}

header("Location: ausleihe.php");
exit();
