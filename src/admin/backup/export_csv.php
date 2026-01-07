<?php
// src/admin/backup/export_csv.php
session_start();
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('forbidden'); }

$table = $_GET['table'] ?? '';
if ($table === '') { http_response_code(400); exit('table required'); }

// primitive Whitelist: existiert Tabelle?
$exists = false;
$rst = $conn->query("SHOW TABLES");
while ($r = $rst->fetch_array()) { if ($r[0] === $table) { $exists = true; break; } }
if (!$exists) { http_response_code(404); exit('unknown table'); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd_His') . '.csv' . '"');

$out = fopen('php://output', 'w');

// UTF-8 BOM (damit Excel Umlaute korrekt erkennt)
fwrite($out, "\xEF\xBB\xBF");

$res = $conn->query("SELECT * FROM `{$table}`");
if ($res) {
    // Header
    $headers = [];
    $fields = $res->fetch_fields();
    foreach ($fields as $f) { $headers[] = $f->name; }
    fputcsv($out, $headers, ';');

    // Rows
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, array_values($row), ';');
    }
}
fclose($out);
